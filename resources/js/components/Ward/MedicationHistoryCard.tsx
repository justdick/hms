import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { format } from 'date-fns';
import {
    Calendar,
    CheckCircle2,
    Clock,
    Pill,
    PlayCircle,
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
    created_at?: string;
    discontinued_at?: string;
    discontinued_by?: User;
    discontinuation_reason?: string;
    completed_at?: string;
    completed_by?: User;
    completion_reason?: string;
}

interface MedicationHistoryCardProps {
    prescription: Prescription;
    onDiscontinue: (prescriptionId: number) => void;
    onComplete: (prescriptionId: number) => void;
    onResume?: (prescriptionId: number) => void;
}

export function MedicationHistoryCard({
    prescription,
    onDiscontinue,
    onComplete,
    onResume,
}: MedicationHistoryCardProps) {
    const isDiscontinued = !!prescription.discontinued_at;
    const isCompleted = !!prescription.completed_at;
    const isInactive = isDiscontinued || isCompleted;

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

        if (isCompleted) {
            return (
                <Badge
                    variant="outline"
                    className="border-green-500 text-green-700 dark:border-green-600 dark:text-green-400"
                >
                    <CheckCircle2 className="mr-1 h-3 w-3" />
                    Completed
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
        <Card className={isInactive ? 'opacity-60 dark:opacity-50' : ''}>
            <CardContent className="p-4">
                <div className="flex items-start justify-between gap-4">
                    <div className="flex-1 space-y-2">
                        {/* Drug Name and Status */}
                        <div className="flex items-start gap-2">
                            <div className="flex-1">
                                <h4 className="font-semibold text-gray-900 dark:text-gray-100">
                                    {prescription.drug?.name ||
                                        prescription.medication_name}
                                    {(prescription.dose_quantity ||
                                        prescription.dosage) && (
                                        <span className="ml-1 text-sm font-normal text-muted-foreground">
                                            (
                                            {prescription.dose_quantity ||
                                                prescription.dosage}
                                            )
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
                            {prescription.created_at && (
                                <div className="flex items-center gap-1">
                                    <span className="text-xs">Prescribed:</span>
                                    <span>
                                        {format(
                                            new Date(prescription.created_at),
                                            'MMM d, yyyy',
                                        )}
                                    </span>
                                </div>
                            )}
                            {prescription.drug?.form && (
                                <div className="flex items-center gap-1">
                                    <span className="text-xs">Form:</span>
                                    <span>{prescription.drug.form}</span>
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

                        {/* Completed Information */}
                        {isCompleted && (
                            <div className="rounded-md bg-green-50 p-2 text-sm dark:bg-green-950/20">
                                <p className="font-medium text-green-900 dark:text-green-200">
                                    Completed on{' '}
                                    {format(
                                        new Date(prescription.completed_at!),
                                        'MMM d, yyyy HH:mm',
                                    )}
                                </p>
                                {prescription.completed_by && (
                                    <p className="text-green-700 dark:text-green-300">
                                        By: {prescription.completed_by.name}
                                    </p>
                                )}
                                {prescription.completion_reason && (
                                    <p className="mt-1 text-green-700 dark:text-green-300">
                                        Notes:{' '}
                                        {prescription.completion_reason}
                                    </p>
                                )}
                            </div>
                        )}

                        {/* Instructions */}
                        {prescription.instructions && (
                            <p className="text-sm text-amber-600 italic dark:text-amber-400">
                                {prescription.instructions}
                            </p>
                        )}
                    </div>

                    {/* Action Buttons */}
                    <div className="flex flex-col gap-2">
                        {isDiscontinued ? (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => onResume?.(prescription.id)}
                                className="h-8 border-green-500 text-green-600 hover:bg-green-50 hover:text-green-700 dark:border-green-600 dark:text-green-400 dark:hover:bg-green-950 dark:hover:text-green-300"
                            >
                                <PlayCircle className="mr-1 h-3.5 w-3.5" />
                                Resume
                            </Button>
                        ) : isCompleted ? (
                            <span className="text-sm text-muted-foreground">
                                Course finished
                            </span>
                        ) : (
                            <>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => onComplete(prescription.id)}
                                    className="h-8 border-green-500 text-green-600 hover:bg-green-50 hover:text-green-700 dark:border-green-600 dark:text-green-400 dark:hover:bg-green-950 dark:hover:text-green-300"
                                >
                                    <CheckCircle2 className="mr-1 h-3.5 w-3.5" />
                                    Complete
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => onDiscontinue(prescription.id)}
                                    className="h-8 border-red-500 text-red-600 hover:bg-red-50 hover:text-red-700 dark:border-red-600 dark:text-red-400 dark:hover:bg-red-950 dark:hover:text-red-300"
                                >
                                    <XCircle className="mr-1 h-3.5 w-3.5" />
                                    Discontinue
                                </Button>
                            </>
                        )}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
