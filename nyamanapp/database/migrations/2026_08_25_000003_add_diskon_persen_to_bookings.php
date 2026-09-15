<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persentase diskon yang disepakati.
 *
 * Yang menentukan uang tetap `diskon_nominal` — kolom ini hanya MENCATAT
 * bahwa potongannya disepakati dalam bentuk persen, supaya nota dan panel
 * bisa menulis "Diskon 10%" alih-alih hanya nominalnya.
 *
 *   diskon_persen   0 = potongan diisi sebagai nominal rupiah
 *                   1-99 = potongan disepakati sebagai persen
 *
 * Nominalnya tetap dihitung sekali saat menyimpan, lalu dibekukan. Kalau
 * persennya yang dijadikan patokan, mengganti kamar setelah tamu setuju akan
 * diam-diam mengubah potongan yang sudah dijanjikan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedTinyInteger('diskon_persen')->default(0)->after('diskon_nominal');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('diskon_persen');
        });
    }
};
