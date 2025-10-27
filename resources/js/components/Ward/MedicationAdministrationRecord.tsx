import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { format, isAfter, isPast, isToday } from 'date-fns';
import {
    AlertCircle,
    CheckCircle2,
    Circle,
    Clock,
    Pill,
    X,
    XCircle,
} from 'lucide-react';
import { useMemo } from 'react';

interface Drug {
    id: number;
    name: string;
    strength?: string;
}

interface Prescription {
    id: number;
    medication_name: string;
    dosage?: string;
    frequency?: string;
    duration?: string;
    route?: string;
    drug?: Drug;
}

interface MedicationAdministration {
    id: number;
    prescription: Prescription;
    scheduled_time: string;
    status: 'scheduled' | 'given' | 'held' | 'refused' | 'omitted';
    dosage_given?: string;
    route?: string;
    notes?: string;
    administered_at?: string;
    administered_by?: {
        id: number;
        name: string;
    };
}

interface MedicationAdministrationRecordProps {
    medications: MedicationAdministration[];
    onAdminister: (med: MedicationAdministration) => void;
    onHold: (med: MedicationAdministration) => void;
    onRefuse: (med: MedicationAdministration) => void;
}

// Common medication time slots (24-hour format)
const TIME_SLOTS = [
    '06:00',
    '08:00',
    '10:00',
    '12:00',
    '14:00',
    '16:00',
    '18:00',
    '20:00',
    '22:00',
    '00:00',
];

