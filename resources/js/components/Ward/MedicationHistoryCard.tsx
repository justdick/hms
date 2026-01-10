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
import { Calendar, Clock, MoreVertical, Pill, PlayCircle, XCircle } from 'lucide-react';

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
}

interface MedicationHistoryCardProps {
    prescription: Prescription;
    onDiscontinue: (prescriptionId: number) => void;
    onResume?: (prescriptionId: number) => void;
}

export function MedicationHistoryCard({
    prescription,
    onDiscontinue,
    onResume,
}: MedicationHistoryCardProps) {
    const isDiscontinued = !!prescription.discontinued_at;

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

        return (
            <Badge variant="default" className="bg-green-600 dark:bg-green-700">
                <Pill className="mr-1 h-3 w-3" />
                Active
            </Badge>
        );
    };

    return (
        <Card className={isDiscontinued ? 'opacity-60 dark:opacity-50' : ''}>
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
                                            ({prescription.dose_quantity ||
                                                prescription.dosage})
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

                        {/* Instructions */}
                        {prescription.instructions && (
                            <p className="text-sm text-amber-600 dark:text-amber-400 italic">
                                {prescription.instructions}
                            </p>
                        )}
                    </div>

                    {/* Action Buttons */}
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="sm">
                                <MoreVertical className="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            {isDiscontinued ? (
                                <DropdownMenuItem
                                    onClick={() =>
                                        onResume?.(prescription.id)
                                    }
                                    className="text-green-600 focus:text-green-600 dark:text-green-400 dark:focus:text-green-400"
                                >
                                    <PlayCircle className="mr-2 h-4 w-4" />
                                    Resume
                                </DropdownMenuItem>
                            ) : (
                                <DropdownMenuItem
                                    onClick={() =>
                                        onDiscontinue(prescription.id)
                                    }
                                    className="text-red-600 focus:text-red-600 dark:text-red-400 dark:focus:text-red-400"
                                >
                                    <XCircle className="mr-2 h-4 w-4" />
                                    Discontinue
                                </DropdownMenuItem>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </CardContent>
        </Card>
    );
}
