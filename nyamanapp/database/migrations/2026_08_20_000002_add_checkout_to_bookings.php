<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alur check-out yang tercatat — penutup celah "kamar dijual dua kali".
 *
 * Sebelum ini, kepergian tamu tidak pernah dicatat: booking hanya kedaluwarsa
 * sendiri saat tanggal terlewat. Akibatnya kamar yang ditinggalkan tamu jam 6
 * sore tidak meninggalkan jejak apa pun di sistem, padahal secara fisik sudah
 * kosong dan bisa dijual ulang tanpa masuk pembukuan.
 *
 * Kolom-kolom berikut membuat setiap kepergian meninggalkan jejak:
 *
 *   checked_out_at   waktu SERVER saat check-out diproses (bukan jam ponsel)
 *   checkout_status  awal | tepat | telat, dihitung terhadap batas jam 12.00
 *   checkout_menit   besar penyimpangan dlm menit (sisa sewa / lama telat)
 *   foto_kartu       bukti kartu kunci sudah kembali ke laci
 *   checkout_by      siapa yang memproses — pertanyaan bisa dialamatkan
 *   checkout_catatan keterangan bebas (mis. kesepakatan late check-out)
 *
 * checkin_menit_awal menyimpan sisi sebaliknya: tamu yang datang sebelum jam
 * 14.00 (early check-in), yang berarti kamar terpakai lebih lama dari yang
 * dibayar dan wajib terlihat oleh pemilik.
 *
 * PENTING — check-out TIDAK mengubah check_out. Kamar tetap terkunci di
 * sistem sampai batas waktu sewanya habis, sehingga tidak bisa dijual ulang
 * diam-diam. Kalau kamar memang mau dijual lagi, jalurnya adalah booking baru
 * yang tercatat.
 *
 * Nilai status & menit DISIMPAN, bukan dihitung ulang saat ditampilkan, agar
 * perubahan jam standar hotel di kemudian hari tidak mengubah riwayat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedInteger('checkin_menit_awal')->default(0)->after('checked_in_at');
            $table->timestamp('checked_out_at')->nullable()->after('checkin_menit_awal');
            $table->string('checkout_status', 10)->nullable()->after('checked_out_at');
            $table->unsignedInteger('checkout_menit')->default(0)->after('checkout_status');
            $table->string('foto_kartu')->nullable()->after('checkout_menit');
            $table->string('checkout_catatan')->nullable()->after('foto_kartu');
            // Tanpa constrained(): SQLite tidak bisa menambah foreign key lewat
            // ALTER TABLE, dan kolom ini hanya perlu menyimpan jejak siapa yang
            // memproses — bukan menegakkan integritas relasi.
            $table->unsignedBigInteger('checkout_by')->nullable()->after('checkout_catatan');

            $table->index('checked_out_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['checked_out_at']);
            $table->dropColumn([
                'checkin_menit_awal', 'checked_out_at', 'checkout_status',
                'checkout_menit', 'foto_kartu', 'checkout_catatan', 'checkout_by',
            ]);
        });
    }
};
