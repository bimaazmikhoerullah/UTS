import { cn } from '@/lib/utils';

interface BadgeProps {
    variant?: 'success' | 'warning' | 'danger' | 'default';
    children: React.ReactNode;
    className?: string;
}

export function Badge({ variant = 'default', children, className }: BadgeProps) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold',
                {
                    'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400':
                        variant === 'success',
                    'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400':
                        variant === 'warning',
                    'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400':
                        variant === 'danger',
                    'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300':
                        variant === 'default',
                },
                className,
            )}
        >
            {children}
        </span>
    );
}

export function stockBadge(stock: number) {
    if (stock === 0) return { variant: 'danger' as const, label: 'Habis' };
    if (stock <= 10) return { variant: 'warning' as const, label: 'Hampir Habis' };
    return { variant: 'success' as const, label: 'Tersedia' };
}
