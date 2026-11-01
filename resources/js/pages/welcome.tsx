import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import {
    ShoppingCart,
    Plus,
    Minus,
    Trash2,
    CheckCircle2,
    AlertTriangle,
    Package,
    Receipt,
    RefreshCw,
    FileText,
    Layers,
    Store,
    ArrowUpRight,
    Terminal,
    Check,
    X,
} from 'lucide-react';

interface Product {
    id: number;
    sku: string;
    name: string;
    harga: number;
    stock: number;
    minimum_stock: number;
}

interface CartItem {
    product: Product;
    quantity: number;
}

interface TransactionResult {
    success: boolean;
    data?: any;
    error?: {
        type?: string;
        title?: string;
        status?: number;
        detail?: string;
        instance?: string;
        invalid_params?: Array<{ name: string; reason: string }>;
    };
}

export default function Welcome({ products: initialProducts = [] }: { products?: Product[] }) {
    const [products, setProducts] = useState<Product[]>(initialProducts);
    const [cart, setCart] = useState<CartItem[]>([]);
    const [search, setSearch] = useState('');
    const [loading, setLoading] = useState(false);
    const [result, setResult] = useState<TransactionResult | null>(null);
    const [showCurlModal, setShowCurlModal] = useState(false);

    // Filter produk
    const filteredProducts = products.filter(
        (p) =>
            p.name.toLowerCase().includes(search.toLowerCase()) ||
            p.sku.toLowerCase().includes(search.toLowerCase())
    );

    // Keranjang aksi
    const addToCart = (product: Product) => {
        if (product.stock <= 0) return;
        setCart((prev) => {
            const existing = prev.find((item) => item.product.sku === product.sku);
            if (existing) {
                if (existing.quantity >= product.stock) return prev;
                return prev.map((item) =>
                    item.product.sku === product.sku
                        ? { ...item, quantity: item.quantity + 1 }
                        : item
                );
            }
            return [...prev, { product, quantity: 1 }];
        });
    };

    const updateQuantity = (sku: string, delta: number) => {
        setCart((prev) =>
            prev
                .map((item) => {
                    if (item.product.sku === sku) {
                        const newQty = item.quantity + delta;
                        if (newQty <= 0) return null;
                        if (newQty > item.product.stock) return item;
                        return { ...item, quantity: newQty };
                    }
                    return item;
                })
                .filter(Boolean) as CartItem[]
        );
    };

    const removeFromCart = (sku: string) => {
        setCart((prev) => prev.filter((item) => item.product.sku !== sku));
    };

    const totalBayar = cart.reduce((acc, item) => acc + item.product.harga * item.quantity, 0);

    // Refresh katalog dari backend REST API
    const reloadProducts = async () => {
        setLoading(true);
        try {
            const res = await fetch('/api/v1/produk');
            const data = await res.json();
            if (Array.isArray(data)) {
                setProducts(data);
            }
        } catch (e) {
            console.error('Gagal memuat produk', e);
        } finally {
            setLoading(false);
        }
    };

    // Eksekusi Transaksi POS ke REST API (POST /api/v1/transaksi)
    const handleCheckout = async () => {
        if (cart.length === 0 || loading) return;
        setLoading(true);

        const payload = {
            items: cart.map((item) => ({
                sku: item.product.sku,
                quantity: item.quantity,
            })),
        };

        try {
            const res = await fetch('/api/v1/transaksi', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json, application/problem+json',
                },
                body: JSON.stringify(payload),
            });

            const json = await res.json();

            if (res.status === 201) {
                setResult({
                    success: true,
                    data: json,
                });
                setCart([]);
                // Reload stok terbaru
                await reloadProducts();
            } else {
                setResult({
                    success: false,
                    error: json,
                });
            }
        } catch (err: any) {
            setResult({
                success: false,
                error: {
                    title: 'Network Error',
                    status: 500,
                    detail: err.message || 'Gagal terhubung ke backend server.',
                },
            });
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="min-h-screen bg-slate-50 text-slate-800 font-sans">
            <Head title="Kasir Digital & Inventori — Warung Pak Hendy" />

            {/* Top Navbar */}
            <header className="sticky top-0 z-30 bg-white border-b border-slate-200 shadow-sm">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="bg-emerald-600 text-white p-2 rounded-lg shadow-sm">
                            <Store className="w-5 h-5" />
                        </div>
                        <div>
                            <h1 className="font-bold text-lg text-slate-900 leading-tight">
                                Warung Kelontong Pak Hendy
                            </h1>
                            <p className="text-xs text-slate-500">
                                Sistem Terdistribusi Kasir Digital & Supply Chain (Kelompok 67)
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <button
                            onClick={() => setShowCurlModal(true)}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 transition"
                        >
                            <Terminal className="w-3.5 h-3.5" />
                            <span>cURL Demo</span>
                        </button>
                        <a
                            href="/laporan-integrasi-warung-pak-hendy.pdf"
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 transition"
                        >
                            <FileText className="w-3.5 h-3.5" />
                            <span>Dokumen PDF</span>
                            <ArrowUpRight className="w-3 h-3" />
                        </a>
                    </div>
                </div>
            </header>

            {/* Architecture Banner */}
            <div className="bg-slate-900 text-slate-200 px-4 py-2.5 text-xs">
                <div className="max-w-7xl mx-auto flex flex-wrap items-center justify-between gap-2">
                    <div className="flex items-center gap-4">
                        <span className="flex items-center gap-1.5 text-emerald-400 font-medium">
                            <span className="w-2 h-2 rounded-full bg-emerald-400 animate-pulse" />
                            REST API: Sinkron (201 / 409 / 422)
                        </span>
                        <span className="hidden sm:inline-block text-slate-500">•</span>
                        <span className="flex items-center gap-1.5 text-amber-300 font-medium">
                            <Layers className="w-3.5 h-3.5" />
                            RabbitMQ: Asinkron (stock.threshold.reached)
                        </span>
                    </div>
                    <div className="text-slate-400">
                        Pessimistic Locking &bull; RFC 9457 Problem Details &bull; Idempotent Consumer
                    </div>
                </div>
            </div>

            {/* Main Content */}
            <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    {/* Catalog Section (8 cols) */}
                    <div className="lg:col-span-8 flex flex-col gap-4">
                        {/* Search & Actions Bar */}
                        <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col sm:flex-row gap-3 items-center justify-between">
                            <div className="relative w-full sm:w-80">
                                <input
                                    type="text"
                                    placeholder="Cari SKU atau nama barang..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="w-full px-3.5 py-2 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                />
                            </div>
                            <div className="flex items-center gap-2 w-full sm:w-auto justify-end">
                                <button
                                    onClick={reloadProducts}
                                    disabled={loading}
                                    className="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition"
                                >
                                    <RefreshCw className={`w-3.5 h-3.5 ${loading ? 'animate-spin' : ''}`} />
                                    <span>Sync Stok</span>
                                </button>
                                <span className="text-xs text-slate-500 font-medium">
                                    {filteredProducts.length} Produk
                                </span>
                            </div>
                        </div>

                        {/* Product Cards Grid */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                            {filteredProducts.map((p) => {
                                const isLow = p.stock > 0 && p.stock <= p.minimum_stock;
                                const isOut = p.stock <= 0;
                                const inCart = cart.find((i) => i.product.sku === p.sku);

                                return (
                                    <div
                                        key={p.sku}
                                        className="bg-white rounded-xl border border-slate-200 p-4 shadow-sm hover:shadow-md transition flex flex-col justify-between"
                                    >
                                        <div>
                                            <div className="flex items-start justify-between gap-2 mb-2">
                                                <span className="font-mono text-xs font-semibold px-2 py-0.5 rounded bg-slate-100 text-slate-700">
                                                    {p.sku}
                                                </span>
                                                {isOut ? (
                                                    <span className="inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-0.5 rounded-full bg-rose-100 text-rose-700">
                                                        Habis
                                                    </span>
                                                ) : isLow ? (
                                                    <span className="inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">
                                                        Ambang Stok ({p.stock})
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">
                                                        Stok: {p.stock}
                                                    </span>
                                                )}
                                            </div>

                                            <h3 className="font-semibold text-slate-900 text-sm leading-snug mb-1">
                                                {p.name}
                                            </h3>
                                            <p className="text-base font-bold text-emerald-700 mb-3">
                                                Rp {p.harga.toLocaleString('id-ID')}
                                            </p>
                                        </div>

                                        <div className="pt-2 border-t border-slate-100 flex items-center justify-between">
                                            <span className="text-[11px] text-slate-400">
                                                Min. Reorder: {p.minimum_stock}
                                            </span>
                                            <button
                                                onClick={() => addToCart(p)}
                                                disabled={isOut}
                                                className={`inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg transition ${
                                                    isOut
                                                        ? 'bg-slate-100 text-slate-400 cursor-not-allowed'
                                                        : inCart
                                                        ? 'bg-emerald-600 text-white hover:bg-emerald-700'
                                                        : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200'
                                                }`}
                                            >
                                                <Plus className="w-3.5 h-3.5" />
                                                <span>{inCart ? `Tambah (${inCart.quantity})` : 'Pilih'}</span>
                                            </button>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    {/* Cart & Checkout Panel (4 cols) */}
                    <div className="lg:col-span-4">
                        <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-4 sticky top-24 flex flex-col h-[calc(100vh-8rem)]">
                            <div className="flex items-center justify-between pb-3 border-b border-slate-200 mb-3">
                                <div className="flex items-center gap-2">
                                    <ShoppingCart className="w-4 h-4 text-emerald-600" />
                                    <h2 className="font-bold text-sm text-slate-900">Keranjang Kasir</h2>
                                </div>
                                {cart.length > 0 && (
                                    <button
                                        onClick={() => setCart([])}
                                        className="text-xs text-rose-600 hover:text-rose-700 font-medium"
                                    >
                                        Kosongkan
                                    </button>
                                )}
                            </div>

                            {/* Cart Items List */}
                            <div className="flex-1 overflow-y-auto space-y-2.5 pr-1">
                                {cart.length === 0 ? (
                                    <div className="h-full flex flex-col items-center justify-center text-center p-6 text-slate-400">
                                        <Package className="w-10 h-10 mb-2 stroke-1" />
                                        <p className="text-sm font-medium">Keranjang masih kosong</p>
                                        <p className="text-xs text-slate-400 mt-1">
                                            Pilih barang dari katalog untuk memulai transaksi kasir.
                                        </p>
                                    </div>
                                ) : (
                                    cart.map((item) => (
                                        <div
                                            key={item.product.sku}
                                            className="p-3 bg-slate-50 border border-slate-200 rounded-lg flex flex-col gap-2"
                                        >
                                            <div className="flex items-start justify-between">
                                                <div>
                                                    <span className="font-mono text-[10px] text-slate-500 font-medium">
                                                        {item.product.sku}
                                                    </span>
                                                    <p className="text-xs font-medium text-slate-800 leading-tight">
                                                        {item.product.name}
                                                    </p>
                                                    <p className="text-xs text-emerald-700 font-semibold mt-0.5">
                                                        Rp {(item.product.harga * item.quantity).toLocaleString('id-ID')}
                                                    </p>
                                                </div>
                                                <button
                                                    onClick={() => removeFromCart(item.product.sku)}
                                                    className="text-slate-400 hover:text-rose-500 p-1"
                                                >
                                                    <Trash2 className="w-3.5 h-3.5" />
                                                </button>
                                            </div>

                                            <div className="flex items-center justify-between pt-1 border-t border-slate-200/60 text-xs">
                                                <span className="text-[11px] text-slate-500">
                                                    @ Rp {item.product.harga.toLocaleString('id-ID')}
                                                </span>
                                                <div className="flex items-center gap-1.5">
                                                    <button
                                                        onClick={() => updateQuantity(item.product.sku, -1)}
                                                        className="w-6 h-6 rounded bg-white border border-slate-300 flex items-center justify-center text-slate-700 hover:bg-slate-100"
                                                    >
                                                        <Minus className="w-3 h-3" />
                                                    </button>
                                                    <span className="w-6 text-center font-bold text-slate-800">
                                                        {item.quantity}
                                                    </span>
                                                    <button
                                                        onClick={() => updateQuantity(item.product.sku, 1)}
                                                        disabled={item.quantity >= item.product.stock}
                                                        className={`w-6 h-6 rounded border flex items-center justify-center ${
                                                            item.quantity >= item.product.stock
                                                                ? 'bg-slate-100 border-slate-200 text-slate-300 cursor-not-allowed'
                                                                : 'bg-white border-slate-300 text-slate-700 hover:bg-slate-100'
                                                        }`}
                                                    >
                                                        <Plus className="w-3 h-3" />
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    ))
                                )}
                            </div>

                            {/* Summary & Checkout Button */}
                            <div className="pt-3 border-t border-slate-200 mt-2">
                                <div className="flex items-center justify-between mb-3 text-sm">
                                    <span className="text-slate-600 font-medium">Total Tagihan</span>
                                    <span className="text-lg font-bold text-slate-900">
                                        Rp {totalBayar.toLocaleString('id-ID')}
                                    </span>
                                </div>
                                <button
                                    onClick={handleCheckout}
                                    disabled={cart.length === 0 || loading}
                                    className={`w-full py-2.5 px-4 rounded-lg font-medium text-sm flex items-center justify-center gap-2 transition shadow-sm ${
                                        cart.length === 0 || loading
                                            ? 'bg-slate-200 text-slate-400 cursor-not-allowed'
                                            : 'bg-emerald-600 hover:bg-emerald-700 text-white'
                                    }`}
                                >
                                    {loading ? (
                                        <>
                                            <RefreshCw className="w-4 h-4 animate-spin" />
                                            <span>Memproses Transaksi...</span>
                                        </>
                                    ) : (
                                        <>
                                            <Receipt className="w-4 h-4" />
                                            <span>Proses Pembayaran (REST)</span>
                                        </>
                                    )}
                                </button>
                                <p className="text-[10px] text-center text-slate-400 mt-2">
                                    Memanggil POST /api/v1/transaksi (Pessimistic Lock)
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>

            {/* Modal Hasil Transaksi (Sukses / Problem Details) */}
            {result && (
                <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
                    <div className="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200 animate-in fade-in zoom-in-95 duration-150">
                        {result.success ? (
                            <div>
                                <div className="w-12 h-12 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mb-3">
                                    <CheckCircle2 className="w-6 h-6" />
                                </div>
                                <span className="font-mono text-xs font-semibold px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    HTTP 201 Created
                                </span>
                                <h3 className="text-lg font-bold text-slate-900 mt-2">
                                    Transaksi Penjualan Berhasil
                                </h3>
                                <p className="text-xs text-slate-500 mt-1">
                                    Stok fisik telah dipotong dengan transaksi ACID. Jika stok berada di bawah ambang batas, outbox event otomatis diterbitkan ke RabbitMQ.
                                </p>

                                <div className="mt-4 p-3 bg-slate-50 border border-slate-200 rounded-lg text-xs space-y-1.5 font-mono">
                                    <div className="flex justify-between">
                                        <span className="text-slate-500">Transaction ID:</span>
                                        <span className="font-semibold text-slate-800">
                                            {result.data?.transaction?.transaction_id?.slice(0, 18)}...
                                        </span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-slate-500">Total Harga:</span>
                                        <span className="font-bold text-emerald-700">
                                            Rp {result.data?.transaction?.total_price?.toLocaleString('id-ID')}
                                        </span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-slate-500">Jumlah Item:</span>
                                        <span>{result.data?.transaction?.items?.length || 0} barang</span>
                                    </div>
                                </div>

                                <button
                                    onClick={() => setResult(null)}
                                    className="mt-5 w-full py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-semibold"
                                >
                                    Tutup Struk
                                </button>
                            </div>
                        ) : (
                            <div>
                                <div className="w-12 h-12 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center mb-3">
                                    <AlertTriangle className="w-6 h-6" />
                                </div>
                                <span className="font-mono text-xs font-semibold px-2 py-0.5 rounded bg-rose-50 text-rose-700 border border-rose-200">
                                    RFC 9457 Problem Details (Status {result.error?.status || 409})
                                </span>
                                <h3 className="text-lg font-bold text-slate-900 mt-2">
                                    {result.error?.title || 'Transaksi Ditolak'}
                                </h3>
                                <p className="text-xs text-slate-600 mt-1">
                                    {result.error?.detail || 'Terjadi konflik ketersediaan stok fisik.'}
                                </p>

                                <div className="mt-4 p-3 bg-rose-50/60 border border-rose-200 rounded-lg text-xs space-y-1 font-mono text-rose-900">
                                    <p><strong>Type:</strong> {result.error?.type || 'about:blank'}</p>
                                    <p><strong>Status:</strong> {result.error?.status || 409}</p>
                                    {result.error?.instance && (
                                        <p className="truncate"><strong>Instance:</strong> {result.error.instance}</p>
                                    )}
                                </div>

                                <button
                                    onClick={() => setResult(null)}
                                    className="mt-5 w-full py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-semibold"
                                >
                                    Kembali ke Kasir
                                </button>
                            </div>
                        )}
                    </div>
                </div>
            )}

            {/* Modal cURL Demo Commands */}
            {showCurlModal && (
                <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
                    <div className="bg-white rounded-2xl max-w-xl w-full p-6 shadow-2xl border border-slate-200 max-h-[85vh] overflow-y-auto">
                        <div className="flex items-center justify-between pb-3 border-b border-slate-200">
                            <div className="flex items-center gap-2">
                                <Terminal className="w-4 h-4 text-emerald-600" />
                                <h3 className="font-bold text-sm text-slate-900">
                                    cURL Snippet untuk Demo 4 Menit Dosen
                                </h3>
                            </div>
                            <button onClick={() => setShowCurlModal(false)} className="text-slate-400 hover:text-slate-600">
                                <X className="w-4 h-4" />
                            </button>
                        </div>

                        <div className="space-y-4 mt-4 text-xs font-mono">
                            <div>
                                <p className="font-sans font-semibold text-slate-700 mb-1">
                                    1. Demo Request Sukses (201 Created):
                                </p>
                                <pre className="p-3 bg-slate-900 text-emerald-400 rounded-lg overflow-x-auto whitespace-pre">
{`curl -X POST "http://localhost:8000/api/v1/transaksi" \\
  -H "Content-Type: application/json" \\
  -d '{"items": [{"sku": "MYK-001", "quantity": 1}]}'`}
                                </pre>
                            </div>

                            <div>
                                <p className="font-sans font-semibold text-slate-700 mb-1">
                                    2. Demo Request Gagal Stok Kurang (409 Conflict):
                                </p>
                                <pre className="p-3 bg-slate-900 text-amber-300 rounded-lg overflow-x-auto whitespace-pre">
{`curl -X POST "http://localhost:8000/api/v1/transaksi" \\
  -H "Content-Type: application/json" \\
  -d '{"items": [{"sku": "MYK-001", "quantity": 9999}]}'`}
                                </pre>
                            </div>

                            <div>
                                <p className="font-sans font-semibold text-slate-700 mb-1">
                                    3. Eksekusi Seluruh Feature Test:
                                </p>
                                <pre className="p-3 bg-slate-900 text-slate-200 rounded-lg overflow-x-auto whitespace-pre">
{`php artisan test`}
                                </pre>
                            </div>
                        </div>

                        <button
                            onClick={() => setShowCurlModal(false)}
                            className="mt-5 w-full py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-semibold"
                        >
                            Tutup
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
