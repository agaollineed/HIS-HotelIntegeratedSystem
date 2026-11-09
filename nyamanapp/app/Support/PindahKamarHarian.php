<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\Room;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pindah kamar MULAI TANGGAL TERTENTU di tengah masa menginap.
 *
 * Contoh: tamu memesan 202 untuk 8–10 Okt, lalu pada 9 Okt ingin pindah ke
 * 203 atau upgrade ke kamar Family. Baris booking dipecah:
 *
 *     sebelum  202  8 Okt → 10 Okt   (2 malam)
 *     sesudah  202  8 Okt →  9 Okt   (1 malam)
 *              203  9 Okt → 10 Okt   (1 malam, pindah_dari_id = baris 202)
 *
 * Keduanya tetap SATU PESANAN (grup yang sama): satu tamu, satu tagihan,
 * satu diskon. Tagihannya otomatis mengikuti tarif tiap kamar per malam,
 * dan status bayarnya disesuaikan lewat PenyesuaianBayar.
 *
 * Bila tanggal mulai = tanggal check-in, seluruh masa menginap pindah dan
 * aturan PindahKamar yang lama berlaku (termasuk tukar kamar).
 *
 * Status kedatangan:
 *   - Tamu belum check-in: kedua baris menunggu check-in seperti biasa.
 *     Baris pindahan check-in sendiri saat tamunya pindah.
 *   - Tamu sudah di dalam dan tanggal mulainya hari ini atau sudah lewat:
 *     perpindahan langsung dijalankan. Kamar lama ditutup dengan status
 *     "Pindah kamar", kamar baru langsung tercatat sudah check-in.
 *   - Tamu sudah di dalam tapi tanggal mulainya masih nanti: kamar baru
 *     menunggu; begitu ia di-check-in, kamar lama tertutup otomatis.
 */
final class PindahKamarHarian
{
    private function __construct(
        public readonly Booking $pindah,
        public readonly ?Room $asal,
        public readonly Room $tujuan,
        public readonly Carbon $mulai,
        public readonly Carbon $selesai,
        public readonly bool $breakfast,
        /** true = baris dipecah; false = seluruh masa menginap pindah. */
        public readonly bool $pecah,
        /** Rencana pindah penuh (dengan kemungkinan tukar), bila tidak dipecah. */
        public readonly ?PindahKamar $penuh,
        public readonly ?string $galat,
    ) {
    }

    public static function rencanakan(Booking $pindah, Room $tujuan, Carbon $mulai, ?bool $breakfast = null): self
    {
        $asal    = $pindah->room;
        $awal    = $pindah->check_in->copy()->startOfDay();
        $selesai = $pindah->check_out->copy()->startOfDay();
        $mulai   = $mulai->copy()->startOfDay();

        // Kamar Family tidak dijual tanpa sarapan.
        $bf = $tujuan->bolehTanpaBreakfast()
            ? (bool) ($breakfast ?? $pindah->breakfast)
            : true;

        $gagal = fn (string $pesan) => new self($pindah, $asal, $tujuan, $mulai, $selesai, $bf, false, null, $pesan);

        if ($pindah->isCheckedOut()) {
            return $gagal('Kamar ' . ($asal?->nomor ?? '—') . ' sudah ditinggalkan tamunya, jadi tidak bisa dipindah lagi.');
        }

        if ($mulai->lt($awal) || $mulai->gte($selesai)) {
            return $gagal('Tanggal mulai pindah harus di dalam masa menginap kamar ini: '
                . $awal->translatedFormat('j M Y') . ' sampai malam terakhir '
                . $selesai->copy()->subDay()->translatedFormat('j M Y') . '.');
        }

        if ($asal !== null && (int) $asal->id === (int) $tujuan->id) {
            return $gagal('Kamar tujuan sama dengan kamar sekarang.');
        }

        // Seluruh masa menginap pindah: aturan lama, termasuk tukar kamar.
        if ($mulai->equalTo($awal)) {
            $penuh = PindahKamar::rencanakan($pindah, $tujuan, $awal, $selesai);

            return new self($pindah, $asal, $tujuan, $mulai, $selesai, $bf, false, $penuh, $penuh->galat);
        }

        // Dipecah: kamar tujuan harus kosong sejak tanggal mulai. Tukar tidak
        // ditawarkan di sini — tamu yang menempati kamar tujuan masa
        // menginapnya bisa jauh lebih panjang atau lebih pendek dari sisa
        // malam yang dipindah, jadi tidak ada satu tempat yang pas untuknya.
        $bentrok = Booking::query()
            ->where('room_id', $tujuan->id)
            ->menahanKamar()
            ->where('id', '!=', $pindah->id)
            ->where('check_in', '<', $selesai)
            ->where('check_out', '>', $mulai)
            ->orderBy('check_in')
            ->get();

        if ($bentrok->isNotEmpty()) {
            return $gagal('Kamar ' . $tujuan->nomor . ' sudah terisi pada '
                . $mulai->translatedFormat('j M') . ' – ' . $selesai->translatedFormat('j M Y')
                . ' (' . self::sebut($bentrok) . '). Pilih kamar lain atau ubah tanggal mulainya.');
        }

        return new self($pindah, $asal, $tujuan, $mulai, $selesai, $bf, true, null, null);
    }

