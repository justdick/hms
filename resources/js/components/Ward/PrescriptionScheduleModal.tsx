import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import {
    CheckCircle,
    Clock,
    Info,
    Loader2,
    MinusCircle,
    XCircle,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { ScheduleAdjustmentBadge } from './ScheduleAdjustmentBadge';

/**
 * Check if the schedule pattern is a smart input pattern
 */
function isSmartSchedulePattern(
    pattern: Prescription['schedule_pattern'],
): pattern is SmartSchedulePattern {
    if (!pattern) return false;
    return (
        'type' in pattern &&
        ['standard', 'split_dose', 'custom_interval', 'taper'].includes(
            pattern.type as string,
        )
    );
}

/**
 * Format smart schedule pattern for display
 */
function formatSmartPattern(pattern: SmartSchedulePattern): string {
    switch (pattern.type) {
        case 'split_dose':
            if (pattern.pattern) {
                const { morning, noon, evening } = pattern.pattern;
                return `Split dose: ${morning}-${noon}-${evening} (${pattern.daily_total}/day)`;
            }
            return 'Split dose pattern';

        case 'custom_interval':
            if (pattern.intervals_hours) {
                const intervals = pattern.intervals_hours
                    .map((h) => `${h}h`)
                    .join(', ');
                return `Custom intervals: ${intervals} (${pattern.total_doses} doses)`;
            }
            return 'Custom interval schedule';

        case 'taper':
            if (pattern.doses) {
                return `Taper: ${pattern.doses.join('-')} over ${pattern.duration_days} days`;
            }
            return 'Taper schedule';

        case 'standard':
            return `Standard: ${pattern.frequency_code} (${pattern.times_per_day}x daily)`;

        default:
            return 'Custom schedule';
    }
}

interface Drug {
    id: number;
    name: string;
    strength?: string;
}

interface User {
    id: number;
    name: string;
}

interface MedicationScheduleAdjustment {
    id: number;
    original_time: string;
    adjusted_time: string;
    reason?: string;
    adjusted_by: User;
    created_at: string;
}

interface MedicationAdministration {
    id: number;
    scheduled_time: string;
    administered_at?: string;
    status:
        | 'scheduled'
        | 'given'
        | 'held'
        | 'refused'
        | 'omitted'
        | 'cancelled';
    dosage_given?: string;
    route?: string;
    notes?: string;
    is_adjusted: boolean;
    administered_by?: User;
    schedule_adjustments?: MedicationScheduleAdjustment[];
}

interface SmartSchedulePattern {
    type: 'standard' | 'split_dose' | 'custom_interval' | 'taper';
    frequency_code?: string;
    times_per_day?: number;
    pattern?: {
        morning: number;
        noon: number;
        evening: number;
    };
    daily_total?: number;
    intervals_hours?: number[];
    dose_per_interval?: number;
    total_doses?: number;
    doses?: number[];
    duration_days?: number;
}

interface Prescription {
    id: number;
    drug?: Drug;
    medication_name: string;
    frequency?: string;
    duration?: string;
    dose_quantity?: string;
    schedule_pattern?: SmartSchedulePattern;
}

interface PrescriptionScheduleModalProps {
    prescription: Prescription | null;
    isOpen: boolean;
    onClose: () => void;
}

export function PrescriptionScheduleModal({
    prescription,
    isOpen,
    onClose,
}: PrescriptionScheduleModalProps) {
    const [loading, setLoading] = useState(false);
    const [administrations, setAdministrations] = useState<
        MedicationAdministration[]
    >([]);

    useEffect(() => {
        if (isOpen && prescription) {
            setLoading(true);
            fetch(`/api/prescriptions/${prescription.id}/schedule`)
                .then((res) => res.json())
                .then((data) => {
                    setAdministrations(data.administrations || []);
                })
                .catch(() => {
                    toast.error('Failed to load schedule');
                })
                .finally(() => {
                    setLoading(false);
                });
        }
    }, [isOpen, prescription]);

    const formatDateTime = (dateString: string) => {
        return new Date(dateString).toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true,
        });
    };

    const formatTime = (dateString: string) => {
        return new Date(dateString).toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: true,
        });
    };

    const getStatusBadge = (status: MedicationAdministration['status']) => {
        const configs = {
            given: {
                icon: CheckCircle,
                label: 'Given',
                className:
                    'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            },
            scheduled: {
                icon: Clock,
                label: 'Scheduled',
                className:
                    'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            },
            held: {
                icon: MinusCircle,
                label: 'Held',
                className:
                    'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
            },
            refused: {
                icon: XCircle,
                label: 'Refused',
                className:
                    'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
            },
            cancelled: {
                icon: XCircle,
                label: 'Cancelled',
                className:
                    'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
            },
            omitted: {
                icon: MinusCircle,
                label: 'Omitted',
                className:
                    'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
            },
        };

        const config = configs[status];
        const Icon = config.icon;

        return (
            <Badge variant="outline" className={cn('gap-1', config.className)}>
                <Icon className="h-3 w-3" />
                {config.label}
            </Badge>
        );
    };

    if (!prescription) return null;

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-h-[90vh] max-w-4xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Full Medication Schedule</DialogTitle>
                    <DialogDescription>
                        {prescription.drug?.name ||
                            prescription.medication_name}
                        {prescription.drug?.strength &&
                            ` ${prescription.drug.strength}`}{' '}
                        - {prescription.frequency}
                        {prescription.duration &&
                            ` for ${prescription.duration}`}
                    </DialogDescription>
                </DialogHeader>

                {/* Display original smart input pattern if available */}
                {isSmartSchedulePattern(prescription.schedule_pattern) && (
                    <div className="rounded-lg border border-purple-200 bg-purple-50 p-3 dark:border-purple-800 dark:bg-purple-950/20">
                        <div className="flex items-start gap-2">
                            <Info className="mt-0.5 h-4 w-4 flex-shrink-0 text-purple-600 dark:text-purple-400" />
                            <div className="flex-1">
                                <p className="text-sm font-medium text-purple-900 dark:text-purple-200">
                                    Original Prescription Pattern
                                </p>
                                <p className="text-sm text-purple-700 dark:text-purple-300">
                                    {prescription.dose_quantity && (
                                        <span className="font-medium">
                                            {prescription.dose_quantity}{' '}
                                        </span>
                                    )}
                                    {formatSmartPattern(
                                        prescription.schedule_pattern,
                                    )}
                                </p>
                                {prescription.schedule_pattern.type ===
                                    'custom_interval' &&
                                    prescription.schedule_pattern
                                        .intervals_hours && (
                                        <div className="mt-2 flex flex-wrap gap-1">
                                            {prescription.schedule_pattern.intervals_hours.map(
                                                (h, idx) => (
                                                    <Badge
                                                        key={idx}
                                                        variant="outline"
                                                        className="border-purple-300 bg-purple-100 text-purple-800 dark:border-purple-700 dark:bg-purple-900/50 dark:text-purple-200"
                                                    >
                                                        {h}h
                                                    </Badge>
                                                ),
                                            )}
                                        </div>
                                    )}
                            </div>
                        </div>
                    </div>
                )}

                {loading ? (
                    <div className="flex items-center justify-center py-8">
                        <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
                    </div>
                ) : administrations.length === 0 ? (
                    <div className="py-8 text-center text-muted-foreground">
                        No scheduled administrations found
                    </div>
                ) : (
                    <div className="space-y-4">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Scheduled Time</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Administered</TableHead>
                                    <TableHead>By</TableHead>
                                    <TableHead>Notes</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {administrations.map((admin) => (
                                    <TableRow key={admin.id}>
                                        <TableCell>
                                            <div className="flex items-center gap-2">
                                                <span className="font-medium">
                                                    {formatDateTime(
                                                        admin.scheduled_time,
                                                    )}
                                                </span>
                                                {admin.is_adjusted && (
                                                    <ScheduleAdjustmentBadge
                                                        adjustments={
                                                            admin.schedule_adjustments ||
                                                            []
                                                        }
                                                    />
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {getStatusBadge(admin.status)}
                                        </TableCell>
                                        <TableCell>
                                            {admin.administered_at
                                                ? formatTime(
                                                      admin.administered_at,
                                                  )
                                                : '-'}
                                        </TableCell>
                                        <TableCell>
                                            {admin.administered_by?.name || '-'}
                                        </TableCell>
                                        <TableCell>
                                            {admin.notes ? (
                                                <TooltipProvider>
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <span className="cursor-help text-sm text-muted-foreground">
                                                                {admin.notes.substring(
                                                                    0,
                                                                    30,
                                                                )}
                                                                {admin.notes
                                                                    .length >
                                                                    30 && '...'}
                                                            </span>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            <p className="max-w-xs">
                                                                {admin.notes}
                                                            </p>
                                                        </TooltipContent>
                                                    </Tooltip>
                                                </TooltipProvider>
                                            ) : (
                                                '-'
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        <div className="flex justify-end">
                            <Button onClick={onClose}>Close</Button>
                        </div>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