export function MedicationAdministrationRecord({
    medications,
    onAdminister,
    onHold,
    onRefuse,
}: MedicationAdministrationRecordProps) {
    // Group medications by prescription (unique drugs)
    const groupedMedications = useMemo(() => {
        const grouped = new Map<
            number,
            {
                prescription: Prescription;
                administrations: MedicationAdministration[];
            }
        >();

        medications.forEach((med) => {
            const prescriptionId = med.prescription.id;
            if (!grouped.has(prescriptionId)) {
                grouped.set(prescriptionId, {
                    prescription: med.prescription,
                    administrations: [],
                });
            }
            grouped.get(prescriptionId)!.administrations.push(med);
        });

        return Array.from(grouped.values());
    }, [medications]);

    // Filter to show only today's medications
    const todayMedications = useMemo(() => {
        return groupedMedications.map((group) => ({
            ...group,
            administrations: group.administrations.filter((med) =>
                isToday(new Date(med.scheduled_time)),
            ),
        }));
    }, [groupedMedications]);

    if (todayMedications.length === 0) {
        return (
            <Card>
                <CardContent className="flex flex-col items-center justify-center py-12">
                    <Pill className="mb-4 h-12 w-12 text-muted-foreground/50" />
                    <p className="text-muted-foreground">
                        No medications scheduled for today
                    </p>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Pill className="h-5 w-5" />
                    Medication Administration Record (MAR) - Today
                </CardTitle>
                <div className="flex flex-wrap gap-3 pt-2 text-xs">
                    <div className="flex items-center gap-1">
                        <Circle className="h-3 w-3 text-gray-400" />
                        <span>Scheduled</span>
                    </div>
                    <div className="flex items-center gap-1">
                        <Clock className="h-3 w-3 text-orange-500" />
                        <span>Due Now</span>
                    </div>
                    <div className="flex items-center gap-1">
                        <CheckCircle2 className="h-3 w-3 text-green-600" />
                        <span>Given</span>
                    </div>
                    <div className="flex items-center gap-1">
                        <AlertCircle className="h-3 w-3 text-yellow-600" />
                        <span>Held</span>
                    </div>
                    <div className="flex items-center gap-1">
                        <X className="h-3 w-3 text-red-600" />
                        <span>Refused</span>
                    </div>
                    <div className="flex items-center gap-1">
                        <XCircle className="h-3 w-3 text-gray-600" />
                        <span>Omitted</span>
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                <div className="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-[250px]">
                                    Medication
                                </TableHead>
                                <TableHead className="w-[120px]">
                                    Dose & Route
                                </TableHead>
                                {TIME_SLOTS.map((time) => (
                                    <TableHead
                                        key={time}
                                        className="text-center"
                                    >
                                        {time}
                                    </TableHead>
                                ))}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {todayMedications.map((group) => (
                                <TableRow key={group.prescription.id}>
                                    <TableCell className="font-medium">
                                        <div className="space-y-1">
                                            <div className="font-semibold">
                                                {group.prescription.drug
                                                    ?.name ||
                                                    group.prescription
                                                        .medication_name}
                                            </div>
                                            {group.prescription.drug
                                                ?.strength && (
                                                <div className="text-xs text-muted-foreground">
                                                    {
                                                        group.prescription.drug
                                                            .strength
                                                    }
                                                </div>
                                            )}
                                            <Badge
                                                variant="outline"
                                                className="text-xs"
                                            >
                                                {group.prescription.frequency}
                                            </Badge>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div className="space-y-1 text-sm">
                                            <div>
                                                {group.prescription.dosage}
                                            </div>
                                            <div className="text-muted-foreground">
                                                {group.prescription.route ||
                                                    'PO'}
                                            </div>
                                        </div>
                                    </TableCell>
                                    {TIME_SLOTS.map((timeSlot) => {
                                        const administration =
                                            group.administrations.find(
                                                (admin) => {
                                                    const scheduledTime =
                                                        format(
                                                            new Date(
                                                                admin.scheduled_time,
                                                            ),
                                                            'HH:mm',
                                                        );
                                                    return (
                                                        scheduledTime ===
                                                        timeSlot
                                                    );
                                                },
                                            );

                                        return (
                                            <TableCell
                                                key={timeSlot}
                                                className="p-2 text-center"
                                            >
                                                {administration ? (
                                                    <MedicationSlot
                                                        administration={
                                                            administration
                                                        }
                                                        onAdminister={
                                                            onAdminister
                                                        }
                                                        onHold={onHold}
                                                        onRefuse={onRefuse}
                                                    />
                                                ) : (
                                                    <div className="text-muted-foreground">
                                                        -
                                                    </div>
                                                )}
                                            </TableCell>
                                        );
                                    })}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </CardContent>
        </Card>
    );
}

interface MedicationSlotProps {
    administration: MedicationAdministration;
    onAdminister: (med: MedicationAdministration) => void;
    onHold: (med: MedicationAdministration) => void;
    onRefuse: (med: MedicationAdministration) => void;
}

function MedicationSlot({
    administration,
    onAdminister,
    onHold,
    onRefuse,
}: MedicationSlotProps) {
    const scheduledTime = new Date(administration.scheduled_time);
    const isDue =
        isPast(scheduledTime) && administration.status === 'scheduled';
    const isUpcoming =
        isAfter(scheduledTime, new Date()) &&
        administration.status === 'scheduled';

    const getStatusIcon = () => {
        switch (administration.status) {
            case 'given':
                return (
                    <CheckCircle2 className="h-6 w-6 text-green-600 dark:text-green-500" />
                );
            case 'held':
                return (
                    <AlertCircle className="h-6 w-6 text-yellow-600 dark:text-yellow-500" />
                );
            case 'refused':
                return <X className="h-6 w-6 text-red-600 dark:text-red-500" />;
            case 'omitted':
                return (
                    <XCircle className="h-6 w-6 text-gray-600 dark:text-gray-500" />
                );
            case 'scheduled':
                if (isDue) {
                    return (
                        <Clock className="h-6 w-6 text-orange-500 dark:text-orange-400" />
                    );
                }
                return (
                    <Circle className="h-6 w-6 text-gray-400 dark:text-gray-500" />
                );
            default:
                return null;
        }
    };

    const getTooltipContent = () => {
        const baseInfo = `${administration.prescription.drug?.name || administration.prescription.medication_name}\nScheduled: ${format(scheduledTime, 'HH:mm')}`;

        switch (administration.status) {
            case 'given':
                return `${baseInfo}\nGiven: ${administration.administered_at ? format(new Date(administration.administered_at), 'HH:mm') : 'N/A'}\nBy: ${administration.administered_by?.name || 'Unknown'}${administration.notes ? `\nNotes: ${administration.notes}` : ''}`;
            case 'held':
                return `${baseInfo}\nStatus: Held\nReason: ${administration.notes || 'No reason provided'}`;
            case 'refused':
                return `${baseInfo}\nStatus: Refused by patient${administration.notes ? `\nNotes: ${administration.notes}` : ''}`;
            case 'omitted':
                return `${baseInfo}\nStatus: Omitted\nReason: ${administration.notes || 'No reason provided'}`;
            case 'scheduled':
                return isDue
                    ? `${baseInfo}\nStatus: DUE NOW - Click to administer`
                    : `${baseInfo}\nStatus: Scheduled`;
            default:
                return baseInfo;
        }
    };

    if (administration.status === 'scheduled') {
        return (
            <TooltipProvider>
                <Tooltip>
                    <TooltipTrigger asChild>
                        <div className="flex items-center justify-center gap-1">
                            <Button
                                size="sm"
                                variant={isDue ? 'default' : 'ghost'}
                                className={cn(
                                    'h-8 w-8 rounded-full p-0',
                                    isDue &&
                                        'animate-pulse bg-orange-500 hover:bg-orange-600',
                                )}
                                onClick={() => onAdminister(administration)}
                            >
                                {getStatusIcon()}
                            </Button>
                        </div>
                    </TooltipTrigger>
                    <TooltipContent>
                        <pre className="text-xs whitespace-pre-wrap">
                            {getTooltipContent()}
                        </pre>
                    </TooltipContent>
                </Tooltip>
            </TooltipProvider>
        );
    }

    return (
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger asChild>
                    <div className="flex items-center justify-center">
                        {getStatusIcon()}
                    </div>
                </TooltipTrigger>
                <TooltipContent>
                    <pre className="text-xs whitespace-pre-wrap">
                        {getTooltipContent()}
                    </pre>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}
