import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { TooltipProvider } from '@/components/ui/tooltip';
import { VitalsStatusBadge } from '@/components/Ward/VitalsStatusBadge';
import { Link } from '@inertiajs/react';
import {
    AlertCircle,
    Bed,
    Calendar,
    Pill,
    ShieldCheck,
    Stethoscope,
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
    active_insurance?: PatientInsurance;
}

interface Doctor {
    id: number;
    name: string;
}

interface User {
    id: number;
    name: string;
}

interface VitalSign {
    id: number;
    temperature?: number;
    blood_pressure_systolic?: number;
    blood_pressure_diastolic?: number;
    pulse_rate?: number;
    respiratory_rate?: number;
    oxygen_saturation?: number;
    recorded_at: string;
    recorded_by?: User;
}

interface Bed {
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

interface PatientAdmission {
    id: number;
    admission_number: string;
    patient: Patient;
    status: string;
    admitted_at: string;
    bed_id?: number;
    bed?: Bed;
    consultation?: Consultation;
    latest_vital_signs?: VitalSign[];
    pending_medications?: any[];
    ward_rounds_count?: number;
    nursing_notes_count?: number;
    vitals_schedule?: VitalsSchedule;
}

interface Props {
    admissions: PatientAdmission[];
    wardId: number;
}

export function CurrentPatientsTable({ admissions, wardId }: Props) {
    const formatDateTime = (dateString: string) => {
        return new Date(dateString).toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const isVitalsOverdue = (admission: PatientAdmission) => {
        const latestVital = admission.latest_vital_signs?.[0];
        if (!latestVital) return true;

        return (
            new Date(latestVital.recorded_at) <
            new Date(Date.now() - 4 * 60 * 60 * 1000)
        );
    };

    if (admissions.length === 0) {
        return (
            <div className="py-12 text-center">
                <Bed className="mx-auto mb-4 h-12 w-12 text-gray-300 dark:text-gray-600" />
                <p className="text-gray-500 dark:text-gray-400">
                    No patients currently admitted to this ward
                </p>
            </div>
        );
    }

    return (
        <TooltipProvider>
            <div className="rounded-md border dark:border-gray-700">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Patient</TableHead>
                            <TableHead>Admission #</TableHead>
                            <TableHead>Bed</TableHead>
                            <TableHead>Doctor</TableHead>
                            <TableHead>Insurance</TableHead>
                            <TableHead>Admitted</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Vitals Status</TableHead>
                            <TableHead className="text-center">
                                Alerts
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {admissions.map((admission) => {
                            const vitalsOverdue = isVitalsOverdue(admission);
                            const hasPendingMeds =
                                admission.pending_medications &&
                                admission.pending_medications.length > 0;
                            const hasOverdueVitals =
                                admission.vitals_schedule &&
                                calculateVitalsStatus(
                                    admission.vitals_schedule,
                                ) === 'overdue';

                            return (
                                <TableRow
                                    key={admission.id}
                                    className={`cursor-pointer ${hasOverdueVitals ? 'bg-red-50 dark:bg-red-950/20' : ''}`}
                                >
                                    <TableCell>
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
                                                    {admission.patient
                                                        .date_of_birth && (
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
                                    </TableCell>

                                    <TableCell>
                                        <Link
                                            href={`/wards/${wardId}/patients/${admission.id}`}
                                            className="block font-mono text-sm text-gray-700 dark:text-gray-300"
                                        >
                                            {admission.admission_number}
                                        </Link>
                                    </TableCell>

                                    <TableCell>
                                        <Link
                                            href={`/wards/${wardId}/patients/${admission.id}`}
                                            className="block"
                                        >
                                            {admission.bed ? (
                                                <div className="flex items-center gap-2">
                                                    <Bed className="h-4 w-4 text-gray-500 dark:text-gray-400" />
                                                    <span className="font-medium">
                                                        {
                                                            admission.bed
                                                                .bed_number
                                                        }
                                                    </span>
                                                </div>
                                            ) : (
                                                <span className="text-sm text-gray-500 dark:text-gray-400">
                                                    No bed
                                                </span>
                                            )}
                                        </Link>
                                    </TableCell>

                                    <TableCell>
                                        <Link
                                            href={`/wards/${wardId}/patients/${admission.id}`}
                                            className="block"
                                        >
                                            {admission.consultation?.doctor ? (
                                                <div className="flex items-center gap-2 text-sm">
                                                    <Stethoscope className="h-4 w-4 text-gray-500 dark:text-gray-400" />
                                                    <span>
                                                        Dr.{' '}
                                                        {
                                                            admission
                                                                .consultation
                                                                .doctor.name
                                                        }
                                                    </span>
                                                </div>
                                            ) : (
                                                <span className="text-sm text-gray-500 dark:text-gray-400">
                                                    Not assigned
                                                </span>
                                            )}
                                        </Link>
                                    </TableCell>

                                    <TableCell>
                                        <Link
                                            href={`/wards/${wardId}/patients/${admission.id}`}
                                            className="block"
                                        >
                                            {admission.patient.active_insurance
                                                ?.plan?.provider ? (
                                                <div className="flex items-center gap-1 text-sm">
                                                    <ShieldCheck className="h-4 w-4 text-green-600 dark:text-green-400" />
                                                    <div className="flex flex-col">
                                                        <span className="font-medium text-gray-900 dark:text-gray-100">
                                                            {
                                                                admission
                                                                    .patient
                                                                    .active_insurance
                                                                    .plan
                                                                    .provider
                                                                    .name
                                                            }
                                                        </span>
                                                        <span className="text-xs text-gray-500 dark:text-gray-400">
                                                            {
                                                                admission
                                                                    .patient
                                                                    .active_insurance
                                                                    .plan
                                                                    .plan_name
                                                            }
                                                        </span>
                                                    </div>
                                                </div>
                                            ) : (
                                                <span className="text-sm text-gray-500 dark:text-gray-400">
                                                    None
                                                </span>
                                            )}
                                        </Link>
                                    </TableCell>

                                    <TableCell>
                                        <Link
                                            href={`/wards/${wardId}/patients/${admission.id}`}
                                            className="block text-sm text-gray-600 dark:text-gray-400"
                                        >
                                            <div className="flex items-center gap-1">
                                                <Calendar className="h-3 w-3" />
                                                {formatDateTime(
                                                    admission.admitted_at,
                                                )}
                                            </div>
                                        </Link>
                                    </TableCell>

                                    <TableCell>
                                        <Link
                                            href={`/wards/${wardId}/patients/${admission.id}`}
                                            className="block"
                                        >
                                            <Badge variant="default">
                                                {admission.status
                                                    .replace('_', ' ')
                                                    .toUpperCase()}
                                            </Badge>
                                        </Link>
                                    </TableCell>

                                    <TableCell>
                                        {admission.vitals_schedule ? (
                                            <VitalsStatusBadge
                                                schedule={
                                                    admission.vitals_schedule
                                                }
                                                admissionId={admission.id}
                                                wardId={wardId}
                                            />
                                        ) : (
                                            <span className="text-sm text-gray-400 dark:text-gray-600">
                                                No schedule
                                            </span>
                                        )}
                                    </TableCell>

                                    <TableCell>
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
                                                        <span className="hidden xl:inline">
                                                            Vitals
                                                        </span>
                                                    </div>
                                                )}
                                                {hasPendingMeds && (
                                                    <div
                                                        className="flex items-center gap-1 text-xs text-orange-600 dark:text-orange-400"
                                                        title={`${admission.pending_medications!.length} pending medication(s)`}
                                                    >
                                                        <Pill className="h-4 w-4" />
                                                        <span className="hidden xl:inline">
                                                            {
                                                                admission
                                                                    .pending_medications!
                                                                    .length
                                                            }
                                                        </span>
                                                    </div>
                                                )}
                                                {!vitalsOverdue &&
                                                    !hasPendingMeds && (
                                                        <span className="text-xs text-gray-400 dark:text-gray-600">
                                                            -
                                                        </span>
                                                    )}
                                            </div>
                                        </Link>
                                    </TableCell>
                                </TableRow>
                            );
                        })}
                    </TableBody>
                </Table>
            </div>
        </TooltipProvider>
    );
}

/**
 * Calculate vitals status from schedule
 */
function calculateVitalsStatus(
    schedule: VitalsSchedule,
): 'upcoming' | 'due' | 'overdue' {
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
}
