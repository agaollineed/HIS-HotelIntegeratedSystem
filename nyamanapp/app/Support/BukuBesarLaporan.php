<?php

namespace App\Support;

use App\Models\BukuBesar;
use App\Models\BukuBesarAwal;
use Illuminate\Support\Carbon;

/**
 * Hitungan buku besar: saldo berjalan BNI & BRI per hari.
 *
 * Saldo akhir hari X = saldo awal + seluruh uang masuk − uang keluar sampai
 * hari X. Tidak ada saldo yang disimpan, jadi mengoreksi satu hari otomatis
 * membetulkan saldo semua hari sesudahnya — persis seperti rumus di Excel.
 */
final class BukuBesarLaporan
{
    /** Perbandingan saldo tanggal 5 dimulai dari bulan ini. */
    public const MULAI_BANDING = '2026-04-01';

    /** Tanggal pembanding setiap bulan. */
    public const TANGGAL_BANDING = 5;

    /** Saldo [bni, bri] di AKHIR hari sebelum $tanggal. */
    public static function saldoSebelum(Carbon $tanggal): array
    {
        $awal = BukuBesarAwal::aktif();
        $sum  = BukuBesar::query()
            ->whereDate('tanggal', '<', $tanggal->toDateString())
            ->selectRaw('COALESCE(SUM(masuk_bni),0) mb, COALESCE(SUM(masuk_bri),0) mr, COALESCE(SUM(keluar_bni),0) kb, COALESCE(SUM(keluar_bri),0) kr')
            ->first();

        return [
            $awal->bni + (int) $sum->mb - (int) $sum->kb,
            $awal->bri + (int) $sum->mr - (int) $sum->kr,
        ];
    }

    /** Saldo terkini dan tanggal catatan terakhir. */
    public static function terkini(): array
    {
        [$bni, $bri] = self::saldoSebelum(now()->addDay()->startOfDay());
        $terakhir = BukuBesar::query()->orderByDesc('tanggal')->value('tanggal');

        return [
            'bni'     => $bni,
            'bri'     => $bri,
            'total'   => $bni + $bri,
            'tanggal' => $terakhir ? Carbon::parse($terakhir) : null,
        ];
    }

    /** Bulan pertama pencatatan (bulan sesudah tanggal saldo awal). */
    public static function bulanPertama(): Carbon
    {
        return BukuBesarAwal::aktif()->tanggal->copy()->addDay()->startOfMonth();
    }

    /**
     * Seluruh hari dalam satu bulan — termasuk hari tanpa transaksi, seperti
     * di Excel — sampai hari ini bila bulannya sedang berjalan.
     *
     * @return array{baris: list<array<string, mixed>>, awal: array{bni: int, bri: int}, total: array<string, int>, akhir: array{bni: int, bri: int}}
     */
    public static function bulan(Carbon $bulan): array
    {
        $awalBuku = BukuBesarAwal::aktif();
        $mulai    = $bulan->copy()->startOfMonth()->max($awalBuku->tanggal->copy()->addDay());
        $akhir    = $bulan->copy()->endOfMonth()->startOfDay()->min(now()->startOfDay());

        [$bni, $bri] = self::saldoSebelum($mulai);
        $awal = ['bni' => $bni, 'bri' => $bri];

        $entri = BukuBesar::query()
            ->whereDate('tanggal', '>=', $mulai->toDateString())
            ->whereDate('tanggal', '<=', $bulan->copy()->endOfMonth()->toDateString())
            ->orderBy('tanggal')
            ->get()
            ->keyBy(fn (BukuBesar $b) => $b->tanggal->toDateString());

        // Catatan bertanggal sesudah hari ini (seharusnya tidak ada) tetap ikut tampil.
        if ($entri->isNotEmpty()) {
            $akhir = $akhir->max(Carbon::parse($entri->keys()->last()));
        }

        $baris = [];
        $total = ['masuk_bni' => 0, 'masuk_bri' => 0, 'keluar_bni' => 0, 'keluar_bri' => 0];

        for ($d = $mulai->copy(); $d->lte($akhir); $d->addDay()) {
            /** @var BukuBesar|null $e */
            $e = $entri->get($d->toDateString());
            $mb = $e?->masuk_bni ?? 0;
            $mr = $e?->masuk_bri ?? 0;
            $kb = $e?->keluar_bni ?? 0;
            $kr = $e?->keluar_bri ?? 0;

            $bni += $mb - $kb;
            $bri += $mr - $kr;

            $total['masuk_bni'] += $mb;
            $total['masuk_bri'] += $mr;
            $total['keluar_bni'] += $kb;
            $total['keluar_bri'] += $kr;

            $baris[] = [
                'tanggal'    => $d->copy(),
                'entri'      => $e,
                'masuk_bni'  => $mb,
                'masuk_bri'  => $mr,
                'keluar_bni' => $kb,
                'keluar_bri' => $kr,
                'saldo_bni'  => $bni,
                'saldo_bri'  => $bri,
                'keterangan' => $e?->keterangan,
            ];
        }

        return [
            'baris' => $baris,
            'awal'  => $awal,
            'total' => $total,
            'akhir' => ['bni' => $bni, 'bri' => $bri],
        ];
    }

