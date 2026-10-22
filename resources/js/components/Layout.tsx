import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';

interface LayoutProps {
    children: React.ReactNode;
    className?: string;
}

export default function Layout({ children, className }: LayoutProps) {
    return (
        <div className="min-h-screen bg-gray-50 dark:bg-gray-950">
            {/* Navbar */}
            <nav className="sticky top-0 z-50 border-b border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div className="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
                    <Link href="/" className="flex items-center gap-2">
                        <span className="text-xl font-bold text-blue-600 dark:text-blue-400">
                            🏪 Berkah Jaya
                        </span>
                        <span className="hidden text-sm text-gray-500 sm:block dark:text-gray-400">
                            Katalog & Stok
                        </span>
                    </Link>
                    <div className="flex items-center gap-1">
                        <NavLink href="/">Dashboard</NavLink>
                        <NavLink href="/produk">Produk</NavLink>
                    </div>
                </div>
            </nav>

            {/* Content */}
            <main className={cn('mx-auto max-w-6xl px-4 py-8', className)}>
                {children}
            </main>

            {/* Footer */}
            <footer className="mt-12 border-t border-gray-200 py-6 text-center text-sm text-gray-400 dark:border-gray-800 dark:text-gray-600">
                © 2026 Toko Berkah Jaya — Sistem Katalog & Stok
            </footer>
        </div>
    );
}

function NavLink({
    href,
    children,
}: {
    href: string;
    children: React.ReactNode;
}) {
    const isActive =
        typeof window !== 'undefined' && window.location.pathname === href;
    return (
        <Link
            href={href}
            className={cn(
                'rounded-md px-3 py-2 text-sm font-medium transition-colors',
                isActive
                    ? 'bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400'
                    : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-100',
            )}
        >
            {children}
        </Link>
    );
}
