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
}
