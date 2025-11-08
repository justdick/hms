import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { ColumnDef } from '@tanstack/react-table';
import { format } from 'date-fns';
import {
    AlertTriangle,
    ArrowUpDown,
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

interface ColumnActions {
    onConfigureTimes: (prescriptionId: number) => void;
    onReconfigureTimes: (prescriptionId: number) => void;
    onViewSchedule: (prescriptionId: number) => void;
    onDiscontinue: (prescriptionId: number, reason: string) => void;
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
                        {prescription.drug?.name || prescription.medication_name}
                    </div>
                    {prescription.drug?.strength && (
                        <div className="text-sm text-muted-foreground">
                            {prescription.drug.strength}
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
                <div className="text-sm">
                    {row.original.frequency || '-'}
                </div>
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
        accessorKey: 'start_date',
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                    className="hover:bg-transparent"
                >
                    Start Date
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            const startDate = row.original.start_date;
            return (
                <div className="text-sm">
                    {startDate
                        ? format(new Date(startDate), 'MMM d, yyyy')
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
            const hasPendingSchedule =
                !prescription.schedule_pattern && !isDiscontinued;

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
            const hasPendingSchedule =
                !prescription.schedule_pattern && !isDiscontinued;
            const hasSchedule = !!prescription.schedule_pattern;

            if (hasPendingSchedule) {
                return (
                    <div className="flex items-center gap-2">
                        <Button
                            size="sm"
                            onClick={() =>
                                actions.onConfigureTimes(prescription.id)
                            }
                            className="bg-orange-600 hover:bg-orange-700 dark:bg-orange-700 dark:hover:bg-orange-800"
                        >
                            <Settings className="mr-2 h-4 w-4" />
                            Configure Times
                        </Button>
                    </div>
                );
            }

            return (
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
                                        actions.onViewSchedule(prescription.id)
                                    }
                                >
                                    <Eye className="mr-2 h-4 w-4" />
                                    View Full Schedule
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onClick={() =>
                                        actions.onReconfigureTimes(
                                            prescription.id,
                                        )
                                    }
                                >
                                    <Settings className="mr-2 h-4 w-4" />
                                    Reconfigure Times
                                </DropdownMenuItem>
                            </>
                        )}
                        {!isDiscontinued && (
                            <DropdownMenuItem
                                onClick={() => {
                                    const reason = prompt(
                                        'Please provide a reason for discontinuing this medication:',
                                    );
                                    if (reason) {
                                        actions.onDiscontinue(
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
            );
        },
    },
];
