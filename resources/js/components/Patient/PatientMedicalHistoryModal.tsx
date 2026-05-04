import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Beaker,
    Calendar,
    ChevronRight,
    ClipboardList,
    Eye,
    FileText,
    Heart,
    Hospital,
    Loader2,
    Pill,
    Scissors,
    Stethoscope,
    Thermometer,
    Users,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import AdmissionDetailView from '@/pages/Patients/components/AdmissionDetailView';

interface Diagnosis {
    type: string;
    code: string | null;
    description: string | null;
    notes?: string | null;
    is_active?: boolean;
}

interface Prescription {
    drug_name: string | null;
    generic_name: string | null;
    form: string | null;
    strength: string | null;
    dose_quantity: string | null;
    frequency: string | null;
    duration: string | null;
    quantity: number | null;
    instructions: string | null;
    status: string;
}

interface LabOrder {
    id: number;
    service_name: string | null;
    code: string | null;
    is_imaging: boolean;
    test_parameters?: {
        parameters: Array<{
            name: string;
            label: string;
            type: string;
            unit?: string;
            normal_range?: {
                min?: number;
                max?: number;
            };
        }>;
    } | null;
    status?: string;
    result_values: Record<string, unknown> | null;
    result_notes: string | null;
    ordered_at: string | null;
    result_entered_at: string | null;
}

interface Procedure {
    name: string | null;
    code: string | null;
    notes: string | null;
}

interface ConsultationVitals {
    blood_pressure: string | null;
    temperature: number | null;
    pulse_rate: number | null;
    respiratory_rate: number | null;
    oxygen_saturation: number | null;
    blood_sugar: number | null;
    weight: number | null;
    height: number | null;
    bmi: number | null;
    recorded_at: string | null;
    recorded_by: string | null;
}

interface Consultation {
    id: number;
    date: string | null;
    doctor: string | null;
    department: string | null;
    presenting_complaint: string | null;
    history_presenting_complaint: string | null;
    on_direct_questioning: string | null;
    examination_findings: string | null;
    assessment_notes: string | null;
    plan_notes: string | null;
    vitals: ConsultationVitals | null;
    diagnoses: Diagnosis[];
    prescriptions: Prescription[];
    lab_orders: LabOrder[];
    procedures: Procedure[];
}

interface WardRound {
    id: number;
    date: string | null;
    doctor: string | null;
    day_number: number | null;
    round_type: string | null;
    presenting_complaint: string | null;
    history_presenting_complaint: string | null;
    on_direct_questioning: string | null;
    examination_findings: string | null;
    assessment_notes: string | null;
    plan_notes: string | null;
    patient_status: string | null;
    prescriptions: Prescription[];
    lab_orders: LabOrder[];
    procedures: Procedure[];
}

interface Admission {
    id: number;
    admission_number: string;
    admitted_at: string | null;
    discharged_at: string | null;
    status: string;
    ward: string | null;
    bed: string | null;
    admission_reason: string | null;
    discharge_notes: string | null;
    admitting_doctor: string | null;
    diagnoses: Diagnosis[];
    ward_rounds: WardRound[];
}

interface BackgroundHistory {
    past_medical_surgical_history: string | null;
    drug_history: string | null;
    family_history: string | null;
    social_history: string | null;
}

interface MedicalHistoryData {
    patient_name: string;
    background_history: BackgroundHistory;
    medical_history: {
        consultations: Consultation[];
        admissions: Admission[];
        minor_procedures: MinorProcedureRecord[];
    };
}

interface MinorProcedureRecord {
    id: number;
    date: string | null;
    nurse: string | null;
    department: string | null;
    procedure_name: string | null;
    procedure_code: string | null;
    procedure_notes: string | null;
    vitals: ConsultationVitals | null;
    diagnoses: { code: string | null; description: string | null }[];
    supplies: {
        drug_name: string | null;
        quantity: number | null;
        status: string;
    }[];
}

interface PatientMedicalHistoryModalProps {
    patientId: number | null;
    isOpen: boolean;
    onClose: () => void;
}

