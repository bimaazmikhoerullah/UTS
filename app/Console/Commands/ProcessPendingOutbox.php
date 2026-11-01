<?php

namespace App\Console\Commands;

use App\Jobs\ProcessOutboxMessage;
use App\Models\Outbox;
use Illuminate\Console\Command;

class ProcessPendingOutbox extends Command
{
    protected $signature = 'outbox:process {--limit=50 : Jumlah pesan yang diproses per sekali}';
    protected $description = 'Proses pesan outbox yang masih menunggu dikirim ke RabbitMQ';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $pending = Outbox::whereNull('dikirim_pada')
            ->orderBy('id')
            ->take($limit)
            ->get();

        if ($pending->isEmpty()) {
            $this->info('Tidak ada pesan outbox yang perlu diproses.');
            return Command::SUCCESS;
        }

        $this->info("Memproses {$pending->count()} pesan outbox...");

        foreach ($pending as $outbox) {
            ProcessOutboxMessage::dispatch($outbox->id);
        }

        $this->info('Selesai. Pesan telah diantrekan untuk dikirim.');

        return Command::SUCCESS;
    }
}
