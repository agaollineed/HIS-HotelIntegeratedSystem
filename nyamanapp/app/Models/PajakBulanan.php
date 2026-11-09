<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Pajak satu bulan: nominal dan tanggal dibayar (kosong = belum dibayar).
 */
class PajakBulanan extends Model
{
    protected $table = 'pajak_bulanan';

    protected $fillable = ['periode', 'nominal', 'tanggal_bayar', 'dicatat_by'];

    protected $casts = [
        'periode'       => 'date',
        'nominal'       => 'integer',
        'tanggal_bayar' => 'date',
    ];

    /**
     * Mulai bulan ini, tanggal bayar = tanggal pembayaran dicentang.
     * Bulan-bulan sebelumnya dicatat dibayar tanggal 5 bulan berikutnya.
     */
    public const MULAI_TANGGAL_NYATA = '2026-11-01';

    /** Tanggal pembayaran bulan-bulan lama: tanggal 5 bulan berikutnya. */
    public const TANGGAL_BAYAR_LAMA = 5;

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_by');
    }

    public function dibayar(): bool
    {
        return $this->tanggal_bayar !== null;
    }

    /** Tanggal bayar yang dicatat bila pajak $periode dicentang dibayar pada $sekarang. */
    public static function tanggalBayarUntuk(Carbon $periode, ?Carbon $sekarang = null): Carbon
    {
        $sekarang ??= now();

        return $periode->greaterThanOrEqualTo(Carbon::parse(self::MULAI_TANGGAL_NYATA))
            ? $sekarang->copy()->startOfDay()
            : $periode->copy()->startOfMonth()->addMonthNoOverflow()->day(self::TANGGAL_BAYAR_LAMA);
    }

    public function labelPeriode(): string
    {
        return $this->periode->translatedFormat('F Y');
    }

    public function kunciPeriode(): string
    {
        return $this->periode->format('Y-m');
    }
}
