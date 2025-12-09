import { ServiceBlockAlert } from '@/components/billing/ServiceBlockAlert';
import { ConsultationLabOrdersTable } from '@/components/Consultation/ConsultationLabOrdersTable';
import DiagnosisFormSection from '@/components/Consultation/DiagnosisFormSection';
import MedicalHistoryNotes from '@/components/Consultation/MedicalHistoryNotes';
import { PatientHistorySidebar } from '@/components/Consultation/PatientHistorySidebar';
import PrescriptionFormSection from '@/components/Consultation/PrescriptionFormSection';
import TheatreProceduresTab from '@/components/Consultation/TheatreProceduresTab';
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
    ArrowRightLeft,
    Bed,
    Building,
    Clock,
    FileText,
    Heart,
    Pill,
    Plus,
    Stethoscope,
    TestTube,
    User,
    UserPlus,
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

interface Department {
    id: number;
    name: string;
}

interface VitalSigns {
    id: number;
    temperature: number;
    blood_pressure_systolic: number;
    blood_pressure_diastolic: number;
    pulse_rate: number; // Note: Database uses pulse_rate, not heart_rate
    respiratory_rate: number;
    recorded_at: string;
}

interface Doctor {
    id: number;
    name: string;
}

interface Diagnosis {
    id: number;
    diagnosis: string;
    code: string;
    g_drg: string;
    icd_10: string;
}