    public function bisa(): bool
    {
        return $this->galat === null;
    }

    public function menukar(): bool
    {
        return $this->penuh?->menukar() ?? false;
    }

    public function keteranganTukar(): string
    {
        return $this->penuh?->keteranganTukar() ?? '';
    }

    /** Jumlah malam yang pindah ke kamar tujuan. */
    public function malamPindah(): int
    {
        return max(1, (int) $this->mulai->diffInDays($this->selesai));
    }

    /** Tarif per malam sekarang dan sesudah pindah. */
    public function tarifLama(): int
    {
        return $this->pindah->hargaMalam();
    }

    public function tarifBaru(): int
    {
        return $this->tujuan->hargaPerMalam($this->breakfast);
    }

    /** Perubahan total kamar akibat pindah (positif = tamu membayar lebih). */
    public function selisih(): int
    {
        return ($this->tarifBaru() - $this->tarifLama()) * $this->malamPindah();
    }

    /**
     * Pratinjau uang pesanan bila rencana ini dijalankan, tanpa menyimpan.
     *
     * @return array{tagihan_lama: int, tagihan_baru: int, dibayar: int, sisa_baru: int}
     */
    public function pratinjauBayar(): array
    {
        $anggota = $this->pindah->groupMembers()->with('room')->get();
        $utama   = $anggota->firstWhere('id', $this->pindah->id) ?? $this->pindah;

        $totalLama = Booking::totalPesananDari($anggota);
        $totalBaru = $totalLama + $this->selisih();

        $persen = $utama->diskonPersen();
        $diskonBaru = $persen > 0
            ? Booking::nominalDariPersen($totalBaru, $persen)
            : $utama->diskonPesanan();

        $tagihanBaru = max(0, $totalBaru - $diskonBaru);
        $dibayar     = $utama->dibayar($totalLama);

        return [
            'tagihan_lama' => $utama->tagihanPesanan($totalLama),
            'tagihan_baru' => $tagihanBaru,
            'dibayar'      => $dibayar,
            'sisa_baru'    => max(0, $tagihanBaru - $dibayar),
        ];
    }

    /** "202 → 203 mulai 9 Okt (1 malam)" */
    public function ringkasan(): string
    {
        return ($this->asal?->nomor ?? '—') . ' → ' . $this->tujuan->nomor
            . ($this->pecah
                ? ' mulai ' . $this->mulai->translatedFormat('j M') . ' (' . $this->malamPindah() . ' malam)'
                : ' (seluruh masa menginap)');
    }

