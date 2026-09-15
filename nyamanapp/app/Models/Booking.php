<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    protected $fillable = [
        'room_id',
        'grup',
        'nama_tamu',
        'telepon',
        'breakfast',
        'sumber',
        'status_bayar',
        'dp_nominal',
        'diskon_nominal',
        'diskon_persen',
        'foto_ktp',
        'foto_pelunasan',
        'foto_bayar_awal',
        'checked_in_at',
        'checkin_menit_awal',
        'checked_out_at',
        'checkout_status',
        'checkout_menit',
        'late_ditandai_at',
        'late_ditandai_by',
        'foto_pembayaran_late',
        'foto_kartu',
        'checkout_kondisi_ok',
        'foto_kerusakan',
        'report_id',
        'checkout_catatan',
        'checkout_by',
        'check_in',
        'durasi',
        'check_out',
        'created_by',
    ];

    protected $casts = [
        'check_in'           => 'date',
        'check_out'          => 'date',
        'durasi'             => 'integer',
        'breakfast'          => 'boolean',
        'dp_nominal'         => 'integer',
        'diskon_nominal'     => 'integer',
        'diskon_persen'      => 'integer',
        'checked_in_at'      => 'datetime',
        'checkin_menit_awal' => 'integer',
        'checked_out_at'      => 'datetime',
        'checkout_menit'      => 'integer',
        'late_ditandai_at'    => 'datetime',
        'checkout_kondisi_ok' => 'boolean',
    ];

    /**
     * Jam standar hotel.
     *
     *   13.00  batas early check-in — datang sebelum ini, kamar terpakai
     *          lebih lama dari yang dibayar
     *   09.00  batas early check-out — pergi sebelum ini (termasuk pada hari
     *          sebelumnya) meninggalkan kamar kosong yang masih tersewa
     *   12.00  batas akhir masa sewa — lewat dari ini menjadi late check-out
     *
     * Ketiganya berarti kamar dipakai di luar jam yang dibayar, jadi harus
     * tercatat — bukan diselesaikan lewat kesepakatan lisan di meja resepsionis.
     */
    public const JAM_CHECKIN       = '13:00';
    public const JAM_CHECKOUT_AWAL = '09:00';
    public const JAM_CHECKOUT      = '12:00';

    /** Asal pesanan. Tiket.com selalu terbayar di muka. */
    /* ---------------- Aturan harga ---------------- */

    /** Check-in sebelum jam ini dikenai biaya tambahan 1 malam penuh. */
    public const JAM_EARLY_BERBAYAR = '12:30';

    /** Check-out telat sampai jam ini dikenai 50% harga; lewat = 100%. */
    public const JAM_LATE_SETENGAH = '17:00';

    /** Harga kamar per malam DENGAN sarapan (300rb King/Twin, 450rb Family). */
    public function hargaDasarMalam(): int
    {
        return $this->room?->hargaPerMalam(true) ?? 0;
    }

    /**
     * Harga per malam yang BERLAKU untuk booking ini.
     *
     * Dengan sarapan  : tarif penuh tipe kamar (300rb / 450rb).
     * Tanpa sarapan   : 250rb untuk King & Twin.
     * Kamar family    : tidak boleh tanpa sarapan, jadi selalu tarif penuh.
     */
    public function hargaMalam(): int
    {
        return $this->room?->hargaPerMalam((bool) $this->breakfast) ?? 0;
    }

    /** Kamar ini wajib dijual dengan sarapan (family). */
    public function breakfastWajib(): bool
    {
        return ! ($this->room?->bolehTanpaBreakfast() ?? true);
    }

    /** Total sewa kamar ini: harga malam berlaku x durasi. */
    public function totalKamar(): int
    {
        return $this->hargaMalam() * max(1, (int) $this->durasi);
    }

    /**
     * Biaya early check-in: masuk SEBELUM 12:30 pada tanggal check-in
     * dikenai tambahan 1 malam penuh (harga yang berlaku). Antara 12:30
     * dan 13:00 dianggap toleransi tanpa biaya.
     *
     * $waktu opsional untuk PRATINJAU (mis. halaman check-in memakai now()
     * sebelum tamu benar-benar tercatat masuk).
     */
    public function biayaEarlyCheckin(?CarbonInterface $waktu = null): int
    {
        $waktu ??= $this->checked_in_at;

        if (! $waktu || ! $this->check_in) {
            return 0;
        }

        $batas = $this->check_in->copy()->setTimeFromTimeString(self::JAM_EARLY_BERBAYAR);

        return $waktu->lt($batas) ? $this->hargaMalam() : 0;
    }

    /**
     * Biaya late check-out: lewat 12:00 sampai 17:00 = 50% harga malam;
     * lewat 17:00 = 100% (dianggap memakai kamar hampir sehari penuh).
     *
     * $waktu opsional untuk pratinjau tagihan sebelum check-out tercatat.
     */
    public function biayaLateCheckout(?CarbonInterface $waktu = null): int
    {
        $waktu ??= $this->checked_out_at;

        if (! $waktu || ! $this->check_out) {
            return 0;
        }

        if ($waktu->lte($this->batasCheckout())) {
            return 0;
        }

        // Sampai 17.00 = setengah harga malam; lewat 17.00 (over late)
        // dianggap memakai kamar hampir sehari penuh = 1 malam penuh.
        return $this->overLatePada($waktu)
            ? $this->hargaMalam()
            : (int) round($this->hargaMalam() / 2);
    }

    /** Format rupiah singkat untuk tampilan: Rp 300.000 */
    public static function rupiah(int $n): string
    {
        return 'Rp ' . number_format($n, 0, ',', '.');
    }

    // ------------------------------------------------------------------
    // Diskon
    //
    // Potongan berlaku untuk SATU PESANAN — seluruh kamar yang dipesan
    // bersama. Nominalnya disalin ke setiap baris kamar dalam grup (sama
    // seperti nama tamu dan tanggal), jadi dibaca sekali dari baris mana pun,
    // BUKAN dijumlahkan antar-baris.
    // ------------------------------------------------------------------

    /**
     * Total tagihan kamar untuk satu pesanan, dari koleksi booking segrup.
     *
     * Tipe kamar dalam satu pesanan boleh berbeda-beda, jadi totalnya
     * dijumlahkan per kamar — bukan harga satu kamar dikali jumlah kamar.
     *
     * @param  iterable<int, self>  $anggota
     */
    public static function totalPesananDari($anggota): int
    {
        return (int) collect($anggota)->sum(fn (self $b) => $b->totalKamar());
    }

    /** Total tagihan kamar seluruh pesanan ini (memuat anggota grupnya). */
    public function totalPesanan(): int
    {
        return static::totalPesananDari($this->groupMembers()->with('room')->get());
    }

    /** Potongan harga yang diberikan untuk pesanan ini. */
    public function diskonPesanan(): int
    {
        return max(0, (int) $this->diskon_nominal);
    }

    /** Persen yang disepakati, 0 bila potongannya diisi sebagai nominal. */
    public function diskonPersen(): int
    {
        return max(0, min(100, (int) $this->diskon_persen));
    }

    /**
     * Yang benar-benar harus dibayar tamu: total kamar dikurangi diskon.
     *
     * Diskon MEMOTONG tagihan, bukan menambah uang masuk — dua hal itu dipisah
     * supaya laporan pendapatan tidak ikut menghitung potongan sebagai
     * pemasukan.
     */
    public function tagihanPesanan(?int $totalKamar = null): int
    {
        $totalKamar ??= $this->totalPesanan();

        return max(0, $totalKamar - $this->diskonPesanan());
    }

    /**
     * "Diskon 10% (Rp 60.000)" atau "Diskon Rp 50.000".
     *
     * Persennya ikut ditulis karena itulah yang diucapkan ke tamu; nominalnya
     * tetap disebut karena itu yang masuk ke hitungan uang.
     */
    public function labelDiskon(): string
    {
        if ($this->diskonPesanan() <= 0) {
            return '';
        }

        return $this->diskonPersen() > 0
            ? 'Diskon ' . $this->diskonPersen() . '% (' . self::rupiah($this->diskonPesanan()) . ')'
            : 'Diskon ' . self::rupiah($this->diskonPesanan());
    }

    /**
     * Nominal potongan dari sebuah persen, dibulatkan ke ribuan terdekat.
     *
     * Dipakai server maupun peramban supaya angka di layar dan angka yang
     * tersimpan tidak pernah berbeda.
     */
    public static function nominalDariPersen(int $total, int $persen): int
    {
        if ($persen <= 0 || $total <= 0) {
            return 0;
        }

        return (int) (round($total * min(100, $persen) / 100 / 1000) * 1000);
    }

    public const SUMBER_UMUM  = 'umum';
    public const SUMBER_TIKET = 'tiket';

    /**
     * Status pembayaran.
     *
     *   lunas  seluruh tagihan kamar sudah masuk
     *   dp     sebagian sudah masuk (nominalnya di kolom dp_nominal)
     *   belum  belum ada uang yang masuk
     */
    public const BAYAR_LUNAS = 'lunas';
    public const BAYAR_DP    = 'dp';
    public const BAYAR_BELUM = 'belum';

    /** Seluruh status yang sah — dipakai validasi & formulir. */
    public const STATUS_BAYAR = [self::BAYAR_LUNAS, self::BAYAR_DP, self::BAYAR_BELUM];

    /** Status check-out. */
    public const CO_AWAL  = 'awal';   // pergi sebelum 09.00 — early check-out
    public const CO_TEPAT = 'tepat';  // antara 09.00 dan 12.00 pada tanggal check-out
    public const CO_TELAT = 'telat';  // lewat dari 12.00

    /**
     * Tamu sudah melakukan check-in (foto KTP sudah diunggah saat kedatangan).
     */
    /**
     * Label sarapan untuk tampilan.
     */
    public function labelBreakfast(): string
    {
        return $this->breakfast ? 'Dengan Breakfast' : 'Tanpa Breakfast';
    }

    public function isCheckedIn(): bool
    {
        return $this->checked_in_at !== null;
    }

    public function isCheckedOut(): bool
    {
        return $this->checked_out_at !== null;
    }

    // ------------------------------------------------------------------
    // Batas waktu & perhitungan status
    // ------------------------------------------------------------------

    /** Awal masa sewa: tanggal check-in pukul 14.00. */
    public function batasCheckin(): \Illuminate\Support\Carbon
    {
        [$jam, $menit] = array_map('intval', explode(':', self::JAM_CHECKIN));

        return $this->check_in->copy()->setTime($jam, $menit);
    }

    /** Akhir masa sewa: tanggal check-out pukul 12.00. */
    public function batasCheckout(): \Illuminate\Support\Carbon
    {
        [$jam, $menit] = array_map('intval', explode(':', self::JAM_CHECKOUT));

        return $this->check_out->copy()->setTime($jam, $menit);
    }

    /** Batas early check-out: tanggal check-out pukul 09.00. */
    public function batasCheckoutAwal(): \Illuminate\Support\Carbon
    {
        [$jam, $menit] = array_map('intval', explode(':', self::JAM_CHECKOUT_AWAL));

        return $this->check_out->copy()->setTime($jam, $menit);
    }

    /** Batas tarif setengah pada late check-out: tanggal check-out pukul 17.00. */
    public function batasLateSetengah(): \Illuminate\Support\Carbon
    {
        return $this->check_out->copy()->setTimeFromTimeString(self::JAM_LATE_SETENGAH);
    }

    /**
     * Berapa menit lebih awal tamu datang dibanding jam 14.00.
     * 0 berarti datang tepat waktu atau sesudahnya.
     */
    public function menitEarlyCheckinPada(CarbonInterface $waktu): int
    {
        $batas = $this->batasCheckin();
        $w     = $waktu->copy()->startOfMinute();

        return $w->greaterThanOrEqualTo($batas) ? 0 : (int) $w->diffInMinutes($batas);
    }

    /**
     * Status check-out bila diproses pada $waktu.
     *
     * Kuncinya ada pada cabang pertama: kepergian SEBELUM tanggal check-out
     * adalah kasus yang paling rawan disalahgunakan — kamar kosong secara
     * fisik padahal masa sewanya masih berjalan. Kasus itu diberi status
     * tersendiri supaya menonjol di panel, bukan diperlakukan sama dengan
     * check-out biasa yang kebetulan terjadi pagi hari.
     *
     * @return array{0: string, 1: int} [status, menit penyimpangan]
     */
    public function statusCheckoutPada(CarbonInterface $waktu): array
    {
        $batas = $this->batasCheckout();
        $w     = $waktu->copy()->startOfMinute();

        // Satu ambang menutup dua kasus sekaligus: pergi pagi buta pada hari
        // check-out, dan pergi sehari lebih awal (yang otomatis juga jatuh
        // sebelum pukul 09.00 tanggal check-out).
        if ($w->lessThan($this->batasCheckoutAwal())) {
            return [self::CO_AWAL, (int) $w->diffInMinutes($batas)];
        }

        if ($w->lessThanOrEqualTo($batas)) {
            return [self::CO_TEPAT, 0];
        }

        return [self::CO_TELAT, (int) $batas->diffInMinutes($w)];
    }

    /**
     * Tamu belum di-check-out padahal masa sewanya sudah lewat.
     * Inilah yang memunculkan toast peringatan di panel kamar.
     */
    public function lateBerjalan(): bool
    {
        return $this->isCheckedIn()
            && ! $this->isCheckedOut()
            && now()->greaterThan($this->batasCheckout());
    }

    /** Sudah lewat 12.00 dan diakui staff lewat toast. */
    public function lateDitandai(): bool
    {
        return $this->late_ditandai_at !== null;
    }

    // ------------------------------------------------------------------
    // Pembayaran
    // ------------------------------------------------------------------

    public function lunas(): bool
    {
        return $this->status_bayar === self::BAYAR_LUNAS;
    }

    /** Pesanan ini dibayar sebagian di muka (uang muka / DP). */
    public function dp(): bool
    {
        return $this->status_bayar === self::BAYAR_DP;
    }

    /**
     * Uang muka yang tercatat untuk pesanan ini.
     *
     * Nilainya tetap tersimpan setelah pesanan dilunasi, jadi ia dibaca apa
     * adanya — untuk pertanyaan "berapa yang sudah masuk" pakai dibayar().
     */
    public function dpPesanan(): int
    {
        return max(0, (int) $this->dp_nominal);
    }

    /**
     * Uang yang SUDAH masuk untuk pesanan ini.
     *
     * $totalKamar dilewatkan dari luar supaya halaman daftar bisa memakai
     * total yang sudah dihitung sekali untuk grupnya.
     */
    public function dibayar(?int $totalKamar = null): int
    {
        $tagihan = $this->tagihanPesanan($totalKamar);

        return match ($this->status_bayar) {
            self::BAYAR_LUNAS => $tagihan,
            self::BAYAR_DP    => min($tagihan, $this->dpPesanan()),
            default           => 0,
        };
    }

    /** Sisa yang masih harus ditagih saat tamu tiba. */
    public function sisaBayar(?int $totalKamar = null): int
    {
        $tagihan = $this->tagihanPesanan($totalKamar);

        return max(0, $tagihan - $this->dibayar($totalKamar));
    }

    /**
     * Ringkasan pembayaran satu pesanan: "DP Rp 200.000 · sisa Rp 700.000".
     */
    public function ringkasanBayar(?int $totalKamar = null): string
    {
        $tagihan = $this->tagihanPesanan($totalKamar);
        $potong  = $this->diskonPesanan() > 0
            ? ' (setelah ' . lcfirst($this->labelDiskon()) . ')'
            : '';

        if ($this->lunas()) {
            return ($this->dpPesanan() > 0
                ? 'Lunas (termasuk DP ' . self::rupiah($this->dpPesanan()) . ')'
                : 'Lunas') . $potong;
        }

        if ($this->dp()) {
            return 'DP ' . self::rupiah($this->dibayar($totalKamar))
                . ' · sisa ' . self::rupiah($this->sisaBayar($totalKamar)) . $potong;
        }

        return 'Belum lunas · tagihan ' . self::rupiah($tagihan) . $potong;
    }

    /**
     * Pesanan yang menyatakan uang SUDAH masuk wajib membawa buktinya saat
     * itu juga.
     *
     * "Lunas" dan "DP" adalah pengakuan bahwa uang sudah diterima. Tanpa foto
     * yang menyertainya, pengakuan itu tidak bisa dicocokkan dengan apa pun:
     * pesanan bisa ditandai lunas tanpa uang pernah masuk, atau nominal DP
     * ditulis lebih kecil dari yang diterima — dan selisihnya tidak muncul di
     * mana-mana. "Belum lunas" tidak menuntut apa-apa karena memang belum ada
     * yang perlu dibuktikan.
     *
     * Pesanan Tiket.com dikecualikan: uangnya masuk lewat mitra, statusnya
     * dipaksa lunas oleh server, dan tidak ada transaksi di meja depan yang
     * bisa difoto.
     */
    public static function butuhBuktiBayarAwal(?string $statusBayar, ?string $sumber): bool
    {
        if ($sumber === self::SUMBER_TIKET) {
            return false;
        }

        return in_array($statusBayar, [self::BAYAR_LUNAS, self::BAYAR_DP], true);
    }

    /** Versi objek dari butuhBuktiBayarAwal(). */
    public function wajibBuktiBayarAwal(): bool
    {
        return self::butuhBuktiBayarAwal($this->status_bayar, $this->sumber);
    }

    public function dariTiket(): bool
    {
        return $this->sumber === self::SUMBER_TIKET;
    }

    public function labelSumber(): string
    {
        return $this->dariTiket() ? 'Tiket.com' : 'Umum';
    }

    public function labelBayar(): string
    {
        return match ($this->status_bayar) {
            self::BAYAR_LUNAS => 'Lunas',
            self::BAYAR_DP    => 'DP',
            default           => 'Belum Lunas',
        };
    }

    /**
     * Bukti pelunasan wajib bila tagihan belum lunas, ATAU bila tamu datang
     * sebelum jam 13.00.
     *
     * Early check-in menambah jam pakai kamar dan ada biayanya sendiri, jadi
     * pesanan yang sudah lunas pun tetap meninggalkan bukti bayar — kalau
     * tidak, biaya jam ekstra itu tidak punya jejak di mana pun.
     */
    public function butuhFotoPelunasan(?CarbonInterface $waktu = null): bool
    {
        $waktu ??= now();

        return ! $this->lunas() || $this->menitEarlyCheckinPada($waktu) > 0;
    }

    public function checkoutAwal(): bool
    {
        return $this->checkout_status === self::CO_AWAL;
    }

    public function checkoutTelat(): bool
    {
        return $this->checkout_status === self::CO_TELAT;
    }

    /** Kepergian pada $waktu sudah melewati batas tarif setengah (17.00). */
    public function overLatePada(CarbonInterface $waktu): bool
    {
        return $this->check_out && $waktu->greaterThan($this->batasLateSetengah());
    }

    /**
     * Over late check-out: telat DAN lewat jam 17.00, sehingga biayanya
     * naik dari setengah harga malam menjadi satu malam penuh.
     */
    public function overLateCheckout(): bool
    {
        return $this->checkoutTelat()
            && $this->checked_out_at !== null
            && $this->overLatePada($this->checked_out_at);
    }

    public function earlyCheckin(): bool
    {
        return (int) $this->checkin_menit_awal > 0;
    }

    // ------------------------------------------------------------------
    // Label tampilan
    // ------------------------------------------------------------------

    /**
     * "1 jam 20 menit" / "2 hari 5 jam" — dipakai untuk sisa masa sewa
     * maupun lama keterlambatan.
     */
    public static function labelDurasi(int $menit): string
    {
        $menit = max(0, $menit);

        if ($menit < 60) {
            return $menit . ' menit';
        }

        $hari = intdiv($menit, 1440);
        $jam  = intdiv($menit % 1440, 60);
        $sisa = $menit % 60;

        if ($hari > 0) {
            return $hari . ' hari' . ($jam > 0 ? ' ' . $jam . ' jam' : '');
        }

        return $jam . ' jam' . ($sisa > 0 ? ' ' . $sisa . ' menit' : '');
    }

    public function labelStatusCheckout(): string
    {
        return match ($this->checkout_status) {
            self::CO_AWAL  => 'Early check-out',
            self::CO_TELAT => $this->overLateCheckout() ? 'Over late check-out' : 'Late check-out',
            self::CO_TEPAT => 'Check-out tepat waktu',
            default        => 'Belum check-out',
        };
    }

    public function labelKondisi(): string
    {
        if (! $this->isCheckedOut()) {
            return '—';
        }

        return $this->checkout_kondisi_ok
            ? 'Tidak ada kerusakan / barang tertinggal'
            : 'Ada kerusakan atau barang tertinggal';
    }

    public function labelEarlyCheckin(): string
    {
        return $this->earlyCheckin() ? 'Early check-in' : 'Check-in normal';
    }

    /**
     * Bukti pembayaran wajib saat CHECK-OUT: late maupun over late
     * check-out sama-sama menimbulkan biaya kelebihan jam.
     */
    public function butuhFotoBayarCheckout(?CarbonInterface $waktu = null): bool
    {
        $waktu ??= now();

        return $this->biayaLateCheckout($waktu) > 0;
    }

    /**
     * Booking yang MENGINAP pada tanggal tsb: check_in <= T < check_out.
     *
     * Memakai whereDate() — bukan perbandingan string biasa — karena kolom
     * check_in/check_out disimpan sebagai datetime ('2026-08-14 00:00:00').
     * Membandingkannya langsung dengan '2026-08-14' membuat SQLite/MySQL
     * menganggap string lebih panjang itu LEBIH BESAR, sehingga semua tamu
     * yang check-in pada hari itu hilang dan yang check-out malah terhitung.
     */
    public function scopeOccupyingOn(Builder $query, CarbonInterface $tanggal): Builder
    {
        return $query
            ->whereDate('check_in', '<=', $tanggal->toDateString())
            ->whereDate('check_out', '>', $tanggal->toDateString());
    }

    /**
     * Versi PHP dari scopeOccupyingOn (untuk koleksi yang sudah di-load).
     */
    public function occupiesOn(CarbonInterface $tanggal): bool
    {
        $t = $tanggal->toDateString();

        return $this->check_in->toDateString() <= $t && $this->check_out->toDateString() > $t;
    }

    /**
     * Seluruh anggota grup pesanan ini (termasuk dirinya).
     * Booking lama tanpa kolom grup dianggap grup tunggal.
     */
    public function groupMembers(): \Illuminate\Database\Eloquent\Builder
    {
        return $this->grup
            ? static::query()->where('grup', $this->grup)
            : static::query()->where('id', $this->id);
    }

    /** Pengajuan perubahan yang pernah diajukan untuk baris booking ini. */
    public function permintaan(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BookingRequest::class);
    }

    /** Pengajuan yang masih menunggu keputusan admin, bila ada. */
    public function permintaanTertunda(): ?BookingRequest
    {
        return $this->permintaan()->menunggu()->latest()->first();
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Siapa yang memproses check-out — jejak untuk pertanyaan susulan. */
    public function checkoutBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checkout_by');
    }

    /** Tiket laporan kerusakan / barang tertinggal, bila ada. */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'report_id');
    }

    /**
     * Booking pada kamar tertentu yang rentang tanggalnya tumpang tindih
     * dengan rentang [checkIn, checkOut). Check-out dianggap eksklusif,
     * sehingga check-in baru pada hari yang sama dengan check-out lama
     * tetap diperbolehkan.
     */
    public function scopeOverlapping(
        Builder $query,
        int $roomId,
        CarbonInterface $checkIn,
        CarbonInterface $checkOut,
        ?int $ignoreId = null
    ): Builder {
        return $query
            ->where('room_id', $roomId)
            ->menahanKamar()
            ->when($ignoreId, fn (Builder $q) => $q->where('id', '!=', $ignoreId))
            ->where('check_in', '<', $checkOut)
            ->where('check_out', '>', $checkIn);
    }

    /**
     * Booking yang MASIH menahan kamarnya.
     *
     * Begitu tamu di-check-out, kamarnya langsung terbuka dan boleh dijual
     * lagi lewat booking baru pada tanggal yang sama — termasuk pada early
     * check-out. Riwayatnya tetap utuh: baris booking lama tidak diubah,
     * hanya tidak lagi dihitung sebagai penahan kamar.
     */
    public function scopeMenahanKamar(Builder $query): Builder
    {
        return $query->whereNull('checked_out_at');
    }
}
