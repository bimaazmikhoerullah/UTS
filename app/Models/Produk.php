<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Produk extends Model
{
    use HasFactory;

    protected $table = 'products';

    protected $fillable = [
        'sku',
        'nama',
        'name',
        'harga',
        'stok',
        'stock',
    ];

    protected $casts = [
        'harga' => 'integer',
        'stock' => 'integer',
    ];

    // Mutator: input 'nama' otomatis disimpan ke kolom database 'name'
    public function setNamaAttribute($value)
    {
        $this->attributes['name'] = $value;
    }

    // Accessor: baca 'nama' dari kolom database 'name'
    public function getNamaAttribute()
    {
        return $this->attributes['name'] ?? '';
    }

    // Mutator: input 'stok' otomatis disimpan ke kolom database 'stock'
    public function setStokAttribute($value)
    {
        $this->attributes['stock'] = $value;
    }

    // Accessor: baca 'stok' dari kolom database 'stock'
    public function getStokAttribute()
    {
        return $this->attributes['stock'] ?? 0;
    }
}
