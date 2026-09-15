<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class Jadwal extends Model
{
    protected $table = 'jadwal';

    protected $fillable = ['karyawan_id', 'tanggal', 'jam_mulai', 'jam_selesai', 'created_by'];

    protected $casts = ['tanggal' => 'date'];

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Absensi untuk tanggal jadwal ini (satu orang satu hari).
     */
    public function absensi(): HasOne
    {
        return $this->hasOne(Absensi::class);
    }

    public function scopeTanggal(Builder $query, CarbonInterface $tanggal): Builder
    {
        return $query->whereDate('tanggal', $tanggal->toDateString());
    }

    /**
     * Shift yang melewati tengah malam, mis. 21:00–07:00.
     */
    public function lintasHari(): bool
    {
        return $this->jam_selesai <= $this->jam_mulai;
    }

    /**
     * Format seperti di grup WhatsApp: "06.00 - 15.00".
     */
    public function labelJam(): string
    {
        return str_replace(':', '.', $this->jam_mulai) . ' - ' . str_replace(':', '.', $this->jam_selesai);
    }

    /**
     * Waktu mulai shift sebagai objek tanggal-waktu penuh.
     */
    public function mulaiPada(): Carbon
    {
        [$jam, $menit] = array_map('intval', explode(':', $this->jam_mulai));

        return $this->tanggal->copy()->setTime($jam, $menit);
    }

    /**
     * Berapa menit keterlambatan bila absen dilakukan pada $waktu.
     * Perbandingan dibulatkan ke menit: 07:00:59 masih tepat waktu,
     * 07:01:00 sudah telat 1 menit.
     */
    public function menitTelat(CarbonInterface $waktu): int
    {
        $batas = $this->mulaiPada();
        $absen = $waktu->copy()->startOfMinute();

        return $absen->lessThanOrEqualTo($batas) ? 0 : $batas->diffInMinutes($absen);
    }
}
