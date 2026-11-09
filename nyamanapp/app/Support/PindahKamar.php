<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\Room;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Rencana pindah kamar untuk SATU baris booking — termasuk tukar kamar.
 *
 * Dulu pindah ke kamar yang sedang terisi selalu ditolak. Di lapangan itu
 * menyulitkan justru pada saat hotel penuh: tamu 101 ingin ke 102, tamu 102
 * sebenarnya tidak keberatan di 101, tetapi sistem menolak karena 102 "terisi".
 *
 * Aturannya sekarang: bila kamar tujuan ditempati TEPAT SATU tamu lain pada
 * rentang menginap yang akan berlaku, tamu itu otomatis digeser ke kamar
 * asal — kedua tamu bertukar kamar. Yang dipastikan sebelum menukar:
 *
 *   - tamu yang digeser muat di kamar asal pada tanggalnya sendiri
 *     (tanpa menghitung baris yang sedang pindah, karena ia akan keluar);
 *   - tarif per malam tamu yang digeser TIDAK berubah di kamar asal. Ia
 *     dipindah karena keputusan hotel, bukan permintaannya sendiri, jadi
 *     tagihannya tidak boleh ikut naik atau turun diam-diam. Tukar hanya
 *     terjadi antar kamar yang tarifnya sama untuk tamu itu.
 *
 * Bentrok dihitung terhadap SEMUA tamu lain di kamar tujuan, termasuk kamar
 * lain dalam pesanan yang sama. Dulu seluruh pesanan diabaikan, sehingga
 * memindah 101 ke 103 milik pesanan yang sama lolos dan dua baris menempati
 * 103 pada malam yang sama.
 *
 * Kamar tujuan yang ditempati dua tamu atau lebih (misalnya dua pesanan
 * berurutan) TIDAK ditukar: tidak ada satu tempat yang bisa menampung
 * keduanya, jadi lebih jujur menolak dengan menyebut siapa saja yang ada.
 *
 * Pindah ke kamar yang sama (hanya tanggal yang berubah) bukan tukar: tamu
 * lain di kamar itu pada tanggal baru adalah bentrok biasa.
 */
final class PindahKamar
{
    private function __construct(
        public readonly Booking $pindah,
        public readonly ?Room $asal,
        public readonly Room $tujuan,
        public readonly Carbon $mulai,
        public readonly Carbon $selesai,
        /** Penghuni kamar tujuan yang akan digeser ke kamar asal, bila ada. */
        public readonly ?Booking $ditukar,
        /** Alasan rencana ini tidak bisa dijalankan, bila ada. */
        public readonly ?string $galat,
    ) {
    }

    /**
     * Susun rencana untuk memindahkan $pindah ke $tujuan pada [$mulai, $selesai).
     *
     * Rentang diberikan terpisah karena formulir ubah bisa menggeser kamar
     * dan tanggal sekaligus — yang diperiksa adalah rentang yang AKAN berlaku.
     */
    public static function rencanakan(Booking $pindah, Room $tujuan, Carbon $mulai, Carbon $selesai): self
    {
        $asal    = $pindah->room;
        $abaikan = [$pindah->id];

        $penghuni = self::penghuni($tujuan->id, $mulai, $selesai, $abaikan);

        if ($penghuni->isEmpty()) {
            return new self($pindah, $asal, $tujuan, $mulai, $selesai, null, null);
        }

        $rentang = $mulai->translatedFormat('j M Y') . ' – ' . $selesai->translatedFormat('j M Y');

        // Kamar tidak berubah: ini bentrok tanggal biasa, bukan tukar.
        if ($asal === null || (int) $asal->id === (int) $tujuan->id) {
            return self::gagal($pindah, $asal, $tujuan, $mulai, $selesai,
                'Kamar ' . $tujuan->nomor . ' sudah dibooking pada rentang ' . $rentang
                . ' (' . self::sebut($penghuni) . '). Pilih kamar atau tanggal lain.');
        }

        if ($penghuni->count() > 1) {
            return self::gagal($pindah, $asal, $tujuan, $mulai, $selesai,
                'Kamar ' . $tujuan->nomor . ' ditempati ' . $penghuni->count() . ' tamu pada rentang '
                . $rentang . ' (' . self::sebut($penghuni) . '), jadi tidak bisa ditukar dengan kamar '
                . $asal->nomor . '. Pilih kamar lain.');
        }

        /** @var Booking $tamu */
        $tamu       = $penghuni->first();
        $tamuRentang = $tamu->check_in->translatedFormat('j M') . ' – ' . $tamu->check_out->translatedFormat('j M Y');

        // Tamu yang digeser harus muat di kamar asal pada tanggalnya sendiri.
        // Baris yang pindah (dan grupnya) diabaikan: ia justru akan keluar.
        $bentrokAsal = self::penghuni($asal->id, $tamu->check_in->copy(), $tamu->check_out->copy(),
            array_merge($abaikan, [$tamu->id]));

        if ($bentrokAsal->isNotEmpty()) {
            return self::gagal($pindah, $asal, $tujuan, $mulai, $selesai,
                'Kamar ' . $tujuan->nomor . ' ditempati ' . $tamu->nama_tamu . ' (' . $tamuRentang . '), '
                . 'dan ' . $tamu->nama_tamu . ' tidak bisa ditukar ke kamar ' . $asal->nomor
                . ' karena kamar itu sudah terisi ' . self::sebut($bentrokAsal)
                . ' pada tanggal tersebut. Pilih kamar lain.');
        }

        if (! $tamu->breakfast && ! $asal->bolehTanpaBreakfast()) {
            return self::gagal($pindah, $asal, $tujuan, $mulai, $selesai,
                'Kamar ' . $tujuan->nomor . ' ditempati ' . $tamu->nama_tamu . ' (' . $tamuRentang . ') '
                . 'dengan pesanan tanpa sarapan, sedangkan kamar ' . $asal->nomor
                . ' (' . $asal->labelTipe() . ') wajib dengan sarapan — jadi tidak bisa ditukar. '
                . 'Pilih kamar lain.');
        }

        $tarifSekarang = $tujuan->hargaPerMalam((bool) $tamu->breakfast);
        $tarifDiAsal   = $asal->hargaPerMalam((bool) $tamu->breakfast);

        if ($tarifSekarang !== $tarifDiAsal) {
            return self::gagal($pindah, $asal, $tujuan, $mulai, $selesai,
                'Kamar ' . $tujuan->nomor . ' ditempati ' . $tamu->nama_tamu . ' (' . $tamuRentang . '). '
                . 'Menukarnya ke kamar ' . $asal->nomor . ' (' . $asal->labelTipe() . ') akan mengubah tarifnya dari '
                . \App\Models\Booking::rupiah($tarifSekarang) . ' menjadi ' . \App\Models\Booking::rupiah($tarifDiAsal)
                . ' per malam, padahal ia tidak meminta pindah. Tukar hanya bisa antar kamar bertarif sama — pilih kamar lain.');
        }

        return new self($pindah, $asal, $tujuan, $mulai, $selesai, $tamu, null);
    }

