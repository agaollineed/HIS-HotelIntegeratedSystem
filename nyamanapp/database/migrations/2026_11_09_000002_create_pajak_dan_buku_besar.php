<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pajak bulanan dan buku besar (arus kas BNI & BRI).
 *
 * pajak_bulanan
 *     Satu baris per bulan: nominal pajak dan kapan dibayar. tanggal_bayar
 *     kosong = belum dibayar.
 *
 * buku_besar
 *     Satu baris per hari: uang masuk dan keluar di BNI dan BRI — salinan
 *     lembar "SALDO BANK" di Excel. Saldo akhir TIDAK disimpan; ia selalu
 *     dihitung dari saldo awal + seluruh uang masuk − uang keluar sampai hari
 *     itu, jadi mengoreksi satu hari otomatis membetulkan semua hari sesudahnya.
 *
 * buku_besar_awal
 *     Saldo awal sebelum pencatatan dimulai (31 Maret 2026). Satu baris saja.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pajak_bulanan', function (Blueprint $table) {
            $table->id();
            $table->date('periode')->unique();            // selalu tanggal 1
            $table->unsignedBigInteger('nominal');
            $table->date('tanggal_bayar')->nullable();
            $table->foreignId('dicatat_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('buku_besar', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal')->unique();
            $table->unsignedBigInteger('masuk_bni')->default(0);
            $table->unsignedBigInteger('masuk_bri')->default(0);
            $table->unsignedBigInteger('keluar_bni')->default(0);
            $table->unsignedBigInteger('keluar_bri')->default(0);
            $table->string('keterangan', 500)->nullable();
            $table->foreignId('dicatat_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('buku_besar_awal', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');                       // hari SEBELUM pencatatan pertama
            $table->bigInteger('bni')->default(0);
            $table->bigInteger('bri')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buku_besar_awal');
        Schema::dropIfExists('buku_besar');
        Schema::dropIfExists('pajak_bulanan');
    }
};
