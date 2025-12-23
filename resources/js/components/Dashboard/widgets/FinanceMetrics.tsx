import { FileText, TrendingUp, Wallet } from 'lucide-react';

import { DashboardMetricsGrid } from '@/components/Dashboard/DashboardLayout';
import { MetricCard } from '@/components/Dashboard/MetricCard';

export interface FinanceMetricsData {
    todayRevenue: number;
    outstandingReceivables: number;
    pendingInsuranceClaims: number;
    pendingInsuranceClaimsCount: number;
}

export interface FinanceMetricsProps {
    metrics: FinanceMetricsData;
    reportsHref?: string;
    claimsHref?: string;
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

export function FinanceMetrics({
    metrics,
    reportsHref,
    claimsHref,
}: FinanceMetricsProps) {
    return (
        <DashboardMetricsGrid columns={3}>
            <MetricCard
                title="Today's Revenue"
                value={formatCurrency(metrics.todayRevenue)}
                icon={TrendingUp}
                variant="success"
                href={reportsHref}
            />
            <MetricCard
                title="Outstanding Receivables"
                value={formatCurrency(metrics.outstandingReceivables)}
                icon={Wallet}
                variant={
                    metrics.outstandingReceivables > 10000
                        ? 'warning'
                        : 'primary'
                }
            />
            <MetricCard
                title={`Pending Claims (${metrics.pendingInsuranceClaimsCount})`}
                value={formatCurrency(metrics.pendingInsuranceClaims)}
                icon={FileText}
                variant={
                    metrics.pendingInsuranceClaimsCount > 50
                        ? 'warning'
                        : 'accent'
                }
                href={claimsHref}
            />
        </DashboardMetricsGrid>
    );
}
