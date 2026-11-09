<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pindah kamar per hari + bukti pembayaran tambahan.
 *
 * bookings.pindah_dari_id
 *     Baris ini MELANJUTKAN masa menginap baris lain di kamar berbeda.
 *     Tamu 202 yang pindah ke 203 mulai malam kedua menjadi dua baris:
 *     202 (malam pertama) dan 203 (malam berikutnya), dan baris 203
 *     menunjuk ke baris 202. Dari tautan inilah sistem tahu bahwa
 *     "check-in" ke 203 adalah perpindahan, bukan kedatangan baru — jadi
 *     tidak ada biaya early check-in, KTP tidak diminta ulang, dan kamar
 *     202 otomatis ditutup.
 *
 * bookings.foto_bayar_tambahan
 *     Bukti pembayaran SELISIH ketika tagihan naik setelah pesanan dibayar
 *     (upgrade kamar atau tambah malam). Disimpan terpisah dari bukti awal
 *     dan bukti pelunasan, supaya bukti yang sudah ada tidak tertimpa.
 *
 * booking_requests.pindah_mulai
 *     Tanggal mulai pada pengajuan pindah kamar dari staff. Kosong atau sama
 *     dengan tanggal check-in = seluruh masa menginap pindah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('pindah_dari_id')->nullable()->after('grup')
                ->constrained('bookings')->nullOnDelete();
            $table->string('foto_bayar_tambahan')->nullable()->after('foto_bayar_awal');
        });

        Schema::table('booking_requests', function (Blueprint $table) {
            $table->date('pindah_mulai')->nullable()->after('room_id_baru');
        });
    }

    public function down(): void
    {
        Schema::table('booking_requests', function (Blueprint $table) {
            $table->dropColumn('pindah_mulai');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pindah_dari_id');
            $table->dropColumn('foto_bayar_tambahan');
        });
    }
};
