<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Permintaan perubahan booking yang diajukan staff dan diputus admin.
 *
 * Kolom bernilai null berarti bagian itu TIDAK diusulkan berubah. Seluruh
 * pembacaan di kelas ini mengikuti aturan itu, sehingga sebuah permintaan bisa
 * hanya memindah kamar tanpa menyentuh tanggal, atau sebaliknya.
 */
class BookingRequest extends Model
{
    protected $fillable = [
        'booking_id',
        'jenis',
        'room_id_baru',
        'nama_tamu_baru',
        'telepon_baru',
        'check_in_baru',
        'durasi_baru',
        'alasan',
        'status',
        'catatan_admin',
        'diajukan_by',
        'diputus_by',
        'diputus_at',
    ];

    protected $casts = [
        'check_in_baru' => 'date',
        'durasi_baru'   => 'integer',
        'diputus_at'    => 'datetime',
    ];

    public const MENUNGGU  = 'menunggu';
    public const DISETUJUI = 'disetujui';
    public const DITOLAK   = 'ditolak';

    /**
     * Jenis perubahan. Satu permintaan hanya membawa SATU jenis, sehingga
     * admin selalu memutuskan satu hal yang jelas.
     */
    public const PINDAH_KAMAR = 'pindah_kamar';
    public const PERPANJANG   = 'perpanjang';
    public const UBAH_TANGGAL = 'ubah_tanggal';
    public const UBAH_TAMU    = 'ubah_tamu';

    /** jenis => label yang dibaca manusia. Urutannya = urutan di formulir. */
    public const JENIS = [
        self::PINDAH_KAMAR => 'Pindah Kamar',
        self::PERPANJANG   => 'Perpanjang Menginap',
        self::UBAH_TANGGAL => 'Ubah Tanggal Menginap',
        self::UBAH_TAMU    => 'Ubah Nama / Telepon Tamu',
    ];

    public function labelJenis(): string
    {
        return self::JENIS[$this->jenis] ?? 'Perubahan';
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function roomBaru(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id_baru');
    }

    public function pengaju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diajukan_by');
    }

    public function pemutus(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diputus_by');
    }

    public function scopeMenunggu(Builder $query): Builder
    {
        return $query->where('status', self::MENUNGGU);
    }

    /**
     * Belum diputus admin.
     *
     * Namanya sengaja BUKAN menunggu(): nama itu sudah dipakai scope query di
     * atas, dan Eloquent akan menganggapnya metode instance sehingga
     * BookingRequest::menunggu() gagal dipanggil sebagai scope.
     */
    public function belumDiputus(): bool
    {
        return $this->status === self::MENUNGGU;
    }

    public function labelStatus(): string
    {
        return match ($this->status) {
            self::DISETUJUI => 'Disetujui',
            self::DITOLAK   => 'Ditolak',
            default         => 'Menunggu Persetujuan',
        };
    }

    /** Tanggal keluar yang diusulkan, bila tanggal/durasi ikut diubah. */
    public function checkOutBaru(): ?\Illuminate\Support\Carbon
    {
        $mulai  = $this->check_in_baru ?? $this->booking?->check_in;
        $durasi = $this->durasi_baru ?? $this->booking?->durasi;

        if ($mulai === null || $durasi === null) {
            return null;
        }

        return $mulai->copy()->addDays(max(1, (int) $durasi));
    }

    /**
     * Daftar perubahan sebagai pasangan "Apa: dari → menjadi".
     *
     * Dibangun dari baris booking yang masih berlaku, jadi admin selalu
     * melihat perbandingan terhadap keadaan SEKARANG — bukan terhadap keadaan
     * saat permintaan diajukan, yang bisa saja sudah berubah sejak itu.
     *
     * @return array<int, array{label: string, dari: string, jadi: string}>
     */
    public function perubahan(): array
    {
        $b = $this->booking;

        if ($b === null) {
            return [];
        }

        $baris = [];

        if ($this->room_id_baru !== null && $this->room_id_baru !== $b->room_id) {
            $baris[] = [
                'label' => 'Kamar',
                'dari'  => $b->room?->nomor ?? '—',
                'jadi'  => $this->roomBaru?->nomor ?? '—',
            ];
        }

        if ($this->nama_tamu_baru !== null && $this->nama_tamu_baru !== $b->nama_tamu) {
            $baris[] = ['label' => 'Nama Tamu', 'dari' => $b->nama_tamu, 'jadi' => $this->nama_tamu_baru];
        }

        if ($this->telepon_baru !== null && $this->telepon_baru !== $b->telepon) {
            $baris[] = ['label' => 'Telepon', 'dari' => $b->telepon ?? '—', 'jadi' => $this->telepon_baru];
        }

        if ($this->check_in_baru !== null && ! $this->check_in_baru->isSameDay($b->check_in)) {
            $baris[] = [
                'label' => 'Check-in',
                'dari'  => $b->check_in->translatedFormat('j M Y'),
                'jadi'  => $this->check_in_baru->translatedFormat('j M Y'),
            ];
        }

        if ($this->durasi_baru !== null && (int) $this->durasi_baru !== (int) $b->durasi) {
            $baris[] = [
                'label' => 'Lama Menginap',
                'dari'  => $b->durasi . ' malam (keluar ' . $b->check_out->translatedFormat('j M') . ')',
                'jadi'  => $this->durasi_baru . ' malam (keluar '
                    . ($this->checkOutBaru()?->translatedFormat('j M') ?? '—') . ')',
            ];
        }

        return $baris;
    }

    /**
     * Perubahan ini sudah tidak berlaku lagi.
     *
     * Booking-nya bisa saja sudah diubah admin secara langsung setelah
     * permintaan diajukan, sehingga usulannya menjadi sama dengan keadaan
     * sekarang. Menyetujui yang seperti ini tidak berbahaya, tetapi admin
     * berhak tahu bahwa tidak ada lagi yang akan berubah.
     */
    public function usang(): bool
    {
        return $this->perubahan() === [];
    }

    /** Ringkasan satu baris untuk daftar: "Kamar 101 → 205". */
    public function ringkasan(): string
    {
        $baris = $this->perubahan();

        if ($baris === []) {
            return 'Tidak ada lagi yang berubah';
        }

        return implode(' · ', array_map(
            fn (array $b) => $b['dari'] . ' → ' . $b['jadi'],
            $baris
        ));
    }
}