export function PatientMedicalHistoryModal({
    patientId,
    isOpen,
    onClose,
}: PatientMedicalHistoryModalProps) {
    const [data, setData] = useState<MedicalHistoryData | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [selectedLabResult, setSelectedLabResult] = useState<LabOrder | null>(null);

    useEffect(() => {
        if (patientId && isOpen) {
            setLoading(true);
            setError(null);

            fetch(`/patients/${patientId}/medical-history`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then((response) => {
                    if (!response.ok)
                        throw new Error('Failed to load medical history');
                    return response.json();
                })
                .then((result: MedicalHistoryData) => {
                    setData(result);
                    setLoading(false);
                })
                .catch((err) => {
                    console.error('Failed to load medical history:', err);
                    setError('Failed to load medical history.');
                    setLoading(false);
                });
        }
    }, [patientId, isOpen]);

    useEffect(() => {
        if (!isOpen) {
            setData(null);
            setError(null);
        }
    }, [isOpen]);

    const formatDateTime = (dateString: string | null) => {
        if (!dateString) return 'N/A';
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const bg = data?.background_history;
    const hasBackground =
        bg?.past_medical_surgical_history ||
        bg?.drug_history ||
        bg?.family_history ||
        bg?.social_history;

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-h-[90vh] w-[90vw] max-w-5xl overflow-hidden p-0 sm:max-w-5xl">
                <DialogHeader className="border-b px-6 py-4">
                    <DialogTitle className="flex items-center gap-2">
                        <FileText className="h-5 w-5" aria-hidden="true" />
                        Medical History
                        {data && (
                            <span className="text-muted-foreground font-normal">
                                — {data.patient_name}
                            </span>
                        )}
                    </DialogTitle>
                </DialogHeader>

                <ScrollArea className="max-h-[calc(90vh-120px)]">
                    <div className="p-6">
                        {loading && (
                            <div
                                className="flex h-64 items-center justify-center"
                                role="status"
                            >
                                <Loader2 className="h-8 w-8 animate-spin text-gray-400" />
                                <span className="sr-only">Loading...</span>
                            </div>
                        )}

                        {error && !data && (
                            <div className="flex h-64 flex-col items-center justify-center gap-4">
                                <p className="text-gray-500">{error}</p>
                                <Button variant="outline" onClick={onClose}>
                                    Close
                                </Button>
                            </div>
                        )}

                        {data && (
                            <Tabs
                                defaultValue="consultations"
                                className="space-y-4"
                            >
                                <TabsList className="grid w-full grid-cols-4 gap-1 rounded-none border-b border-gray-200 bg-transparent p-1 dark:border-gray-700">
                                    <TabsTrigger
                                        value="background"
                                        className="flex items-center gap-1.5 rounded-md border-b-2 border-transparent bg-slate-50 text-xs text-slate-700 shadow-none transition-all hover:bg-slate-100 data-[state=active]:border-slate-600 data-[state=active]:bg-slate-100 data-[state=active]:text-slate-700 data-[state=active]:shadow-none dark:bg-slate-950 dark:text-slate-300 dark:hover:bg-slate-900 dark:data-[state=active]:border-slate-400 dark:data-[state=active]:bg-slate-900 dark:data-[state=active]:text-slate-300"
                                    >
                                        <FileText className="h-3.5 w-3.5" />
                                        <span className="hidden sm:inline">
                                            Background
                                        </span>
                                    </TabsTrigger>
                                    <TabsTrigger
                                        value="consultations"
                                        className="flex items-center gap-1.5 rounded-md border-b-2 border-transparent bg-violet-50 text-xs text-violet-700 shadow-none transition-all hover:bg-violet-100 data-[state=active]:border-violet-600 data-[state=active]:bg-violet-100 data-[state=active]:text-violet-700 data-[state=active]:shadow-none dark:bg-violet-950 dark:text-violet-300 dark:hover:bg-violet-900 dark:data-[state=active]:border-violet-400 dark:data-[state=active]:bg-violet-900 dark:data-[state=active]:text-violet-300"
                                    >
                                        <Stethoscope className="h-3.5 w-3.5" />
                                        <span className="hidden sm:inline">
                                            Consultations
                                        </span>
                                        <Badge
                                            variant="secondary"
                                            className="ml-1"
                                        >
                                            {
                                                data.medical_history
                                                    .consultations.length
                                            }
                                        </Badge>
                                    </TabsTrigger>
                                    <TabsTrigger
                                        value="admissions"
                                        className="flex items-center gap-1.5 rounded-md border-b-2 border-transparent bg-blue-50 text-xs text-blue-700 shadow-none transition-all hover:bg-blue-100 data-[state=active]:border-blue-600 data-[state=active]:bg-blue-100 data-[state=active]:text-blue-700 data-[state=active]:shadow-none dark:bg-blue-950 dark:text-blue-300 dark:hover:bg-blue-900 dark:data-[state=active]:border-blue-400 dark:data-[state=active]:bg-blue-900 dark:data-[state=active]:text-blue-300"
                                    >
                                        <Hospital className="h-3.5 w-3.5" />
                                        <span className="hidden sm:inline">
                                            Admissions
                                        </span>
                                        <Badge
                                            variant="secondary"
                                            className="ml-1"
                                        >
                                            {
                                                data.medical_history.admissions
                                                    .length
                                            }
                                        </Badge>
                                    </TabsTrigger>
                                    <TabsTrigger
                                        value="minor-procedures"
                                        className="flex items-center gap-1.5 rounded-md border-b-2 border-transparent bg-amber-50 text-xs text-amber-700 shadow-none transition-all hover:bg-amber-100 data-[state=active]:border-amber-600 data-[state=active]:bg-amber-100 data-[state=active]:text-amber-700 data-[state=active]:shadow-none dark:bg-amber-950 dark:text-amber-300 dark:hover:bg-amber-900 dark:data-[state=active]:border-amber-400 dark:data-[state=active]:bg-amber-900 dark:data-[state=active]:text-amber-300"
                                    >
                                        <Scissors className="h-3.5 w-3.5" />
                                        <span className="hidden sm:inline">
                                            Minor Procedures
                                        </span>
                                        <Badge
                                            variant="secondary"
                                            className="ml-1"
                                        >
                                            {
                                                data.medical_history
                                                    .minor_procedures?.length ?? 0
                                            }
                                        </Badge>
                                    </TabsTrigger>
                                </TabsList>

                                {/* Background Tab */}
                                <TabsContent
                                    value="background"
                                    className="space-y-4"
                                >
                                    {hasBackground ? (
                                        <div className="grid gap-4 lg:grid-cols-2">
                                            {bg?.past_medical_surgical_history && (
                                                <Card>
                                                    <CardHeader className="pb-3">
                                                        <CardTitle className="flex items-center gap-2 text-base">
                                                            <Heart className="h-4 w-4 text-red-500" />
                                                            Past
                                                            Medical/Surgical
                                                            History
                                                        </CardTitle>
                                                    </CardHeader>
                                                    <CardContent>
                                                        <p className="whitespace-pre-wrap text-sm leading-relaxed">
                                                            {
                                                                bg.past_medical_surgical_history
                                                            }
                                                        </p>
                                                    </CardContent>
                                                </Card>
                                            )}
                                            {bg?.drug_history && (
                                                <Card>
                                                    <CardHeader className="pb-3">
                                                        <CardTitle className="flex items-center gap-2 text-base">
                                                            <Pill className="h-4 w-4 text-blue-500" />
                                                            Drug History /
                                                            Allergies
                                                        </CardTitle>
                                                    </CardHeader>
                                                    <CardContent>
                                                        <p className="whitespace-pre-wrap text-sm leading-relaxed">
                                                            {bg.drug_history}
                                                        </p>
                                                    </CardContent>
                                                </Card>
                                            )}
                                            {bg?.family_history && (
                                                <Card>
                                                    <CardHeader className="pb-3">
                                                        <CardTitle className="flex items-center gap-2 text-base">
                                                            <Users className="h-4 w-4 text-purple-500" />
                                                            Family History
                                                        </CardTitle>
                                                    </CardHeader>
                                                    <CardContent>
                                                        <p className="whitespace-pre-wrap text-sm leading-relaxed">
                                                            {bg.family_history}
                                                        </p>
                                                    </CardContent>
                                                </Card>
                                            )}
                                            {bg?.social_history && (
                                                <Card>
                                                    <CardHeader className="pb-3">
                                                        <CardTitle className="flex items-center gap-2 text-base">
                                                            <ClipboardList className="h-4 w-4 text-orange-500" />
                                                            Social History
                                                        </CardTitle>
                                                    </CardHeader>
                                                    <CardContent>
                                                        <p className="whitespace-pre-wrap text-sm leading-relaxed">
                                                            {bg.social_history}
                                                        </p>
                                                    </CardContent>
                                                </Card>
                                            )}
                                        </div>
                                    ) : (
                                        <div className="py-12 text-center text-gray-500">
                                            No background history recorded
                                        </div>
                                    )}
                                </TabsContent>

                                {/* Consultations Tab */}
                                <TabsContent
                                    value="consultations"
                                    className="space-y-4"
                                >
                                    {data.medical_history.consultations.length >
                                    0 ? (
                                        <div className="space-y-3">
                                            {data.medical_history.consultations.map(
                                                (c) => (
                                                    <Collapsible key={c.id}>
                                                        <div className="rounded-lg border">
                                                            <CollapsibleTrigger className="flex w-full items-center justify-between p-4 text-left hover:bg-muted/50 transition-colors">
                                                                <div className="flex items-center gap-3">
                                                                    <ChevronRight className="h-4 w-4 text-muted-foreground transition-transform [[data-state=open]_&]:rotate-90" />
                                                                    <div>
                                                                        <div className="flex items-center gap-2">
                                                                            <span className="text-sm font-semibold">
                                                                                {c.department || 'Consultation'}
                                                                            </span>
                                                                            {c.diagnoses.length > 0 && (
                                                                                <Badge variant="secondary" className="text-xs">
                                                                                    {c.diagnoses.length} dx
                                                                                </Badge>
                                                                            )}
                                                                        </div>
                                                                        <div className="mt-0.5 flex items-center gap-2 text-xs text-muted-foreground">
                                                                            <Calendar className="h-3 w-3" />
                                                                            {formatDateTime(c.date)}
                                                                            {c.doctor && ` • Dr. ${c.doctor}`}
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </CollapsibleTrigger>
                                                            <CollapsibleContent>
                                                                <div className="border-t p-4 space-y-4">
                                                                    <Tabs defaultValue="notes" className="space-y-3">
                                                                        <TabsList className="grid w-full grid-cols-5 gap-1 rounded-none border-b border-gray-200 bg-transparent p-1 dark:border-gray-700">
                                                                            <TabsTrigger value="notes" className="flex items-center gap-1.5 rounded-md border-b-2 border-transparent bg-violet-50 text-xs text-violet-700 shadow-none transition-all hover:bg-violet-100 data-[state=active]:border-violet-600 data-[state=active]:bg-violet-100 data-[state=active]:text-violet-700 data-[state=active]:shadow-none dark:bg-violet-950 dark:text-violet-300 dark:hover:bg-violet-900 dark:data-[state=active]:border-violet-400 dark:data-[state=active]:bg-violet-900 dark:data-[state=active]:text-violet-300">
                                                                                <Stethoscope className="h-3.5 w-3.5" />
                                                                                Notes
                                                                            </TabsTrigger>
                                                                            <TabsTrigger value="diagnoses" className="flex items-center gap-1.5 rounded-md border-b-2 border-transparent bg-slate-50 text-xs text-slate-700 shadow-none transition-all hover:bg-slate-100 data-[state=active]:border-slate-600 data-[state=active]:bg-slate-100 data-[state=active]:text-slate-700 data-[state=active]:shadow-none dark:bg-slate-950 dark:text-slate-300 dark:hover:bg-slate-900 dark:data-[state=active]:border-slate-400 dark:data-[state=active]:bg-slate-900 dark:data-[state=active]:text-slate-300">
                                                                                <ClipboardList className="h-3.5 w-3.5" />
                                                                                Dx
                                                                            </TabsTrigger>
                                                                            <TabsTrigger value="vitals" className="flex items-center gap-1.5 rounded-md border-b-2 border-transparent bg-rose-50 text-xs text-rose-700 shadow-none transition-all hover:bg-rose-100 data-[state=active]:border-rose-600 data-[state=active]:bg-rose-100 data-[state=active]:text-rose-700 data-[state=active]:shadow-none dark:bg-rose-950 dark:text-rose-300 dark:hover:bg-rose-900 dark:data-[state=active]:border-rose-400 dark:data-[state=active]:bg-rose-900 dark:data-[state=active]:text-rose-300">
                                                                                <Thermometer className="h-3.5 w-3.5" />
                                                                                Vitals
                                                                            </TabsTrigger>
                                                                            <TabsTrigger value="prescriptions" className="flex items-center gap-1.5 rounded-md border-b-2 border-transparent bg-green-50 text-xs text-green-700 shadow-none transition-all hover:bg-green-100 data-[state=active]:border-green-600 data-[state=active]:bg-green-100 data-[state=active]:text-green-700 data-[state=active]:shadow-none dark:bg-green-950 dark:text-green-300 dark:hover:bg-green-900 dark:data-[state=active]:border-green-400 dark:data-[state=active]:bg-green-900 dark:data-[state=active]:text-green-300">
                                                                                <Pill className="h-3.5 w-3.5" />
                                                                                Rx
                                                                            </TabsTrigger>
                                                                            <TabsTrigger value="labs" className="flex items-center gap-1.5 rounded-md border-b-2 border-transparent bg-teal-50 text-xs text-teal-700 shadow-none transition-all hover:bg-teal-100 data-[state=active]:border-teal-600 data-[state=active]:bg-teal-100 data-[state=active]:text-teal-700 data-[state=active]:shadow-none dark:bg-teal-950 dark:text-teal-300 dark:hover:bg-teal-900 dark:data-[state=active]:border-teal-400 dark:data-[state=active]:bg-teal-900 dark:data-[state=active]:text-teal-300">
                                                                                <Beaker className="h-3.5 w-3.5" />
                                                                                Labs
                                                                            </TabsTrigger>
                                                                        </TabsList>

                                                                        <TabsContent value="notes" className="space-y-3">
                                                                            <div className="grid gap-3 text-sm">
                                                                                {c.presenting_complaint && (
                                                                                    <div>
                                                                                        <span className="font-medium text-blue-700 dark:text-blue-400">Presenting Complaint:</span>
                                                                                        <p className="mt-1">{c.presenting_complaint}</p>
                                                                                    </div>
                                                                                )}
                                                                                {c.history_presenting_complaint && (
                                                                                    <div>
                                                                                        <span className="font-medium text-teal-700 dark:text-teal-400">History of PC:</span>
                                                                                        <p className="mt-1">{c.history_presenting_complaint}</p>
                                                                                    </div>
                                                                                )}
                                                                                {c.on_direct_questioning && (
                                                                                    <div>
                                                                                        <span className="font-medium text-cyan-700 dark:text-cyan-400">On Direct Questioning:</span>
                                                                                        <p className="mt-1">{c.on_direct_questioning}</p>
                                                                                    </div>
                                                                                )}
                                                                                {c.examination_findings && (
                                                                                    <div>
                                                                                        <span className="font-medium text-amber-700 dark:text-amber-400">Examination:</span>
                                                                                        <p className="mt-1">{c.examination_findings}</p>
                                                                                    </div>
                                                                                )}
                                                                                {c.assessment_notes && (
                                                                                    <div>
                                                                                        <span className="font-medium text-orange-700 dark:text-orange-400">Assessment:</span>
                                                                                        <p className="mt-1">{c.assessment_notes}</p>
                                                                                    </div>
                                                                                )}
                                                                                {c.plan_notes && (
                                                                                    <div>
                                                                                        <span className="font-medium text-emerald-700 dark:text-emerald-400">Plan:</span>
                                                                                        <p className="mt-1">{c.plan_notes}</p>
                                                                                    </div>
                                                                                )}
                                                                                {!c.presenting_complaint && !c.history_presenting_complaint && !c.on_direct_questioning && !c.examination_findings && !c.assessment_notes && !c.plan_notes && (
                                                                                    <p className="text-sm text-muted-foreground py-4 text-center">No clinical notes recorded</p>
                                                                                )}
                                                                            </div>
                                                                        </TabsContent>

                                                                        <TabsContent value="diagnoses">
                                                                            {c.diagnoses.length > 0 ? (
                                                                                <div className="flex flex-wrap gap-2">
                                                                                    {c.diagnoses.map((d, idx) => (
                                                                                        <Badge key={idx} variant={d.type === 'principal' ? 'default' : 'secondary'}>
                                                                                            {d.code && `${d.code}: `}{d.description}
                                                                                        </Badge>
                                                                                    ))}
                                                                                </div>
                                                                            ) : (
                                                                                <p className="text-sm text-muted-foreground py-4 text-center">No diagnoses recorded</p>
                                                                            )}
                                                                            {c.procedures.length > 0 && (
                                                                                <div className="mt-3">
                                                                                    <h5 className="mb-1 text-xs font-medium text-gray-500">Procedures</h5>
                                                                                    <div className="space-y-0.5">
                                                                                        {c.procedures.map((p, idx) => (
                                                                                            <div key={idx} className="flex items-center gap-2 text-sm">
                                                                                                <Scissors className="text-muted-foreground h-3 w-3" />
                                                                                                <span>{p.name}</span>
                                                                                            </div>
                                                                                        ))}
                                                                                    </div>
                                                                                </div>
                                                                            )}
                                                                        </TabsContent>

                                                                        <TabsContent value="vitals">
                                                                            {c.vitals ? (
                                                                                <div className="grid grid-cols-2 gap-2 text-sm sm:grid-cols-4">
                                                                                    {c.vitals.blood_pressure && (
                                                                                        <div><span className="text-muted-foreground">BP:</span> <span className="font-medium">{c.vitals.blood_pressure}</span></div>
                                                                                    )}
                                                                                    {c.vitals.temperature && (
                                                                                        <div><span className="text-muted-foreground">Temp:</span> <span className="font-medium">{c.vitals.temperature}°C</span></div>
                                                                                    )}
                                                                                    {c.vitals.pulse_rate && (
                                                                                        <div><span className="text-muted-foreground">Pulse:</span> <span className="font-medium">{c.vitals.pulse_rate}</span></div>
                                                                                    )}
                                                                                    {c.vitals.oxygen_saturation && (
                                                                                        <div><span className="text-muted-foreground">SpO2:</span> <span className="font-medium">{c.vitals.oxygen_saturation}%</span></div>
                                                                                    )}
                                                                                    {c.vitals.blood_sugar && (
                                                                                        <div><span className="text-muted-foreground">BS:</span> <span className="font-medium">{c.vitals.blood_sugar} mmol/L</span></div>
                                                                                    )}
                                                                                    {c.vitals.weight && (
                                                                                        <div><span className="text-muted-foreground">Wt:</span> <span className="font-medium">{c.vitals.weight}kg</span></div>
                                                                                    )}
                                                                                </div>
                                                                            ) : (
                                                                                <p className="text-sm text-muted-foreground py-4 text-center">No vitals recorded</p>
                                                                            )}
                                                                        </TabsContent>

                                                                        <TabsContent value="prescriptions">
                                                                            {c.prescriptions.length > 0 ? (
                                                                                <div className="space-y-1">
                                                                                    {c.prescriptions.map((p, idx) => (
                                                                                        <div key={idx} className="flex items-center gap-2 text-sm">
                                                                                            <Pill className="text-muted-foreground h-3.5 w-3.5" />
                                                                                            <span>{p.drug_name}{p.strength && ` ${p.strength}`}{p.dose_quantity && ` - ${p.dose_quantity}`}{p.frequency && ` ${p.frequency}`}{p.duration && ` x ${p.duration}`}</span>
                                                                                            <Badge variant="outline" className="text-xs">{p.status}</Badge>
                                                                                        </div>
                                                                                    ))}
                                                                                </div>
                                                                            ) : (
                                                                                <p className="text-sm text-muted-foreground py-4 text-center">No prescriptions</p>
                                                                            )}
                                                                        </TabsContent>

                                                                        <TabsContent value="labs">
                                                                            {c.lab_orders.length > 0 ? (
                                                                                <div className="space-y-1">
                                                                                    {c.lab_orders.map((l, idx) => (
                                                                                        <div key={idx} className="flex items-center gap-2 text-sm">
                                                                                            <Beaker className="text-muted-foreground h-3.5 w-3.5" />
                                                                                            <span>{l.service_name}</span>
                                                                                            <Badge variant={l.status === 'completed' ? 'default' : 'secondary'} className="text-xs">{l.status}</Badge>
                                                                                            {l.status === 'completed' && l.result_values && (
                                                                                                <Button variant="ghost" size="sm" className="h-6 px-2 text-xs" onClick={() => setSelectedLabResult(l)}>
                                                                                                    <Eye className="mr-1 h-3 w-3" />
                                                                                                    View Results
                                                                                                </Button>
                                                                                            )}
                                                                                        </div>
                                                                                    ))}
                                                                                </div>
                                                                            ) : (
                                                                                <p className="text-sm text-muted-foreground py-4 text-center">No lab orders</p>
                                                                            )}
                                                                        </TabsContent>
                                                                    </Tabs>
                                                                </div>
                                                            </CollapsibleContent>
                                                        </div>
                                                    </Collapsible>
                                                ),
                                            )}
                                        </div>
                                    ) : (
                                        <div className="py-12 text-center text-gray-500">
                                            No consultation records found
                                        </div>
                                    )}
                                </TabsContent>
                                {/* Admissions Tab */}
                                <TabsContent
                                    value="admissions"
                                    className="space-y-4"
                                >
                                    {data.medical_history.admissions.length >
                                    0 ? (
                                        <div className="space-y-3">
                                            {data.medical_history.admissions.map(
                                                (a) => (
                                                    <Collapsible key={a.id}>
                                                        <div className="rounded-lg border">
                                                            <CollapsibleTrigger className="flex w-full items-center justify-between p-4 text-left hover:bg-muted/50 transition-colors">
                                                                <div className="flex items-center gap-3">
                                                                    <ChevronRight className="h-4 w-4 text-muted-foreground transition-transform [[data-state=open]_&]:rotate-90" />
                                                                    <div>
                                                                        <div className="flex items-center gap-2">
                                                                            <span className="text-sm font-semibold">
                                                                                {a.admission_number}
                                                                            </span>
                                                                            <Badge
                                                                                variant={a.status === 'discharged' ? 'outline' : 'secondary'}
                                                                                className="text-xs"
                                                                            >
                                                                                {a.status}
                                                                            </Badge>
                                                                        </div>
                                                                        <div className="mt-0.5 flex items-center gap-2 text-xs text-muted-foreground">
                                                                            <Calendar className="h-3 w-3" />
                                                                            {formatDateTime(a.admitted_at)}
                                                                            {a.discharged_at && ` → ${formatDateTime(a.discharged_at)}`}
                                                                            {a.ward && ` • ${a.ward}`}
                                                                            {a.bed && ` (Bed ${a.bed})`}
                                                                            {a.admitting_doctor && ` • Dr. ${a.admitting_doctor}`}
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                {a.ward_rounds && a.ward_rounds.length > 0 && (
                                                                    <Badge variant="secondary" className="text-xs">
                                                                        {a.ward_rounds.length} round{a.ward_rounds.length !== 1 ? 's' : ''}
                                                                    </Badge>
                                                                )}
                                                            </CollapsibleTrigger>
                                                            <CollapsibleContent>
                                                                <div className="border-t p-4 space-y-4">
                                                                    {a.admission_reason && (
                                                                        <div className="text-sm">
                                                                            <span className="text-muted-foreground font-medium">Reason:</span>
                                                                            <p className="mt-1">{a.admission_reason}</p>
                                                                        </div>
                                                                    )}

                                                                    {a.diagnoses.length > 0 && (
                                                                        <div>
                                                                            <span className="text-sm text-muted-foreground font-medium">Diagnoses:</span>
                                                                            <div className="mt-1 flex flex-wrap gap-2">
                                                                                {a.diagnoses.map((d, idx) => (
                                                                                    <Badge key={idx} variant="secondary">
                                                                                        {d.code && `${d.code}: `}
                                                                                        {d.description}
                                                                                    </Badge>
                                                                                ))}
                                                                            </div>
                                                                        </div>
                                                                    )}

                                                                    {/* Inline Admission Detail */}
                                                                    {patientId && (
                                                                        <AdmissionDetailView
                                                                            patientId={patientId}
                                                                            admissionId={a.id}
                                                                        />
                                                                    )}
                                                                </div>
                                                            </CollapsibleContent>
                                                        </div>
                                                    </Collapsible>
                                                ),
                                            )}
                                        </div>
                                    ) : (
                                        <div className="py-12 text-center text-gray-500">
                                            No admission records found
                                        </div>
                                    )}
                                </TabsContent>

                                {/* Minor Procedures Tab */}
                                <TabsContent
                                    value="minor-procedures"
                                    className="space-y-4"
                                >
                                    {(data.medical_history.minor_procedures?.length ?? 0) >
                                    0 ? (
                                        data.medical_history.minor_procedures.map(
                                            (mp) => (
                                                <Card key={mp.id}>
                                                    <CardHeader className="pb-3">
                                                        <div className="flex items-start justify-between">
                                                            <div>
                                                                <CardTitle className="text-base">
                                                                    {mp.procedure_name ||
                                                                        'Minor Procedure'}
                                                                </CardTitle>
                                                                <CardDescription className="mt-1 flex items-center gap-2">
                                                                    <Calendar className="h-3.5 w-3.5" />
                                                                    {formatDateTime(
                                                                        mp.date,
                                                                    )}
                                                                    {mp.nurse && (
                                                                        <span>
                                                                            •{' '}
                                                                            {
                                                                                mp.nurse
                                                                            }
                                                                        </span>
                                                                    )}
                                                                </CardDescription>
                                                            </div>
                                                        </div>
                                                    </CardHeader>
                                                    <CardContent className="space-y-4">
                                                        {/* Vitals */}
                                                        {mp.vitals && (
                                                            <div className="rounded-lg bg-muted/50 p-3">
                                                                <h4 className="mb-2 flex items-center gap-2 text-sm font-medium">
                                                                    <Thermometer className="h-3.5 w-3.5" />
                                                                    Vitals
                                                                </h4>
                                                                <div className="grid grid-cols-2 gap-2 text-sm sm:grid-cols-4">
                                                                    {mp.vitals
                                                                        .blood_pressure && (
                                                                        <div>
                                                                            <span className="text-muted-foreground">
                                                                                BP:
                                                                            </span>{' '}
                                                                            <span className="font-medium">
                                                                                {
                                                                                    mp
                                                                                        .vitals
                                                                                        .blood_pressure
                                                                                }
                                                                            </span>
                                                                        </div>
                                                                    )}
                                                                    {mp.vitals
                                                                        .temperature && (
                                                                        <div>
                                                                            <span className="text-muted-foreground">
                                                                                Temp:
                                                                            </span>{' '}
                                                                            <span className="font-medium">
                                                                                {
                                                                                    mp
                                                                                        .vitals
                                                                                        .temperature
                                                                                }
                                                                                °C
                                                                            </span>
                                                                        </div>
                                                                    )}
                                                                    {mp.vitals
                                                                        .pulse_rate && (
                                                                        <div>
                                                                            <span className="text-muted-foreground">
                                                                                Pulse:
                                                                            </span>{' '}
                                                                            <span className="font-medium">
                                                                                {
                                                                                    mp
                                                                                        .vitals
                                                                                        .pulse_rate
                                                                                }
                                                                            </span>
                                                                        </div>
                                                                    )}
                                                                    {mp.vitals
                                                                        .oxygen_saturation && (
                                                                        <div>
                                                                            <span className="text-muted-foreground">
                                                                                SpO2:
                                                                            </span>{' '}
                                                                            <span className="font-medium">
                                                                                {
                                                                                    mp
                                                                                        .vitals
                                                                                        .oxygen_saturation
                                                                                }
                                                                                %
                                                                            </span>
                                                                        </div>
                                                                    )}
                                                                    {mp.vitals
                                                                        .blood_sugar && (
                                                                        <div>
                                                                            <span className="text-muted-foreground">
                                                                                BS:
                                                                            </span>{' '}
                                                                            <span className="font-medium">
                                                                                {
                                                                                    mp
                                                                                        .vitals
                                                                                        .blood_sugar
                                                                                }
                                                                                {' '}mmol/L
                                                                            </span>
                                                                        </div>
                                                                    )}
                                                                    {mp.vitals
                                                                        .weight && (
                                                                        <div>
                                                                            <span className="text-muted-foreground">
                                                                                Wt:
                                                                            </span>{' '}
                                                                            <span className="font-medium">
                                                                                {
                                                                                    mp
                                                                                        .vitals
                                                                                        .weight
                                                                                }
                                                                                kg
                                                                            </span>
                                                                        </div>
                                                                    )}
                                                                </div>
                                                            </div>
                                                        )}

                                                        {/* Diagnoses */}
                                                        {mp.diagnoses.length >
                                                            0 && (
                                                            <div>
                                                                <h4 className="mb-2 text-sm font-medium">
                                                                    Diagnoses
                                                                </h4>
                                                                <div className="flex flex-wrap gap-2">
                                                                    {mp.diagnoses.map(
                                                                        (
                                                                            d,
                                                                            idx,
                                                                        ) => (
                                                                            <Badge
                                                                                key={
                                                                                    idx
                                                                                }
                                                                                variant="secondary"
                                                                            >
                                                                                {d.code &&
                                                                                    `${d.code}: `}
                                                                                {
                                                                                    d.description
                                                                                }
                                                                            </Badge>
                                                                        ),
                                                                    )}
                                                                </div>
                                                            </div>
                                                        )}

                                                        {/* Procedure Notes */}
                                                        {mp.procedure_notes && (
                                                            <div className="text-sm">
                                                                <span className="text-muted-foreground font-medium">
                                                                    Notes:
                                                                </span>
                                                                <p className="mt-1">
                                                                    {
                                                                        mp.procedure_notes
                                                                    }
                                                                </p>
                                                            </div>
                                                        )}

                                                        {/* Supplies */}
                                                        {mp.supplies.length >
                                                            0 && (
                                                            <div>
                                                                <h4 className="mb-2 text-sm font-medium">
                                                                    Supplies
                                                                </h4>
                                                                <div className="space-y-1">
                                                                    {mp.supplies.map(
                                                                        (
                                                                            s,
                                                                            idx,
                                                                        ) => (
                                                                            <div
                                                                                key={
                                                                                    idx
                                                                                }
                                                                                className="flex items-center gap-2 text-sm"
                                                                            >
                                                                                <Pill className="text-muted-foreground h-3.5 w-3.5" />
                                                                                <span>
                                                                                    {
                                                                                        s.drug_name
                                                                                    }
                                                                                    {s.quantity &&
                                                                                        ` x ${s.quantity}`}
                                                                                </span>
                                                                                <Badge
                                                                                    variant={
                                                                                        s.status ===
                                                                                        'dispensed'
                                                                                            ? 'default'
                                                                                            : 'secondary'
                                                                                    }
                                                                                    className="text-xs"
                                                                                >
                                                                                    {
                                                                                        s.status
                                                                                    }
                                                                                </Badge>
                                                                            </div>
                                                                        ),
                                                                    )}
                                                                </div>
                                                            </div>
                                                        )}
                                                    </CardContent>
                                                </Card>
                                            ),
                                        )
                                    ) : (
                                        <div className="py-12 text-center text-gray-500">
                                            No minor procedure records found
                                        </div>
                                    )}
                                </TabsContent>
                            </Tabs>
                        )}
                    </div>
                </ScrollArea>

                {/* Lab Results Dialog */}
                {selectedLabResult && (
                    <Dialog
                        open={!!selectedLabResult}
                        onOpenChange={() => setSelectedLabResult(null)}
                    >
                        <DialogContent className="max-w-2xl">
                            <DialogHeader>
                                <DialogTitle className="flex items-center gap-2">
                                    {selectedLabResult.is_imaging ? (
                                        <FileText className="h-5 w-5" />
                                    ) : (
                                        <Beaker className="h-5 w-5" />
                                    )}
                                    {selectedLabResult.service_name}
                                    {selectedLabResult.code && (
                                        <span className="text-sm font-normal text-muted-foreground">
                                            ({selectedLabResult.code})
                                        </span>
                                    )}
                                </DialogTitle>
                            </DialogHeader>
                            <div className="space-y-4">
                                {selectedLabResult.result_entered_at && (
                                    <div className="text-sm text-muted-foreground">
                                        Results entered:{' '}
                                        {formatDateTime(selectedLabResult.result_entered_at)}
                                    </div>
                                )}

                                {selectedLabResult.result_values &&
                                Object.keys(selectedLabResult.result_values).length > 0 ? (
                                    <div className="space-y-2">
                                        <h4 className="text-sm font-medium">Results</h4>
                                        <div className="max-h-[60vh] overflow-y-auto pr-1">
                                            <div className="grid gap-2 grid-cols-2 sm:grid-cols-3">
                                                {Object.entries(selectedLabResult.result_values).map(
                                                    ([key, result]) => {
                                                        const isObject =
                                                            typeof result === 'object' && result !== null;
                                                        const value = isObject
                                                            ? (result as Record<string, unknown>).value
                                                            : result;
                                                        const unit = isObject
                                                            ? String((result as Record<string, unknown>).unit || '')
                                                            : '';
                                                        let range: string = isObject
                                                            ? String((result as Record<string, unknown>).range || '')
                                                            : '';
                                                        let flag = isObject
                                                            ? ((result as Record<string, unknown>).flag as string)
                                                            : 'normal';

                                                        if (!range && selectedLabResult.test_parameters?.parameters) {
                                                            const param = selectedLabResult.test_parameters.parameters.find(
                                                                (p) => p.name === key || p.name.toLowerCase() === key.toLowerCase(),
                                                            );
                                                            if (param?.normal_range) {
                                                                const { min, max } = param.normal_range;
                                                                if (min !== undefined && max !== undefined) {
                                                                    range = `${min}-${max}`;
                                                                } else if (min !== undefined) {
                                                                    range = `>${min}`;
                                                                } else if (max !== undefined) {
                                                                    range = `<${max}`;
                                                                }
                                                                if (flag === 'normal' && param.type === 'numeric') {
                                                                    const numValue = parseFloat(String(value));
                                                                    if (!isNaN(numValue)) {
                                                                        if (min !== undefined && numValue < min) flag = 'low';
                                                                        else if (max !== undefined && numValue > max) flag = 'high';
                                                                    }
                                                                }
                                                            }
                                                        }

                                                        const getFlagColor = (f: string) => {
                                                            switch (f) {
                                                                case 'high':
                                                                case 'critical':
                                                                    return 'text-red-600 dark:text-red-400';
                                                                case 'low':
                                                                    return 'text-orange-600 dark:text-orange-400';
                                                                default:
                                                                    return '';
                                                            }
                                                        };

                                                        return (
                                                            <div key={key} className="rounded-lg border px-2.5 py-2">
                                                                <span className="text-xs text-muted-foreground capitalize">
                                                                    {key.replace(/_/g, ' ')}
                                                                </span>
                                                                <p className={`text-sm font-semibold leading-tight ${getFlagColor(flag)}`}>
                                                                    {String(value)}
                                                                    {unit && (
                                                                        <span className="ml-1 text-xs font-normal text-muted-foreground">
                                                                            {unit}
                                                                        </span>
                                                                    )}
                                                                </p>
                                                                <div className="flex items-center gap-1 mt-0.5">
                                                                    {range && (
                                                                        <span className="text-xs text-muted-foreground">
                                                                            Ref: {range}
                                                                        </span>
                                                                    )}
                                                                    {flag && flag !== 'normal' && (
                                                                        <span className={`text-xs font-medium ${getFlagColor(flag)}`}>
                                                                            ({flag.toUpperCase()})
                                                                        </span>
                                                                    )}
                                                                </div>
                                                            </div>
                                                        );
                                                    },
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                ) : null}

                                {selectedLabResult.result_notes && (
                                    <div>
                                        <h4 className="mb-1 text-sm font-medium">Notes</h4>
                                        <p className="text-sm whitespace-pre-wrap text-muted-foreground">
                                            {selectedLabResult.result_notes}
                                        </p>
                                    </div>
                                )}
                            </div>
                        </DialogContent>
                    </Dialog>
                )}
            </DialogContent>
        </Dialog>
    );
}