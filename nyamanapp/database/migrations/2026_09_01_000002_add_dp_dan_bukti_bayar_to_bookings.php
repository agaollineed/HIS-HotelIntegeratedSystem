<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uang muka (DP) dan bukti pembayaran saat pesanan DIBUAT.
 *
 * ── Kenapa DP ──
 * Sebelumnya pembayaran hanya punya dua keadaan: lunas atau belum. Kenyataan
 * di meja depan ada di antara keduanya — tamu menitipkan sebagian uang saat
 * memesan, sisanya dibayar ketika tiba. Tanpa tempat mencatatnya, angka itu
 * hanya hidup di ingatan penerima pesanan, dan sisa tagihan tidak bisa
 * ditagihkan oleh orang yang berbeda pada shift berikutnya.
 *
 * dp_nominal berlaku untuk SATU PESANAN, bukan per kamar — sama seperti nama
 * tamu, tanggal, dan status_bayar yang juga disalin ke setiap baris kamar
 * dalam grup. Sisa tagihan DIHITUNG, tidak disimpan, supaya tidak mungkin
 * berbeda dengan total kamar yang sedang berlaku.
 *
 * ── Kenapa foto_bayar_awal kolomnya sendiri ──
 * Ia TIDAK menumpang foto_pelunasan, karena keduanya menjawab pertanyaan
 * berbeda:
 *
 *   foto_bayar_awal   bukti uang yang masuk saat pesanan dibuat
 *   foto_pelunasan    bukti sisa tagihan yang dibayar saat check-in
 *
 * Kalau digabung, DP yang sudah berfoto akan membuat pemeriksaan check-in
 * menganggap sisanya sudah dibayar, dan pelunasannya lolos tanpa bukti —
 * persis celah yang ingin ditutup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('dp_nominal')->default(0)->after('status_bayar');
            $table->string('foto_bayar_awal')->nullable()->after('foto_pelunasan');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['dp_nominal', 'foto_bayar_awal']);
        });
    }
};
