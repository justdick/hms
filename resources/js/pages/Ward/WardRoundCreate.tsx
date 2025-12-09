import DiagnosisFormSection from '@/components/Consultation/DiagnosisFormSection';
import MedicalHistoryNotes from '@/components/Consultation/MedicalHistoryNotes';
import { PatientHistorySidebar } from '@/components/Consultation/PatientHistorySidebar';
import PrescriptionFormSection from '@/components/Consultation/PrescriptionFormSection';
import WardRoundProceduresTab from '@/components/Ward/WardRoundProceduresTab';
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
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { Head, router, useForm } from '@inertiajs/react';
import {
    Activity,
    Bed,
    Building,
    Clock,
    FileText,
    Heart,
    Pill,
    Plus,
    Stethoscope,
    TestTube,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

interface Patient {
    id: number;
    first_name: string;
    last_name: string;
    date_of_birth: string;
    gender: string;
    phone_number: string;
    email?: string;
}

interface Ward {
    id: number;
    name: string;
    code: string;
    available_beds: number;
}

interface VitalSigns {
    id: number;
    temperature: number;
    blood_pressure_systolic: number;
    blood_pressure_diastolic: number;
    pulse_rate: number;
    respiratory_rate: number;
    recorded_at: string;
}

interface Doctor {
    id: number;
    name: string;
}

interface DiagnosisDisplay {
    id: number;
    diagnosis: string;
    code: string;
    g_drg: string;
    icd_10: string;
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

interface Drug {
    id: number;
    name: string;
    generic_name?: string;
    brand_name?: string;
    drug_code: string;
    form: string;
    strength?: string;
    unit_price: number;
    unit_type: string;
    bottle_size?: number;
}

interface Prescription {
    id: number;
    medication_name: string;
    frequency: string;
    duration: string;
    instructions?: string;
    status: string;
    drug?: Drug;
}

interface LabService {
    id: number;
    name: string;
    code: string;
    category: string;
    price: number;
    sample_type: string;
}

interface LabOrder {
    id: number;
    lab_service: LabService;
    status:
        | 'ordered'
        | 'sample_collected'
        | 'in_progress'
        | 'completed'
        | 'cancelled';
    priority: 'routine' | 'urgent' | 'stat';
    special_instructions?: string;
    ordered_at: string;
    sample_collected_at?: string;
    result_entered_at?: string;
    result_values?: any;
    result_notes?: string;
    ordered_by?: {
        id: number;
        name: string;
    };
}

interface PatientAdmission {
    id: number;
    patient: Patient;
    ward: Ward;
    bed_number?: string;
    admission_date: string;
    discharge_date?: string;
    admission_reason: string;
    admission_notes?: string;
    status: 'admitted' | 'discharged' | 'transferred';
    latest_vital_signs?: VitalSigns[];
}

interface ProcedureType {
    id: number;
    name: string;
    code: string;
    type: 'minor' | 'major';
    category: string;
    price: number;
}

interface WardRoundProcedure {
    id: number;
    procedure_type: ProcedureType;
    comments: string | null;
    performed_at: string;
    doctor: {
        id: number;
        name: string;
    };
}

interface WardRound {
    id: number;
    presenting_complaint?: string;
    history_presenting_complaint?: string;
    on_direct_questioning?: string;
    examination_findings?: string;
    assessment_notes?: string;
    plan_notes?: string;
    status: 'in_progress' | 'completed';
    created_at: string;
    doctor: Doctor;
    diagnoses: WardRoundDiagnosis[];
    prescriptions: Prescription[];
    lab_orders: LabOrder[];
    procedures?: WardRoundProcedure[];
}

interface Props {
    admission: PatientAdmission;
    wardRound: WardRound;
    dayNumber: number;
    labServices: LabService[];
    availableDrugs: Drug[];
    availableProcedures?: ProcedureType[];
    patientHistories: {
        past_medical_surgical_history: string;
        drug_history: string;
        family_history: string;
        social_history: string;
    };
    patientHistory?: {
        previousWardRounds?: WardRound[];
        previousPrescriptions?: Prescription[];
        allergies?: string[];
    };
}

export default function WardRoundCreate({
    admission,
    wardRound,
    dayNumber,
    labServices,
    availableDrugs = [],
    availableProcedures = [],
    patientHistories,
    patientHistory,
}: Props) {
    const [activeTab, setActiveTab] = useState('notes');
    const [isSaving, setIsSaving] = useState(false);
    const [lastSaved, setLastSaved] = useState<Date | null>(null);
    const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);
    const autoSaveTimeout = useRef<NodeJS.Timeout | null>(null);

    // Lab order state
    const [showLabOrderDialog, setShowLabOrderDialog] = useState(false);
    const {
        data: labOrderData,
        setData: setLabOrderData,
        post: postLabOrder,
        processing: labOrderProcessing,
        reset: resetLabOrder,
    } = useForm({
        lab_service_id: '',
        priority: 'routine',
        special_instructions: '',
    });

    const [showCompleteDialog, setShowCompleteDialog] = useState(false);
    const [deleteDialogState, setDeleteDialogState] = useState<{
        open: boolean;
        type: 'diagnosis' | 'prescription' | 'laborder';
        id: number | null;
    }>({ open: false, type: 'diagnosis', id: null });

    // Main form data for ward round - matching consultation structure
    const { data, setData, patch, processing } = useForm({
        presenting_complaint: wardRound.presenting_complaint || '',
        history_presenting_complaint:
            wardRound.history_presenting_complaint || '',
        on_direct_questioning: wardRound.on_direct_questioning || '',
        examination_findings: wardRound.examination_findings || '',
        assessment_notes: wardRound.assessment_notes || '',
        plan_notes: wardRound.plan_notes || '',
        follow_up_date: '',
        past_medical_surgical_history:
            patientHistories.past_medical_surgical_history,
        drug_history: patientHistories.drug_history,
        family_history: patientHistories.family_history,
        social_history: patientHistories.social_history,
    });

    const {
        data: prescriptionData,
        setData: setPrescriptionData,
        post: postPrescription,
        processing: prescriptionProcessing,
        reset: resetPrescription,
    } = useForm({
        drug_id: null as number | null,
        medication_name: '',
        frequency: '',
        duration: '',
        instructions: '',
    });

    // Auto-save function
    const autoSave = useCallback(() => {
        if (!hasUnsavedChanges || wardRound.status !== 'in_progress') {
            return;
        }

        setIsSaving(true);
        patch(`/admissions/${admission.id}/ward-rounds/${wardRound.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setIsSaving(false);
                setHasUnsavedChanges(false);
                setLastSaved(new Date());
            },
            onError: () => {
                setIsSaving(false);
            },
        });
    }, [
        hasUnsavedChanges,
        wardRound.id,
        wardRound.status,
        admission.id,
        patch,
    ]);

    // Track changes and trigger auto-save
    useEffect(() => {
        if (hasUnsavedChanges) {
            // Clear existing timeout
            if (autoSaveTimeout.current) {
                clearTimeout(autoSaveTimeout.current);
            }

            // Set new timeout for auto-save (3 seconds after last change)
            autoSaveTimeout.current = setTimeout(() => {
                autoSave();
            }, 3000);
        }

        return () => {
            if (autoSaveTimeout.current) {
                clearTimeout(autoSaveTimeout.current);
            }
        };
    }, [hasUnsavedChanges, autoSave]);

    // Warn before leaving with unsaved changes
    useEffect(() => {
        const handleBeforeUnload = (e: BeforeUnloadEvent) => {
            if (hasUnsavedChanges) {
                e.preventDefault();
                e.returnValue = '';
            }
        };

        window.addEventListener('beforeunload', handleBeforeUnload);
        return () =>
            window.removeEventListener('beforeunload', handleBeforeUnload);
    }, [hasUnsavedChanges]);

    // Custom setData wrapper to track changes
    const handleDataChange = (field: string, value: string) => {
        setData(field as any, value);
        setHasUnsavedChanges(true);
    };

    const handlePatientHistoryUpdate = (field: string, value: string) => {
        setData(field as any, value);
        setHasUnsavedChanges(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setIsSaving(true);
        patch(`/admissions/${admission.id}/ward-rounds/${wardRound.id}`, {
            onSuccess: () => {
                setIsSaving(false);
                setHasUnsavedChanges(false);
                setLastSaved(new Date());
            },
            onError: () => {
                setIsSaving(false);
            },
        });
    };

    const handlePrescriptionSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        postPrescription(
            `/admissions/${admission.id}/ward-rounds/${wardRound.id}/prescriptions`,
            {
                onSuccess: () => {
                    resetPrescription();
                },
            },
        );
    };

    const handleDiagnosisAdd = (
        diagnosisId: number,
        type: 'provisional' | 'principal',
    ) => {
        // Map consultation types to ward round types
        const diagnosis_type: 'working' | 'complication' | 'comorbidity' =
            type === 'provisional' ? 'working' : 'comorbidity';

        router.post(
            `/admissions/${admission.id}/ward-rounds/${wardRound.id}/diagnoses`,
            {
                diagnosis_id: diagnosisId,
                diagnosis_type: diagnosis_type,
            },
            {
                preserveScroll: true,
            },
        );
    };

    const handleLabOrderSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        postLabOrder(
            `/admissions/${admission.id}/ward-rounds/${wardRound.id}/lab-orders`,
            {
                onSuccess: () => {
                    resetLabOrder();
                    setShowLabOrderDialog(false);
                },
            },
        );
    };

    const handleDelete = () => {
        if (deleteDialogState.id === null) return;

        if (deleteDialogState.type === 'diagnosis') {
            router.delete(
                `/admissions/${admission.id}/ward-rounds/${wardRound.id}/diagnoses/${deleteDialogState.id}`,
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setDeleteDialogState({
                            open: false,
                            type: 'diagnosis',
                            id: null,
                        });
                    },
                },
            );
        } else if (deleteDialogState.type === 'prescription') {
            router.delete(
                `/admissions/${admission.id}/ward-rounds/${wardRound.id}/prescriptions/${deleteDialogState.id}`,
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setDeleteDialogState({
                            open: false,
                            type: 'prescription',
                            id: null,
                        });
                    },
                },
            );
        } else if (deleteDialogState.type === 'laborder') {
            router.delete(
                `/admissions/${admission.id}/ward-rounds/${wardRound.id}/lab-orders/${deleteDialogState.id}`,
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setDeleteDialogState({
                            open: false,
                            type: 'laborder',
                            id: null,
                        });
                    },
                },
            );
        }
    };

    const handleCompleteWardRound = () => {
        router.post(
            `/admissions/${admission.id}/ward-rounds/${wardRound.id}/complete`,
            {
                ...data,
            },
            {
                onSuccess: () => {
                    router.visit(
                        `/wards/${admission.ward.id}/patients/${admission.id}`,
                    );
                },
            },
        );
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

    const calculateAge = (dateOfBirth: string) => {
        const today = new Date();
        const birth = new Date(dateOfBirth);
        let age = today.getFullYear() - birth.getFullYear();
        const monthDiff = today.getMonth() - birth.getMonth();

        if (
            monthDiff < 0 ||
            (monthDiff === 0 && today.getDate() < birth.getDate())
        ) {
            age--;
        }

        return age;
    };

    const latestVitals = admission.latest_vital_signs?.[0];

    const breadcrumbs = [
        { title: 'Wards', href: '/wards' },
        { title: admission.ward.name, href: `/wards/${admission.ward.id}` },
        {
            title: `${admission.patient.first_name} ${admission.patient.last_name}`,
            href: `/wards/${admission.ward.id}/patients/${admission.id}`,
        },
        { title: `Ward Round - Day ${dayNumber}`, href: '' },
    ];

    const pageTitle = `Ward Round - Day ${dayNumber} - ${admission.patient.first_name} ${admission.patient.last_name}`;

    // Convert ward round diagnoses to display format
    // Convert ward round diagnoses to display format
    const displayDiagnoses = (wardRound.diagnoses || []).map((wd) => {
        // Create diagnosis object from ward round diagnosis data
        const diagnosis = {
            id: 0, // Temporary ID since it's already saved
            diagnosis: wd.diagnosis_name,
            code: wd.icd_code,
            g_drg: '',
            icd_10: wd.icd_code,
        };

        // Map ward round diagnosis types to consultation types
        const type =
            wd.diagnosis_type === 'working' ? 'provisional' : 'principal';

        return {
            id: wd.id,
            diagnosis: diagnosis,
            type: type as 'provisional' | 'principal',
        };
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={pageTitle} />

            <div className="space-y-6">
                {/* Header - Compact */}
                <div className="rounded-lg border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-950">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-6">
                            <div>
                                <h1 className="text-xl font-bold text-gray-900 dark:text-gray-100">
                                    {admission.patient.first_name}{' '}
                                    {admission.patient.last_name}
                                </h1>
                                <div className="mt-1 flex items-center gap-3 text-sm text-gray-600 dark:text-gray-400">
                                    <span>
                                        Age:{' '}
                                        {calculateAge(
                                            admission.patient.date_of_birth,
                                        )}
                                    </span>
                                    <span>•</span>
                                    <span className="capitalize">
                                        {admission.patient.gender}
                                    </span>
                                </div>
                            </div>

                            <div className="flex items-center gap-4 text-sm">
                                <div className="flex items-center gap-2">
                                    <Building className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                    <span className="text-gray-700 dark:text-gray-300">
                                        {admission.ward.name}
                                    </span>
                                </div>
                                <Separator
                                    orientation="vertical"
                                    className="h-4"
                                />
                                {admission.bed_number && (
                                    <>
                                        <div className="flex items-center gap-2">
                                            <Bed className="h-4 w-4 text-green-600 dark:text-green-400" />
                                            <span className="text-gray-700 dark:text-gray-300">
                                                Bed {admission.bed_number}
                                            </span>
                                        </div>
                                        <Separator
                                            orientation="vertical"
                                            className="h-4"
                                        />
                                    </>
                                )}
                                <div className="flex items-center gap-2">
                                    <Clock className="h-4 w-4 text-purple-600 dark:text-purple-400" />
                                    <span className="text-gray-700 dark:text-gray-300">
                                        Day {dayNumber}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div className="flex items-center gap-3">
                            <Badge variant="default">New Ward Round</Badge>
                        </div>
                    </div>
                </div>

                {/* Latest Vitals Quick View */}
                {latestVitals && (
                    <div className="rounded-lg border-l-4 border-green-600 bg-green-50 p-4 shadow-sm dark:border-green-400 dark:bg-green-950">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <Activity className="h-5 w-5 text-green-600 dark:text-green-400" />
                                <div>
                                    <h3 className="font-semibold text-green-900 dark:text-green-100">
                                        Latest Vital Signs
                                    </h3>
                                    <p className="text-sm text-green-700 dark:text-green-300">
                                        Recorded:{' '}
                                        {formatDateTime(
                                            latestVitals.recorded_at,
                                        )}
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-4 text-sm">
                                <div className="flex items-center gap-1.5">
                                    <Heart className="h-4 w-4 text-red-500 dark:text-red-400" />
                                    <span className="text-green-700 dark:text-green-300">
                                        BP:{' '}
                                    </span>
                                    <span className="font-medium text-green-900 dark:text-green-100">
                                        {latestVitals.blood_pressure_systolic}/
                                        {latestVitals.blood_pressure_diastolic}
                                    </span>
                                </div>
                                <div className="flex items-center gap-1.5">
                                    <Activity className="h-4 w-4 text-green-500 dark:text-green-400" />
                                    <span className="text-green-700 dark:text-green-300">
                                        HR:{' '}
                                    </span>
                                    <span className="font-medium text-green-900 dark:text-green-100">
                                        {latestVitals.pulse_rate} bpm
                                    </span>
                                </div>
                                <div className="flex items-center gap-1.5">
                                    <Activity className="h-4 w-4 text-blue-500 dark:text-blue-400" />
                                    <span className="text-green-700 dark:text-green-300">
                                        Temp:{' '}
                                    </span>
                                    <span className="font-medium text-green-900 dark:text-green-100">
                                        {latestVitals.temperature}°F
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* Quick Actions */}
                <div className="mb-6 flex items-center justify-between gap-4">
                    <div className="flex gap-4">
                        <PatientHistorySidebar
                            previousConsultations={[]}
                            allergies={patientHistory?.allergies || []}
                        />
                        <Button
                            onClick={() => setShowCompleteDialog(true)}
                            variant="default"
                            className="bg-green-600 hover:bg-green-700 dark:bg-green-600 dark:hover:bg-green-700"
                        >
                            <Stethoscope className="mr-2 h-4 w-4" />
                            Complete Ward Round
                        </Button>
                    </div>
                </div>

                <Tabs
                    value={activeTab}
                    onValueChange={setActiveTab}
                    className="w-full"
                >
                    <TabsList className="grid w-full grid-cols-6">
                        <TabsTrigger
                            value="notes"
                            className="flex items-center gap-2"
                        >
                            <FileText className="h-4 w-4" />
                            Consultation Notes
                        </TabsTrigger>
                        <TabsTrigger
                            value="vitals"
                            className="flex items-center gap-2"
                        >
                            <Activity className="h-4 w-4" />
                            Vitals
                        </TabsTrigger>
                        <TabsTrigger
                            value="diagnosis"
                            className="flex items-center gap-2"
                        >
                            <FileText className="h-4 w-4" />
                            Diagnosis
                        </TabsTrigger>
                        <TabsTrigger
                            value="prescriptions"
                            className="flex items-center gap-2"
                        >
                            <Pill className="h-4 w-4" />
                            Prescriptions
                        </TabsTrigger>
                        <TabsTrigger
                            value="orders"
                            className="flex items-center gap-2"
                        >
                            <TestTube className="h-4 w-4" />
                            Lab Orders
                        </TabsTrigger>
                        <TabsTrigger
                            value="theatre"
                            className="flex items-center gap-2"
                        >
                            <Stethoscope className="h-4 w-4" />
                            Theatre
                        </TabsTrigger>
                    </TabsList>

                    {/* Medical History & Consultation Notes Tab */}
                    <TabsContent value="notes">
                        <div className="space-y-6">
                            <MedicalHistoryNotes
                                initialData={{
                                    presenting_complaint:
                                        data.presenting_complaint,
                                    history_presenting_complaint:
                                        data.history_presenting_complaint,
                                    on_direct_questioning:
                                        data.on_direct_questioning,
                                    examination_findings:
                                        data.examination_findings,
                                    assessment_notes: data.assessment_notes,
                                    plan_notes: data.plan_notes,
                                    follow_up_date: data.follow_up_date,
                                }}
                                patientHistories={{
                                    past_medical_surgical_history:
                                        data.past_medical_surgical_history,
                                    drug_history: data.drug_history,
                                    family_history: data.family_history,
                                    social_history: data.social_history,
                                }}
                                onDataChange={(newData) => {
                                    Object.keys(newData).forEach((key) => {
                                        handleDataChange(key, newData[key]);
                                    });
                                }}
                                onPatientHistoryUpdate={
                                    handlePatientHistoryUpdate
                                }
                                onSubmit={handleSubmit}
                                processing={processing || isSaving}
                                status={wardRound.status}
                            />
                        </div>
                    </TabsContent>

                    {/* Vitals Tab */}
                    <TabsContent value="vitals">
                        <div className="space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Current Vital Signs</CardTitle>
                                    {latestVitals && (
                                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                            Recorded on{' '}
                                            {formatDateTime(
                                                latestVitals.recorded_at,
                                            )}
                                        </p>
                                    )}
                                </CardHeader>
                                <CardContent>
                                    {latestVitals ? (
                                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
                                            <div className="rounded-lg border border-blue-200 bg-blue-50 p-6 transition-all hover:shadow-md dark:border-blue-800 dark:bg-blue-950">
                                                <div className="mb-3 flex items-center justify-between">
                                                    <p className="text-sm font-medium text-blue-900 dark:text-blue-100">
                                                        Temperature
                                                    </p>
                                                    <Activity className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                                </div>
                                                <p className="text-3xl font-bold text-blue-600 dark:text-blue-400">
                                                    {latestVitals.temperature}°F
                                                </p>
                                                <p className="mt-2 text-xs text-blue-700 dark:text-blue-300">
                                                    Normal: 97-99°F
                                                </p>
                                            </div>
                                            <div className="rounded-lg border border-red-200 bg-red-50 p-6 transition-all hover:shadow-md dark:border-red-800 dark:bg-red-950">
                                                <div className="mb-3 flex items-center justify-between">
                                                    <p className="text-sm font-medium text-red-900 dark:text-red-100">
                                                        Blood Pressure
                                                    </p>
                                                    <Activity className="h-5 w-5 text-red-600 dark:text-red-400" />
                                                </div>
                                                <p className="text-3xl font-bold text-red-600 dark:text-red-400">
                                                    {
                                                        latestVitals.blood_pressure_systolic
                                                    }
                                                    /
                                                    {
                                                        latestVitals.blood_pressure_diastolic
                                                    }
                                                </p>
                                                <p className="mt-2 text-xs text-red-700 dark:text-red-300">
                                                    Normal: 90-120/60-80 mmHg
                                                </p>
                                            </div>
                                            <div className="rounded-lg border border-green-200 bg-green-50 p-6 transition-all hover:shadow-md dark:border-green-800 dark:bg-green-950">
                                                <div className="mb-3 flex items-center justify-between">
                                                    <p className="text-sm font-medium text-green-900 dark:text-green-100">
                                                        Heart Rate
                                                    </p>
                                                    <Activity className="h-5 w-5 text-green-600 dark:text-green-400" />
                                                </div>
                                                <p className="text-3xl font-bold text-green-600 dark:text-green-400">
                                                    {latestVitals.pulse_rate}
                                                </p>
                                                <p className="mt-2 text-xs text-green-700 dark:text-green-300">
                                                    bpm • Normal: 60-100
                                                </p>
                                            </div>
                                            <div className="rounded-lg border border-purple-200 bg-purple-50 p-6 transition-all hover:shadow-md dark:border-purple-800 dark:bg-purple-950">
                                                <div className="mb-3 flex items-center justify-between">
                                                    <p className="text-sm font-medium text-purple-900 dark:text-purple-100">
                                                        Respiratory Rate
                                                    </p>
                                                    <Activity className="h-5 w-5 text-purple-600 dark:text-purple-400" />
                                                </div>
                                                <p className="text-3xl font-bold text-purple-600 dark:text-purple-400">
                                                    {
                                                        latestVitals.respiratory_rate
                                                    }
                                                </p>
                                                <p className="mt-2 text-xs text-purple-700 dark:text-purple-300">
                                                    /min • Normal: 12-20
                                                </p>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="py-16 text-center text-gray-500 dark:text-gray-400">
                                            <Activity className="mx-auto mb-4 h-16 w-16 text-gray-300 dark:text-gray-600" />
                                            <p className="text-lg font-medium">
                                                No vital signs recorded
                                            </p>
                                            <p className="mt-2 text-sm">
                                                Vital signs will appear here
                                                once recorded by nursing staff
                                            </p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Previous Vitals History */}
                            {admission.latest_vital_signs &&
                                admission.latest_vital_signs.length > 1 && (
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>
                                                Vitals History
                                            </CardTitle>
                                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                                Previous{' '}
                                                {admission.latest_vital_signs
                                                    .length - 1}{' '}
                                                recording(s)
                                            </p>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="space-y-4">
                                                {admission.latest_vital_signs
                                                    .slice(1)
                                                    .map((vitals, index) => (
                                                        <div
                                                            key={vitals.id}
                                                            className="rounded-lg border bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900"
                                                        >
                                                            <div className="mb-3 flex items-center justify-between">
                                                                <h4 className="font-semibold text-gray-900 dark:text-gray-100">
                                                                    Recording #
                                                                    {index + 2}
                                                                </h4>
                                                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                                                    {formatDateTime(
                                                                        vitals.recorded_at,
                                                                    )}
                                                                </p>
                                                            </div>
                                                            <div className="grid grid-cols-2 gap-4 text-sm md:grid-cols-4">
                                                                <div>
                                                                    <p className="text-gray-500 dark:text-gray-400">
                                                                        Temperature
                                                                    </p>
                                                                    <p className="font-medium text-gray-900 dark:text-gray-100">
                                                                        {
                                                                            vitals.temperature
                                                                        }
                                                                        °F
                                                                    </p>
                                                                </div>
                                                                <div>
                                                                    <p className="text-gray-500 dark:text-gray-400">
                                                                        Blood
                                                                        Pressure
                                                                    </p>
                                                                    <p className="font-medium text-gray-900 dark:text-gray-100">
                                                                        {
                                                                            vitals.blood_pressure_systolic
                                                                        }
                                                                        /
                                                                        {
                                                                            vitals.blood_pressure_diastolic
                                                                        }
                                                                    </p>
                                                                </div>
                                                                <div>
                                                                    <p className="text-gray-500 dark:text-gray-400">
                                                                        Heart
                                                                        Rate
                                                                    </p>
                                                                    <p className="font-medium text-gray-900 dark:text-gray-100">
                                                                        {
                                                                            vitals.pulse_rate
                                                                        }{' '}
                                                                        bpm
                                                                    </p>
                                                                </div>
                                                                <div>
                                                                    <p className="text-gray-500 dark:text-gray-400">
                                                                        Respiratory
                                                                    </p>
                                                                    <p className="font-medium text-gray-900 dark:text-gray-100">
                                                                        {
                                                                            vitals.respiratory_rate
                                                                        }
                                                                        /min
                                                                    </p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    ))}
                                            </div>
                                        </CardContent>
                                    </Card>
                                )}
                        </div>
                    </TabsContent>

                    {/* Diagnosis Tab */}
                    <TabsContent value="diagnosis">
                        <Card>
                            <CardHeader>
                                <CardTitle>Diagnoses</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <DiagnosisFormSection
                                    consultationDiagnoses={displayDiagnoses}
                                    onAdd={handleDiagnosisAdd}
                                    onDelete={(id) =>
                                        setDeleteDialogState({
                                            open: true,
                                            type: 'diagnosis',
                                            id: id,
                                        })
                                    }
                                    processing={false}
                                    consultationStatus={wardRound.status}
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Prescriptions Tab */}
                    <TabsContent value="prescriptions">
                        <Card>
                            <CardHeader>
                                <CardTitle>Prescriptions</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <PrescriptionFormSection
                                    drugs={availableDrugs}
                                    prescriptions={wardRound.prescriptions}
                                    prescriptionData={prescriptionData}
                                    setPrescriptionData={setPrescriptionData}
                                    onSubmit={handlePrescriptionSubmit}
                                    onDelete={(id) =>
                                        setDeleteDialogState({
                                            open: true,
                                            type: 'prescription',
                                            id: id,
                                        })
                                    }
                                    processing={prescriptionProcessing}
                                    consultationStatus={wardRound.status}
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Lab Orders Tab */}
                    <TabsContent value="orders">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-4">
                                <CardTitle>Laboratory Orders</CardTitle>
                                {wardRound.status === 'in_progress' && (
                                    <Dialog
                                        open={showLabOrderDialog}
                                        onOpenChange={setShowLabOrderDialog}
                                    >
                                        <DialogTrigger asChild>
                                            <Button>
                                                <Plus className="mr-2 h-4 w-4" />
                                                Order Lab Test
                                            </Button>
                                        </DialogTrigger>
                                        <DialogContent className="max-w-md">
                                            <DialogHeader>
                                                <DialogTitle>
                                                    Order Laboratory Test
                                                </DialogTitle>
                                            </DialogHeader>
                                            <form
                                                onSubmit={handleLabOrderSubmit}
                                                className="space-y-4"
                                            >
                                                <div>
                                                    <Label htmlFor="lab_service">
                                                        Select Lab Test
                                                    </Label>
                                                    <Select
                                                        value={
                                                            labOrderData.lab_service_id
                                                        }
                                                        onValueChange={(
                                                            value,
                                                        ) =>
                                                            setLabOrderData(
                                                                'lab_service_id',
                                                                value,
                                                            )
                                                        }
                                                        required
                                                    >
                                                        <SelectTrigger
                                                            id="lab_service"
                                                            className="dark:border-gray-700 dark:bg-gray-950"
                                                        >
                                                            <SelectValue placeholder="Choose a lab test" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {labServices.map(
                                                                (service) => (
                                                                    <SelectItem
                                                                        key={
                                                                            service.id
                                                                        }
                                                                        value={service.id.toString()}
                                                                    >
                                                                        {
                                                                            service.name
                                                                        }{' '}
                                                                        - $
                                                                        {
                                                                            service.price
                                                                        }
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                </div>

                                                <div>
                                                    <Label htmlFor="priority">
                                                        Priority
                                                    </Label>
                                                    <Select
                                                        value={
                                                            labOrderData.priority
                                                        }
                                                        onValueChange={(
                                                            value,
                                                        ) =>
                                                            setLabOrderData(
                                                                'priority',
                                                                value,
                                                            )
                                                        }
                                                        required
                                                    >
                                                        <SelectTrigger
                                                            id="priority"
                                                            className="dark:border-gray-700 dark:bg-gray-950"
                                                        >
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="routine">
                                                                Routine
                                                            </SelectItem>
                                                            <SelectItem value="urgent">
                                                                Urgent
                                                            </SelectItem>
                                                            <SelectItem value="stat">
                                                                STAT (Immediate)
                                                            </SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>

                                                <div>
                                                    <Label htmlFor="special_instructions">
                                                        Special Instructions
                                                        (Optional)
                                                    </Label>
                                                    <Textarea
                                                        id="special_instructions"
                                                        placeholder="Any special instructions for the lab..."
                                                        value={
                                                            labOrderData.special_instructions
                                                        }
                                                        onChange={(e) =>
                                                            setLabOrderData(
                                                                'special_instructions',
                                                                e.target.value,
                                                            )
                                                        }
                                                        rows={3}
                                                        className="dark:border-gray-700 dark:bg-gray-950"
                                                    />
                                                </div>

                                                <div className="flex gap-2 pt-4">
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        onClick={() =>
                                                            setShowLabOrderDialog(
                                                                false,
                                                            )
                                                        }
                                                        className="flex-1"
                                                    >
                                                        Cancel
                                                    </Button>
                                                    <Button
                                                        type="submit"
                                                        disabled={
                                                            labOrderProcessing ||
                                                            !labOrderData.lab_service_id
                                                        }
                                                        className="flex-1"
                                                    >
                                                        {labOrderProcessing
                                                            ? 'Ordering...'
                                                            : 'Order Test'}
                                                    </Button>
                                                </div>
                                            </form>
                                        </DialogContent>
                                    </Dialog>
                                )}
                            </CardHeader>
                            <CardContent>
                                {wardRound.lab_orders &&
                                wardRound.lab_orders.length > 0 ? (
                                    <div className="space-y-3">
                                        {wardRound.lab_orders.map((order) => (
                                            <div
                                                key={order.id}
                                                className="flex items-center justify-between rounded-lg border p-4 dark:border-gray-700"
                                            >
                                                <div>
                                                    <p className="font-medium text-gray-900 dark:text-gray-100">
                                                        {order.lab_service.name}
                                                    </p>
                                                    <p className="text-sm text-gray-600 dark:text-gray-400">
                                                        Priority:{' '}
                                                        {order.priority} •
                                                        Status: {order.status}
                                                    </p>
                                                    {order.special_instructions && (
                                                        <p className="text-sm text-gray-600 dark:text-gray-400">
                                                            {
                                                                order.special_instructions
                                                            }
                                                        </p>
                                                    )}
                                                </div>
                                                {wardRound.status ===
                                                    'in_progress' && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            setDeleteDialogState(
                                                                {
                                                                    open: true,
                                                                    type: 'laborder',
                                                                    id: order.id,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        Remove
                                                    </Button>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <div className="py-8 text-center text-gray-500 dark:text-gray-400">
                                        <TestTube className="mx-auto mb-4 h-16 w-16 text-gray-300 dark:text-gray-600" />
                                        <p className="text-lg font-medium">
                                            No lab orders added
                                        </p>
                                        <p className="mt-2 text-sm">
                                            Click "Order Lab Test" to add lab
                                            orders
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Theatre Procedures Tab */}
                    <TabsContent value="theatre">
                        <WardRoundProceduresTab
                            admissionId={admission.id}
                            wardRoundId={wardRound.id}
                            procedures={wardRound.procedures || []}
                            availableProcedures={availableProcedures || []}
                        />
                    </TabsContent>
                </Tabs>
            </div>

            {/* Complete Ward Round Dialog */}
            <AlertDialog
                open={showCompleteDialog}
                onOpenChange={setShowCompleteDialog}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Complete Ward Round</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to complete this ward round?
                            This will save all the information you've entered.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={handleCompleteWardRound}>
                            Complete
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {/* Delete/Cancel Dialog */}
            <AlertDialog
                open={deleteDialogState.open}
                onOpenChange={(open) =>
                    setDeleteDialogState({ ...deleteDialogState, open })
                }
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            {deleteDialogState.type === 'diagnosis'
                                ? 'Remove Diagnosis'
                                : deleteDialogState.type === 'prescription'
                                  ? 'Remove Prescription'
                                  : 'Remove Lab Order'}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {deleteDialogState.type === 'diagnosis'
                                ? 'Are you sure you want to remove this diagnosis?'
                                : deleteDialogState.type === 'prescription'
                                  ? 'Are you sure you want to remove this prescription?'
                                  : 'Are you sure you want to remove this lab order?'}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={handleDelete}
                            className="bg-red-600 hover:bg-red-700 dark:bg-red-600 dark:hover:bg-red-700"
                        >
                            Remove
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
