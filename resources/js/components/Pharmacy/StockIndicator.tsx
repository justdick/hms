import { cn } from '@/lib/utils';
import { AlertCircle, CheckCircle, XCircle } from 'lucide-react';

interface StockIndicatorProps {
    available: boolean;
    inStock: number;
    requested: number;
    className?: string;
}

export function StockIndicator({
    available,
    inStock,
    requested,
    className,
}: StockIndicatorProps) {
    const getStatus = () => {
        if (available) {
            return {
                icon: CheckCircle,
                text: 'In Stock',
                variant: 'success' as const,
                color: 'text-green-600 dark:text-green-400',
            };
        }

        if (inStock > 0) {
            return {
                icon: AlertCircle,
                text: 'Partial Stock',
                variant: 'warning' as const,
                color: 'text-yellow-600 dark:text-yellow-400',
            };
        }

        return {
            icon: XCircle,
            text: 'Out of Stock',
            variant: 'danger' as const,
            color: 'text-red-600 dark:text-red-400',
        };
    };

    const status = getStatus();
    const Icon = status.icon;

    return (
        <div className={cn('flex items-center gap-2', className)}>
            <Icon className={cn('h-4 w-4', status.color)} />
            <div className="flex flex-col">
                <span className={cn('text-sm font-medium', status.color)}>
                    {status.text}
                </span>
                <span className="text-xs text-muted-foreground">
                    {inStock} / {requested} available
                </span>
            </div>
        </div>
    );
}
