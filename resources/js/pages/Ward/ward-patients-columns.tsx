'use client';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { VitalsStatusBadge } from '@/components/Ward/VitalsStatusBadge';
import { Link, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import {
    AlertCircle,
    ArrowUpDown,
    Bed,
    Calendar,
    Eye,
    MoreHorizontal,
    Pill,
    Plus,
    RefreshCw,
    ShieldCheck,
    Stethoscope,
    X,
} from 'lucide-react';

interface InsuranceProvider {
    id: number;
    name: string;
}

interface InsurancePlan {
    id: number;
    plan_name: string;
    plan_type: string;
    provider: InsuranceProvider;
}

interface PatientInsurance {
    id: number;
    member_number: string;
    status: string;
    coverage_start_date: string;
    coverage_end_date?: string;
    plan: InsurancePlan;
}

interface Patient {
    id: number;
    first_name: string;
    last_name: string;
    date_of_birth?: string;
    gender?: string;
    patient_number: string;
    active_insurance?: PatientInsurance;
}

interface Doctor {
    id: number;
    name: string;
}

interface BedData {
    id: number;
    bed_number: string;
}

interface Consultation {
    id: number;
    doctor: Doctor;
}

interface VitalsSchedule {
    id: number;
    patient_admission_id: number;
    interval_minutes: number;
    next_due_at: string;
    last_recorded_at?: string;
    is_active: boolean;
}

interface MedicationAdministration {
    id: number;
    administered_at: string;
    status: string;
}

export interface WardPatientData {
    id: number;
    admission_number: string;
    patient: Patient;
    status: string;
    admitted_at: string;
    bed_id?: number;
    bed?: BedData;
    consultation?: Consultation;
    latest_vital_signs?: any[];
    today_medication_administrations?: MedicationAdministration[];
    ward_rounds_count?: number;
    nursing_notes_count?: number;
    vitals_schedule?: VitalsSchedule;
    wardId?: number;
}

const formatDateTime = (dateString: string) => {
    return new Date(dateString).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const isVitalsOverdue = (admission: WardPatientData) => {
    const latestVital = admission.latest_vital_signs?.[0];
    if (!latestVital) return true;
    return (
        new Date(latestVital.recorded_at) <
        new Date(Date.now() - 4 * 60 * 60 * 1000)
    );
};

const calculateVitalsStatus = (
    schedule: VitalsSchedule,
): 'upcoming' | 'due' | 'overdue' => {
    const now = new Date();
    const nextDue = new Date(schedule.next_due_at);
    const diffMinutes = Math.floor(
        (nextDue.getTime() - now.getTime()) / (1000 * 60),
    );
    const GRACE_PERIOD_MINUTES = 15;

    if (diffMinutes > GRACE_PERIOD_MINUTES) {
        return 'upcoming';
    } else if (diffMinutes >= -GRACE_PERIOD_MINUTES) {
        return 'due';
    } else {
        return 'overdue';
    }
};

export const createWardPatientsColumns = (
    wardId: number,
    bedManagementEnabled: boolean = true,
): ColumnDef<WardPatientData>[] => {
    const columns: ColumnDef<WardPatientData>[] = [
    {
        accessorKey: 'patient',
        id: 'patient',
        header: ({ column }) => (
            <Button
                variant="ghost"
                onClick={() =>
                    column.toggleSorting(column.getIsSorted() === 'asc')
                }
            >
                Patient
                <ArrowUpDown className="ml-2 h-4 w-4" />
            </Button>
        ),
        cell: ({ row }) => {
            const admission = row.original;
            return (
                <Link
                    href={`/wards/${wardId}/patients/${admission.id}`}
                    className="block"
                >
                    <div className="font-medium text-gray-900 dark:text-gray-100">
                        {admission.patient.first_name}{' '}
                        {admission.patient.last_name}
                    </div>
                    {admission.patient.gender && (
                        <div className="text-sm text-gray-500 dark:text-gray-400">
                            {admission.patient.gender}
                            {admission.patient.date_of_birth && (
                                <>
                                    {' '}
                                    •{' '}
                                    {new Date(
                                        admission.patient.date_of_birth,
                                    ).toLocaleDateString()}
                                </>
                            )}
                        </div>
                    )}
                </Link>
            );
        },
        sortingFn: (rowA, rowB) => {
            const nameA =
                `${rowA.original.patient.first_name} ${rowA.original.patient.last_name}`.toLowerCase();
            const nameB =
                `${rowB.original.patient.first_name} ${rowB.original.patient.last_name}`.toLowerCase();
            return nameA.localeCompare(nameB);
        },
    },
    {
        accessorKey: 'patient.patient_number',
        id: 'folder_number',
        header: ({ column }) => (
            <Button
                variant="ghost"
                onClick={() =>
                    column.toggleSorting(column.getIsSorted() === 'asc')
                }
            >
                Folder #
                <ArrowUpDown className="ml-2 h-4 w-4" />
            </Button>
        ),
        cell: ({ row }) => (
            <Link
                href={`/wards/${wardId}/patients/${row.original.id}`}
                className="block font-mono text-sm text-gray-700 dark:text-gray-300"
            >
                {row.original.patient.patient_number}
            </Link>
        ),
    },
    {
        accessorKey: 'doctor',
        id: 'doctor',
        header: 'Doctor',
        cell: ({ row }) => {
            const admission = row.original;
            return (
                <Link
                    href={`/wards/${wardId}/patients/${admission.id}`}
                    className="block"
                >
                    {admission.consultation?.doctor ? (
                        <div className="flex items-center gap-2 text-sm">
                            <Stethoscope className="h-4 w-4 text-gray-500 dark:text-gray-400" />
                            <span>
                                Dr. {admission.consultation.doctor.name}
                            </span>
                        </div>
                    ) : (
                        <span className="text-sm text-gray-500 dark:text-gray-400">
                            Not assigned
                        </span>
                    )}
                </Link>
            );
        },
    },
    {
        accessorKey: 'insurance',
        id: 'insurance',
        header: 'Insurance',
        cell: ({ row }) => {
            const admission = row.original;
            const insurance = admission.patient.active_insurance;
            return (
                <Link
                    href={`/wards/${wardId}/patients/${admission.id}`}
                    className="block"
                >
                    {insurance?.plan?.provider ? (
                        <div className="flex items-center gap-1 text-sm">
                            <ShieldCheck className="h-4 w-4 text-green-600 dark:text-green-400" />
                            <div className="flex flex-col">
                                <span className="font-medium text-gray-900 dark:text-gray-100">
                                    {insurance.plan.provider.name}
                                </span>
                                <span className="text-xs text-gray-500 dark:text-gray-400">
                                    {insurance.plan.plan_name}
                                </span>
                            </div>
                        </div>
                    ) : (
                        <span className="text-sm text-gray-500 dark:text-gray-400">
                            None
                        </span>
                    )}
                </Link>
            );
        },
    },
    {
        accessorKey: 'admitted_at',
        id: 'admitted_at',
        header: ({ column }) => (
            <Button
                variant="ghost"
                onClick={() =>
                    column.toggleSorting(column.getIsSorted() === 'asc')
                }
            >
                Admitted
                <ArrowUpDown className="ml-2 h-4 w-4" />
            </Button>
        ),
        cell: ({ row }) => (
            <Link
                href={`/wards/${wardId}/patients/${row.original.id}`}
                className="block text-sm text-gray-600 dark:text-gray-400"
            >
                <div className="flex items-center gap-1">
                    <Calendar className="h-3 w-3" />
                    {formatDateTime(row.original.admitted_at)}
                </div>
            </Link>
        ),
    },
    {
        accessorKey: 'status',
        id: 'status',
        header: 'Status',
        cell: ({ row }) => (
            <Link
                href={`/wards/${wardId}/patients/${row.original.id}`}
                className="block"
            >
                <Badge variant="default">
                    {row.original.status.replace('_', ' ').toUpperCase()}
                </Badge>
            </Link>
        ),
        filterFn: (row, id, value) => {
            return value.includes(row.getValue(id));
        },
    },
    {
        accessorKey: 'vitals_schedule',
        id: 'vitals_status',
        header: 'Vitals Status',
        cell: ({ row }) => {
            const admission = row.original;
            return admission.vitals_schedule ? (
                <VitalsStatusBadge
                    schedule={admission.vitals_schedule}
                    admissionId={admission.id}
                    wardId={wardId}
                />
            ) : (
                <span className="text-sm text-gray-400 dark:text-gray-600">
                    No schedule
                </span>
            );
        },
    },
    {
        id: 'alerts',
        header: () => <div className="text-center">Alerts</div>,
        cell: ({ row }) => {
            const admission = row.original;
            const vitalsOverdue = isVitalsOverdue(admission);
            const hasTodayMeds =
                admission.today_medication_administrations &&
                admission.today_medication_administrations.length > 0;
            const givenCount =
                admission.today_medication_administrations?.filter(
                    (m) => m.status === 'given',
                ).length || 0;

            return (
                <Link
                    href={`/wards/${wardId}/patients/${admission.id}`}
                    className="block"
                >
                    <div className="flex items-center justify-center gap-2">
                        {vitalsOverdue && (
                            <div
                                className="flex items-center gap-1 text-xs text-yellow-600 dark:text-yellow-400"
                                title="Vitals overdue"
                            >
                                <AlertCircle className="h-4 w-4" />
                                <span className="hidden xl:inline">Vitals</span>
                            </div>
                        )}
                        {hasTodayMeds && (
                            <div
                                className="flex items-center gap-1 text-xs text-green-600 dark:text-green-400"
                                title={`${givenCount} medication(s) given today`}
                            >
                                <Pill className="h-4 w-4" />
                                <span className="hidden xl:inline">
                                    {givenCount}
                                </span>
                            </div>
                        )}
                        {!vitalsOverdue && !hasTodayMeds && (
                            <span className="text-xs text-gray-400 dark:text-gray-600">
                                -
                            </span>
                        )}
                    </div>
                </Link>
            );
        },
    },
    {
        id: 'actions',
        enableHiding: false,
        cell: ({ row }) => {
            const admission = row.original;
            return (
                <Button size="sm" variant="ghost" asChild>
                    <Link href={`/wards/${wardId}/patients/${admission.id}`}>
                        <Eye className="mr-1 h-3 w-3" />
                        View
                    </Link>
                </Button>
            );
        },
    },
];

    // Add bed column only if bed management is enabled
    if (bedManagementEnabled) {
        columns.splice(2, 0, {
            accessorKey: 'bed',
            id: 'bed',
            header: 'Bed',
            cell: ({ row, table }) => {
                const admission = row.original;
                const onBedAction = (table.options.meta as any)?.onBedAction;

                const handleRemoveBed = () => {
                    if (
                        confirm(
                            'Are you sure you want to remove the bed assignment?',
                        )
                    ) {
                        router.delete(
                            `/admissions/${admission.id}/bed-assignment`,
                            {
                                preserveScroll: true,
                            },
                        );
                    }
                };

                return (
                    <div className="flex items-center gap-2">
                        {admission.bed ? (
                            <>
                                <Bed className="h-4 w-4 text-gray-500 dark:text-gray-400" />
                                <span className="font-medium">
                                    {admission.bed.bed_number}
                                </span>
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="h-6 w-6 p-0"
                                        >
                                            <MoreHorizontal className="h-4 w-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem
                                            onClick={() =>
                                                onBedAction?.(admission, 'change')
                                            }
                                        >
                                            <RefreshCw className="mr-2 h-4 w-4" />
                                            Change Bed
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            onClick={handleRemoveBed}
                                            className="text-red-600 focus:text-red-600"
                                        >
                                            <X className="mr-2 h-4 w-4" />
                                            Remove Bed
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </>
                        ) : (
                            <Button
                                variant="outline"
                                size="sm"
                                className="h-7 text-xs"
                                onClick={() => onBedAction?.(admission, 'assign')}
                            >
                                <Plus className="mr-1 h-3 w-3" />
                                Assign
                            </Button>
                        )}
                    </div>
                );
            },
        });
    }

    return columns;
};
