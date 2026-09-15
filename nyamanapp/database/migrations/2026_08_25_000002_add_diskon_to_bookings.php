<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diskon langsung pada pemesanan.
 *
 * Potongan harga yang diberikan di meja depan — tamu langganan, rombongan,
 * atau kamar yang kondisinya kurang. Sebelumnya potongan seperti ini tidak
 * punya tempat sama sekali: satu-satunya cara mencatatnya adalah lewat
 * kesepakatan lisan, yang justru tidak meninggalkan jejak apa pun.
 *
 *   diskon_nominal   rupiah yang DIPOTONG dari tagihan kamar
 *
 * Angka ini berlaku untuk SATU PESANAN dan disalin ke setiap baris kamar dalam
 * grup — persis seperti nama tamu dan tanggal — jadi dibaca sekali, bukan
 * dijumlahkan antar baris.
 *
 * Urutan hitungannya tetap: tagihan = total kamar - diskon, lalu
 * sisa = tagihan - yang sudah dibayar. Diskon memotong tagihan, bukan
 * menambah uang masuk, sehingga laporan pendapatan tetap jujur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('diskon_nominal')->default(0)->after('status_bayar');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('diskon_nominal');
        });
    }
};
