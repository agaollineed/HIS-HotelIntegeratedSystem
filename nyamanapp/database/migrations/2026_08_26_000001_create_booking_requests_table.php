<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permintaan perubahan booking dari staff.
 *
 * Mengubah kamar, tanggal, atau nama tamu berarti mengubah jejak yang sudah
 * tercatat — dan jejak itulah alat pengawasannya. Karena itu staff tidak
 * mengubah langsung: ia MENGAJUKAN, dan perubahannya baru berlaku setelah
 * admin menyetujui.
 *
 * SATU PERMINTAAN = SATU JENIS PERUBAHAN = SATU PERSETUJUAN. Kolom `jenis`
 * menentukan kolom usulan mana yang terisi; sisanya null. Admin dengan
 * demikian selalu memutuskan satu hal yang jelas — bukan sekumpulan perubahan
 * yang harus diterima atau ditolak sekaligus.
 *
 * Yang disimpan adalah NILAI YANG DIUSULKAN, bukan perintah untuk mengubah.
 * Nilai lama tidak ikut disimpan karena masih ada di baris booking-nya sampai
 * detik persetujuan; menyalinnya hanya akan melahirkan dua sumber kebenaran
 * yang bisa berbeda.
 *
 * Baris permintaan TIDAK pernah dihapus setelah diputus. Yang ditolak pun
 * tetap tersimpan: pertanyaan "siapa pernah minta apa, dan siapa menolaknya"
 * adalah bagian dari pengawasan, bukan sampah yang boleh dibersihkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();

            // pindah_kamar | perpanjang | ubah_tanggal | ubah_tamu
            $table->string('jenis', 20);

            // Nilai yang diusulkan. Hanya kolom milik `jenis` yang terisi.
            $table->foreignId('room_id_baru')->nullable()->constrained('rooms')->nullOnDelete();
            $table->string('nama_tamu_baru')->nullable();
            $table->string('telepon_baru')->nullable();
            $table->date('check_in_baru')->nullable();
            $table->unsignedSmallInteger('durasi_baru')->nullable();

            // Alasan WAJIB diisi staff. Perubahan tanpa alasan adalah persis
            // hal yang paling sulit ditelusuri belakangan.
            $table->text('alasan');

            $table->string('status', 20)->default('menunggu'); // menunggu | disetujui | ditolak
            $table->text('catatan_admin')->nullable();

            $table->foreignId('diajukan_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('diputus_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diputus_at')->nullable();

            $table->timestamps();

            // Panel admin selalu bertanya "apa yang masih menunggu?", dan
            // halaman booking bertanya "apakah baris ini sedang diajukan?".
            $table->index(['status', 'created_at']);
            $table->index(['booking_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_requests');
    }
};