    public function bisa(): bool
    {
        return $this->galat === null;
    }

    public function menukar(): bool
    {
        return $this->ditukar !== null;
    }

    /**
     * Jalankan: baris ini ke kamar tujuan, penghuni tujuan (bila ada) ke
     * kamar asal. Panggil di dalam transaksi bersama perubahan lain.
     *
     * @param array<string, mixed> $atribut kolom lain baris ini yang ikut disimpan
     */
    public function terapkan(array $atribut = []): void
    {
        if (! $this->bisa()) {
            throw new \LogicException($this->galat);
        }

        $asalId = $this->pindah->room_id;

        $this->pindah->update(['room_id' => $this->tujuan->id] + $atribut);

        if ($this->ditukar !== null) {
            $this->ditukar->update(['room_id' => $asalId]);
        }
    }

    /**
     * Kalimat untuk pesan sukses / kartu pengajuan: siapa yang ikut bergeser.
     * Kosong bila tidak ada yang ditukar.
     */
    public function keteranganTukar(): string
    {
        if ($this->ditukar === null || $this->asal === null) {
            return '';
        }

        return 'Kamar ' . $this->tujuan->nomor . ' sebelumnya ditempati ' . $this->ditukar->nama_tamu
            . ' (' . $this->ditukar->check_in->translatedFormat('j M') . ' – '
            . $this->ditukar->check_out->translatedFormat('j M Y') . ') — '
            . $this->ditukar->nama_tamu . ' dipindah ke kamar ' . $this->asal->nomor . '.';
    }

    /**
     * Booking lain yang masih menahan $roomId dan tumpang tindih [$mulai, $selesai).
     *
     * @param  array<int, int> $abaikan
     * @return Collection<int, Booking>
     */
    private static function penghuni(int $roomId, Carbon $mulai, Carbon $selesai, array $abaikan): Collection
    {
        return Booking::query()
            ->where('room_id', $roomId)
            ->menahanKamar()
            ->when($abaikan !== [], fn ($q) => $q->whereNotIn('id', $abaikan))
            ->where('check_in', '<', $selesai)
            ->where('check_out', '>', $mulai)
            ->orderBy('check_in')
            ->get();
    }

    /** "Budi 1 – 3 Sep 2026, Sari 3 – 5 Sep 2026" */
    private static function sebut(Collection $bookings): string
    {
        return $bookings
            ->map(fn (Booking $b) => $b->nama_tamu . ' ' . $b->check_in->translatedFormat('j M')
                . ' – ' . $b->check_out->translatedFormat('j M Y'))
            ->implode(', ');
    }

    private static function gagal(Booking $pindah, ?Room $asal, Room $tujuan, Carbon $mulai, Carbon $selesai, string $galat): self
    {
        return new self($pindah, $asal, $tujuan, $mulai, $selesai, null, $galat);
    }
}
