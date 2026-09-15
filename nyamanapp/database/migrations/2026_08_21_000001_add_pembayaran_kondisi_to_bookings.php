<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Melengkapi alur pesanan: asal pesanan, status pembayaran, dan kondisi kamar
 * saat ditinggalkan.
 *
 *   sumber        umum | tiket  — pesanan lewat Tiket.com selalu sudah lunas,
 *                                 jadi statusnya dikunci saat pencatatan.
 *   status_bayar  lunas | belum — menentukan apakah bukti pelunasan wajib
 *                                 diunggah saat tamu check-in.
 *
 * Tiga foto tambahan, masing-masing menutup satu celah uang:
 *
 *   foto_pelunasan        bukti tamu membayar sisa tagihan saat tiba
 *   foto_pembayaran_late  bukti pembayaran kelebihan jam pada late check-out
 *   foto_kerusakan        bukti kerusakan / barang tertinggal saat check-out
 *
 * Kondisi kamar saat check-out wajib dinyatakan (checkout_kondisi_ok). Bila
 * ada masalah, laporannya langsung menjadi tiket untuk admin dan nomor
 * tiketnya disimpan di report_id, sehingga temuan tidak berhenti sebagai
 * catatan lepas yang tidak pernah ditindaklanjuti.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('sumber', 10)->default('umum')->after('telepon');
            $table->string('status_bayar', 10)->default('belum')->after('sumber');
            $table->string('foto_pelunasan')->nullable()->after('foto_ktp');

            $table->timestamp('late_ditandai_at')->nullable()->after('checkout_menit');
            $table->unsignedBigInteger('late_ditandai_by')->nullable()->after('late_ditandai_at');
            $table->string('foto_pembayaran_late')->nullable()->after('late_ditandai_by');

            $table->boolean('checkout_kondisi_ok')->default(true)->after('foto_kartu');
            $table->string('foto_kerusakan')->nullable()->after('checkout_kondisi_ok');
            $table->unsignedBigInteger('report_id')->nullable()->after('foto_kerusakan');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'sumber', 'status_bayar', 'foto_pelunasan',
                'late_ditandai_at', 'late_ditandai_by', 'foto_pembayaran_late',
                'checkout_kondisi_ok', 'foto_kerusakan', 'report_id',
            ]);
        });
    }
};