    /**
     * Saldo akhir [bni, bri] setiap tanggal dalam [$dari, $sampai] — untuk
     * mencocokkan kolom saldo akhir di berkas impor.
     *
     * @return array<string, array{0: int, 1: int}>
     */
    public static function saldoHarian(Carbon $dari, Carbon $sampai): array
    {
        [$bni, $bri] = self::saldoSebelum($dari);
        $entri = BukuBesar::query()
            ->whereDate('tanggal', '>=', $dari->toDateString())
            ->whereDate('tanggal', '<=', $sampai->toDateString())
            ->get()
            ->keyBy(fn (BukuBesar $b) => $b->tanggal->toDateString());

        $hasil = [];
        for ($d = $dari->copy(); $d->lte($sampai); $d->addDay()) {
            if ($e = $entri->get($d->toDateString())) {
                $bni += $e->masuk_bni - $e->keluar_bni;
                $bri += $e->masuk_bri - $e->keluar_bri;
            }
            $hasil[$d->toDateString()] = [$bni, $bri];
        }

        return $hasil;
    }

    /**
     * Saldo akhir SETIAP hari — BNI, BRI, dan totalnya — dari hari pertama
     * buku besar sampai hari ini. Inilah sumber halaman Saldo Rekening:
     * saldonya tidak diketik lagi, melainkan dihitung dari transaksi.
     *
     * @return list<array{tanggal: string, label: string, bni: int, bri: int, saldo: int, masuk: int, keluar: int, ada: bool, keterangan: ?string}>
     */
    public static function deret(?Carbon $sampai = null): array
    {
        $awal   = BukuBesarAwal::aktif();
        $dari   = $awal->tanggal->copy()->addDay()->startOfDay();
        $sampai = ($sampai ?? now())->copy()->startOfDay();

        // Catatan bertanggal sesudah hari ini (seharusnya tidak ada) tetap ikut.
        $terakhir = BukuBesar::query()->max('tanggal');
        if ($terakhir !== null) {
            $sampai = $sampai->max(Carbon::parse($terakhir)->startOfDay());
        }
        if ($sampai->lt($dari)) {
            return [];
        }

        $entri = BukuBesar::query()
            ->whereDate('tanggal', '>=', $dari->toDateString())
            ->whereDate('tanggal', '<=', $sampai->toDateString())
            ->get()
            ->keyBy(fn (BukuBesar $b) => $b->tanggal->toDateString());

        [$bni, $bri] = self::saldoSebelum($dari);
        $hasil = [];

        for ($d = $dari->copy(); $d->lte($sampai); $d->addDay()) {
            /** @var BukuBesar|null $e */
            $e = $entri->get($d->toDateString());
            $masuk  = $e ? $e->masukTotal() : 0;
            $keluar = $e ? $e->keluarTotal() : 0;
            if ($e) {
                $bni += $e->masuk_bni - $e->keluar_bni;
                $bri += $e->masuk_bri - $e->keluar_bri;
            }

            $hasil[] = [
                'tanggal'    => $d->toDateString(),
                'label'      => $d->translatedFormat('D, j M Y'),
                'bni'        => $bni,
                'bri'        => $bri,
                'saldo'      => $bni + $bri,
                'masuk'      => $masuk,
                'keluar'     => $keluar,
                'ada'        => $e !== null && ($masuk > 0 || $keluar > 0),
                'keterangan' => $e?->keterangan,
            ];
        }

        return $hasil;
    }

    /**
     * Saldo akhir hari setiap tanggal 5, satu baris per bulan sejak
     * MULAI_BANDING. Buku besar mencatat setiap hari, jadi tanggal 5 selalu
     * punya saldo — kecuali belum tiba atau sebelum buku besar dimulai.
     *
     * @param  list<array<string, mixed>>  $deret  hasil deret()
     * @return list<array{bulan: string, label: string, tanggal: string, saldo: ?int, bni: ?int, bri: ?int, selisih: ?int, belum: bool}>
     */
    public static function perTanggalLima(array $deret, ?Carbon $hariIni = null): array
    {
        $hariIni = ($hariIni ?? now())->copy()->startOfDay();
        $mulai   = Carbon::parse(self::MULAI_BANDING)->startOfMonth();
        if ($hariIni->lt($mulai)) {
            return [];
        }

        $perTanggal = collect($deret)->keyBy('tanggal');
        $hasil   = [];
        $sebelum = null;

        for ($b = $mulai->copy(); $b->lte($hariIni->copy()->startOfMonth()); $b->addMonth()) {
            $target = $b->copy()->day(self::TANGGAL_BANDING);
            $belum  = $target->gt($hariIni);
            $hari   = $belum ? null : $perTanggal->get($target->toDateString());
            $saldo  = $hari['saldo'] ?? null;

            $hasil[] = [
                'bulan'   => $b->format('Y-m'),
                'label'   => $b->translatedFormat('M Y'),
                'tanggal' => $target->toDateString(),
                'saldo'   => $saldo,
                'bni'     => $hari['bni'] ?? null,
                'bri'     => $hari['bri'] ?? null,
                'selisih' => ($saldo !== null && $sebelum !== null) ? $saldo - $sebelum : null,
                'belum'   => $belum,
            ];

            if ($saldo !== null) {
                $sebelum = $saldo;
            }
        }

        return $hasil;
    }
}
