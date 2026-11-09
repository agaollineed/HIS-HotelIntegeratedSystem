<?php

namespace App\Support;

use App\Models\Booking;
use Illuminate\Support\Collection;

/**
 * Penanda kartu pesanan untuk tombol filter di halaman Kamar:
 * Tiket.com / Umum, Breakfast, DP, Belum Lunas.
 *
 * Penyaringannya terjadi di peramban (seketika, tanpa memuat ulang), jadi
 * yang dihitung di sini hanya penanda yang ditempel ke setiap kartu.
 */
final class FilterKamar
{
    /** [kunci => [label, kelompok]] — dalam satu kelompok berlaku "atau", antarkelompok "dan". */
    public const PILIHAN = [
        'tiket' => ['Tiket.com', 'sumber'],
        'umum'  => ['Umum', 'sumber'],
        'bf'    => ['Breakfast', 'bf'],
        'dp'    => ['DP', 'bayar'],
        'belum' => ['Belum Lunas', 'bayar'],
    ];

    /**
     * Penanda satu pesanan (kumpulan baris booking segrup).
     *
     * @param  Collection<int, Booking>  $grup
     * @return list<string>
     */
    public static function tag(Collection $grup): array
    {
        /** @var Booking $utama */
        $utama = $grup->first();

        return array_values(array_filter([
            $utama->dariTiket() ? 'tiket' : 'umum',
            $grup->contains(fn (Booking $b) => (bool) $b->breakfast) ? 'bf' : null,
            match ($utama->status_bayar) {
                Booking::BAYAR_DP    => 'dp',
                Booking::BAYAR_LUNAS => 'lunas',
                default              => 'belum',
            },
        ]));
    }

    /**
     * Jumlah pesanan per pilihan, untuk angka kecil di tombolnya.
     *
     * @param  Collection<int, Collection<int, Booking>>  $grupGrup
     * @return array<string, int>
     */
    public static function hitung(Collection $grupGrup): array
    {
        $n = array_fill_keys(array_keys(self::PILIHAN), 0);

        foreach ($grupGrup as $grup) {
            foreach (self::tag($grup) as $t) {
                if (isset($n[$t])) {
                    $n[$t]++;
                }
            }
        }

        return $n;
    }
}
