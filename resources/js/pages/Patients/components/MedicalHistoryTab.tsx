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
    Pill,
    Scissors,
    Stethoscope,
    Thermometer,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import AdmissionDetailView from './AdmissionDetailView';


interface Diagnosis {
    type: string;
    code: string | null;
    description: string | null;
    notes?: string | null;
    is_active?: boolean;
}

interface Prescription {
    id?: number;
    date?: string | null;
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
    id?: number;
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
    ordered_by?: string | null;
    ordered_at?: string | null;
    result_entered_at: string | null;
    result_values: Record<string, unknown> | null;
    result_notes: string | null;
}

interface ConsultationVitals {
    blood_pressure: string | null;
    temperature: number | null;
    pulse_rate: number | null;
    respiratory_rate: number | null;
    oxygen_saturation: number | null;
    weight: number | null;
    height: number | null;
    bmi: number | null;
    recorded_at: string | null;
    recorded_by: string | null;
}

interface Procedure {
    name: string | null;
    code: string | null;
    notes: string | null;
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

interface VitalSign {
    id: number;
    recorded_at: string | null;
    recorded_by: string | null;
    blood_pressure: string;
    temperature: number | null;
    pulse_rate: number | null;
    respiratory_rate: number | null;
    oxygen_saturation: number | null;
    weight: number | null;
    height: number | null;
    bmi: number | null;
    notes: string | null;
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
    ward_rounds?: WardRound[];
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

interface BackgroundHistory {
    past_medical_surgical_history: string | null;
    drug_history: string | null;
    family_history: string | null;
    social_history: string | null;
}

interface TheatreProcedure {
    id: number;
    performed_at: string | null;
    procedure_name: string | null;
    procedure_code: string | null;
    category: string | null;
    doctor: string | null;
    department: string | null;
    indication: string | null;
    assistant: string | null;
    anaesthetist: string | null;
    anaesthesia_type: string | null;
    procedure_subtype: string | null;
    procedure_steps: string | null;
    findings: string | null;
    plan: string | null;
    comments: string | null;
    estimated_gestational_age: string | null;
    parity: string | null;
}

interface MedicalHistory {
    consultations: Consultation[];
    vitals: VitalSign[];
    admissions: Admission[];
    prescriptions: Prescription[];
    lab_results: LabOrder[];
    theatre_procedures: TheatreProcedure[];
    minor_procedures: MinorProcedureRecord[];
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

interface Props {
    patientId: number;
    backgroundHistory: BackgroundHistory;
    medicalHistory: MedicalHistory | null;
}

export default function MedicalHistoryTab({
    patientId,
    backgroundHistory,
    medicalHistory,
}: Props) {
    const [selectedLabResult, setSelectedLabResult] = useState<LabOrder | null>(
        null,
    );

    const formatDate = (dateString: string | null) => {
        if (!dateString) return 'N/A';
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    };

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

    const getStatusBadgeVariant = (
        status: string,
    ): 'default' | 'secondary' | 'outline' | 'destructive' => {
        const statusMap: Record<
            string,
            'default' | 'secondary' | 'outline' | 'destructive'
        > = {
            completed: 'default',
            dispensed: 'default',
            admitted: 'secondary',
            discharged: 'outline',
            pending: 'secondary',
            prescribed: 'secondary',
            cancelled: 'destructive',
        };
        return statusMap[status] || 'outline';
    };

    const hasBackgroundHistory =
        backgroundHistory.past_medical_surgical_history ||
        backgroundHistory.drug_history ||
        backgroundHistory.family_history ||
        backgroundHistory.social_history;

    return (
        <Tabs defaultValue="consultations" className="space-y-4">
            <TabsList className="grid w-full grid-cols-5 gap-1 rounded-none border-b border-gray-200 bg-transparent p-1 dark:border-gray-700">
                <TabsTrigger
                    value="background"
                    className="flex items-center gap-1.5 rounded-md border-b-2 border-transparent bg-slate-50 text-xs text-slate-700 shadow-none transition-all hover:bg-slate-100 data-[state=active]:border-slate-600 data-[state=active]:bg-slate-100 data-[state=active]:text-slate-700 data-[state=active]:shadow-none dark:bg-slate-950 dark:text-slate-300 dark:hover:bg-slate-900 dark:data-[state=active]:border-slate-400 dark:data-[state=active]:bg-slate-900 dark:data-[state=active]:text-slate-300"
                >
                    <FileText className="h-3.5 w-3.5" />
                    <span className="hidden sm:inline">Background</span>
                </TabsTrigger>
                <TabsTrigger
                    value="consultations"
                    className="flex items-center gap-1.5 rounded-md border-b-2 border-transparent bg-violet-50 text-xs text-violet-700 shadow-none transition-all hover:bg-violet-100 data-[state=active]:border-violet-600 data-[state=active]:bg-violet-100 data-[state=active]:text-violet-700 data-[state=active]:shadow-none dark:bg-violet-950 dark:text-violet-300 dark:hover:bg-violet-900 dark:data-[state=active]:border-violet-400 dark:data-[state=active]:bg-violet-900 dark:data-[state=active]:text-violet-300"
                >
                    <Stethoscope className="h-3.5 w-3.5" />
                    <span className="hidden sm:inline">Consultations</span>
                </TabsTrigger>
                <TabsTrigger
                    value="minor-procedures"
                    className="flex items-center gap-1.5 rounded-md border-b-2 border-transparent bg-amber-50 text-xs text-amber-700 shadow-none transition-all hover:bg-amber-100 data-[state=active]:border-amber-600 data-[state=active]:bg-amber-100 data-[state=active]:text-amber-700 data-[state=active]:shadow-none dark:bg-amber-950 dark:text-amber-300 dark:hover:bg-amber-900 dark:data-[state=active]:border-amber-400 dark:data-[state=active]:bg-amber-900 dark:data-[state=active]:text-amber-300"
                >
                    <Scissors className="h-3.5 w-3.5" />
                    <span className="hidden sm:inline">Minor Procedures</span>
                </TabsTrigger>
                <TabsTrigger
                    value="theatre"
                    className="flex items-center gap-1.5 rounded-md border-b-2 border-transparent bg-rose-50 text-xs text-rose-700 shadow-none transition-all hover:bg-rose-100 data-[state=active]:border-rose-600 data-[state=active]:bg-rose-100 data-[state=active]:text-rose-700 data-[state=active]:shadow-none dark:bg-rose-950 dark:text-rose-300 dark:hover:bg-rose-900 dark:data-[state=active]:border-rose-400 dark:data-[state=active]:bg-rose-900 dark:data-[state=active]:text-rose-300"
                >
                    <Scissors className="h-3.5 w-3.5" />
                    <span className="hidden sm:inline">Theatre</span>
                </TabsTrigger>
                <TabsTrigger
                    value="admissions"
                    className="flex items-center gap-1.5 rounded-md border-b-2 border-transparent bg-blue-50 text-xs text-blue-700 shadow-none transition-all hover:bg-blue-100 data-[state=active]:border-blue-600 data-[state=active]:bg-blue-100 data-[state=active]:text-blue-700 data-[state=active]:shadow-none dark:bg-blue-950 dark:text-blue-300 dark:hover:bg-blue-900 dark:data-[state=active]:border-blue-400 dark:data-[state=active]:bg-blue-900 dark:data-[state=active]:text-blue-300"
                >
                    <Hospital className="h-3.5 w-3.5" />
                    <span className="hidden sm:inline">Admissions</span>
                </TabsTrigger>
            </TabsList>

            {/* Background History Tab */}
            <TabsContent value="background" className="space-y-4">
                {hasBackgroundHistory ? (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {backgroundHistory.past_medical_surgical_history && (
                            <Card>
                                <CardHeader className="pb-3">
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <Heart className="h-4 w-4 text-red-500" />
                                        Past Medical/Surgical History
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm leading-relaxed whitespace-pre-wrap">
                                        {
                                            backgroundHistory.past_medical_surgical_history
                                        }
                                    </p>
                                </CardContent>
                            </Card>
                        )}
                        {backgroundHistory.drug_history && (
                            <Card>
                                <CardHeader className="pb-3">
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <Pill className="h-4 w-4 text-blue-500" />
                                        Drug History / Allergies
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm leading-relaxed whitespace-pre-wrap">
                                        {backgroundHistory.drug_history}
                                    </p>
                                </CardContent>
                            </Card>
                        )}
                        {backgroundHistory.family_history && (
                            <Card>
                                <CardHeader className="pb-3">
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <Users className="h-4 w-4 text-purple-500" />
                                        Family History
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm leading-relaxed whitespace-pre-wrap">
                                        {backgroundHistory.family_history}
                                    </p>
                                </CardContent>
                            </Card>
                        )}
                        {backgroundHistory.social_history && (
                            <Card>
                                <CardHeader className="pb-3">
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <ClipboardList className="h-4 w-4 text-orange-500" />
                                        Social History
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm leading-relaxed whitespace-pre-wrap">
                                        {backgroundHistory.social_history}
                                    </p>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                ) : (
                    <EmptyState
                        icon={FileText}
                        title="No Background History"
                        description="No background medical history has been recorded"
                    />
                )}
            </TabsContent>

            {/* Consultations Tab */}
            <TabsContent value="consultations" className="space-y-4">
                {medicalHistory?.consultations &&
                medicalHistory.consultations.length > 0 ? (
                    <div className="space-y-3">
                        {medicalHistory.consultations.map((consultation) => (
                            <Collapsible key={consultation.id}>
                                <div className="rounded-lg border">
                                    <CollapsibleTrigger className="flex w-full items-center justify-between p-4 text-left hover:bg-muted/50 transition-colors">
                                        <div className="flex items-center gap-3">
                                            <ChevronRight className="h-4 w-4 text-muted-foreground transition-transform [[data-state=open]_&]:rotate-90" />
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <span className="text-sm font-semibold">
                                                        {consultation.department || 'Consultation'}
                                                    </span>
                                                    {consultation.diagnoses.length > 0 && (
                                                        <Badge variant="secondary" className="text-xs">
                                                            {consultation.diagnoses.length} dx
                                                        </Badge>
                                                    )}
                                                </div>
                                                <div className="mt-0.5 flex items-center gap-2 text-xs text-muted-foreground">
                                                    <Calendar className="h-3 w-3" />
                                                    {formatDateTime(consultation.date)}
                                                    {consultation.doctor && ` • Dr. ${consultation.doctor}`}
                                                </div>
                                            </div>
                                        </div>
                                    </CollapsibleTrigger>
                                    <CollapsibleContent>
                                        <div className="border-t p-4 space-y-4">
                                            <Tabs defaultValue="notes" className="space-y-3">
                                                <TabsList className="grid w-full grid-cols-5 gap-1 rounded-none border-b border-gray-200 bg-transparent p-1 dark:border-gray-700">
                                                    <TabsTrigger
                                                        value="notes"
                                                        className="flex items-center gap-1.5 rounded-md border-b-2 border-transparent bg-violet-50 text-xs text-violet-700 shadow-none transition-all hover:bg-violet-100 data-[state=active]:border-violet-600 data-[state=active]:bg-violet-100 data-[state=active]:text-violet-700 data-[state=active]:shadow-none dark:bg-violet-950 dark:text-violet-300 dark:hover:bg-violet-900 dark:data-[state=active]:border-violet-400 dark:data-[state=active]:bg-violet-900 dark:data-[state=active]:text-violet-300"
                                                    >
                                                        <Stethoscope className="h-3.5 w-3.5" />
                                                        Notes
                                                    </TabsTrigger>
                                                    <TabsTrigger
                                                        value="diagnoses"
                                                        className="flex items-center gap-1.5 rounded-md border-b-2 border-transparent bg-slate-50 text-xs text-slate-700 shadow-none transition-all hover:bg-slate-100 data-[state=active]:border-slate-600 data-[state=active]:bg-slate-100 data-[state=active]:text-slate-700 data-[state=active]:shadow-none dark:bg-slate-950 dark:text-slate-300 dark:hover:bg-slate-900 dark:data-[state=active]:border-slate-400 dark:data-[state=active]:bg-slate-900 dark:data-[state=active]:text-slate-300"
                                                    >
                                                        <ClipboardList className="h-3.5 w-3.5" />
                                                        Diagnoses
                                                    </TabsTrigger>
                                                    <TabsTrigger
                                                        value="vitals"
                                                        className="flex items-center gap-1.5 rounded-md border-b-2 border-transparent bg-rose-50 text-xs text-rose-700 shadow-none transition-all hover:bg-rose-100 data-[state=active]:border-rose-600 data-[state=active]:bg-rose-100 data-[state=active]:text-rose-700 data-[state=active]:shadow-none dark:bg-rose-950 dark:text-rose-300 dark:hover:bg-rose-900 dark:data-[state=active]:border-rose-400 dark:data-[state=active]:bg-rose-900 dark:data-[state=active]:text-rose-300"
                                                    >
                                                        <Thermometer className="h-3.5 w-3.5" />
                                                        Vitals
                                                    </TabsTrigger>
                                                    <TabsTrigger
                                                        value="prescriptions"
                                                        className="flex items-center gap-1.5 rounded-md border-b-2 border-transparent bg-green-50 text-xs text-green-700 shadow-none transition-all hover:bg-green-100 data-[state=active]:border-green-600 data-[state=active]:bg-green-100 data-[state=active]:text-green-700 data-[state=active]:shadow-none dark:bg-green-950 dark:text-green-300 dark:hover:bg-green-900 dark:data-[state=active]:border-green-400 dark:data-[state=active]:bg-green-900 dark:data-[state=active]:text-green-300"
                                                    >
                                                        <Pill className="h-3.5 w-3.5" />
                                                        Rx
                                                    </TabsTrigger>
                                                    <TabsTrigger
                                                        value="labs"
                                                        className="flex items-center gap-1.5 rounded-md border-b-2 border-transparent bg-teal-50 text-xs text-teal-700 shadow-none transition-all hover:bg-teal-100 data-[state=active]:border-teal-600 data-[state=active]:bg-teal-100 data-[state=active]:text-teal-700 data-[state=active]:shadow-none dark:bg-teal-950 dark:text-teal-300 dark:hover:bg-teal-900 dark:data-[state=active]:border-teal-400 dark:data-[state=active]:bg-teal-900 dark:data-[state=active]:text-teal-300"
                                                    >
                                                        <Beaker className="h-3.5 w-3.5" />
                                                        Labs
                                                    </TabsTrigger>
                                                </TabsList>

                                                {/* Clinical Notes Tab */}
                                                <TabsContent value="notes" className="space-y-3">
                                                    <div className="grid gap-3 text-sm">
                                                        {consultation.presenting_complaint && (
                                                            <div>
                                                                <span className="font-medium text-blue-700 dark:text-blue-400">Presenting Complaint:</span>
                                                                <p className="mt-1">{consultation.presenting_complaint}</p>
                                                            </div>
                                                        )}
                                                        {consultation.history_presenting_complaint && (
                                                            <div>
                                                                <span className="font-medium text-teal-700 dark:text-teal-400">History of PC:</span>
                                                                <p className="mt-1">{consultation.history_presenting_complaint}</p>
                                                            </div>
                                                        )}
                                                        {consultation.on_direct_questioning && (
                                                            <div>
                                                                <span className="font-medium text-cyan-700 dark:text-cyan-400">On Direct Questioning:</span>
                                                                <p className="mt-1">{consultation.on_direct_questioning}</p>
                                                            </div>
                                                        )}
                                                        {consultation.examination_findings && (
                                                            <div>
                                                                <span className="font-medium text-amber-700 dark:text-amber-400">Examination:</span>
                                                                <p className="mt-1">{consultation.examination_findings}</p>
                                                            </div>
                                                        )}
                                                        {consultation.assessment_notes && (
                                                            <div>
                                                                <span className="font-medium text-orange-700 dark:text-orange-400">Assessment:</span>
                                                                <p className="mt-1">{consultation.assessment_notes}</p>
                                                            </div>
                                                        )}
                                                        {consultation.plan_notes && (
                                                            <div>
                                                                <span className="font-medium text-emerald-700 dark:text-emerald-400">Plan:</span>
                                                                <p className="mt-1">{consultation.plan_notes}</p>
                                                            </div>
                                                        )}
                                                        {!consultation.presenting_complaint && !consultation.history_presenting_complaint && !consultation.on_direct_questioning && !consultation.examination_findings && !consultation.assessment_notes && !consultation.plan_notes && (
                                                            <p className="text-sm text-muted-foreground py-4 text-center">No clinical notes recorded</p>
                                                        )}
                                                    </div>
                                                </TabsContent>

                                                {/* Diagnoses Tab */}
                                                <TabsContent value="diagnoses">
                                                    {consultation.diagnoses.length > 0 ? (
                                                        <div className="flex flex-wrap gap-2">
                                                            {consultation.diagnoses.map((d, idx) => (
                                                                <Badge key={idx} variant={d.type === 'principal' ? 'default' : 'secondary'}>
                                                                    {d.code && `${d.code}: `}{d.description}
                                                                </Badge>
                                                            ))}
                                                        </div>
                                                    ) : (
                                                        <p className="text-sm text-muted-foreground py-4 text-center">No diagnoses recorded</p>
                                                    )}
                                                    {consultation.procedures.length > 0 && (
                                                        <div className="mt-3">
                                                            <h5 className="mb-1 text-xs font-medium text-gray-500">Procedures</h5>
                                                            <div className="space-y-0.5">
                                                                {consultation.procedures.map((p, idx) => (
                                                                    <div key={idx} className="flex items-center gap-2 text-sm">
                                                                        <Scissors className="h-3 w-3 text-muted-foreground" />
                                                                        <span>{p.name}</span>
                                                                        {p.code && <span className="text-xs text-muted-foreground">({p.code})</span>}
                                                                    </div>
                                                                ))}
                                                            </div>
                                                        </div>
                                                    )}
                                                </TabsContent>

                                                {/* Vitals Tab */}
                                                <TabsContent value="vitals">
                                                    {consultation.vitals ? (
                                                        <div className="grid grid-cols-2 gap-2 text-sm sm:grid-cols-4">
                                                            {consultation.vitals.blood_pressure && (
                                                                <div><span className="text-muted-foreground">BP:</span> <span className="font-medium">{consultation.vitals.blood_pressure}</span></div>
                                                            )}
                                                            {consultation.vitals.temperature && (
                                                                <div><span className="text-muted-foreground">Temp:</span> <span className="font-medium">{consultation.vitals.temperature}°C</span></div>
                                                            )}
                                                            {consultation.vitals.pulse_rate && (
                                                                <div><span className="text-muted-foreground">Pulse:</span> <span className="font-medium">{consultation.vitals.pulse_rate}</span></div>
                                                            )}
                                                            {consultation.vitals.respiratory_rate && (
                                                                <div><span className="text-muted-foreground">RR:</span> <span className="font-medium">{consultation.vitals.respiratory_rate}</span></div>
                                                            )}
                                                            {consultation.vitals.oxygen_saturation && (
                                                                <div><span className="text-muted-foreground">SpO2:</span> <span className="font-medium">{consultation.vitals.oxygen_saturation}%</span></div>
                                                            )}
                                                            {consultation.vitals.weight && (
                                                                <div><span className="text-muted-foreground">Weight:</span> <span className="font-medium">{consultation.vitals.weight}kg</span></div>
                                                            )}
                                                            {consultation.vitals.height && (
                                                                <div><span className="text-muted-foreground">Height:</span> <span className="font-medium">{consultation.vitals.height}cm</span></div>
                                                            )}
                                                            {consultation.vitals.bmi && (
                                                                <div><span className="text-muted-foreground">BMI:</span> <span className="font-medium">{consultation.vitals.bmi}</span></div>
                                                            )}
                                                        </div>
                                                    ) : (
                                                        <p className="text-sm text-muted-foreground py-4 text-center">No vitals recorded for this visit</p>
                                                    )}
                                                </TabsContent>

                                                {/* Prescriptions Tab */}
                                                <TabsContent value="prescriptions">
                                                    {consultation.prescriptions.length > 0 ? (
                                                        <div className="space-y-1">
                                                            {consultation.prescriptions.map((p, idx) => (
                                                                <div key={idx} className="flex items-center gap-2 text-sm">
                                                                    <Pill className="h-3.5 w-3.5 text-muted-foreground" />
                                                                    <span>
                                                                        {p.drug_name}{p.strength && ` ${p.strength}`}{p.dose_quantity && ` - ${p.dose_quantity}`}{p.frequency && ` ${p.frequency}`}{p.duration && ` x ${p.duration}`}
                                                                    </span>
                                                                    <Badge variant="outline" className="text-xs">{p.status}</Badge>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    ) : (
                                                        <p className="text-sm text-muted-foreground py-4 text-center">No prescriptions</p>
                                                    )}
                                                </TabsContent>

                                                {/* Labs Tab */}
                                                <TabsContent value="labs">
                                                    {consultation.lab_orders.length > 0 ? (
                                                        <div className="space-y-1">
                                                            {consultation.lab_orders.map((l, idx) => (
                                                                <div key={idx} className="flex items-center gap-2 text-sm">
                                                                    <Beaker className="h-3.5 w-3.5 text-muted-foreground" />
                                                                    <span>{l.service_name}</span>
                                                                    <Badge variant={getStatusBadgeVariant(l.status || '')} className="text-xs">{l.status}</Badge>
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
                        ))}
                    </div>
                ) : (
                    <EmptyState
                        icon={Stethoscope}
                        title="No Consultations"
                        description="No consultation records found"
                    />
                )}
            </TabsContent>


            {/* Minor Procedures Tab */}
            <TabsContent value="minor-procedures" className="space-y-4">
                {medicalHistory?.minor_procedures &&
                medicalHistory.minor_procedures.length > 0 ? (
                    medicalHistory.minor_procedures.map((mp) => (
                        <Card key={mp.id}>
                            <CardHeader className="pb-3">
                                <div className="flex items-start justify-between">
                                    <div>
                                        <CardTitle className="text-base">
                                            {mp.procedure_name || 'Minor Procedure'}
                                        </CardTitle>
                                        <CardDescription className="mt-1 flex items-center gap-2">
                                            <Calendar className="h-3.5 w-3.5" />
                                            {formatDateTime(mp.date)}
                                            {mp.nurse && (
                                                <span>• {mp.nurse}</span>
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
                                            {mp.vitals.blood_pressure && (
                                                <div>
                                                    <span className="text-muted-foreground">BP:</span>{' '}
                                                    <span className="font-medium">{mp.vitals.blood_pressure}</span>
                                                </div>
                                            )}
                                            {mp.vitals.temperature && (
                                                <div>
                                                    <span className="text-muted-foreground">Temp:</span>{' '}
                                                    <span className="font-medium">{mp.vitals.temperature}°C</span>
                                                </div>
                                            )}
                                            {mp.vitals.pulse_rate && (
                                                <div>
                                                    <span className="text-muted-foreground">Pulse:</span>{' '}
                                                    <span className="font-medium">{mp.vitals.pulse_rate}</span>
                                                </div>
                                            )}
                                            {mp.vitals.oxygen_saturation && (
                                                <div>
                                                    <span className="text-muted-foreground">SpO2:</span>{' '}
                                                    <span className="font-medium">{mp.vitals.oxygen_saturation}%</span>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                )}

                                {/* Diagnoses */}
                                {mp.diagnoses.length > 0 && (
                                    <div>
                                        <h4 className="mb-2 text-sm font-medium">Diagnoses</h4>
                                        <div className="flex flex-wrap gap-2">
                                            {mp.diagnoses.map((d, idx) => (
                                                <Badge key={idx} variant="secondary">
                                                    {d.code && `${d.code}: `}
                                                    {d.description}
                                                </Badge>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                {/* Procedure Notes */}
                                {mp.procedure_notes && (
                                    <div className="text-sm">
                                        <span className="text-muted-foreground font-medium">Notes:</span>
                                        <p className="mt-1">{mp.procedure_notes}</p>
                                    </div>
                                )}

                                {/* Supplies */}
                                {mp.supplies.length > 0 && (
                                    <div>
                                        <h4 className="mb-2 text-sm font-medium">Supplies</h4>
                                        <div className="space-y-1">
                                            {mp.supplies.map((s, idx) => (
                                                <div key={idx} className="flex items-center gap-2 text-sm">
                                                    <Pill className="text-muted-foreground h-3.5 w-3.5" />
                                                    <span>
                                                        {s.drug_name}
                                                        {s.quantity && ` x ${s.quantity}`}
                                                    </span>
                                                    <Badge
                                                        variant={s.status === 'dispensed' ? 'default' : 'secondary'}
                                                        className="text-xs"
                                                    >
                                                        {s.status}
                                                    </Badge>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    ))
                ) : (
                    <EmptyState
                        icon={Scissors}
                        title="No Minor Procedures"
                        description="No minor procedure records found"
                    />
                )}
            </TabsContent>

            {/* Theatre Procedures Tab */}
            <TabsContent value="theatre" className="space-y-4">
                {medicalHistory?.theatre_procedures &&
                medicalHistory.theatre_procedures.length > 0 ? (
                    <div className="space-y-4">
                        {medicalHistory.theatre_procedures.map((procedure) => (
                            <Card key={procedure.id}>
                                <CardHeader className="pb-3">
                                    <div className="flex items-start justify-between">
                                        <div>
                                            <CardTitle className="flex items-center gap-2 text-base">
                                                <Scissors className="h-4 w-4" />
                                                {procedure.procedure_name ||
                                                    'Procedure'}
                                                {procedure.procedure_code && (
                                                    <span className="text-sm font-normal text-muted-foreground">
                                                        (
                                                        {
                                                            procedure.procedure_code
                                                        }
                                                        )
                                                    </span>
                                                )}
                                            </CardTitle>
                                            <CardDescription className="mt-1 flex items-center gap-2">
                                                <Calendar className="h-3.5 w-3.5" />
                                                {formatDateTime(
                                                    procedure.performed_at,
                                                )}
                                                {procedure.doctor && (
                                                    <span>
                                                        • Dr. {procedure.doctor}
                                                    </span>
                                                )}
                                            </CardDescription>
                                        </div>
                                        {procedure.category && (
                                            <Badge variant="secondary">
                                                {procedure.category}
                                            </Badge>
                                        )}
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {/* Procedure Team */}
                                    <div className="grid gap-3 text-sm sm:grid-cols-3">
                                        {procedure.assistant && (
                                            <div>
                                                <span className="text-muted-foreground">
                                                    Assistant:
                                                </span>{' '}
                                                <span className="font-medium">
                                                    {procedure.assistant}
                                                </span>
                                            </div>
                                        )}
                                        {procedure.anaesthetist && (
                                            <div>
                                                <span className="text-muted-foreground">
                                                    Anaesthetist:
                                                </span>{' '}
                                                <span className="font-medium">
                                                    {procedure.anaesthetist}
                                                </span>
                                            </div>
                                        )}
                                        {procedure.anaesthesia_type && (
                                            <div>
                                                <span className="text-muted-foreground">
                                                    Anaesthesia:
                                                </span>{' '}
                                                <span className="font-medium capitalize">
                                                    {procedure.anaesthesia_type}
                                                </span>
                                            </div>
                                        )}
                                    </div>

                                    {/* Obstetric Info (if applicable) */}
                                    {(procedure.estimated_gestational_age ||
                                        procedure.parity ||
                                        procedure.procedure_subtype) && (
                                        <div className="rounded-lg bg-muted/50 p-3">
                                            <h4 className="mb-2 text-sm font-medium">
                                                Obstetric Details
                                            </h4>
                                            <div className="grid gap-2 text-sm sm:grid-cols-3">
                                                {procedure.procedure_subtype && (
                                                    <div>
                                                        <span className="text-muted-foreground">
                                                            Type:
                                                        </span>{' '}
                                                        <span className="font-medium">
                                                            {
                                                                procedure.procedure_subtype
                                                            }
                                                        </span>
                                                    </div>
                                                )}
                                                {procedure.estimated_gestational_age && (
                                                    <div>
                                                        <span className="text-muted-foreground">
                                                            Gestational Age:
                                                        </span>{' '}
                                                        <span className="font-medium">
                                                            {
                                                                procedure.estimated_gestational_age
                                                            }
                                                        </span>
                                                    </div>
                                                )}
                                                {procedure.parity && (
                                                    <div>
                                                        <span className="text-muted-foreground">
                                                            Parity:
                                                        </span>{' '}
                                                        <span className="font-medium">
                                                            {procedure.parity}
                                                        </span>
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    )}

                                    {/* Indication */}
                                    {procedure.indication && (
                                        <div className="text-sm">
                                            <span className="font-medium text-muted-foreground">
                                                Indication:
                                            </span>
                                            <p className="mt-1">
                                                {procedure.indication}
                                            </p>
                                        </div>
                                    )}

                                    {/* Procedure Steps */}
                                    {procedure.procedure_steps && (
                                        <div className="text-sm">
                                            <span className="font-medium text-muted-foreground">
                                                Procedure Steps:
                                            </span>
                                            <p className="mt-1 whitespace-pre-wrap">
                                                {procedure.procedure_steps}
                                            </p>
                                        </div>
                                    )}

                                    {/* Findings */}
                                    {procedure.findings && (
                                        <div className="text-sm">
                                            <span className="font-medium text-muted-foreground">
                                                Findings:
                                            </span>
                                            <p className="mt-1 whitespace-pre-wrap">
                                                {procedure.findings}
                                            </p>
                                        </div>
                                    )}

                                    {/* Plan */}
                                    {procedure.plan && (
                                        <div className="text-sm">
                                            <span className="font-medium text-muted-foreground">
                                                Plan:
                                            </span>
                                            <p className="mt-1 whitespace-pre-wrap">
                                                {procedure.plan}
                                            </p>
                                        </div>
                                    )}

                                    {/* Comments */}
                                    {procedure.comments && (
                                        <div className="text-sm">
                                            <span className="font-medium text-muted-foreground">
                                                Comments:
                                            </span>
                                            <p className="mt-1 whitespace-pre-wrap">
                                                {procedure.comments}
                                            </p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                ) : (
                    <EmptyState
                        icon={Scissors}
                        title="No Theatre Procedures"
                        description="No surgical/theatre procedure records found"
                    />
                )}
            </TabsContent>

            {/* Admissions Tab */}
            <TabsContent value="admissions" className="space-y-4">
                {medicalHistory?.admissions &&
                medicalHistory.admissions.length > 0 ? (
                    <div className="space-y-3">
                        {medicalHistory.admissions.map((admission) => (
                            <Collapsible key={admission.id}>
                                <div className="rounded-lg border">
                                    <CollapsibleTrigger className="flex w-full items-center justify-between p-4 text-left hover:bg-muted/50 transition-colors">
                                        <div className="flex items-center gap-3">
                                            <ChevronRight className="h-4 w-4 text-muted-foreground transition-transform [[data-state=open]_&]:rotate-90" />
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <span className="text-sm font-semibold">
                                                        {admission.admission_number}
                                                    </span>
                                                    <Badge
                                                        variant={getStatusBadgeVariant(admission.status)}
                                                        className="text-xs"
                                                    >
                                                        {admission.status}
                                                    </Badge>
                                                </div>
                                                <div className="mt-0.5 flex items-center gap-2 text-xs text-muted-foreground">
                                                    <Calendar className="h-3 w-3" />
                                                    {formatDateTime(admission.admitted_at)}
                                                    {admission.discharged_at && ` → ${formatDateTime(admission.discharged_at)}`}
                                                    {admission.ward && ` • ${admission.ward}`}
                                                    {admission.bed && ` (Bed ${admission.bed})`}
                                                    {admission.admitting_doctor && ` • Dr. ${admission.admitting_doctor}`}
                                                </div>
                                            </div>
                                        </div>
                                        {admission.ward_rounds && admission.ward_rounds.length > 0 && (
                                            <Badge variant="secondary" className="text-xs">
                                                {admission.ward_rounds.length} round{admission.ward_rounds.length !== 1 ? 's' : ''}
                                            </Badge>
                                        )}
                                    </CollapsibleTrigger>
                                    <CollapsibleContent>
                                        <div className="border-t p-4 space-y-4">
                                            {admission.admission_reason && (
                                                <div className="text-sm">
                                                    <span className="text-muted-foreground font-medium">Reason:</span>
                                                    <p className="mt-1">{admission.admission_reason}</p>
                                                </div>
                                            )}

                                            {admission.diagnoses.length > 0 && (
                                                <div>
                                                    <span className="text-sm text-muted-foreground font-medium">Diagnoses:</span>
                                                    <div className="mt-1 flex flex-wrap gap-2">
                                                        {admission.diagnoses.map((d, idx) => (
                                                            <Badge
                                                                key={idx}
                                                                variant={d.is_active ? 'default' : 'outline'}
                                                            >
                                                                {d.code && `${d.code}: `}
                                                                {d.description}
                                                            </Badge>
                                                        ))}
                                                    </div>
                                                </div>
                                            )}

                                            {admission.discharge_notes && (
                                                <div className="text-sm">
                                                    <span className="text-muted-foreground font-medium">Discharge Notes:</span>
                                                    <p className="mt-1">{admission.discharge_notes}</p>
                                                </div>
                                            )}

                                            {/* Inline Admission Detail (Ward Rounds, MAR, Vitals, Nursing Notes, Labs) */}
                                            <AdmissionDetailView
                                                patientId={patientId}
                                                admissionId={admission.id}
                                            />
                                        </div>
                                    </CollapsibleContent>
                                </div>
                            </Collapsible>
                        ))}
                    </div>
                ) : (
                    <EmptyState
                        icon={Hospital}
                        title="No Admissions"
                        description="No admission records found"
                    />
                )}
            </TabsContent>

            {/* Lab Results Modal */}
            <Dialog
                open={!!selectedLabResult}
                onOpenChange={() => setSelectedLabResult(null)}
            >
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            {selectedLabResult?.is_imaging ? (
                                <FileText className="h-5 w-5" />
                            ) : (
                                <Beaker className="h-5 w-5" />
                            )}
                            {selectedLabResult?.service_name}
                            {selectedLabResult?.code && (
                                <span className="text-sm font-normal text-muted-foreground">
                                    ({selectedLabResult.code})
                                </span>
                            )}
                        </DialogTitle>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div className="text-sm text-muted-foreground">
                            {selectedLabResult?.result_entered_at && (
                                <span>
                                    Results entered:{' '}
                                    {formatDateTime(
                                        selectedLabResult.result_entered_at,
                                    )}
                                </span>
                            )}
                        </div>

                        {selectedLabResult?.result_values &&
                        Object.keys(selectedLabResult.result_values).length >
                            0 ? (
                            <div className="space-y-2">
                                <h4 className="text-sm font-medium">Results</h4>
                                <div className="max-h-[60vh] overflow-y-auto pr-1">
                                <div className="grid gap-2 grid-cols-2 sm:grid-cols-3">
                                    {Object.entries(
                                        selectedLabResult.result_values,
                                    ).map(([key, result]) => {
                                        const isObject =
                                            typeof result === 'object' &&
                                            result !== null;
                                        const value = isObject
                                            ? (
                                                  result as Record<
                                                      string,
                                                      unknown
                                                  >
                                              ).value
                                            : result;
                                        const unit = isObject
                                            ? String(
                                                  (
                                                      result as Record<
                                                          string,
                                                          unknown
                                                      >
                                                  ).unit || '',
                                              )
                                            : '';

                                        let range: string = isObject
                                            ? String(
                                                  (
                                                      result as Record<
                                                          string,
                                                          unknown
                                                      >
                                                  ).range || '',
                                              )
                                            : '';
                                        let flag = isObject
                                            ? ((
                                                  result as Record<
                                                      string,
                                                      unknown
                                                  >
                                              ).flag as string)
                                            : 'normal';

                                        if (
                                            !range &&
                                            selectedLabResult.test_parameters
                                                ?.parameters
                                        ) {
                                            const param =
                                                selectedLabResult.test_parameters.parameters.find(
                                                    (p) =>
                                                        p.name === key ||
                                                        p.name.toLowerCase() ===
                                                            key.toLowerCase(),
                                                );
                                            if (param?.normal_range) {
                                                const { min, max } =
                                                    param.normal_range;
                                                if (
                                                    min !== undefined &&
                                                    max !== undefined
                                                ) {
                                                    range = `${min}-${max}`;
                                                } else if (min !== undefined) {
                                                    range = `>${min}`;
                                                } else if (max !== undefined) {
                                                    range = `<${max}`;
                                                }

                                                if (
                                                    flag === 'normal' &&
                                                    param.type === 'numeric'
                                                ) {
                                                    const numValue = parseFloat(
                                                        String(value),
                                                    );
                                                    if (!isNaN(numValue)) {
                                                        if (
                                                            min !== undefined &&
                                                            numValue < min
                                                        ) {
                                                            flag = 'low';
                                                        } else if (
                                                            max !== undefined &&
                                                            numValue > max
                                                        ) {
                                                            flag = 'high';
                                                        }
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
                                            <div
                                                key={key}
                                                className="rounded-lg border px-2.5 py-2"
                                            >
                                                <span className="text-xs text-muted-foreground capitalize">
                                                    {key.replace(/_/g, ' ')}
                                                </span>
                                                <p
                                                    className={`text-sm font-semibold leading-tight ${getFlagColor(flag)}`}
                                                >
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
                                                        <span
                                                            className={`text-xs font-medium ${getFlagColor(flag)}`}
                                                        >
                                                            ({flag.toUpperCase()})
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                                </div>
                            </div>
                        ) : null}

                        {selectedLabResult?.result_notes && (
                            <div>
                                <h4 className="mb-1 text-sm font-medium">
                                    Notes
                                </h4>
                                <p className="text-sm whitespace-pre-wrap text-muted-foreground">
                                    {selectedLabResult.result_notes}
                                </p>
                            </div>
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </Tabs>
    );
}

function EmptyState({
    icon: Icon,
    title,
    description,
}: {
    icon: React.ComponentType<{ className?: string }>;
    title: string;
    description: string;
}) {
    return (
        <Card>
            <CardContent className="flex flex-col items-center justify-center py-12">
                <div className="flex h-16 w-16 items-center justify-center rounded-full bg-muted">
                    <Icon className="h-8 w-8 text-muted-foreground/50" />
                </div>
                <p className="mt-4 text-sm font-medium">{title}</p>
                <p className="mt-1 text-sm text-muted-foreground">
                    {description}
                </p>
            </CardContent>
        </Card>
    );
}
