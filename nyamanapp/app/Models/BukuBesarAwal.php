<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Saldo awal buku besar — satu baris, hari sebelum pencatatan pertama.
 */
class BukuBesarAwal extends Model
{
    protected $table = 'buku_besar_awal';

    protected $fillable = ['tanggal', 'bni', 'bri'];

    protected $casts = [
        'tanggal' => 'date',
        'bni'     => 'integer',
        'bri'     => 'integer',
    ];

    /** Saldo awal bawaan: "tanggal 0 April 2026". */
    public const TANGGAL_BAWAAN = '2026-03-31';

    public static function aktif(): self
    {
        return static::query()->first()
            ?? new static(['tanggal' => self::TANGGAL_BAWAAN, 'bni' => 0, 'bri' => 0]);
    }

    /** Simpan saldo awal (hanya ada satu baris). */
    public static function simpan(string $tanggal, int $bni, int $bri): self
    {
        $awal = static::query()->first() ?? new static();
        $awal->fill(['tanggal' => $tanggal, 'bni' => $bni, 'bri' => $bri])->save();

        return $awal;
    }

    public function total(): int
    {
        return $this->bni + $this->bri;
    }
}
