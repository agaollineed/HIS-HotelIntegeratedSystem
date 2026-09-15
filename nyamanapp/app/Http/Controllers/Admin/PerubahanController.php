<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingRequest;
use App\Models\Room;
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
 * Cakupan perubahan mengikuti aturan yang sudah berlaku di formulir ubah:
 * KAMAR hanya berpindah untuk baris itu sendiri, sedangkan NAMA, TELEPON, dan
 * TANGGAL berlaku untuk seluruh kamar dalam satu pesanan. Dua perilaku yang
 * berbeda untuk hal yang sama akan menjadi sumber salah paham di meja depan.
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
        if ($galat = $this->galatTerap($permintaan, $booking)) {
            return back()->with('error', $galat);
        }

        DB::transaction(function () use ($permintaan, $booking, $data) {
            $this->terapkan($permintaan, $booking);

            $permintaan->update([
                'status'        => BookingRequest::DISETUJUI,
                'catatan_admin' => $data['catatan_admin'] ?? null,
                'diputus_by'    => Auth::id(),
                'diputus_at'    => now(),
            ]);
        });

        return back()->with('success', 'Pengajuan "' . $permintaan->labelJenis() . '" untuk '
            . $booking->nama_tamu . ' disetujui dan sudah diterapkan.');
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
     * Kamar hanya untuk baris ini; nama, telepon, dan tanggal untuk seluruh
     * pesanan — sama seperti formulir ubah milik admin.
     */
    private function terapkan(BookingRequest $permintaan, Booking $booking): void
    {
        if ($permintaan->jenis === BookingRequest::PINDAH_KAMAR) {
            $booking->update(['room_id' => $permintaan->room_id_baru]);

            return;
        }

        $anggota = $booking->groupMembers()->get();

        foreach ($anggota as $m) {
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
    }

    /**
     * Alasan yang membuat usulan tidak bisa diterapkan SEKARANG.
     *
     * Yang diperiksa adalah bentrok kamar pada rentang tanggal yang akan
     * berlaku, dengan mengabaikan pesanan itu sendiri.
     */
    private function galatTerap(BookingRequest $permintaan, Booking $booking): ?string
    {
        $anggota = $booking->groupMembers()->get();
        $abaikan = $anggota->pluck('id')->all();

        // Rentang & kamar yang akan berlaku setelah usulan diterapkan.
        $mulai = $permintaan->jenis === BookingRequest::UBAH_TANGGAL
            ? Carbon::parse($permintaan->check_in_baru)
            : $booking->check_in->copy();

        $durasi = $permintaan->jenis === BookingRequest::PERPANJANG
            ? (int) $permintaan->durasi_baru
            : (int) $booking->durasi;

        $selesai = $mulai->copy()->addDays(max(1, $durasi));

        // Pindah kamar hanya menyentuh baris ini; jenis lain menyentuh seluruh
        // pesanan, jadi seluruh kamarnya ikut diperiksa pada rentang baru.
        $diperiksa = $permintaan->jenis === BookingRequest::PINDAH_KAMAR
            ? [(int) $permintaan->room_id_baru]
            : $anggota->pluck('room_id')->map(fn ($id) => (int) $id)->all();

        $bentrok = [];

        foreach (array_unique($diperiksa) as $roomId) {
            $ada = Booking::where('room_id', $roomId)
                ->whereNotIn('id', $abaikan)
                ->whereDate('check_in', '<', $selesai->toDateString())
                ->whereDate('check_out', '>', $mulai->toDateString())
                ->exists();

            if ($ada) {
                $bentrok[] = Room::find($roomId)?->nomor ?? ('#' . $roomId);
            }
        }

        if ($bentrok !== []) {
            return 'Tidak bisa disetujui: kamar ' . implode(', ', array_unique($bentrok))
                . ' sudah terisi pada ' . $mulai->translatedFormat('j M Y')
                . ' – ' . $selesai->translatedFormat('j M Y')
                . '. Minta staff mengajukan ulang dengan kamar atau tanggal lain.';
        }

        return null;
    }
}
