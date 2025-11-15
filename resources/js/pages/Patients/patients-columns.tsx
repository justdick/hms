'use client';

import { Link } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import {
    ArrowUpDown,
    Calendar,
    CreditCard,
    Eye,
    Stethoscope,
    User,
} from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

export interface PatientData {
    id: number;
    patient_number: string;
    full_name: string;
    first_name: string;
    last_name: string;
    age: number;
    gender: string;
    phone_number: string | null;
    date_of_birth: string;
    address: string | null;
    status: string;
    active_insurance: {
        id: number;
        insurance_plan: {
            id: number;
            name: string;
        };
        membership_id: string;
        coverage_start_date: string;
        coverage_end_date: string | null;
    } | null;
    recent_checkin: {
        id: number;
        checked_in_at: string;
        status: string;
    } | null;
}

const getGenderBadgeColor = (gender: string) => {
    switch (gender.toLowerCase()) {
        case 'male':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300';
        case 'female':
            return 'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-300';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300';
    }
};

const getCheckinStatusBadge = (status: string) => {
    const statusConfig: Record<
        string,
        { label: string; className: string }
    > = {
        checked_in: {
            label: 'Checked In',
            className:
                'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
        },
        vitals_taken: {
            label: 'Vitals Taken',
            className:
                'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
        },
        awaiting_consultation: {
            label: 'Awaiting Consultation',
            className:
                'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
        },
        in_consultation: {
            label: 'In Consultation',
            className:
                'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        },
    };

    return (
        statusConfig[status] || {
            label: status,
            className:
                'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300',
        }
    );
};

const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleDateString();
};

const formatDateTime = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleString();
};

export const patientsColumns: ColumnDef<PatientData>[] = [
    {
        accessorKey: 'patient_number',
        id: 'patient_number',
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                >
                    Patient #
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            return (
                <div className="font-mono text-sm font-medium">
                    {row.original.patient_number}
                </div>
            );
        },
    },
    {
        accessorKey: 'full_name',
        id: 'full_name',
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                >
                    Patient Name
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            const patient = row.original;
            return (
                <div className="space-y-1">
                    <div className="flex items-center gap-2 font-medium">
                        <User className="h-4 w-4 text-blue-600" />
                        {patient.full_name}
                    </div>
                    <div className="text-sm text-muted-foreground">
                        {patient.age} years • {patient.gender}
                    </div>
                </div>
            );
        },
    },
    {
        accessorKey: 'gender',
        id: 'gender',
        header: 'Gender',
        cell: ({ row }) => {
            const gender = row.original.gender;
            return (
                <Badge className={getGenderBadgeColor(gender)}>
                    {gender.charAt(0).toUpperCase() + gender.slice(1)}
                </Badge>
            );
        },
        filterFn: (row, id, value) => {
            return value.includes(row.getValue(id));
        },
    },
    {
        accessorKey: 'phone_number',
        id: 'phone_number',
        header: 'Contact',
        cell: ({ row }) => {
            const phone = row.original.phone_number;
            return (
                <div className="text-sm">
                    {phone || (
                        <span className="text-muted-foreground">
                            No phone
                        </span>
                    )}
                </div>
            );
        },
    },
    {
        accessorKey: 'date_of_birth',
        id: 'date_of_birth',
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                >
                    Date of Birth
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            return (
                <div className="flex items-center gap-1 text-sm">
                    <Calendar className="h-3 w-3 text-muted-foreground" />
                    {formatDate(row.original.date_of_birth)}
                </div>
            );
        },
    },
    {
        id: 'insurance',
        header: 'Insurance',
        cell: ({ row }) => {
            const insurance = row.original.active_insurance;
            if (!insurance) {
                return (
                    <span className="text-sm text-muted-foreground">
                        No insurance
                    </span>
                );
            }

            return (
                <div className="space-y-1">
                    <div className="flex items-center gap-1 text-sm font-medium">
                        <CreditCard className="h-3 w-3 text-green-600" />
                        {insurance.insurance_plan.name}
                    </div>
                    <div className="text-xs text-muted-foreground">
                        {insurance.membership_id}
                    </div>
                </div>
            );
        },
    },
    {
        id: 'recent_checkin',
        header: 'Recent Check-in',
        cell: ({ row }) => {
            const checkin = row.original.recent_checkin;
            if (!checkin) {
                return (
                    <span className="text-sm text-muted-foreground">
                        No active check-in
                    </span>
                );
            }

            const statusConfig = getCheckinStatusBadge(checkin.status);

            return (
                <div className="space-y-1">
                    <Badge className={statusConfig.className}>
                        {statusConfig.label}
                    </Badge>
                    <div className="flex items-center gap-1 text-xs text-muted-foreground">
                        <Stethoscope className="h-3 w-3" />
                        {formatDateTime(checkin.checked_in_at)}
                    </div>
                </div>
            );
        },
    },
    {
        id: 'actions',
        enableHiding: false,
        cell: ({ row }) => {
            const patient = row.original;

            return (
                <div className="flex items-center gap-1">
                    <Button size="sm" variant="ghost" asChild>
                        <Link href={`/patients/${patient.id}`}>
                            <Eye className="mr-1 h-3 w-3" />
                            View
                        </Link>
                    </Button>
                </div>
            );
        },
    },
];