    /**
     * Jalankan rencana dalam satu transaksi.
     *
     * @param  bool  $lunasSekarang  tamu membayar seluruh sisa tagihan saat ini
     * @param  string|null  $buktiTambahan  path bukti bayar selisih (sudah disimpan pemanggil)
     * @param  bool  $sesuaikanBayar  false bila pemanggil memindah beberapa kamar
     *                                sekaligus dan menyesuaikan bayarnya sekali di akhir
     * @return array{baru: Booking, bayar: array<string, mixed>|null, transisi: bool}
     */
    public function terapkan(?int $aktor = null, bool $lunasSekarang = false, ?string $buktiTambahan = null, bool $sesuaikanBayar = true): array
    {
        if (! $this->bisa()) {
            throw new \LogicException((string) $this->galat);
        }

        return DB::transaction(function () use ($aktor, $lunasSekarang, $buktiTambahan, $sesuaikanBayar) {
            $sebelum  = PenyesuaianBayar::potret($this->pindah);
            $transisi = false;

            if (! $this->pecah) {
                $this->penuh->terapkan(['breakfast' => $this->breakfast]);
                $baru = $this->pindah->fresh();
            } else {
                [$baru, $transisi] = $this->pecahBaris($aktor);
            }

            $bayar = $sesuaikanBayar
                ? PenyesuaianBayar::terapkan($this->pindah, $sebelum, $lunasSekarang)
                : null;

            if ($buktiTambahan !== null) {
                $baru->forceFill(['foto_bayar_tambahan' => $buktiTambahan])->save();
            }

            return ['baru' => $baru->fresh(), 'bayar' => $bayar, 'transisi' => $transisi];
        });
    }

    /**
     * @return array{0: Booking, 1: bool} [baris baru, perpindahan langsung dijalankan]
     */
    private function pecahBaris(?int $aktor): array
    {
        $b       = $this->pindah;
        $sekarang = now();

        // Lanjutan yang sudah ada (pesanan yang sebelumnya sudah pernah
        // dipecah) disambung ke baris baru, supaya urutan kamarnya tetap.
        $lanjutan = Booking::where('pindah_dari_id', $b->id)->first();

        $baru = $b->replicate();
        $baru->forceFill([
            'room_id'        => $this->tujuan->id,
            'breakfast'      => $this->breakfast,
            'check_in'       => $this->mulai->copy(),
            'check_out'      => $this->selesai->copy(),
            'durasi'         => $this->malamPindah(),
            'pindah_dari_id' => $b->id,

            // Jejak kedatangan & kepergian milik KAMAR, bukan milik tamu.
            'checked_in_at'        => null,
            'checkin_menit_awal'   => 0,
            'checked_out_at'       => null,
            'checkout_status'      => null,
            'checkout_menit'       => 0,
            'checkout_by'          => null,
            'checkout_catatan'     => null,
            'checkout_kondisi_ok'  => true,
            'foto_kartu'           => null,
            'foto_kerusakan'       => null,
            'foto_pembayaran_late' => null,
            'late_ditandai_at'     => null,
            'late_ditandai_by'     => null,
            'report_id'            => null,
            'foto_bayar_tambahan'  => null,
        ]);
        $baru->save();

        if ($lanjutan !== null) {
            $lanjutan->forceFill(['pindah_dari_id' => $baru->id])->save();
        }

        $b->forceFill([
            'check_out' => $this->mulai->copy(),
            'durasi'    => max(1, (int) $b->check_in->copy()->startOfDay()->diffInDays($this->mulai)),
        ])->save();

        // Tamu sudah di dalam dan hari pindahnya sudah tiba: jalankan sekarang.
        $transisi = $b->isCheckedIn()
            && ! $b->isCheckedOut()
            && $this->mulai->lte($sekarang->copy()->startOfDay());

        if ($transisi) {
            self::tutupKamarLama($b, $aktor, $sekarang);

            $baru->forceFill([
                'checked_in_at'      => $sekarang,
                'checkin_menit_awal' => 0,
            ])->save();
        }

        return [$baru, $transisi];
    }

    /**
     * Tutup kamar lama saat tamu benar-benar pindah ke kamar lanjutannya.
     * Dipakai juga oleh check-in baris pindahan.
     */
    public static function tutupKamarLama(Booking $lama, ?int $aktor, ?Carbon $waktu = null): void
    {
        if (! $lama->isCheckedIn() || $lama->isCheckedOut()) {
            return;
        }

        $lama->forceFill([
            'checked_out_at'  => $waktu ?? now(),
            'checkout_status' => Booking::CO_PINDAH,
            'checkout_menit'  => 0,
            'checkout_by'     => $aktor,
        ])->save();
    }

    /** "Budi 9 – 11 Okt 2026, Sari 11 – 12 Okt 2026" */
    private static function sebut(Collection $bookings): string
    {
        return $bookings
            ->map(fn (Booking $b) => $b->nama_tamu . ' ' . $b->check_in->translatedFormat('j M')
                . ' – ' . $b->check_out->translatedFormat('j M Y'))
            ->implode(', ');
    }
}
