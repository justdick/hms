import { ServiceBlockAlert } from '@/components/Billing/ServiceBlockAlert';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { BedAssignmentModal } from '@/components/Ward/BedAssignmentModal';
import { ConfigureScheduleTimesModal } from '@/components/Ward/ConfigureScheduleTimesModal';
import { ConsultationVitalsCard } from '@/components/Ward/ConsultationVitalsCard';
import { DiscontinueMedicationModal } from '@/components/Ward/DiscontinueMedicationModal';
import { MedicationAdministrationPanel } from '@/components/Ward/MedicationAdministrationPanel';
import { MedicationAdministrationRecord } from '@/components/Ward/MedicationAdministrationRecord';
import { MedicationHistoryTab } from '@/components/Ward/MedicationHistoryTab';
import { NursingNotesModal } from '@/components/Ward/NursingNotesModal';
import { PrescriptionScheduleModal } from '@/components/Ward/PrescriptionScheduleModal';
import { RecordVitalsModal } from '@/components/Ward/RecordVitalsModal';
import { VitalsChart } from '@/components/Ward/VitalsChart';
import { VitalsScheduleModal } from '@/components/Ward/VitalsScheduleModal';
import {
    VitalsSchedule,
    VitalsStatusBadge,
} from '@/components/Ward/VitalsStatusBadge';
import { VitalsTable } from '@/components/Ward/VitalsTable';
import { WardRoundModal } from '@/components/Ward/WardRoundModal';
import { WardRoundsTable } from '@/components/Ward/WardRoundsTable';
import { WardRoundViewModal } from '@/components/Ward/WardRoundViewModal';
import { OverviewTab } from '@/components/Ward/OverviewTab';
import { LabsTab } from '@/components/Ward/LabsTab';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import {
    Activity,
    AlertCircle,
    AlertTriangle,
    ArrowLeft,
    Bed as BedIcon,
    Calendar,
    ClipboardList,
    Clock,
    Edit2,
    Eye,
    FileText,
    FlaskConical,
    Heart,
    LayoutDashboard,
    Pill,
    Plus,
    ShieldCheck,
    Stethoscope,
    Trash2,
    User,
    UserCheck,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import { isToday } from 'date-fns';

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
    patient_number: string;
    first_name: string;
    last_name: string;
    date_of_birth?: string;
    gender?: string;
    phone_number?: string;
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

interface Drug {
    id: number;
    name: string;
    strength?: string;
    form?: string;
}

interface ConsultationPrescription {
    id: number;
    drug?: Drug;
    medication_name: string;
    dosage?: string;
    dosage_form?: string;
    frequency: string;
    duration: string;
    route?: string;
    dose_quantity?: string;
    instructions?: string;
}

interface Prescription {
    id: number;
    drug: Drug;
    medication_name: string;
    dosage?: string;
    frequency?: string;
    duration?: string;
    route?: string;
}

interface VitalSign {
    id: number;
    temperature?: number;
    blood_pressure_systolic?: number;
    blood_pressure_diastolic?: number;
    pulse_rate?: number;
    respiratory_rate?: number;
    oxygen_saturation?: number;
    weight?: number;
    height?: number;
    recorded_at: string;
    recorded_by?: User;
}

interface MedicationAdministration {
    id: number;
    prescription: Prescription;
    scheduled_time: string;
    administered_at?: string;
    status: 'scheduled' | 'given' | 'held' | 'refused' | 'omitted' | 'cancelled';
    administered_by?: User;
    notes?: string;
    dosage_given?: string;
    route?: string;
    is_adjusted?: boolean;
}

interface Nurse {
    id: number;
    name: string;
}

interface NursingNote {
    id: number;
    type: 'assessment' | 'care' | 'observation' | 'incident' | 'handover';
    note: string;
    noted_at: string;
    nurse: Nurse;
    created_at: string;
}

interface WardRoundDiagnosis {
    id: number;
    diagnosis_name: string;
    icd_code: string;
    diagnosis_type: string;
    diagnosed_by?: {
        id: number;
        name: string;
    };
}

interface LabService {
    id: number;
    name: string;
    code: string;
    price: number;
}

interface LabOrder {
    id: number;
    lab_service?: LabService;
    status: string;
    ordered_at: string;
    priority: string;
    special_instructions?: string;
    result_values?: any;
    result_notes?: string;
}

interface WardRoundPrescription {
    id: number;
    medication_name: string;
    drug?: Drug;
    dose_quantity?: string;
    frequency: string;
    duration: string;
    instructions?: string;
    status: string;
}

interface WardRound {
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
    notes?: string;
    findings?: string;
    plan?: string;
    created_at: string;
    updated_at: string;
    diagnoses?: WardRoundDiagnosis[];
    prescriptions?: WardRoundPrescription[];
    lab_orders?: LabOrder[];
}

interface BedType {
    id: number;
    ward_id: number;
    bed_number: string;
    status: string;
    type: string;
}

interface Ward {
    id: number;
    name: string;
    code: string;
}

interface PatientCheckin {
    id: number;
    vital_signs?: VitalSign[];
}

interface Consultation {
    id: number;
    doctor: Doctor;
    chief_complaint?: string;
    diagnosis?: string;
    patient_checkin?: PatientCheckin;
    prescriptions?: ConsultationPrescription[];
}

interface PatientAdmission {
    id: number;
    admission_number: string;
    patient: Patient;
    patient_checkin_id?: number;
    status: string;
    admitted_at: string;
    discharged_at?: string;
    bed_id?: number;
    bed?: BedType;
    is_overflow_patient?: boolean;
    overflow_notes?: string;
    ward?: Ward;
    consultation?: Consultation;
    vital_signs?: VitalSign[];
    medication_administrations?: MedicationAdministration[];
    nursing_notes?: NursingNote[];
    ward_rounds?: WardRound[];
    vitals_schedule?: VitalsSchedule;
}

interface ServiceCharge {
    id: number;
    description: string;
    amount: number;
    service_type: string;
}

interface ServiceAccessOverride {
    id: number;
    service_type: string;
    reason: string;
    authorized_by: {
        id: number;
        name: string;
    };
    expires_at: string;
    remaining_duration: string;
}

interface Props {
    admission: PatientAdmission;
    availableBeds?: BedType[];
    allBeds?: BedType[];
    hasAvailableBeds?: boolean;
    serviceBlocked?: boolean;
    blockReason?: string;
    pendingCharges?: ServiceCharge[];
    activeOverride?: ServiceAccessOverride | null;
}

const NOTE_TYPES = [
    { value: 'assessment', label: 'Assessment', icon: Stethoscope },
    { value: 'care', label: 'Care', icon: UserCheck },
    { value: 'observation', label: 'Observation', icon: Eye },
    { value: 'incident', label: 'Incident', icon: AlertCircle },
    { value: 'handover', label: 'Handover', icon: ClipboardList },
];

export default function WardPatientShow({
    admission,
    availableBeds = [],
    allBeds = [],
    hasAvailableBeds = false,
    serviceBlocked = false,
    blockReason,
    pendingCharges = [],
    activeOverride,
}: Props) {
    const [activeTab, setActiveTab] = useState('overview');
    const [vitalsModalOpen, setVitalsModalOpen] = useState(false);
    const [nursingNotesModalOpen, setNursingNotesModalOpen] = useState(false);
    const [wardRoundModalOpen, setWardRoundModalOpen] = useState(false);
    const [medicationPanelOpen, setMedicationPanelOpen] = useState(false);
    const [bedAssignmentModalOpen, setBedAssignmentModalOpen] = useState(false);
    const [confirmNewRoundOpen, setConfirmNewRoundOpen] = useState(false);
    const [noteToDelete, setNoteToDelete] = useState<NursingNote | null>(null);
    const [selectedWardRound, setSelectedWardRound] =
        useState<WardRound | null>(null);
    const [wardRoundViewModalOpen, setWardRoundViewModalOpen] = useState(false);
    const [vitalsScheduleModalOpen, setVitalsScheduleModalOpen] =
        useState(false);
    
    // Medication scheduling modals
    const [configureTimesModalOpen, setConfigureTimesModalOpen] = useState(false);
    const [reconfigureTimesModalOpen, setReconfigureTimesModalOpen] = useState(false);
    const [viewScheduleModalOpen, setViewScheduleModalOpen] = useState(false);
    const [discontinueModalOpen, setDiscontinueModalOpen] = useState(false);
    const [selectedPrescription, setSelectedPrescription] = useState<ConsultationPrescription | WardRoundPrescription | null>(null);

    // Note: Vitals alerts are now handled globally in AppLayout
    // No need to fetch or show toasts here

    const loadBedData = () => {
        router.reload({
            only: ['availableBeds', 'allBeds', 'hasAvailableBeds'],
            onSuccess: () => setBedAssignmentModalOpen(true),
        });
    };

    const formatDateTime = (dateString: string) => {
        return new Date(dateString).toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const formatInterval = (minutes: number): string => {
        if (minutes < 60) {
            return `${minutes} minute${minutes !== 1 ? 's' : ''}`;
        }
        const hours = Math.floor(minutes / 60);
        const remainingMinutes = minutes % 60;
        if (remainingMinutes === 0) {
            return `${hours} hour${hours !== 1 ? 's' : ''}`;
        }
        return `${hours} hour${hours !== 1 ? 's' : ''} ${remainingMinutes} minute${remainingMinutes !== 1 ? 's' : ''}`;
    };

    const calculateAge = (dob?: string) => {
        if (!dob) return null;
        const birthDate = new Date(dob);
        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const monthDiff = today.getMonth() - birthDate.getMonth();
        if (
            monthDiff < 0 ||
            (monthDiff === 0 && today.getDate() < birthDate.getDate())
        ) {
            age--;
        }
        return age;
    };

    // Merge consultation vitals with ward vitals
    const allVitals = [
        ...(admission.vital_signs || []),
        ...(admission.consultation?.patient_checkin?.vital_signs || []),
    ].sort(
        (a, b) =>
            new Date(b.recorded_at).getTime() -
            new Date(a.recorded_at).getTime(),
    );

    // Merge consultation prescriptions with ward medication administrations
    // Note: Consultation prescriptions would need to be converted to medication administration records
    // For now, we'll just use the existing medication_administrations from the backend
    const allMedications = admission.medication_administrations || [];

    const latestVital = allVitals[0];
    
    // Calculate today's pending medications only (for badge display)
    const todayPendingMeds = allMedications.filter(
        (med) =>
            med.status === 'scheduled' &&
            isToday(new Date(med.scheduled_time)),
    );
    
    // Keep all pending meds for other uses
    const pendingMeds = allMedications.filter(
        (med) => med.status === 'scheduled',
    );

    // Collect all prescriptions from consultation and ward rounds
    const allPrescriptions = [
        ...(admission.consultation?.prescriptions || []),
        ...(admission.ward_rounds?.flatMap((round) => round.prescriptions || []) || []),
    ];

    // Collect all lab orders from ward rounds
    const allLabOrders = useMemo(() => {
        return (
            admission.ward_rounds
                ?.flatMap((round) => round.lab_orders || [])
                .sort(
                    (a, b) =>
                        new Date(b.ordered_at).getTime() -
                        new Date(a.ordered_at).getTime(),
                ) || []
        );
    }, [admission]);

    // Check if there's an in-progress ward round
    const inProgressRound = admission.ward_rounds?.find(
        (round) => round.status === 'in_progress',
    );

    // Tab navigation handler
    const handleNavigateToTab = (tabValue: string) => {
        setActiveTab(tabValue);
    };

    const handleStartNewWardRound = () => {
        if (inProgressRound) {
            setConfirmNewRoundOpen(true);
        } else {
            router.visit(`/admissions/${admission.id}/ward-rounds/create`);
        }
    };

    const confirmStartNewRound = () => {
        setConfirmNewRoundOpen(false);
        router.visit(
            `/admissions/${admission.id}/ward-rounds/create?force=true`,
        );
    };

    const handleViewWardRound = (wardRound: WardRound) => {
        setSelectedWardRound(wardRound);
        setWardRoundViewModalOpen(true);
    };

    // Calculate next day number
    const calculateAdmissionDays = () => {
        const admissionDate = new Date(admission.admitted_at);
        const today = new Date();
        const diffTime = Math.abs(today.getTime() - admissionDate.getTime());
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        return diffDays;
    };

    const getNoteTypeStyle = (
        type: string,
    ): { badge: string; icon: any; label: string } => {
        const typeConfig = NOTE_TYPES.find((t) => t.value === type);
        const colorMap = {
            assessment:
                'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            care: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            observation:
                'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
            incident:
                'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
            handover:
                'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
        };

        return {
            badge: colorMap[type as keyof typeof colorMap] || '',
            icon: typeConfig?.icon || FileText,
            label: typeConfig?.label || type,
        };
    };

    const canEditNote = (note: NursingNote) => {
        const createdAt = new Date(note.created_at);
        const hoursSinceCreation =
            (Date.now() - createdAt.getTime()) / (1000 * 60 * 60);
        return hoursSinceCreation < 24;
    };

    const canDeleteNote = (note: NursingNote) => {
        const createdAt = new Date(note.created_at);
        const hoursSinceCreation =
            (Date.now() - createdAt.getTime()) / (1000 * 60 * 60);
        return hoursSinceCreation < 2;
    };

    const handleDeleteNote = () => {
        if (!noteToDelete) return;

        router.delete(
            `/admissions/${admission.id}/nursing-notes/${noteToDelete.id}`,
            {
                onSuccess: () => {
                    toast.success('Nursing note deleted successfully');
                    setNoteToDelete(null);
                },
                onError: () => {
                    toast.error('Failed to delete nursing note');
                },
            },
        );
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Wards', href: '/wards' },
                {
                    title: admission.ward?.name || 'Ward',
                    href: `/wards/${admission.ward?.id}`,
                },
                {
                    title: `${admission.patient.first_name} ${admission.patient.last_name}`,
                    href: '',
                },
            ]}
        >
            <Head
                title={`${admission.patient.first_name} ${admission.patient.last_name} - Ward Patient`}
            />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div className="flex items-center gap-4">
                        <Link href={`/wards/${admission.ward?.id}`}>
                            <Button variant="ghost" size="sm">
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Back to Ward
                            </Button>
                        </Link>
                        <div>
                            <div className="flex items-center gap-3">
                                <h1 className="flex items-center gap-2 text-3xl font-bold text-gray-900 dark:text-gray-100">
                                    <User className="h-8 w-8" />
                                    {admission.patient.first_name}{' '}
                                    {admission.patient.last_name}
                                </h1>
                                <Badge
                                    variant={
                                        admission.status === 'admitted'
                                            ? 'default'
                                            : 'secondary'
                                    }
                                >
                                    {admission.status.replace('_', ' ')}
                                </Badge>
                            </div>
                            <p className="mt-2 text-gray-600 dark:text-gray-400">
                                Admission: {admission.admission_number}
                            </p>
                        </div>
                    </div>

                    {admission.consultation && (
                        <Link
                            href={`/consultation/${admission.consultation.id}`}
                        >
                            <Button>
                                <FileText className="mr-2 h-4 w-4" />
                                View Consultation
                            </Button>
                        </Link>
                    )}
                </div>

                {/* Service Block Alert */}
                <ServiceBlockAlert
                    isBlocked={serviceBlocked}
                    blockReason={blockReason}
                    pendingCharges={pendingCharges}
                    activeOverride={activeOverride}
                    checkinId={admission.patient_checkin_id}
                />

                {/* Patient Info Cards */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-5">
                    <Card>
                        <CardContent className="p-6">
                            <div className="space-y-2">
                                <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                    Patient Information
                                </p>
                                <div className="space-y-1 text-sm">
                                    {admission.patient.date_of_birth && (
                                        <p className="text-gray-900 dark:text-gray-100">
                                            Age:{' '}
                                            {calculateAge(
                                                admission.patient.date_of_birth,
                                            )}{' '}
                                            years
                                        </p>
                                    )}
                                    {admission.patient.gender && (
                                        <p className="text-gray-900 dark:text-gray-100">
                                            Gender: {admission.patient.gender}
                                        </p>
                                    )}
                                    {admission.patient.phone_number && (
                                        <p className="text-gray-900 dark:text-gray-100">
                                            Phone:{' '}
                                            {admission.patient.phone_number}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="space-y-2">
                                <div className="flex items-center justify-between">
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Ward & Bed
                                    </p>
                                    <Button
                                        size="sm"
                                        variant={
                                            admission.bed
                                                ? 'outline'
                                                : 'default'
                                        }
                                        onClick={loadBedData}
                                    >
                                        <BedIcon className="mr-2 h-3 w-3" />
                                        {admission.bed
                                            ? 'Change Bed'
                                            : 'Assign Bed'}
                                    </Button>
                                </div>
                                <div className="space-y-1">
                                    <div className="flex items-center gap-2 text-sm text-gray-900 dark:text-gray-100">
                                        <BedIcon className="h-4 w-4" />
                                        {admission.bed ? (
                                            <span>
                                                Bed {admission.bed.bed_number}
                                            </span>
                                        ) : (
                                            <span className="text-gray-500 dark:text-gray-400">
                                                No bed assigned
                                            </span>
                                        )}
                                    </div>
                                    {admission.ward && (
                                        <p className="text-sm text-gray-900 dark:text-gray-100">
                                            Ward: {admission.ward.name} (
                                            {admission.ward.code})
                                        </p>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="space-y-2">
                                <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                    Attending Physician
                                </p>
                                {admission.consultation?.doctor ? (
                                    <div className="flex items-center gap-2 text-sm text-gray-900 dark:text-gray-100">
                                        <Stethoscope className="h-4 w-4" />
                                        <span>
                                            Dr.{' '}
                                            {admission.consultation.doctor.name}
                                        </span>
                                    </div>
                                ) : (
                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                        Not assigned
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="space-y-2">
                                <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                    Admission Date
                                </p>
                                <div className="flex items-center gap-2 text-sm text-gray-900 dark:text-gray-100">
                                    <Calendar className="h-4 w-4" />
                                    {formatDateTime(admission.admitted_at)}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="space-y-2">
                                <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                    Insurance Coverage
                                </p>
                                {admission.patient.active_insurance ? (
                                    <div className="space-y-1">
                                        <div className="flex items-center gap-2 text-sm text-gray-900 dark:text-gray-100">
                                            <ShieldCheck className="h-4 w-4 text-green-600 dark:text-green-400" />
                                            <span className="font-medium">
                                                {
                                                    admission.patient
                                                        .active_insurance
                                                        .plan
                                                        .provider.name
                                                }
                                            </span>
                                        </div>
                                        <p className="text-xs text-gray-600 dark:text-gray-400">
                                            {
                                                admission.patient
                                                    .active_insurance
                                                    .plan.plan_name
                                            }{' '}
                                            (
                                            {
                                                admission.patient
                                                    .active_insurance
                                                    .plan.plan_type
                                            }
                                            )
                                        </p>
                                        <p className="text-xs text-gray-600 dark:text-gray-400">
                                            Member:{' '}
                                            {
                                                admission.patient
                                                    .active_insurance
                                                    .member_number
                                            }
                                        </p>
                                    </div>
                                ) : (
                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                        No active insurance
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Alert Badges */}
                {(admission.is_overflow_patient ||
                    !admission.bed ||
                    (todayPendingMeds && todayPendingMeds.length > 0) ||
                    !latestVital ||
                    new Date(latestVital.recorded_at) <
                        new Date(Date.now() - 4 * 60 * 60 * 1000)) && (
                    <div className="flex flex-wrap gap-2">
                        {admission.is_overflow_patient && (
                            <Badge
                                variant="outline"
                                className="border-red-500 text-red-700 dark:border-red-600 dark:text-red-400"
                            >
                                <AlertTriangle className="mr-2 h-3 w-3" />
                                Overflow Patient - No Bed
                            </Badge>
                        )}
                        {!admission.bed && !admission.is_overflow_patient && (
                            <Badge
                                variant="outline"
                                className="border-orange-500 text-orange-700 dark:border-orange-600 dark:text-orange-400"
                            >
                                <BedIcon className="mr-2 h-3 w-3" />
                                No Bed Assigned
                            </Badge>
                        )}
                        {todayPendingMeds && todayPendingMeds.length > 0 && (
                            <Badge
                                variant="outline"
                                className="border-orange-500 text-orange-700 dark:border-orange-600 dark:text-orange-400"
                            >
                                <Pill className="mr-2 h-3 w-3" />
                                {todayPendingMeds.length} Pending Medication
                                {todayPendingMeds.length !== 1 ? 's' : ''}
                            </Badge>
                        )}
                        {(!latestVital ||
                            new Date(latestVital.recorded_at) <
                                new Date(Date.now() - 4 * 60 * 60 * 1000)) && (
                            <Badge
                                variant="outline"
                                className="border-yellow-500 text-yellow-700 dark:border-yellow-600 dark:text-yellow-400"
                            >
                                <AlertCircle className="mr-2 h-3 w-3" />
                                Vitals Overdue
                            </Badge>
                        )}
                    </div>
                )}

                {/* Tabbed Content */}
                <Tabs value={activeTab} onValueChange={setActiveTab} defaultValue="overview" className="w-full">
                    <TabsList>
                        <TabsTrigger
                            value="overview"
                            className="flex items-center gap-2"
                        >
                            <LayoutDashboard className="h-4 w-4" />
                            Overview
                        </TabsTrigger>
                        <TabsTrigger
                            value="vitals"
                            className="flex items-center gap-2"
                        >
                            <Heart className="h-4 w-4" />
                            Vital Signs
                        </TabsTrigger>
                        <TabsTrigger
                            value="medications"
                            className="flex items-center gap-2"
                        >
                            <Pill className="h-4 w-4" />
                            Medication Administration
                            {todayPendingMeds && todayPendingMeds.length > 0 && (
                                <Badge variant="destructive" className="ml-1">
                                    {todayPendingMeds.length}
                                </Badge>
                            )}
                        </TabsTrigger>
                        <TabsTrigger
                            value="history"
                            className="flex items-center gap-2"
                        >
                            <ClipboardList className="h-4 w-4" />
                            Medication History
                        </TabsTrigger>
                        {allLabOrders.length > 0 && (
                            <TabsTrigger
                                value="labs"
                                className="flex items-center gap-2"
                            >
                                <FlaskConical className="h-4 w-4" />
                                Labs
                            </TabsTrigger>
                        )}
                        <TabsTrigger
                            value="notes"
                            className="flex items-center gap-2"
                        >
                            <FileText className="h-4 w-4" />
                            Nursing Notes
                        </TabsTrigger>
                        <TabsTrigger
                            value="rounds"
                            className="flex items-center gap-2"
                        >
                            <Stethoscope className="h-4 w-4" />
                            Ward Rounds
                        </TabsTrigger>
                    </TabsList>

                    {/* Overview Tab */}
                    <TabsContent value="overview">
                        <OverviewTab
                            admission={admission}
                            onNavigateToTab={handleNavigateToTab}
                        />
                    </TabsContent>

                    {/* Vital Signs Tab */}
                    <TabsContent value="vitals">
                        <div className="space-y-4">
                            {/* Vitals Schedule Section */}
                            <Card>
                                <CardHeader>
                                    <div className="flex items-center justify-between">
                                        <CardTitle className="flex items-center gap-2">
                                            <Clock className="h-5 w-5" />
                                            Vitals Schedule
                                        </CardTitle>
                                        <div className="flex items-center gap-2">
                                            {admission.vitals_schedule && (
                                                <VitalsStatusBadge
                                                    schedule={
                                                        admission.vitals_schedule
                                                    }
                                                    admissionId={admission.id}
                                                    wardId={
                                                        admission.ward?.id || 0
                                                    }
                                                    onClick={() =>
                                                        setVitalsModalOpen(true)
                                                    }
                                                />
                                            )}
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    setVitalsScheduleModalOpen(
                                                        true,
                                                    )
                                                }
                                            >
                                                {admission.vitals_schedule ? (
                                                    <>
                                                        <Edit2 className="mr-2 h-4 w-4" />
                                                        Edit Schedule
                                                    </>
                                                ) : (
                                                    <>
                                                        <Plus className="mr-2 h-4 w-4" />
                                                        Create Schedule
                                                    </>
                                                )}
                                            </Button>
                                            <Button
                                                onClick={() =>
                                                    setVitalsModalOpen(true)
                                                }
                                            >
                                                <Activity className="mr-2 h-4 w-4" />
                                                Record Vitals Now
                                            </Button>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    {admission.vitals_schedule ? (
                                        <div className="space-y-3">
                                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                                <div className="space-y-1">
                                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                                        Recording Interval
                                                    </p>
                                                    <p className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                                        {formatInterval(
                                                            admission
                                                                .vitals_schedule
                                                                .interval_minutes,
                                                        )}
                                                    </p>
                                                </div>
                                                <div className="space-y-1">
                                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                                        Next Due
                                                    </p>
                                                    <p className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                                        {formatDateTime(
                                                            admission
                                                                .vitals_schedule
                                                                .next_due_at,
                                                        )}
                                                    </p>
                                                </div>
                                                <div className="space-y-1">
                                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                                        Last Recorded
                                                    </p>
                                                    <p className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                                        {admission
                                                            .vitals_schedule
                                                            .last_recorded_at
                                                            ? formatDateTime(
                                                                  admission
                                                                      .vitals_schedule
                                                                      .last_recorded_at,
                                                              )
                                                            : 'Never'}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="py-8 text-center">
                                            <Clock className="mx-auto mb-3 h-12 w-12 text-gray-300 dark:text-gray-600" />
                                            <p className="mb-2 text-gray-600 dark:text-gray-400">
                                                No vitals schedule configured
                                            </p>
                                            <p className="text-sm text-gray-500 dark:text-gray-500">
                                                Create a schedule to receive
                                                automatic reminders for vitals
                                                recording
                                            </p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Vitals Recording Section */}
                            <div className="flex items-center justify-between">
                                <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    Vital Signs History
                                </h3>
                            </div>
                            <div className="grid grid-cols-1 gap-4 lg:grid-cols-5">
                                {/* Vitals Table - Left Side (2/5 width) */}
                                <Card className="lg:col-span-2">
                                    <CardHeader>
                                        <CardTitle>Vitals History</CardTitle>
                                    </CardHeader>
                                    <CardContent className="max-h-[800px] overflow-y-auto">
                                        <VitalsTable vitals={allVitals} />
                                    </CardContent>
                                </Card>

                                {/* Vitals Charts - Right Side (3/5 width) */}
                                <Card className="lg:col-span-3">
                                    <CardHeader>
                                        <CardTitle>Vitals Trends</CardTitle>
                                    </CardHeader>
                                    <CardContent className="max-h-[800px] overflow-y-auto">
                                        <VitalsChart vitals={allVitals} />
                                    </CardContent>
                                </Card>
                            </div>
                        </div>
                    </TabsContent>

                    {/* Medication Administration Tab */}
                    <TabsContent value="medications">
                        <div className="space-y-6">
                            <div className="flex items-center justify-between">
                                <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    Medication Administration
                                </h3>
                                <Button
                                    onClick={() => setMedicationPanelOpen(true)}
                                >
                                    <Pill className="mr-2 h-4 w-4" />
                                    Quick View & Administer
                                </Button>
                            </div>

                            {/* MAR Chart */}
                            <MedicationAdministrationRecord
                                medications={allMedications}
                                onAdminister={(med) => {
                                    // Open the panel to administer
                                    setMedicationPanelOpen(true);
                                }}
                                onHold={(med) => {
                                    // Open the panel to hold
                                    setMedicationPanelOpen(true);
                                }}
                                onRefuse={(med) => {
                                    // Open the panel to refuse
                                    setMedicationPanelOpen(true);
                                }}
                            />
                        </div>
                    </TabsContent>

                    {/* Nursing Notes Tab */}
                    <TabsContent value="notes">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <CardTitle>Nursing Notes</CardTitle>
                                <Button
                                    onClick={() =>
                                        setNursingNotesModalOpen(true)
                                    }
                                >
                                    <FileText className="mr-2 h-4 w-4" />
                                    Add Note
                                </Button>
                            </CardHeader>
                            <CardContent>
                                {admission.nursing_notes &&
                                admission.nursing_notes.length > 0 ? (
                                    <div className="space-y-3">
                                        {admission.nursing_notes.map((note) => {
                                            const typeStyle = getNoteTypeStyle(
                                                note.type,
                                            );
                                            const Icon = typeStyle.icon;

                                            return (
                                                <div
                                                    key={note.id}
                                                    className="rounded-lg border p-4 dark:border-gray-700"
                                                >
                                                    <div className="mb-2 flex items-start justify-between">
                                                        <div className="flex items-center gap-2">
                                                            <Badge
                                                                className={
                                                                    typeStyle.badge
                                                                }
                                                            >
                                                                <Icon className="mr-1 h-3 w-3" />
                                                                {
                                                                    typeStyle.label
                                                                }
                                                            </Badge>
                                                        </div>
                                                        <div className="flex items-center gap-1">
                                                            {canEditNote(
                                                                note,
                                                            ) && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() => {
                                                                        // TODO: Implement edit functionality
                                                                        toast.info(
                                                                            'Edit functionality coming soon',
                                                                        );
                                                                    }}
                                                                >
                                                                    <Edit2 className="h-4 w-4" />
                                                                </Button>
                                                            )}
                                                            {canDeleteNote(
                                                                note,
                                                            ) && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        setNoteToDelete(
                                                                            note,
                                                                        )
                                                                    }
                                                                >
                                                                    <Trash2 className="h-4 w-4 text-destructive" />
                                                                </Button>
                                                            )}
                                                        </div>
                                                    </div>

                                                    <p className="text-sm text-gray-700 dark:text-gray-300">
                                                        {note.note}
                                                    </p>

                                                    <div className="mt-2 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                                        <span className="flex items-center gap-1">
                                                            <UserCheck className="h-3 w-3" />
                                                            {note.nurse.name}
                                                        </span>
                                                        <span>
                                                            {formatDateTime(
                                                                note.noted_at,
                                                            )}
                                                        </span>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                ) : (
                                    <div className="py-12 text-center">
                                        <FileText className="mx-auto mb-4 h-12 w-12 text-gray-300 dark:text-gray-600" />
                                        <p className="text-gray-500 dark:text-gray-400">
                                            No nursing notes yet
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Medication History Tab */}
                    <TabsContent value="history">
                        <MedicationHistoryTab
                            patientAdmissionId={admission.id}
                            prescriptions={allPrescriptions}
                            onConfigureTimes={(prescriptionId) => {
                                const prescription = allPrescriptions.find(p => p.id === prescriptionId);
                                setSelectedPrescription(prescription || null);
                                setConfigureTimesModalOpen(true);
                            }}
                            onReconfigureTimes={(prescriptionId) => {
                                const prescription = allPrescriptions.find(p => p.id === prescriptionId);
                                setSelectedPrescription(prescription || null);
                                setReconfigureTimesModalOpen(true);
                            }}
                            onViewSchedule={(prescriptionId) => {
                                const prescription = allPrescriptions.find(p => p.id === prescriptionId);
                                setSelectedPrescription(prescription || null);
                                setViewScheduleModalOpen(true);
                            }}
                            onDiscontinue={(prescriptionId, reason) => {
                                const prescription = allPrescriptions.find(p => p.id === prescriptionId);
                                setSelectedPrescription(prescription || null);
                                setDiscontinueModalOpen(true);
                            }}
                        />
                    </TabsContent>

                    {/* Labs Tab - Conditional */}
                    {allLabOrders.length > 0 && (
                        <TabsContent value="labs">
                            <LabsTab labOrders={allLabOrders} />
                        </TabsContent>
                    )}

                    {/* Ward Rounds Tab */}
                    <TabsContent value="rounds">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <CardTitle>Ward Rounds</CardTitle>
                                <Button onClick={handleStartNewWardRound}>
                                    <Stethoscope className="mr-2 h-4 w-4" />
                                    Start New Ward Round
                                </Button>
                            </CardHeader>
                            <CardContent>
                                {admission.ward_rounds &&
                                admission.ward_rounds.length > 0 ? (
                                    <WardRoundsTable
                                        admissionId={admission.id}
                                        wardRounds={admission.ward_rounds}
                                        onViewWardRound={handleViewWardRound}
                                    />
                                ) : (
                                    <div className="py-12 text-center">
                                        <Stethoscope className="mx-auto mb-4 h-12 w-12 text-gray-300 dark:text-gray-600" />
                                        <p className="text-gray-500 dark:text-gray-400">
                                            No ward rounds yet
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>

                {/* Modals */}
                <RecordVitalsModal
                    open={vitalsModalOpen}
                    onClose={() => setVitalsModalOpen(false)}
                    admission={admission}
                    onSuccess={() => setVitalsModalOpen(false)}
                />

                <NursingNotesModal
                    open={nursingNotesModalOpen}
                    onClose={() => setNursingNotesModalOpen(false)}
                    admission={admission}
                />

                <WardRoundModal
                    open={wardRoundModalOpen}
                    onClose={() => setWardRoundModalOpen(false)}
                    admission={admission}
                />

                <MedicationAdministrationPanel
                    admission={admission}
                    open={medicationPanelOpen}
                    onOpenChange={setMedicationPanelOpen}
                />

                <BedAssignmentModal
                    open={bedAssignmentModalOpen}
                    onClose={() => setBedAssignmentModalOpen(false)}
                    admission={admission}
                    availableBeds={availableBeds}
                    allBeds={allBeds}
                    hasAvailableBeds={hasAvailableBeds}
                    isChangingBed={!!admission.bed}
                />

                <WardRoundViewModal
                    open={wardRoundViewModalOpen}
                    onClose={() => {
                        setWardRoundViewModalOpen(false);
                        setSelectedWardRound(null);
                    }}
                    wardRound={selectedWardRound}
                    patientName={`${admission.patient.first_name} ${admission.patient.last_name}`}
                />

                <VitalsScheduleModal
                    open={vitalsScheduleModalOpen}
                    onOpenChange={setVitalsScheduleModalOpen}
                    wardId={admission.ward?.id || 0}
                    admissionId={admission.id}
                    scheduleId={admission.vitals_schedule?.id}
                    currentInterval={
                        admission.vitals_schedule?.interval_minutes
                    }
                    patientName={`${admission.patient.first_name} ${admission.patient.last_name}`}
                />

                {/* Medication Scheduling Modals */}
                <ConfigureScheduleTimesModal
                    prescription={selectedPrescription}
                    isOpen={configureTimesModalOpen}
                    onClose={() => {
                        setConfigureTimesModalOpen(false);
                        setSelectedPrescription(null);
                    }}
                    isReconfigure={false}
                />

                <ConfigureScheduleTimesModal
                    prescription={selectedPrescription}
                    isOpen={reconfigureTimesModalOpen}
                    onClose={() => {
                        setReconfigureTimesModalOpen(false);
                        setSelectedPrescription(null);
                    }}
                    isReconfigure={true}
                />

                <PrescriptionScheduleModal
                    prescription={selectedPrescription}
                    isOpen={viewScheduleModalOpen}
                    onClose={() => {
                        setViewScheduleModalOpen(false);
                        setSelectedPrescription(null);
                    }}
                />

                <DiscontinueMedicationModal
                    prescription={selectedPrescription}
                    isOpen={discontinueModalOpen}
                    onClose={() => {
                        setDiscontinueModalOpen(false);
                        setSelectedPrescription(null);
                    }}
                />

                {/* Confirmation Dialog for Starting New Ward Round */}
                <AlertDialog
                    open={confirmNewRoundOpen}
                    onOpenChange={setConfirmNewRoundOpen}
                >
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>
                                Complete Current Round and Start New?
                            </AlertDialogTitle>
                            <AlertDialogDescription className="space-y-2">
                                <p>
                                    This will complete{' '}
                                    <strong>
                                        Ward Round Day{' '}
                                        {inProgressRound?.day_number}
                                    </strong>{' '}
                                    and create a new{' '}
                                    <strong>
                                        Ward Round Day{' '}
                                        {calculateAdmissionDays()}
                                    </strong>
                                    .
                                </p>
                                <p className="text-muted-foreground">
                                    All current data will be saved
                                    automatically.
                                </p>
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                            <AlertDialogAction onClick={confirmStartNewRound}>
                                Start New Round
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>

                {/* Confirmation Dialog for Deleting Nursing Note */}
                <AlertDialog
                    open={!!noteToDelete}
                    onOpenChange={(open) => !open && setNoteToDelete(null)}
                >
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>
                                Delete Nursing Note?
                            </AlertDialogTitle>
                            <AlertDialogDescription>
                                Are you sure you want to delete this nursing
                                note? This action cannot be undone.
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                            <AlertDialogAction
                                onClick={handleDeleteNote}
                                className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                            >
                                Delete Note
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </div>
        </AppLayout>
    );
}
