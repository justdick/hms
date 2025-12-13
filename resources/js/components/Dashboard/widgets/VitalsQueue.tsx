import { Link } from '@inertiajs/react';
import {
    Activity,
    ArrowRight,
    Bed,
    CheckCircle2,
    Clock,
    Pill,
} from 'lucide-react';

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

export interface NurseMetricsData {
    awaitingVitals: number;
    pendingMedications: number;
    activeAdmissions: number;
    vitalsRecordedToday: number;
}

export interface NurseMetricsProps {
    metrics: NurseMetricsData;
    checkinHref?: string;
    medicationsHref?: string;
    wardsHref?: string;
}

export function NurseMetrics({
    metrics,
    checkinHref,
    medicationsHref,
    wardsHref,
}: NurseMetricsProps) {
    return (
        <DashboardMetricsGrid columns={4}>
            <MetricCard
                title="Awaiting Vitals"
                value={metrics.awaitingVitals}
                icon={Activity}
                variant={metrics.awaitingVitals > 10 ? 'warning' : 'primary'}
                href={checkinHref}
            />
            <MetricCard
                title="Pending Medications"
                value={metrics.pendingMedications}
                icon={Pill}
                variant={metrics.pendingMedications > 5 ? 'warning' : 'accent'}
                href={medicationsHref}
            />
            <MetricCard
                title="Active Admissions"
                value={metrics.activeAdmissions}
                icon={Bed}
                variant="info"
                href={wardsHref}
            />
            <MetricCard
                title="Vitals Recorded Today"
                value={metrics.vitalsRecordedToday}
                icon={CheckCircle2}
                variant="success"
            />
        </DashboardMetricsGrid>
    );
}

export interface VitalsPatient {
    id: number;
    patient_name: string;
    department: string;
    wait_time: string;
    is_urgent: boolean;
}

export interface VitalsQueueProps {
    queue: VitalsPatient[];
    viewAllHref?: string;
    className?: string;
}

export function VitalsQueue({
    queue,
    viewAllHref,
    className,
}: VitalsQueueProps) {
    // Show only top 5 longest waiting
    const waiting = queue.slice(0, 5);

    return (
        <Card className={cn('', className)}>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
                <div>
                    <CardTitle className="text-base font-semibold">
                        Vitals Queue
                    </CardTitle>
                    <CardDescription>
                        Patients awaiting vitals
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
                {waiting.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-6 text-muted-foreground">
                        <CheckCircle2 className="mb-2 h-8 w-8 opacity-50" />
                        <span>No patients waiting</span>
                    </div>
                ) : (
                    <div className="space-y-2">
                        {waiting.map((patient, index) => {
                            const isLongWait = patient.wait_time.includes('h') || 
                                (patient.wait_time.endsWith('m') && parseInt(patient.wait_time) > 15);
                            return (
                                <div
                                    key={patient.id}
                                    className={cn(
                                        'flex items-center justify-between rounded-lg border p-3',
                                        isLongWait && 'border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950'
                                    )}
                                >
                                    <div className="flex flex-col">
                                        <span className="font-medium text-sm">
                                            {patient.patient_name}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {patient.department}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-1 text-muted-foreground">
                                        <Clock className="h-3 w-3" />
                                        <span className={cn(
                                            'text-xs font-mono',
                                            isLongWait && 'text-amber-600 font-medium'
                                        )}>
                                            {patient.wait_time}
                                        </span>
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
