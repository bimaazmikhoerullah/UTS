import { Head, Link, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import Layout from '@/components/Layout';
import { Badge, stockBadge } from '@/components/Badge';
import { cn } from '@/lib/utils';
import type { Produk } from '@/types/produk';

interface ProdukShowProps {
    produk: Produk;
    riwayat?: Array<{ id: number; adjustment: number; created_at: string }>;
    errors?: Record<string, string>;
}

export default function ProdukShow({ produk, riwayat = [], errors = {} }: ProdukShowProps) {
    const [mode, setMode] = useState<'tambah' | 'kurang'>('tambah');
    const { variant, label } = stockBadge(produk.stock);

    const { data, setData, post, processing, reset } = useForm({
        adjustment: 0,
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        const adj = mode === 'kurang' ? -Math.abs(data.adjustment) : Math.abs(data.adjustment);
        router.post(
            `/api/v1/produk/${produk.sku}/penyesuaian-stok`,
            { adjustment: adj },
            {
                onSuccess: () => reset(),
            },
        );
    }

    return (
        <Layout>
            <Head title={`${produk.name} — Detail Produk`} />

            {/* Back */}
            <Link
                href="/produk"
                className="mb-6 inline-flex items-center gap-1 text-sm text-gray-500 hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400"
            >
                ← Kembali ke Daftar Produk
            </Link>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                {/* Info Produk */}
                <div className="lg:col-span-2 space-y-6">
                    <div className="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div className="mb-4 flex items-start justify-between">
                            <div>
                                <p className="mb-1 font-mono text-xs text-gray-400">{produk.sku}</p>
                                <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{produk.name}</h1>
                            </div>
                            <Badge variant={variant}>{label}</Badge>
                        </div>

                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                            <InfoItem label="Harga Satuan" value={formatRupiah(produk.harga)} />
                            <InfoItem
                                label="Stok Saat Ini"
                                value={
                                    <span className={cn(
                                        'text-2xl font-bold',
                                        produk.stock === 0 ? 'text-red-600 dark:text-red-400' :
                                        produk.stock <= 10 ? 'text-yellow-600 dark:text-yellow-400' :
                                        'text-green-600 dark:text-green-400',
                                    )}>
                                        {produk.stock}
                                    </span>
                                }
                            />
                            <InfoItem
                                label="Nilai Stok"
                                value={formatRupiah(produk.harga * produk.stock)}
                            />
                        </div>

                        <div className="mt-4 border-t border-gray-100 pt-4 dark:border-gray-800">
                            <div className="grid grid-cols-2 gap-4 text-xs text-gray-400 dark:text-gray-500">
                                <span>Dibuat: {formatDate(produk.created_at)}</span>
                                <span>Diperbarui: {formatDate(produk.updated_at)}</span>
                            </div>
                        </div>
                    </div>

                    {/* Riwayat */}
                    {riwayat.length > 0 && (
                        <div className="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                            <h2 className="mb-4 text-base font-semibold text-gray-900 dark:text-white">Riwayat Penyesuaian Stok</h2>
                            <div className="space-y-2">
                                {riwayat.map((r) => (
                                    <div key={r.id} className="flex items-center justify-between rounded-lg bg-gray-50 px-4 py-2.5 dark:bg-gray-800">
                                        <span className="text-xs text-gray-500 dark:text-gray-400">{formatDate(r.created_at)}</span>
                                        <span className={cn('text-sm font-semibold', r.adjustment > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400')}>
                                            {r.adjustment > 0 ? `+${r.adjustment}` : r.adjustment}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>

                {/* Form Penyesuaian Stok */}
                <div className="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900 h-fit">
                    <h2 className="mb-4 text-base font-semibold text-gray-900 dark:text-white">⚖️ Penyesuaian Stok</h2>

                    {/* Toggle Tambah/Kurang */}
                    <div className="mb-4 flex rounded-lg border border-gray-200 p-1 dark:border-gray-700">
                        <button
                            type="button"
                            onClick={() => setMode('tambah')}
                            className={cn(
                                'flex-1 rounded-md py-2 text-sm font-semibold transition-colors',
                                mode === 'tambah'
                                    ? 'bg-green-500 text-white shadow'
                                    : 'text-gray-500 hover:text-gray-700 dark:text-gray-400',
                            )}
                        >
                            + Tambah
                        </button>
                        <button
                            type="button"
                            onClick={() => setMode('kurang')}
                            className={cn(
                                'flex-1 rounded-md py-2 text-sm font-semibold transition-colors',
                                mode === 'kurang'
                                    ? 'bg-red-500 text-white shadow'
                                    : 'text-gray-500 hover:text-gray-700 dark:text-gray-400',
                            )}
                        >
                            − Kurangi
                        </button>
                    </div>

                    <form onSubmit={handleSubmit}>
                        <div className="mb-4">
                            <label className="mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-400">
                                Jumlah {mode === 'tambah' ? 'Penambahan' : 'Pengurangan'}
                            </label>
                            <input
                                type="number"
                                min={1}
                                value={data.adjustment}
                                onChange={(e) => setData('adjustment', Number(e.target.value))}
                                placeholder="0"
                                className={cn(
                                    'w-full rounded-lg border px-3 py-2 text-center text-lg font-bold focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:text-white',
                                    errors.adjustment ? 'border-red-400' : 'border-gray-300 dark:border-gray-700',
                                )}
                                required
                            />
                            {errors.adjustment && <p className="mt-1 text-xs text-red-500">{errors.adjustment}</p>}
                        </div>

                        {/* Preview */}
                        <div className="mb-4 rounded-lg bg-gray-50 p-3 text-center dark:bg-gray-800">
                            <p className="text-xs text-gray-400 dark:text-gray-500">Stok setelah penyesuaian</p>
                            <p className={cn(
                                'text-xl font-bold',
                                mode === 'tambah' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400',
                            )}>
                                {mode === 'tambah'
                                    ? produk.stock + Math.abs(data.adjustment)
                                    : Math.max(0, produk.stock - Math.abs(data.adjustment))}
                            </p>
                        </div>

                        <button
                            type="submit"
                            disabled={processing || data.adjustment <= 0}
                            className={cn(
                                'w-full rounded-lg py-2.5 text-sm font-semibold text-white transition-colors disabled:opacity-50',
                                mode === 'tambah' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700',
                            )}
                        >
                            {processing ? 'Menyimpan...' : mode === 'tambah' ? '✓ Tambah Stok' : '✓ Kurangi Stok'}
                        </button>
                    </form>
                </div>
            </div>
        </Layout>
    );
}

function InfoItem({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
            <p className="mb-1 text-xs text-gray-400 dark:text-gray-500">{label}</p>
            <div className="font-semibold text-gray-900 dark:text-white">{value}</div>
        </div>
    );
}

function formatRupiah(amount: number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount);
}

function formatDate(dateStr: string) {
    return new Intl.DateTimeFormat('id-ID', {
        day: 'numeric', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    }).format(new Date(dateStr));
}
