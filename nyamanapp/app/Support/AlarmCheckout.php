<?php

namespace App\Support;

use App\Models\Booking;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pengingat check-out dan perpanjangan otomatis karena over late.
 *
 * Tiga keadaan yang perlu dilihat resepsionis:
 *
 *   SEGERA      tanggal check-out hari ini, sudah pukul 11.00, tamu masih di
 *               kamar. Satu jam sebelum batas 12.00 — waktunya mengingatkan.
 *   LEWAT       batas 12.00 sudah lewat, kamar belum di-check-out.
 *   DIPERPANJANG
 *               lewat 17.00 tamu masih di kamar: dianggap menambah satu malam
 *               di kamar yang sama, dan sistem langsung mencatatnya.
 *
 * Perpanjangan hanya terjadi bila kamar itu KOSONG untuk malam tambahan.
 * Kalau sudah dipesan tamu lain, sistem tidak menimpanya — tamu tersebut
 * muncul dengan peringatan merah, dan salah satunya harus dipindah.
 * Peringatan yang sama muncul sejak pukul 11.00 supaya resepsionis tahu
 * lebih awal bahwa tamu ini memang harus keluar tepat waktu.
 *
 * Tidak ada penjadwal (cron) di hosting, jadi pemeriksaan ini dijalankan
 * setiap kali halaman Kamar atau Beranda dibuka. Hasilnya sama saja: tamu
 * yang masih di kamar lewat 17.00 akan diperpanjang pada pembukaan halaman
 * pertama sesudahnya.
 */
final class AlarmCheckout
{
    /** Mulai jam ini tamu yang check-out hari ini diingatkan. */
    public const JAM_PENGINGAT = '11:00';

    /** Batas pengaman bila halaman lama tidak dibuka (mis. tamu tertinggal berhari-hari). */
    private const MAKS_MALAM_SEKALI_JALAN = 14;

    /**
     * Semua bahan untuk kotak pengingat.
     *
     * @return array{segera: Collection, lewat: Collection, diperpanjang: Collection, jumlah: int}
     */
    public static function kumpulkan(): array
    {
        self::perpanjangOtomatis();

        $sekarang = now();

        $aktif = Booking::with(['room', 'pindahKe'])
            ->whereNotNull('checked_in_at')
            ->whereNull('checked_out_at')
            ->whereDate('check_out', '<=', $sekarang->toDateString())
            ->get()
            // Kamar yang berlanjut ke kamar lain ditutup saat tamu pindah.
            ->reject(fn (Booking $b) => $b->punyaLanjutan());

        foreach ($aktif as $b) {
            $b->pemesan_berikut = self::pemesanBerikut($b, $b->check_out, $b->check_out->copy()->addDay());
        }

        $segera = $aktif->filter(fn (Booking $b) => $b->check_out->isSameDay($sekarang)
            && $sekarang->greaterThanOrEqualTo(self::jamPengingat($b))
            && $sekarang->lessThanOrEqualTo($b->batasCheckout()));

        $lewat = $aktif->filter(fn (Booking $b) => $b->lateBerjalan());

        $diperpanjang = Booking::with('room')
            ->whereNull('checked_out_at')
            ->whereDate('perpanjang_otomatis_at', $sekarang->toDateString())
            ->get();

        return [
            'segera'       => self::perPesanan($segera),
            'lewat'        => self::perPesanan($lewat),
            'diperpanjang' => self::perPesanan($diperpanjang),
            'jumlah'       => $segera->count() + $lewat->count() + $diperpanjang->count(),
        ];
    }

    /**
     * Tambah satu malam (atau lebih, bila tertinggal) untuk tamu yang masih
     * di kamar lewat 17.00 pada tanggal check-out, selama kamarnya kosong.
     *
     * @return Collection<int, array{booking: Booking, malam: int}>  yang berhasil diperpanjang
     */
    public static function perpanjangOtomatis(): Collection
    {
        $kandidat = Booking::with(['room', 'pindahKe'])
            ->whereNotNull('checked_in_at')
            ->whereNull('checked_out_at')
            ->whereDate('check_out', '<=', now()->toDateString())
            ->get()
            ->filter(fn (Booking $b) => $b->lateBerjalan() && $b->overLatePada(now()));

        $hasil = collect();

        foreach ($kandidat->groupBy(fn (Booking $b) => $b->grup ?? ('single-' . $b->id)) as $grup) {
            $tambah = DB::transaction(function () use ($grup) {
                $utama   = $grup->first();
                $sebelum = PenyesuaianBayar::potret($utama);
                $tambah  = [];

                foreach ($grup as $b) {
                    // Dibaca ulang di dalam transaksi: dua halaman yang
                    // dibuka bersamaan tidak boleh menambah malam dua kali.
                    $m = Booking::query()->lockForUpdate()->find($b->id);
                    $n = 0;

                    while ($m && $n < self::MAKS_MALAM_SEKALI_JALAN
                        && $m->isCheckedIn() && ! $m->isCheckedOut()
                        && $m->overLatePada(now())
                        && self::pemesanBerikut($m, $m->check_out, $m->check_out->copy()->addDay()) === null) {
                        $m->forceFill([
                            'check_out'                 => $m->check_out->copy()->addDay(),
                            'durasi'                    => (int) $m->durasi + 1,
                            'perpanjang_otomatis_malam' => (int) $m->perpanjang_otomatis_malam + 1,
                            'perpanjang_otomatis_at'    => now(),
                        ])->save();
                        $n++;
                    }

                    if ($n > 0) {
                        $tambah[] = ['booking' => $m, 'malam' => $n];
                    }
                }

                // Malam tambahan menaikkan tagihan: pesanan lunas turun jadi
                // DP dan selisihnya ditagih saat check-out.
                if ($tambah !== []) {
                    PenyesuaianBayar::terapkan($utama, $sebelum);
                }

                return $tambah;
            });

            $hasil = $hasil->merge($tambah);
        }

        return $hasil;
    }

    /**
     * Pesanan lain yang menahan kamar ini pada rentang [$dari, $sampai).
     */
    public static function pemesanBerikut(Booking $b, Carbon $dari, Carbon $sampai): ?Booking
    {
        return Booking::query()
            ->where('room_id', $b->room_id)
            ->where('id', '!=', $b->id)
            ->menahanKamar()
            ->where('check_in', '<', $sampai->copy()->startOfDay())
            ->where('check_out', '>', $dari->copy()->startOfDay())
            ->orderBy('check_in')
            ->first();
    }

    /** Jam pengingat pada tanggal check-out booking ini. */
    public static function jamPengingat(Booking $b): Carbon
    {
        return $b->check_out->copy()->setTimeFromTimeString(self::JAM_PENGINGAT);
    }

    /**
     * Satu tamu dengan lima kamar menjadi satu peringatan, bukan lima — kecuali
     * batas check-out kamarnya berbeda, karena waktunya memang tidak sama.
     * Pesanan yang kamarnya sudah dipesan orang lain ditaruh paling atas.
     */
    private static function perPesanan(Collection $baris): Collection
    {
        return $baris
            ->groupBy(fn (Booking $b) => ($b->grup ?? ('single-' . $b->id)) . '|' . $b->check_out->toDateString())
            ->map(fn ($g) => $g->sortBy(fn ($b) => (int) preg_replace('/\D/', '', (string) $b->room?->nomor))->values())
            ->sortBy(fn ($g) => $g->contains(fn ($b) => $b->pemesan_berikut ?? null) ? 0 : 1)
            ->values();
    }
}
