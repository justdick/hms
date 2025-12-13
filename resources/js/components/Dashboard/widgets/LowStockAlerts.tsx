import { Link } from '@inertiajs/react';
import { AlertTriangle, ArrowRight, Package } from 'lucide-react';

import { cn } from '@/lib/utils';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

export interface LowStockItem {
    id: number;
    name: string;
    drug_code: string | null;
    current_stock: number;
    minimum_level: number;
}

export interface LowStockAlertsProps {
    items: LowStockItem[];
    viewAllHref?: string;
    className?: string;
}

function getStockStatus(currentStock: number, minimumLevel: number) {
    if (currentStock === 0) {
        return { label: 'Out', variant: 'destructive' as const, color: 'text-red-600' };
    }
    const percentage = minimumLevel > 0 ? (currentStock / minimumLevel) * 100 : 0;
    if (percentage <= 25) {
        return { label: 'Critical', variant: 'destructive' as const, color: 'text-red-600' };
    }
    return { label: 'Low', variant: 'outline' as const, color: 'text-amber-600' };
}

export function LowStockAlerts({
    items,
    viewAllHref,
    className,
}: LowStockAlertsProps) {
    // Show only top 5 most critical
    const critical = items.slice(0, 5);

    return (
        <Card className={cn('', className)}>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
                <div className="flex items-center gap-2">
                    <AlertTriangle className="h-5 w-5 text-amber-500" />
                    <div>
                        <CardTitle className="text-base font-semibold">
                            Low Stock Alerts
                        </CardTitle>
                        <CardDescription>
                            {items.length} items below minimum
                        </CardDescription>
                    </div>
                </div>
                {viewAllHref && (
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={viewAllHref}>
                            View All
                            <ArrowRight className="ml-1 h-4 w-4" />
                        </Link>
                    </Button>
                )}
            </CardHeader>
            <CardContent>
                {critical.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-6 text-muted-foreground">
                        <Package className="h-8 w-8 mb-2 opacity-50" />
                        <span>All items well stocked</span>
                    </div>
                ) : (
                    <div className="space-y-2">
                        {critical.map((item) => {
                            const status = getStockStatus(item.current_stock, item.minimum_level);
                            return (
                                <div
                                    key={item.id}
                                    className={cn(
                                        'flex items-center justify-between rounded-lg border p-3',
                                        item.current_stock === 0 && 'border-red-300 bg-red-50 dark:border-red-800 dark:bg-red-950'
                                    )}
                                >
                                    <div className="flex flex-col min-w-0">
                                        <span className="font-medium text-sm truncate">
                                            {item.name}
                                        </span>
                                        {item.drug_code && (
                                            <span className="text-xs text-muted-foreground">
                                                {item.drug_code}
                                            </span>
                                        )}
                                    </div>
                                    <div className="flex items-center gap-2 shrink-0">
                                        <span className={cn('font-mono text-sm font-medium', status.color)}>
                                            {item.current_stock}
                                        </span>
                                        <Badge variant={status.variant} className="text-xs">
                                            {status.label}
                                        </Badge>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
