<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saldo rekening harian.
 *
 * Satu baris = saldo rekening pada satu tanggal, diketik dari aplikasi bank.
 * Hanya dua angka yang penting: tanggal dan saldonya. Dari deret inilah
 * grafik saldo harian dan perbandingan saldo setiap tanggal 5 digambar.
 *
 * Tanggal dibuat unik: mengisi ulang tanggal yang sama berarti mengoreksi,
 * bukan menambah baris kedua yang diam-diam ikut tergambar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saldo_harian', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal')->unique();
            $table->unsignedBigInteger('saldo');
            $table->foreignId('dicatat_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saldo_harian');
    }
};
