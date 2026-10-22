import { Head, Link } from '@inertiajs/react';
import Layout from '@/components/Layout';
import { Badge, stockBadge } from '@/components/Badge';
import type { Produk } from '@/types/produk';

interface DashboardProps {
    totalProduk: number;
    stokHabis: number;
    hampirHabis: number;
    produkTerbaru: Produk[];
}

export default function Welcome({
    totalProduk = 0,
    stokHabis = 0,
    hampirHabis = 0,
    produkTerbaru = [],
}: DashboardProps) {
    return (
        <Layout>
            <Head title="Dashboard" />

            <div className="mb-8 rounded-2xl bg-gradient-to-br from-blue-600 to-blue-700 p-8 text-white shadow-lg">
                <h1 className="mb-1 text-3xl font-bold">🏪 Toko Berkah Jaya</h1>
                <p className="text-blue-100">Sistem manajemen katalog dan stok produk</p>
            </div>

            <div className="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard icon="📦" label="Total Produk" value={totalProduk} color="blue" />
                <StatCard icon="⚠️" label="Hampir Habis" value={hampirHabis} color="yellow" />
                <StatCard icon="🚫" label="Stok Habis" value={stokHabis} color="red" />
            </div>

            <div className="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div className="mb-4 flex items-center justify-between">
                    <h2 className="text-lg font-semibold text-gray-900 dark:text-white">Produk Terbaru</h2>
                    <Link href="/produk" className="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">
                        Lihat semua →
                    </Link>
                </div>

                {produkTerbaru.length === 0 ? (
                    <EmptyState
                        icon="📭"
                        message="Belum ada produk. Tambahkan produk pertama Anda!"
                        action={{ label: 'Tambah Produk', href: '/produk' }}
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-gray-100 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                    <th className="pb-3 pr-4">SKU</th>
                                    <th className="pb-3 pr-4">Nama</th>
                                    <th className="pb-3 pr-4 text-right">Harga</th>
                                    <th className="pb-3 pr-4 text-right">Stok</th>
                                    <th className="pb-3">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50 dark:divide-gray-800">
                                {produkTerbaru.map((p) => {
                                    const { variant, label } = stockBadge(p.stock);
                                    return (
                                        <tr key={p.sku} className="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                            <td className="py-3 pr-4 font-mono text-xs text-gray-500 dark:text-gray-400">{p.sku}</td>
                                            <td className="py-3 pr-4 font-medium text-gray-900 dark:text-white">
                                                <Link href={`/produk/${p.sku}`} className="hover:text-blue-600 hover:underline dark:hover:text-blue-400">
                                                    {p.name}
                                                </Link>
                                            </td>
                                            <td className="py-3 pr-4 text-right text-gray-700 dark:text-gray-300">{formatRupiah(p.harga)}</td>
                                            <td className="py-3 pr-4 text-right font-semibold text-gray-900 dark:text-white">{p.stock}</td>
                                            <td className="py-3"><Badge variant={variant}>{label}</Badge></td>
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

function StatCard({ icon, label, value, color }: {
    icon: string; label: string; value: number; color: 'blue' | 'yellow' | 'red';
}) {
    const bg = {
        blue: 'bg-blue-50 border-blue-100 dark:bg-blue-900/20 dark:border-blue-900/40',
        yellow: 'bg-yellow-50 border-yellow-100 dark:bg-yellow-900/20 dark:border-yellow-900/40',
        red: 'bg-red-50 border-red-100 dark:bg-red-900/20 dark:border-red-900/40',
    };
    const vc = {
        blue: 'text-blue-700 dark:text-blue-300',
        yellow: 'text-yellow-700 dark:text-yellow-300',
        red: 'text-red-700 dark:text-red-300',
    };
    return (
        <div className={`rounded-2xl border p-6 shadow-sm ${bg[color]}`}>
            <div className="mb-2 text-2xl">{icon}</div>
            <div className={`text-3xl font-bold ${vc[color]}`}>{value}</div>
            <div className="mt-1 text-sm text-gray-500 dark:text-gray-400">{label}</div>
        </div>
    );
}

function EmptyState({ icon, message, action }: {
    icon: string; message: string; action?: { label: string; href: string };
}) {
    return (
        <div className="flex flex-col items-center justify-center py-12 text-center">
            <span className="mb-3 text-4xl">{icon}</span>
            <p className="mb-4 text-gray-500 dark:text-gray-400">{message}</p>
            {action && (
                <Link href={action.href} className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    {action.label}
                </Link>
            )}
        </div>
    );
}

function formatRupiah(amount: number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(amount);
}
