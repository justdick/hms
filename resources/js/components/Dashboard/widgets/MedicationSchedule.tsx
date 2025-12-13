import { Link } from '@inertiajs/react';
import { AlertCircle, ArrowRight, CheckCircle2, Clock, Pill } from 'lucide-react';

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

export interface UpcomingMedication {
    id: number;
    patient_name: string;
    ward: string;
    bed: string | null;
    medication: string;
    dosage: string | null;
    scheduled_time: string;
    is_overdue: boolean;
}

export interface MedicationScheduleProps {
    schedule: UpcomingMedication[];
    viewAllHref?: string;
    className?: string;
}

export function MedicationSchedule({
    schedule,
    viewAllHref,
    className,
}: MedicationScheduleProps) {
    // Show only next 5 medications
    const upcoming = schedule.slice(0, 5);
    const overdueCount = schedule.filter(m => m.is_overdue).length;

    return (
        <Card className={cn('', className)}>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
                <div className="flex items-center gap-2">
                    <Pill className="h-5 w-5 text-primary" />
                    <div>
                        <CardTitle className="text-base font-semibold">
                            Medication Schedule
                        </CardTitle>
                        <CardDescription>
                            {overdueCount > 0 ? (
                                <span className="text-red-600">{overdueCount} overdue</span>
                            ) : (
                                'Next 2 hours'
                            )}
                        </CardDescription>
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
                {upcoming.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-6 text-muted-foreground">
                        <CheckCircle2 className="mb-2 h-8 w-8 opacity-50" />
                        <span>No medications scheduled</span>
                    </div>
                ) : (
                    <div className="space-y-2">
                        {upcoming.map((med) => (
                            <div
                                key={med.id}
                                className={cn(
                                    'flex items-center justify-between rounded-lg border p-3',
                                    med.is_overdue && 'border-red-300 bg-red-50 dark:border-red-800 dark:bg-red-950'
                                )}
                            >
                                <div className="flex flex-col min-w-0">
                                    <div className="flex items-center gap-2">
                                        {med.is_overdue && (
                                            <AlertCircle className="h-4 w-4 text-red-600 shrink-0" />
                                        )}
                                        <span className="font-medium text-sm truncate">
                                            {med.patient_name}
                                        </span>
                                    </div>
                                    <span className="text-xs text-muted-foreground">
                                        {med.ward}{med.bed ? ` • Bed ${med.bed}` : ''}
                                    </span>
                                    <span className="text-xs text-muted-foreground truncate">
                                        {med.medication} {med.dosage && `(${med.dosage})`}
                                    </span>
                                </div>
                                <Badge 
                                    variant={med.is_overdue ? 'destructive' : 'outline'} 
                                    className="font-mono shrink-0"
                                >
                                    <Clock className="mr-1 h-3 w-3" />
                                    {med.scheduled_time}
                                </Badge>
                            </div>
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
