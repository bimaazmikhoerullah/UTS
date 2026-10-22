import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import Layout from '@/components/Layout';
import { Badge, stockBadge } from '@/components/Badge';
import { cn } from '@/lib/utils';
import type { Produk, ProdukInput } from '@/types/produk';

interface ProdukIndexProps {
    produk: Produk[];
    errors?: Record<string, string>;
}

export default function ProdukIndex({ produk = [], errors = {} }: ProdukIndexProps) {
    const [showForm, setShowForm] = useState(false);
    const [search, setSearch] = useState('');

    const { data, setData, post, processing, reset } = useForm<ProdukInput>({
        sku: '',
        name: '',
        harga: 0,
        stock: 0,
    });

    const filtered = produk.filter(
        (p) =>
            p.name.toLowerCase().includes(search.toLowerCase()) ||
            p.sku.toLowerCase().includes(search.toLowerCase()),
    );

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        post('/api/v1/produk', {
            onSuccess: () => {
                reset();
                setShowForm(false);
            },
        });
    }

    return (
        <Layout>
            <Head title="Daftar Produk" />

            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Daftar Produk</h1>
                    <p className="text-sm text-gray-500 dark:text-gray-400">{produk.length} produk terdaftar</p>
                </div>
                <button
                    onClick={() => setShowForm(!showForm)}
                    className="flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-blue-700"
                >
                    {showForm ? '✕ Batal' : '+ Tambah Produk'}
                </button>
            </div>

            {showForm && (
                <div className="mb-6 rounded-2xl border border-blue-100 bg-blue-50 p-6 shadow-sm dark:border-blue-900/40 dark:bg-blue-950/30">
                    <h2 className="mb-4 text-base font-semibold text-blue-900 dark:text-blue-200">Tambah Produk Baru</h2>
                    <form onSubmit={handleSubmit} className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label className="mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-400">SKU *</label>
                            <input
                                type="text"
                                value={data.sku}
                                onChange={(e) => setData('sku', e.target.value)}
                                placeholder="PRD-001"
                                className={cn('w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:text-white', errors.sku ? 'border-red-400' : 'border-gray-300 dark:border-gray-700')}
                                required
                            />
                            {errors.sku && <p className="mt-1 text-xs text-red-500">{errors.sku}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-400">Nama Produk *</label>
                            <input
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="Beras Premium 5kg"
                                className={cn('w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:text-white', errors.name ? 'border-red-400' : 'border-gray-300 dark:border-gray-700')}
                                required
                            />
                            {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-400">Harga (Rp) *</label>
                            <input
                                type="number"
                                min={0}
                                value={data.harga}
                                onChange={(e) => setData('harga', Number(e.target.value))}
                                placeholder="0"
                                className={cn('w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:text-white', errors.harga ? 'border-red-400' : 'border-gray-300 dark:border-gray-700')}
                                required
                            />
                            {errors.harga && <p className="mt-1 text-xs text-red-500">{errors.harga}</p>}
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-400">Stok Awal *</label>
                            <input
                                type="number"
                                min={0}
                                value={data.stock}
                                onChange={(e) => setData('stock', Number(e.target.value))}
                                placeholder="0"
                                className={cn('w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:text-white', errors.stock ? 'border-red-400' : 'border-gray-300 dark:border-gray-700')}
                                required
                            />
                            {errors.stock && <p className="mt-1 text-xs text-red-500">{errors.stock}</p>}
                        </div>
                        <div className="sm:col-span-2 lg:col-span-4">
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60"
                            >
                                {processing ? 'Menyimpan...' : 'Simpan Produk'}
                            </button>
                        </div>
                    </form>
                </div>
            )}

            <div className="mb-4">
                <input
                    type="text"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="🔍 Cari produk berdasarkan nama atau SKU..."
                    className="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                />
            </div>

            <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                {filtered.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-16 text-center">
                        <span className="mb-3 text-4xl">📭</span>
                        <p className="text-gray-500 dark:text-gray-400">
                            {search ? `Tidak ada produk untuk "${search}"` : 'Belum ada produk.'}
                        </p>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="border-b border-gray-100 dark:border-gray-800">
                                <tr className="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    <th className="px-6 py-4">SKU</th>
                                    <th className="px-6 py-4">Nama Produk</th>
                                    <th className="px-6 py-4 text-right">Harga</th>
                                    <th className="px-6 py-4 text-right">Stok</th>
                                    <th className="px-6 py-4">Status</th>
                                    <th className="px-6 py-4 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50 dark:divide-gray-800">
                                {filtered.map((p) => {
                                    const { variant, label } = stockBadge(p.stock);
                                    return (
                                        <tr key={p.sku} className="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                            <td className="px-6 py-4 font-mono text-xs text-gray-500 dark:text-gray-400">{p.sku}</td>
                                            <td className="px-6 py-4 font-medium text-gray-900 dark:text-white">{p.name}</td>
                                            <td className="px-6 py-4 text-right text-gray-700 dark:text-gray-300">{formatRupiah(p.harga)}</td>
                                            <td className="px-6 py-4 text-right">
                                                <span className={cn('font-bold', p.stock === 0 ? 'text-red-600 dark:text-red-400' : p.stock <= 10 ? 'text-yellow-600 dark:text-yellow-400' : 'text-green-600 dark:text-green-400')}>
                                                    {p.stock}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4"><Badge variant={variant}>{label}</Badge></td>
                                            <td className="px-6 py-4 text-right">
                                                <Link
                                                    href={`/produk/${p.sku}`}
                                                    className="rounded-md bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-blue-100 hover:text-blue-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-blue-900/30 dark:hover:text-blue-400"
                                                >
                                                    Detail →
                                                </Link>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </Layout>
    );
}

function formatRupiah(amount: number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount);
}
