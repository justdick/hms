import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { format } from 'date-fns';
import {
    AlertTriangle,
    Calendar,
    Clock,
    Eye,
    MoreVertical,
    Pill,
    Settings,
    XCircle,
} from 'lucide-react';

interface Drug {
    id: number;
    name: string;
    strength?: string;
    form?: string;
}

interface User {
    id: number;
    name: string;
}

interface Prescription {
    id: number;
    drug?: Drug;
    medication_name: string;
    dosage?: string;
    dose_quantity?: string;
    frequency?: string;
    duration?: string;
    route?: string;
    instructions?: string;
    status?: string;
    start_date?: string;
    discontinued_at?: string;
    discontinued_by?: User;
    discontinuation_reason?: string;
    schedule_pattern?: {
        day_1?: string[];
        day_2?: string[];
        subsequent?: string[];
        [key: string]: string[] | undefined;
    };
}

interface MedicationHistoryCardProps {
    prescription: Prescription;
    onConfigureTimes: (prescriptionId: number) => void;
    onReconfigureTimes: (prescriptionId: number) => void;
    onViewSchedule: (prescriptionId: number) => void;
    onDiscontinue: (prescriptionId: number, reason: string) => void;
}

export function MedicationHistoryCard({
    prescription,
    onConfigureTimes,
    onReconfigureTimes,
    onViewSchedule,
    onDiscontinue,
}: MedicationHistoryCardProps) {
    const isDiscontinued = !!prescription.discontinued_at;
    const hasPendingSchedule =
        !prescription.schedule_pattern && !isDiscontinued;
    const hasSchedule = !!prescription.schedule_pattern;

    const getStatusBadge = () => {
        if (isDiscontinued) {
            return (
                <Badge
                    variant="outline"
                    className="border-red-500 text-red-700 dark:border-red-600 dark:text-red-400"
                >
                    <XCircle className="mr-1 h-3 w-3" />
                    Discontinued
                </Badge>
            );
        }

        if (hasPendingSchedule) {
            return (
                <Badge
                    variant="outline"
                    className="border-orange-500 text-orange-700 dark:border-orange-600 dark:text-orange-400"
                >
                    <AlertTriangle className="mr-1 h-3 w-3" />
                    Pending Schedule
                </Badge>
            );
        }

        return (
            <Badge variant="default" className="bg-green-600 dark:bg-green-700">
                <Pill className="mr-1 h-3 w-3" />
                Active
            </Badge>
        );
    };

    return (
        <Card
            className={
                isDiscontinued
                    ? 'opacity-60 dark:opacity-50'
                    : hasPendingSchedule
                      ? 'border-orange-300 bg-orange-50/50 dark:border-orange-800 dark:bg-orange-950/10'
                      : ''
            }
        >
            <CardContent className="p-4">
                <div className="flex items-start justify-between gap-4">
                    <div className="flex-1 space-y-2">
                        {/* Drug Name and Status */}
                        <div className="flex items-start gap-2">
                            <div className="flex-1">
                                <h4 className="font-semibold text-gray-900 dark:text-gray-100">
                                    {prescription.drug?.name ||
                                        prescription.medication_name}
                                    {prescription.drug?.strength && (
                                        <span className="ml-1 text-sm font-normal text-muted-foreground">
                                            {prescription.drug.strength}
                                        </span>
                                    )}
                                </h4>
                            </div>
                            {getStatusBadge()}
                        </div>

                        {/* Prescription Details */}
                        <div className="grid grid-cols-2 gap-x-4 gap-y-1 text-sm text-muted-foreground md:grid-cols-4">
                            {prescription.frequency && (
                                <div className="flex items-center gap-1">
                                    <Clock className="h-3 w-3" />
                                    <span>{prescription.frequency}</span>
                                </div>
                            )}
                            {prescription.duration && (
                                <div className="flex items-center gap-1">
                                    <Calendar className="h-3 w-3" />
                                    <span>{prescription.duration}</span>
                                </div>
                            )}
                            {prescription.start_date && (
                                <div className="flex items-center gap-1">
                                    <span className="text-xs">Started:</span>
                                    <span>
                                        {format(
                                            new Date(prescription.start_date),
                                            'MMM d, yyyy',
                                        )}
                                    </span>
                                </div>
                            )}
                            {(prescription.dosage ||
                                prescription.dose_quantity) && (
                                <div className="flex items-center gap-1">
                                    <span className="text-xs">Dose:</span>
                                    <span>
                                        {prescription.dosage ||
                                            prescription.dose_quantity}
                                    </span>
                                </div>
                            )}
                        </div>

                        {/* Discontinued Information */}
                        {isDiscontinued && (
                            <div className="rounded-md bg-red-50 p-2 text-sm dark:bg-red-950/20">
                                <p className="font-medium text-red-900 dark:text-red-200">
                                    Discontinued on{' '}
                                    {format(
                                        new Date(prescription.discontinued_at!),
                                        'MMM d, yyyy HH:mm',
                                    )}
                                </p>
                                {prescription.discontinued_by && (
                                    <p className="text-red-700 dark:text-red-300">
                                        By: {prescription.discontinued_by.name}
                                    </p>
                                )}
                                {prescription.discontinuation_reason && (
                                    <p className="mt-1 text-red-700 dark:text-red-300">
                                        Reason:{' '}
                                        {prescription.discontinuation_reason}
                                    </p>
                                )}
                            </div>
                        )}

                        {/* Instructions */}
                        {prescription.instructions && (
                            <p className="text-sm text-muted-foreground italic">
                                {prescription.instructions}
                            </p>
                        )}
                    </div>

                    {/* Action Buttons */}
                    <div className="flex items-start gap-2">
                        {/* Prominent Configure Times Button for Pending Schedule */}
                        {hasPendingSchedule && (
                            <Button
                                size="sm"
                                onClick={() =>
                                    onConfigureTimes(prescription.id)
                                }
                                className="bg-orange-600 hover:bg-orange-700 dark:bg-orange-700 dark:hover:bg-orange-800"
                            >
                                <Settings className="mr-2 h-4 w-4" />
                                Configure Times
                            </Button>
                        )}

                        {/* Three-dot Menu */}
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="ghost" size="sm">
                                    <MoreVertical className="h-4 w-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                {hasSchedule && (
                                    <>
                                        <DropdownMenuItem
                                            onClick={() =>
                                                onViewSchedule(prescription.id)
                                            }
                                        >
                                            <Eye className="mr-2 h-4 w-4" />
                                            View Full Schedule
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            onClick={() =>
                                                onReconfigureTimes(
                                                    prescription.id,
                                                )
                                            }
                                        >
                                            <Settings className="mr-2 h-4 w-4" />
                                            Reconfigure Times
                                        </DropdownMenuItem>
                                    </>
                                )}
                                {hasPendingSchedule && (
                                    <DropdownMenuItem
                                        onClick={() =>
                                            onConfigureTimes(prescription.id)
                                        }
                                    >
                                        <Settings className="mr-2 h-4 w-4" />
                                        Configure Times
                                    </DropdownMenuItem>
                                )}
                                {!isDiscontinued && (
                                    <DropdownMenuItem
                                        onClick={() => {
                                            const reason = prompt(
                                                'Please provide a reason for discontinuing this medication:',
                                            );
                                            if (reason) {
                                                onDiscontinue(
                                                    prescription.id,
                                                    reason,
                                                );
                                            }
                                        }}
                                        className="text-red-600 focus:text-red-600 dark:text-red-400 dark:focus:text-red-400"
                                    >
                                        <XCircle className="mr-2 h-4 w-4" />
                                        Discontinue
                                    </DropdownMenuItem>
                                )}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
