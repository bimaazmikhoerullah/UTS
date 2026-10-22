<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Outbox;
use App\Models\Product;
use App\Events\StokDisesuaikan;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ProdukFormController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sku'   => 'required|string|unique:products,sku',
            'name'  => 'required|string',
            'harga' => 'required|integer|min:0',
            'stock' => 'required|integer|min:0',
        ]);

        Product::create($validated);

        return redirect()->route('produk.index')
            ->with('success', "Produk {$validated['name']} berhasil ditambahkan.");
    }

    public function adjustStock(Request $request, string $sku): RedirectResponse
    {
        $product = Product::where('sku', $sku)->firstOrFail();

        $validated = $request->validate([
            'adjustment' => 'required|integer|not_in:0',
        ]);

        $perubahan = (int) $validated['adjustment'];
        $alasan    = $perubahan < 0 ? 'penjualan' : 'restok';

        try {
            DB::transaction(function () use ($product, $perubahan, $alasan) {
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
            });
        } catch (\Exception $e) {
            if ($e->getMessage() === 'STOK_TIDAK_CUKUP') {
                return redirect()->back()
                    ->withErrors(['adjustment' => 'Stok tidak cukup untuk dikurangi sebesar ' . abs($perubahan) . '.']);
            }
            throw $e;
        }

        return redirect()->route('produk.show', $sku)
            ->with('success', 'Stok berhasil disesuaikan.');
    }
}
