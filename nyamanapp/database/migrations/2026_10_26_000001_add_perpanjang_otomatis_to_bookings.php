<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perpanjangan otomatis karena over late check-out.
 *
 * Tamu yang masih di kamar lewat jam 17.00 pada tanggal check-out dianggap
 * menambah satu malam di kamar yang sama — selama kamar itu belum dipesan
 * tamu lain untuk malam tersebut.
 *
 * bookings.perpanjang_otomatis_malam
 *     Berapa malam yang ditambahkan sistem dengan cara ini.
 *
 * bookings.perpanjang_otomatis_at
 *     Kapan perpanjangan terakhir terjadi. Dipakai supaya tamu yang pulang
 *     pada malam itu juga tercatat over late check-out, bukan early check-out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedSmallInteger('perpanjang_otomatis_malam')->default(0)->after('late_ditandai_by');
            $table->timestamp('perpanjang_otomatis_at')->nullable()->after('perpanjang_otomatis_malam');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['perpanjang_otomatis_malam', 'perpanjang_otomatis_at']);
        });
    }
};