interface ConsultationDiagnosis {
    id: number;
    diagnosis: Diagnosis;
    type: 'provisional' | 'principal';
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

interface AdmissionWardRound {
    id: number;
    doctor: {
        id: number;
        name: string;
    };
    notes: string;
    created_at: string;
}

interface PatientAdmission {
    id: number;
    ward: Ward;
    bed_number?: string;
    admission_date: string;
    discharge_date?: string;
    admission_reason: string;
    admission_notes?: string;
    status: 'admitted' | 'discharged' | 'transferred';
    latest_vital_signs?: VitalSigns[];
    latest_ward_round?: AdmissionWardRound[];
}

interface PatientWithAdmission extends Patient {
    active_admission?: PatientAdmission;
}

interface ProcedureType {
    id: number;
    name: string;
    code: string;
    type: 'minor' | 'major';
    category: string;
    price: number;
}

interface ConsultationProcedure {
    id: number;
    procedure_type: ProcedureType;
    comments: string | null;
    performed_at: string;
    doctor: {
        id: number;
        name: string;
    };
}

interface Consultation {
    id: number;
    started_at: string;
    completed_at?: string;
    status: string;
    presenting_complaint?: string;
    history_presenting_complaint?: string;
    on_direct_questioning?: string;
    examination_findings?: string;
    assessment_notes?: string;
    plan_notes?: string;
    follow_up_date?: string;
    patient_checkin: {
        id: number;
        patient: PatientWithAdmission;
        department: Department;
        checked_in_at: string;
        vital_signs?: VitalSigns[]; // Note: Laravel serializes vitalSigns relationship as vital_signs in JSON
    };
    doctor: Doctor;
    diagnoses: ConsultationDiagnosis[];
    prescriptions: Prescription[];
    lab_orders: LabOrder[];
    procedures?: ConsultationProcedure[];
}

interface Ward {
    id: number;
    name: string;
    code: string;
    available_beds: number;
}

interface Department {
    id: number;
    name: string;
    code: string;
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
    consultation: Consultation;
    labServices: LabService[];
    patientHistory?: {
        previousConsultations: {
            id: number;
            started_at: string;
            completed_at?: string;
            status: string;
            presenting_complaint?: string;
            history_presenting_complaint?: string;
            on_direct_questioning?: string;
            examination_findings?: string;
            assessment_notes?: string;
            plan_notes?: string;
            follow_up_date?: string;
            patient_checkin: {
                id: number;
                department: {
                    id: number;
                    name: string;
                };
                vital_signs?: VitalSigns[];
            };
            doctor: {
                id: number;
                name: string;
            };
            diagnoses: {
                id: number;
                icd_code: string;
                diagnosis_description: string;
                is_primary: boolean;
            }[];
            prescriptions: {
                id: number;
                medication_name: string;
                dosage: string;
                frequency: string;
                duration: string;
                instructions?: string;
                status: string;
            }[];
            lab_orders?: LabOrder[];
        }[];
        previousPrescriptions: Prescription[];
        previousMinorProcedures?: {
            id: number;
            performed_at: string;
            status: string;
            procedure_notes: string;
            procedure_type: {
                id: number;
                name: string;
                code: string;
            };
            nurse: {
                id: number;
                name: string;
            };
            patient_checkin: {
                id: number;
                department: {
                    id: number;
                    name: string;
                };
            };
            diagnoses: {
                id: number;
                diagnosis: string;
                code: string;
                icd_10: string;
                g_drg: string;
            }[];
            supplies: {
                id: number;
                drug: {
                    id: number;
                    name: string;
                    form: string;
                    strength: string;
                };
                quantity: number;
                dispensed: boolean;
            }[];
        }[];
        allergies: string[];
    };
    patientHistories: {
        past_medical_surgical_history: string;
        drug_history: string;
        family_history: string;
        social_history: string;
    };
    availableWards: Ward[];
    availableDrugs?: Drug[];
    availableDepartments?: Department[];
    availableDiagnoses?: Diagnosis[];
    availableProcedures?: ProcedureType[];
    serviceBlocked?: boolean;
    blockReason?: string;
    pendingCharges?: ServiceCharge[];
    activeOverride?: ServiceAccessOverride | null;
}

export default function ConsultationShow({
    consultation,
    labServices,
    patientHistory,
    patientHistories,
    availableWards,
    availableDrugs = [],
    availableDepartments = [],
    availableDiagnoses = [],
    availableProcedures = [],
    serviceBlocked = false,
    blockReason,
    pendingCharges = [],
    activeOverride,
}: Props) {
    const [activeTab, setActiveTab] = useState('notes');
    const [isSaving, setIsSaving] = useState(false);
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

    const { data, setData, patch, processing } = useForm({
        presenting_complaint: consultation.presenting_complaint || '',
        history_presenting_complaint:
            consultation.history_presenting_complaint || '',
        on_direct_questioning: consultation.on_direct_questioning || '',
        examination_findings: consultation.examination_findings || '',
        assessment_notes: consultation.assessment_notes || '',
        plan_notes: consultation.plan_notes || '',
        follow_up_date: consultation.follow_up_date || '',
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
        medication_name: '',
        drug_id: null as number | null,
        frequency: '',
        duration: '',
        instructions: '',
    });

    const { processing: diagnosisProcessing, reset: resetDiagnosis } = useForm({
        diagnosis_id: null as number | null,
        type: 'provisional' as 'provisional' | 'principal',
    });

    const [showAdmissionModal, setShowAdmissionModal] = useState(false);
    const [showTransferModal, setShowTransferModal] = useState(false);
    const [showCompleteDialog, setShowCompleteDialog] = useState(false);
    const [deleteDialogState, setDeleteDialogState] = useState<{
        open: boolean;
        type: 'diagnosis' | 'prescription';
        id: number | null;
    }>({ open: false, type: 'diagnosis', id: null });

    const {
        data: admissionData,
        setData: setAdmissionData,
        post: postAdmission,
        processing: admissionProcessing,
        reset: resetAdmission,
    } = useForm({
        ward_id: '',
        admission_reason: '',
        admission_notes: '',
    });

    const {
        data: transferData,
        setData: setTransferData,
        post: postTransfer,
        processing: transferProcessing,
        reset: resetTransfer,
    } = useForm({
        department_id: '',
        reason: '',
    });

    // Auto-save function
    const autoSave = useCallback(async () => {
        if (!hasUnsavedChanges || consultation.status !== 'in_progress') {
            return;
        }

        setIsSaving(true);

        try {
            const csrfToken = document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content');

            const response = await fetch(`/consultation/${consultation.id}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(data),
            });

            if (response.ok) {
                setIsSaving(false);
                setHasUnsavedChanges(false);
            } else {
                setIsSaving(false);
                console.error('Autosave failed:', response.statusText);
            }
        } catch (error) {
            setIsSaving(false);
            console.error('Autosave error:', error);
        }
    }, [hasUnsavedChanges, consultation.id, consultation.status, data]);

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
        patch(`/consultation/${consultation.id}`, {
            onSuccess: () => {
                setIsSaving(false);
                setHasUnsavedChanges(false);
            },
            onError: () => {
                setIsSaving(false);
            },
        });
    };

    const handlePrescriptionSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        postPrescription(`/consultation/${consultation.id}/prescriptions`, {
            onSuccess: () => {
                resetPrescription();
            },
        });
    };

    const handleDiagnosisAdd = (
        diagnosisId: number,
        type: 'provisional' | 'principal',
    ) => {
        router.post(
            `/consultation/${consultation.id}/diagnoses`,
            {
                diagnosis_id: diagnosisId,
                type: type,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    resetDiagnosis();
                },
            },
        );
    };

    // Lab order functionality
    const handleLabOrderSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        postLabOrder(`/consultation/${consultation.id}/lab-orders`, {
            onSuccess: () => {
                resetLabOrder();
                setShowLabOrderDialog(false);
            },
        });
    };

    const handleAdmissionSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        postAdmission(`/consultation/${consultation.id}/admit`, {
            onSuccess: () => {
                resetAdmission();
                setShowAdmissionModal(false);
            },
        });
    };

    const handleTransferSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        postTransfer(`/consultation/${consultation.id}/transfer`, {
            onSuccess: () => {
                resetTransfer();
                setShowTransferModal(false);
            },
        });
    };

    const handleWardChange = (wardId: string) => {
        setAdmissionData('ward_id', wardId);
    };

    const handleTransferDepartmentChange = (departmentId: string) => {
        setTransferData('department_id', departmentId);
    };

    const handleCompleteConsultation = () => {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/consultation/${consultation.id}/complete`;

        const csrfToken = document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content');
        if (csrfToken) {
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = csrfToken;
            form.appendChild(csrfInput);
        }

        document.body.appendChild(form);
        form.submit();
    };

    const handleDelete = () => {
        if (!deleteDialogState.id) {
            return;
        }

        if (deleteDialogState.type === 'diagnosis') {
            router.delete(
                `/consultation/${consultation.id}/diagnoses/${deleteDialogState.id}`,
                {
                    onSuccess: () => {
                        setDeleteDialogState({
                            open: false,
                            type: 'diagnosis',
                            id: null,
                        });
                    },
                },
            );
        } else {
            router.delete(
                `/consultation/${consultation.id}/prescriptions/${deleteDialogState.id}`,
                {
                    onSuccess: () => {
                        setDeleteDialogState({
                            open: false,
                            type: 'diagnosis',
                            id: null,
                        });
                    },
                },
            );
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

    const getStatusBadge = (status: string) => {
        const variants = {
            in_progress: 'default',
            completed: 'outline',
            paused: 'secondary',
        } as const;

        return (
            <Badge
                variant={
                    variants[status as keyof typeof variants] || 'secondary'
                }
            >
                {status.replace('_', ' ').toUpperCase()}
            </Badge>
        );
    };

    const vitalSigns = consultation.patient_checkin.vital_signs || [];
    const latestVitals = vitalSigns[0];

    const breadcrumbs = [
        { title: 'Consultation', href: '/consultation' },
        {
            title: `${consultation.patient_checkin.patient.first_name} ${consultation.patient_checkin.patient.last_name}`,
            href: '',
        },
    ];

    const pageTitle = `Consultation - ${consultation.patient_checkin.patient.first_name} ${consultation.patient_checkin.patient.last_name}`;

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
                                    {
                                        consultation.patient_checkin.patient
                                            .first_name
                                    }{' '}
                                    {
                                        consultation.patient_checkin.patient
                                            .last_name
                                    }
                                </h1>
                                <div className="mt-1 flex items-center gap-3 text-sm text-gray-600 dark:text-gray-400">
                                    <span>
                                        Age:{' '}
                                        {calculateAge(
                                            consultation.patient_checkin.patient
                                                .date_of_birth,
                                        )}
                                    </span>
                                    <span>•</span>
                                    <span className="capitalize">
                                        {
                                            consultation.patient_checkin.patient
                                                .gender
                                        }
                                    </span>
                                </div>
                            </div>

                            <div className="flex items-center gap-4 text-sm">
                                <div className="flex items-center gap-2">
                                    <Building className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                    <span className="text-gray-700 dark:text-gray-300">
                                        {
                                            consultation.patient_checkin
                                                .department.name
                                        }
                                    </span>
                                </div>
                                <Separator
                                    orientation="vertical"
                                    className="h-4"
                                />
                                <div className="flex items-center gap-2">
                                    <User className="h-4 w-4 text-green-600 dark:text-green-400" />
                                    <span className="text-gray-700 dark:text-gray-300">
                                        Dr. {consultation.doctor.name}
                                    </span>
                                </div>
                                <Separator
                                    orientation="vertical"
                                    className="h-4"
                                />
                                <div className="flex items-center gap-2">
                                    <Clock className="h-4 w-4 text-purple-600 dark:text-purple-400" />
                                    <span className="text-gray-700 dark:text-gray-300">
                                        {formatDateTime(
                                            consultation.patient_checkin
                                                .checked_in_at,
                                        )}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div className="flex items-center gap-3">
                            {getStatusBadge(consultation.status)}
                            <div className="text-sm text-gray-600 dark:text-gray-400">
                                Started:{' '}
                                {formatDateTime(consultation.started_at)}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Service Block Alert */}
                <ServiceBlockAlert
                    isBlocked={serviceBlocked}
                    blockReason={blockReason}
                    pendingCharges={pendingCharges}
                    activeOverride={activeOverride}
                    checkinId={consultation.patient_checkin.id}
                />

                {/* Admission Context Banner */}
                {consultation.patient_checkin.patient.active_admission && (
                    <div className="rounded-lg border-l-4 border-blue-600 bg-blue-50 p-4 shadow-sm dark:border-blue-400 dark:bg-blue-950">
                        <div className="flex items-start justify-between">
                            <div className="flex items-start gap-3">
                                <Bed className="mt-0.5 h-5 w-5 text-blue-600 dark:text-blue-400" />
                                <div className="flex-1">
                                    <div className="flex items-center gap-2">
                                        <h3 className="font-semibold text-blue-900 dark:text-blue-100">
                                            Currently Admitted Patient
                                        </h3>
                                        <Badge
                                            variant="outline"
                                            className="border-blue-600 text-blue-600 dark:border-blue-400 dark:text-blue-400"
                                        >
                                            {consultation.patient_checkin.patient.active_admission.status.toUpperCase()}
                                        </Badge>
                                    </div>
                                    <div className="mt-2 grid grid-cols-1 gap-3 text-sm md:grid-cols-3">
                                        <div className="flex items-center gap-2">
                                            <Building className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                            <div>
                                                <span className="text-blue-700 dark:text-blue-300">
                                                    Ward:{' '}
                                                </span>
                                                <span className="font-medium text-blue-900 dark:text-blue-100">
                                                    {
                                                        consultation
                                                            .patient_checkin
                                                            .patient
                                                            .active_admission
                                                            .ward.name
                                                    }
                                                </span>
                                            </div>
                                        </div>
                                        {consultation.patient_checkin.patient
                                            .active_admission.bed_number && (
                                            <div className="flex items-center gap-2">
                                                <Bed className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                                <div>
                                                    <span className="text-blue-700 dark:text-blue-300">
                                                        Bed:{' '}
                                                    </span>
                                                    <span className="font-medium text-blue-900 dark:text-blue-100">
                                                        {
                                                            consultation
                                                                .patient_checkin
                                                                .patient
                                                                .active_admission
                                                                .bed_number
                                                        }
                                                    </span>
                                                </div>
                                            </div>
                                        )}
                                        <div className="flex items-center gap-2">
                                            <Clock className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                            <div>
                                                <span className="text-blue-700 dark:text-blue-300">
                                                    Admitted:{' '}
                                                </span>
                                                <span className="font-medium text-blue-900 dark:text-blue-100">
                                                    {formatDateTime(
                                                        consultation
                                                            .patient_checkin
                                                            .patient
                                                            .active_admission
                                                            .admission_date,
                                                    )}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    {consultation.patient_checkin.patient
                                        .active_admission
                                        .latest_ward_round?.[0] && (
                                        <div className="mt-3 rounded border border-blue-200 bg-white p-3 dark:border-blue-800 dark:bg-blue-900">
                                            <div className="flex items-center gap-2 text-sm">
                                                <Stethoscope className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                                <span className="font-medium text-blue-900 dark:text-blue-100">
                                                    Latest Ward Round
                                                </span>
                                                <span className="text-blue-600 dark:text-blue-400">
                                                    by Dr.{' '}
                                                    {
                                                        consultation
                                                            .patient_checkin
                                                            .patient
                                                            .active_admission
                                                            .latest_ward_round[0]
                                                            ?.doctor?.name
                                                    }
                                                </span>
                                                <span className="text-xs text-blue-500 dark:text-blue-400">
                                                    •{' '}
                                                    {formatDateTime(
                                                        consultation
                                                            .patient_checkin
                                                            .patient
                                                            .active_admission
                                                            .latest_ward_round[0]
                                                            ?.created_at || '',
                                                    )}
                                                </span>
                                            </div>
                                            <p className="mt-2 text-sm text-blue-800 dark:text-blue-200">
                                                {
                                                    consultation.patient_checkin
                                                        .patient
                                                        .active_admission
                                                        .latest_ward_round[0]
                                                        ?.notes
                                                }
                                            </p>
                                        </div>
                                    )}
                                    {consultation.patient_checkin.patient
                                        .active_admission.latest_vital_signs &&
                                        consultation.patient_checkin.patient
                                            .active_admission.latest_vital_signs
                                            .length > 0 && (
                                            <div className="mt-2 flex items-center gap-4 text-sm">
                                                <div className="flex items-center gap-1.5">
                                                    <Heart className="h-4 w-4 text-red-500 dark:text-red-400" />
                                                    <span className="text-blue-700 dark:text-blue-300">
                                                        BP:{' '}
                                                    </span>
                                                    <span className="font-medium text-blue-900 dark:text-blue-100">
                                                        {
                                                            consultation
                                                                .patient_checkin
                                                                .patient
                                                                .active_admission
                                                                .latest_vital_signs[0]
                                                                .blood_pressure_systolic
                                                        }
                                                        /
                                                        {
                                                            consultation
                                                                .patient_checkin
                                                                .patient
                                                                .active_admission
                                                                .latest_vital_signs[0]
                                                                .blood_pressure_diastolic
                                                        }
                                                    </span>
                                                </div>
                                                <div className="flex items-center gap-1.5">
                                                    <Activity className="h-4 w-4 text-green-500 dark:text-green-400" />
                                                    <span className="text-blue-700 dark:text-blue-300">
                                                        HR:{' '}
                                                    </span>
                                                    <span className="font-medium text-blue-900 dark:text-blue-100">
                                                        {
                                                            consultation
                                                                .patient_checkin
                                                                .patient
                                                                .active_admission
                                                                .latest_vital_signs[0]
                                                                .pulse_rate
                                                        }{' '}
                                                        bpm
                                                    </span>
                                                </div>
                                                <div className="flex items-center gap-1.5">
                                                    <Activity className="h-4 w-4 text-blue-500 dark:text-blue-400" />
                                                    <span className="text-blue-700 dark:text-blue-300">
                                                        Temp:{' '}
                                                    </span>
                                                    <span className="font-medium text-blue-900 dark:text-blue-100">
                                                        {
                                                            consultation
                                                                .patient_checkin
                                                                .patient
                                                                .active_admission
                                                                .latest_vital_signs[0]
                                                                .temperature
                                                        }
                                                        °F
                                                    </span>
                                                </div>
                                            </div>
                                        )}
                                </div>
                            </div>
                            <Button
                                onClick={() =>
                                    router.visit(
                                        `/wards/${consultation.patient_checkin.patient.active_admission?.ward.id}`,
                                    )
                                }
                                variant="outline"
                                size="sm"
                                className="border-blue-600 text-blue-600 hover:bg-blue-100 dark:border-blue-400 dark:text-blue-400 dark:hover:bg-blue-900"
                            >
                                <Bed className="mr-2 h-4 w-4" />
                                View Ward Details
                            </Button>
                        </div>
                    </div>
                )}

                {/* Quick Actions */}
                <div className="mb-6 flex items-center justify-between gap-4">
                    <div className="flex gap-4">
                        <PatientHistorySidebar
                            previousConsultations={
                                patientHistory?.previousConsultations || []
                            }
                            previousMinorProcedures={
                                patientHistory?.previousMinorProcedures || []
                            }
                            allergies={patientHistory?.allergies || []}
                        />
                        {consultation.status === 'in_progress' && (
                            <>
                                <Button
                                    onClick={() => setShowCompleteDialog(true)}
                                    variant="outline"
                                >
                                    Complete Consultation
                                </Button>
                                {!consultation.patient_checkin.patient
                                    .active_admission && (
                                    <Dialog
                                        open={showAdmissionModal}
                                        onOpenChange={setShowAdmissionModal}
                                    >
                                        <DialogTrigger asChild>
                                            <Button
                                                variant="outline"
                                                className="border-green-600 text-green-600 hover:bg-green-50 dark:hover:bg-green-950"
                                            >
                                                <UserPlus className="mr-2 h-4 w-4" />
                                                Admit Patient
                                            </Button>
                                        </DialogTrigger>
                                        <DialogContent className="max-w-md">
                                            <DialogHeader>
                                                <DialogTitle>
                                                    Admit Patient
                                                </DialogTitle>
                                            </DialogHeader>
                                            <form
                                                onSubmit={handleAdmissionSubmit}
                                                className="space-y-4"
                                            >
                                                <div>
                                                    <Label htmlFor="ward_id">
                                                        Select Ward
                                                    </Label>
                                                    <Select
                                                        value={
                                                            admissionData.ward_id
                                                        }
                                                        onValueChange={
                                                            handleWardChange
                                                        }
                                                    >
                                                        <SelectTrigger>
                                                            <SelectValue placeholder="Choose a ward" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {availableWards.map(
                                                                (ward) => (
                                                                    <SelectItem
                                                                        key={
                                                                            ward.id
                                                                        }
                                                                        value={ward.id.toString()}
                                                                    >
                                                                        {
                                                                            ward.name
                                                                        }{' '}
                                                                        (
                                                                        {
                                                                            ward.available_beds
                                                                        }{' '}
                                                                        beds
                                                                        available)
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                </div>

                                                <div>
                                                    <Label htmlFor="admission_reason">
                                                        Admission Reason
                                                    </Label>
                                                    <Textarea
                                                        id="admission_reason"
                                                        placeholder="Reason for admission..."
                                                        value={
                                                            admissionData.admission_reason
                                                        }
                                                        onChange={(e) =>
                                                            setAdmissionData(
                                                                'admission_reason',
                                                                e.target.value,
                                                            )
                                                        }
                                                        required
                                                        rows={3}
                                                    />
                                                </div>

                                                <div>
                                                    <Label htmlFor="admission_notes">
                                                        Admission Notes
                                                        (Optional)
                                                    </Label>
                                                    <Textarea
                                                        id="admission_notes"
                                                        placeholder="Additional notes..."
                                                        value={
                                                            admissionData.admission_notes
                                                        }
                                                        onChange={(e) =>
                                                            setAdmissionData(
                                                                'admission_notes',
                                                                e.target.value,
                                                            )
                                                        }
                                                        rows={2}
                                                    />
                                                </div>

                                                <div className="flex gap-2 pt-4">
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        onClick={() =>
                                                            setShowAdmissionModal(
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
                                                            admissionProcessing ||
                                                            !admissionData.ward_id ||
                                                            !admissionData.admission_reason
                                                        }
                                                        className="flex-1 bg-green-600 hover:bg-green-700"
                                                    >
                                                        {admissionProcessing
                                                            ? 'Admitting...'
                                                            : 'Admit Patient'}
                                                    </Button>
                                                </div>
                                            </form>
                                        </DialogContent>
                                    </Dialog>
                                )}

                                <Dialog
                                    open={showTransferModal}
                                    onOpenChange={setShowTransferModal}
                                >
                                    <DialogTrigger asChild>
                                        <Button
                                            variant="outline"
                                            className="border-blue-600 text-blue-600 hover:bg-blue-50"
                                        >
                                            <ArrowRightLeft className="mr-2 h-4 w-4" />
                                            Transfer Patient
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent className="max-w-md">
                                        <DialogHeader>
                                            <DialogTitle>
                                                Transfer Patient to Another
                                                Department
                                            </DialogTitle>
                                        </DialogHeader>
                                        <form
                                            onSubmit={handleTransferSubmit}
                                            className="space-y-4"
                                        >
                                            <div className="rounded-md border border-blue-200 bg-blue-50 p-3">
                                                <p className="text-sm text-blue-900">
                                                    <strong>
                                                        Current Department:
                                                    </strong>{' '}
                                                    {
                                                        consultation
                                                            .patient_checkin
                                                            .department.name
                                                    }
                                                </p>
                                            </div>

                                            <div>
                                                <Label htmlFor="transfer_department_id">
                                                    Select New Department
                                                </Label>
                                                <Select
                                                    value={
                                                        transferData.department_id
                                                    }
                                                    onValueChange={
                                                        handleTransferDepartmentChange
                                                    }
                                                >
                                                    <SelectTrigger>
                                                        <SelectValue placeholder="Choose destination department" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {availableDepartments
                                                            .filter(
                                                                (dept) =>
                                                                    dept.id !==
                                                                    consultation
                                                                        .patient_checkin
                                                                        .department
                                                                        .id,
                                                            )
                                                            .map(
                                                                (
                                                                    department,
                                                                ) => (
                                                                    <SelectItem
                                                                        key={
                                                                            department.id
                                                                        }
                                                                        value={department.id.toString()}
                                                                    >
                                                                        {
                                                                            department.name
                                                                        }
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                    </SelectContent>
                                                </Select>
                                            </div>

                                            <div>
                                                <Label htmlFor="transfer_reason">
                                                    Reason for Transfer
                                                    (Optional)
                                                </Label>
                                                <Textarea
                                                    id="transfer_reason"
                                                    placeholder="Reason for department transfer..."
                                                    value={transferData.reason}
                                                    onChange={(e) =>
                                                        setTransferData(
                                                            'reason',
                                                            e.target.value,
                                                        )
                                                    }
                                                    rows={3}
                                                />
                                            </div>

                                            <div className="flex gap-2 pt-4">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    onClick={() =>
                                                        setShowTransferModal(
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
                                                        transferProcessing ||
                                                        !transferData.department_id
                                                    }
                                                    className="flex-1 bg-blue-600 hover:bg-blue-700"
                                                >
                                                    {transferProcessing
                                                        ? 'Transferring...'
                                                        : 'Transfer Patient'}
                                                </Button>
                                            </div>
                                        </form>
                                    </DialogContent>
                                </Dialog>
                            </>
                        )}
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
                                status={consultation.status}
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
                                        <p className="mt-1 text-sm text-gray-500">
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
                                            <div className="rounded-lg border border-blue-200 bg-blue-50 p-6 transition-all hover:shadow-md">
                                                <div className="mb-3 flex items-center justify-between">
                                                    <p className="text-sm font-medium text-blue-900">
                                                        Temperature
                                                    </p>
                                                    <Activity className="h-5 w-5 text-blue-600" />
                                                </div>
                                                <p className="text-3xl font-bold text-blue-600">
                                                    {latestVitals.temperature}°F
                                                </p>
                                                <p className="mt-2 text-xs text-blue-700">
                                                    Normal: 97-99°F
                                                </p>
                                            </div>
                                            <div className="rounded-lg border border-red-200 bg-red-50 p-6 transition-all hover:shadow-md">
                                                <div className="mb-3 flex items-center justify-between">
                                                    <p className="text-sm font-medium text-red-900">
                                                        Blood Pressure
                                                    </p>
                                                    <Activity className="h-5 w-5 text-red-600" />
                                                </div>
                                                <p className="text-3xl font-bold text-red-600">
                                                    {
                                                        latestVitals.blood_pressure_systolic
                                                    }
                                                    /
                                                    {
                                                        latestVitals.blood_pressure_diastolic
                                                    }
                                                </p>
                                                <p className="mt-2 text-xs text-red-700">
                                                    Normal: 90-120/60-80 mmHg
                                                </p>
                                            </div>
                                            <div className="rounded-lg border border-green-200 bg-green-50 p-6 transition-all hover:shadow-md">
                                                <div className="mb-3 flex items-center justify-between">
                                                    <p className="text-sm font-medium text-green-900">
                                                        Heart Rate
                                                    </p>
                                                    <Activity className="h-5 w-5 text-green-600" />
                                                </div>
                                                <p className="text-3xl font-bold text-green-600">
                                                    {latestVitals.pulse_rate}
                                                </p>
                                                <p className="mt-2 text-xs text-green-700">
                                                    bpm • Normal: 60-100
                                                </p>
                                            </div>
                                            <div className="rounded-lg border border-purple-200 bg-purple-50 p-6 transition-all hover:shadow-md">
                                                <div className="mb-3 flex items-center justify-between">
                                                    <p className="text-sm font-medium text-purple-900">
                                                        Respiratory Rate
                                                    </p>
                                                    <Activity className="h-5 w-5 text-purple-600" />
                                                </div>
                                                <p className="text-3xl font-bold text-purple-600">
                                                    {
                                                        latestVitals.respiratory_rate
                                                    }
                                                </p>
                                                <p className="mt-2 text-xs text-purple-700">
                                                    /min • Normal: 12-20
                                                </p>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="py-16 text-center text-gray-500">
                                            <Activity className="mx-auto mb-4 h-16 w-16 text-gray-300" />
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
                            {vitalSigns.length > 1 && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Vitals History</CardTitle>
                                        <p className="mt-1 text-sm text-gray-500">
                                            Previous {vitalSigns.length - 1}{' '}
                                            recording(s)
                                        </p>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="space-y-4">
                                            {vitalSigns
                                                .slice(1)
                                                .map((vitals, index) => (
                                                    <div
                                                        key={vitals.id}
                                                        className="rounded-lg border bg-gray-50 p-4"
                                                    >
                                                        <div className="mb-3 flex items-center justify-between">
                                                            <h4 className="font-semibold text-gray-900">
                                                                Recording #
                                                                {index + 2}
                                                            </h4>
                                                            <p className="text-sm text-gray-500">
                                                                {formatDateTime(
                                                                    vitals.recorded_at,
                                                                )}
                                                            </p>
                                                        </div>
                                                        <div className="grid grid-cols-2 gap-4 text-sm md:grid-cols-4">
                                                            <div>
                                                                <p className="text-gray-500">
                                                                    Temperature
                                                                </p>
                                                                <p className="font-medium text-gray-900">
                                                                    {
                                                                        vitals.temperature
                                                                    }
                                                                    °F
                                                                </p>
                                                            </div>
                                                            <div>
                                                                <p className="text-gray-500">
                                                                    Blood
                                                                    Pressure
                                                                </p>
                                                                <p className="font-medium text-gray-900">
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
                                                                <p className="text-gray-500">
                                                                    Heart Rate
                                                                </p>
                                                                <p className="font-medium text-gray-900">
                                                                    {
                                                                        vitals.pulse_rate
                                                                    }{' '}
                                                                    bpm
                                                                </p>
                                                            </div>
                                                            <div>
                                                                <p className="text-gray-500">
                                                                    Respiratory
                                                                </p>
                                                                <p className="font-medium text-gray-900">
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
                        <div className="space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Diagnoses</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <DiagnosisFormSection
                                        consultationDiagnoses={
                                            consultation.diagnoses
                                        }
                                        onAdd={handleDiagnosisAdd}
                                        onDelete={(id) =>
                                            setDeleteDialogState({
                                                open: true,
                                                type: 'diagnosis',
                                                id,
                                            })
                                        }
                                        processing={diagnosisProcessing}
                                        consultationStatus={consultation.status}
                                    />
                                </CardContent>
                            </Card>
                        </div>
                    </TabsContent>

                    {/* Prescriptions Tab */}
                    <TabsContent value="prescriptions">
                        <div className="space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Prescriptions</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <PrescriptionFormSection
                                        drugs={availableDrugs}
                                        prescriptions={
                                            consultation.prescriptions
                                        }
                                        prescriptionData={prescriptionData}
                                        setPrescriptionData={
                                            setPrescriptionData
                                        }
                                        onSubmit={handlePrescriptionSubmit}
                                        onDelete={(id) =>
                                            setDeleteDialogState({
                                                open: true,
                                                type: 'prescription',
                                                id,
                                            })
                                        }
                                        processing={prescriptionProcessing}
                                        consultationStatus={consultation.status}
                                    />
                                </CardContent>
                            </Card>
                        </div>
                    </TabsContent>

                    {/* Lab Orders Tab */}
                    <TabsContent value="orders">
                        <div className="space-y-6">
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-4">
                                    <CardTitle>Laboratory Orders</CardTitle>
                                    {consultation.status === 'in_progress' && (
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
                                                    onSubmit={
                                                        handleLabOrderSubmit
                                                    }
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
                                                            <SelectTrigger id="lab_service">
                                                                <SelectValue placeholder="Choose a lab test" />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {labServices.map(
                                                                    (
                                                                        service,
                                                                    ) => (
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
                                                            <SelectTrigger id="priority">
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
                                                                    STAT
                                                                    (Immediate)
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
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            rows={3}
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
                                    <ConsultationLabOrdersTable
                                        labOrders={consultation.lab_orders}
                                    />
                                </CardContent>
                            </Card>
                        </div>
                    </TabsContent>

                    {/* Theatre Procedures Tab */}
                    <TabsContent value="theatre">
                        <TheatreProceduresTab
                            consultationId={consultation.id}
                            procedures={consultation.procedures || []}
                            availableProcedures={availableProcedures || []}
                        />
                    </TabsContent>
                </Tabs>
            </div>

            {/* Complete Consultation Dialog */}
            <AlertDialog
                open={showCompleteDialog}
                onOpenChange={setShowCompleteDialog}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Complete Consultation
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to complete this consultation?
                            This action will mark the consultation as finished
                            and the patient will be able to proceed to billing.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={handleCompleteConsultation}>
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
                                ? 'Delete Diagnosis'
                                : 'Cancel Prescription'}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {deleteDialogState.type === 'diagnosis'
                                ? 'Are you sure you want to delete this diagnosis? This action cannot be undone.'
                                : 'Are you sure you want to cancel this prescription? This action cannot be undone.'}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={handleDelete}
                            className="bg-red-600 hover:bg-red-700"
                        >
                            {deleteDialogState.type === 'diagnosis'
                                ? 'Delete'
                                : 'Cancel Prescription'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
