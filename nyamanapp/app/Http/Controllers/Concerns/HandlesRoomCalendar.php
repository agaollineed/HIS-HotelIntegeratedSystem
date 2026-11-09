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
     * Pencarian tamu lintas tanggal: nama, telepon, atau nomor kamar.
     *
     * Panel tanggal hanya menampilkan satu malam, padahal pertanyaan yang
     * paling sering muncul di meja depan adalah "Pak Budi menginap kapan,
     * di kamar berapa?". Hasilnya dikelompokkan per PESANAN — kamar-kamar
     * yang dipesan bersama, termasuk kamar hasil pindah per hari, tampil
     * sebagai satu kunjungan dengan satu tautan ke kalender.
     *
     * Urutan: yang sedang menginap, lalu yang akan datang (terdekat dulu),
     * lalu yang sudah lewat (terbaru dulu).
     *
     * @return array{0: string, 1: Collection|null}
     */
    protected function hasilPencarian(Request $request): array
    {
        $cari = trim((string) $request->query('cari', ''));

        if (mb_strlen($cari) < 2) {
            return [$cari, null];
        }

        $angka = ctype_digit($cari);

        $cocok = Booking::query()
            ->where(function ($q) use ($cari, $angka) {
                $q->where('nama_tamu', 'like', '%' . $cari . '%')
                  ->orWhere('telepon', 'like', '%' . $cari . '%');

                // Nomor kamar dicocokkan PERSIS: "10" tidak boleh menyeret
                // seluruh kamar 101–108.
                if ($angka) {
                    $q->orWhereHas('room', fn ($r) => $r->where('nomor', $cari));
                }
            })
            ->orderByDesc('check_in')
            ->limit(200)
            ->get(['id', 'grup']);

        if ($cocok->isEmpty()) {
            return [$cari, collect()];
        }

        // Seluruh anggota pesanan ikut dimuat, supaya pesanan dua kamar yang
        // cocok lewat satu nomor kamar tetap tampil lengkap.
        $grup   = $cocok->pluck('grup')->filter()->unique()->values();
        $tunggal = $cocok->whereNull('grup')->pluck('id');

        $semua = Booking::with('room')
            ->where(fn ($q) => $q->whereIn('grup', $grup)->orWhereIn('id', $tunggal))
            ->get();

        $hariIni = now()->startOfDay();

        $hasil = $semua
            ->groupBy(fn (Booking $b) => $b->grup ?? ('single-' . $b->id))
            ->map(function (Collection $anggota) use ($hariIni) {
                $anggota = $anggota
                    ->sortBy(fn (Booking $b) => $b->check_in->format('Ymd') . sprintf('%06d', (int) preg_replace('/\D/', '', (string) $b->room?->nomor)))
                    ->values();

                $awal  = $anggota->min(fn (Booking $b) => $b->check_in->copy()->startOfDay());
                $akhir = $anggota->max(fn (Booking $b) => $b->check_out->copy()->startOfDay());
                $utama = $anggota->first();

                $menginap = $anggota->contains(fn (Booking $b) => $b->isCheckedIn() && ! $b->isCheckedOut());
                $selesai  = $anggota->every(fn (Booking $b) => $b->isCheckedOut());

                $status = match (true) {
                    $menginap                => 'menginap',
                    $selesai                 => 'selesai',
                    $akhir->lte($hariIni)    => 'lewat',
                    default                  => 'akan',
                };

                // Tanggal yang dibuka di kalender: hari ini bila kunjungannya
                // sedang berjalan, selain itu malam pertamanya.
                $buka = $awal->lte($hariIni) && $akhir->gt($hariIni) ? $hariIni->copy() : $awal->copy();

                return (object) [
                    'utama'   => $utama,
                    'anggota' => $anggota,
                    'awal'    => $awal,
                    'akhir'   => $akhir,
                    'malam'   => (int) $awal->diffInDays($akhir),
                    'status'  => $status,
                    'buka'    => $buka,
                    'total'   => Booking::totalPesananDari($anggota),
                ];
            })
            ->sortBy(fn ($k) => match ($k->status) {
                'menginap' => '0-' . $k->awal->format('Ymd'),
                'akan'     => '1-' . $k->awal->format('Ymd'),
                default    => '2-' . (99999999 - (int) $k->awal->format('Ymd')),
            })
            ->take(25)
            ->values();

        return [$cari, $hasil];
    }

    /**
     * Booking yang menginap pada tanggal tsb, terurut nomor kamar.
     */
    protected function bookingsOccupying(Carbon $tanggal): Collection
    {
        return Booking::with(['room', 'creator', 'pindahDari.room', 'pindahKe.room'])
            ->occupyingOn($tanggal)
            ->get()
            ->sortBy(fn (Booking $b) => (int) preg_replace('/\D/', '', (string) $b->room?->nomor))
            ->values();
    }
}
