<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu hari di buku besar: uang masuk & keluar di BNI dan BRI.
 * Saldo akhir tidak disimpan — dihitung berjalan dari saldo awal.
 */
class BukuBesar extends Model
{
    protected $table = 'buku_besar';

    protected $fillable = ['tanggal', 'masuk_bni', 'masuk_bri', 'keluar_bni', 'keluar_bri', 'keterangan', 'dicatat_by'];

    protected $casts = [
        'tanggal'    => 'date',
        'masuk_bni'  => 'integer',
        'masuk_bri'  => 'integer',
        'keluar_bni' => 'integer',
        'keluar_bri' => 'integer',
    ];

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_by');
    }

    public function masukTotal(): int
    {
        return $this->masuk_bni + $this->masuk_bri;
    }

    public function keluarTotal(): int
    {
        return $this->keluar_bni + $this->keluar_bri;
    }
}
