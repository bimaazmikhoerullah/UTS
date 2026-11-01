<?php

namespace App\Http\Controllers\Api;

use App\Events\StokDisesuaikan;
use App\Http\Controllers\Controller;
use App\Messaging\RabbitPublisher;
use App\Models\Outbox;
use App\Models\Product;
use App\Models\SaleTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class TransaksiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = max(1, min(100, (int) $request->query('limit', 20)));

        $transactions = SaleTransaction::query()
            ->latest('created_at')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $transactions,
            'total' => $transactions->count(),
            'limit' => $limit,
        ]);
    }

    public function show(string $transactionId): JsonResponse
    {
        $transaction = SaleTransaction::where('transaction_id', $transactionId)->first();

        if (! $transaction) {
            return response()->json([
                'type' => 'about:blank',
                'title' => 'Transaction Not Found',
                'status' => Response::HTTP_NOT_FOUND,
                'detail' => "Transaksi dengan transaction_id {$transactionId} tidak ditemukan.",
                'instance' => request()->fullUrl(),
            ], Response::HTTP_NOT_FOUND, ['Content-Type' => 'application/problem+json']);
        }

        return response()->json($transaction);
    }

    public function createTransaction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.sku' => 'required|string|exists:products,sku',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            $result = DB::transaction(function () use ($validated) {
                $transactionId = (string) Str::uuid();
                $totalPrice = 0;
                $items = [];

                foreach ($validated['items'] as $item) {
                    $product = Product::where('sku', $item['sku'])->lockForUpdate()->first();
                    $quantity = (int) $item['quantity'];

                    if ((int) $product->stock < $quantity) {
                        throw new \Exception("STOK_TIDAK_CUKUP: {$product->sku}");
                    }

                    $product->decrement('stock', $quantity);
                    $product->refresh();

                    $totalPrice += (int) $product->harga * $quantity;
                    $items[] = [
                        'sku' => $product->sku,
                        'name' => $product->name,
                        'quantity' => $quantity,
                        'price' => (int) $product->harga,
                        'subtotal' => (int) $product->harga * $quantity,
                    ];

                    $event = new StokDisesuaikan(
                        sku: $product->sku,
                        perubahan: -$quantity,
                        alasan: 'penjualan',
                        stokSesudah: (int) $product->stock,
                        harga: (int) $product->harga,
                    );

                    Outbox::catat($event);

                    try {
                        app(RabbitPublisher::class)->publish(
                            routingKey: 'stok.penjualan',
                            data: $event->payload(),
                            messageId: $event->id,
                            type: 'StokDisesuaikan',
                        );
                    } catch (\Throwable $e) {
                        Log::warning('Gagal mempublikasikan event transaksi ke RabbitMQ.', [
                            'event' => $event->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $transaction = SaleTransaction::create([
                    'transaction_id' => $transactionId,
                    'total_price' => $totalPrice,
                    'items' => $items,
                ]);

                return [
                    'transaction' => $transaction,
                    'total_price' => $totalPrice,
                    'items' => $items,
                ];
            });

            return response()->json($result, Response::HTTP_CREATED);
        } catch (\Exception $e) {
            if (str_starts_with($e->getMessage(), 'STOK_TIDAK_CUKUP')) {
                return response()->json([
                    'type' => 'about:blank',
                    'title' => 'Conflict',
                    'status' => Response::HTTP_CONFLICT,
                    'detail' => $e->getMessage(),
                    'instance' => request()->fullUrl(),
                ], Response::HTTP_CONFLICT, ['Content-Type' => 'application/problem+json']);
            }

            throw $e;
        }
    }
}
