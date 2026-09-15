<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Menampilkan daftar akun beserta perannya — untuk memastikan tidak ada
 * akun uji yang tertinggal sebelum aplikasi dibuka ke publik.
 *
 *   php artisan user:list
 */
class ListUsers extends Command
{
    protected $signature = 'user:list';

    protected $description = 'Tampilkan seluruh akun beserta perannya';

    public function handle(): int
    {
        $users = User::orderBy('role')->orderBy('email')->get(['name', 'email', 'role', 'created_at']);

        if ($users->isEmpty()) {
            $this->warn('Belum ada akun. Buat dengan: php artisan user:create');

            return self::SUCCESS;
        }

        $this->table(
            ['Nama', 'Email', 'Peran', 'Dibuat'],
            $users->map(fn (User $u) => [
                $u->name,
                $u->email,
                $u->role,
                $u->created_at?->translatedFormat('j M Y') ?? '-',
            ])->all()
        );

        return self::SUCCESS;
    }
}
