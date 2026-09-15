<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Pendapatan harian dari hunian kamar.
 *
 * Aturan: sebuah kamar menghasilkan tarif satu malam untuk setiap tanggal T
 * di mana check_in <= T < check_out (konsisten dengan definisi "terisi").
 *
 * Tarif diambil dari Booking::hargaMalam(), BUKAN dari Room::HARGA — karena
 * tarif yang berlaku bergantung pada pilihan sarapan booking itu sendiri:
 * King/Twin tanpa sarapan dijual 250.000, bukan 300.000. Memakai daftar tarif
 * kamar apa adanya membuat grafik melaporkan uang yang tidak pernah masuk,
 * dan selisihnya baru ketahuan saat setoran bulanan dicocokkan.
 */
class RevenueController extends Controller
{
    /**
     * Data grafik satu bulan (JSON) — dipakai dashboard untuk render & auto-refresh.
     */
    public function month(Request $request): JsonResponse
    {
        $month = $this->parseMonth((string) $request->query('bulan', ''));
        $harian = $this->pendapatanHarian($month);

        $nilai   = $harian->pluck('total');
        $total   = $nilai->sum();
        $hariAda = $nilai->filter(fn ($v) => $v > 0)->count();
        $puncak  = $harian->sortByDesc('total')->first();

        return response()->json([
            'bulan'      => $month->format('Y-m'),
            'label'      => $month->translatedFormat('F Y'),
            'prev'       => $month->copy()->subMonth()->format('Y-m'),
            'next'       => $month->copy()->addMonth()->format('Y-m'),
            'is_current' => $month->isSameMonth(now()),
            'today'      => now()->toDateString(),
            'hari'       => $harian->values(),
            'ringkasan'  => [
                'total'       => $total,
                'rata_rata'   => $hariAda > 0 ? (int) round($total / $hariAda) : 0,
                'hari_terisi' => $hariAda,
                'puncak'      => $puncak && $puncak['total'] > 0 ? $puncak : null,
            ],
            'diperbarui' => now()->translatedFormat('H:i:s'),
        ]);
    }

    /**
     * Rincian pendapatan satu tanggal (partial HTML) — muncul saat batang diklik.
     */
    public function day(string $tanggal)
    {
        $selected = Carbon::createFromFormat('Y-m-d', $tanggal)->startOfDay();

        $bookings = Booking::with(['room', 'creator'])
            ->occupyingOn($selected)
            ->get()
            ->sortBy(fn (Booking $b) => (int) preg_replace('/\D/', '', (string) $b->room?->nomor))
            ->values();

        // Rekap dipecah per TIPE + PILIHAN SARAPAN, bukan per tipe saja.
        //
        // Satu tipe kamar bisa terjual pada dua tarif berbeda pada malam yang
        // sama (King dengan sarapan 300rb, King tanpa sarapan 250rb). Kalau
        // keduanya dijadikan satu baris, kolom "harga" harus memilih salah
        // satu, dan barisnya menjadi tidak bisa dicocokkan: jumlah x harga
        // tidak akan sama dengan totalnya.
        $perTipe = $bookings
            ->groupBy(fn (Booking $b) => ($b->room?->tipe ?? 'lainnya') . '|' . ($b->breakfast ? '1' : '0'))
            ->map(function (Collection $g) {
                $contoh = $g->first();
                $tipe   = $contoh->room?->tipe ?? 'lainnya';

                return [
                    'tipe'   => $tipe,
                    'label'  => ucfirst($tipe) . ($contoh->breakfast ? '' : ' (tanpa sarapan)'),
                    'jumlah' => $g->count(),
                    'harga'  => $contoh->hargaMalam(),
                    'total'  => $g->sum(fn (Booking $b) => $b->hargaMalam()),
                ];
            })
            ->sortByDesc('total')
            ->values();

        return view('admin.revenue._day', [
            'selected' => $selected,
            'bookings' => $bookings,
            'perTipe'  => $perTipe,
            'total'    => $bookings->sum(fn (Booking $b) => $b->hargaMalam()),
        ]);
    }

    /**
     * Total pendapatan tercatat untuk satu bulan.
     *
     * Dipakai halaman Keuangan sebagai PEMBANDING setoran nyata. Sengaja
     * memanggil hitungan yang sama dengan grafik harian — dua rumus untuk
     * angka yang sama akan selalu berselisih, dan selisih palsu itu justru
     * menutupi selisih yang sungguhan.
     */
    public static function totalBulan(Carbon $bulan): int
    {
        return (int) (new self())->pendapatanHarian($bulan->copy()->startOfMonth())->sum('total');
    }

    // ------------------------------------------------------------------
    // Helper
    // ------------------------------------------------------------------

    private function parseMonth(string $bulan): Carbon
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulan)
            ? Carbon::createFromFormat('Y-m-d', $bulan . '-01')->startOfDay()
            : now()->startOfMonth()->startOfDay();
    }

    /**
     * Pendapatan tiap tanggal dalam bulan tsb.
     *
     * @return Collection<int, array{tanggal: string, hari: int, total: int, kamar: int}>
     */
    private function pendapatanHarian(Carbon $month): Collection
    {
        $awal  = $month->copy()->startOfMonth();
        $akhir = $month->copy()->endOfMonth();

        // Ambil sekali, hitung di PHP: jauh lebih hemat daripada query per tanggal.
        $bookings = Booking::with('room')
            ->whereDate('check_in', '<=', $akhir->toDateString())
            ->whereDate('check_out', '>', $awal->toDateString())
            ->get();

        $hasil = collect();

        for ($d = $awal->copy(); $d->lte($akhir); $d->addDay()) {
            $key = $d->toDateString();

            $menginap = $bookings->filter(
                fn (Booking $b) => $b->check_in->toDateString() <= $key && $b->check_out->toDateString() > $key
            );

            $hasil->push([
                'tanggal' => $key,
                'hari'    => (int) $d->format('j'),
                'kamar'   => $menginap->count(),
                'total'   => (int) $menginap->sum(fn (Booking $b) => $b->hargaMalam()),
            ]);
        }

        return $hasil;
    }
}
