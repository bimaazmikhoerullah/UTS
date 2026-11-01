<?php

namespace App\Messaging;

use App\Models\OrderStatus;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Illuminate\Support\Facades\Log;

/**
 * Consumer RabbitMQ untuk Supplier Order Gateway.
 * Menerima event StokDisesuaikan dan memproses pemesanan ulang ke supplier.
 */
class SupplierOrderConsumer
{
    public function __construct(
        protected RabbitPublisher $publisher,
    ) {
    }

    /**
     * Jalankan consumer secara terus-menerus (blocking).
     */
    public function run(): void
    {
        $c = config('rabbitmq');

        $connection = new AMQPStreamConnection(
            $c['host'], $c['port'], $c['user'], $c['password'], $c['vhost']
        );
        $channel = $connection->channel();

        // Pastikan exchange exists (should match publisher)
        $channel->exchange_declare($c['exchange'], 'topic', false, true, false);

        // Buat queue untuk menerima pesan
        [$queueName, ,] = $channel->queue_declare('', false, false, true, false);

        // Bind queue ke exchange dengan routing key pattern
        $channel->queue_bind($queueName, $c['exchange'], 'stok.*');

        Log::info('Supplier Order Gateway mendengarkan event stok.', ['queue' => $queueName]);

        $channel->basic_consume($queueName, false, false, false, false, function (AMQPMessage $msg) {
            $this->processMessage($msg);
        });

        while ($channel->is_consuming()) {
            $channel->wait();
        }

        $channel->close();
        $connection->close();
    }

    /**
     * Proses pesan yang diterima dari RabbitMQ.
     */
    protected function processMessage(AMQPMessage $msg): void
    {
        try {
            $data = json_decode($msg->getBody(), true);

            if (!$data || !isset($data['sku'])) {
                Log::warning('Supplier Order Gateway: pesan tidak valid.', [
                    'body' => $msg->getBody(),
                ]);
                $msg->ack();
                return;
            }

            Log::info('Supplier Order Gateway menerima event stok.', [
                'sku' => $data['sku'],
                'perubahan' => $data['perubahan'] ?? 0,
                'alasan' => $data['alasan'] ?? 'tidak_diketahui',
            ]);

            // Hanya proses jika alasan menunjukkan kebutuhan restock
            $alasan = $data['alasan'] ?? '';
            if (!in_array($alasan, ['restok', 'minimum_stock', 'stok_minimum', 'penjumlahan'])) {
                $msg->ack();
                return;
            }

            // Buat pemesanan ulang ke supplier
            $quantity = abs($data['perubahan'] ?? 0);
            if ($quantity <= 0) {
                $msg->ack();
                return;
            }

            $order = OrderStatus::buatPemesananUlang(
                sku: $data['sku'],
                quantity: $quantity,
                supplier: $data['supplier'] ?? null,
            );

            // Tandai sudah dikirim ke supplier (dalam implementasi nyata, kirim ke API supplier)
            $order->tandaiDikirim();

            Log::info('Supplier Order Gateway: pemesanan ulang dibuat.', [
                'order_id' => $order->order_id,
                'sku' => $data['sku'],
                'quantity' => $quantity,
            ]);

            $msg->ack();
        } catch (\Throwable $e) {
            Log::error('Supplier Order Gateway: gagal memproses pesan.', [
                'error' => $e->getMessage(),
                'body' => $msg->getBody(),
            ]);
            $msg->nack(false, true); // retry
        }
    }
}
