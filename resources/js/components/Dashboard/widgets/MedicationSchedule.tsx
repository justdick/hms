import { Link } from '@inertiajs/react';
import { ArrowRight, CheckCircle2, Clock, Pill } from 'lucide-react';

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

export interface RecentMedication {
    id: number;
    patient_name: string;
    ward: string;
    bed: string | null;
    medication: string;
    dosage: string | null;
    administered_at: string;
    status: 'given' | 'held' | 'refused' | 'omitted';
}

export interface MedicationScheduleProps {
    schedule: RecentMedication[];
    viewAllHref?: string;
    className?: string;
}

const statusConfig = {
    given: { label: 'Given', variant: 'default' as const, className: 'bg-green-600' },
    held: { label: 'Held', variant: 'secondary' as const, className: '' },
    refused: { label: 'Refused', variant: 'destructive' as const, className: '' },
    omitted: { label: 'Omitted', variant: 'outline' as const, className: '' },
};

export function MedicationSchedule({
    schedule,
    viewAllHref,
    className,
}: MedicationScheduleProps) {
    // Show only last 5 medications
    const recent = schedule.slice(0, 5);
    const givenCount = schedule.filter(m => m.status === 'given').length;

    return (
        <Card className={cn('', className)}>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
                <div className="flex items-center gap-2">
                    <Pill className="h-5 w-5 text-primary" />
                    <div>
                        <CardTitle className="text-base font-semibold">
                            Recent Medications
                        </CardTitle>
                        <CardDescription>
                            {givenCount > 0 ? (
                                <span className="text-green-600">{givenCount} given today</span>
                            ) : (
                                'Today\'s activity'
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
                {recent.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-6 text-muted-foreground">
                        <CheckCircle2 className="mb-2 h-8 w-8 opacity-50" />
                        <span>No medications recorded today</span>
                    </div>
                ) : (
                    <div className="space-y-2">
                        {recent.map((med) => {
                            const config = statusConfig[med.status] || statusConfig.given;
                            return (
                                <div
                                    key={med.id}
                                    className="flex items-center justify-between rounded-lg border p-3"
                                >
                                    <div className="flex flex-col min-w-0">
                                        <span className="font-medium text-sm truncate">
                                            {med.patient_name}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {med.ward}{med.bed ? ` • Bed ${med.bed}` : ''}
                                        </span>
                                        <span className="text-xs text-muted-foreground truncate">
                                            {med.medication} {med.dosage && `(${med.dosage})`}
                                        </span>
                                    </div>
                                    <div className="flex flex-col items-end gap-1 shrink-0">
                                        <Badge 
                                            variant={config.variant}
                                            className={config.className}
                                        >
                                            {config.label}
                                        </Badge>
                                        <span className="text-xs text-muted-foreground flex items-center">
                                            <Clock className="mr-1 h-3 w-3" />
                                            {med.administered_at}
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
