import {
    Banknote,
    Building2,
    DollarSign,
    ShieldCheck,
    Users,
    UsersRound,
} from 'lucide-react';

import { DashboardMetricsGrid } from '@/components/Dashboard/DashboardLayout';
import { MetricCard } from '@/components/Dashboard/MetricCard';

export interface AdminMetricsData {
    totalPatientsToday: number;
    totalRevenueToday: number;
    activeUsersCount: number;
    totalDepartments: number;
    nhisAttendance?: number;
    nonInsuredAttendance?: number;
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
        <DashboardMetricsGrid columns={6}>
            <MetricCard
                title="Total Attendance"
                value={metrics.totalPatientsToday}
                icon={UsersRound}
                variant="primary"
                href={patientsHref}
            />
            <MetricCard
                title="NHIS Attendance"
                value={metrics.nhisAttendance ?? 0}
                icon={ShieldCheck}
                variant="success"
            />
            <MetricCard
                title="Non-Insured"
                value={metrics.nonInsuredAttendance ?? 0}
                icon={Banknote}
                variant="warning"
            />
            <MetricCard
                title="Revenue"
                value={formatCurrency(metrics.totalRevenueToday)}
                icon={DollarSign}
                variant="accent"
                href={reportsHref}
            />
            <MetricCard
                title="Active Users"
                value={metrics.activeUsersCount}
                icon={Users}
                variant="info"
                href={usersHref}
            />
            <MetricCard
                title="Departments"
                value={metrics.totalDepartments}
                icon={Building2}
                variant="default"
            />
        </DashboardMetricsGrid>
    );
}
