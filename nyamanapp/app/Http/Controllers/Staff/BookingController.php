<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Concerns\HandlesRoomCalendar;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Report;
use App\Models\Room;
use App\Support\FotoKompresor;
use App\Support\PindahKamar;
use App\Support\PindahKamarHarian;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    /**
     * Sejauh mana booking boleh dimundurkan tanggalnya.
     *
     * Tamu yang datang dini hari — misal jam 1 pagi tanggal 25 — sebenarnya
     * memakai kamar untuk malam tanggal 24, dan check-out siang tanggal 25.
     * Tanpa ini, malam itu tidak bisa dicatat sama sekali.
     *
     * Batasnya tetap ada supaya salah ketik bulan atau tahun tidak lolos
     * diam-diam menjadi booking di masa lalu yang jauh.
     */
    public const MUNDUR_MAKS_HARI = 7;

    private static function batasMundur(): string
    {
        return now()->subDays(self::MUNDUR_MAKS_HARI)->toDateString();
    }

    use HandlesRoomCalendar;

    /**
     * Halaman kamar staff: tombol tambah, kalender bulanan,
     * lalu daftar booking (per grup) untuk tanggal terpilih.
     */
    public function index(Request $request)
    {
        [$month, $selected] = $this->monthAndSelected($request);
        [$cari, $hasilCari] = $this->hasilPencarian($request);

        return view('staff.rooms.index', [
            'cari'          => $cari,
            'hasilCari'     => $hasilCari,
            'alarm'         => \App\Support\AlarmCheckout::kumpulkan(),
            'month'         => $month,
            'selected'      => $selected,
            'terisiPerHari' => $this->terisiPerHari($month),
            'prevMonth'     => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth'     => $month->copy()->addMonth()->format('Y-m'),
        ] + $this->dayData($selected));
    }

    /**
     * Panel detail satu tanggal (dipakai kalender via fetch).
     */
    public function day(string $tanggal)
    {
        $selected = Carbon::createFromFormat('Y-m-d', $tanggal)->startOfDay();

        return view('staff.rooms._day', ['selected' => $selected] + $this->dayData($selected));
    }

    /**
     * Form tambah booking: pilih beberapa kamar sekaligus (checkbox),
     * data tamu diisi sekali. Hanya untuk hari ini / tanggal ke depan.
     */
    public function create()
    {
        [$checkIn, $durasi] = $this->initialRange();

        return view('staff.rooms.create', [
            'roomsByTipe' => $this->roomsByTipe(),
            'occupiedIds' => $this->occupiedRoomIds($checkIn, $checkIn->copy()->addDays($durasi)),
        ]);
    }

    /**
     * Simpan booking baru: satu record per kamar, ULID grup yang sama.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedMulti($request);

        // Bukti pembayaran diperiksa lebih dulu supaya staff tidak harus
        // membetulkan hal lain hanya untuk menemukan fotonya juga kurang.
        $this->validasiBuktiBayarAwal(
            $request,
            (string) ($data['status_bayar'] ?? Booking::BAYAR_BELUM),
            (string) $data['sumber'],
        );

        [$checkIn, $checkOut, $malam] = $this->rangeFrom($data);

        $roomIds = array_values(array_unique(array_map('intval', $data['room_ids'])));
        $rooms   = Room::whereIn('id', $roomIds)->get()->keyBy('id');

        $breakfast = (bool) ($data['breakfast'] ?? false);

        // Kamar family tidak dijual tanpa sarapan — dijaga di server, bukan
        // hanya dikunci di peramban.
        if (! $breakfast) {
            $wajibBf = $rooms->only($roomIds)->reject->bolehTanpaBreakfast()->pluck('nomor');

            if ($wajibBf->isNotEmpty()) {
                return back()->withInput()->withErrors([
                    'breakfast' => 'Kamar ' . $wajibBf->implode(', ') . ' (Family) wajib dengan sarapan. '
                        . 'Pilih "Dengan Breakfast" atau ganti tipe kamarnya.',
                ]);
            }
        }

        // Semua kamar harus bebas bentrok terlebih dahulu (all-or-nothing).
        $bentrok = [];
        foreach ($roomIds as $id) {
            if ($this->bentrok($id, $checkIn, $checkOut)) {
                $bentrok[] = $rooms[$id]->nomor ?? ('#' . $id);
            }
        }

        if ($bentrok !== []) {
            return back()->withInput()->withErrors([
                'room_ids' => 'Kamar ' . implode(', ', $bentrok) . ' sudah dibooking pada rentang '
                    . $checkIn->translatedFormat('j M Y') . ' – ' . $checkOut->translatedFormat('j M Y')
                    . '. Pilih kamar atau tanggal lain.',
            ]);
        }

        // Diskon berlaku untuk SATU PESANAN, jadi nominalnya diperiksa
        // terhadap total seluruh kamar yang dicentang — bukan per kamar.
        $total = $this->totalTagihan($rooms->only($roomIds), $malam, $breakfast);
        [$diskon, $persen] = $this->diskonFinal($data, $total);

        if ($galat = $this->galatDiskon($diskon, $total)) {
            return back()->withInput()->withErrors(['diskon_nominal' => $galat]);
        }

        $statusBayar = $this->statusBayarFinal($data);
        $dp          = $this->dpFinal($data, $statusBayar);

        // DP diuji terhadap tagihan SETELAH diskon — itulah angka yang
        // sebenarnya harus dibayar tamu.
        if ($galat = $this->galatDp($statusBayar, $dp, $total - $diskon)) {
            return back()->withInput()->withErrors(['dp_nominal' => $galat]);
        }

        // Foto disimpan SETELAH seluruh pemeriksaan lolos, supaya formulir
        // yang ditolak tidak meninggalkan berkas yatim di disk.
        $fotoBayarAwal = ($request->hasFile('foto_bayar_awal')
            && Booking::butuhBuktiBayarAwal($statusBayar, $data['sumber']))
            ? $this->simpanFoto($request->file('foto_bayar_awal'), 'bayar')
            : null;

        $grup = (string) Str::ulid();

        foreach ($roomIds as $id) {
            Booking::create([
                'room_id'    => $id,
                'grup'       => $grup,
                'nama_tamu'  => $data['nama_tamu'],
                'telepon'    => $data['telepon'],
                'breakfast'    => $breakfast,
                'sumber'       => $data['sumber'],
                'status_bayar' => $statusBayar,
                'dp_nominal'     => $dp,
                'diskon_nominal' => $diskon,
                'diskon_persen'  => $persen,
                'foto_bayar_awal' => $fotoBayarAwal,
                'check_in'     => $checkIn,
                'durasi'       => $malam,
                'check_out'    => $checkOut,
                'created_by'   => Auth::id(),
            ]);
        }

        $daftar = $rooms->only($roomIds)->pluck('nomor')->implode(', ');

        return redirect()
            ->route($this->ruteIndex(), ['tanggal' => $checkIn->toDateString(), 'bulan' => $checkIn->format('Y-m')])
            ->with('success', count($roomIds) . ' kamar (' . $daftar . ') berhasil dibooking untuk '
                . $data['nama_tamu'] . '.'
                . ($diskon > 0
                    ? ' Diskon ' . ($persen > 0 ? $persen . '% (' . Booking::rupiah($diskon) . ')' : Booking::rupiah($diskon))
                      . ' diberikan — tagihan menjadi ' . Booking::rupiah(max(0, $total - $diskon)) . '.'
                    : '')
                . ($statusBayar === Booking::BAYAR_DP
                    ? ' DP ' . Booking::rupiah($dp) . ' tercatat — sisa '
                      . Booking::rupiah(max(0, $total - $diskon - $dp)) . ' ditagih saat tamu tiba.'
                    : '')
                . ' Tekan "Tamu Check-in" saat tamu tiba.');
    }

    /**
     * Form ubah booking. Data tamu & tanggal berlaku untuk seluruh grup;
     * pilihan kamar hanya untuk baris booking ini.
     */
    /** Route index tujuan redirect setelah ubah/hapus — dioverride admin. */
    protected function ruteIndex(): string
    {
        return 'staff.rooms.index';
    }

    /** View halaman edit. Route edit kini hanya terdaftar di sisi admin. */
    protected function viewEdit(): string
    {
        return 'admin.rooms.edit';
    }

    public function edit(Booking $booking)
    {
        [$checkIn, $durasi] = $this->initialRange($booking);
        $anggota  = $booking->groupMembers()->with('room')->get();
        $groupIds = $anggota->pluck('id')->all();

        // Ketersediaan dihitung terhadap SEMUA tamu lain, termasuk kamar lain
        // dalam pesanan yang sama — dua baris satu pesanan di satu kamar pada
        // malam yang sama tetap bentrok.
        $abaikan = [$booking->id];

        // Total kamar LAIN dalam pesanan ini, masing-masing dengan malam dan
        // sarapannya sendiri. Formulir ubah hanya memegang satu kamar,
        // sedangkan diskon dan tagihan berlaku untuk seluruh pesanan. Dulu
        // yang dikirim tarif per malam lalu dikali malam kamar INI — salah
        // begitu masa menginap antar kamar berbeda.
        $totalLain = (int) $anggota
            ->where('id', '!=', $booking->id)
            ->sum(fn (Booking $b) => $b->totalKamar());

        return view($this->viewEdit(), [
            'booking'     => $booking,
            'groupCount'  => count($groupIds),
            'totalLain'   => $totalLain,
            'roomsByTipe' => $this->roomsByTipe(),
            'occupiedIds' => $this->occupiedRoomIdsIgnoring($checkIn, $checkIn->copy()->addDays($durasi), $abaikan),
            'penghuni'    => $this->penghuniKamar($checkIn, $checkIn->copy()->addDays($durasi), $abaikan),
            // Bukti bayar dari pintu mana pun (awal, pelunasan check-in, atau
            // tambahan) dianggap sudah ada.
            'adaBukti'    => Booking::adaBuktiBayarDi($anggota),
            'buktiUrl'    => $this->urlBuktiBayar($anggota),
            'dibayarLama' => $booking->dibayar(Booking::totalPesananDari($anggota)),
            'pindahDari'  => $booking->pindahDari()->with('room')->first(),
            'pindahKe'    => $booking->pindahKe()->with('room')->first(),
        ]);
    }

    /**
     * Simpan perubahan.
     *
     * Pembagiannya disengaja dan inilah bagian terpentingnya:
     *
     *   PER BARIS KAMAR   kamar, tanggal menginap, durasi, sarapan
     *   SELURUH PESANAN   nama tamu, telepon, asal, status bayar, DP, diskon
     *
     * Sebelumnya tanggal ikut disalin ke seluruh grup, sehingga kamar 101 yang
     * diperpanjang semalam DIAM-DIAM ikut memperpanjang 103 — kamar yang
     * tamunya sudah pulang tetap terhitung terisi, dan hunian malam itu salah
     * tanpa ada yang tahu. Sekarang tiap kamar punya masa menginapnya sendiri.
     *
     * Yang tetap satu untuk seluruh pesanan adalah IDENTITAS dan UANG: satu
     * tamu, satu tagihan. Total tagihannya dihitung dari masing-masing kamar,
     * jadi memperpanjang satu kamar otomatis menaikkan tagihan pesanannya.
     */
    public function update(Request $request, Booking $booking): RedirectResponse
    {
        $data = $this->validatedSingle($request);

        $members  = $booking->groupMembers()->with('room')->get();
        $ignore   = $members->pluck('id')->all();
        $adaBukti = Booking::adaBuktiBayarDi($members);

        // Bukti bayar AWAL hanya diminta bila pesanan ini belum punya bukti
        // apa pun. Pesanan yang dilunasi saat check-in sudah punya buktinya
        // (bukti pelunasan) — memindah kamarnya tidak boleh memintanya lagi.
        $this->validasiBuktiBayarAwal(
            $request,
            (string) ($data['status_bayar'] ?? Booking::BAYAR_BELUM),
            (string) $data['sumber'],
            $adaBukti,
        );

        [$checkIn, $checkOut, $malam] = $this->rangeFrom($data);

        // Kamar hasil pindah per hari bersambung dengan kamar sebelum atau
        // sesudahnya. Menggeser tanggal di sambungan itu akan membuat malam
        // yang bolong atau dobel, jadi sambungannya dikunci.
        if ($booking->isPindahan() && ! $checkIn->isSameDay($booking->check_in)) {
            return back()->withInput()->withErrors([
                'check_in' => 'Kamar ini lanjutan pindah dari kamar ' . ($booking->pindahDari?->room?->nomor ?? 'sebelumnya')
                    . ', jadi tanggal check-in-nya terkunci di ' . $booking->check_in->translatedFormat('j M Y') . '.',
            ]);
        }

        if ($booking->punyaLanjutan() && ! $checkOut->isSameDay($booking->check_out)) {
            return back()->withInput()->withErrors([
                'check_out' => 'Tamu pindah dari kamar ini ke kamar ' . ($booking->pindahKe?->room?->nomor ?? 'lain')
                    . ' mulai ' . $booking->check_out->translatedFormat('j M Y')
                    . ', jadi tanggal check-out-nya terkunci. Perpanjang di kamar lanjutannya.',
            ]);
        }

        $room = Room::findOrFail($data['room_id']);

        $breakfast = (bool) ($data['breakfast'] ?? false);

        // Sarapan kini melekat pada BARIS KAMAR INI saja, jadi yang diperiksa
        // cukup kamarnya sendiri. Kamar family di baris lain tidak lagi
        // memaksa kamar king pada pesanan yang sama ikut membeli sarapan.
        if (! $breakfast && ! $room->bolehTanpaBreakfast()) {
            return back()->withInput()->withErrors([
                'breakfast' => 'Kamar ' . $room->nomor . ' (Family) wajib dengan sarapan.',
            ]);
        }

        // Hanya kamar baris ini yang pindah tanggal, jadi hanya kamar ini yang
        // perlu diperiksa. Kamar lain segrup tetap pada tanggalnya masing-
        // masing dan tidak ikut bergerak.
        //
        // Kamar tujuan yang sedang terisi TIDAK lagi otomatis ditolak: bila
        // penghuninya satu tamu dan ia muat di kamar asal, keduanya bertukar
        // kamar (lihat PindahKamar). Yang diabaikan tetap SELURUH grup: kalau
        // tidak, memperpanjang 101 sehari akan dianggap bentrok dengan
        // barisnya sendiri.
        $rencana = PindahKamar::rencanakan($booking, $room, $checkIn, $checkOut);

        if (! $rencana->bisa()) {
            return back()->withInput()->withErrors(['room_id' => $rencana->galat]);
        }

        // Total pesanan SETELAH perubahan.
        //
        // Kamar lain segrup memakai TANGGAL DAN SARAPANNYA SENDIRI — sejak
        // masa menginap bisa berbeda per kamar, mengalikan seluruh grup dengan
        // durasi baris ini akan melaporkan tagihan yang tidak pernah ada.
        $totalLain = (int) $members
            ->where('id', '!=', $booking->id)
            ->sum(fn (Booking $m) => $m->totalKamar());

        $total = $room->hargaPerMalam($breakfast) * max(1, $malam) + $totalLain;

        [$diskon, $persen] = $this->diskonFinal($data, $total);

        if ($galat = $this->galatDiskon($diskon, $total)) {
            return back()->withInput()->withErrors(['diskon_nominal' => $galat]);
        }

        $statusBayar = $this->statusBayarFinal($data);
        $dp          = $this->dpFinal($data, $statusBayar);

        if ($galat = $this->galatDp($statusBayar, $dp, $total - $diskon)) {
            return back()->withInput()->withErrors(['dp_nominal' => $galat]);
        }

        // ---- Bukti pembayaran TAMBAHAN ----
        // Diminta hanya bila uang yang diakui sudah diterima BERTAMBAH —
        // biasanya karena upgrade kamar atau tambah malam pada pesanan yang
        // tetap ditandai lunas. Pindah ke kamar setarif tidak menambah uang
        // apa pun, jadi tidak meminta bukti apa pun.
        $dibayarLama = $booking->dibayar(Booking::totalPesananDari($members));
        $dibayarBaru = Booking::dibayarUntuk($statusBayar, $dp, max(0, $total - $diskon));
        $tiket       = $data['sumber'] === Booking::SUMBER_TIKET;
        $perluTambah = $dibayarBaru > $dibayarLama && ($adaBukti || $tiket);

        $request->validate([
            'foto_bayar_tambahan' => ($perluTambah ? 'required' : 'nullable') . '|image|mimes:jpg,jpeg,png|max:8192',
        ], [
            'foto_bayar_tambahan.required' => $tiket
                ? 'Tagihan naik ' . Booking::rupiah($dibayarBaru - $dibayarLama) . ' dari yang sudah dibayar lewat Tiket.com. '
                    . 'Selisihnya dibayar di hotel — unggah bukti pembayarannya.'
                : 'Uang yang diterima naik ' . Booking::rupiah($dibayarBaru - $dibayarLama) . ' dibanding sebelumnya '
                    . '(upgrade kamar atau tambah malam). Unggah bukti pembayaran selisihnya, '
                    . 'atau pilih "DP" bila tamu belum membayar selisih itu.',
            'foto_bayar_tambahan.image' => 'Bukti pembayaran tambahan harus berupa gambar.',
            'foto_bayar_tambahan.mimes' => 'Bukti pembayaran tambahan harus berformat JPG atau PNG.',
            'foto_bayar_tambahan.max'   => 'Ukuran bukti pembayaran tambahan maksimal 8 MB.',
        ]);

        $fotoTambahan = $booking->foto_bayar_tambahan;
        if ($perluTambah && $request->hasFile('foto_bayar_tambahan')) {
            $lama         = $fotoTambahan;
            $fotoTambahan = $this->simpanFoto($request->file('foto_bayar_tambahan'), 'bayar');

            if ($lama && ! Booking::where('foto_bayar_tambahan', $lama)->where('id', '!=', $booking->id)->exists()) {
                Storage::disk('local')->delete($lama);
            }
        }

        // Bukti bayar baru menggantikan yang lama; berkas lama dihapus supaya
        // tidak menumpuk tanpa pemilik.
        $fotoBayarAwal = $booking->foto_bayar_awal;
        if ($request->hasFile('foto_bayar_awal')
            && Booking::butuhBuktiBayarAwal($statusBayar, $data['sumber'])) {
            $baru = $this->simpanFoto($request->file('foto_bayar_awal'), 'bayar');

            if ($fotoBayarAwal) {
                $dipakaiLain = Booking::where('foto_bayar_awal', $fotoBayarAwal)
                    ->whereNotIn('id', $ignore)->exists();
                if (! $dipakaiLain) {
                    Storage::disk('local')->delete($fotoBayarAwal);
                }
            }

            $fotoBayarAwal = $baru;
        }

        DB::transaction(function () use ($rencana, $members, $data, $breakfast, $checkIn, $malam, $checkOut, $statusBayar, $dp, $diskon, $persen, $fotoBayarAwal, $fotoTambahan) {
            // ---- PER BARIS KAMAR: kamar, tanggal, durasi, sarapan ----
            // Bila kamar tujuan terisi, penghuninya ikut digeser ke kamar asal
            // di langkah yang sama — dua tamu bertukar kamar dalam satu transaksi.
            $rencana->terapkan([
                'breakfast' => $breakfast,
                'check_in'  => $checkIn,
                'durasi'    => $malam,
                'check_out' => $checkOut,
                'foto_bayar_tambahan' => $fotoTambahan,
            ]);

            // ---- SELURUH PESANAN: identitas tamu dan uangnya ----
            foreach ($members as $m) {
                $m->update([
                    'nama_tamu' => $data['nama_tamu'],
                    'telepon'   => $data['telepon'],
                    'sumber'       => $data['sumber'],
                    'status_bayar' => $statusBayar,
                    'dp_nominal'     => $dp,
                    'diskon_nominal' => $diskon,
                    'diskon_persen'  => $persen,
                    'foto_bayar_awal' => $fotoBayarAwal,
                ]);
            }
        });

        $suffix = $members->count() > 1
            ? ' Tanggal & sarapan berlaku untuk kamar ' . $room->nomor . ' saja; '
              . 'data tamu dan pembayaran berlaku untuk ' . $members->count() . ' kamar segrup.'
            : '';

        if ($rencana->menukar()) {
            $suffix .= ' ' . $rencana->keteranganTukar();
        }

        if ($dibayarLama > $dibayarBaru && $statusBayar === Booking::BAYAR_LUNAS) {
            $suffix .= ' Tagihan turun di bawah yang sudah dibayar: tamu kelebihan bayar '
                . Booking::rupiah($dibayarLama - $dibayarBaru) . ' — kembalikan atau catat sebagai deposit.';
        }

        return redirect()
            ->route($this->ruteIndex(), ['tanggal' => $checkIn->toDateString(), 'bulan' => $checkIn->format('Y-m')])
            ->with('success', 'Booking kamar ' . $room->nomor . ' — ' . $data['nama_tamu']
                . ' berhasil diperbarui.' . $suffix);
    }

    /**
     * Halaman "Tamu Check-in": unggah foto KTP — berlaku utk seluruh grup.
     */
    public function checkin(Booking $booking)
    {
        $total    = $booking->totalPesanan();
        $pindahan = $booking->isPindahan();
        $calon    = $this->calonCheckin($booking);

        return view('staff.rooms.checkin', [
            'calon'        => $calon,
            'terpilih'     => request()->query('scope') === 'one' ? [$booking->id] : $calon->pluck('id')->all(),
            'booking'      => $booking,
            'menitAwal'    => $booking->menitEarlyCheckinPada(now()),
            'butuhBayar'   => $this->butuhBayarCheckin($booking, $total, now()),
            'groupRooms'   => $this->nomorKamar($calon),
            'pindahDari'   => $pindahan ? $booking->pindahDari()->with('room')->first() : null,
            'totalKamar'   => $total,
            'diskon'       => $booking->diskonPesanan(),
            'totalTagihan' => $booking->tagihanPesanan($total),
            'sudahDibayar' => $booking->dibayar($total),
            'sisaBayar'    => $booking->sisaBayar($total),
        ]);
    }

    /**
     * Proses check-in untuk kamar yang dipilih.
     *
     * Satu pesanan bisa datang bertahap: tamu kamar 209 tiba lebih dulu, tamu
     * 210 menyusul dengan KTP sendiri. Foto dan waktu hanya ditulis ke kamar
     * yang dipilih, supaya kamar yang sudah masuk tidak tertimpa.
     */
    public function checkinStore(Request $request, Booking $booking): RedirectResponse
    {
        $sekarang   = now();
        $pindahan   = $booking->isPindahan();
        $members    = $this->kamarDipilih($request, $this->calonCheckin($booking));

        if ($members->isEmpty()) {
            return back()->withErrors(['kamar' => 'Pilih minimal satu kamar yang check-in.']);
        }

        $awal       = (int) $members->max(fn (Booking $m) => $m->menitEarlyCheckinPada($sekarang));
        $wajibFoto  = $members->contains(fn (Booking $m) => $m->foto_ktp === null);
        // Bukti pelunasan diminta bila tagihan belum lunas ATAU tamu datang
        // lebih awal (early check-in menambah jam pakai kamar). Untuk kamar
        // pindahan: hanya bila masih ada sisa tagihan (misalnya upgrade).
        $total      = $booking->totalPesanan();
        $wajibBayar = $members->contains(fn (Booking $m) => $this->butuhBayarCheckin($m, $total, $sekarang));

        $request->validate([
            'foto_ktp'       => ($wajibFoto ? 'required' : 'nullable') . '|image|mimes:jpg,jpeg,png|max:8192',
            'foto_pelunasan' => ($wajibBayar ? 'required' : 'nullable') . '|image|mimes:jpg,jpeg,png|max:8192',
        ], [
            'foto_ktp.required'       => 'Foto KTP wajib diunggah saat check-in.',
            'foto_ktp.image'          => 'Foto KTP harus berupa gambar.',
            'foto_ktp.mimes'          => 'Foto KTP harus berformat JPG atau PNG.',
            'foto_ktp.max'            => 'Ukuran foto KTP maksimal 8 MB.',
            'foto_pelunasan.required' => 'Bukti pelunasan wajib diunggah.',
            'foto_pelunasan.image'    => 'Bukti pelunasan harus berupa gambar.',
            'foto_pelunasan.mimes'    => 'Bukti pelunasan harus berformat JPG atau PNG.',
            'foto_pelunasan.max'      => 'Ukuran bukti pelunasan maksimal 8 MB.',
        ]);

        $sudahCheckin = $booking->isCheckedIn();

        // Mengganti foto tamu yang sudah masuk: hanya kamar yang memakai foto
        // yang sama, bukan kamar lain yang masuk dengan KTP berbeda.
        if ($sudahCheckin && $booking->foto_ktp) {
            $members = $booking->groupMembers()->with('room')->where('foto_ktp', $booking->foto_ktp)->get();
        }

        $fotoPath = $members->first()->foto_ktp;
        if ($request->hasFile('foto_ktp')) {
            // Berkas lama hanya dihapus bila tidak dipakai baris lain — kamar
            // pindahan dan kamar lain dalam pesanan berbagi foto KTP yang sama.
            if ($fotoPath && ! Booking::where('foto_ktp', $fotoPath)->whereNotIn('id', $members->pluck('id'))->exists()) {
                Storage::disk('local')->delete($fotoPath);
            }
            $fotoPath = $this->simpanFoto($request->file('foto_ktp'), 'ktp');
        }

        // Bukti pelunasan BARU yang diunggah sekarang. Menyimpan ulang foto KTP
        // tidak boleh ikut "melunasi" pesanan hanya karena dulu pernah ada
        // bukti pelunasan — tagihannya bisa saja sudah naik sejak itu.
        $bayarBaru = $request->hasFile('foto_pelunasan')
            ? $this->simpanFoto($request->file('foto_pelunasan'), 'bayar')
            : null;

        DB::transaction(function () use ($members, $fotoPath, $booking, $pindahan, $bayarBaru, $sudahCheckin, $sekarang) {
            foreach ($members as $m) {
                $m->foto_ktp = $fotoPath;

                if ($m->checked_in_at === null) {
                    $m->checkin_menit_awal = $m->menitEarlyCheckinPada($sekarang);
                    $m->checked_in_at      = $sekarang;
                }
                $m->save();
            }

            // Pembayaran berlaku untuk SELURUH PESANAN, bukan hanya kamar yang
            // sedang di-check-in — status bayar adalah milik pesanannya.
            if ($bayarBaru !== null) {
                $anggota = $booking->groupMembers()->get();
                $lama    = $pindahan ? null : $booking->foto_pelunasan;

                foreach ($anggota as $g) {
                    if (! $pindahan) {
                        $g->foto_pelunasan = $bayarBaru;
                    }
                    $g->status_bayar = Booking::BAYAR_LUNAS;
                    $g->save();
                }

                if ($pindahan) {
                    $booking->forceFill(['foto_bayar_tambahan' => $bayarBaru])->save();
                } elseif ($lama && $lama !== $bayarBaru) {
                    Storage::disk('local')->delete($lama);
                }
            }

            // Tamu pindah: kamar lamanya selesai dipakai sekarang.
            if ($pindahan && ! $sudahCheckin && $booking->pindahDari) {
                PindahKamarHarian::tutupKamarLama($booking->pindahDari, Auth::id(), $sekarang);
            }
        });

        $nomor = $members->map(fn ($m) => $m->room?->nomor)->filter()->sort()->implode(', ');

        if ($pindahan && ! $sudahCheckin) {
            return redirect()
                ->route($this->ruteIndex(), ['tanggal' => $booking->check_in->toDateString(), 'bulan' => $booking->check_in->format('Y-m')])
                ->with('success', 'Tamu ' . $booking->nama_tamu . ' pindah ke kamar ' . $nomor
                    . ' — kamar ' . ($booking->pindahDari?->room?->nomor ?? 'sebelumnya') . ' ditutup.'
                    . ($bayarBaru !== null ? ' Sisa tagihan dilunasi.' : ''));
        }

        $catatanAwal = ($awal > 0 && ! $sudahCheckin)
            ? ' Early check-in ' . Booking::labelDurasi($awal) . ' tercatat.'
            : '';

        // Kamar lain dalam pesanan yang belum datang tetap menunggu.
        $menunggu = $this->nomorKamar($booking->groupMembers()->with('room')->get()
            ->filter(fn (Booking $m) => ! $m->isCheckedIn() && ! $m->isCheckedOut() && ! $m->isPindahan()));

        if (! $sudahCheckin && $menunggu->isNotEmpty()) {
            $catatanAwal .= ' Kamar ' . $menunggu->implode(', ') . ' belum check-in.';
        }

        return redirect()
            ->route($this->ruteIndex(), ['tanggal' => $booking->check_in->toDateString(), 'bulan' => $booking->check_in->format('Y-m')])
            ->with('success', $sudahCheckin
                ? 'Berkas check-in kamar ' . $nomor . ' diperbarui.'
                : 'Tamu ' . $booking->nama_tamu . ' check-in — kamar ' . $nomor . '.' . $catatanAwal);
    }

    /**
     * Halaman check-out: kartu kembali, kondisi kamar, dan (bila telat)
     * bukti pembayaran kelebihan jam.
     */
    public function checkout(Booking $booking)
    {
        abort_unless($booking->isCheckedIn(), 404);
        abort_if($booking->isCheckedOut(), 404);

        $calon = $this->calonCheckout($booking);
        $pilih = array_filter(array_map('intval', explode(',', (string) request()->query('pilih'))));

        return view('staff.rooms.checkout', [
            'booking'    => $booking,
            'calon'      => $calon,
            'terpilih'   => request()->query('scope') === 'one'
                ? [$booking->id]
                : ($pilih !== [] ? $pilih : $calon->pluck('id')->all()),
            'sisaBayar'  => $booking->sisaBayar($booking->totalPesanan()),
            // Kamar yang tetap aktif walau semua kamar di halaman ini keluar
            // (mis. belum check-in): sisa tagihan menunggu kamar terakhir.
            'kamarLain'  => $this->kamarTersisa($booking, $calon)->count(),
            'groupRooms' => $this->nomorKamar($calon),
        ]);
    }

    /**
     * Tandai pesanan yang lewat jam 12.00 sebagai late check-out.
     *
     * Dipanggil dari kotak pengingat check-out. Statusnya sendiri sudah dihitung
     * otomatis dari waktu, jadi tombol ini tidak menentukan apa-apa soal
     * tagihan — yang dicatat adalah SIAPA yang melihat peringatan itu dan
     * KAPAN, supaya kamar yang lewat jam tidak bisa didiamkan diam-diam.
     */
    public function tandaiLate(Booking $booking): RedirectResponse
    {
        abort_unless($booking->lateBerjalan(), 404);

        // Hanya kamar yang memang lewat jam: tamu lain dalam pesanan yang sama
        // bisa saja sudah pulang tepat waktu atau masa sewanya masih berjalan.
        $kamar = $booking->groupMembers()->with(['room', 'pindahKe'])->get()
            ->filter(fn (Booking $m) => $m->lateBerjalan());

        foreach ($kamar as $m) {
            if ($m->late_ditandai_at === null) {
                $m->forceFill([
                    'late_ditandai_at' => now(),
                    'late_ditandai_by' => Auth::id(),
                ])->save();
            }
        }

        return back()->with('success',
            'Kamar ' . $this->nomorKamar($kamar)->implode(', ')
            . ' ditandai late check-out. Bukti pembayaran kelebihan jam wajib diunggah saat check-out.');
    }

    /**
     * Proses check-out untuk kamar yang dipilih.
     *
     * Status dan biaya late dihitung PER KAMAR: dalam satu pesanan, tamu 209
     * bisa pulang tepat waktu sementara tamu 210 late check-out. Sisa tagihan
     * milik pesanan, jadi baru wajib lunas saat kamar terakhir keluar.
     *
     * Tanggal check_out sengaja TIDAK diubah: masa sewa yang sudah dibayar
     * tetap tercatat apa adanya, kepergian lebih awal hanya menandai status
     * booking sebagai early check-out.
     */
    public function checkoutStore(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless($booking->isCheckedIn(), 404);

        if ($booking->isCheckedOut()) {
            return redirect()
                ->route($this->ruteIndex(), ['tanggal' => $booking->check_in->toDateString(), 'bulan' => $booking->check_in->format('Y-m')])
                ->withErrors(['foto_kartu' => 'Pesanan ' . $booking->nama_tamu . ' sudah di-check-out sebelumnya.']);
        }

        $sekarang = now();
        $calon    = $this->calonCheckout($booking);
        $keluar   = $this->kamarDipilih($request, $calon);

        if ($keluar->isEmpty()) {
            return back()->withErrors(['kamar' => 'Pilih minimal satu kamar yang check-out.']);
        }

        $tersisa  = $this->kamarTersisa($booking, $keluar);
        $terakhir = $tersisa->isEmpty();

        // Late maupun over late check-out sama-sama bertagihan, jadi bukti
        // pembayarannya wajib — tidak bisa diselesaikan lisan di meja depan.
        // Sisa tagihan pesanan wajib lunas saat kamar terakhir keluar; selama
        // masih ada kamar lain, tamu boleh melunasinya sekarang atau nanti.
        $biayaLate = (int) $keluar->sum(fn (Booking $m) => $m->biayaLateCheckout($sekarang));
        $sisa      = $booking->sisaBayar($booking->totalPesanan());
        $lunasi    = $sisa > 0 && ($terakhir || $request->boolean('lunasi_sisa'));
        $telat     = $biayaLate > 0 || $lunasi;
        // Kondisi kamar tidak punya nilai bawaan: staff wajib menyatakannya.
        $rusak  = $request->input('kondisi') === 'rusak';

        $request->validate([
            'kondisi'          => 'required|in:ok,rusak',
            'foto_kartu'       => 'required|image|mimes:jpg,jpeg,png|max:8192',
            'foto_pembayaran'  => ($telat ? 'required' : 'nullable') . '|image|mimes:jpg,jpeg,png|max:8192',
            'foto_kerusakan'   => ($rusak ? 'required' : 'nullable') . '|image|mimes:jpg,jpeg,png|max:8192',
            'catatan_kerusakan' => ($rusak ? 'required' : 'nullable') . '|string|max:2000',
            'catatan'          => 'nullable|string|max:255',
        ], [
            'kondisi.required'           => 'Nyatakan kondisi kamar terlebih dahulu.',
            'kondisi.in'                 => 'Pilihan kondisi kamar tidak valid.',
            'foto_kartu.required'        => 'Foto kartu kunci yang dikembalikan wajib diunggah.',
            'foto_kartu.image'           => 'Foto kartu harus berupa gambar.',
            'foto_kartu.mimes'           => 'Foto kartu harus berformat JPG atau PNG.',
            'foto_kartu.max'             => 'Ukuran foto kartu maksimal 8 MB.',
            'foto_pembayaran.required'   => $lunasi
                ? 'Masih ada sisa tagihan ' . Booking::rupiah($sisa) . ' — unggah bukti pelunasannya sebelum tamu pulang.'
                : 'Late / over late check-out: bukti pembayaran kelebihan jam wajib diunggah.',
            'foto_pembayaran.image'      => 'Bukti pembayaran harus berupa gambar.',
            'foto_kerusakan.required'    => 'Foto kerusakan / barang tertinggal wajib diunggah.',
            'foto_kerusakan.image'       => 'Foto kerusakan harus berupa gambar.',
            'catatan_kerusakan.required' => 'Tulis keterangan kerusakan / barang tertinggal.',
            'catatan_kerusakan.max'      => 'Keterangan maksimal 2000 karakter.',
            'catatan.max'                => 'Catatan maksimal 255 karakter.',
        ]);

        $kartuPath = $this->simpanFoto($request->file('foto_kartu'), 'kartu');

        $bayarPath = $request->hasFile('foto_pembayaran')
            ? $this->simpanFoto($request->file('foto_pembayaran'), 'bayar')
            : null;

        $rusakPath = $request->hasFile('foto_kerusakan')
            ? $this->simpanFoto($request->file('foto_kerusakan'), 'kerusakan')
            : null;

        $nomor   = $this->nomorKamar($keluar)->implode(', ') ?: (string) ($booking->room?->nomor ?? '—');

        // Temuan kerusakan langsung menjadi tiket, bukan catatan lepas yang
        // hanya terbaca kalau ada yang membuka detail booking.
        $reportId = null;
        if ($rusak) {
            $report = Report::create([
                'user_id' => Auth::id(),
                'judul'   => 'Kerusakan / barang tertinggal — kamar ' . $nomor,
                'isi'     => 'Tamu: ' . $booking->nama_tamu . ' (' . $booking->telepon . ')' . PHP_EOL
                    . 'Check-out: ' . $sekarang->translatedFormat('j F Y, H:i') . PHP_EOL
                    . 'Kamar: ' . $nomor . PHP_EOL . PHP_EOL
                    . $request->input('catatan_kerusakan'),
                'status'  => 'open',
            ]);
            $reportId = $report->id;
        }

        // Setiap kamar memakai status dan batas waktunya sendiri. Rinciannya
        // dicatat dulu, sebelum baris kamarnya berubah.
        $rincian = $keluar->mapWithKeys(fn (Booking $m) => [
            (string) $m->room?->nomor => $this->ringkasanCheckout($m, $sekarang),
        ]);

        foreach ($keluar as $m) {
            [$status, $menit] = $m->statusCheckoutPada($sekarang);

            $m->forceFill([
                'checked_out_at'       => $sekarang,
                'checkout_status'      => $status,
                'checkout_menit'       => $menit,
                'foto_kartu'           => $kartuPath,
                'foto_pembayaran_late' => $bayarPath,
                'checkout_kondisi_ok'  => ! $rusak,
                'foto_kerusakan'       => $rusakPath,
                'report_id'            => $reportId,
                'checkout_catatan'     => $request->input('catatan'),
                'checkout_by'          => Auth::id(),
            ])->save();
        }

        $pesan = 'Check-out ' . $booking->nama_tamu . ' — kamar ' . $nomor
            . ' pukul ' . $sekarang->format('H:i') . '. ';

        $pesan .= $rincian->unique()->count() === 1
            ? $rincian->first()
            : $rincian->map(fn ($teks, $no) => 'Kamar ' . $no . ': ' . $teks)->implode(' ');

        if ($reportId !== null) {
            $pesan .= ' Laporan kerusakan dikirim sebagai tiket #' . $reportId . '.';
        }

        // Bukti di atas sekaligus pelunasan sisa tagihan pesanan.
        if ($lunasi && $bayarPath !== null) {
            $booking->groupMembers()->update(['status_bayar' => Booking::BAYAR_LUNAS]);
            $pesan .= ' Sisa tagihan ' . Booking::rupiah($sisa) . ' dilunasi.';
        }

        if (! $terakhir) {
            $pesan .= ' Kamar ' . $this->nomorKamar($tersisa)->implode(', ') . ' belum check-out.';

            if ($sisa > 0 && ! $lunasi) {
                $pesan .= ' Sisa tagihan ' . Booking::rupiah($sisa) . ' ditagih saat kamar terakhir check-out.';
            }
        }

        return redirect()
            ->route($this->ruteIndex(), ['tanggal' => $booking->check_in->toDateString(), 'bulan' => $booking->check_in->format('Y-m')])
            ->with('success', $pesan);
    }

    /**
     * Hapus booking: satu kamar ini saja, atau seluruh grup (?scope=group).
     */
    public function destroy(Request $request, Booking $booking): RedirectResponse
    {
        $scope   = $request->input('scope', 'one');
        $members = $booking->groupMembers()->get();

        $targets = $scope === 'group' ? $members : $members->where('id', $booking->id)->values();
        $sisa    = $members->count() - $targets->count();

        $label = $targets->map(fn ($b) => $b->room?->nomor ?? 'kamar')->implode(', ');
        $nama  = $booking->nama_tamu;

        foreach ($targets as $t) {
            // Foto dipakai bersama grup: hapus file hanya bila tak ada booking lain yang memakainya.
            foreach (['foto_ktp', 'foto_kartu', 'foto_pelunasan', 'foto_bayar_awal', 'foto_bayar_tambahan', 'foto_pembayaran_late', 'foto_kerusakan'] as $kolom) {
                if (! $t->{$kolom}) {
                    continue;
                }

                $dipakaiLain = Booking::where($kolom, $t->{$kolom})->where('id', '!=', $t->id)->exists();
                if (! $dipakaiLain) {
                    Storage::disk('local')->delete($t->{$kolom});
                }
            }
            $t->delete();
        }

        $pesan = $scope === 'group' && $targets->count() > 1
            ? 'Grup booking ' . $nama . ' (' . $targets->count() . ' kamar: ' . $label . ') berhasil dihapus.'
            : 'Booking kamar ' . $label . ' — ' . $nama . ' berhasil dihapus' . ($sisa > 0 ? ' (kamar lain di grup tetap ada)' : '') . '.';

        return redirect()->route($this->ruteIndex())->with('success', $pesan);
    }

    /**
     * Endpoint JSON: id kamar TERISI pada rentang tanggal & durasi terpilih.
     */
    public function available(Request $request): JsonResponse
    {
        $tanggal = (string) $request->query('check_in', '');
        $checkIn = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal) && strtotime($tanggal))
            ? Carbon::createFromFormat('Y-m-d', $tanggal)->startOfDay()
            : now()->startOfDay();

        $durasi   = max(1, (int) $request->query('durasi', 1));
        $ignoreId = $request->filled('ignore') ? (int) $request->query('ignore') : null;

        // Saat edit/pindah: abaikan baris itu sendiri saja. Kamar lain dalam
        // pesanan yang sama tetap dihitung terisi.
        $ignoreIds = $ignoreId ? [$ignoreId] : [];

        $checkOut = $checkIn->copy()->addDays($durasi);
        $occupied = $this->occupiedRoomIdsIgnoring($checkIn, $checkOut, $ignoreIds);

        // Nama penghuni per kamar dipakai formulir ubah untuk memberi tahu
        // siapa yang akan ditukar bila kamar terisi dipilih.
        return response()->json([
            'occupied' => $occupied,
            'penghuni' => (object) $this->penghuniKamar($checkIn, $checkOut, $ignoreIds),
        ]);
    }

    // ------------------------------------------------------------------
    // Helper
    // ------------------------------------------------------------------

    /**
     * Simpan unggahan ke disk PRIVAT setelah dimampatkan.
     * $folder memisahkan jenis berkas: ktp / bayar / kartu / kerusakan.
     */
    private function simpanFoto(\Illuminate\Http\UploadedFile $berkas, string $folder): string
    {
        $biner = FotoKompresor::kompres($berkas);

        if ($biner === null) {
            return $berkas->store($folder, 'local');
        }

        $path = $folder . '/' . Str::random(40) . '.jpg';
        Storage::disk('local')->put($path, $biner);

        return $path;
    }

    /**
     * Kamar yang ikut di-check-in bersama.
     *
     * Daftar ini yang ditawarkan di halaman check-in; staff memilih kamar yang
     * tamunya sudah datang, jadi satu pesanan boleh masuk bertahap.
     *
     * Kamar pindahan TIDAK ikut check-in pesanan: ia di-check-in sendiri saat
     * tamunya benar-benar pindah, dan saat itulah kamar lamanya ditutup.
     * Kamar yang sudah ditinggalkan juga tidak disentuh lagi.
     */
    private function calonCheckin(Booking $booking): Collection
    {
        if ($booking->isPindahan() || $booking->isCheckedIn()) {
            return collect([$booking->loadMissing('room')]);
        }

        return $this->urutKamar($booking->groupMembers()->with('room')->get()
            ->filter(fn (Booking $m) => ! $m->isCheckedIn() && ! $m->isCheckedOut() && ! $m->isPindahan()));
    }

    /** Kamar yang bisa di-check-out: hanya yang sedang ditempati. */
    private function calonCheckout(Booking $booking): Collection
    {
        return $this->urutKamar($booking->groupMembers()->with(['room', 'pindahKe'])->get()
            ->filter(fn (Booking $m) => $m->isCheckedIn() && ! $m->isCheckedOut()));
    }

    /**
     * Kamar yang dicentang di formulir, dan hanya yang memang boleh diproses —
     * id dari peramban tidak dipercaya begitu saja. Bila pilihannya cuma satu
     * kamar, kamar itulah yang diproses.
     */
    private function kamarDipilih(Request $request, Collection $calon): Collection
    {
        if ($calon->count() === 1) {
            return $calon;
        }

        $ids = array_map('intval', (array) $request->input('kamar', []));

        return $calon->filter(fn (Booking $m) => in_array($m->id, $ids, true))->values();
    }

    /**
     * Kamar pesanan yang tetap aktif setelah $keluar check-out.
     *
     * Lanjutan pindah kamar dari kamar yang keluar tidak dihitung: tamunya
     * sudah pergi, jadi tidak ada lagi yang akan menempatinya.
     */
    private function kamarTersisa(Booking $booking, Collection $keluar): Collection
    {
        $ids = $keluar->pluck('id');

        return $booking->groupMembers()->with('room')->get()
            ->reject(fn (Booking $m) => $m->isCheckedOut()
                || $ids->contains($m->id)
                || $ids->contains($m->pindah_dari_id))
            ->values();
    }

    /** Kalimat hasil check-out satu kamar, dipakai pesan konfirmasi. */
    private function ringkasanCheckout(Booking $m, \Carbon\CarbonInterface $waktu): string
    {
        [$status] = $m->statusCheckoutPada($waktu);

        return match ($status) {
            Booking::CO_AWAL  => 'Early check-out.',
            Booking::CO_TELAT => $m->keluarPadaMalamPerpanjangan($waktu)
                ? 'Over late check-out — dihitung extend ' . (int) $m->perpanjang_otomatis_malam . ' malam di tagihan pesanan.'
                : $m->labelCheckoutPada($waktu) . ' — biaya ' . Booking::rupiah($m->biayaLateCheckout($waktu)) . '.',
            default           => 'Tepat waktu.',
        };
    }

    private function urutKamar(Collection $bookings): Collection
    {
        return $bookings->sortBy(fn (Booking $b) => (int) preg_replace('/\D/', '', (string) $b->room?->nomor))->values();
    }

    /** Nomor kamar terurut dari sekumpulan booking. */
    private function nomorKamar(Collection $bookings): Collection
    {
        return $bookings
            ->map(fn (Booking $b) => $b->room?->nomor)
            ->filter()
            ->sortBy(fn ($n) => (int) preg_replace('/\D/', '', (string) $n))
            ->values();
    }

    /**
     * Bukti pelunasan diminta saat check-in?
     *
     * Kedatangan biasa: bila belum lunas atau datang lebih awal (dan belum
     * ada bukti pelunasan). Kamar pindahan: hanya bila masih ada sisa
     * tagihan — misalnya karena upgrade yang ditagih saat tamu pindah.
     */
    private function butuhBayarCheckin(Booking $booking, int $total, \Carbon\CarbonInterface $waktu): bool
    {
        if ($booking->isCheckedIn()) {
            return false;
        }

        if ($booking->isPindahan()) {
            return $booking->sisaBayar($total) > 0;
        }

        return $booking->butuhFotoPelunasan($waktu) && $booking->foto_pelunasan === null;
    }

    /** URL foto bukti bayar terbaik untuk pratinjau di formulir ubah. */
    private function urlBuktiBayar(Collection $anggota): ?string
    {
        foreach ($anggota as $b) {
            if ($b->foto_bayar_awal) {
                return route('bayar.awal', $b);
            }
        }
        foreach ($anggota as $b) {
            if ($b->foto_pelunasan) {
                return route('bayar.show', $b);
            }
        }
        foreach ($anggota as $b) {
            if ($b->foto_bayar_tambahan) {
                return route('bayar.tambahan', $b);
            }
        }

        return null;
    }

    /**
     * Data panel tanggal terpilih: grup booking yang MENGINAP malam itu
     * + ringkasan terisi/kosong (total & per tipe).
     */
    private function dayData(Carbon $tanggal): array
    {
        $rooms = $this->orderedRooms();

        $menginap = $this->bookingsOccupying($tanggal);

        // Grup: booking segrup jadi satu kartu (booking lama tanpa grup = grup sendiri).
        $dayGroups = $menginap
            ->groupBy(fn ($b) => $b->grup ?? ('single-' . $b->id))
            ->values();

        // Detail harga & pembayaran berlaku untuk SELURUH pesanan, termasuk
        // kamar yang tidak menginap malam ini (tanggal berbeda, atau hasil
        // pindah per hari). Dimuat sekali di sini, bukan per kartu.
        $kodeGrup = $menginap->pluck('grup')->filter()->unique()->values();
        $semua    = $kodeGrup->isEmpty()
            ? collect()
            : Booking::with(['room', 'creator'])->whereIn('grup', $kodeGrup)->get()->groupBy('grup');

        foreach ($menginap as $b) {
            $b->grup_semua = $b->grup ? $semua->get($b->grup, collect([$b])) : collect([$b]);
        }

        $totalPerTipe  = $rooms->groupBy('tipe')->map->count();
        $terisiPerTipe = $menginap
            ->unique('room_id')
            ->groupBy(fn ($b) => $b->room?->tipe)
            ->map->count();

        // Satu kamar yang dijual ulang setelah check-out menghasilkan dua baris
        // booking pada tanggal yang sama; yang dihitung tetap jumlah KAMAR.
        $kamarTerisi = $menginap->pluck('room_id')->unique()->count();

        return [
            'dayGroups'     => $dayGroups,
            'terisi'        => $kamarTerisi,
            'kosong'        => max(0, $rooms->count() - $kamarTerisi),
            'totalKamar'    => $rooms->count(),
            'totalPerTipe'  => $totalPerTipe,
            'terisiPerTipe' => $terisiPerTipe,
        ];
    }

    /**
     * Validasi form TAMBAH (multi-kamar via checkbox, hanya hari ini/ke depan).
     */
    private function validatedMulti(Request $request): array
    {
        return $request->validate([
            'room_ids'   => 'required|array|min:1',
            'room_ids.*' => 'required|distinct|exists:rooms,id',
            'nama_tamu'  => 'required|string|max:255',
            'telepon'    => 'required|string|max:30',
            'breakfast'  => 'nullable|boolean',
            'check_in'     => 'required|date|after_or_equal:' . self::batasMundur(),
            'check_out'    => 'required|date|after:check_in',
            'sumber'       => 'required|in:umum,tiket',
            'status_bayar' => 'nullable|in:lunas,dp,belum',
            'dp_nominal'   => 'nullable|integer|min:0|max:1000000000',
            'diskon_nominal' => 'nullable|integer|min:0|max:1000000000',
            'diskon_mode'    => 'nullable|in:rupiah,persen',
            'diskon_persen'  => 'nullable|integer|min:0|max:99',
        ], $this->pesan() + [
            'room_ids.required'      => 'Centang minimal satu kamar.',
            'room_ids.min'           => 'Centang minimal satu kamar.',
            'room_ids.*.required'    => 'Ada pilihan kamar yang tidak valid.',
            'room_ids.*.distinct'    => 'Ada kamar yang dipilih lebih dari sekali.',
            'room_ids.*.exists'      => 'Kamar yang dipilih tidak ditemukan.',
            'check_in.after_or_equal' => 'Tanggal check-in paling jauh ' . self::MUNDUR_MAKS_HARI
                . ' hari ke belakang. Untuk tamu yang datang dini hari, pilih tanggal kemarin.',
        ]);
    }

    /**
     * Validasi form UBAH (satu kamar; tanggal lampau dibiarkan utk booking berjalan).
     */
    private function validatedSingle(Request $request): array
    {
        return $request->validate([
            'room_id'   => 'required|exists:rooms,id',
            'nama_tamu' => 'required|string|max:255',
            'telepon'   => 'required|string|max:30',
            'breakfast' => 'nullable|boolean',
            'check_in'     => 'required|date',
            'check_out'    => 'required|date|after:check_in',
            'sumber'       => 'required|in:umum,tiket',
            'status_bayar' => 'nullable|in:lunas,dp,belum',
            'dp_nominal'   => 'nullable|integer|min:0|max:1000000000',
            'diskon_nominal' => 'nullable|integer|min:0|max:1000000000',
            'diskon_mode'    => 'nullable|in:rupiah,persen',
            'diskon_persen'  => 'nullable|integer|min:0|max:99',
        ], $this->pesan() + [
            'room_id.required' => 'Pilih kamar terlebih dahulu.',
            'room_id.exists'   => 'Kamar yang dipilih tidak ditemukan.',
        ]);
    }

    private function pesan(): array
    {
        return [
            'nama_tamu.required' => 'Nama tamu wajib diisi.',
            'telepon.required'   => 'Nomor telepon wajib diisi.',
            'check_in.required'   => 'Tanggal check-in wajib diisi.',
            'check_in.date'       => 'Tanggal check-in tidak valid.',
            'check_out.required'  => 'Tanggal check-out wajib diisi.',
            'check_out.date'      => 'Tanggal check-out tidak valid.',
            'check_out.after'     => 'Tanggal check-out harus setelah tanggal check-in.',
            'sumber.required'     => 'Pilih asal pesanan.',
            'sumber.in'           => 'Asal pesanan tidak valid.',
            'status_bayar.in'     => 'Status pembayaran tidak valid.',
            'dp_nominal.integer'  => 'Nominal DP harus berupa angka rupiah tanpa titik.',
            'dp_nominal.min'      => 'Nominal DP tidak boleh negatif.',
            'dp_nominal.max'      => 'Nominal DP terlalu besar.',
        ];
    }

    /**
     * Bukti pembayaran yang diterima saat pesanan DIBUAT.
     *
     * Wajib begitu staff menyatakan uang sudah masuk (Lunas atau DP). Tanpa
     * ini, dua pernyataan paling berpengaruh di formulir — status bayar dan
     * nominal DP — tidak punya satu pun sandaran di luar ingatan orang yang
     * mengetiknya.
     *
     * $adaBukti diisi pada formulir UBAH: pesanan yang sudah punya bukti
     * dalam bentuk apa pun (awal, pelunasan saat check-in, atau tambahan)
     * tidak perlu mengunggah ulang hanya karena barisnya disunting.
     */
    private function validasiBuktiBayarAwal(
        Request $request,
        string $statusBayar,
        string $sumber,
        bool $adaBukti = false
    ): void {
        $wajib = Booking::butuhBuktiBayarAwal($statusBayar, $sumber) && ! $adaBukti;

        $label = $statusBayar === Booking::BAYAR_LUNAS ? 'Lunas' : 'DP';

        $request->validate([
            'foto_bayar_awal' => ($wajib ? 'required' : 'nullable') . '|image|mimes:jpg,jpeg,png|max:8192',
        ], [
            'foto_bayar_awal.required' => 'Status pembayaran "' . $label
                . '" berarti uang sudah diterima — unggah foto bukti pembayarannya.',
            'foto_bayar_awal.image'    => 'Bukti pembayaran harus berupa gambar.',
            'foto_bayar_awal.mimes'    => 'Bukti pembayaran harus berformat JPG atau PNG.',
            'foto_bayar_awal.max'      => 'Ukuran bukti pembayaran maksimal 8 MB.',
        ]);
    }

    /**
     * Nominal DP yang akan DISIMPAN.
     *
     * Hanya bermakna pada status DP. Kalau staff mengubah pilihan setelah
     * sempat mengetik angka, angkanya tidak boleh ikut tersimpan dan diam-diam
     * memunculkan "sisa" pada pesanan yang sudah lunas.
     */
    private function dpFinal(array $data, string $statusBayar): int
    {
        return $statusBayar === Booking::BAYAR_DP
            ? max(0, (int) ($data['dp_nominal'] ?? 0))
            : 0;
    }

    /**
     * DP harus berada di antara nol dan total tagihan.
     *
     * DP nol berarti belum ada uang yang masuk, DP sebesar tagihan berarti
     * lunas — keduanya punya pilihan statusnya sendiri, jadi tidak boleh
     * disamarkan sebagai DP.
     */
    private function galatDp(string $status, int $dp, int $total): ?string
    {
        if ($status !== Booking::BAYAR_DP) {
            return null;
        }

        if ($dp <= 0) {
            return 'Isi nominal uang muka yang sudah dibayar tamu, atau pilih "Belum Lunas".';
        }

        if ($total > 0 && $dp >= $total) {
            return 'Uang muka ' . Booking::rupiah($dp) . ' sama atau lebih besar dari total tagihan '
                . Booking::rupiah($total) . '. Kalau memang sudah dibayar penuh, pilih "Lunas".';
        }

        return null;
    }

    /**
     * Rentang menginap. Durasi tidak lagi diketik staff melainkan diturunkan
     * dari dua tanggal, sehingga tanggal yang tampil di layar dan malam yang
     * ditagih tidak mungkin berbeda.
     *
     * @return array{0: Carbon, 1: Carbon, 2: int} [check_in, check_out, malam]
     */
    private function rangeFrom(array $data): array
    {
        $checkIn  = Carbon::parse($data['check_in'])->startOfDay();
        $checkOut = Carbon::parse($data['check_out'])->startOfDay();
        $malam    = max(1, (int) $checkIn->diffInDays($checkOut));

        return [$checkIn, $checkOut, $malam];
    }

    /**
     * Pesanan Tiket.com sudah terbayar di muka: statusnya dipaksa lunas di
     * server, bukan hanya dikunci di peramban.
     */
    private function statusBayarFinal(array $data): string
    {
        if ($data['sumber'] === Booking::SUMBER_TIKET) {
            return Booking::BAYAR_LUNAS;
        }

        // Radio yang dikunci di peramban tidak ikut terkirim; anggap belum
        // lunas bila tidak ada nilainya, bukan menolak seluruh formulir.
        $status = (string) ($data['status_bayar'] ?? Booking::BAYAR_BELUM);

        return in_array($status, Booking::STATUS_BAYAR, true) ? $status : Booking::BAYAR_BELUM;
    }

    /**
     * Nominal & persen diskon yang akan DISIMPAN.
     *
     * Persen dihitung ULANG di server, tidak memakai nominal kiriman peramban:
     * yang disepakati dengan tamu adalah persennya, dan hasilnya harus sama
     * persis dengan yang tampil di layar.
     *
     * Tarif Tiket.com ditetapkan mitra dan uangnya masuk lewat mereka, jadi
     * tidak ada ruang memberi potongan di meja depan.
     *
     * @return array{0: int, 1: int} [diskon_nominal, diskon_persen]
     */
    private function diskonFinal(array $data, int $total): array
    {
        if (($data['sumber'] ?? null) === Booking::SUMBER_TIKET) {
            return [0, 0];
        }

        $mode   = (string) ($data['diskon_mode'] ?? 'rupiah');
        $persen = max(0, min(99, (int) ($data['diskon_persen'] ?? 0)));

        if ($mode === 'persen' && $persen > 0) {
            return [Booking::nominalDariPersen($total, $persen), $persen];
        }

        return [max(0, (int) ($data['diskon_nominal'] ?? 0)), 0];
    }

    /**
     * Total tagihan kamar untuk satu pesanan, dari kamar & malam di formulir.
     *
     * @param  \Illuminate\Support\Collection<int, Room>  $rooms
     */
    private function totalTagihan($rooms, int $malam, bool $breakfast): int
    {
        return (int) collect($rooms)->sum(fn (Room $r) => $r->hargaPerMalam($breakfast)) * max(1, $malam);
    }

    /**
     * Diskon tidak boleh menghabiskan atau melebihi tagihan.
     *
     * Kamar gratis adalah keputusan yang harus terlihat, bukan tersamar
     * sebagai diskon.
     */
    private function galatDiskon(int $diskon, int $total): ?string
    {
        if ($diskon > 0 && $total > 0 && $diskon >= $total) {
            return 'Diskon ' . Booking::rupiah($diskon) . ' sama atau lebih besar dari total kamar '
                . Booking::rupiah($total) . '. Turunkan nominalnya.';
        }

        return null;
    }

    /**
     * Nilai awal check-in & durasi untuk form (form tambah: minimal hari ini).
     *
     * @return array{0: Carbon, 1: int}
     */
    private function initialRange(?Booking $booking = null): array
    {
        $tanggal = (string) old('check_in', $booking?->check_in?->toDateString() ?? now()->toDateString());
        $checkIn = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal) && strtotime($tanggal))
            ? Carbon::createFromFormat('Y-m-d', $tanggal)->startOfDay()
            : now()->startOfDay();

        if ($booking === null && $checkIn->lt(now()->startOfDay())) {
            $checkIn = now()->startOfDay();
        }

        $durasi = max(1, (int) old('durasi', $booking->durasi ?? 1));

        // Form kini mengirim tanggal check-out; nilai lama dipakai bila ada.
        $keluar = (string) old('check_out', $booking?->check_out?->toDateString() ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $keluar) && strtotime($keluar)) {
            $selisih = (int) $checkIn->diffInDays(Carbon::createFromFormat('Y-m-d', $keluar)->startOfDay());
            $durasi  = max(1, $selisih);
        }

        return [$checkIn, $durasi];
    }

    private function bentrok(int $roomId, Carbon $checkIn, Carbon $checkOut, ?int $ignoreId = null): bool
    {
        return Booking::overlapping($roomId, $checkIn, $checkOut, $ignoreId)->exists();
    }

    /**
     * Id kamar yang terisi (punya booking tumpang tindih) pada rentang tsb.
     */
    private function occupiedRoomIds(Carbon $checkIn, Carbon $checkOut): Collection
    {
        return $this->occupiedRoomIdsIgnoring($checkIn, $checkOut, []);
    }

    private function occupiedRoomIdsIgnoring(Carbon $checkIn, Carbon $checkOut, array $ignoreIds): Collection
    {
        // Kamar yang tamunya sudah check-out tidak ikut terhitung: kamarnya
        // langsung terbuka dan boleh dibooking ulang pada tanggal yang sama.
        return Booking::query()
            ->menahanKamar()
            ->when($ignoreIds !== [], fn ($q) => $q->whereNotIn('id', $ignoreIds))
            ->where('check_in', '<', $checkOut)
            ->where('check_out', '>', $checkIn)
            ->pluck('room_id')
            ->unique()
            ->values();
    }

    /**
     * Penghuni tiap kamar terisi pada rentang tsb: room_id => ['Budi'] atau
     * ['Budi', 'Sari'] bila lebih dari satu pesanan menempatinya bergantian.
     * Satu nama berarti kamar itu bisa ditukar; lebih dari satu tidak.
     *
     * @return array<int, list<string>>
     */
    private function penghuniKamar(Carbon $checkIn, Carbon $checkOut, array $ignoreIds): array
    {
        return Booking::query()
            ->menahanKamar()
            ->when($ignoreIds !== [], fn ($q) => $q->whereNotIn('id', $ignoreIds))
            ->where('check_in', '<', $checkOut)
            ->where('check_out', '>', $checkIn)
            ->orderBy('check_in')
            ->get(['room_id', 'nama_tamu'])
            ->groupBy('room_id')
            ->map(fn (Collection $g) => $g->pluck('nama_tamu')->values()->all())
            ->all();
    }

    /**
     * Seluruh kamar terurut nomor (101, 102, ... 212).
     */
    private function orderedRooms(): Collection
    {
        return Room::all()
            ->sortBy(fn ($r) => (int) preg_replace('/\D/', '', $r->nomor) ?: PHP_INT_MAX)
            ->values();
    }

    /**
     * Kamar dikelompokkan per tipe (King -> Twin -> Family) utk daftar checkbox.
     */
    private function roomsByTipe(): Collection
    {
        $urutan = ['king' => 1, 'twin' => 2, 'family' => 3];

        return $this->orderedRooms()
            ->sortBy(fn ($r) => sprintf('%d-%09d', $urutan[$r->tipe] ?? 9, (int) preg_replace('/\D/', '', $r->nomor)))
            ->values()
            ->groupBy('tipe');
    }
}
