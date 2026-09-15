<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id', 'jenis', 'uraian', 'keterangan',
        'jumlah_kamar', 'malam', 'harga', 'subtotal', 'urutan',
    ];

    protected $casts = [
        'jumlah_kamar' => 'integer',
        'malam'        => 'integer',
        'harga'        => 'integer',
        'subtotal'     => 'integer',
        'urutan'       => 'integer',
    ];

    public const KAMAR = 'kamar';
    public const BIAYA = 'biaya';

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function baris(): bool
    {
        return $this->jenis === self::KAMAR;
    }
}
