import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    AlertCircle,
    DollarSign,
    TrendingUp,
    Wallet,
} from 'lucide-react';

interface BillingStats {
    pending_charges_count: number;
    pending_charges_amount: number;
    todays_revenue: number;
    total_outstanding: number;
    collection_rate: number;
}

interface BillingStatsCardsProps {
    stats: BillingStats;
    formatCurrency: (amount: number) => string;
}

export function BillingStatsCards({
    stats,
    formatCurrency,
}: BillingStatsCardsProps) {
    const formatPercentage = (value: number) => {
        return `${value.toFixed(1)}%`;
    };

    const getCollectionRateColor = (rate: number) => {
        if (rate >= 80) return 'text-green-600';
        if (rate >= 60) return 'text-yellow-600';
        return 'text-red-600';
    };

    return (
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            {/* Pending Charges */}
            <Card>
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">
                        Pending Charges
                    </CardTitle>
                    <AlertCircle className="h-4 w-4 text-orange-600" />
                </CardHeader>
                <CardContent>
                    <div className="text-2xl font-bold text-orange-600">
                        {formatCurrency(stats.pending_charges_amount)}
                    </div>
                    <p className="text-xs text-muted-foreground">
                        {stats.pending_charges_count} charge
                        {stats.pending_charges_count !== 1 ? 's' : ''} pending
                    </p>
                </CardContent>
            </Card>

            {/* Today's Revenue */}
            <Card>
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">
                        Today's Revenue
                    </CardTitle>
                    <DollarSign className="h-4 w-4 text-green-600" />
                </CardHeader>
                <CardContent>
                    <div className="text-2xl font-bold text-green-600">
                        {formatCurrency(stats.todays_revenue)}
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Collected today
                    </p>
                </CardContent>
            </Card>

            {/* Total Outstanding */}
            <Card>
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">
                        Total Outstanding
                    </CardTitle>
                    <Wallet className="h-4 w-4 text-blue-600" />
                </CardHeader>
                <CardContent>
                    <div className="text-2xl font-bold text-blue-600">
                        {formatCurrency(stats.total_outstanding)}
                    </div>
                    <p className="text-xs text-muted-foreground">
                        All unpaid charges
                    </p>
                </CardContent>
            </Card>

            {/* Collection Rate */}
            <Card>
                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle className="text-sm font-medium">
                        Collection Rate
                    </CardTitle>
                    <TrendingUp className="h-4 w-4 text-purple-600" />
                </CardHeader>
                <CardContent>
                    <div
                        className={`text-2xl font-bold ${getCollectionRateColor(stats.collection_rate)}`}
                    >
                        {formatPercentage(stats.collection_rate)}
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Payment success rate
                    </p>
                </CardContent>
            </Card>
        </div>
    );
}
