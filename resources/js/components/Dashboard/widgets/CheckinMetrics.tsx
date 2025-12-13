import * as React from 'react';
import { ClipboardCheck, Clock, Stethoscope } from 'lucide-react';

import { MetricCard } from '@/components/Dashboard/MetricCard';
import { DashboardMetricsGrid } from '@/components/Dashboard/DashboardLayout';

export interface CheckinMetricsData {
    todayCheckins: number;
    waitingPatients: number;
    awaitingConsultation: number;
}

export interface CheckinMetricsProps {
    metrics: CheckinMetricsData;
    checkinHref?: string;
}

export function CheckinMetrics({ metrics, checkinHref }: CheckinMetricsProps) {
    return (
        <DashboardMetricsGrid columns={3}>
            <MetricCard
                title="Today's Check-ins"
                value={metrics.todayCheckins}
                icon={ClipboardCheck}
                variant="default"
                href={checkinHref}
            />
            <MetricCard
                title="Waiting Patients"
                value={metrics.waitingPatients}
                icon={Clock}
                variant={metrics.waitingPatients > 10 ? 'warning' : 'default'}
            />
            <MetricCard
                title="Awaiting Consultation"
                value={metrics.awaitingConsultation}
                icon={Stethoscope}
                variant={metrics.awaitingConsultation > 5 ? 'warning' : 'success'}
            />
        </DashboardMetricsGrid>
    );
}
