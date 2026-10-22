<?php

namespace App\Models;

use App\Events\StokDisesuaikan;
use Illuminate\Database\Eloquent\Model;

class Outbox extends Model
{
    protected $table = 'outbox';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'dikirim_pada' => 'datetime',
    ];

    /** Catat event sebagai pesan yang menunggu dikirim. Panggil di DALAM transaksi bisnis. */
    public static function catat(StokDisesuaikan $event): self
    {
        return static::create([
            'message_id' => $event->id,
            'jenis' => 'StokDisesuaikan',
            'routing_key' => "stok.{$event->alasan}",
            'payload' => $event->payload(),
        ]);
    }
}
