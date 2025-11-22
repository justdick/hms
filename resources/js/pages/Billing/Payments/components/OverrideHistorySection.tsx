import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { AlertTriangle, Clock, DollarSign, ShieldAlert } from 'lucide-react';
import { useEffect, useState } from 'react';

interface OverrideHistoryItem {
    id: number;
    type: 'override' | 'waiver' | 'adjustment';
    service_type?: string;
    charge_description?: string;
    reason: string;
    authorized_by: {
        id: number;
        name: string;
    };
    authorized_at: string;
    expires_at?: string;
    is_active?: boolean;
    remaining_duration?: string;
    original_amount?: number;
    adjustment_amount?: number;
    final_amount?: number;
    adjustment_type?: string;
}

interface OverrideHistorySectionProps {
    history: OverrideHistoryItem[];
    formatCurrency: (amount: number) => string;
}

export function OverrideHistorySection({
    history,
    formatCurrency,
}: OverrideHistorySectionProps) {
    const [currentTime, setCurrentTime] = useState(new Date());

    // Update time every minute for countdown timers
    useEffect(() => {
        const interval = setInterval(() => {
            setCurrentTime(new Date());
        }, 60000); // Update every minute

        return () => clearInterval(interval);
    }, []);

    const formatServiceType = (serviceType: string) => {
        return serviceType
            .split('_')
            .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
            .join(' ');
    };

    const formatDateTime = (dateString: string) => {
        const date = new Date(dateString);
        return date.toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
        });
    };

    const calculateRemainingTime = (expiresAt: string): string => {
        const now = currentTime.getTime();
        const expiry = new Date(expiresAt).getTime();
        const diff = expiry - now;

        if (diff <= 0) {
            return 'Expired';
        }

        const hours = Math.floor(diff / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));

        if (hours > 0) {
            return `${hours}h ${minutes}m`;
        }
        return `${minutes}m`;
    };

    const getOverrideTypeIcon = (type: string) => {
        switch (type) {
            case 'override':
                return <ShieldAlert className="h-4 w-4" />;
            case 'waiver':
                return <DollarSign className="h-4 w-4" />;
            case 'adjustment':
                return <DollarSign className="h-4 w-4" />;
            default:
                return <AlertTriangle className="h-4 w-4" />;
        }
    };

    const getOverrideTypeColor = (type: string) => {
        switch (type) {
            case 'override':
                return 'bg-yellow-100 text-yellow-800 border-yellow-300 dark:bg-yellow-950/20 dark:text-yellow-300 dark:border-yellow-800';
            case 'waiver':
                return 'bg-gray-100 text-gray-800 border-gray-300 dark:bg-gray-900/20 dark:text-gray-300 dark:border-gray-700';
            case 'adjustment':
                return 'bg-blue-100 text-blue-800 border-blue-300 dark:bg-blue-950/20 dark:text-blue-300 dark:border-blue-800';
            default:
                return 'bg-gray-100 text-gray-800 border-gray-300 dark:bg-gray-900/20 dark:text-gray-300 dark:border-gray-700';
        }
    };

    const getAdjustmentTypeLabel = (adjustmentType?: string) => {
        switch (adjustmentType) {
            case 'discount_percentage':
                return 'Percentage Discount';
            case 'discount_fixed':
                return 'Fixed Discount';
            case 'waiver':
                return 'Full Waiver';
            default:
                return 'Adjustment';
        }
    };

    // Sort history by most recent first, with active overrides at the top
    const sortedHistory = [...history].sort((a, b) => {
        // Active overrides first
        if (a.is_active && !b.is_active) return -1;
        if (!a.is_active && b.is_active) return 1;

        // Then by date (most recent first)
        return (
            new Date(b.authorized_at).getTime() -
            new Date(a.authorized_at).getTime()
        );
    });

    if (history.length === 0) {
        return null;
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                    <AlertTriangle className="h-5 w-5" />
                    Override & Adjustment History
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div className="space-y-3">
                    {sortedHistory.map((item) => (
                        <div
                            key={item.id}
                            className={`rounded-lg border p-4 transition-colors ${
                                item.is_active
                                    ? 'border-yellow-300 bg-yellow-50 shadow-sm dark:border-yellow-800 dark:bg-yellow-950/20'
                                    : 'bg-muted/30'
                            }`}
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div className="flex-1 space-y-2">
                                    {/* Type and Status Badges */}
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge
                                            variant="outline"
                                            className={`text-xs ${getOverrideTypeColor(item.type)}`}
                                        >
                                            <span className="flex items-center gap-1">
                                                {getOverrideTypeIcon(item.type)}
                                                {item.type.toUpperCase()}
                                            </span>
                                        </Badge>
                                        {item.is_active && (
                                            <Badge
                                                variant="outline"
                                                className="animate-pulse bg-green-100 text-xs text-green-800 dark:bg-green-950/20 dark:text-green-300"
                                            >
                                                ACTIVE
                                            </Badge>
                                        )}
                                        {item.adjustment_type && (
                                            <Badge
                                                variant="outline"
                                                className="text-xs"
                                            >
                                                {getAdjustmentTypeLabel(
                                                    item.adjustment_type,
                                                )}
                                            </Badge>
                                        )}
                                    </div>

                                    {/* Title/Description */}
                                    <div>
                                        <p className="font-medium">
                                            {item.type === 'override' &&
                                                item.service_type &&
                                                `${formatServiceType(item.service_type)} Service Override`}
                                            {item.type === 'waiver' &&
                                                item.charge_description &&
                                                `Charge Waived: ${item.charge_description}`}
                                            {item.type === 'adjustment' &&
                                                item.charge_description &&
                                                `Charge Adjusted: ${item.charge_description}`}
                                        </p>
                                    </div>

                                    {/* Reason */}
                                    <div className="rounded-md bg-muted/50 p-2">
                                        <p className="text-xs text-muted-foreground">
                                            <span className="font-medium">
                                                Reason:
                                            </span>{' '}
                                            {item.reason}
                                        </p>
                                    </div>

                                    {/* Metadata */}
                                    <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                        <span>
                                            By {item.authorized_by.name}
                                        </span>
                                        <span>•</span>
                                        <span>
                                            {formatDateTime(item.authorized_at)}
                                        </span>
                                    </div>

                                    {/* Countdown Timer for Active Overrides */}
                                    {item.expires_at && item.is_active && (
                                        <div className="flex items-center gap-2 rounded-md border border-yellow-300 bg-yellow-100 p-2 dark:border-yellow-800 dark:bg-yellow-950/30">
                                            <Clock className="h-4 w-4 text-yellow-700 dark:text-yellow-400" />
                                            <div className="flex-1">
                                                <p className="text-xs font-medium text-yellow-900 dark:text-yellow-100">
                                                    Expires in:{' '}
                                                    <span className="font-bold">
                                                        {calculateRemainingTime(
                                                            item.expires_at,
                                                        )}
                                                    </span>
                                                </p>
                                                <p className="text-[10px] text-yellow-700 dark:text-yellow-400">
                                                    {formatDateTime(
                                                        item.expires_at,
                                                    )}
                                                </p>
                                            </div>
                                        </div>
                                    )}

                                    {/* Expired Override Notice */}
                                    {item.expires_at &&
                                        !item.is_active &&
                                        item.type === 'override' && (
                                            <p className="text-xs text-muted-foreground">
                                                Expired on:{' '}
                                                {formatDateTime(item.expires_at)}
                                            </p>
                                        )}
                                </div>

                                {/* Amount Display for Waivers/Adjustments */}
                                {(item.type === 'waiver' ||
                                    item.type === 'adjustment') && (
                                    <div className="text-right">
                                        {item.original_amount !== undefined && (
                                            <p className="text-xs text-muted-foreground line-through">
                                                {formatCurrency(
                                                    item.original_amount,
                                                )}
                                            </p>
                                        )}
                                        {item.final_amount !== undefined && (
                                            <p className="text-sm font-bold text-green-600">
                                                {formatCurrency(
                                                    item.final_amount,
                                                )}
                                            </p>
                                        )}
                                        {item.adjustment_amount !== undefined &&
                                            item.adjustment_amount > 0 && (
                                                <p className="text-xs text-red-600">
                                                    -
                                                    {formatCurrency(
                                                        item.adjustment_amount,
                                                    )}
                                                </p>
                                            )}
                                    </div>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}
