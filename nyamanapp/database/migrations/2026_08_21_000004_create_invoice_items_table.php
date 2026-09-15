<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baris rincian invoice.
 *
 * Sebelumnya satu invoice hanya bisa memuat satu tipe kamar, sehingga tamu
 * yang memesan King dan Family sekaligus tidak bisa ditagih dalam satu
 * dokumen. Rincian dipisah ke tabel sendiri supaya satu invoice bisa memuat
 * beberapa baris kamar sekaligus baris biaya tambahan (late check-out,
 * early check-in, dan lain-lain).
 *
 * subtotal disimpan, bukan dihitung ulang saat tampil: invoice yang sudah
 * diserahkan ke tamu tidak boleh berubah angkanya kalau tarif kamar naik.
 *
 * Rumus seragam untuk semua jenis: jumlah_kamar × malam × harga.
 * Untuk baris biaya, kedua pengali bernilai 1 sehingga subtotal = harga.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('jenis', 10)->default('kamar');   // kamar | biaya
            $table->string('uraian');                        // "Kamar King" / "Late check-out"
            $table->string('keterangan')->nullable();        // "101, 102" / "2 jam"
            $table->unsignedInteger('jumlah_kamar')->default(1);
            $table->unsignedInteger('malam')->default(1);
            $table->unsignedBigInteger('harga')->default(0);
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
        });

        // Kolom lama menjadi opsional: rinciannya kini ada di invoice_items.
        // Nilainya tetap diisi (ringkasan baris pertama) supaya daftar dan
        // export lama tidak perlu dibongkar.
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('tipe_kamar', 30)->nullable()->change();
            $table->unsignedInteger('malam')->nullable()->change();
            $table->unsignedBigInteger('harga_per_malam')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
