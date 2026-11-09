<?php

namespace App\Models;

use App\Http\Controllers\Admin\RevenueController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Catatan keuangan NYATA per bulan.
 *
 * Angka di sini diketik manual oleh pemilik dari rekening dan buku kas —
 * bukan dihitung dari booking. Itu bukan kekurangan, itu inti gunanya:
 * laporan pendapatan menjawab "berapa yang SEHARUSNYA masuk menurut catatan
 * kamar", tabel ini menjawab "berapa yang BENAR-BENAR masuk". Selisih di
 * antara keduanya adalah satu-satunya angka yang bisa memunculkan kebocoran
 * tanpa perlu menuduh siapa pun lebih dulu.
 *
 * Kalau keduanya berasal dari sumber yang sama, selisihnya selalu nol dan
 * seluruh fitur ini tidak ada gunanya.
 */
class Keuangan extends Model
{
    protected $table = 'keuangan_bulanan';

    protected $fillable = [
        'periode',
        'pemasukan',
        'pengeluaran',
        'catatan',
        'dicatat_by',
    ];

    protected $casts = [
        'periode'     => 'date',
        'pemasukan'   => 'integer',
        'pengeluaran' => 'integer',
    ];

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_by');
    }

    /**
     * Normalkan sebuah bulan menjadi tanggal 1.
     *
     * Seluruh baris disimpan pada tanggal 1 supaya "April 2026" hanya punya
     * satu bentuk. Tanpa ini, 2026-04-01 dan 2026-04-30 akan menjadi dua baris
     * berbeda untuk bulan yang sama, dan kunci uniknya tidak menahan apa pun.
     */
    public static function periodeDari(string $bulan): ?Carbon
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulan)) {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d', $bulan . '-01')->startOfDay();
    }

    /** "April 2026" */
    public function labelPeriode(): string
    {
        return $this->periode->translatedFormat('F Y');
    }

    /** "2026-04" — dipakai sebagai nilai input type=month. */
    public function kunciPeriode(): string
    {
        return $this->periode->format('Y-m');
    }

    /** Bonus karyawan: persentase dari keuntungan bulan itu. */
    public const PERSEN_BONUS = 5;

    /** Laba bersih bulan itu. Boleh negatif — bulan rugi memang ada. */
    public function laba(): int
    {
        return $this->pemasukan - $this->pengeluaran;
    }

    /** Keuntungan = omset − pengeluaran (nama lain laba bersih). */
    public function keuntungan(): int
    {
        return $this->laba();
    }

    /**
     * Bonus karyawan: PERSEN_BONUS dari keuntungan, dibulatkan ke rupiah.
     * Bulan rugi tidak menghasilkan bonus negatif — bonusnya nol.
     */
    public function bonusKaryawan(): int
    {
        return self::bonusDari($this->keuntungan());
    }

    public static function bonusDari(int $keuntungan): int
    {
        return $keuntungan > 0 ? (int) round($keuntungan * self::PERSEN_BONUS / 100) : 0;
    }

    /**
     * Pendapatan menurut CATATAN KAMAR pada bulan yang sama.
     *
     * Dihitung ulang dari booking dengan aturan yang sama persis dengan
     * grafik pendapatan harian, termasuk tarif tanpa sarapan — kalau memakai
     * aturan yang berbeda, selisihnya akan selalu ada dan tidak berarti
     * apa-apa.
     */
    public function pendapatanTercatat(): int
    {
        return RevenueController::totalBulan($this->periode);
    }

    /**
     * Selisih setoran terhadap catatan kamar.
     *
     * Negatif = uang yang masuk lebih sedikit dari yang seharusnya.
     */
    public function selisih(): int
    {
        return $this->pemasukan - $this->pendapatanTercatat();
    }

    public static function rupiah(int $n): string
    {
        return ($n < 0 ? '-Rp ' : 'Rp ') . number_format(abs($n), 0, ',', '.');
    }
}
