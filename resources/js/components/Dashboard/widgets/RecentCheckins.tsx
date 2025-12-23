import { Link } from '@inertiajs/react';
import { Activity, ArrowRight, CheckCircle2, Clock, Users } from 'lucide-react';

import { DashboardMetricsGrid } from '@/components/Dashboard/DashboardLayout';
import { MetricCard } from '@/components/Dashboard/MetricCard';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';

export interface ReceptionistMetricsData {
    todayCheckins: number;
    awaitingVitals: number;
    awaitingConsultation: number;
    completedToday: number;
}

export interface ReceptionistMetricsProps {
    metrics: ReceptionistMetricsData;
    checkinHref?: string;
}

export function ReceptionistMetrics({
    metrics,
    checkinHref,
}: ReceptionistMetricsProps) {
    return (
        <DashboardMetricsGrid columns={4}>
            <MetricCard
                title="Today's Check-ins"
                value={metrics.todayCheckins}
                icon={Users}
                variant="primary"
                href={checkinHref}
            />
            <MetricCard
                title="Awaiting Vitals"
                value={metrics.awaitingVitals}
                icon={Activity}
                variant={metrics.awaitingVitals > 5 ? 'warning' : 'accent'}
            />
            <MetricCard
                title="Awaiting Consultation"
                value={metrics.awaitingConsultation}
                icon={Clock}
                variant={metrics.awaitingConsultation > 10 ? 'warning' : 'info'}
            />
            <MetricCard
                title="Completed Today"
                value={metrics.completedToday}
                icon={CheckCircle2}
                variant="success"
            />
        </DashboardMetricsGrid>
    );
}

export interface WaitingPatient {
    id: number;
    patient_name: string;
    department: string;
    wait_time: string;
    status: 'checked_in' | 'vitals_taken' | 'awaiting_consultation';
}

export interface WaitingPatientsProps {
    patients: WaitingPatient[];
    viewAllHref?: string;
    className?: string;
}

const statusConfig: Record<string, { label: string; color: string }> = {
    checked_in: { label: 'Needs Vitals', color: 'text-amber-600' },
    vitals_taken: { label: 'Ready', color: 'text-blue-600' },
    awaiting_consultation: { label: 'Waiting', color: 'text-green-600' },
};

export function WaitingPatients({
    patients,
    viewAllHref,
    className,
}: WaitingPatientsProps) {
    // Show only top 5 longest waiting
    const topWaiting = patients.slice(0, 5);

    return (
        <Card className={cn('', className)}>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
                <div>
                    <CardTitle className="text-base font-semibold">
                        Longest Waiting
                    </CardTitle>
                    <CardDescription>
                        Patients waiting the longest
                    </CardDescription>
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
                {topWaiting.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-6 text-muted-foreground">
                        <CheckCircle2 className="mb-2 h-8 w-8 opacity-50" />
                        <span>No patients waiting</span>
                    </div>
                ) : (
                    <div className="space-y-2">
                        {topWaiting.map((patient) => {
                            const config =
                                statusConfig[patient.status] ||
                                statusConfig.checked_in;
                            return (
                                <div
                                    key={patient.id}
                                    className="flex items-center justify-between rounded-lg border p-3"
                                >
                                    <div className="flex flex-col">
                                        <span className="text-sm font-medium">
                                            {patient.patient_name}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {patient.department}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span
                                            className={cn(
                                                'text-xs font-medium',
                                                config.color,
                                            )}
                                        >
                                            {config.label}
                                        </span>
                                        <Badge
                                            variant="outline"
                                            className="font-mono"
                                        >
                                            {patient.wait_time}
                                        </Badge>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
