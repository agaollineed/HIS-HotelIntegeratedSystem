<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Peran baru: superadmin.
 *
 * Kolom `role` sudah berupa string bebas, jadi tidak ada skema yang perlu
 * diubah. Yang WAJIB dikerjakan migrasi ini adalah memastikan setelah deploy
 * masih ada satu orang yang bisa mengelola akun.
 *
 * Tanpa langkah ini, begitu wewenang "kelola akun" berpindah ke superadmin
 * dan belum ada satu pun akun superadmin, tidak akan ada yang bisa membuat
 * akun atau mengganti kata sandi siapa pun — termasuk membuat superadmin
 * pertama. Aplikasi terkunci dari dirinya sendiri.
 *
 * Karena itu: akun ADMIN TERTUA (id terkecil, yang aktif) dinaikkan menjadi
 * superadmin. Itu akun pemilik pada pemasangan yang sudah berjalan. Bila
 * ternyata bukan orang yang tepat, superadmin itu bisa memindahkan perannya
 * lewat halaman Kelola Akun, atau lewat:
 *
 *     php artisan user:peran email@contoh.com superadmin
 */
return new class extends Migration
{
    public function up(): void
    {
        // Sudah ada superadmin (mis. migrasi dijalankan ulang) — jangan
        // menaikkan siapa pun lagi.
        if (DB::table('users')->where('role', 'superadmin')->exists()) {
            return;
        }

        $calon = DB::table('users')
            ->where('role', 'admin')
            ->where('aktif', true)
            ->orderBy('id')
            ->first();

        // Tidak ada admin aktif: coba admin mana pun, supaya pemasangan yang
        // adminnya sedang dinonaktifkan tetap punya jalan masuk.
        $calon ??= DB::table('users')->where('role', 'admin')->orderBy('id')->first();

        if ($calon === null) {
            return;
        }

        DB::table('users')->where('id', $calon->id)->update(['role' => 'superadmin']);
    }

    /**
     * Turunkan kembali ke admin. Peran superadmin tidak dikenal pada versi
     * sebelumnya, dan akun bersisa peran asing akan gagal semua pemeriksaan.
     */
    public function down(): void
    {
        DB::table('users')->where('role', 'superadmin')->update(['role' => 'admin']);
    }
};
