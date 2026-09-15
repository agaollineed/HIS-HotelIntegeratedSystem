<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan kehadiran harian beserta foto selfie sebagai bukti.
 *
 * `status` dihitung saat absen dilakukan dengan membandingkan waktu kirim
 * terhadap jam_mulai pada jadwal — masuk 07:00 lalu absen 07:01 = telat.
 * Nilainya disimpan (bukan dihitung ulang) supaya perubahan jadwal di
 * kemudian hari tidak mengubah riwayat kehadiran yang sudah terjadi.
 *
 * Foto disimpan di penyimpanan PRIVAT (storage/app/absensi), sama seperti
 * foto KTP, dan hanya bisa dibuka lewat route yang butuh login.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absensi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('karyawan_id')->constrained('karyawan')->cascadeOnDelete();
            $table->foreignId('jadwal_id')->nullable()->constrained('jadwal')->nullOnDelete();
            $table->date('tanggal');
            $table->timestamp('waktu_absen');
            $table->string('status', 10);          // hadir | telat
            $table->unsignedInteger('menit_telat')->default(0);
            $table->string('foto')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['karyawan_id', 'tanggal']);
            $table->index('tanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absensi');
    }
};
