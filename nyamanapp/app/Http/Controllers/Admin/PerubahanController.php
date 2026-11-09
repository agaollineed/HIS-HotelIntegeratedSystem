<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingRequest;
use App\Support\PenyesuaianBayar;
use App\Support\PindahKamarHarian;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Keputusan admin atas pengajuan perubahan booking.
 *
 * Satu pengajuan = satu jenis perubahan = satu keputusan.
 *
 * Cakupan:
 *   KAMAR      hanya baris itu sendiri — seluruh masa menginap, atau mulai
 *              malam tertentu (pindah per hari, barisnya dipecah).
 *   NAMA, TELEPON, TANGGAL
 *              seluruh kamar dalam satu pesanan, karena pengajuan staff
 *              dibuat untuk tamunya, bukan untuk satu kamar.
 *
 * Setiap perubahan yang menggeser tagihan ikut menyesuaikan status bayar
 * lewat PenyesuaianBayar: pesanan lunas yang tagihannya naik turun menjadi
 * DP, dan selisihnya ditagih saat check-in atau check-out.
 */
class PerubahanController extends Controller
{
    /**
     * Setujui: perubahan baru diterapkan di sini, di dalam satu transaksi.
     */
    public function setujui(Request $request, BookingRequest $permintaan): RedirectResponse
    {
        $data = $request->validate([
            'catatan_admin' => 'nullable|string|max:500',
        ]);

        if (! $permintaan->belumDiputus()) {
            return back()->with('error', 'Pengajuan ini sudah diputus sebelumnya.');
        }

        $booking = $permintaan->booking;

        if ($booking === null) {
            return back()->with('error', 'Booking-nya sudah tidak ada.');
        }

        // Diperiksa ULANG di detik persetujuan. Kamar yang kosong saat staff
        // mengajukan bisa saja sudah terisi sekarang — memeriksa hanya di awal
        // berarti menyetujui gambaran yang sudah basi.
        $rencana = $permintaan->rencanaPindah();

        $galat = $rencana !== null
            ? $rencana->galat
            : $this->galatTerap($permintaan, $booking);

        $galat ??= $this->galatTiket($permintaan, $booking, $rencana);

        if ($galat !== null) {
            return back()->with('error', 'Tidak bisa disetujui: ' . $galat);
        }

        $bayar = DB::transaction(function () use ($permintaan, $booking, $data, $rencana) {
            $bayar = $this->terapkan($permintaan, $booking, $rencana);

            $permintaan->update([
                'status'        => BookingRequest::DISETUJUI,
                'catatan_admin' => $data['catatan_admin'] ?? null,
                'diputus_by'    => Auth::id(),
                'diputus_at'    => now(),
            ]);

            return $bayar;
        });

        $pesan = 'Pengajuan "' . $permintaan->labelJenis() . '" untuk '
            . $booking->nama_tamu . ' disetujui dan sudah diterapkan.';

        if ($rencana?->menukar()) {
            $pesan .= ' ' . $rencana->keteranganTukar();
        }

        if ($bayar !== null) {
            $pesan .= PenyesuaianBayar::kalimat($bayar);
        }

        return back()->with('success', $pesan);
    }

    /** Tolak: booking tidak disentuh sama sekali, jejak pengajuannya tetap. */
    public function tolak(Request $request, BookingRequest $permintaan): RedirectResponse
    {
        $data = $request->validate([
            'catatan_admin' => 'required|string|min:3|max:500',
        ], [
            'catatan_admin.required' => 'Tuliskan alasan penolakan — staff perlu tahu kenapa.',
        ]);

        if (! $permintaan->belumDiputus()) {
            return back()->with('error', 'Pengajuan ini sudah diputus sebelumnya.');
        }

        $permintaan->update([
            'status'        => BookingRequest::DITOLAK,
            'catatan_admin' => $data['catatan_admin'],
            'diputus_by'    => Auth::id(),
            'diputus_at'    => now(),
        ]);

        return back()->with('success', 'Pengajuan "' . $permintaan->labelJenis() . '" ditolak.');
    }

