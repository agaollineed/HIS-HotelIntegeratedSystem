<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Absensi extends Model
{
    protected $table = 'absensi';

    protected $fillable = [
        'karyawan_id', 'jadwal_id', 'tanggal', 'waktu_absen',
        'status', 'menit_telat', 'foto', 'foto_kartu', 'created_by',
    ];

    protected $casts = [
        'tanggal'     => 'date',
        'waktu_absen' => 'datetime',
        'menit_telat' => 'integer',
    ];

    public const HADIR = 'hadir';
    public const TELAT = 'telat';

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function jadwal(): BelongsTo
    {
        return $this->belongsTo(Jadwal::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeTanggal(Builder $query, CarbonInterface $tanggal): Builder
    {
        return $query->whereDate('tanggal', $tanggal->toDateString());
    }

    public function telat(): bool
    {
        return $this->status === self::TELAT;
    }

    /**
     * "Telat 12 menit" / "Telat 1 jam 5 menit".
     */
    public function labelTelat(): string
    {
        if (! $this->telat()) {
            return 'Tepat waktu';
        }

        $jam   = intdiv($this->menit_telat, 60);
        $menit = $this->menit_telat % 60;

        return 'Telat ' . ($jam > 0 ? $jam . ' jam ' : '') . $menit . ' menit';
    }
}
