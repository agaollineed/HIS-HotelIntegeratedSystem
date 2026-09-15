<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Logika kalender hunian yang dipakai BERSAMA oleh panel staff & admin.
 *
 * Sengaja disatukan di satu tempat: sebelumnya tiap controller menghitung
 * sendiri, dan perbedaan kecil pada perbandingan tanggal membuat angka di
 * kalender tidak cocok dengan daftar di bawahnya.
 */
trait HandlesRoomCalendar
{
    /**
     * Bulan yang ditampilkan (?bulan=YYYY-MM) dan tanggal terpilih
     * (?tanggal=YYYY-MM-DD, default hari ini bila bulannya bulan berjalan).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function monthAndSelected(Request $request): array
    {
        $bulanParam = (string) $request->query('bulan', '');
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulanParam)
            ? Carbon::createFromFormat('Y-m-d', $bulanParam . '-01')->startOfDay()
            : now()->startOfMonth()->startOfDay();

        $tanggalParam = (string) $request->query('tanggal', '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalParam) && strtotime($tanggalParam)) {
            $selected = Carbon::createFromFormat('Y-m-d', $tanggalParam)->startOfDay();
        } elseif ($month->isSameMonth(now())) {
            $selected = now()->startOfDay();
        } else {
            $selected = $month->copy();
        }

        return [$month, $selected];
    }

    /**
     * Jumlah kamar TERISI untuk tiap tanggal dalam bulan tsb
     * (aturan sama persis dengan Booking::occupyingOn: check_in <= T < check_out).
     *
     * @return Collection<string, int>
     */
    protected function terisiPerHari(Carbon $month): Collection
    {
        $awal  = $month->copy()->startOfMonth();
        $akhir = $month->copy()->endOfMonth();

        $bookings = Booking::query()
            ->whereDate('check_in', '<=', $akhir->toDateString())
            ->whereDate('check_out', '>', $awal->toDateString())
            ->get(['room_id', 'check_in', 'check_out']);

        $hasil = collect();

        for ($d = $awal->copy(); $d->lte($akhir); $d->addDay()) {
            $key = $d->toDateString();
            $n = $bookings->filter(
                fn (Booking $b) => $b->check_in->toDateString() <= $key && $b->check_out->toDateString() > $key
            )->pluck('room_id')->unique()->count();

            if ($n > 0) {
                $hasil[$key] = $n;
            }
        }

        return $hasil;
    }

    /**
     * Booking yang menginap pada tanggal tsb, terurut nomor kamar.
     */
    protected function bookingsOccupying(Carbon $tanggal): Collection
    {
        return Booking::with(['room', 'creator'])
            ->occupyingOn($tanggal)
            ->get()
            ->sortBy(fn (Booking $b) => (int) preg_replace('/\D/', '', (string) $b->room?->nomor))
            ->values();
    }
}
