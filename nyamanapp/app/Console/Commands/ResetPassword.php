<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Setel ulang kata sandi akun dari terminal.
 *
 *     php artisan user:sandi eca@contoh.com
 *     php artisan user:sandi eca@contoh.com --acak
 *     php artisan user:sandi --semua --acak
 *
 * Ada di sini karena halaman Kelola Akun kini hanya bisa dibuka superadmin.
 * Kalau sandi superadmin-nya sendiri yang hilang, tidak ada pintu tersisa
 * lewat antarmuka — dan satu-satunya alternatif adalah mengedit hash di
 * phpMyAdmin, yang jauh lebih rawan salah daripada perintah ini.
 *
 * Dua aturan yang dijaga:
 *
 *   1. Sandi yang diketik TIDAK PERNAH muncul di layar maupun di riwayat
 *      perintah shell. Ia diminta lewat prompt tersembunyi, bukan lewat
 *      argumen — argumen tersimpan di ~/.bash_history dan terbaca siapa pun
 *      yang bisa masuk ke akun hosting.
 *
 *   2. `remember_token` ikut diputar. Tanpa itu, peramban yang pernah
 *      mencentang "ingat saya" tetap bisa masuk memakai sandi lama — reset
 *      yang tidak mereset apa-apa.
 */
class ResetPassword extends Command
{
    protected $signature = 'user:sandi
                            {email? : Email akun yang direset}
                            {--semua : Reset SELURUH akun sekaligus}
                            {--acak : Buatkan sandi acak, jangan diketik}';

    protected $description = 'Setel ulang kata sandi sebuah akun (atau semua akun)';

    public function handle(): int
    {
        $email = $this->argument('email');
        $semua = (bool) $this->option('semua');

        if ($semua && $email !== null) {
            $this->error('Pilih salah satu: sebutkan email, ATAU pakai --semua. Tidak keduanya.');

            return self::FAILURE;
        }

        if (! $semua && $email === null) {
            $this->error('Sebutkan email akun, atau pakai --semua untuk mereset semuanya.');
            $this->line('');
            $this->line('  php artisan user:sandi admin@contoh.com');
            $this->line('  php artisan user:sandi --semua --acak');
            $this->line('');
            $this->line('Lupa emailnya? Jalankan: php artisan user:list');

            return self::FAILURE;
        }

        $target = $semua
            ? User::orderBy('role')->orderBy('name')->get()
            : User::where('email', $email)->get();

        if ($target->isEmpty()) {
            $this->error("Akun {$email} tidak ditemukan. Cek daftarnya dengan: php artisan user:list");

            return self::FAILURE;
        }

        // Reset borongan menyentuh akun orang lain juga, jadi ia harus
        // disengaja — bukan sesuatu yang terjadi karena salah ketik.
        if ($semua) {
            $this->warn('Akan mereset kata sandi ' . $target->count() . ' akun:');
            foreach ($target as $u) {
                $this->line('  - ' . $u->email . '  (' . $u->role . ')');
            }
            $this->line('');
            $this->warn('Semua orang harus diberi tahu sandi barunya. Sesi yang sedang berjalan');
            $this->warn('TIDAK ikut terputus (driver sesi: file) — hapus storage/framework/sessions/*');
            $this->warn('bila ingin memaksa semua orang login ulang.');
            $this->line('');

            if (! $this->confirm('Lanjutkan?', false)) {
                $this->info('Dibatalkan.');

                return self::SUCCESS;
            }
        }

        $acak = (bool) $this->option('acak');

        // Satu sandi diketik sekali untuk satu akun. Untuk reset borongan,
        // sandi selalu acak per akun — memberi satu sandi yang sama ke semua
        // orang menghapus arti seluruh jejak "siapa mengerjakan apa".
        $sandiSeragam = null;

        if (! $acak && ! $semua) {
            $sandiSeragam = $this->secret('Kata sandi baru (minimal 12 karakter, tidak ditampilkan)');
            $ulang        = $this->secret('Ulangi kata sandi');

            if ($sandiSeragam !== $ulang) {
                $this->error('Kata sandi tidak sama.');

                return self::FAILURE;
            }

            if (mb_strlen((string) $sandiSeragam) < 12) {
                $this->error('Kata sandi minimal 12 karakter.');

                return self::FAILURE;
            }
        }

        if (! $acak && $semua) {
            $this->line('Reset borongan selalu memakai sandi acak per akun.');
            $acak = true;
        }

        $hasil = [];

        foreach ($target as $u) {
            $sandi = $acak ? Str::password(16, symbols: false) : $sandiSeragam;

            $u->forceFill([
                'password'       => Hash::make($sandi),
                // Cookie "ingat saya" yang lama ikut mati bersama token ini.
                'remember_token' => Str::random(60),
            ])->save();

            $hasil[] = [$u->name, $u->email, $u->role, $acak ? $sandi : '(yang Anda ketik)'];
        }

        $this->line('');
        $this->table(['Nama', 'Email', 'Peran', 'Kata Sandi Baru'], $hasil);

        if ($acak) {
            $this->line('');
            $this->warn('CATAT SEKARANG. Kata sandi di atas tidak ditampilkan lagi,');
            $this->warn('dan tidak tersimpan di mana pun dalam bentuk yang bisa dibaca.');
            $this->line('');
            $this->line('Setelah masuk, ganti sendiri lewat halaman Kelola Akun.');
        }

        // Peringatan yang paling gampang terlewat: sandi sudah benar tapi
        // akunnya nonaktif, lalu login ditolak dan orangnya menyangka
        // sandinya salah lagi.
        $nonaktif = $target->where('aktif', false);
        if ($nonaktif->isNotEmpty()) {
            $this->line('');
            $this->warn('Akun berikut NONAKTIF — sandinya sudah direset, tapi tetap tidak bisa login:');
            foreach ($nonaktif as $u) {
                $this->line('  - ' . $u->email);
            }
            $this->line('Aktifkan lewat halaman Kelola Akun setelah superadmin bisa masuk.');
        }

        // Tanpa superadmin, halaman Kelola Akun tidak bisa dibuka siapa pun.
        if (User::where('role', User::ROLE_SUPERADMIN)->where('aktif', true)->count() === 0) {
            $this->line('');
            $this->error('PERHATIAN: tidak ada superadmin aktif. Halaman Kelola Akun tidak bisa dibuka.');
            $this->line('Angkat satu: php artisan user:peran ' . ($target->first()->email ?? 'email@anda.com') . ' superadmin');
        }

        return self::SUCCESS;
    }
}
