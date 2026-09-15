<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Status aktif akun.
 *
 * Akun TIDAK pernah dihapus, hanya dinonaktifkan. Nama pembuat menempel di
 * banyak catatan lama (booking, invoice, log stok, tanda late) — menghapus
 * akunnya berarti memutus jejak "siapa mengerjakan apa", padahal jejak itu
 * justru alasan aplikasi ini dibuat. Staff yang keluar cukup dinonaktifkan:
 * tidak bisa masuk lagi, riwayatnya tetap utuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('aktif')->default(true)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('aktif');
        });
    }
};
