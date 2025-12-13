import { Building2, DollarSign, Users, UsersRound } from 'lucide-react';

import { DashboardMetricsGrid } from '@/components/Dashboard/DashboardLayout';
import { MetricCard } from '@/components/Dashboard/MetricCard';

export interface AdminMetricsData {
    totalPatientsToday: number;
    totalRevenueToday: number;
    activeUsersCount: number;
    totalDepartments: number;
}

export interface AdminMetricsProps {
    metrics: AdminMetricsData;
    patientsHref?: string;
    reportsHref?: string;
    usersHref?: string;
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

export function AdminMetrics({
    metrics,
    patientsHref,
    reportsHref,
    usersHref,
}: AdminMetricsProps) {
    return (
        <DashboardMetricsGrid columns={4}>
            <MetricCard
                title="Patients Today"
                value={metrics.totalPatientsToday}
                icon={UsersRound}
                variant="primary"
                href={patientsHref}
            />
            <MetricCard
                title="Today's Revenue"
                value={formatCurrency(metrics.totalRevenueToday)}
                icon={DollarSign}
                variant="success"
                href={reportsHref}
            />
            <MetricCard
                title="Active Users"
                value={metrics.activeUsersCount}
                icon={Users}
                variant="accent"
                href={usersHref}
            />
            <MetricCard
                title="Departments"
                value={metrics.totalDepartments}
                icon={Building2}
                variant="info"
            />
        </DashboardMetricsGrid>
    );
}
