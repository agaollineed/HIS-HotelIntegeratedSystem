<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class StockLog extends Model
{
    protected $fillable = [
        'stock_id',
        'nama',
        'action',
        'keterangan',
        'selisih',
        'by_name',
        'by_role',
    ];

    protected $casts = [
        'selisih' => 'integer',
    ];

    /**
     * Catat satu entri log. Pelaku diambil otomatis dari user yang sedang login.
     */
    public static function rekam(Stock $stock, string $action, string $keterangan, ?int $selisih = null): void
    {
        $user = Auth::user();

        static::create([
            'stock_id'   => $stock->id,
            'nama'       => $stock->nama,
            'action'     => $action,
            'keterangan' => $keterangan,
            'selisih'    => $selisih,
            'by_name'    => $user?->name ?? 'Sistem',
            'by_role'    => $user?->role ?? '-',
        ]);
    }
}
