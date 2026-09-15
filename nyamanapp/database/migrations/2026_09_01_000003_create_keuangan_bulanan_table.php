<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keuangan bulanan — angka NYATA yang diterima pemilik.
 *
 * Ini bukan salinan dari laporan pendapatan. Grafik pendapatan dihitung dari
 * booking yang tercatat di aplikasi; tabel ini berisi uang yang benar-benar
 * masuk ke rekening. Keduanya sengaja dipisah karena justru SELISIH di antara
 * keduanya yang berharga: pendapatan tercatat Rp 40 juta sementara setoran
 * Rp 34 juta adalah pertanyaan yang harus dijawab, dan pertanyaan itu tidak
 * akan pernah muncul kalau angkanya berasal dari sumber yang sama.
 *
 * Satu baris = satu bulan. Kolom `periode` disimpan sebagai tanggal 1 bulan
 * tersebut (2026-04-01) supaya bisa diurutkan dan dibandingkan sebagai tanggal
 * sungguhan, bukan sebagai teks "April 2026" yang urutannya alfabetis.
 *
 * Pengeluaran ikut dicatat supaya laba bersih bisa dibaca tanpa kalkulator
 * terpisah. Catatannya bebas — di sanalah alasan bulan yang menyimpang
 * dituliskan, dan itu yang paling sering dicari setahun kemudian.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keuangan_bulanan', function (Blueprint $table) {
            $table->id();
            $table->date('periode')->unique();          // selalu tanggal 1
            $table->unsignedBigInteger('pemasukan')->default(0);
            $table->unsignedBigInteger('pengeluaran')->default(0);
            $table->text('catatan')->nullable();
            $table->foreignId('dicatat_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keuangan_bulanan');
    }
};
