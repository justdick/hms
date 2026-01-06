import { ServiceBlockAlert } from '@/components/billing/ServiceBlockAlert';
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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { BedAssignmentModal } from '@/components/Ward/BedAssignmentModal';
import { DiscontinueMedicationModal } from '@/components/Ward/DiscontinueMedicationModal';
import { LabsTab } from '@/components/Ward/LabsTab';
import { MARTable } from '@/components/Ward/MARTable';
import { MedicationAdministrationPanel } from '@/components/Ward/MedicationAdministrationPanel';
import { MedicationHistoryTab } from '@/components/Ward/MedicationHistoryTab';
import { NursingNotesModal } from '@/components/Ward/NursingNotesModal';
import { OverviewTab } from '@/components/Ward/OverviewTab';
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
import { WardTransferModal } from '@/components/Ward/WardTransferModal';
import AppLayout from '@/layouts/app-layout';
import { SharedData } from '@/types';
import { Head, Link, router, usePage, Form } from '@inertiajs/react';
import { isToday } from 'date-fns';
import {
    Activity,
    AlertCircle,
    AlertTriangle,
    ArrowLeft,
    ArrowRightLeft,
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
    Loader2,
    LogOut,
    MoreHorizontal,
    Pill,
    Plus,
    RefreshCw,
    ShieldCheck,
    Stethoscope,
    Trash2,
    User,
    UserCheck,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';

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
    discontinued_at?: string;
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
    notes?: string | null;
    recorded_at: string;
    recorded_by?: User;
}

