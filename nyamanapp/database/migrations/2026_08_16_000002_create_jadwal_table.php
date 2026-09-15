<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jadwal shift harian.
 *
 * Karyawan yang LIBUR tidak punya baris di tanggal tersebut — jadi
 * "Full Team" berarti semua karyawan aktif punya baris hari itu.
 *
 * Jam disimpan sebagai teks 'HH:MM' agar sederhana dan sama persis di
 * SQLite maupun MySQL. Shift yang melewati tengah malam (mis. 21:00–07:00)
 * dikenali dari jam_selesai <= jam_mulai.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('karyawan_id')->constrained('karyawan')->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('jam_mulai', 5);
            $table->string('jam_selesai', 5);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['karyawan_id', 'tanggal']);
            $table->index('tanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal');
    }
};
