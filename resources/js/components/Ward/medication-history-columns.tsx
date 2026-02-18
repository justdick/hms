import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ColumnDef } from '@tanstack/react-table';
import { format } from 'date-fns';
import {
    ArrowUpDown,
    CheckCircle2,
    Pill,
    PlayCircle,
    RotateCcw,
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

export interface MedicationHistoryRow {
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
    prescribed_at?: string;
    created_at?: string;
    discontinued_at?: string;
    discontinued_by?: User;
    discontinuation_reason?: string;
    completed_at?: string;
    completed_by?: User;
    completion_reason?: string;
}

interface ColumnActions {
    onDiscontinue: (prescriptionId: number) => void;
    onComplete: (prescriptionId: number) => void;
    onResume?: (prescriptionId: number) => void;
    onUncomplete?: (prescriptionId: number) => void;
}

export const medicationHistoryColumns = (
    actions: ColumnActions,
): ColumnDef<MedicationHistoryRow>[] => [
    {
        accessorKey: 'medication_name',
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                    className="hover:bg-transparent"
                >
                    Medication
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            const prescription = row.original;
            return (
                <div className="flex flex-col gap-1">
                    <div className="font-medium">
                        {prescription.drug?.name ||
                            prescription.medication_name}
                    </div>
                    {(prescription.dose_quantity || prescription.dosage) && (
                        <div className="text-sm text-muted-foreground">
                            Dose:{' '}
                            {prescription.dose_quantity || prescription.dosage}
                        </div>
                    )}
                    {prescription.instructions && (
                        <div className="text-xs text-amber-600 italic dark:text-amber-400">
                            {prescription.instructions}
                        </div>
                    )}
                </div>
            );
        },
    },
    {
        accessorKey: 'frequency',
        header: 'Frequency',
        cell: ({ row }) => {
            return (
                <div className="text-sm">{row.original.frequency || '-'}</div>
            );
        },
    },
    {
        accessorKey: 'duration',
        header: 'Duration',
        cell: ({ row }) => {
            return (
                <div className="text-sm">{row.original.duration || '-'}</div>
            );
        },
    },
    {
        id: 'prescribed_at',
        accessorFn: (row) => row.prescribed_at || row.created_at,
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                    className="hover:bg-transparent"
                >
                    Prescribed At
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            const prescribedAt = row.original.prescribed_at || row.original.created_at;
            return (
                <div className="text-sm">
                    {prescribedAt
                        ? format(new Date(prescribedAt), 'MMM d, yyyy')
                        : '-'}
                </div>
            );
        },
    },
    {
        id: 'status',
        header: 'Status',
        cell: ({ row }) => {
            const prescription = row.original;
            const isDiscontinued = !!prescription.discontinued_at;
            const isCompleted = !!prescription.completed_at;

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
                <Badge
                    variant="default"
                    className="bg-green-600 dark:bg-green-700"
                >
                    <Pill className="mr-1 h-3 w-3" />
                    Active
                </Badge>
            );
        },
    },
    {
        id: 'actions',
        header: 'Actions',
        cell: ({ row }) => {
            const prescription = row.original;
            const isDiscontinued = !!prescription.discontinued_at;
            const isCompleted = !!prescription.completed_at;

            if (isDiscontinued) {
                return (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => actions.onResume?.(prescription.id)}
                        className="h-8 border-green-500 text-green-600 hover:bg-green-50 hover:text-green-700 dark:border-green-600 dark:text-green-400 dark:hover:bg-green-950 dark:hover:text-green-300"
                    >
                        <PlayCircle className="mr-1 h-3.5 w-3.5" />
                        Resume
                    </Button>
                );
            }

            if (isCompleted) {
                return (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => actions.onUncomplete?.(prescription.id)}
                        className="h-8 border-amber-500 text-amber-600 hover:bg-amber-50 hover:text-amber-700 dark:border-amber-600 dark:text-amber-400 dark:hover:bg-amber-950 dark:hover:text-amber-300"
                    >
                        <RotateCcw className="mr-1 h-3.5 w-3.5" />
                        Undo
                    </Button>
                );
            }

            return (
                <div className="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => actions.onComplete(prescription.id)}
                        className="h-8 border-green-500 text-green-600 hover:bg-green-50 hover:text-green-700 dark:border-green-600 dark:text-green-400 dark:hover:bg-green-950 dark:hover:text-green-300"
                    >
                        <CheckCircle2 className="mr-1 h-3.5 w-3.5" />
                        Complete
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => actions.onDiscontinue(prescription.id)}
                        className="h-8 border-red-500 text-red-600 hover:bg-red-50 hover:text-red-700 dark:border-red-600 dark:text-red-400 dark:hover:bg-red-950 dark:hover:text-red-300"
                    >
                        <XCircle className="mr-1 h-3.5 w-3.5" />
                        Discontinue
                    </Button>
                </div>
            );
        },
    },
];
