<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Ubah peran sebuah akun dari baris perintah.
 *
 *     php artisan user:peran eca@contoh.com superadmin
 *
 * Ada di sini sebagai JALAN KELUAR DARURAT. Halaman Kelola Akun kini hanya
 * bisa dibuka superadmin; kalau akun superadmin satu-satunya hilang aksesnya,
 * tidak ada lagi pintu lewat antarmuka. Perintah ini menyediakan pintu itu
 * lewat terminal cPanel, tanpa perlu menyentuh tabel database langsung.
 */
class SetRole extends Command
{
    protected $signature = 'user:peran {email : Email akun} {peran : superadmin|admin|staff}';

    protected $description = 'Ubah peran sebuah akun (superadmin / admin / staff)';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $peran = (string) $this->argument('peran');

        if (! in_array($peran, User::ROLES, true)) {
            $this->error('Peran tidak dikenal. Pilihannya: ' . implode(', ', User::ROLES) . '.');

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();

        if ($user === null) {
            $this->error("Akun {$email} tidak ditemukan.");

            return self::FAILURE;
        }

        // Menurunkan superadmin terakhir akan mengunci aplikasi dari dirinya
        // sendiri — aturan yang sama seperti di halaman Kelola Akun.
        if ($user->isSuperadmin() && $peran !== User::ROLE_SUPERADMIN) {
            $lain = User::where('role', User::ROLE_SUPERADMIN)
                ->where('aktif', true)
                ->where('id', '!=', $user->id)
                ->count();

            if ($lain === 0) {
                $this->error("{$user->name} adalah superadmin aktif terakhir. Angkat superadmin lain lebih dulu.");

                return self::FAILURE;
            }
        }

        $sebelum = $user->role;
        $user->forceFill(['role' => $peran])->save();

        $this->info("Peran {$user->name} ({$email}): {$sebelum} -> {$peran}");

        return self::SUCCESS;
    }
}
