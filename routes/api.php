<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProdukController;
use App\Http\Middleware\FormatJsonResponse;

Route::middleware([FormatJsonResponse::class])->group(function () {
    Route::get('/', function () {
        return response()->json([
            'name' => 'Layanan Katalog dan Stok Toko Berkah Jaya API',
            'version' => 'v1',
            'status' => 'active',
            'endpoints' => [
                'list_produk' => url('/api/v1/produk'),
                'tambah_produk' => url('/api/v1/produk'),
                'detail_produk' => url('/api/v1/produk/{sku}'),
                'penyesuaian_stok' => url('/api/v1/produk/{sku}/penyesuaian-stok'),
            ],
        ]);
    });

    Route::prefix('v1')->group(function () {
        Route::get('/', function () {
            return response()->json([
                'name' => 'Layanan Katalog dan Stok Toko Berkah Jaya API',
                'version' => '1.0.0',
                'status' => 'active',
                'endpoints' => [
                    'list_produk' => [
                        'method' => 'GET',
                        'url' => url('/api/v1/produk'),
                        'description' => 'Daftar semua produk',
                    ],
                    'tambah_produk' => [
                        'method' => 'POST',
                        'url' => url('/api/v1/produk'),
                        'description' => 'Tambah produk baru',
                    ],
                    'detail_produk' => [
                        'method' => 'GET',
                        'url' => url('/api/v1/produk/{sku}'),
                        'description' => 'Detail produk berdasarkan SKU',
                    ],
                    'penyesuaian_stok' => [
                        'method' => 'POST',
                        'url' => url('/api/v1/produk/{sku}/penyesuaian-stok'),
                        'description' => 'Sesuaikan stok (bisa juga via POST /api/v1/produk/{sku})',
                    ],
                ],
            ]);
        });

        Route::apiResource('produk', ProdukController::class)
             ->only(['index', 'store', 'show']);

        Route::post('produk/{sku}/penyesuaian-stok', [ProdukController::class, 'adjustStock'])
             ->name('produk.adjustStock');

        Route::post('produk/{sku}', [ProdukController::class, 'adjustStock'])
             ->name('produk.adjustStockDirect');
    });

    Route::fallback(function () {
        return response()->json([
            'type' => 'about:blank',
            'title' => 'Endpoint Not Found',
            'status' => 404,
            'detail' => 'Route API yang diminta tidak ditemukan.',
            'instance' => request()->fullUrl(),
        ], 404);
    });
});
