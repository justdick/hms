import { CheckCircle, FileSearch, Send } from 'lucide-react';

import { DashboardMetricsGrid } from '@/components/Dashboard/DashboardLayout';
import { MetricCard } from '@/components/Dashboard/MetricCard';

export interface ClaimsMetricsData {
    claimsPendingVetting: number;
    claimsSubmitted: number;
    claimsApprovedMonth: number;
    claimsApprovedAmountMonth: number;
}

export interface ClaimsMetricsProps {
    metrics: ClaimsMetricsData;
    vettingHref?: string;
    batchesHref?: string;
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

export function ClaimsMetrics({
    metrics,
    vettingHref,
    batchesHref,
}: ClaimsMetricsProps) {
    return (
        <DashboardMetricsGrid columns={3}>
            <MetricCard
                title="Pending Vetting"
                value={metrics.claimsPendingVetting}
                icon={FileSearch}
                variant={
                    metrics.claimsPendingVetting > 20 ? 'warning' : 'default'
                }
                href={vettingHref}
            />
            <MetricCard
                title="Claims Submitted"
                value={metrics.claimsSubmitted}
                icon={Send}
                variant={metrics.claimsSubmitted > 0 ? 'default' : 'success'}
                href={batchesHref}
            />
            <MetricCard
                title={`Approved This Month (${metrics.claimsApprovedMonth})`}
                value={formatCurrency(metrics.claimsApprovedAmountMonth)}
                icon={CheckCircle}
                variant="success"
            />
        </DashboardMetricsGrid>
    );
}
