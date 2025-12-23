import { Link } from '@inertiajs/react';
import {
    ArrowRight,
    Banknote,
    CreditCard,
    Receipt,
    TrendingUp,
} from 'lucide-react';

import { DashboardMetricsGrid } from '@/components/Dashboard/DashboardLayout';
import { MetricCard } from '@/components/Dashboard/MetricCard';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';

export interface CashierMetricsData {
    todayCollections: number;
    transactionCount: number;
    pendingPayments: number;
    averageTransaction: number;
}

export interface CashierMetricsProps {
    metrics: CashierMetricsData;
    billingHref?: string;
}

function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('en-GH', {
        style: 'currency',
        currency: 'GHS',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(amount);
}

export function CashierMetrics({ metrics, billingHref }: CashierMetricsProps) {
    return (
        <DashboardMetricsGrid columns={4}>
            <MetricCard
                title="Today's Collections"
                value={formatCurrency(metrics.todayCollections)}
                icon={Banknote}
                variant="success"
            />
            <MetricCard
                title="Transactions"
                value={metrics.transactionCount}
                icon={Receipt}
                variant="primary"
            />
            <MetricCard
                title="Pending Payments"
                value={metrics.pendingPayments}
                icon={CreditCard}
                variant={metrics.pendingPayments > 10 ? 'warning' : 'accent'}
                href={billingHref}
            />
            <MetricCard
                title="Avg Transaction"
                value={formatCurrency(metrics.averageTransaction)}
                icon={TrendingUp}
                variant="info"
            />
        </DashboardMetricsGrid>
    );
}

export interface RecentPayment {
    id: number;
    patient_name: string;
    amount: number;
    method: string;
    time: string;
}

export interface RecentPaymentsProps {
    payments: RecentPayment[];
    viewAllHref?: string;
    className?: string;
}

const methodIcons: Record<string, string> = {
    cash: '💵',
    card: '💳',
    mobile: '📱',
    insurance: '🏥',
};

export function RecentPayments({
    payments,
    viewAllHref,
    className,
}: RecentPaymentsProps) {
    // Show only last 5 payments
    const recent = payments.slice(0, 5);

    return (
        <Card className={cn('', className)}>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
                <div className="flex items-center gap-2">
                    <Receipt className="h-5 w-5 text-primary" />
                    <div>
                        <CardTitle className="text-base font-semibold">
                            Recent Payments
                        </CardTitle>
                        <CardDescription>Latest transactions</CardDescription>
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
                {recent.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-6 text-muted-foreground">
                        <CreditCard className="mb-2 h-8 w-8 opacity-50" />
                        <span>No payments today</span>
                    </div>
                ) : (
                    <div className="space-y-2">
                        {recent.map((payment) => (
                            <div
                                key={payment.id}
                                className="flex items-center justify-between rounded-lg border p-3"
                            >
                                <div className="flex items-center gap-3">
                                    <span className="text-lg">
                                        {methodIcons[payment.method] || '💰'}
                                    </span>
                                    <div className="flex flex-col">
                                        <span className="text-sm font-medium">
                                            {payment.patient_name}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {payment.time}
                                        </span>
                                    </div>
                                </div>
                                <span className="font-medium text-green-600">
                                    +{formatCurrency(payment.amount)}
                                </span>
                            </div>
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
