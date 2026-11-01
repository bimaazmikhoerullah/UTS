<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SaleTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function sales(): JsonResponse
    {
        $totalRevenue = (int) SaleTransaction::sum('total_price');
        $totalTransactions = SaleTransaction::count();

        $topProducts = SaleTransaction::all()
            ->flatMap(fn ($transaction) => $transaction->items ?? [])
            ->groupBy('sku')
            ->map(function ($items, $sku) {
                $quantity = $items->sum(fn ($item) => (int) ($item['quantity'] ?? 0));
                $name = $items->first(fn ($item) => ! empty($item['name']))['name'] ?? $sku;

                return [
                    'sku' => $sku,
                    'name' => $name,
                    'quantity_sold' => $quantity,
                ];
            })
            ->sortByDesc('quantity_sold')
            ->take(10)
            ->values();

        return response()->json([
            'report' => 'sales',
            'generated_at' => now()->toIso8601String(),
            'total_revenue' => $totalRevenue,
            'total_transactions' => $totalTransactions,
            'top_products' => $topProducts,
        ]);
    }

    public function stock(): JsonResponse
    {
        $products = Product::all();
        $totalUnits = (int) $products->sum('stock');
        $stockValue = (int) $products->sum(fn ($product) => (int) $product->stock * (int) ($product->harga ?? 0));

        return response()->json([
            'report' => 'stock',
            'generated_at' => now()->toIso8601String(),
            'total_products' => $products->count(),
            'total_units' => $totalUnits,
            'stock_value' => $stockValue,
            'low_stock_count' => $products->filter(fn ($product) => (int) $product->stock <= (int) ($product->minimum_stock ?? 5))->count(),
            'out_of_stock_count' => $products->where('stock', 0)->count(),
        ]);
    }
}
