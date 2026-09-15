<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoice / tagihan tamu.
 *
 * Nilai-nilainya DISALIN, bukan dibaca ulang dari booking saat ditampilkan.
 * Sebuah invoice adalah dokumen yang sudah diserahkan ke tamu: kalau tarif
 * kamar naik bulan depan, invoice yang sudah dicetak tidak boleh ikut
 * berubah angkanya. Karena itu tipe kamar, harga per malam, dan totalnya
 * dibekukan di baris ini.
 *
 * booking_id hanya penanda asal (boleh kosong) supaya invoice tetap bisa
 * dibuat untuk hal di luar pesanan yang tercatat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('nomor', 30)->unique();          // INV/2026/08/0001
            $table->unsignedBigInteger('booking_id')->nullable()->index();

            $table->string('nama_tamu');
            $table->string('tipe_kamar', 30);               // King / Twin / Family
            $table->string('nomor_kamar', 60)->nullable();  // boleh beberapa: "101, 102"
            $table->unsignedInteger('malam');
            $table->unsignedBigInteger('harga_per_malam');
            $table->unsignedBigInteger('total');

            $table->date('tanggal_masuk')->nullable();
            $table->date('tanggal_keluar')->nullable();

            // Keterangan tambahan yang memengaruhi tagihan.
            $table->boolean('breakfast')->default(false);
            $table->boolean('early_checkin')->default(false);
            $table->boolean('lunas')->default(false);
            $table->text('catatan')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
