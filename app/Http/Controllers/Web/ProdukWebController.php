<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

class ProdukWebController extends Controller
{
    public function dashboard(): Response
    {
        $produk = Product::all(['id', 'sku', 'name', 'harga', 'stock', 'created_at', 'updated_at']);

        return Inertia::render('welcome', [
            'totalProduk'   => $produk->count(),
            'stokHabis'     => $produk->where('stock', 0)->count(),
            'hampirHabis'   => $produk->where('stock', '>', 0)->where('stock', '<=', 10)->count(),
            'produkTerbaru' => $produk->sortByDesc('created_at')->take(5)->values(),
        ]);
    }

    public function index(): Response
    {
        $produk = Product::orderBy('name')
            ->get(['id', 'sku', 'name', 'harga', 'stock', 'created_at', 'updated_at']);

        return Inertia::render('produk/index', [
            'produk' => $produk,
        ]);
    }

    public function show(string $sku): Response|\Illuminate\Http\RedirectResponse
    {
        $produk = Product::where('sku', $sku)
            ->first(['id', 'sku', 'name', 'harga', 'stock', 'created_at', 'updated_at']);

        if (!$produk) {
            return redirect('/produk')->with('error', "Produk dengan SKU {$sku} tidak ditemukan.");
        }

        return Inertia::render('produk/show', [
            'produk' => $produk,
        ]);
    }
}
