<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderStatus extends Model
{
    use HasFactory;

    protected $table = 'order_status';

    protected $fillable = [
        'order_id',
        'sku',
        'quantity',
        'supplier',
        'status',
        'notes',
        'sent_at',
        'confirmed_at',
        'received_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'sent_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'received_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Buat pesanan ulang baru untuk supplier.
     */
    public static function buatPemesananUlang(string $sku, int $quantity, ?string $supplier = null): self
    {
        return static::create([
            'order_id' => (string) \Illuminate\Support\Str::uuid(),
            'sku' => $sku,
            'quantity' => $quantity,
            'supplier' => $supplier,
            'status' => 'pending',
        ]);
    }

    /**
     * Tandai pesanan sudah dikirim ke supplier.
     */
    public function tandaiDikirim(): self
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
        return $this->refresh();
    }

    /**
     * Tandai pesanan sudah dikonfirmasi oleh supplier.
     */
    public function tandaiDikonfirmasi(?string $notes = null): self
    {
        $this->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'notes' => $notes,
        ]);
        return $this->refresh();
    }

    /**
     * Tandai pesanan sudah diterima (stok masuk).
     */
    public function tandaiDiterima(?string $notes = null): self
    {
        $this->update([
            'status' => 'received',
            'received_at' => now(),
            'notes' => $notes,
        ]);
        return $this->refresh();
    }

    /**
     * Batalkan pesanan.
     */
    public function batalkan(?string $alasan = null): self
    {
        $this->update([
            'status' => 'cancelled',
            'notes' => $alasan,
        ]);
        return $this->refresh();
    }
}
