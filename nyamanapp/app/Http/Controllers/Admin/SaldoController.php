<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\BukuBesarLaporan;
use App\Support\CsvKeuangan;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Live report saldo rekening — admin dan superadmin.
 *
 * Saldo TIDAK diketik lagi: setiap angka di halaman ini dihitung dari Buku
 * Besar (saldo awal + uang masuk − uang keluar, BNI dan BRI). Mencatat atau
 * mengoreksi transaksi di Buku Besar otomatis menggeser grafik di sini, dan
 * halamannya menyegarkan diri sendiri tanpa perlu dimuat ulang.
 */
class SaldoController extends Controller
{
    /** Banyaknya hari bertransaksi yang ditampilkan di bawah grafik. */
    private const TRANSAKSI_TERAKHIR = 7;

    public function index(): View
    {
        $deret = BukuBesarLaporan::deret();

        return view('admin.keuangan.saldo', [
            'ringkas'   => $this->ringkasan($deret),
            'transaksi' => collect($deret)->where('ada', true)->reverse()->take(self::TRANSAKSI_TERAKHIR)->values(),
        ]);
    }

    /** Data grafik (JSON) — dipanggil berkala supaya halaman tetap live. */
    public function data(): JsonResponse
    {
        $deret = BukuBesarLaporan::deret();

        return response()->json([
            'harian'     => array_map(fn ($h) => [
                'tanggal' => $h['tanggal'],
                'label'   => $h['label'],
                'saldo'   => $h['saldo'],
                'bni'     => $h['bni'],
                'bri'     => $h['bri'],
            ], $deret),
            'lima'       => BukuBesarLaporan::perTanggalLima($deret),
            'ringkas'    => $this->ringkasan($deret),
            'diperbarui' => now()->translatedFormat('H:i:s'),
        ]);
    }

    /** Unduh saldo akhir setiap hari (hasil hitungan Buku Besar) sebagai CSV. */
    public function exportCsv()
    {
        return CsvKeuangan::unduh(
            'saldo-harian-' . now()->format('Ymd') . '.csv',
            ['tanggal', 'saldo_bni', 'saldo_bri', 'total_saldo'],
            array_map(fn ($h) => [$h['tanggal'], $h['bni'], $h['bri'], $h['saldo']], BukuBesarLaporan::deret())
        );
    }

    /**
     * Angka ringkas di atas halaman: saldo saat ini (BNI, BRI, total), hasil
     * bersih hari transaksi terakhir, dan selisihnya dari tanggal 5 terakhir.
     *
     * @param  list<array<string, mixed>>  $deret
     * @return array<string, mixed>
     */
    private function ringkasan(array $deret): array
    {
        $kini     = $deret === [] ? null : $deret[array_key_last($deret)];
        $terakhir = collect($deret)->where('ada', true)->last();
        $lima     = collect(BukuBesarLaporan::perTanggalLima($deret))->whereNotNull('saldo')->last();

        return [
            'saldo'        => $kini['saldo'] ?? null,
            'bni'          => $kini['bni'] ?? null,
            'bri'          => $kini['bri'] ?? null,
            'tanggal'      => $kini ? Carbon::parse($kini['tanggal'])->translatedFormat('j M Y') : null,
            'perubahan'    => $terakhir ? $terakhir['masuk'] - $terakhir['keluar'] : null,
            'tanggalUbah'  => $terakhir ? Carbon::parse($terakhir['tanggal'])->translatedFormat('j M') : null,
            'vsLima'       => ($kini && $lima) ? $kini['saldo'] - $lima['saldo'] : null,
            'tanggalLima'  => $lima ? Carbon::parse($lima['tanggal'])->translatedFormat('j M') : null,
        ];
    }
}
