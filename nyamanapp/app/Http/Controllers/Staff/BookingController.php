<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Concerns\HandlesRoomCalendar;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Report;
use App\Models\Room;
use App\Support\FotoKompresor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
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

        return view('staff.rooms.index', [
            'lewatJam'      => $this->lewatJamCheckout(),
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
        $groupIds = $booking->groupMembers()->pluck('id')->all();

        // Tarif kamar LAIN dalam pesanan ini. Formulir ubah hanya memegang satu
        // kamar, sedangkan diskon berlaku untuk seluruh pesanan — tanpa angka
        // ini, pratinjau tagihan di layar akan lebih kecil dari yang sebenarnya.
        $hargaLain = (int) $booking->groupMembers()->with('room')->get()
            ->where('id', '!=', $booking->id)
            ->sum(fn (Booking $b) => $b->hargaMalam());

        return view($this->viewEdit(), [
            'booking'     => $booking,
            'groupCount'  => count($groupIds),
            'hargaLain'   => $hargaLain,
            'roomsByTipe' => $this->roomsByTipe(),
            'occupiedIds' => $this->occupiedRoomIdsIgnoring($checkIn, $checkIn->copy()->addDays($durasi), $groupIds),
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

        $this->validasiBuktiBayarAwal(
            $request,
            (string) ($data['status_bayar'] ?? Booking::BAYAR_BELUM),
            (string) $data['sumber'],
            $booking->foto_bayar_awal,
        );

        [$checkIn, $checkOut, $malam] = $this->rangeFrom($data);

        $room    = Room::findOrFail($data['room_id']);
        $members = $booking->groupMembers()->get();
        $ignore  = $members->pluck('id')->all();

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
        // Yang diabaikan tetap SELURUH grup: kalau tidak, memperpanjang 101
        // sehari akan dianggap bentrok dengan barisnya sendiri.
        if ($this->bentrokIgnoring($room->id, $checkIn, $checkOut, $ignore)) {
            return back()->withInput()->withErrors([
                'room_id' => 'Kamar ' . $room->nomor . ' sudah dibooking pada rentang '
                    . $checkIn->translatedFormat('j M Y') . ' – ' . $checkOut->translatedFormat('j M Y')
                    . '. Pilih kamar atau tanggal lain.',
            ]);
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

        // ---- PER BARIS KAMAR: kamar, tanggal, durasi, sarapan ----
        $booking->update([
            'room_id'   => $room->id,
            'breakfast' => $breakfast,
            'check_in'  => $checkIn,
            'durasi'    => $malam,
            'check_out' => $checkOut,
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

        $suffix = $members->count() > 1
            ? ' Tanggal & sarapan berlaku untuk kamar ' . $room->nomor . ' saja; '
              . 'data tamu dan pembayaran berlaku untuk ' . $members->count() . ' kamar segrup.'
            : '';

        return redirect()
            ->route($this->ruteIndex(), ['tanggal' => $checkIn->toDateString(), 'bulan' => $checkIn->format('Y-m')])
            ->with('success', 'Booking kamar ' . $room->nomor . ' — ' . $data['nama_tamu']
                . ' berhasil diperbarui.' . $suffix);
    }

    /**
     * Anggota pesanan yang ikut diproses oleh sebuah aksi.
     *
     * Sejak masa menginap bisa berbeda antar kamar dalam satu pesanan, aksi
     * yang selalu mengenai seluruh grup menjadi berbahaya: kamar 101 yang
     * diperpanjang semalam akan ikut ter-check-out ketika 103 pulang, dan
     * kamar itu terhitung kosong padahal tamunya masih di dalam.
     *
     * Karena itu lingkupnya dinyatakan, bukan diasumsikan:
     *
     *   scope=group  (bawaan)  seluruh kamar pesanan — tamu datang/pulang serentak
     *   scope=one              hanya baris kamar ini
     *
     * Bawaannya tetap 'group' supaya perilaku lama tidak berubah diam-diam
     * untuk pesanan yang memang seragam.
     *
     * @return \Illuminate\Support\Collection<int, Booking>
     */
    protected function anggotaLingkup(Request $request, Booking $booking)
    {
        return $request->input('scope') === 'one'
            ? collect([$booking])
            : $booking->groupMembers()->with('room')->get();
    }

    /**
     * Halaman "Tamu Check-in": unggah foto KTP — berlaku utk seluruh grup.
     */
    public function checkin(Booking $booking)
    {
        $total = $booking->totalPesanan();
        $scope = request()->query('scope') === 'one' ? 'one' : 'group';

        return view('staff.rooms.checkin', [
            'scope'        => $scope,
            'booking'      => $booking,
            'menitAwal'    => $booking->menitEarlyCheckinPada(now()),
            'butuhBayar'   => $booking->butuhFotoPelunasan(now()),
            'groupRooms'   => $this->nomorKamarGrup($booking),
            'totalKamar'   => $total,
            'diskon'       => $booking->diskonPesanan(),
            'totalTagihan' => $booking->tagihanPesanan($total),
            'sudahDibayar' => $booking->dibayar($total),
            'sisaBayar'    => $booking->sisaBayar($total),
        ]);
    }

    /**
     * Proses check-in SEKALI untuk seluruh grup:
     * foto & waktu check-in diterapkan ke semua kamar dalam grup.
     */
    public function checkinStore(Request $request, Booking $booking): RedirectResponse
    {
        $sekarang   = now();
        $awal       = $booking->menitEarlyCheckinPada($sekarang);
        $wajibFoto  = $booking->foto_ktp === null;
        // Bukti pelunasan diminta bila tagihan belum lunas ATAU tamu datang
        // lebih awal (early check-in menambah jam pakai kamar).
        $wajibBayar = $booking->butuhFotoPelunasan($sekarang) && $booking->foto_pelunasan === null;

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

        $members      = $this->anggotaLingkup($request, $booking);
        $sudahCheckin = $booking->isCheckedIn();

        $fotoPath = $booking->foto_ktp;
        if ($request->hasFile('foto_ktp')) {
            if ($fotoPath) {
                Storage::disk('local')->delete($fotoPath);
            }
            $fotoPath = $this->simpanFoto($request->file('foto_ktp'), 'ktp');
        }

        $bayarPath = $booking->foto_pelunasan;
        if ($request->hasFile('foto_pelunasan')) {
            if ($bayarPath) {
                Storage::disk('local')->delete($bayarPath);
            }
            $bayarPath = $this->simpanFoto($request->file('foto_pelunasan'), 'bayar');
        }

        $waktu = $booking->checked_in_at ?? $sekarang;

        foreach ($members as $m) {
            $m->foto_ktp       = $fotoPath;
            $m->foto_pelunasan = $bayarPath;

            // Bukti pelunasan yang masuk sekaligus melunasi tagihan.
            if ($bayarPath !== null) {
                $m->status_bayar = Booking::BAYAR_LUNAS;
            }

            if ($m->checked_in_at === null) {
                $m->checked_in_at      = $waktu;
                $m->checkin_menit_awal = $awal;
            }
            $m->save();
        }

        $nomor = $members->map(fn ($m) => $m->room?->nomor)->filter()->sort()->implode(', ');

        $catatanAwal = ($awal > 0 && ! $sudahCheckin)
            ? ' Early check-in ' . Booking::labelDurasi($awal) . ' tercatat.'
            : '';

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

        [$status, $menit] = $booking->statusCheckoutPada(now());

        $scope = request()->query('scope') === 'one' ? 'one' : 'group';

        return view('staff.rooms.checkout', [
            'scope'      => $scope,
            'booking'    => $booking,
            'status'     => $status,
            'menit'      => $menit,
            'overLate'   => $booking->overLatePada(now()),
            'biayaLate'  => $booking->biayaLateCheckout(now()),
            'butuhBayar' => $booking->butuhFotoBayarCheckout(now()),
            'groupRooms' => $scope === 'one'
                ? collect([$booking->room?->nomor])->filter()
                : $this->nomorKamarGrup($booking),
        ]);
    }

    /**
     * Tandai pesanan yang lewat jam 12.00 sebagai late check-out.
     *
     * Dipanggil dari toast di panel kamar. Statusnya sendiri sudah dihitung
     * otomatis dari waktu, jadi tombol ini tidak menentukan apa-apa soal
     * tagihan — yang dicatat adalah SIAPA yang melihat peringatan itu dan
     * KAPAN, supaya kamar yang lewat jam tidak bisa didiamkan diam-diam.
     */
    public function tandaiLate(Booking $booking): RedirectResponse
    {
        abort_unless($booking->lateBerjalan(), 404);

        foreach ($booking->groupMembers()->get() as $m) {
            if ($m->late_ditandai_at === null) {
                $m->forceFill([
                    'late_ditandai_at' => now(),
                    'late_ditandai_by' => Auth::id(),
                ])->save();
            }
        }

        return back()->with('success',
            'Kamar ' . $this->nomorKamarGrup($booking)->implode(', ')
            . ' ditandai late check-out. Bukti pembayaran kelebihan jam wajib diunggah saat check-out.');
    }

    /**
     * Proses check-out SEKALI untuk seluruh grup.
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

        $sekarang         = now();
        [$status, $menit] = $booking->statusCheckoutPada($sekarang);

        // Late maupun over late check-out sama-sama bertagihan, jadi bukti
        // pembayarannya wajib — tidak bisa diselesaikan lisan di meja depan.
        $telat  = $booking->butuhFotoBayarCheckout($sekarang);
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
            'foto_pembayaran.required'   => 'Late / over late check-out: bukti pembayaran kelebihan jam wajib diunggah.',
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

        $nomor = $request->input('scope') === 'one'
            ? (string) ($booking->room?->nomor ?? '—')
            : $this->nomorKamarGrup($booking)->implode(', ');

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

        foreach ($this->anggotaLingkup($request, $booking) as $m) {
            // Kamar yang sudah di-check-out sebelumnya tidak disentuh lagi —
            // waktu dan status kepergiannya adalah catatan yang sudah terjadi.
            if ($m->isCheckedOut()) {
                continue;
            }

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

        $pesan .= match ($status) {
            Booking::CO_AWAL  => 'Early check-out.',
            Booking::CO_TELAT => ($booking->overLatePada($sekarang) ? 'Over late check-out' : 'Late check-out')
                . ' — biaya ' . Booking::rupiah($booking->biayaLateCheckout($sekarang)) . ' per kamar.',
            default           => 'Tepat waktu.',
        };

        if ($reportId !== null) {
            $pesan .= ' Laporan kerusakan dikirim sebagai tiket #' . $reportId . '.';
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
            foreach (['foto_ktp', 'foto_kartu', 'foto_pelunasan', 'foto_bayar_awal', 'foto_pembayaran_late', 'foto_kerusakan'] as $kolom) {
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

        // Saat edit: abaikan seluruh grup milik booking tsb.
        $ignoreIds = [];
        if ($ignoreId && ($b = Booking::find($ignoreId))) {
            $ignoreIds = $b->groupMembers()->pluck('id')->all();
        }

        $occupied = $this->occupiedRoomIdsIgnoring($checkIn, $checkIn->copy()->addDays($durasi), $ignoreIds);

        return response()->json(['occupied' => $occupied]);
    }

    // ------------------------------------------------------------------
    // Helper
    // ------------------------------------------------------------------

    /**
     * Pesanan yang sudah check-in, belum check-out, dan masa sewanya lewat
     * jam 12.00 — bahan untuk toast peringatan di panel kamar.
     *
     * Dikelompokkan per pesanan supaya satu tamu dengan lima kamar muncul
     * sebagai satu peringatan, bukan lima.
     */
    private function lewatJamCheckout(): Collection
    {
        return Booking::with('room')
            ->whereNotNull('checked_in_at')
            ->whereNull('checked_out_at')
            ->get()
            ->filter(fn (Booking $b) => $b->lateBerjalan())
            ->groupBy(fn (Booking $b) => $b->grup ?? ('single-' . $b->id))
            ->map(fn ($grup) => $grup->sortBy(fn ($b) => (int) preg_replace('/\D/', '', (string) $b->room?->nomor))->values())
            ->values();
    }

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
     * Nomor kamar seluruh grup pesanan, terurut (101, 102, ... 212).
     */
    private function nomorKamarGrup(Booking $booking): Collection
    {
        return $booking->groupMembers()->with('room')->get()
            ->sortBy(fn ($b) => (int) preg_replace('/\D/', '', (string) $b->room?->nomor))
            ->map(fn ($b) => $b->room?->nomor ?? '—')
            ->values();
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
     * $adaSebelumnya diisi pada formulir UBAH: pesanan yang buktinya sudah
     * masuk tidak perlu mengunggah ulang hanya karena barisnya disunting.
     */
    private function validasiBuktiBayarAwal(
        Request $request,
        string $statusBayar,
        string $sumber,
        ?string $adaSebelumnya = null
    ): void {
        $wajib = Booking::butuhBuktiBayarAwal($statusBayar, $sumber) && $adaSebelumnya === null;

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

    private function bentrokIgnoring(int $roomId, Carbon $checkIn, Carbon $checkOut, array $ignoreIds): bool
    {
        return Booking::query()
            ->where('room_id', $roomId)
            ->menahanKamar()
            ->when($ignoreIds !== [], fn ($q) => $q->whereNotIn('id', $ignoreIds))
            ->where('check_in', '<', $checkOut)
            ->where('check_out', '>', $checkIn)
            ->exists();
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
