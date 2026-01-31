import { ServiceBlockAlert } from '@/components/billing/ServiceBlockAlert';
import VitalsModal from '@/components/Checkin/VitalsModal';
import DiagnosisFormSection from '@/components/Consultation/DiagnosisFormSection';
import { InvestigationsWithBatch } from '@/components/Consultation/InvestigationsWithBatch';
import MedicalHistoryNotes from '@/components/Consultation/MedicalHistoryNotes';
import { PatientHistorySidebar } from '@/components/Consultation/PatientHistorySidebar';
import PrescriptionSection from '@/components/Consultation/PrescriptionSection';
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
import { SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    Activity,
    ArrowRightLeft,
    Bed,
    Building,
    Clock,
    FileText,
    FlaskConical,
    Heart,
    Pill,
    Stethoscope,
    User,
    UserPlus,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';

interface InsuranceProvider {
    id: number;
    name: string;
    code: string;
}

interface InsurancePlan {
    id: number;
    name: string;
    provider: InsuranceProvider;
}

interface PatientInsurance {
    id: number;
    plan: InsurancePlan;
}

interface Patient {
    id: number;
    patient_number?: string;
    first_name: string;
    last_name: string;
    date_of_birth: string;
    gender: string;
    phone_number: string;
    email?: string;
    age?: number;
    active_insurance?: PatientInsurance | null;
}

interface Department {
    id: number;
    name: string;
}

interface VitalSigns {
    id: number;
    temperature: number | null;
    blood_pressure_systolic: number | null;
    blood_pressure_diastolic: number | null;
    pulse_rate: number | null; // Note: Database uses pulse_rate, not heart_rate
    respiratory_rate: number | null;
    oxygen_saturation: number | null;
    blood_sugar: number | null;
    weight: number | null;
    height: number | null;
    notes: string | null;
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
    dose_quantity?: string;
    quantity_to_dispense?: number;
    instructions?: string;
    status: string;
    drug_id?: number;
    drug?: Drug;
    refilled_from_prescription_id?: number;
}

interface PreviousPrescription {
    id: number;
    medication_name: string;
    dose_quantity?: string;
    frequency: string;
    duration: string;
    instructions?: string;
    status: string;
    drug?: Drug;
    consultation: {
        id: number;
        started_at: string;
        doctor: {
            id: number;
            name: string;
        };
        patient_checkin: {
            department: {
                id: number;
                name: string;
            };
        };
    };
}

interface LabService {
    id: number;
    name: string;
    code: string;
    category: string;
    price: number | null;
    sample_type: string;
    is_imaging?: boolean;
    modality?: string | null;
}

interface ImagingAttachment {
    id: number;
    lab_order_id: number;
    file_path?: string;
    file_name: string;
    file_type: string;
    file_size?: number;
    description?: string | null;
    is_external: boolean;
    external_facility_name?: string | null;
    external_study_date?: string | null;
    uploaded_by?: { id: number; name: string };
    uploaded_at?: string;
    url?: string;
    thumbnail_url?: string | null;
}

interface LabOrder {
    id: number;
    lab_service: LabService;
    status:
        | 'ordered'
        | 'sample_collected'
        | 'in_progress'
        | 'completed'
        | 'cancelled'
        | 'external_referral';
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
    imaging_attachments?: ImagingAttachment[];
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
    admitted_at: string;
    discharge_date?: string;
    admission_reason: string;
    admission_notes?: string;
    status: 'admitted' | 'discharged' | 'transferred';
    latest_vital_signs?: VitalSigns[];
    latest_ward_round?: AdmissionWardRound[];
}

interface PatientWithAdmission extends Patient {
    active_admission?: PatientAdmission;
    active_admissions?: PatientAdmission[];
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
    indication: string | null;
    assistant: string | null;
    anaesthetist: string | null;
    anaesthesia_type: string | null;
    estimated_gestational_age: string | null;
    parity: string | null;
    procedure_subtype: string | null;
    procedure_steps: string | null;
    template_selections: Record<string, string> | null;
    findings: string | null;
    plan: string | null;
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
    service_date?: string;
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
        service_date?: string;
        status?: string;
        vital_signs?: VitalSigns[]; // Note: Laravel serializes vitalSigns relationship as vital_signs in JSON
    };
    doctor: Doctor;
    diagnoses: ConsultationDiagnosis[];
    prescriptions: Prescription[];
    lab_orders: LabOrder[];
    procedures?: ConsultationProcedure[];
    patient_admission?: {
        id: number;
        consultation_id: number;
        ward_id: number;
        status: string;
    };
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
        previousPrescriptions: PreviousPrescription[];
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
        previousImagingStudies?: {
            id: number;
            lab_service: {
                id: number;
                name: string;
                code: string;
                category: string;
                modality?: string | null;
                is_imaging?: boolean;
            };
            status: string;
            priority: string;
            special_instructions?: string;
            ordered_at: string;
            result_entered_at?: string;
            result_notes?: string;
            ordered_by?: {
                id: number;
                name: string;
            };
            result_entered_by?: {
                id: number;
                name: string;
            };
            imaging_attachments?: {
                id: number;
                lab_order_id: number;
                file_name: string;
                file_type: string;
                is_external: boolean;
                external_facility_name?: string | null;
                external_study_date?: string | null;
            }[];
            has_images?: boolean;
            is_external?: boolean;
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
    can?: {
        editVitals: boolean;
    };
}

export default function ConsultationShow({
    consultation,
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
    can,
}: Props) {
    const { auth, flash, features } = usePage<SharedData>().props;
    const canUploadExternal =
        auth.permissions?.investigations?.uploadExternal ?? false;
    const bedManagementEnabled = features?.bedManagement ?? false;

    // Handle flash messages
    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash]);

    // Determine if consultation is editable
    // Editable if: in_progress OR completed within last 24 hours
    const isEditable = (() => {
        if (consultation.status === 'in_progress') return true;
        if (consultation.status === 'completed' && consultation.completed_at) {
            const completedAt = new Date(consultation.completed_at);
            const twentyFourHoursAgo = new Date(
                Date.now() - 24 * 60 * 60 * 1000,
            );
            return completedAt > twentyFourHoursAgo;
        }
        return false;
    })();

    const [activeTab, setActiveTab] = useState('vitals');
    const [isSaving, setIsSaving] = useState(false);
    const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);
    const autoSaveTimeout = useRef<NodeJS.Timeout | null>(null);

    // Vitals modal state
    const [vitalsModalOpen, setVitalsModalOpen] = useState(false);
    const [vitalsModalMode, setVitalsModalMode] = useState<'create' | 'edit'>(
        'edit',
    );

    // Lab order state
    const [showLabOrderDialog, setShowLabOrderDialog] = useState(false);
    const [selectedLabService, setSelectedLabService] = useState<{
        id: number;
        name: string;
        code: string;
        category: string;
        sample_type: string;
        price?: number | null;
    } | null>(null);
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
    } = useForm<{
        medication_name: string;
        drug_id: number | null;
        dose_quantity: string;
        frequency: string;
        duration: string;
        quantity_to_dispense: string | number;
        schedule_pattern: object | null;
        instructions: string;
    }>({
        medication_name: '',
        drug_id: null,
        dose_quantity: '',
        frequency: '',
        duration: '',
        quantity_to_dispense: '',
        schedule_pattern: null,
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
    const [editingPrescription, setEditingPrescription] = useState<{
        id: number;
        medication_name: string;
        frequency: string;
        duration: string;
        dose_quantity?: string;
        quantity_to_dispense?: number;
        instructions?: string;
        status: string;
        drug_id?: number;
    } | null>(null);

    const {
        data: admissionData,
        setData: setAdmissionData,
        post: postAdmission,
        processing: admissionProcessing,
        reset: resetAdmission,
    } = useForm({
        ward_id: '',
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
        if (!hasUnsavedChanges || !isEditable) {
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
    }, [hasUnsavedChanges, consultation.id, isEditable, data]);

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

    const handlePrescriptionEdit = (prescription: {
        id: number;
        medication_name: string;
        frequency: string;
        duration: string;
        dose_quantity?: string;
        quantity_to_dispense?: number;
        instructions?: string;
        status: string;
        drug_id?: number;
    }) => {
        setEditingPrescription(prescription);
        // Populate form with prescription data
        setPrescriptionData('drug_id', prescription.drug_id || null);
        setPrescriptionData('medication_name', prescription.medication_name);
        setPrescriptionData('dose_quantity', prescription.dose_quantity || '');
        setPrescriptionData('frequency', prescription.frequency);
        setPrescriptionData('duration', prescription.duration);
        setPrescriptionData(
            'quantity_to_dispense',
            prescription.quantity_to_dispense || '',
        );
        setPrescriptionData('instructions', prescription.instructions || '');
    };

    const handlePrescriptionCancelEdit = () => {
        setEditingPrescription(null);
        resetPrescription();
    };

    const handlePrescriptionUpdate = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingPrescription) return;

        router.patch(
            `/consultation/${consultation.id}/prescriptions/${editingPrescription.id}`,
            {
                drug_id: prescriptionData.drug_id,
                medication_name: prescriptionData.medication_name,
                dose_quantity: prescriptionData.dose_quantity,
                frequency: prescriptionData.frequency,
                duration: prescriptionData.duration,
                quantity_to_dispense: prescriptionData.quantity_to_dispense,
                instructions: prescriptionData.instructions,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setEditingPrescription(null);
                    resetPrescription();
                },
            },
        );
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
                setSelectedLabService(null);
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
        router.post(
            `/consultation/${consultation.id}/complete`,
            {},
            {
                preserveScroll: true,
                onError: () => {
                    // Errors will be shown via flash messages
                },
            },
        );
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
        if (status === 'completed') {
            return (
                <Badge className="border-green-300 bg-green-100 text-green-800 dark:border-green-700 dark:bg-green-900 dark:text-green-200">
                    COMPLETED
                </Badge>
            );
        }

        if (status === 'in_progress') {
            return (
                <Badge className="border-blue-300 bg-blue-100 text-blue-800 dark:border-blue-700 dark:bg-blue-900 dark:text-blue-200">
                    IN PROGRESS
                </Badge>
            );
        }

        return (
            <Badge variant="secondary">
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
                                        {new Date(
                                            consultation.service_date ||
                                                consultation.patient_checkin
                                                    .service_date ||
                                                consultation.started_at,
                                        ).toLocaleDateString('en-US', {
                                            year: 'numeric',
                                            month: 'short',
                                            day: 'numeric',
                                        })}
                                    </span>
                                </div>
                                <Separator
                                    orientation="vertical"
                                    className="h-4"
                                />
                                {consultation.patient_checkin.patient
                                    .active_insurance ? (
                                    <Badge
                                        variant="outline"
                                        className="bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300"
                                    >
                                        {
                                            consultation.patient_checkin.patient
                                                .active_insurance.plan.provider
                                                .code
                                        }
                                    </Badge>
                                ) : (
                                    <Badge
                                        variant="outline"
                                        className="text-muted-foreground"
                                    >
                                        Cash
                                    </Badge>
                                )}
                            </div>
                        </div>

                        <div className="flex items-center gap-3">
                            {getStatusBadge(consultation.status)}
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

                {/* Admission Context Banner - Multiple Admissions */}
                {consultation.patient_checkin.patient.active_admissions &&
                    consultation.patient_checkin.patient.active_admissions
                        .length > 0 && (
                        <div className="rounded-lg border-l-4 border-blue-600 bg-blue-50 p-4 shadow-sm dark:border-blue-400 dark:bg-blue-950">
                            <div className="flex items-center gap-2 mb-3">
                                <Bed className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                <h3 className="font-semibold text-blue-900 dark:text-blue-100">
                                    Currently Admitted Patient
                                </h3>
                                <Badge
                                    variant="outline"
                                    className="border-blue-600 text-blue-600 dark:border-blue-400 dark:text-blue-400"
                                >
                                    {
                                        consultation.patient_checkin.patient
                                            .active_admissions.length
                                    }{' '}
                                    Active{' '}
                                    {consultation.patient_checkin.patient
                                        .active_admissions.length === 1
                                        ? 'Admission'
                                        : 'Admissions'}
                                </Badge>
                            </div>
                            <div className="flex flex-wrap gap-3">
                                {consultation.patient_checkin.patient.active_admissions.map(
                                    (admission) => (
                                        <div
                                            key={admission.id}
                                            className="flex items-center gap-3 rounded-lg border border-blue-200 bg-white px-4 py-2 dark:border-blue-800 dark:bg-blue-900"
                                        >
                                            <div className="flex items-center gap-2">
                                                <Building className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                                <span className="font-medium text-blue-900 dark:text-blue-100">
                                                    {admission.ward.name}
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-2 text-sm text-blue-700 dark:text-blue-300">
                                                <Clock className="h-3.5 w-3.5" />
                                                {new Date(
                                                    admission.admitted_at,
                                                ).toLocaleDateString('en-US', {
                                                    month: 'short',
                                                    day: 'numeric',
                                                })}
                                            </div>
                                            <Button
                                                onClick={() =>
                                                    router.visit(
                                                        `/wards/${admission.ward.id}`,
                                                    )
                                                }
                                                variant="outline"
                                                size="sm"
                                                className="h-7 border-blue-600 px-2 text-xs text-blue-600 hover:bg-blue-100 dark:border-blue-400 dark:text-blue-400 dark:hover:bg-blue-800"
                                            >
                                                <Bed className="mr-1 h-3 w-3" />
                                                View Ward Details
                                            </Button>
                                        </div>
                                    ),
                                )}
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
                            previousImagingStudies={
                                patientHistory?.previousImagingStudies || []
                            }
                            allergies={patientHistory?.allergies || []}
                        />
                        {isEditable && (
                                <>
                                    <Button
                                        onClick={() =>
                                            setShowCompleteDialog(true)
                                        }
                                        variant="outline"
                                        className="border-gray-600 text-gray-600 hover:bg-gray-600 hover:text-white dark:border-gray-400 dark:text-gray-400 dark:hover:bg-gray-600 dark:hover:text-white"
                                    >
                                        Complete Consultation
                                    </Button>
                                    {/* Only show Admit and Transfer if this consultation doesn't already have an admission */}
                                    {!consultation.patient_admission && (
                                    <>
                                    <Dialog
                                        open={showAdmissionModal}
                                        onOpenChange={setShowAdmissionModal}
                                    >
                                        <DialogTrigger asChild>
                                            <Button
                                                variant="outline"
                                                className="border-green-600 text-green-600 hover:bg-green-600 hover:text-white dark:border-green-400 dark:text-green-400 dark:hover:bg-green-600 dark:hover:text-white"
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
                                                                        }
                                                                        {bedManagementEnabled && (
                                                                            <>
                                                                                {' '}
                                                                                {ward.available_beds >
                                                                                0 ? (
                                                                                    <span className="text-green-600">
                                                                                        (
                                                                                        {
                                                                                            ward.available_beds
                                                                                        }{' '}
                                                                                        beds
                                                                                        available)
                                                                                    </span>
                                                                                ) : (
                                                                                    <span className="text-orange-600">
                                                                                        (Full
                                                                                        -
                                                                                        overflow)
                                                                                    </span>
                                                                                )}
                                                                            </>
                                                                        )}
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                    {bedManagementEnabled &&
                                                        admissionData.ward_id &&
                                                        availableWards.find(
                                                            (w) =>
                                                                w.id.toString() ===
                                                                admissionData.ward_id,
                                                        )?.available_beds ===
                                                            0 && (
                                                            <p className="mt-1 text-sm text-orange-600">
                                                                ⚠️ This ward is
                                                                full. Patient
                                                                will be admitted
                                                                as overflow.
                                                            </p>
                                                        )}
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
                                                            !admissionData.ward_id
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
                                    
                                    <Dialog
                                        open={showTransferModal}
                                        onOpenChange={setShowTransferModal}
                                    >
                                        <DialogTrigger asChild>
                                            <Button
                                                variant="outline"
                                                className="border-blue-600 text-blue-600 hover:bg-blue-600 hover:text-white dark:border-blue-400 dark:text-blue-400 dark:hover:bg-blue-600 dark:hover:text-white"
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
                                                        value={
                                                            transferData.reason
                                                        }
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
                                </>
                            )}
                    </div>
                </div>

                <Tabs
                    value={activeTab}
                    onValueChange={setActiveTab}
                    className="w-full"
                >
                    <TabsList className="grid w-full grid-cols-6 gap-1 rounded-none border-b border-gray-200 bg-transparent p-1 dark:border-gray-700">
                        <TabsTrigger
                            value="vitals"
                            className="flex items-center gap-2 rounded-md border-b-2 border-transparent bg-rose-50 text-rose-700 shadow-none transition-all hover:bg-rose-100 data-[state=active]:border-rose-600 data-[state=active]:bg-rose-100 data-[state=active]:text-rose-700 data-[state=active]:shadow-none dark:bg-rose-950 dark:text-rose-300 dark:hover:bg-rose-900 dark:data-[state=active]:border-rose-400 dark:data-[state=active]:bg-rose-900 dark:data-[state=active]:text-rose-300"
                        >
                            <Activity className="h-4 w-4" />
                            Vitals
                        </TabsTrigger>
                        <TabsTrigger
                            value="notes"
                            className="flex items-center gap-2 rounded-md border-b-2 border-transparent bg-blue-50 text-blue-700 shadow-none transition-all hover:bg-blue-100 data-[state=active]:border-blue-600 data-[state=active]:bg-blue-100 data-[state=active]:text-blue-700 data-[state=active]:shadow-none dark:bg-blue-950 dark:text-blue-300 dark:hover:bg-blue-900 dark:data-[state=active]:border-blue-400 dark:data-[state=active]:bg-blue-900 dark:data-[state=active]:text-blue-300"
                        >
                            <FileText className="h-4 w-4" />
                            Consultation Notes
                        </TabsTrigger>
                        <TabsTrigger
                            value="diagnosis"
                            className="flex items-center gap-2 rounded-md border-b-2 border-transparent bg-violet-50 text-violet-700 shadow-none transition-all hover:bg-violet-100 data-[state=active]:border-violet-600 data-[state=active]:bg-violet-100 data-[state=active]:text-violet-700 data-[state=active]:shadow-none dark:bg-violet-950 dark:text-violet-300 dark:hover:bg-violet-900 dark:data-[state=active]:border-violet-400 dark:data-[state=active]:bg-violet-900 dark:data-[state=active]:text-violet-300"
                        >
                            <FileText className="h-4 w-4" />
                            Diagnosis
                        </TabsTrigger>
                        <TabsTrigger
                            value="prescriptions"
                            className="flex items-center gap-2 rounded-md border-b-2 border-transparent bg-green-50 text-green-700 shadow-none transition-all hover:bg-green-100 data-[state=active]:border-green-600 data-[state=active]:bg-green-100 data-[state=active]:text-green-700 data-[state=active]:shadow-none dark:bg-green-950 dark:text-green-300 dark:hover:bg-green-900 dark:data-[state=active]:border-green-400 dark:data-[state=active]:bg-green-900 dark:data-[state=active]:text-green-300"
                        >
                            <Pill className="h-4 w-4" />
                            Prescriptions
                        </TabsTrigger>
                        <TabsTrigger
                            value="orders"
                            className="flex items-center gap-2 rounded-md border-b-2 border-transparent bg-teal-50 text-teal-700 shadow-none transition-all hover:bg-teal-100 data-[state=active]:border-teal-600 data-[state=active]:bg-teal-100 data-[state=active]:text-teal-700 data-[state=active]:shadow-none dark:bg-teal-950 dark:text-teal-300 dark:hover:bg-teal-900 dark:data-[state=active]:border-teal-400 dark:data-[state=active]:bg-teal-900 dark:data-[state=active]:text-teal-300"
                        >
                            <FlaskConical className="h-4 w-4" />
                            Investigations
                        </TabsTrigger>
                        <TabsTrigger
                            value="theatre"
                            className="flex items-center gap-2 rounded-md border-b-2 border-transparent bg-amber-50 text-amber-700 shadow-none transition-all hover:bg-amber-100 data-[state=active]:border-amber-600 data-[state=active]:bg-amber-100 data-[state=active]:text-amber-700 data-[state=active]:shadow-none dark:bg-amber-950 dark:text-amber-300 dark:hover:bg-amber-900 dark:data-[state=active]:border-amber-400 dark:data-[state=active]:bg-amber-900 dark:data-[state=active]:text-amber-300"
                        >
                            <Stethoscope className="h-4 w-4" />
                            Theatre
                        </TabsTrigger>
                    </TabsList>

                    {/* Vitals Tab */}
                    <TabsContent value="vitals">
                        <div className="space-y-6">
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between">
                                    <div>
                                        <CardTitle>
                                            Current Vital Signs
                                        </CardTitle>
                                        {latestVitals && (
                                            <p className="mt-1 text-sm text-gray-500">
                                                Recorded on{' '}
                                                {formatDateTime(
                                                    latestVitals.recorded_at,
                                                )}
                                            </p>
                                        )}
                                    </div>
                                    {can?.editVitals && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => {
                                                setVitalsModalMode(
                                                    latestVitals
                                                        ? 'edit'
                                                        : 'create',
                                                );
                                                setVitalsModalOpen(true);
                                            }}
                                        >
                                            {latestVitals
                                                ? 'Edit Vitals'
                                                : 'Add Vitals'}
                                        </Button>
                                    )}
                                </CardHeader>
                                <CardContent>
                                    {latestVitals ? (
                                        <div className="grid grid-cols-2 gap-3 md:grid-cols-4 lg:grid-cols-4">
                                            <div className="rounded-lg border border-red-200 bg-red-50 p-3">
                                                <p className="text-xs font-medium text-red-900">
                                                    Blood Pressure
                                                </p>
                                                <p className="text-xl font-bold text-red-600">
                                                    {Math.round(
                                                        latestVitals.blood_pressure_systolic ??
                                                            0,
                                                    )}
                                                    /
                                                    {Math.round(
                                                        latestVitals.blood_pressure_diastolic ??
                                                            0,
                                                    )}
                                                </p>
                                                <p className="text-xs text-red-700">
                                                    mmHg
                                                </p>
                                            </div>
                                            <div className="rounded-lg border border-blue-200 bg-blue-50 p-3">
                                                <p className="text-xs font-medium text-blue-900">
                                                    Temperature
                                                </p>
                                                <p className="text-xl font-bold text-blue-600">
                                                    {latestVitals.temperature}°C
                                                </p>
                                                <p className="text-xs text-blue-700">
                                                    Normal: 36.1-37.2
                                                </p>
                                            </div>
                                            <div className="rounded-lg border border-green-200 bg-green-50 p-3">
                                                <p className="text-xs font-medium text-green-900">
                                                    Pulse Rate
                                                </p>
                                                <p className="text-xl font-bold text-green-600">
                                                    {latestVitals.pulse_rate}
                                                </p>
                                                <p className="text-xs text-green-700">
                                                    bpm
                                                </p>
                                            </div>
                                            <div className="rounded-lg border border-purple-200 bg-purple-50 p-3">
                                                <p className="text-xs font-medium text-purple-900">
                                                    Respiratory Rate
                                                </p>
                                                <p className="text-xl font-bold text-purple-600">
                                                    {
                                                        latestVitals.respiratory_rate
                                                    }
                                                </p>
                                                <p className="text-xs text-purple-700">
                                                    /min
                                                </p>
                                            </div>
                                            <div className="rounded-lg border border-cyan-200 bg-cyan-50 p-3">
                                                <p className="text-xs font-medium text-cyan-900">
                                                    SpO₂
                                                </p>
                                                <p className="text-xl font-bold text-cyan-600">
                                                    {latestVitals.oxygen_saturation ??
                                                        '-'}
                                                    %
                                                </p>
                                                <p className="text-xs text-cyan-700">
                                                    Normal: 95-100
                                                </p>
                                            </div>
                                            <div className="rounded-lg border border-amber-200 bg-amber-50 p-3">
                                                <p className="text-xs font-medium text-amber-900">
                                                    Weight
                                                </p>
                                                <p className="text-xl font-bold text-amber-600">
                                                    {latestVitals.weight ?? '-'}{' '}
                                                    kg
                                                </p>
                                            </div>
                                            <div className="rounded-lg border border-indigo-200 bg-indigo-50 p-3">
                                                <p className="text-xs font-medium text-indigo-900">
                                                    Height
                                                </p>
                                                <p className="text-xl font-bold text-indigo-600">
                                                    {latestVitals.height ?? '-'}{' '}
                                                    cm
                                                </p>
                                            </div>
                                            {latestVitals.weight &&
                                                latestVitals.height && (
                                                    <div className="rounded-lg border border-gray-200 bg-gray-50 p-3">
                                                        <p className="text-xs font-medium text-gray-900">
                                                            BMI
                                                        </p>
                                                        <p className="text-xl font-bold text-gray-600">
                                                            {(
                                                                latestVitals.weight /
                                                                Math.pow(
                                                                    latestVitals.height /
                                                                        100,
                                                                    2,
                                                                )
                                                            ).toFixed(1)}
                                                        </p>
                                                        <p className="text-xs text-gray-700">
                                                            kg/m²
                                                        </p>
                                                    </div>
                                                )}
                                        </div>
                                    ) : (
                                        <div className="py-8 text-center text-gray-500">
                                            <Activity className="mx-auto mb-2 h-10 w-10 text-gray-300" />
                                            <p className="text-sm font-medium">
                                                No vital signs recorded
                                            </p>
                                            <p className="text-xs">
                                                Vital signs will appear here
                                                once recorded
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
                                                                    °C
                                                                </p>
                                                            </div>
                                                            <div>
                                                                <p className="text-gray-500">
                                                                    Blood
                                                                    Pressure
                                                                </p>
                                                                <p className="font-medium text-gray-900">
                                                                    {Math.round(
                                                                        vitals.blood_pressure_systolic ??
                                                                            0,
                                                                    )}
                                                                    /
                                                                    {Math.round(
                                                                        vitals.blood_pressure_diastolic ??
                                                                            0,
                                                                    )}
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
                                        isEditable={isEditable}
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
                                    <PrescriptionSection
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
                                        onEdit={handlePrescriptionEdit}
                                        onCancelEdit={
                                            handlePrescriptionCancelEdit
                                        }
                                        onUpdate={handlePrescriptionUpdate}
                                        editingPrescription={
                                            editingPrescription
                                        }
                                        processing={prescriptionProcessing}
                                        consultationId={consultation.id}
                                        isEditable={isEditable}
                                        previousPrescriptions={
                                            patientHistory?.previousPrescriptions
                                        }
                                        prescribableType="consultation"
                                        prescribableId={consultation.id}
                                    />
                                </CardContent>
                            </Card>
                        </div>
                    </TabsContent>

                    {/* Investigations Tab (Laboratory Tests + Imaging) */}
                    <TabsContent value="orders">
                        <InvestigationsWithBatch
                            consultationId={consultation.id}
                            labOrders={consultation.lab_orders}
                            isEditable={isEditable}
                            canUploadExternal={canUploadExternal}
                            orderableType="consultation"
                            orderableId={consultation.id}
                        />
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

            {/* Vitals Edit Modal */}
            <VitalsModal
                open={vitalsModalOpen}
                onClose={() => setVitalsModalOpen(false)}
                checkin={
                    consultation.patient_checkin
                        ? {
                              id: consultation.patient_checkin.id,
                              patient: {
                                  id: consultation.patient_checkin.patient.id,
                                  patient_number:
                                      consultation.patient_checkin.patient
                                          .patient_number || '',
                                  full_name: `${consultation.patient_checkin.patient.first_name} ${consultation.patient_checkin.patient.last_name}`,
                                  age:
                                      consultation.patient_checkin.patient
                                          .age || 0,
                                  gender: consultation.patient_checkin.patient
                                      .gender,
                              },
                              department:
                                  consultation.patient_checkin.department,
                              status: consultation.patient_checkin.status || '',
                              checked_in_at:
                                  consultation.patient_checkin.checked_in_at,
                              vital_signs:
                                  consultation.patient_checkin.vital_signs,
                          }
                        : null
                }
                onSuccess={() => {
                    setVitalsModalOpen(false);
                    router.reload();
                }}
                mode={vitalsModalMode}
            />
        </AppLayout>
    );
}
