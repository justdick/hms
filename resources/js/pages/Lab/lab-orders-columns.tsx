'use client';

import { Link } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { ArrowUpDown, Calendar, Clock, Eye, User } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    AlertCircle,
    CheckCircle,
    FileText,
    TestTube,
    Timer,
} from 'lucide-react';

interface Patient {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
}

interface PatientCheckin {
    patient: Patient;
}

interface Consultation {
    id: number;
    patient_checkin: PatientCheckin;
    chief_complaint: string;
    created_at: string;
}

interface LabService {
    id: number;
    name: string;
    code: string;
    category: string;
    sample_type: string;
    turnaround_time: string;
}

interface User {
    id: number;
    name: string;
}

export interface LabOrder {
    id: number;
    consultation_id: number;
    consultation: Consultation;
    lab_service_id: number;
    lab_service: LabService;
    ordered_by: number;
    ordered_by_user: User;
    ordered_at: string;
    status:
        | 'ordered'
        | 'sample_collected'
        | 'in_progress'
        | 'completed'
        | 'cancelled';
    priority: 'routine' | 'urgent' | 'stat';
    special_instructions?: string;
    sample_collected_at?: string;
    result_entered_at?: string;
    result_values?: any;
    result_notes?: string;
}

const statusConfig = {
    ordered: {
        label: 'Ordered',
        icon: FileText,
        className:
            'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
    },
    sample_collected: {
        label: 'Sample Collected',
        icon: TestTube,
        className:
            'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
    },
    in_progress: {
        label: 'In Progress',
        icon: Timer,
        className:
            'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300',
    },
    completed: {
        label: 'Completed',
        icon: CheckCircle,
        className:
            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    },
    cancelled: {
        label: 'Cancelled',
        icon: AlertCircle,
        className: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
    },
};

const priorityConfig = {
    routine: {
        label: 'Routine',
        className:
            'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
    },
    urgent: {
        label: 'Urgent',
        className:
            'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300',
    },
    stat: {
        label: 'STAT',
        className: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
    },
};

const formatDateTime = (dateString: string) => {
    return new Date(dateString).toLocaleString();
};

export const labOrderColumns: ColumnDef<LabOrder>[] = [
    {
        id: 'patient',
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                >
                    Patient
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            const patient = row.original.consultation?.patient_checkin?.patient;
            if (!patient) {
                return <div className="text-muted-foreground">N/A</div>;
            }
            return (
                <div className="space-y-1">
                    <div className="font-medium">
                        {patient.first_name} {patient.last_name}
                    </div>
                    {patient.phone_number && (
                        <div className="flex items-center gap-1 text-sm text-muted-foreground">
                            <User className="h-3 w-3" />
                            {patient.phone_number}
                        </div>
                    )}
                </div>
            );
        },
        sortingFn: (rowA, rowB) => {
            const patientA =
                rowA.original.consultation?.patient_checkin?.patient;
            const patientB =
                rowB.original.consultation?.patient_checkin?.patient;
            if (!patientA || !patientB) return 0;
            const nameA = `${patientA.first_name} ${patientA.last_name}`;
            const nameB = `${patientB.first_name} ${patientB.last_name}`;
            return nameA.localeCompare(nameB);
        },
    },
    {
        accessorKey: 'lab_service.name',
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                >
                    Test
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            const service = row.original.lab_service;
            return (
                <div className="space-y-1">
                    <div className="font-medium">{service.name}</div>
                    <div className="text-sm text-muted-foreground">
                        <code className="rounded bg-muted px-1 text-xs">
                            {service.code}
                        </code>
                        {' • '}
                        {service.category}
                    </div>
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
            const status = row.getValue('status') as LabOrder['status'];
            const config = statusConfig[status];
            const Icon = config.icon;

            return (
                <Badge className={config.className}>
                    <Icon className="mr-1 h-3 w-3" />
                    {config.label}
                </Badge>
            );
        },
        filterFn: (row, id, value) => {
            return value.includes(row.getValue(id));
        },
    },
    {
        accessorKey: 'priority',
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                >
                    Priority
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            const priority = row.getValue('priority') as LabOrder['priority'];
            const config = priorityConfig[priority];

            return (
                <Badge variant="outline" className={config.className}>
                    {config.label}
                </Badge>
            );
        },
        filterFn: (row, id, value) => {
            return value.includes(row.getValue(id));
        },
    },
    {
        accessorKey: 'lab_service.category',
        header: 'Category',
        cell: ({ row }) => {
            return (
                <Badge variant="outline" className="text-xs">
                    {row.getValue('lab_service.category')}
                </Badge>
            );
        },
        filterFn: (row, id, value) => {
            return value.includes(row.getValue(id));
        },
    },
    {
        id: 'sample_type',
        header: 'Sample',
        cell: ({ row }) => {
            return (
                <div className="text-sm">
                    {row.original.lab_service.sample_type}
                </div>
            );
        },
    },
    {
        accessorKey: 'ordered_at',
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                >
                    Ordered
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            return (
                <div className="flex items-center gap-1 text-sm text-muted-foreground">
                    <Calendar className="h-3 w-3" />
                    {formatDateTime(row.getValue('ordered_at'))}
                </div>
            );
        },
    },
    {
        id: 'turnaround',
        header: 'Turnaround',
        cell: ({ row }) => {
            return (
                <div className="flex items-center gap-1 text-sm text-muted-foreground">
                    <Clock className="h-3 w-3" />
                    {row.original.lab_service.turnaround_time}
                </div>
            );
        },
    },
    {
        id: 'actions',
        enableHiding: false,
        cell: ({ row }) => {
            const order = row.original;

            return (
                <Button size="sm" asChild>
                    <Link href={`/lab/orders/${order.id}`}>
                        <Eye className="mr-1 h-3 w-3" />
                        Process
                    </Link>
                </Button>
            );
        },
    },
];
