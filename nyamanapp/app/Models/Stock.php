<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    protected $fillable = [
        'nama',
        'jumlah',
        'harga',
        'updated_by',
    ];

    protected $casts = [
        'jumlah' => 'integer',
        'harga'  => 'integer',
    ];
}
