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
use Symfony\Component\HttpFoundation\Response;

class ProdukController extends Controller
{
    public function index(): JsonResponse
    {
        $products = Product::orderBy('name')->get();
        return response()->json($products);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sku'   => 'required|string|unique:products,sku',
            'name'  => 'required|string',
            'harga' => 'required|integer|min:0',
            'stock' => 'required|integer|min:0',
        ]);

        $product = Product::create($validated);

        return response()->json($product, Response::HTTP_CREATED)
                        ->header('Location', url("/api/v1/produk/{$product->sku}"));
    }

    public function show(string $sku): JsonResponse
    {
        $product = Product::where('sku', $sku)->first();

        if (!$product) {
            return response()->json([
                'type'     => 'about:blank',
                'title'    => 'Product Not Found',
                'status'   => Response::HTTP_NOT_FOUND,
                'detail'   => "Product with SKU {$sku} not found.",
                'instance' => request()->fullUrl(),
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json($product);
    }

    public function adjustStock(Request $request, string $sku): JsonResponse
    {
        $product = Product::where('sku', $sku)->first();

        if (!$product) {
            return response()->json([
                'type'     => 'about:blank',
                'title'    => 'Product Not Found',
                'status'   => Response::HTTP_NOT_FOUND,
                'detail'   => "Product with SKU {$sku} not found.",
                'instance' => request()->fullUrl(),
            ], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'adjustment' => 'sometimes|integer',
            'quantity'   => 'sometimes|integer',
            'perubahan'  => 'sometimes|integer',
            'alasan'     => 'sometimes|string',
        ]);

        $perubahan = $validated['adjustment'] ?? $validated['perubahan'] ?? $validated['quantity'] ?? null;

        if ($perubahan === null) {
            return response()->json([
                'type'     => 'about:blank',
                'title'    => 'Validation Error',
                'status'   => Response::HTTP_UNPROCESSABLE_ENTITY,
                'detail'   => 'Salah satu dari field "adjustment", "quantity", atau "perubahan" wajib diisi.',
                'instance' => request()->fullUrl(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $alasan    = $validated['alasan'] ?? ($perubahan < 0 ? 'penjualan' : 'restok');

        try {
            $product = DB::transaction(function () use ($product, $perubahan, $alasan) {
                $terkini = Product::whereKey($product->id)->lockForUpdate()->first();

                if ($terkini->stock + $perubahan < 0) {
                    throw new \Exception('STOK_TIDAK_CUKUP');
                }

                $terkini->increment('stock', $perubahan);
                $terkini->refresh();

                $event = new StokDisesuaikan(
                    sku: $terkini->sku,
                    perubahan: $perubahan,
                    alasan: $alasan,
                    stokSesudah: $terkini->stock,
                    harga: $terkini->harga ?? 0,
                );

                Outbox::catat($event);

                // Kirim ke RabbitMQ sekarang (best-effort, non-blocking). Outbox tetap
                // jadi sumber kebenaran: jika broker down, pesan tidak hilang —
                // outbox relayer (job) akan mencoba mengirim ulang pesan yang masih
                // berstatus belum dikirim, sehingga pola outbox tetap aman.
                try {
                    $publisher = app(RabbitPublisher::class);
                    $publisher->publish(
                        routingKey: "stok.{$alasan}",
                        data: $event->payload(),
                        messageId: $event->id,
                        type: 'StokDisesuaikan',
                    );
                } catch (\Throwable $e) {
                    Log::warning('Gagal mempublikasikan event ke RabbitMQ (outbox tetap tersimpan).', [
                        'event' => $event->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                return $terkini;
            });
        } catch (\Exception $e) {
            if ($e->getMessage() === 'STOK_TIDAK_CUKUP') {
                return response()->json([
                    'type'     => 'about:blank',
                    'title'    => 'Conflict',
                    'status'   => Response::HTTP_CONFLICT,
                    'detail'   => 'Stock adjustment would result in negative stock.',
                    'instance' => request()->fullUrl(),
                ], Response::HTTP_CONFLICT);
            }
            throw $e;
        }

        return response()->json($product);
    }
}
