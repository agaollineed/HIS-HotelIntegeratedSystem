<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foto kartu kunci saat absen.
 *
 * Tujuannya bukan sekadar bukti kehadiran, melainkan HITUNGAN KARTU pada
 * pergantian shift. Setiap kali karyawan absen, ia memotret laci kartu apa
 * adanya. Hasilnya: ada rekaman visual bertanggal jumlah kartu yang tersisa
 * di setiap awal shift, yang bisa dicocokkan dengan data booking.
 *
 * Kalau kamar 201 tercatat kosong tetapi kartunya tidak ada di laci pada foto
 * shift malam, selisih itu langsung terlihat — tanpa perlu menonton CCTV
 * berjam-jam lebih dulu.
 *
 * Sama seperti foto selfie: disimpan di disk PRIVAT dan hanya bisa dibuka
 * lewat route yang butuh login.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absensi', function (Blueprint $table) {
            $table->string('foto_kartu')->nullable()->after('foto');
        });
    }

    public function down(): void
    {
        Schema::table('absensi', function (Blueprint $table) {
            $table->dropColumn('foto_kartu');
        });
    }
};
