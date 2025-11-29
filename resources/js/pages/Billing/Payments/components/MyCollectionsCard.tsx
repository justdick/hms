import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Banknote,
    CreditCard,
    Eye,
    RefreshCw,
    Smartphone,
    Wallet,
} from 'lucide-react';
import { useEffect, useState } from 'react';

interface PaymentMethodBreakdown {
    name: string;
    code: string;
    total_amount: number;
    transaction_count: number;
}

interface CollectionSummary {
    cashier: {
        id: number;
        name: string;
    };
    date: string;
    total_amount: number;
    transaction_count: number;
    breakdown: Record<string, PaymentMethodBreakdown>;
}

interface MyCollectionsCardProps {
    formatCurrency: (amount: number) => string;
    onViewDetails?: () => void;
}

const paymentMethodIcons: Record<string, React.ReactNode> = {
    cash: <Banknote className="h-4 w-4" />,
    card: <CreditCard className="h-4 w-4" />,
    mobile_money: <Smartphone className="h-4 w-4" />,
    bank_transfer: <Wallet className="h-4 w-4" />,
};

export function MyCollectionsCard({
    formatCurrency,
    onViewDetails,
}: MyCollectionsCardProps) {
    const [summary, setSummary] = useState<CollectionSummary | null>(null);
    const [isLoading, setIsLoading] = useState(true);
    const [isRefreshing, setIsRefreshing] = useState(false);

    const fetchCollections = async () => {
        try {
            const response = await fetch('/billing/my-collections');
            const data = await response.json();
            setSummary(data.summary);
        } catch (error) {
            console.error('Failed to fetch collections:', error);
        } finally {
            setIsLoading(false);
            setIsRefreshing(false);
        }
    };

    useEffect(() => {
        fetchCollections();
        // Refresh every 30 seconds
        const interval = setInterval(fetchCollections, 30000);
        return () => clearInterval(interval);
    }, []);

    const handleRefresh = () => {
        setIsRefreshing(true);
        fetchCollections();
    };

    if (isLoading) {
        return (
            <Card className="border-primary/20 bg-gradient-to-br from-primary/5 to-primary/10">
                <CardHeader className="pb-2">
                    <Skeleton className="h-5 w-32" />
                </CardHeader>
                <CardContent className="space-y-4">
                    <Skeleton className="h-10 w-40" />
                    <div className="grid grid-cols-2 gap-2">
                        <Skeleton className="h-8 w-full" />
                        <Skeleton className="h-8 w-full" />
                    </div>
                </CardContent>
            </Card>
        );
    }

    if (!summary) {
        return null;
    }

    const breakdownEntries = Object.entries(summary.breakdown).filter(
        ([_, data]) => data.transaction_count > 0,
    );

    return (
        <Card className="border-primary/20 bg-gradient-to-br from-primary/5 to-primary/10">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">
                    My Collections Today
                </CardTitle>
                <div className="flex items-center gap-2">
                    <Button
                        variant="ghost"
                        size="icon"
                        className="h-8 w-8"
                        onClick={handleRefresh}
                        disabled={isRefreshing}
                    >
                        <RefreshCw
                            className={`h-4 w-4 ${isRefreshing ? 'animate-spin' : ''}`}
                        />
                    </Button>
                    {onViewDetails && (
                        <Button
                            variant="ghost"
                            size="icon"
                            className="h-8 w-8"
                            onClick={onViewDetails}
                        >
                            <Eye className="h-4 w-4" />
                        </Button>
                    )}
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                {/* Total Amount */}
                <div>
                    <div className="text-3xl font-bold text-primary">
                        {formatCurrency(summary.total_amount)}
                    </div>
                    <p className="text-xs text-muted-foreground">
                        {summary.transaction_count} transaction
                        {summary.transaction_count !== 1 ? 's' : ''} processed
                    </p>
                </div>

                {/* Payment Method Breakdown */}
                {breakdownEntries.length > 0 && (
                    <div className="space-y-2">
                        <p className="text-xs font-medium text-muted-foreground">
                            By Payment Method
                        </p>
                        <div className="grid grid-cols-2 gap-2">
                            {breakdownEntries.map(([code, data]) => (
                                <div
                                    key={code}
                                    className="flex items-center gap-2 rounded-md bg-background/50 p-2"
                                >
                                    <div className="text-muted-foreground">
                                        {paymentMethodIcons[code] || (
                                            <Wallet className="h-4 w-4" />
                                        )}
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-xs font-medium">
                                            {data.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {formatCurrency(data.total_amount)}
                                        </p>
                                    </div>
                                    <Badge
                                        variant="secondary"
                                        className="text-xs"
                                    >
                                        {data.transaction_count}
                                    </Badge>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Empty State */}
                {summary.transaction_count === 0 && (
                    <p className="text-center text-sm text-muted-foreground">
                        No collections yet today
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
