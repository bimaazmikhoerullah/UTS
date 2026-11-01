<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\OrderStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PersediaanController extends Controller
{
    public function monitorStock(): JsonResponse
    {
        $products = Product::query()->get();

        $products = $products->map(function ($product) {
            $minimumStock = (int) ($product->minimum_stock ?? 5);
            $stock = (int) $product->stock;

            return [
                'sku' => $product->sku,
                'name' => $product->name,
                'stock' => $stock,
                'minimum_stock' => $minimumStock,
                'status' => match (true) {
                    $stock === 0 => 'out_of_stock',
                    $stock <= $minimumStock => 'low_stock',
                    default => 'healthy',
                },
                'inventory_value' => $stock * (int) ($product->harga ?? 0),
            ];
        });

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'total_products' => $products->count(),
            'out_of_stock_count' => $products->where('status', 'out_of_stock')->count(),
            'low_stock_count' => $products->where('status', 'low_stock')->count(),
            'total_inventory_value' => $products->sum('inventory_value'),
            'data' => $products->values(),
        ]);
    }

    public function createReorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sku' => 'required|string|exists:products,sku',
            'quantity' => 'required|integer|min:1',
            'supplier' => 'sometimes|string',
            'notes' => 'sometimes|string',
        ]);

        $order = DB::transaction(function () use ($validated) {
            $product = Product::where('sku', $validated['sku'])->lockForUpdate()->first();

            $order = OrderStatus::buatPemesananUlang(
                sku: $product->sku,
                quantity: $validated['quantity'],
                supplier: $validated['supplier'] ?? null,
            );

            if (! empty($validated['notes'])) {
                $order->notes = $validated['notes'];
                $order->save();
            }

            return $order;
        });

        return response()->json($order, Response::HTTP_CREATED);
    }

    public function listReorders(Request $request): JsonResponse
    {
        $status = $request->query('status');
        $sku = $request->query('sku');

        $orders = OrderStatus::query()
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($sku, fn ($query) => $query->where('sku', $sku))
            ->latest()
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $orders,
            'total' => $orders->count(),
            'filters' => [
                'status' => $status,
                'sku' => $sku,
            ],
        ]);
    }
}
