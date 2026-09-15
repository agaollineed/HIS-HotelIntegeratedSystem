<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Membuat atau memperbarui akun pengguna secara interaktif.
 *
 * Dipakai agar kredensial produksi tidak pernah ditulis di dalam kode,
 * seeder, atau riwayat perintah shell — kata sandi diketik tersembunyi.
 *
 *   php artisan user:create
 *   php artisan user:create --email=admin@hotel.com --role=admin
 */
class CreateUser extends Command
{
    protected $signature = 'user:create
                            {--name= : Nama yang ditampilkan}
                            {--email= : Alamat email untuk login}
                            {--role= : superadmin, admin, atau staff}';

    protected $description = 'Buat akun superadmin/admin/staff baru, atau ubah kata sandi akun yang sudah ada';

    public function handle(): int
    {
        $nama  = $this->option('name')  ?: $this->ask('Nama lengkap');
        $email = $this->option('email') ?: $this->ask('Email untuk login');
        $role  = $this->option('role')  ?: $this->choice('Peran', \App\Models\User::ROLES, 'staff');

        $adaSebelumnya = User::where('email', $email)->exists();

        if ($adaSebelumnya && ! $this->confirm("Akun {$email} sudah ada. Ubah nama, peran, dan kata sandinya?", false)) {
            $this->info('Dibatalkan.');

            return self::SUCCESS;
        }

        $sandi = $this->secret('Kata sandi (minimal 12 karakter, tidak ditampilkan)');
        $ulang = $this->secret('Ulangi kata sandi');

        if ($sandi !== $ulang) {
            $this->error('Kata sandi tidak sama.');

            return self::FAILURE;
        }

        $validator = Validator::make(
            ['name' => $nama, 'email' => $email, 'role' => $role, 'password' => $sandi],
            [
                'name'     => 'required|string|max:255',
                'email'    => 'required|email|max:255',
                'role'     => 'required|in:' . implode(',', User::ROLES),
                'password' => 'required|string|min:12',
            ],
            [
                'password.min' => 'Kata sandi minimal 12 karakter.',
                'role.in'      => 'Peran harus superadmin, admin, atau staff.',
                'email.email'  => 'Format email tidak valid.',
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $pesan) {
                $this->error($pesan);
            }

            return self::FAILURE;
        }

        User::updateOrCreate(
            ['email' => $email],
            ['name' => $nama, 'role' => $role, 'password' => Hash::make($sandi)],
        );

        $this->info(($adaSebelumnya ? 'Akun diperbarui' : 'Akun dibuat') . ": {$email} ({$role})");

        return self::SUCCESS;
    }
}
