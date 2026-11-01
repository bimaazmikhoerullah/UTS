<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleTransaction extends Model
{
    protected $table = 'sales_transactions';

    protected $fillable = [
        'transaction_id',
        'total_price',
        'items',
    ];

    protected $casts = [
        'items' => 'array',
        'total_price' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
