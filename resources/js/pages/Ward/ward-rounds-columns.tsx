'use client';

import { Link } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { ArrowUpDown, Calendar, CheckCircle, Clock } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

interface Doctor {
    id: number;
    name: string;
}

export interface WardRound {
    id: number;
    patient_admission_id: number;
    doctor_id: number;
    doctor?: Doctor;
    day_number: number;
    round_type: string;
    status: 'in_progress' | 'completed';
    round_datetime: string;
    presenting_complaint?: string;
    history_presenting_complaint?: string;
    on_direct_questioning?: string;
    examination_findings?: string;
    assessment_notes?: string;
    plan_notes?: string;
    created_at: string;
    updated_at: string;
}

const statusConfig = {
    in_progress: {
        label: 'In Progress',
        className:
            'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
    },
    completed: {
        label: 'Completed',
        className:
            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    },
};


const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

export const wardRoundsColumns = (
    admissionId: number,
    onViewWardRound?: (wardRound: WardRound) => void,
    canUpdateWardRound?: boolean,
): ColumnDef<WardRound>[] => [
    {
        accessorKey: 'day_number',
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                >
                    Day
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            return (
                <div className="text-center font-semibold text-gray-900 dark:text-gray-100">
                    Day {row.getValue('day_number')}
                </div>
            );
        },
    },
    {
        accessorKey: 'round_datetime',
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                >
                    Date/Time
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            return (
                <div className="flex items-center gap-1 text-sm">
                    <Calendar className="h-3 w-3 text-muted-foreground" />
                    <span>
                        {formatDateTime(row.getValue('round_datetime'))}
                    </span>
                </div>
            );
        },
    },
    {
        id: 'doctor',
        header: 'Doctor',
        cell: ({ row }) => {
            const doctor = row.original.doctor;
            return (
                <div className="text-sm">
                    {doctor ? `Dr. ${doctor.name}` : 'N/A'}
                </div>
            );
        },
    },
    {
        accessorKey: 'status',
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                >
                    Status
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            const status = row.getValue('status') as WardRound['status'];
            const config = statusConfig[status];

            return (
                <Badge className={config.className}>
                    {status === 'completed' && (
                        <CheckCircle className="mr-1 h-3 w-3" />
                    )}
                    {status === 'in_progress' && (
                        <Clock className="mr-1 h-3 w-3" />
                    )}
                    {config.label}
                </Badge>
            );
        },
        filterFn: (row, id, value) => {
            return value.includes(row.getValue(id));
        },
    },
    {
        id: 'actions',
        enableHiding: false,
        cell: ({ row }) => {
            const wardRound = row.original;
            const isInProgress = wardRound.status === 'in_progress';

            return (
                <div className="flex items-center gap-2">
                    {isInProgress && canUpdateWardRound && (
                        <Button size="sm" variant="outline" asChild>
                            <Link
                                href={`/admissions/${admissionId}/ward-rounds/${wardRound.id}/edit`}
                            >
                                Continue
                            </Link>
                        </Button>
                    )}
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() => onViewWardRound?.(wardRound)}
                    >
                        View
                    </Button>
                </div>
            );
        },
    },
];
