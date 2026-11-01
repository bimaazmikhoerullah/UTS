<?php

namespace App\Console\Commands;

use App\Messaging\SupplierOrderConsumer;
use Illuminate\Console\Command;

class RunSupplierOrderGateway extends Command
{
    protected $signature = 'supplier:gateway {--d : Run as daemon (continuous)}';
    protected $description = 'Jalankan Supplier Order Gateway consumer RabbitMQ';

    public function handle(): int
    {
        $this->info('Supplier Order Gateway dimulai...');
        $this->info('Mendengarkan exchange toko.events untuk routing key stok.*');
        $this->info('Tekan Ctrl+C untuk berhenti.');

        $consumer = app(SupplierOrderConsumer::class);
        $consumer->run();

        return Command::SUCCESS;
    }
}
