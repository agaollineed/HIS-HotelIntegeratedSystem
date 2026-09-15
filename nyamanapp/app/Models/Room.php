<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    protected $fillable = [
        'nomor', // contoh: "101", "202"
        'tipe',  // king | twin | family
    ];

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Tarif kamar per malam (rupiah) DENGAN sarapan.
     * King & Twin sama; Family lebih mahal.
     */
    public const HARGA = [
        'king'   => 300000,
        'twin'   => 300000,
        'family' => 450000,
    ];

    /**
     * Tarif per malam TANPA sarapan.
     *
     * Family sengaja TIDAK ada di daftar ini: kamar family selalu dijual
     * dengan sarapan, jadi memang tidak punya tarif tanpa sarapan. Daftar
     * inilah yang dipakai bolehTanpaBreakfast() untuk menutup pilihannya
     * di formulir maupun di validasi server.
     */
    public const HARGA_TANPA_BF = [
        'king' => 250000,
        'twin' => 250000,
    ];

    /** Tipe kamar ini boleh dijual tanpa sarapan? */
    public function bolehTanpaBreakfast(): bool
    {
        return array_key_exists($this->tipe, self::HARGA_TANPA_BF);
    }

    /**
     * Tarif per malam menurut pilihan sarapan.
     * Tipe yang wajib sarapan selalu memakai tarif penuh.
     */
    public function hargaPerMalam(bool $breakfast = true): int
    {
        if (! $breakfast && $this->bolehTanpaBreakfast()) {
            return self::HARGA_TANPA_BF[$this->tipe];
        }

        return self::HARGA[$this->tipe] ?? 0;
    }

    /**
     * Label tipe untuk tampilan (King / Twin / Family).
     */
    public function labelTipe(): string
    {
        return ucfirst($this->tipe);
    }
}
