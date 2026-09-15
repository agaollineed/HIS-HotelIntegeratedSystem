<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = [
        'nomor', 'booking_id', 'nama_tamu', 'tipe_kamar', 'nomor_kamar',
        'malam', 'harga_per_malam', 'total',
        'tanggal_masuk', 'tanggal_keluar',
        'breakfast', 'early_checkin', 'lunas', 'catatan', 'created_by',
    ];

    protected $casts = [
        'malam'           => 'integer',
        'harga_per_malam' => 'integer',
        'total'           => 'integer',
        'tanggal_masuk'   => 'date',
        'tanggal_keluar'  => 'date',
        'breakfast'       => 'boolean',
        'early_checkin'   => 'boolean',
        'lunas'           => 'boolean',
    ];

    /** Identitas hotel — dipakai pada kop invoice. */
    public const HOTEL = [
        'nama'    => 'Nyaman Hotel Magetan',
        'alamat'  => 'Jl. A. Yani No.109, Dusun Onggoprayan, Kepolorejo, Kec. Magetan, Kabupaten Magetan, Jawa Timur 63382',
        'telepon' => '+62851-9088-0080',
    ];

    /** Rincian baris: kamar dan biaya tambahan, terurut. */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('urutan')->orderBy('id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Nomor urut per bulan: INV/2026/08/0001.
     *
     * Diambil dari nomor terbesar pada bulan berjalan, bukan dari jumlah
     * baris — invoice yang dihapus tidak boleh membuat nomor terpakai ulang.
     *
     * Urutan dihitung dari nilai ANGKA di belakang, bukan urutan teks.
     * Perbandingan teks menempatkan "9999" di atas "10000" (karena '9' > '1'),
     * sehingga begitu melewati nomor keempat digit, nomor yang sama akan
     * dihasilkan berulang kali dan tertolak oleh batasan unik.
     */
    public static function nomorBerikutnya(): string
    {
        $prefix = 'INV/' . now()->format('Y/m') . '/';

        $tertinggi = static::where('nomor', 'like', $prefix . '%')
            ->pluck('nomor')
            ->map(fn ($n) => (int) substr((string) $n, strlen($prefix)))
            ->max() ?? 0;

        return $prefix . str_pad((string) ($tertinggi + 1), 4, '0', STR_PAD_LEFT);
    }

    /** "Rp 300.000" */
    public static function rupiah(int $nilai): string
    {
        return 'Rp ' . number_format($nilai, 0, ',', '.');
    }

    /**
     * Keterangan tambahan sebagai daftar siap tampil.
     */
    public function keterangan(): array
    {
        $baris = [];

        $baris[] = $this->breakfast ? 'Termasuk sarapan' : 'Tanpa sarapan';

        if ($this->early_checkin) {
            $baris[] = 'Early check-in';
        }

        $baris[] = $this->lunas ? 'LUNAS' : 'Belum lunas';

        return $baris;
    }
}
