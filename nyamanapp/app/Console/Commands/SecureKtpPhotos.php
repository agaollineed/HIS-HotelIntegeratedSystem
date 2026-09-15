<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Pemindah sekali jalan: foto KTP versi lama tersimpan di disk `public`
 * (storage/app/public/ktp) yang bisa diakses siapa pun lewat URL. Perintah ini
 * memindahkannya ke disk privat `local` (storage/app/ktp).
 *
 *   php artisan ktp:secure
 */
class SecureKtpPhotos extends Command
{
    protected $signature = 'ktp:secure {--dry-run : Tampilkan saja berkas yang akan dipindahkan}';

    protected $description = 'Pindahkan foto KTP lama dari penyimpanan publik ke penyimpanan privat';

    public function handle(): int
    {
        $publik = Storage::disk('public');
        $privat = Storage::disk('local');
        $uji    = (bool) $this->option('dry-run');

        if (! $publik->exists('ktp')) {
            $this->info('Tidak ada folder ktp/ di penyimpanan publik — tidak ada yang perlu dipindahkan.');

            return self::SUCCESS;
        }

        $berkas = $publik->files('ktp');

        if ($berkas === []) {
            $this->info('Folder ktp/ publik sudah kosong.');

            return self::SUCCESS;
        }

        $dipindah = 0;

        foreach ($berkas as $path) {
            if ($uji) {
                $this->line('  [uji] ' . $path . ' -> privat');

                continue;
            }

            if ($privat->exists($path)) {
                $this->warn('  lewati (sudah ada di privat): ' . $path);

                continue;
            }

            $privat->put($path, $publik->get($path));
            $publik->delete($path);
            $dipindah++;
        }

        if ($uji) {
            $this->info(count($berkas) . ' berkas akan dipindahkan (mode uji).');

            return self::SUCCESS;
        }

        $this->info($dipindah . ' foto KTP dipindahkan ke penyimpanan privat.');
        $this->line('Path di database tidak berubah (tetap "ktp/namafile"), jadi tidak perlu migrasi data.');

        return self::SUCCESS;
    }
}
