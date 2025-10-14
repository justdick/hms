'use client';

import { Link } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { ArrowUpDown, Calendar, Clock, Eye, Pill, User } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

interface Patient {
    id: number;
    first_name: string;
    last_name: string;
    patient_number: string;
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

interface Drug {
    id: number;
    name: string;
    form: string;
    unit_type: string;
}

export interface DispensingPrescription {
    id: number;
    consultation_id: number;
    consultation: Consultation | null;
    drug_id?: number;
    drug?: Drug;
    medication_name: string;
    dosage: string;
    frequency: string;
    duration: string;
    quantity: number;
    dosage_form?: string;
    instructions?: string;
    status: 'prescribed' | 'dispensed' | 'cancelled';
    created_at: string;
    updated_at: string;
}

const statusConfig = {
    prescribed: {
        label: 'Prescribed',
        className:
            'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
    },
    dispensed: {
        label: 'Dispensed',
        className:
            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    },
    cancelled: {
        label: 'Cancelled',
        className: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
    },
};

const formatDateTime = (dateString: string) => {
    const date = new Date(dateString);
    return {
        date: date.toLocaleDateString(),
        time: date.toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit',
        }),
    };
};

export const dispensingColumns: ColumnDef<DispensingPrescription>[] = [
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
                return (
                    <div className="text-muted-foreground">Unknown Patient</div>
                );
            }
            return (
                <div className="space-y-1">
                    <div className="font-medium">
                        {patient.first_name} {patient.last_name}
                    </div>
                    <div className="flex items-center gap-1 text-sm text-muted-foreground">
                        <User className="h-3 w-3" />
                        {patient.patient_number}
                    </div>
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
        accessorKey: 'medication_name',
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                >
                    Medication
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            const prescription = row.original;
            const drugName =
                prescription.drug?.name || prescription.medication_name;
            return (
                <div className="space-y-1">
                    <div className="flex items-center gap-2 font-medium">
                        <Pill className="h-4 w-4 text-blue-600" />
                        {drugName}
                    </div>
                    <div className="text-sm text-muted-foreground">
                        {prescription.drug?.form && (
                            <span className="inline-flex items-center gap-1">
                                {prescription.drug.form}
                                {prescription.dosage &&
                                    ` • ${prescription.dosage}`}
                            </span>
                        )}
                    </div>
                </div>
            );
        },
    },
    {
        id: 'prescription_details',
        header: 'Prescription Details',
        cell: ({ row }) => {
            const prescription = row.original;
            return (
                <div className="space-y-1 text-sm">
                    <div className="flex items-center gap-2">
                        <span className="font-medium">Qty:</span>
                        <Badge variant="outline" className="text-xs">
                            {prescription.quantity}{' '}
                            {prescription.drug?.unit_type || 'units'}
                        </Badge>
                    </div>
                    <div className="text-muted-foreground">
                        <div>{prescription.frequency}</div>
                        <div>Duration: {prescription.duration}</div>
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
            const status = row.getValue(
                'status',
            ) as DispensingPrescription['status'];
            const config = statusConfig[status];

            return <Badge className={config.className}>{config.label}</Badge>;
        },
        filterFn: (row, id, value) => {
            return value.includes(row.getValue(id));
        },
    },
    {
        accessorKey: 'created_at',
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                >
                    Prescribed
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            const { date, time } = formatDateTime(row.getValue('created_at'));
            return (
                <div className="space-y-1 text-sm">
                    <div className="flex items-center gap-1 text-muted-foreground">
                        <Calendar className="h-3 w-3" />
                        {date}
                    </div>
                    <div className="flex items-center gap-1 text-muted-foreground">
                        <Clock className="h-3 w-3" />
                        {time}
                    </div>
                </div>
            );
        },
    },
    {
        id: 'actions',
        enableHiding: false,
        cell: ({ row }) => {
            const prescription = row.original;

            return (
                <Button
                    size="sm"
                    asChild
                    disabled={prescription.status !== 'prescribed'}
                >
                    <Link
                        href={`/pharmacy/prescriptions/${prescription.id}/dispense`}
                    >
                        <Eye className="mr-1 h-3 w-3" />
                        {prescription.status === 'prescribed'
                            ? 'Dispense'
                            : 'View'}
                    </Link>
                </Button>
            );
        },
    },
];
