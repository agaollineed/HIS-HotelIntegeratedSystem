<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grup pesanan: satu tamu bisa memesan beberapa kamar sekaligus.
 * Semua kamar dalam satu kali submit memakai ULID `grup` yang sama,
 * sehingga edit/hapus/check-in bisa diterapkan ke seluruh grup.
 * Baris lama (sebelum fitur ini) bernilai NULL dan diperlakukan
 * sebagai grup tunggal berisi dirinya sendiri.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('grup', 26)->nullable()->index()->after('room_id');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['grup']);
            $table->dropColumn('grup');
        });
    }
};
