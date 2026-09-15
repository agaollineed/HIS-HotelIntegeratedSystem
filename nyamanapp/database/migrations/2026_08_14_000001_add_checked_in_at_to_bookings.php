<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flow baru: booking dibuat cukup dengan detail (kamar, tanggal, tamu).
 * Saat tamu datang, staff menekan "Tamu Check-in" lalu mengunggah foto KTP —
 * waktu check-in dicatat di kolom ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('checked_in_at')->nullable()->after('foto_ktp');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('checked_in_at');
        });
    }
};
