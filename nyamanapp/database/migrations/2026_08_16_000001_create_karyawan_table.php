<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daftar karyawan yang masuk jadwal shift.
 *
 * Sengaja dipisah dari tabel `users`: yang perlu dijadwalkan dan diabsen
 * adalah orangnya, bukan akun aplikasi. Satu akun staff di meja depan bisa
 * dipakai bersama, sementara absensi tetap tercatat per orang (dan foto
 * selfie-nya yang menjadi bukti kehadiran).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('karyawan', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('karyawan');
    }
};
