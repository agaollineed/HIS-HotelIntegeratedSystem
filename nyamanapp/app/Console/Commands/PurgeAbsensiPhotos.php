<?php

namespace App\Console\Commands;

use App\Models\Absensi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Retensi foto absensi: catatan kehadiran (jam & status) tetap disimpan
 * sebagai riwayat, tetapi FOTO selfie-nya dihapus setelah sekian hari.
 * Foto hanya berguna sebagai bukti jangka pendek; menyimpannya selamanya
 * menambah risiko tanpa manfaat.
 *
 *   php artisan absensi:purge --days=60
 */
class PurgeAbsensiPhotos extends Command
{
    protected $signature = 'absensi:purge
                            {--days=60 : Hapus foto absensi yang lebih tua dari N hari}
                            {--dry-run : Tampilkan saja, tanpa menghapus}';

    protected $description = 'Hapus foto selfie absensi lama (riwayat kehadiran tetap tersimpan)';

    public function handle(): int
    {
        $hari  = max(1, (int) $this->option('days'));
        $uji   = (bool) $this->option('dry-run');
        $batas = now()->subDays($hari)->startOfDay();

        $data = Absensi::with('karyawan')
            ->where(fn ($q) => $q->whereNotNull('foto')->orWhereNotNull('foto_kartu'))
            ->whereDate('tanggal', '<', $batas->toDateString())
            ->get();

        if ($data->isEmpty()) {
            $this->info("Tidak ada foto absensi yang lebih tua dari {$hari} hari.");

            return self::SUCCESS;
        }

        $disk = Storage::disk('local');
        $dihapus = 0;

        foreach ($data as $a) {
            if ($uji) {
                $this->line(sprintf('  [uji] %-14s %s', $a->karyawan?->nama ?? '-', $a->tanggal->toDateString()));

                continue;
            }

            // Selfie DAN foto kartu sama-sama bukti jangka pendek.
            foreach (['foto', 'foto_kartu'] as $kolom) {
                if ($a->{$kolom} && $disk->exists($a->{$kolom})) {
                    $disk->delete($a->{$kolom});
                    $dihapus++;
                }
            }

            $a->forceFill(['foto' => null, 'foto_kartu' => null])->save();
        }

        $this->info($uji
            ? $data->count() . ' foto akan dihapus (mode uji, tidak ada perubahan).'
            : sprintf('Selesai: %d catatan dibersihkan, %d berkas dihapus. Riwayat kehadiran tetap utuh.', $data->count(), $dihapus));

        return self::SUCCESS;
    }
}