interface MedicationAdministration {
    id: number;
    prescription_id: number;
    administered_at: string;
    status: 'given' | 'held' | 'refused' | 'omitted';
    administered_by?: User;
    notes?: string;
    dosage_given?: string;
    route?: string;
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
    discontinued_at?: string;
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
    can_edit_vitals_timestamp?: boolean;
    can_edit_medication_timestamp?: boolean;
    can_delete_medication_administration?: boolean;
    can_transfer?: boolean;
    availableWards?: Array<{
        id: number;
        name: string;
        code: string;
        available_beds: number;
    }>;
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
    can_edit_vitals_timestamp = false,
    can_edit_medication_timestamp = false,
    can_delete_medication_administration = false,
    can_transfer = false,
    availableWards = [],
}: Props) {
    const { auth } = usePage<SharedData>().props;
    const canDischarge = auth.permissions?.admissions?.discharge ?? false;

    const [activeTab, setActiveTab] = useState('overview');
    const [vitalsModalOpen, setVitalsModalOpen] = useState(false);
    const [vitalsModalMode, setVitalsModalMode] = useState<'create' | 'edit'>(
        'create',
    );
    const [editingVitals, setEditingVitals] = useState<VitalSign | null>(null);
    const [nursingNotesModalOpen, setNursingNotesModalOpen] = useState(false);
    const [wardRoundModalOpen, setWardRoundModalOpen] = useState(false);
    const [medicationPanelOpen, setMedicationPanelOpen] = useState(false);
    const [bedAssignmentModalOpen, setBedAssignmentModalOpen] = useState(false);
    const [transferModalOpen, setTransferModalOpen] = useState(false);
    const [confirmNewRoundOpen, setConfirmNewRoundOpen] = useState(false);
    const [noteToDelete, setNoteToDelete] = useState<NursingNote | null>(null);
    const [noteToEdit, setNoteToEdit] = useState<NursingNote | null>(null);
    const [selectedWardRound, setSelectedWardRound] =
        useState<WardRound | null>(null);
    const [wardRoundViewModalOpen, setWardRoundViewModalOpen] = useState(false);
    const [vitalsScheduleModalOpen, setVitalsScheduleModalOpen] =
        useState(false);

    // Medication modals
    const [discontinueModalOpen, setDiscontinueModalOpen] = useState(false);
    const [selectedPrescription, setSelectedPrescription] = useState<
        ConsultationPrescription | WardRoundPrescription | null
    >(null);

    // Discharge modal
    const [dischargeModalOpen, setDischargeModalOpen] = useState(false);
    const [dischargeNotes, setDischargeNotes] = useState('');
    const [isDischarging, setIsDischarging] = useState(false);

    // Note: Vitals alerts are now handled globally in AppLayout
    // No need to fetch or show toasts here

    const loadBedData = () => {
        router.reload({
            only: ['availableBeds', 'allBeds', 'hasAvailableBeds'],
            onSuccess: () => setBedAssignmentModalOpen(true),
        });
    };

    const handleRemoveBed = () => {
        if (confirm('Are you sure you want to remove the bed assignment?')) {
            router.delete(`/admissions/${admission.id}/bed-assignment`, {
                preserveScroll: true,
            });
        }
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

    // Merge consultation vitals with ward vitals, deduplicating by ID
    const allVitals = useMemo(() => {
        const vitalsMap = new Map<number, VitalSign>();

        // Add ward vitals first
        (admission.vital_signs || []).forEach((v) => vitalsMap.set(v.id, v));

        // Add consultation vitals (won't overwrite if same ID exists)
        (admission.consultation?.patient_checkin?.vital_signs || []).forEach(
            (v) => {
                if (!vitalsMap.has(v.id)) {
                    vitalsMap.set(v.id, v);
                }
            },
        );

        return Array.from(vitalsMap.values()).sort(
            (a, b) =>
                new Date(b.recorded_at).getTime() -
                new Date(a.recorded_at).getTime(),
        );
    }, [
        admission.vital_signs,
        admission.consultation?.patient_checkin?.vital_signs,
    ]);

    // Medication administrations
    const allMedications = admission.medication_administrations || [];

    // Today's count for reference
    const todayAdministrationsCount = allMedications.filter((med) =>
        isToday(new Date(med.administered_at)),
    ).length;

    const latestVital = allVitals[0];

    // Collect all prescriptions from consultation and ward rounds
    const allPrescriptions = [
        ...(admission.consultation?.prescriptions || []),
        ...(admission.ward_rounds?.flatMap(
            (round) => round.prescriptions || [],
        ) || []),
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

    // Vitals edit handler
    const handleEditVitals = (vital: VitalSign) => {
        setEditingVitals(vital);
        setVitalsModalMode('edit');
        setVitalsModalOpen(true);
    };

    const handleOpenNewVitals = () => {
        setEditingVitals(null);
        setVitalsModalMode('create');
        setVitalsModalOpen(true);
    };

    const handleCloseVitalsModal = () => {
        setVitalsModalOpen(false);
        setEditingVitals(null);
        setVitalsModalMode('create');
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

    const handleDischarge = () => {
        setIsDischarging(true);
        router.post(
            `/wards/${admission.ward?.id}/patients/${admission.id}/discharge`,
            { discharge_notes: dischargeNotes },
            {
                onSuccess: () => {
                    toast.success('Patient discharged successfully');
                    setDischargeModalOpen(false);
                    setDischargeNotes('');
                },
                onError: (errors: any) => {
                    const errorMessage =
                        errors.discharge ||
                        errors.admission ||
                        'Failed to discharge patient';
                    toast.error(errorMessage);
                },
                onFinish: () => {
                    setIsDischarging(false);
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

                    <div className="flex items-center gap-2">
                        {admission.consultation && (
                            <Link
                                href={`/consultation/${admission.consultation.id}`}
                            >
                                <Button
                                    variant="outline"
                                    className="border-blue-600 text-blue-600 hover:bg-blue-600 hover:text-white dark:border-blue-400 dark:text-blue-400 dark:hover:bg-blue-600 dark:hover:text-white"
                                >
                                    <FileText className="mr-2 h-4 w-4" />
                                    View Consultation
                                </Button>
                            </Link>
                        )}
                        {admission.status === 'admitted' &&
                            can_transfer &&
                            availableWards.length > 0 && (
                                <Button
                                    variant="outline"
                                    onClick={() => setTransferModalOpen(true)}
                                >
                                    <ArrowRightLeft className="mr-2 h-4 w-4" />
                                    Transfer Ward
                                </Button>
                            )}
                        {admission.status === 'admitted' && canDischarge && (
                            <Button
                                variant="destructive"
                                onClick={() => setDischargeModalOpen(true)}
                            >
                                <LogOut className="mr-2 h-4 w-4" />
                                Discharge Patient
                            </Button>
                        )}
                    </div>
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
                    <div className="rounded-lg border border-blue-200 bg-blue-50 p-4 transition-all hover:shadow-md dark:border-blue-800 dark:bg-blue-950">
                        <div className="mb-2 flex items-center justify-between">
                            <p className="text-sm font-medium text-blue-900 dark:text-blue-100">
                                Patient Information
                            </p>
                            <User className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div className="space-y-1 text-sm">
                            {admission.patient.date_of_birth && (
                                <p className="font-semibold text-blue-700 dark:text-blue-300">
                                    Age:{' '}
                                    {calculateAge(
                                        admission.patient.date_of_birth,
                                    )}{' '}
                                    years
                                </p>
                            )}
                            {admission.patient.gender && (
                                <p className="text-blue-600 dark:text-blue-400">
                                    {admission.patient.gender}
                                </p>
                            )}
                            {admission.patient.phone_number && (
                                <p className="text-xs text-blue-600 dark:text-blue-400">
                                    {admission.patient.phone_number}
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="rounded-lg border border-purple-200 bg-purple-50 p-4 transition-all hover:shadow-md dark:border-purple-800 dark:bg-purple-950">
                        <div className="mb-2 flex items-center justify-between">
                            <p className="text-sm font-medium text-purple-900 dark:text-purple-100">
                                Ward & Bed
                            </p>
                            {admission.bed ? (
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            className="h-6 px-2 text-purple-600 hover:bg-purple-100 hover:text-purple-700 dark:text-purple-400 dark:hover:bg-purple-900"
                                        >
                                            <MoreHorizontal className="h-4 w-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem onClick={loadBedData}>
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
                            ) : (
                                <Button
                                    size="sm"
                                    variant="default"
                                    className="h-6 bg-orange-500 px-2 text-white hover:bg-orange-600 dark:bg-orange-600 dark:hover:bg-orange-700"
                                    onClick={loadBedData}
                                >
                                    <BedIcon className="mr-1 h-3 w-3" />
                                    Assign Bed
                                </Button>
                            )}
                        </div>
                        <div className="space-y-1">
                            <p className="font-semibold text-purple-700 dark:text-purple-300">
                                {admission.bed
                                    ? `Bed ${admission.bed.bed_number}`
                                    : 'No bed assigned'}
                            </p>
                            {admission.ward && (
                                <p className="text-sm text-purple-600 dark:text-purple-400">
                                    {admission.ward.name} ({admission.ward.code}
                                    )
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="rounded-lg border border-green-200 bg-green-50 p-4 transition-all hover:shadow-md dark:border-green-800 dark:bg-green-950">
                        <div className="mb-2 flex items-center justify-between">
                            <p className="text-sm font-medium text-green-900 dark:text-green-100">
                                Attending Physician
                            </p>
                            <Stethoscope className="h-5 w-5 text-green-600 dark:text-green-400" />
                        </div>
                        {admission.consultation?.doctor ? (
                            <p className="font-semibold text-green-700 dark:text-green-300">
                                Dr. {admission.consultation.doctor.name}
                            </p>
                        ) : (
                            <p className="text-sm text-green-600 dark:text-green-400">
                                Not assigned
                            </p>
                        )}
                    </div>

                    <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 transition-all hover:shadow-md dark:border-amber-800 dark:bg-amber-950">
                        <div className="mb-2 flex items-center justify-between">
                            <p className="text-sm font-medium text-amber-900 dark:text-amber-100">
                                Admission
                            </p>
                            <Calendar className="h-5 w-5 text-amber-600 dark:text-amber-400" />
                        </div>
                        <p className="font-semibold text-amber-700 dark:text-amber-300">
                            Day {calculateAdmissionDays()}
                        </p>
                        <p className="text-xs text-amber-600 dark:text-amber-400">
                            {formatDateTime(admission.admitted_at)}
                        </p>
                    </div>

                    <div className="rounded-lg border border-teal-200 bg-teal-50 p-4 transition-all hover:shadow-md dark:border-teal-800 dark:bg-teal-950">
                        <div className="mb-2 flex items-center justify-between">
                            <p className="text-sm font-medium text-teal-900 dark:text-teal-100">
                                Insurance
                            </p>
                            <ShieldCheck className="h-5 w-5 text-teal-600 dark:text-teal-400" />
                        </div>
                        {admission.patient.active_insurance ? (
                            <div className="space-y-1">
                                <p className="font-semibold text-teal-700 dark:text-teal-300">
                                    {
                                        admission.patient.active_insurance.plan
                                            .provider.name
                                    }
                                </p>
                                <p className="text-xs text-teal-600 dark:text-teal-400">
                                    {
                                        admission.patient.active_insurance.plan
                                            .plan_name
                                    }{' '}
                                    (
                                    {
                                        admission.patient.active_insurance.plan
                                            .plan_type
                                    }
                                    )
                                </p>
                                <p className="text-xs text-teal-600 dark:text-teal-400">
                                    Member:{' '}
                                    {
                                        admission.patient.active_insurance
                                            .member_number
                                    }
                                </p>
                            </div>
                        ) : (
                            <p className="text-sm text-teal-600 dark:text-teal-400">
                                No active insurance
                            </p>
                        )}
                    </div>
                </div>

                {/* Alert Badges */}
                {(admission.is_overflow_patient ||
                    !admission.bed ||
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
                <Tabs
                    value={activeTab}
                    onValueChange={setActiveTab}
                    defaultValue="overview"
                    className="w-full"
                >
                    <TabsList className="grid w-full grid-cols-7 gap-1 rounded-none border-b border-gray-200 bg-transparent p-1 dark:border-gray-700">
                        <TabsTrigger
                            value="overview"
                            className="flex items-center gap-2 rounded-md border-b-2 border-transparent bg-slate-50 text-slate-700 shadow-none transition-all hover:bg-slate-100 data-[state=active]:border-slate-600 data-[state=active]:bg-slate-100 data-[state=active]:text-slate-700 data-[state=active]:shadow-none dark:bg-slate-950 dark:text-slate-300 dark:hover:bg-slate-900 dark:data-[state=active]:border-slate-400 dark:data-[state=active]:bg-slate-900 dark:data-[state=active]:text-slate-300"
                        >
                            <LayoutDashboard className="h-4 w-4" />
                            Overview
                        </TabsTrigger>
                        <TabsTrigger
                            value="vitals"
                            className="flex items-center gap-2 rounded-md border-b-2 border-transparent bg-rose-50 text-rose-700 shadow-none transition-all hover:bg-rose-100 data-[state=active]:border-rose-600 data-[state=active]:bg-rose-100 data-[state=active]:text-rose-700 data-[state=active]:shadow-none dark:bg-rose-950 dark:text-rose-300 dark:hover:bg-rose-900 dark:data-[state=active]:border-rose-400 dark:data-[state=active]:bg-rose-900 dark:data-[state=active]:text-rose-300"
                        >
                            <Heart className="h-4 w-4" />
                            Vital Signs
                        </TabsTrigger>
                        <TabsTrigger
                            value="medications"
                            className="flex items-center gap-2 rounded-md border-b-2 border-transparent bg-green-50 text-green-700 shadow-none transition-all hover:bg-green-100 data-[state=active]:border-green-600 data-[state=active]:bg-green-100 data-[state=active]:text-green-700 data-[state=active]:shadow-none dark:bg-green-950 dark:text-green-300 dark:hover:bg-green-900 dark:data-[state=active]:border-green-400 dark:data-[state=active]:bg-green-900 dark:data-[state=active]:text-green-300"
                            title="Medication Administration Record"
                        >
                            <Pill className="h-4 w-4" />
                            MAR
                            {allMedications.length > 0 && (
                                <Badge variant="secondary" className="ml-1">
                                    {allMedications.length}
                                </Badge>
                            )}
                        </TabsTrigger>
                        <TabsTrigger
                            value="history"
                            className="flex items-center gap-2 rounded-md border-b-2 border-transparent bg-emerald-50 text-emerald-700 shadow-none transition-all hover:bg-emerald-100 data-[state=active]:border-emerald-600 data-[state=active]:bg-emerald-100 data-[state=active]:text-emerald-700 data-[state=active]:shadow-none dark:bg-emerald-950 dark:text-emerald-300 dark:hover:bg-emerald-900 dark:data-[state=active]:border-emerald-400 dark:data-[state=active]:bg-emerald-900 dark:data-[state=active]:text-emerald-300"
                            title="View prescription history"
                        >
                            <ClipboardList className="h-4 w-4" />
                            Rx History
                            {allPrescriptions.length > 0 && (
                                <Badge variant="secondary" className="ml-1">
                                    {allPrescriptions.length}
                                </Badge>
                            )}
                        </TabsTrigger>
                        <TabsTrigger
                            value="labs"
                            className="flex items-center gap-2 rounded-md border-b-2 border-transparent bg-teal-50 text-teal-700 shadow-none transition-all hover:bg-teal-100 data-[state=active]:border-teal-600 data-[state=active]:bg-teal-100 data-[state=active]:text-teal-700 data-[state=active]:shadow-none dark:bg-teal-950 dark:text-teal-300 dark:hover:bg-teal-900 dark:data-[state=active]:border-teal-400 dark:data-[state=active]:bg-teal-900 dark:data-[state=active]:text-teal-300"
                        >
                            <FlaskConical className="h-4 w-4" />
                            Labs
                            {allLabOrders.length > 0 && (
                                <Badge variant="secondary" className="ml-1">
                                    {allLabOrders.length}
                                </Badge>
                            )}
                        </TabsTrigger>
                        <TabsTrigger
                            value="notes"
                            className="flex items-center gap-2 rounded-md border-b-2 border-transparent bg-blue-50 text-blue-700 shadow-none transition-all hover:bg-blue-100 data-[state=active]:border-blue-600 data-[state=active]:bg-blue-100 data-[state=active]:text-blue-700 data-[state=active]:shadow-none dark:bg-blue-950 dark:text-blue-300 dark:hover:bg-blue-900 dark:data-[state=active]:border-blue-400 dark:data-[state=active]:bg-blue-900 dark:data-[state=active]:text-blue-300"
                        >
                            <FileText className="h-4 w-4" />
                            Nursing Notes
                        </TabsTrigger>
                        <TabsTrigger
                            value="rounds"
                            className="flex items-center gap-2 rounded-md border-b-2 border-transparent bg-violet-50 text-violet-700 shadow-none transition-all hover:bg-violet-100 data-[state=active]:border-violet-600 data-[state=active]:bg-violet-100 data-[state=active]:text-violet-700 data-[state=active]:shadow-none dark:bg-violet-950 dark:text-violet-300 dark:hover:bg-violet-900 dark:data-[state=active]:border-violet-400 dark:data-[state=active]:bg-violet-900 dark:data-[state=active]:text-violet-300"
                        >
                            <Stethoscope className="h-4 w-4" />
                            Ward Rounds
                        </TabsTrigger>
                    </TabsList>

                    {/* Overview Tab */}
                    <TabsContent value="overview">
                        <OverviewTab
                            admission={admission as any}
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
                                                    onClick={
                                                        handleOpenNewVitals
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
                                                onClick={handleOpenNewVitals}
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

                            {/* Vitals Table - Full Width */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Vitals History</CardTitle>
                                </CardHeader>
                                <CardContent className="max-h-[300px] overflow-y-auto">
                                    <VitalsTable
                                        vitals={allVitals}
                                        onEdit={handleEditVitals}
                                    />
                                </CardContent>
                            </Card>

                            {/* Vitals Charts - 2 columns */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Vitals Trends</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <VitalsChart vitals={allVitals} />
                                </CardContent>
                            </Card>
                        </div>
                    </TabsContent>

                    {/* Medication Administration Tab */}
                    <TabsContent value="medications">
                        <div className="space-y-6">
                            <div className="flex items-center justify-between">
                                <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    Medication Administration Record
                                </h3>
                                <Button
                                    onClick={() => setMedicationPanelOpen(true)}
                                >
                                    <Pill className="mr-2 h-4 w-4" />
                                    Record Medication
                                </Button>
                            </div>

                            <MARTable
                                administrations={allMedications}
                                prescriptions={allPrescriptions}
                                admissionId={admission.id}
                                canDelete={can_delete_medication_administration}
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
                                                                        setNoteToEdit(note);
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
                            onDiscontinue={(prescriptionId) => {
                                const prescription = allPrescriptions.find(
                                    (p) => p.id === prescriptionId,
                                );
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
                    onClose={handleCloseVitalsModal}
                    admission={admission}
                    onSuccess={handleCloseVitalsModal}
                    mode={vitalsModalMode}
                    editVitals={editingVitals}
                    canEditTimestamp={can_edit_vitals_timestamp}
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
                    prescriptions={allPrescriptions}
                    todayAdministrations={allMedications}
                    open={medicationPanelOpen}
                    onOpenChange={setMedicationPanelOpen}
                    canEditTimestamp={can_edit_medication_timestamp}
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

                {/* Edit Nursing Note Dialog */}
                <Dialog
                    open={!!noteToEdit}
                    onOpenChange={(open) => !open && setNoteToEdit(null)}
                >
                    <DialogContent className="max-w-lg">
                        <DialogHeader>
                            <DialogTitle>Edit Nursing Note</DialogTitle>
                            <DialogDescription>
                                Update the nursing note details
                            </DialogDescription>
                        </DialogHeader>
                        {noteToEdit && (
                            <Form
                                action={`/admissions/${admission.id}/nursing-notes/${noteToEdit.id}`}
                                method="put"
                                onSuccess={() => {
                                    toast.success('Nursing note updated successfully');
                                    setNoteToEdit(null);
                                }}
                                onError={() => {
                                    toast.error('Failed to update nursing note');
                                }}
                                className="space-y-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <div className="space-y-2">
                                            <Label htmlFor="edit-type">Note Type</Label>
                                            <Select name="type" defaultValue={noteToEdit.type}>
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="assessment">Assessment</SelectItem>
                                                    <SelectItem value="care">Care</SelectItem>
                                                    <SelectItem value="observation">Observation</SelectItem>
                                                    <SelectItem value="incident">Incident</SelectItem>
                                                    <SelectItem value="handover">Handover</SelectItem>
                                                </SelectContent>
                                            </Select>
                                            {errors.type && (
                                                <p className="text-sm text-destructive">{errors.type}</p>
                                            )}
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="edit-note">Note</Label>
                                            <Textarea
                                                name="note"
                                                id="edit-note"
                                                defaultValue={noteToEdit.note}
                                                rows={5}
                                                required
                                                minLength={10}
                                            />
                                            {errors.note && (
                                                <p className="text-sm text-destructive">{errors.note}</p>
                                            )}
                                        </div>
                                        <div className="flex justify-end gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() => setNoteToEdit(null)}
                                            >
                                                Cancel
                                            </Button>
                                            <Button type="submit" disabled={processing}>
                                                {processing && (
                                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                )}
                                                Save Changes
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        )}
                    </DialogContent>
                </Dialog>

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

                {/* Discharge Patient Modal */}
                <AlertDialog
                    open={dischargeModalOpen}
                    onOpenChange={(open) => {
                        if (!open) {
                            setDischargeModalOpen(false);
                            setDischargeNotes('');
                        }
                    }}
                >
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>
                                Discharge Patient
                            </AlertDialogTitle>
                            <AlertDialogDescription>
                                Are you sure you want to discharge{' '}
                                <span className="font-semibold">
                                    {admission.patient.first_name}{' '}
                                    {admission.patient.last_name}
                                </span>
                                ? This will free up the bed and end the
                                admission.
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <div className="py-4">
                            <label
                                htmlFor="discharge-notes"
                                className="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                Discharge Notes (optional)
                            </label>
                            <textarea
                                id="discharge-notes"
                                value={dischargeNotes}
                                onChange={(e) =>
                                    setDischargeNotes(e.target.value)
                                }
                                placeholder="Enter any discharge notes..."
                                className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm placeholder:text-gray-400 focus:ring-2 focus:ring-primary focus:outline-none dark:border-gray-600 dark:bg-gray-800"
                                rows={3}
                            />
                        </div>
                        <AlertDialogFooter>
                            <AlertDialogCancel disabled={isDischarging}>
                                Cancel
                            </AlertDialogCancel>
                            <AlertDialogAction
                                onClick={handleDischarge}
                                disabled={isDischarging}
                                className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                            >
                                {isDischarging
                                    ? 'Discharging...'
                                    : 'Discharge Patient'}
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>

                {/* Ward Transfer Modal */}
                <WardTransferModal
                    open={transferModalOpen}
                    onOpenChange={setTransferModalOpen}
                    admissionId={admission.id}
                    currentWardName={admission.ward?.name || 'Current Ward'}
                    patientName={`${admission.patient.first_name} ${admission.patient.last_name}`}
                    availableWards={availableWards}
                />
            </div>
        </AppLayout>
    );
}
