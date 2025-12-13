import { Banknote, CreditCard, Receipt } from 'lucide-react';

import { DashboardMetricsGrid } from '@/components/Dashboard/DashboardLayout';
import { MetricCard } from '@/components/Dashboard/MetricCard';

export interface CashierMetricsData {
    pendingPaymentsAmount: number;
    pendingPaymentsCount: number;
    todayCollections: number;
    transactionsToday: number;
}

export interface CashierMetricsProps {
    metrics: CashierMetricsData;
    billingHref?: string;
}

/**
 * Format currency amount for display.
 */
function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('en-GH', {
        style: 'currency',
        currency: 'GHS',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount);
}

export function CashierMetrics({ metrics, billingHref }: CashierMetricsProps) {
    return (
        <DashboardMetricsGrid columns={3}>
            <MetricCard
                title="Pending Payments"
                value={formatCurrency(metrics.pendingPaymentsAmount)}
                icon={Banknote}
                variant={
                    metrics.pendingPaymentsCount > 20 ? 'warning' : 'default'
                }
                href={billingHref}
            />
            <MetricCard
                title="Today's Collections"
                value={formatCurrency(metrics.todayCollections)}
                icon={CreditCard}
                variant={metrics.todayCollections > 0 ? 'success' : 'default'}
            />
            <MetricCard
                title="Transactions Today"
                value={metrics.transactionsToday}
                icon={Receipt}
                variant="default"
            />
        </DashboardMetricsGrid>
    );
}
