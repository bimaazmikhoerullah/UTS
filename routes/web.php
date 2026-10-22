<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Web\ProdukWebController;

Route::get('/', [ProdukWebController::class, 'dashboard'])->name('home');
Route::get('/produk', [ProdukWebController::class, 'index'])->name('produk.index');
Route::get('/produk/{sku}', [ProdukWebController::class, 'show'])->name('produk.show');

// Inertia form POST: tambah produk & penyesuaian stok (redirect back ke halaman web)
use App\Http\Controllers\Web\ProdukFormController;
Route::post('/produk', [ProdukFormController::class, 'store'])->name('produk.store');
Route::post('/produk/{sku}/stok', [ProdukFormController::class, 'adjustStock'])->name('produk.stok');
