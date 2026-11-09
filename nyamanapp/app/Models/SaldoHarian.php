<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Saldo rekening pada satu tanggal, diketik manual dari aplikasi bank.
 */
class SaldoHarian extends Model
{
    protected $table = 'saldo_harian';

    protected $fillable = [
        'tanggal',
        'saldo',
        'dicatat_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'saldo'   => 'integer',
    ];

    /** Perbandingan tanggal 5 dimulai dari bulan ini. */
    public const MULAI_BANDING = '2026-04-01';

    /** Tanggal pembanding setiap bulan. */
    public const TANGGAL_BANDING = 5;

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_by');
    }

    /**
     * Saldo setiap tanggal 5, satu baris per bulan sejak MULAI_BANDING.
     *
     * Bila tanggal 5 tidak dicatat (mis. jatuh pada hari libur), dipakai saldo
     * terakhir antara tanggal 1 dan 5 bulan itu — tanggal yang dipakai ikut
     * dikirim supaya tampilannya jujur. Bila tanggal 1–5 kosong sama sekali,
     * bulan itu dibiarkan kosong, bukan diisi saldo bulan sebelumnya yang
     * sudah basi.
     *
     * @return list<array{bulan: string, label: string, tanggal: string, dipakai: ?string, saldo: ?int, selisih: ?int, belum: bool}>
     */
    public static function perTanggalLima(?Carbon $hariIni = null): array
    {
        $hariIni ??= now();
        $hariIni   = $hariIni->copy()->startOfDay();
        $mulai     = Carbon::parse(self::MULAI_BANDING)->startOfMonth();

        if ($hariIni->lt($mulai)) {
            return [];
        }

        // Cukup baris tanggal 1–5; dibaca sekali lalu dicari di memori.
        $catatan = static::query()
            ->whereDate('tanggal', '>=', $mulai->toDateString())
            ->orderBy('tanggal')
            ->get(['tanggal', 'saldo'])
            ->filter(fn (self $s) => $s->tanggal->day <= self::TANGGAL_BANDING)
            ->groupBy(fn (self $s) => $s->tanggal->format('Y-m'));

        $hasil   = [];
        $sebelum = null;

        for ($b = $mulai->copy(); $b->lte($hariIni->copy()->startOfMonth()); $b->addMonth()) {
            $target = $b->copy()->day(self::TANGGAL_BANDING);
            $baris  = optional($catatan->get($b->format('Y-m')))->last();
            $saldo  = $baris?->saldo;

            $hasil[] = [
                'bulan'   => $b->format('Y-m'),
                'label'   => $b->translatedFormat('M Y'),
                'tanggal' => $target->toDateString(),
                'dipakai' => $baris?->tanggal->toDateString(),
                'saldo'   => $saldo,
                'selisih' => ($saldo !== null && $sebelum !== null) ? $saldo - $sebelum : null,
                // Tanggal 5 bulan berjalan yang belum tiba bukan "tidak dicatat".
                'belum'   => $saldo === null && $target->gt($hariIni),
            ];

            if ($saldo !== null) {
                $sebelum = $saldo;
            }
        }

        return $hasil;
    }

    /** Deret saldo harian untuk grafik, urut tanggal. */
    public static function deret(): Collection
    {
        return static::query()
            ->orderBy('tanggal')
            ->get(['tanggal', 'saldo'])
            ->map(fn (self $s) => [
                'tanggal' => $s->tanggal->toDateString(),
                'label'   => $s->tanggal->translatedFormat('j M Y'),
                'saldo'   => $s->saldo,
            ])
            ->values();
    }

    public static function rupiah(int $n): string
    {
        return ($n < 0 ? '-Rp ' : 'Rp ') . number_format(abs($n), 0, ',', '.');
    }
}