    /**
     * Terapkan usulan ke booking.
     *
     * @return array<string, mixed>|null  hasil penyesuaian bayar, bila tagihan bisa berubah
     */
    private function terapkan(BookingRequest $permintaan, Booking $booking, ?PindahKamarHarian $rencana): ?array
    {
        if ($permintaan->jenis === BookingRequest::PINDAH_KAMAR) {
            if ($rencana === null) {
                throw new \LogicException('Rencana pindah kamar belum disusun.');
            }

            // Seluruh masa menginap (dengan kemungkinan tukar) atau mulai
            // malam tertentu; status bayar ikut disesuaikan di dalamnya.
            return $rencana->terapkan(Auth::id())['bayar'];
        }

        $sebelum = PenyesuaianBayar::potret($booking);
        $anggota = $booking->groupMembers()->get();

        foreach ($anggota as $m) {
            // Tamu kamar ini sudah pulang: masa menginapnya selesai dan tagihannya
            // tidak boleh ikut bertambah karena tamu lain memperpanjang.
            if ($m->isCheckedOut() && $permintaan->jenis !== BookingRequest::UBAH_TAMU) {
                continue;
            }

            match ($permintaan->jenis) {
                BookingRequest::UBAH_TAMU => $m->update(array_filter([
                    'nama_tamu' => $permintaan->nama_tamu_baru,
                    'telepon'   => $permintaan->telepon_baru,
                ], fn ($v) => $v !== null)),

                BookingRequest::PERPANJANG => $m->update([
                    'durasi'    => $permintaan->durasi_baru,
                    'check_out' => $m->check_in->copy()->addDays((int) $permintaan->durasi_baru),
                ]),

                BookingRequest::UBAH_TANGGAL => $m->update([
                    'check_in'  => $permintaan->check_in_baru,
                    'check_out' => $permintaan->check_in_baru->copy()->addDays((int) $m->durasi),
                ]),

                default => null,
            };
        }

        // Tambah malam menaikkan tagihan; pesanan lunas tidak boleh ikut
        // "lunas" untuk malam yang belum dibayar.
        return $permintaan->jenis === BookingRequest::PERPANJANG
            ? PenyesuaianBayar::terapkan($booking, $sebelum)
            : null;
    }

    /**
     * Alasan yang membuat usulan (selain pindah kamar) tidak bisa diterapkan
     * SEKARANG.
     *
     * Bentrok diperiksa per kamar pada rentang yang AKAN berlaku untuk kamar
     * itu — tiap kamar dalam pesanan bisa punya tanggal masuk sendiri.
     */
    private function galatTerap(BookingRequest $permintaan, Booking $booking): ?string
    {
        $anggota = $booking->groupMembers()->with(['room', 'pindahKe'])->get();
        $abaikan = $anggota->pluck('id')->all();

        if (in_array($permintaan->jenis, [BookingRequest::PERPANJANG, BookingRequest::UBAH_TANGGAL], true)
            && $anggota->contains(fn (Booking $m) => $m->isPindahan() || $m->pindahKe !== null)) {
            return 'Pesanan ini sudah dipecah karena pindah kamar per hari. Mengubah tanggal seluruh pesanan '
                . 'akan memutus sambungan antar kamarnya — ubah tanggal lewat formulir ubah pada kamar terakhirnya.';
        }

        if (! in_array($permintaan->jenis, [BookingRequest::PERPANJANG, BookingRequest::UBAH_TANGGAL], true)) {
            return null;
        }

        $bentrok = [];

        foreach ($anggota->reject(fn (Booking $m) => $m->isCheckedOut()) as $m) {
            $mulai = $permintaan->jenis === BookingRequest::UBAH_TANGGAL
                ? Carbon::parse($permintaan->check_in_baru)->startOfDay()
                : $m->check_in->copy()->startOfDay();

            $durasi = $permintaan->jenis === BookingRequest::PERPANJANG
                ? (int) $permintaan->durasi_baru
                : (int) $m->durasi;

            $selesai = $mulai->copy()->addDays(max(1, $durasi));

            $ada = Booking::where('room_id', $m->room_id)
                ->menahanKamar()
                ->whereNotIn('id', $abaikan)
                ->where('check_in', '<', $selesai)
                ->where('check_out', '>', $mulai)
                ->exists();

            if ($ada) {
                $bentrok[] = ($m->room?->nomor ?? ('#' . $m->room_id))
                    . ' (' . $mulai->translatedFormat('j M') . ' – ' . $selesai->translatedFormat('j M Y') . ')';
            }
        }

        if ($bentrok !== []) {
            return 'Kamar ' . implode(', ', array_unique($bentrok))
                . ' sudah terisi pada tanggal tersebut. Minta staff mengajukan ulang dengan kamar atau tanggal lain.';
        }

        return null;
    }

    /**
     * Pesanan Tiket.com selalu lunas lewat mitra. Selisih yang muncul karena
     * upgrade atau tambah malam dibayar di hotel, dan pembayaran itu butuh
     * bukti — sesuatu yang tidak ada pada tombol "Setujui". Jadi perubahan
     * seperti itu dikerjakan lewat menu Pindah Kamar / formulir ubah.
     */
    private function galatTiket(BookingRequest $permintaan, Booking $booking, ?PindahKamarHarian $rencana): ?string
    {
        if (! $booking->dariTiket()) {
            return null;
        }

        $naik = match ($permintaan->jenis) {
            BookingRequest::PINDAH_KAMAR => $rencana !== null && $rencana->selisih() > 0,
            BookingRequest::PERPANJANG   => (int) $permintaan->durasi_baru > (int) $booking->durasi,
            default                      => false,
        };

        return $naik
            ? 'Pesanan Tiket.com: selisih biayanya dibayar di hotel dan perlu bukti bayar. '
                . 'Kerjakan lewat ' . ($permintaan->jenis === BookingRequest::PINDAH_KAMAR ? 'menu Pindah Kamar' : 'formulir ubah')
                . ' supaya pembayarannya tercatat, lalu tolak pengajuan ini.'
            : null;
    }
}
