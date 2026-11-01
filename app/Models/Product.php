<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'name',
        'harga',
        'stock',
        'minimum_stock',
    ];

    protected $casts = [
        'stock' => 'integer',
        'harga' => 'integer',
        'minimum_stock' => 'integer',
    ];
}
