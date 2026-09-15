<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Retensi data pribadi: hapus foto KTP tamu yang sudah lama check-out.
 *
 * Foto KTP hanya dibutuhkan selama tamu menginap dan sebentar sesudahnya
 * untuk keperluan administrasi. Menyimpannya selamanya menambah risiko tanpa
 * manfaat, jadi jadwalkan perintah ini lewat cron.
 *
 *   php artisan ktp:purge --days=30
 *   php artisan ktp:purge --days=30 --dry-run
 *
 * Foto KARTU (kolom foto_kartu) sengaja TIDAK ikut dihapus di sini. Foto KTP
 * dihapus karena data pribadi tamu; foto kartu bukan data pribadi melainkan
 * bukti pengawasan internal, dan justru berguna saat selisih baru ketahuan
 * berbulan-bulan kemudian. Ukurannya kecil, jadi biarkan tersimpan.
 */
class PurgeKtpPhotos extends Command
{
    protected $signature = 'ktp:purge
                            {--days=30 : Hapus foto milik booking yang check-out lebih dari N hari lalu}
                            {--dry-run : Tampilkan saja apa yang akan dihapus, tanpa menghapus}';

    protected $description = 'Hapus foto KTP untuk booking yang sudah lama selesai (retensi data pribadi)';

    public function handle(): int
    {
        $hari = max(1, (int) $this->option('days'));
        $uji  = (bool) $this->option('dry-run');
        $batas = now()->subDays($hari)->startOfDay();

        $bookings = Booking::whereNotNull('foto_ktp')
            ->whereDate('check_out', '<', $batas->toDateString())
            ->get();

        if ($bookings->isEmpty()) {
            $this->info("Tidak ada foto KTP yang lebih tua dari {$hari} hari sejak check-out.");

            return self::SUCCESS;
        }

        $disk = Storage::disk('local');
        $berkasDihapus = 0;

        foreach ($bookings as $booking) {
            $path = $booking->foto_ktp;

            // Foto dipakai bersama satu grup pesanan: berkas fisik baru boleh
            // dihapus jika tidak ada booking LAIN di luar daftar ini yang memakainya.
            $masihDipakai = Booking::where('foto_ktp', $path)
                ->whereNotIn('id', $bookings->pluck('id'))
                ->exists();

            if ($uji) {
                $this->line(sprintf(
                    '  [uji] kamar %-5s %-20s check-out %s%s',
                    $booking->room?->nomor ?? '-',
                    $booking->nama_tamu,
                    $booking->check_out->toDateString(),
                    $masihDipakai ? ' (berkas dipertahankan, dipakai booking lain)' : ''
                ));

                continue;
            }

            if (! $masihDipakai && $path && $disk->exists($path)) {
                $disk->delete($path);
                $berkasDihapus++;
            }

            $booking->forceFill(['foto_ktp' => null])->save();
        }

        if ($uji) {
            $this->info($bookings->count() . ' foto akan dihapus (mode uji, tidak ada perubahan).');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Selesai: %d catatan dibersihkan, %d berkas foto dihapus dari penyimpanan.',
            $bookings->count(),
            $berkasDihapus
        ));

        return self::SUCCESS;
    }
}
