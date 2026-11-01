<?php

namespace App\Http\Controllers\Api;

use App\Events\StokDisesuaikan;
use App\Http\Controllers\Controller;
use App\Messaging\RabbitPublisher;
use App\Models\Outbox;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class PosController extends Controller
{
    public function processTransaction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.sku' => 'required|string|exists:products,sku',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            $result = DB::transaction(function () use ($validated) {
                $transactionId = (string) Str::uuid();
                $totalPrice = 0;
                $updatedProducts = [];

                foreach ($validated['items'] as $item) {
                    $product = Product::where('sku', $item['sku'])->lockForUpdate()->first();
                    $quantity = $item['quantity'];

                    if ($product->stock < $quantity) {
                        throw new \Exception("STOK_TIDAK_CUKUP: {$product->sku}");
                    }

                    $product->decrement('stock', $quantity);
                    $product->refresh();

                    $totalPrice += $product->harga * $quantity;
                    $updatedProducts[] = $product;

                    $event = new StokDisesuaikan(
                        sku: $product->sku,
                        perubahan: -$quantity,
                        alasan: 'penjualan',
                        stokSesudah: $product->stock,
                        harga: $product->harga,
                    );

                    Outbox::catat($event);

                    try {
                        $publisher = app(RabbitPublisher::class);
                        $publisher->publish(
                            routingKey: "stok.penjualan",
                            data: $event->payload(),
                            messageId: $event->id,
                            type: 'StokDisesuaikan',
                        );
                    } catch (\Throwable $e) {
                        Log::warning('Gagal mempublikasikan event POS ke RabbitMQ.', [
                            'event' => $event->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                return [
                    'transaction_id' => $transactionId,
                    'total_price' => $totalPrice,
                    'items' => $updatedProducts,
                ];
            });

            return response()->json($result);

        } catch (\Exception $e) {
            if (str_starts_with($e->getMessage(), 'STOK_TIDAK_CUKUP')) {
                return response()->json([
                    'type'     => 'about:blank',
                    'title'    => 'Conflict',
                    'status'   => Response::HTTP_CONFLICT,
                    'detail'   => $e->getMessage(),
                    'instance' => request()->fullUrl(),
                ], Response::HTTP_CONFLICT, ['Content-Type' => 'application/problem+json']);
            }
            throw $e;
        }
    }
}
