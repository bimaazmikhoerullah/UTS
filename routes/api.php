<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProdukController;
use App\Http\Controllers\Api\PosController;
use App\Http\Controllers\Api\TransaksiController;
use App\Http\Controllers\Api\PersediaanController;
use App\Http\Controllers\Api\ReportController;

Route::middleware(['api'])->group(function () {
    Route::get('/', function () {
        return response()->json([
            'name' => 'Layanan Katalog dan Stok Warung Kelontong Pak Hendy API',
            'version' => 'v1',
            'status' => 'active',
            'endpoints' => [
                'list_produk' => url('/api/v1/produk'),
                'tambah_produk' => url('/api/v1/produk'),
                'detail_produk' => url('/api/v1/produk/{sku}'),
                'penyesuaian_stok' => url('/api/v1/produk/{sku}/penyesuaian-stok'),
                'pos_transaksi' => url('/api/v1/pos/transaksi'),
                'transaksi_penjualan' => url('/api/v1/transaksi'),
                'transaksi_detail' => url('/api/v1/transaksi/{transactionId}'),
                'pemantauan_stok' => url('/api/v1/persediaan/monitor'),
                'pemesanan_ulang' => url('/api/v1/persediaan/reorder'),
                'daftar_pemesanan_ulang' => url('/api/v1/persediaan/reorder'),
                'laporan_penjualan' => url('/api/v1/reports/sales'),
                'laporan_stok' => url('/api/v1/reports/stock'),
            ],
        ]);
    });

    Route::prefix('v1')->group(function () {
        Route::get('/', function () {
            return response()->json([
                'name' => 'Layanan Katalog dan Stok Warung Kelontong Pak Hendy API',
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
                    'pos_transaksi' => [
                        'method' => 'POST',
                        'url' => url('/api/v1/pos/transaksi'),
                        'description' => 'Proses transaksi penjualan (POS)',
                    ],
                    'transaksi_penjualan' => [
                        'method' => 'POST',
                        'url' => url('/api/v1/transaksi'),
                        'description' => 'Catat transaksi penjualan dan kurangi stok',
                    ],
                    'transaksi_list' => [
                        'method' => 'GET',
                        'url' => url('/api/v1/transaksi'),
                        'description' => 'Daftar transaksi penjualan terbaru',
                    ],
                    'transaksi_detail' => [
                        'method' => 'GET',
                        'url' => url('/api/v1/transaksi/{transactionId}'),
                        'description' => 'Detail transaksi penjualan berdasarkan transaction_id',
                    ],
                    'pemantauan_stok' => [
                        'method' => 'GET',
                        'url' => url('/api/v1/persediaan/monitor'),
                        'description' => 'Pemantauan stok dan status produk',
                    ],
                    'pemesanan_ulang' => [
                        'method' => 'POST',
                        'url' => url('/api/v1/persediaan/reorder'),
                        'description' => 'Buat pemesanan ulang ke supplier',
                    ],
                    'daftar_pemesanan_ulang' => [
                        'method' => 'GET',
                        'url' => url('/api/v1/persediaan/reorder'),
                        'description' => 'Daftar pemesanan ulang (filter: ?status=, ?sku=)',
                    ],
                    'laporan_penjualan' => [
                        'method' => 'GET',
                        'url' => url('/api/v1/reports/sales'),
                        'description' => 'Laporan penjualan (revenue, jumlah transaksi, produk terlaris)',
                    ],
                    'laporan_stok' => [
                        'method' => 'GET',
                        'url' => url('/api/v1/reports/stock'),
                        'description' => 'Laporan persediaan (total stok, nilai stok, status)',
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

        Route::post('pos/transaksi', [PosController::class, 'processTransaction'])
             ->name('pos.transaction');

        Route::get('transaksi', [TransaksiController::class, 'index'])
             ->name('transaksi.index');
        Route::post('transaksi', [TransaksiController::class, 'createTransaction'])
             ->name('transaksi.store');
        Route::get('transaksi/{transactionId}', [TransaksiController::class, 'show'])
             ->name('transaksi.show');

        Route::get('persediaan/monitor', [PersediaanController::class, 'monitorStock'])
             ->name('persediaan.monitor');
        Route::get('persediaan/reorder', [PersediaanController::class, 'listReorders'])
             ->name('persediaan.reorder.index');
        Route::post('persediaan/reorder', [PersediaanController::class, 'createReorder'])
             ->name('persediaan.reorder.store');

        Route::get('reports/sales', [ReportController::class, 'sales'])
             ->name('reports.sales');
        Route::get('reports/stock', [ReportController::class, 'stock'])
             ->name('reports.stock');
    });

    Route::fallback(function () {
        return response()->json([
            'type' => 'about:blank',
            'title' => 'Endpoint Not Found',
            'status' => 404,
            'detail' => 'Route API yang diminta tidak ditemukan.',
            'instance' => request()->fullUrl(),
        ], 404, ['Content-Type' => 'application/problem+json']);
    });
});
