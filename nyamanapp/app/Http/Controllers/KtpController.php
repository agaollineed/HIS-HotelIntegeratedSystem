<?php

namespace App\Http\Controllers;

use App\Models\Absensi;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Penyaji foto KTP.
 *
 * Foto KTP adalah data pribadi, jadi TIDAK disimpan di disk `public`
 * (yang dilayani langsung oleh web server tanpa autentikasi). File berada di
 * disk privat `local` (storage/app/ktp) dan hanya bisa dibuka lewat route ini,
 * yang berada di balik middleware `auth`.
 */
class KtpController extends Controller
{
    public function show(Request $request, Booking $booking): BinaryFileResponse
    {
        abort_if($booking->foto_ktp === null, 404);

        return $this->sajikan($booking->foto_ktp, 'ktp/', 'ktp-' . $booking->id);
    }

    /**
     * Foto kartu kunci yang dikembalikan tamu saat check-out.
     *
     * Bukan data pribadi, tetapi tetap disajikan lewat jalur yang sama:
     * ini bukti pengawasan internal, dan tidak ada alasan membiarkannya
     * bisa diambil siapa pun yang menebak URL-nya.
     */
    public function kartu(Request $request, Booking $booking): BinaryFileResponse
    {
        return $this->sajikan($booking->foto_kartu, 'kartu/', 'kartu-' . $booking->id);
    }

    /** Bukti pelunasan yang diunggah saat check-in. */
    public function pelunasan(Request $request, Booking $booking): BinaryFileResponse
    {
        return $this->sajikan($booking->foto_pelunasan, 'bayar/', 'pelunasan-' . $booking->id);
    }

    /** Bukti pembayaran yang diterima saat pesanan dibuat (lunas di muka / DP). */
    public function bayarAwal(Request $request, Booking $booking): BinaryFileResponse
    {
        return $this->sajikan($booking->foto_bayar_awal, 'bayar/', 'bayar-awal-' . $booking->id);
    }

    /** Bukti pembayaran selisih saat tagihan naik (upgrade kamar / tambah malam). */
    public function bayarTambahan(Request $request, Booking $booking): BinaryFileResponse
    {
        return $this->sajikan($booking->foto_bayar_tambahan, 'bayar/', 'bayar-tambahan-' . $booking->id);
    }

    /** Bukti pembayaran kelebihan jam pada late check-out. */
    public function pembayaranLate(Request $request, Booking $booking): BinaryFileResponse
    {
        return $this->sajikan($booking->foto_pembayaran_late, 'bayar/', 'late-' . $booking->id);
    }

    /** Foto kerusakan / barang tertinggal saat check-out. */
    public function kerusakan(Request $request, Booking $booking): BinaryFileResponse
    {
        return $this->sajikan($booking->foto_kerusakan, 'kerusakan/', 'kerusakan-' . $booking->id);
    }

    /** Lampiran foto pada tiket laporan. */
    public function laporan(Request $request, \App\Models\Report $report): BinaryFileResponse
    {
        return $this->sajikan($report->foto, 'laporan/', 'laporan-' . $report->id);
    }

    /**
     * Foto selfie absensi karyawan — juga data pribadi, aturan sama.
     */
    public function absensi(Request $request, Absensi $absensi): BinaryFileResponse
    {
        return $this->sajikan($absensi->foto, 'absensi/', 'absensi-' . $absensi->id);
    }

    /**
     * Foto laci kartu kunci saat karyawan absen (hitungan kartu per shift).
     */
    public function absensiKartu(Request $request, Absensi $absensi): BinaryFileResponse
    {
        return $this->sajikan($absensi->foto_kartu, 'absensi/', 'kartu-absensi-' . $absensi->id);
    }

    /**
     * Pertahanan berlapis: pastikan path benar-benar berada di folder yang
     * diizinkan dan tidak mengandung upaya keluar direktori.
     */
    private function sajikan(?string $path, string $folder, string $namaBerkas): BinaryFileResponse
    {
        abort_if($path === null, 404);
        abort_unless(str_starts_with($path, $folder) && ! str_contains($path, '..'), 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        return response()->file($disk->path($path), [
            // Jangan sampai tersimpan di cache browser/proxy bersama.
            'Cache-Control'           => 'private, no-store, max-age=0, must-revalidate',
            'Pragma'                  => 'no-cache',
            'X-Content-Type-Options'  => 'nosniff',
            'Content-Disposition'     => 'inline; filename="' . $namaBerkas . '"',
            'Referrer-Policy'         => 'no-referrer',
        ]);
    }
}
