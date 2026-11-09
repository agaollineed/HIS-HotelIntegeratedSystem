<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Room;
use App\Support\FotoKompresor;
use App\Support\Panel;
use App\Support\PenyesuaianBayar;
use App\Support\PindahKamarHarian;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Pindah kamar — langsung, oleh staff maupun admin.
 *
 * Pindah kamar tidak lagi lewat pengajuan: staff mengerjakannya sendiri di
 * meja depan, dengan aturan yang sama persis seperti admin (bentrok, tukar
 * kamar, tarif, Tiket.com, dan penyesuaian bayar). Menghapus booking tetap
 * wewenang superadmin.
 *
 * Halaman ini memuat SELURUH kamar dalam satu pesanan. Tamu dengan 201 dan
 * 202 bisa memindah salah satunya saja, atau keduanya sekaligus — masing-
 * masing dengan kamar tujuan dan tanggal mulainya sendiri. Bila beberapa
 * kamar dipindah bersama, semuanya berhasil atau tidak ada yang berubah, dan
 * pembayaran disesuaikan sekali untuk seluruh pesanan.
 */
class PindahKamarController extends Controller
{
    public function create(Booking $booking): View|RedirectResponse
    {
        $anggota = $this->kamarBisaPindah($booking);

        if ($anggota->isEmpty()) {
            return redirect()
                ->to(Panel::rute('rooms.index', ['tanggal' => $booking->check_in->toDateString()]))
                ->with('error', 'Semua kamar pesanan ' . $booking->nama_tamu . ' sudah ditinggalkan, jadi tidak ada yang bisa dipindah.');
        }

        $hariIni = now()->startOfDay();

        // Per kamar: pilihan tanggal mulai (setiap malam masa menginapnya) dan
        // tanggal bawaan — "mulai hari ini" untuk tamu yang sedang di dalam.
        $baris = $anggota->map(function (Booking $b) use ($hariIni) {
            $tanggal = [];
            for ($d = $b->check_in->copy()->startOfDay(); $d->lt($b->check_out); $d->addDay()) {
                $tanggal[] = $d->copy();
            }

            $bawaan = $b->isCheckedIn() && $hariIni->gt($b->check_in) && $hariIni->lt($b->check_out)
                ? $hariIni
                : $b->check_in->copy()->startOfDay();

            return (object) ['booking' => $b, 'tanggal' => $tanggal, 'bawaan' => $bawaan->toDateString()];
        });

        $urutan = ['king' => 1, 'twin' => 2, 'family' => 3];

        return view('admin.rooms.pindah', [
            'booking'      => $booking,
            'baris'        => $baris,
            'roomsByTipe'  => Room::all()
                ->sortBy(fn ($r) => sprintf('%d-%09d', $urutan[$r->tipe] ?? 9, (int) preg_replace('/\D/', '', $r->nomor)))
                ->groupBy('tipe'),
            'potret'       => PenyesuaianBayar::potret($booking),
            'totalPesanan' => $booking->totalPesanan(),
        ]);
    }

