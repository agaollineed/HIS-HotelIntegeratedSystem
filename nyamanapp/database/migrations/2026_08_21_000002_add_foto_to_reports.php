<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lampiran foto pada tiket laporan.
 *
 * Laporan berupa teks saja sering berhenti jadi perdebatan ("separah apa?").
 * Satu foto membuat admin bisa menilai sendiri tanpa harus datang ke lokasi.
 * Disimpan di disk PRIVAT seperti bukti lain, dibuka lewat route ber-login.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->string('foto')->nullable()->after('isi');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn('foto');
        });
    }
};
