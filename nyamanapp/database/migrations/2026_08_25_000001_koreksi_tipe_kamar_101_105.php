<?php

use App\Models\Room;
use Illuminate\Database\Migrations\Migration;

/**
 * Koreksi tipe kamar: 101 menjadi Twin, 105 menjadi King.
 *
 * Yang berubah hanya KOLOM TIPE pada dua baris kamar. Tidak ada kamar yang
 * dihapus atau dibuat ulang, sehingga `rooms.id` tetap — dan seluruh booking
 * yang menunjuk ke kamar itu, termasuk yang sudah check-out berbulan lalu,
 * tetap tersambung ke kamar yang sama.
 *
 * Konsekuensi yang disengaja: booking LAMA di kamar 101 dan 105 akan ikut
 * menampilkan tipe yang baru. Tipe kamar tidak disalin ke tabel booking — ia
 * selalu dibaca dari kamarnya — jadi memang tidak ada tipe lama yang tersimpan
 * untuk dipertahankan. Tarif per malam kedua tipe ini sama (Rp 300.000 dengan
 * sarapan, Rp 250.000 tanpa), sehingga tidak ada nilai uang yang bergeser.
 */
return new class extends Migration
{
    /** nomor kamar => tipe yang benar */
    private const KOREKSI = [
        '101' => 'twin',
        '105' => 'king',
    ];

    public function up(): void
    {
        foreach (self::KOREKSI as $nomor => $tipe) {
            Room::where('nomor', $nomor)->update(['tipe' => $tipe]);
        }
    }

    /**
     * Kembalikan ke keadaan semula: 101 King, 105 Twin.
     */
    public function down(): void
    {
        foreach (['101' => 'king', '105' => 'twin'] as $nomor => $tipe) {
            Room::where('nomor', $nomor)->update(['tipe' => $tipe]);
        }
    }
};
