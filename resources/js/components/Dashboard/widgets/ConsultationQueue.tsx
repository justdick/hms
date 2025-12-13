import { Link } from '@inertiajs/react';
import {
    ArrowRight,
    CheckCircle2,
    Clock,
    FlaskConical,
    Stethoscope,
    Users,
} from 'lucide-react';

import { cn } from '@/lib/utils';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { MetricCard } from '@/components/Dashboard/MetricCard';
import { DashboardMetricsGrid } from '@/components/Dashboard/DashboardLayout';

export interface DoctorMetricsData {
    consultationQueue: number;
    activeConsultations: number;
    pendingLabResults: number;
    completedToday: number;
}

export interface DoctorMetricsProps {
    metrics: DoctorMetricsData;
    consultationHref?: string;
    labResultsHref?: string;
}

export function DoctorMetrics({ metrics, consultationHref, labResultsHref }: DoctorMetricsProps) {
    return (
        <DashboardMetricsGrid columns={4}>
            <MetricCard
                title="Waiting Patients"
                value={metrics.consultationQueue}
                icon={Users}
                variant={metrics.consultationQueue > 10 ? 'warning' : 'primary'}
                href={consultationHref}
            />
            <MetricCard
                title="In Consultation"
                value={metrics.activeConsultations}
                icon={Stethoscope}
                variant="accent"
            />
            <MetricCard
                title="Pending Lab Results"
                value={metrics.pendingLabResults}
                icon={FlaskConical}
                variant={metrics.pendingLabResults > 5 ? 'warning' : 'info'}
                href={labResultsHref}
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

export interface NextPatient {
    id: number;
    patient_name: string;
    patient_number: string;
    department: string;
    chief_complaint?: string;
    wait_time: string;
}

export interface NextPatientsProps {
    patients: NextPatient[];
    viewAllHref?: string;
    onStartConsultation?: (checkinId: number) => void;
    className?: string;
}

export function NextPatients({
    patients,
    viewAllHref,
    onStartConsultation,
    className,
}: NextPatientsProps) {
    // Show only next 3 patients
    const nextUp = patients.slice(0, 3);

    return (
        <Card className={cn('', className)}>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
                <div>
                    <CardTitle className="text-base font-semibold">
                        Next Patients
                    </CardTitle>
                    <CardDescription>
                        Ready for consultation
                    </CardDescription>
                </div>
                {viewAllHref && (
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={viewAllHref}>
                            View Queue
                            <ArrowRight className="ml-1 h-4 w-4" />
                        </Link>
                    </Button>
                )}
            </CardHeader>
            <CardContent>
                {nextUp.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-6 text-muted-foreground">
                        <CheckCircle2 className="mb-2 h-8 w-8 opacity-50" />
                        <span>No patients waiting</span>
                    </div>
                ) : (
                    <div className="space-y-3">
                        {nextUp.map((patient, index) => (
                            <div
                                key={patient.id}
                                className={cn(
                                    'flex items-center justify-between rounded-lg border p-3',
                                    index === 0 && 'border-primary bg-primary/5'
                                )}
                            >
                                <div className="flex flex-col">
                                    <div className="flex items-center gap-2">
                                        {index === 0 && (
                                            <Badge variant="default" className="text-xs">
                                                Next
                                            </Badge>
                                        )}
                                        <span className="font-medium text-sm">
                                            {patient.patient_name}
                                        </span>
                                    </div>
                                    <span className="text-xs text-muted-foreground">
                                        {patient.department} • {patient.patient_number}
                                    </span>
                                    {patient.chief_complaint && (
                                        <span className="text-xs text-muted-foreground mt-1 italic">
                                            "{patient.chief_complaint}"
                                        </span>
                                    )}
                                </div>
                                <div className="flex items-center gap-2">
                                    <div className="flex items-center gap-1 text-muted-foreground">
                                        <Clock className="h-3 w-3" />
                                        <span className="text-xs font-mono">{patient.wait_time}</span>
                                    </div>
                                    {index === 0 && onStartConsultation && (
                                        <Button
                                            size="sm"
                                            onClick={() => onStartConsultation(patient.id)}
                                        >
                                            Start
                                        </Button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
