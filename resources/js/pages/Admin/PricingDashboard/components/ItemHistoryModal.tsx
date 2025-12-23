import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Skeleton } from '@/components/ui/skeleton';
import axios from 'axios';
import { ArrowRight, Clock, History, User } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { PricingItem } from '../Index';

interface ItemHistoryModalProps {
    open: boolean;
    onClose: () => void;
    item: PricingItem;
}

interface HistoryEntry {
    id: number;
    field_changed: string;
    old_value: number | null;
    new_value: number;
    user: {
        id: number;
        name: string;
    } | null;
    created_at: string;
}

const fieldLabels: Record<string, string> = {
    cash_price: 'Cash Price',
    copay: 'Patient Copay',
    tariff: 'Insurance Tariff',
    coverage: 'Coverage %',
};

export function ItemHistoryModal({
    open,
    onClose,
    item,
}: ItemHistoryModalProps) {
    const [history, setHistory] = useState<HistoryEntry[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (open && item) {
            loadHistory();
        }
    }, [open, item]);

    const loadHistory = async () => {
        setLoading(true);
        setError(null);

        try {
            const response = await axios.get(
                '/admin/pricing-dashboard/item-history',
                {
                    params: {
                        item_type: item.type,
                        item_id: item.id,
                    },
                },
            );
            setHistory(response.data.history);
        } catch (err) {
            setError('Failed to load history');
            console.error('Failed to load history:', err);
        } finally {
            setLoading(false);
        }
    };

    const formatCurrency = (value: number | null): string => {
        if (value === null) return 'Not set';
        return `GH₵ ${value.toFixed(2)}`;
    };

    const formatDate = (dateString: string): string => {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-GB', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const getFieldBadgeColor = (field: string): string => {
        switch (field) {
            case 'cash_price':
                return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
            case 'copay':
                return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
            case 'tariff':
                return 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200';
            case 'coverage':
                return 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200';
            default:
                return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
        }
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <History className="h-5 w-5" />
                        Price History
                    </DialogTitle>
                    <DialogDescription>
                        <span className="font-medium">{item.name}</span>
                        {item.code && (
                            <span className="ml-2 font-mono text-xs">
                                ({item.code})
                            </span>
                        )}
                    </DialogDescription>
                </DialogHeader>

                <div className="py-4">
                    {loading ? (
                        <div className="space-y-4">
                            {[1, 2, 3].map((i) => (
                                <div key={i} className="flex items-start gap-3">
                                    <Skeleton className="h-10 w-10 rounded-full" />
                                    <div className="flex-1 space-y-2">
                                        <Skeleton className="h-4 w-3/4" />
                                        <Skeleton className="h-3 w-1/2" />
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : error ? (
                        <div className="py-8 text-center text-red-500">
                            {error}
                            <Button
                                variant="link"
                                onClick={loadHistory}
                                className="ml-2"
                            >
                                Retry
                            </Button>
                        </div>
                    ) : history.length === 0 ? (
                        <div className="py-8 text-center text-gray-500">
                            <History className="mx-auto mb-2 h-12 w-12 text-gray-300" />
                            <p>No price changes recorded for this item.</p>
                        </div>
                    ) : (
                        <ScrollArea className="h-80">
                            <div className="space-y-4 pr-4">
                                {history.map((entry, index) => (
                                    <div
                                        key={entry.id}
                                        className="relative flex gap-4 pb-4"
                                    >
                                        {/* Timeline line */}
                                        {index < history.length - 1 && (
                                            <div className="absolute top-10 left-5 h-full w-px bg-gray-200 dark:bg-gray-700" />
                                        )}

                                        {/* Timeline dot */}
                                        <div className="relative z-10 flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                                            <Clock className="h-4 w-4 text-gray-500" />
                                        </div>

                                        {/* Content */}
                                        <div className="flex-1 space-y-2">
                                            <div className="flex items-center gap-2">
                                                <Badge
                                                    className={getFieldBadgeColor(
                                                        entry.field_changed,
                                                    )}
                                                >
                                                    {fieldLabels[
                                                        entry.field_changed
                                                    ] || entry.field_changed}
                                                </Badge>
                                            </div>

                                            <div className="flex items-center gap-2 text-sm">
                                                <span className="text-gray-500 line-through">
                                                    {entry.field_changed ===
                                                    'coverage'
                                                        ? `${entry.old_value ?? 0}%`
                                                        : formatCurrency(
                                                              entry.old_value,
                                                          )}
                                                </span>
                                                <ArrowRight className="h-3 w-3 text-gray-400" />
                                                <span className="font-medium text-gray-900 dark:text-gray-100">
                                                    {entry.field_changed ===
                                                    'coverage'
                                                        ? `${entry.new_value}%`
                                                        : formatCurrency(
                                                              entry.new_value,
                                                          )}
                                                </span>
                                            </div>

                                            <div className="flex items-center gap-4 text-xs text-gray-500">
                                                <span className="flex items-center gap-1">
                                                    <User className="h-3 w-3" />
                                                    {entry.user?.name ||
                                                        'System'}
                                                </span>
                                                <span>
                                                    {formatDate(
                                                        entry.created_at,
                                                    )}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </ScrollArea>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