    public function store(Request $request, Booking $booking): RedirectResponse
    {
        $data = $request->validate([
            'pindah'               => 'required|array',
            'pindah.*.aktif'       => 'nullable|in:1',
            'pindah.*.room_id'     => 'nullable|exists:rooms,id',
            'pindah.*.mulai'       => 'nullable|date_format:Y-m-d',
            'pindah.*.breakfast'   => 'nullable|boolean',
            'bayar'                => 'nullable|in:nanti,sekarang',
            'foto_bayar_tambahan'  => 'nullable|image|mimes:jpg,jpeg,png|max:8192',
        ], [
            'pindah.required'           => 'Pilih kamar yang akan dipindah.',
            'foto_bayar_tambahan.image' => 'Bukti pembayaran harus berupa gambar.',
            'foto_bayar_tambahan.mimes' => 'Bukti pembayaran harus berformat JPG atau PNG.',
            'foto_bayar_tambahan.max'   => 'Ukuran bukti pembayaran maksimal 8 MB.',
        ]);

        $anggota = $this->kamarBisaPindah($booking)->keyBy('id');

        // Hanya baris yang dicentang, dan hanya kamar milik pesanan ini.
        $pilih = collect($data['pindah'])
            ->filter(fn ($p) => ($p['aktif'] ?? null) === '1')
            ->map(fn ($p, $id) => $p + ['id' => (int) $id]);

        if ($pilih->isEmpty()) {
            return back()->withInput()->withErrors(['pindah' => 'Centang minimal satu kamar yang akan dipindah.']);
        }

        foreach ($pilih as $p) {
            $b = $anggota->get($p['id']);
            if ($b === null) {
                return back()->withInput()->withErrors(['pindah' => 'Ada kamar yang bukan milik pesanan ini atau sudah ditinggalkan.']);
            }
            if (empty($p['room_id']) || empty($p['mulai'])) {
                return back()->withInput()->withErrors(['pindah' => 'Kamar ' . $b->room?->nomor . ': pilih kamar tujuan dan tanggal mulainya.']);
            }
        }

        // Dua kamar ke tujuan yang sama jelas bentrok.
        if ($pilih->pluck('room_id')->duplicates()->isNotEmpty()) {
            return back()->withInput()->withErrors(['pindah' => 'Dua kamar tidak bisa dipindah ke kamar tujuan yang sama.']);
        }

        // Menukar dua kamar dalam satu pesanan cukup dengan memindah salah
        // satunya — penghuni kamar tujuan otomatis bertukar. Memindah keduanya
        // ke kamar masing-masing justru saling membatalkan.
        $kamarAsal = $pilih->mapWithKeys(fn ($p) => [$p['id'] => (int) $anggota[$p['id']]->room_id]);
        foreach ($pilih as $p) {
            $tujuanMilik = $kamarAsal->search((int) $p['room_id']);
            if ($tujuanMilik !== false && $tujuanMilik !== $p['id']) {
                return back()->withInput()->withErrors([
                    'pindah' => 'Kamar ' . $anggota[$p['id']]->room?->nomor . ' dan ' . $anggota[$tujuanMilik]->room?->nomor
                        . ' saling bertukar. Cukup pindahkan salah satunya — yang lain otomatis ikut bertukar.',
                ]);
            }
        }

        // Rencana pertama (tanpa menyimpan) untuk validasi awal dan menghitung
        // selisih tagihan seluruh pindah kamar ini.
        $rencanaAwal = [];
        foreach ($pilih as $p) {
            $r = PindahKamarHarian::rencanakan(
                $anggota[$p['id']],
                Room::findOrFail($p['room_id']),
                Carbon::createFromFormat('Y-m-d', $p['mulai'])->startOfDay(),
                array_key_exists('breakfast', $p) && $p['breakfast'] !== null ? (bool) $p['breakfast'] : null,
            );

            if (! $r->bisa()) {
                return back()->withInput()->withErrors(['pindah' => 'Kamar ' . $anggota[$p['id']]->room?->nomor . ': ' . $r->galat]);
            }

            $rencanaAwal[$p['id']] = $r;
        }

        $sebelum = PenyesuaianBayar::potret($booking);
        $selisih = array_sum(array_map(fn (PindahKamarHarian $r) => $r->selisih(), $rencanaAwal));

        // Diskon persen ikut berubah bersama total; angka pastinya dihitung
        // PenyesuaianBayar setelah semua kamar pindah.
        $utama   = $anggota->first();
        $total   = $booking->totalPesanan() + $selisih;
        $diskon  = $utama->diskonPersen() > 0 ? Booking::nominalDariPersen($total, $utama->diskonPersen()) : $utama->diskonPesanan();
        $sisa    = max(0, ($total - $diskon) - $sebelum['dibayar']);
        $adaSisa = $selisih > 0 && $sisa > 0;

        // Pesanan Tiket.com selalu lunas lewat mitra; selisih upgrade dibayar
        // di hotel, jadi harus dilunasi di tempat — tidak bisa "ditagih nanti".
        $lunasSekarang = $adaSisa && (($data['bayar'] ?? 'nanti') === 'sekarang' || $booking->dariTiket());

        if ($lunasSekarang && ! $request->hasFile('foto_bayar_tambahan')) {
            return back()->withInput()->withErrors([
                'foto_bayar_tambahan' => 'Tamu membayar selisih ' . Booking::rupiah($sisa) . ' sekarang — unggah foto bukti pembayarannya.'
                    . ($booking->dariTiket() ? ' Pesanan Tiket.com tidak bisa menunda pembayaran selisih.' : ''),
            ]);
        }

        $bukti = $lunasSekarang ? FotoKompresor::simpan($request->file('foto_bayar_tambahan'), 'bayar') : null;

        try {
            [$ringkasan, $catatan, $bayar] = DB::transaction(function () use ($pilih, $anggota, $booking, $sebelum, $lunasSekarang, $bukti) {
                $ringkasan = [];
                $catatan   = [];
                $pertama   = null;

                foreach ($pilih as $p) {
                    // Disusun ulang terhadap keadaan TERKINI: kamar yang baru
                    // saja dipindah ikut mengubah ketersediaan kamar berikutnya.
                    $b = $anggota[$p['id']]->fresh(['room']);
                    $r = PindahKamarHarian::rencanakan(
                        $b,
                        Room::findOrFail($p['room_id']),
                        Carbon::createFromFormat('Y-m-d', $p['mulai'])->startOfDay(),
                        array_key_exists('breakfast', $p) && $p['breakfast'] !== null ? (bool) $p['breakfast'] : null,
                    );

                    if (! $r->bisa()) {
                        throw new \RuntimeException('Kamar ' . $b->room?->nomor . ': ' . $r->galat);
                    }

                    $hasil = $r->terapkan(Auth::id(), false, null, false);
                    $pertama ??= $hasil['baru'];

                    $ringkasan[] = $r->ringkasan();

                    if ($r->menukar()) {
                        $catatan[] = $r->keteranganTukar();
                    }

                    if ($hasil['transisi']) {
                        $catatan[] = 'Kamar ' . ($r->asal?->nomor ?? '—') . ' langsung ditutup, kamar ' . $r->tujuan->nomor . ' aktif.';
                    } elseif ($r->pecah && $b->isCheckedIn()) {
                        $catatan[] = 'Saat tamu pindah, tekan "Check-in Pindahan ke ' . $r->tujuan->nomor . '" — kamar '
                            . ($r->asal?->nomor ?? '—') . ' tertutup otomatis.';
                    }
                }

                $bayar = PenyesuaianBayar::terapkan($booking, $sebelum, $lunasSekarang);

                if ($bukti !== null && $pertama !== null) {
                    $pertama->forceFill(['foto_bayar_tambahan' => $bukti])->save();
                }

                return [$ringkasan, $catatan, $bayar];
            });
        } catch (\RuntimeException $e) {
            if ($bukti !== null) {
                Storage::disk('local')->delete($bukti);
            }

            return back()->withInput()->withErrors(['pindah' => $e->getMessage() . ' Tidak ada kamar yang dipindah.']);
        }

        $mulai = Carbon::createFromFormat('Y-m-d', $pilih->min('mulai'));

        return redirect()
            ->to(Panel::rute('rooms.index', ['tanggal' => $mulai->toDateString(), 'bulan' => $mulai->format('Y-m')]))
            ->with('success', 'Tamu ' . $booking->nama_tamu . ' pindah kamar: ' . implode('; ', $ringkasan) . '.'
                . ($catatan !== [] ? ' ' . implode(' ', $catatan) : '')
                . PenyesuaianBayar::kalimat($bayar));
    }

    /**
     * Kamar dalam pesanan ini yang masih bisa dipindah (belum ditinggalkan),
     * urut tanggal lalu nomor kamar.
     *
     * @return Collection<int, Booking>
     */
    private function kamarBisaPindah(Booking $booking): Collection
    {
        return $booking->groupMembers()->with('room')->get()
            ->reject(fn (Booking $b) => $b->isCheckedOut())
            ->sortBy(fn (Booking $b) => $b->check_in->format('Ymd') . sprintf('%06d', (int) preg_replace('/\D/', '', (string) $b->room?->nomor)))
            ->values();
    }
}
