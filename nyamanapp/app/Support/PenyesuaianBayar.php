<?php

namespace App\Support;

use App\Models\Booking;

/**
 * Menyesuaikan status pembayaran pesanan setelah tagihannya berubah.
 *
 * Masalah yang ditutup: status "Lunas" selama ini berarti "tagihan berapa pun
 * sudah terbayar". Tamu yang lunas 2 malam di kamar King lalu upgrade ke
 * Family otomatis ikut "lunas" untuk selisihnya, padahal uang tambahan itu
 * belum pernah diterima — dan tidak muncul sebagai sisa di mana pun.
 *
 * Aturannya sekarang:
 *
 *   - Yang dicatat adalah UANG YANG SUDAH DITERIMA sebelum perubahan.
 *   - Bila tagihan baru melebihi uang itu dan tamu belum membayar selisihnya,
 *     pesanan "Lunas" turun menjadi "DP" sebesar uang yang sudah diterima.
 *     Selisihnya muncul sebagai sisa, dan ditagih saat check-in (atau saat
 *     check-out bila tamu sudah di dalam).
 *   - Bila tamu membayar selisihnya saat itu juga, pesanan tetap "Lunas" —
 *     dengan bukti pembayaran tambahan yang disimpan pemanggil.
 *   - Diskon persen dihitung ulang dari total baru; diskon nominal tetap.
 *   - Tagihan yang turun di bawah uang yang sudah diterima dilaporkan
 *     sebagai kelebihan bayar, supaya tidak hilang diam-diam.
 */
final class PenyesuaianBayar
{
    /**
     * Potret keadaan uang pesanan SEBELUM diubah.
     *
     * @return array{tagihan: int, dibayar: int}
     */
    public static function potret(Booking $booking): array
    {
        $anggota = $booking->groupMembers()->with('room')->get();
        $utama   = $anggota->firstWhere('id', $booking->id) ?? $booking;
        $total   = Booking::totalPesananDari($anggota);

        return [
            'tagihan' => $utama->tagihanPesanan($total),
            'dibayar' => $utama->dibayar($total),
        ];
    }

    /**
     * Terapkan penyesuaian pada SELURUH anggota pesanan.
     *
     * @param  array{tagihan: int, dibayar: int}  $sebelum  hasil potret()
     * @param  bool  $lunasSekarang  tamu membayar seluruh sisanya saat ini
     * @return array{tagihan_lama: int, tagihan_baru: int, dibayar: int, sisa: int, kelebihan: int, status: string, turun_ke_dp: bool}
     */
    public static function terapkan(Booking $booking, array $sebelum, bool $lunasSekarang = false): array
    {
        $anggota = $booking->groupMembers()->with('room')->get();
        $utama   = $anggota->firstWhere('id', $booking->id) ?? $anggota->first();
        $total   = Booking::totalPesananDari($anggota);

        // Diskon persen mengikuti total baru, seperti di formulir ubah.
        $persen = $utama->diskonPersen();
        $diskon = $persen > 0
            ? Booking::nominalDariPersen($total, $persen)
            : $utama->diskonPesanan();
        $diskon = min($diskon, max(0, $total - 1));

        $tagihanBaru = max(0, $total - $diskon);
        $dibayarLama = (int) $sebelum['dibayar'];

        $status = (string) $utama->status_bayar;
        $dp     = $utama->dpPesanan();
        $turun  = false;

        if ($lunasSekarang) {
            $status = Booking::BAYAR_LUNAS;
        } elseif ($tagihanBaru > $dibayarLama && $status === Booking::BAYAR_LUNAS) {
            $status = Booking::BAYAR_DP;
            $dp     = $dibayarLama;
            $turun  = true;
        } elseif ($status === Booking::BAYAR_DP && $dp >= $tagihanBaru) {
            // Tagihan turun sampai tertutup uang muka: pesanan jadi lunas.
            $status = Booking::BAYAR_LUNAS;
        }

        // Pesanan yang belum pernah membayar apa pun tetap "Belum Lunas".
        if ($status === Booking::BAYAR_DP && $dp <= 0) {
            $status = Booking::BAYAR_BELUM;
            $dp     = 0;
        }

        foreach ($anggota as $m) {
            $m->forceFill([
                'status_bayar'   => $status,
                'dp_nominal'     => $status === Booking::BAYAR_LUNAS ? $m->dp_nominal : $dp,
                'diskon_nominal' => $diskon,
            ])->save();
        }

        $dibayarBaru = Booking::dibayarUntuk($status, $dp, $tagihanBaru);

        return [
            'tagihan_lama' => (int) $sebelum['tagihan'],
            'tagihan_baru' => $tagihanBaru,
            'dibayar'      => $dibayarBaru,
            'sisa'         => max(0, $tagihanBaru - $dibayarBaru),
            'kelebihan'    => max(0, $dibayarLama - $tagihanBaru),
            'status'       => $status,
            'turun_ke_dp'  => $turun,
        ];
    }

    /**
     * Kalimat ringkas hasil penyesuaian untuk pesan sukses.
     *
     * @param  array<string, mixed>  $hasil  keluaran terapkan()
     */
    public static function kalimat(array $hasil): string
    {
        $lama = (int) $hasil['tagihan_lama'];
        $baru = (int) $hasil['tagihan_baru'];

        if ($lama === $baru) {
            return '';
        }

        $teks = ' Tagihan pesanan ' . ($baru > $lama ? 'naik' : 'turun') . ' dari '
            . Booking::rupiah($lama) . ' menjadi ' . Booking::rupiah($baru) . '.';

        if ($hasil['sisa'] > 0) {
            $teks .= ' Sisa yang harus ditagih: ' . Booking::rupiah((int) $hasil['sisa']) . '.';
        }

        if ($hasil['kelebihan'] > 0) {
            $teks .= ' Tamu kelebihan bayar ' . Booking::rupiah((int) $hasil['kelebihan'])
                . ' — kembalikan atau catat sebagai deposit.';
        }

        return $teks;
    }
}
