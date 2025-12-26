import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Activity,
    Beaker,
    Calendar,
    ClipboardList,
    FileText,
    Heart,
    Hospital,
    Pill,
    Stethoscope,
    Users,
} from 'lucide-react';

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
    status?: string;
    ordered_by?: string | null;
    ordered_at?: string | null;
    result_entered_at: string | null;
    result_values: Record<string, unknown> | null;
    result_notes: string | null;
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
    examination_findings: string | null;
    assessment_notes: string | null;
    plan_notes: string | null;
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
}

interface BackgroundHistory {
    past_medical_surgical_history: string | null;
    drug_history: string | null;
    family_history: string | null;
    social_history: string | null;
}

interface MedicalHistory {
    consultations: Consultation[];
    vitals: VitalSign[];
    admissions: Admission[];
    prescriptions: Prescription[];
    lab_results: LabOrder[];
}

interface Props {
    backgroundHistory: BackgroundHistory;
    medicalHistory: MedicalHistory | null;
}

export default function MedicalHistoryTab({
    backgroundHistory,
    medicalHistory,
}: Props) {
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
        <Tabs defaultValue="background" className="space-y-4">
            <TabsList className="grid w-full grid-cols-6">
                <TabsTrigger value="background" className="gap-1.5 text-xs">
                    <FileText className="h-3.5 w-3.5" />
                    <span className="hidden sm:inline">Background</span>
                </TabsTrigger>
                <TabsTrigger value="consultations" className="gap-1.5 text-xs">
                    <Stethoscope className="h-3.5 w-3.5" />
                    <span className="hidden sm:inline">Consultations</span>
                </TabsTrigger>
                <TabsTrigger value="vitals" className="gap-1.5 text-xs">
                    <Activity className="h-3.5 w-3.5" />
                    <span className="hidden sm:inline">Vitals</span>
                </TabsTrigger>
                <TabsTrigger value="prescriptions" className="gap-1.5 text-xs">
                    <Pill className="h-3.5 w-3.5" />
                    <span className="hidden sm:inline">Prescriptions</span>
                </TabsTrigger>
                <TabsTrigger value="labs" className="gap-1.5 text-xs">
                    <Beaker className="h-3.5 w-3.5" />
                    <span className="hidden sm:inline">Lab Results</span>
                </TabsTrigger>
                <TabsTrigger value="admissions" className="gap-1.5 text-xs">
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
                                        {backgroundHistory.past_medical_surgical_history}
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
                    <div className="space-y-4">
                        {medicalHistory.consultations.map((consultation) => (
                            <Card key={consultation.id}>
                                <CardHeader className="pb-3">
                                    <div className="flex items-start justify-between">
                                        <div>
                                            <CardTitle className="text-base">
                                                {consultation.department || 'Consultation'}
                                            </CardTitle>
                                            <CardDescription className="flex items-center gap-2 mt-1">
                                                <Calendar className="h-3.5 w-3.5" />
                                                {formatDateTime(consultation.date)}
                                                {consultation.doctor && (
                                                    <span>• Dr. {consultation.doctor}</span>
                                                )}
                                            </CardDescription>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {/* Diagnoses */}
                                    {consultation.diagnoses.length > 0 && (
                                        <div>
                                            <h4 className="text-sm font-medium mb-2">Diagnoses</h4>
                                            <div className="flex flex-wrap gap-2">
                                                {consultation.diagnoses.map((d, idx) => (
                                                    <Badge
                                                        key={idx}
                                                        variant={d.type === 'principal' ? 'default' : 'secondary'}
                                                    >
                                                        {d.code && `${d.code}: `}
                                                        {d.description}
                                                    </Badge>
                                                ))}
                                            </div>
                                        </div>
                                    )}

                                    {/* SOAP Notes */}
                                    <div className="grid gap-3 text-sm">
                                        {consultation.presenting_complaint && (
                                            <div>
                                                <span className="font-medium text-muted-foreground">
                                                    Presenting Complaint:
                                                </span>
                                                <p className="mt-1">{consultation.presenting_complaint}</p>
                                            </div>
                                        )}
                                        {consultation.examination_findings && (
                                            <div>
                                                <span className="font-medium text-muted-foreground">
                                                    Examination:
                                                </span>
                                                <p className="mt-1">{consultation.examination_findings}</p>
                                            </div>
                                        )}
                                        {consultation.assessment_notes && (
                                            <div>
                                                <span className="font-medium text-muted-foreground">
                                                    Assessment:
                                                </span>
                                                <p className="mt-1">{consultation.assessment_notes}</p>
                                            </div>
                                        )}
                                        {consultation.plan_notes && (
                                            <div>
                                                <span className="font-medium text-muted-foreground">
                                                    Plan:
                                                </span>
                                                <p className="mt-1">{consultation.plan_notes}</p>
                                            </div>
                                        )}
                                    </div>

                                    {/* Prescriptions in this consultation */}
                                    {consultation.prescriptions.length > 0 && (
                                        <div>
                                            <h4 className="text-sm font-medium mb-2">Prescriptions</h4>
                                            <div className="space-y-1">
                                                {consultation.prescriptions.map((p, idx) => (
                                                    <div
                                                        key={idx}
                                                        className="text-sm flex items-center gap-2"
                                                    >
                                                        <Pill className="h-3.5 w-3.5 text-muted-foreground" />
                                                        <span>
                                                            {p.drug_name}
                                                            {p.strength && ` ${p.strength}`}
                                                            {p.dose_quantity && ` - ${p.dose_quantity}`}
                                                            {p.frequency && ` ${p.frequency}`}
                                                            {p.duration && ` x ${p.duration}`}
                                                        </span>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}

                                    {/* Lab orders in this consultation */}
                                    {consultation.lab_orders.length > 0 && (
                                        <div>
                                            <h4 className="text-sm font-medium mb-2">
                                                Lab / Imaging Orders
                                            </h4>
                                            <div className="space-y-1">
                                                {consultation.lab_orders.map((l, idx) => (
                                                    <div
                                                        key={idx}
                                                        className="text-sm flex items-center gap-2"
                                                    >
                                                        <Beaker className="h-3.5 w-3.5 text-muted-foreground" />
                                                        <span>{l.service_name}</span>
                                                        <Badge
                                                            variant={getStatusBadgeVariant(l.status || '')}
                                                            className="text-xs"
                                                        >
                                                            {l.status}
                                                        </Badge>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
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

            {/* Vitals Tab */}
            <TabsContent value="vitals" className="space-y-4">
                {medicalHistory?.vitals && medicalHistory.vitals.length > 0 ? (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Activity className="h-4 w-4" />
                                Vital Signs History
                            </CardTitle>
                            <CardDescription>
                                {medicalHistory.vitals.length} records
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b">
                                            <th className="text-left py-2 px-2 font-medium">Date</th>
                                            <th className="text-left py-2 px-2 font-medium">BP</th>
                                            <th className="text-left py-2 px-2 font-medium">Temp</th>
                                            <th className="text-left py-2 px-2 font-medium">Pulse</th>
                                            <th className="text-left py-2 px-2 font-medium">RR</th>
                                            <th className="text-left py-2 px-2 font-medium">SpO2</th>
                                            <th className="text-left py-2 px-2 font-medium">Weight</th>
                                            <th className="text-left py-2 px-2 font-medium">BMI</th>
                                            <th className="text-left py-2 px-2 font-medium">By</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {medicalHistory.vitals.map((v) => (
                                            <tr key={v.id} className="border-b last:border-0">
                                                <td className="py-2 px-2 whitespace-nowrap">
                                                    {formatDate(v.recorded_at)}
                                                </td>
                                                <td className="py-2 px-2">{v.blood_pressure}</td>
                                                <td className="py-2 px-2">
                                                    {v.temperature ? `${v.temperature}°C` : '-'}
                                                </td>
                                                <td className="py-2 px-2">
                                                    {v.pulse_rate ?? '-'}
                                                </td>
                                                <td className="py-2 px-2">
                                                    {v.respiratory_rate ?? '-'}
                                                </td>
                                                <td className="py-2 px-2">
                                                    {v.oxygen_saturation ? `${v.oxygen_saturation}%` : '-'}
                                                </td>
                                                <td className="py-2 px-2">
                                                    {v.weight ? `${v.weight}kg` : '-'}
                                                </td>
                                                <td className="py-2 px-2">{v.bmi ?? '-'}</td>
                                                <td className="py-2 px-2 text-muted-foreground">
                                                    {v.recorded_by}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    <EmptyState
                        icon={Activity}
                        title="No Vitals Recorded"
                        description="No vital signs have been recorded"
                    />
                )}
            </TabsContent>

            {/* Prescriptions Tab */}
            <TabsContent value="prescriptions" className="space-y-4">
                {medicalHistory?.prescriptions &&
                medicalHistory.prescriptions.length > 0 ? (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Pill className="h-4 w-4" />
                                Prescription History
                            </CardTitle>
                            <CardDescription>
                                {medicalHistory.prescriptions.length} prescriptions
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b">
                                            <th className="text-left py-2 px-2 font-medium">Date</th>
                                            <th className="text-left py-2 px-2 font-medium">Medication</th>
                                            <th className="text-left py-2 px-2 font-medium">Dosage</th>
                                            <th className="text-left py-2 px-2 font-medium">Frequency</th>
                                            <th className="text-left py-2 px-2 font-medium">Duration</th>
                                            <th className="text-left py-2 px-2 font-medium">Qty</th>
                                            <th className="text-left py-2 px-2 font-medium">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {medicalHistory.prescriptions.map((p) => (
                                            <tr key={p.id} className="border-b last:border-0">
                                                <td className="py-2 px-2 whitespace-nowrap">
                                                    {formatDate(p.date || null)}
                                                </td>
                                                <td className="py-2 px-2">
                                                    <div>
                                                        <span className="font-medium">{p.drug_name}</span>
                                                        {p.generic_name && (
                                                            <span className="text-muted-foreground text-xs block">
                                                                {p.generic_name}
                                                            </span>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="py-2 px-2">
                                                    {p.dose_quantity}
                                                    {p.form && ` ${p.form}`}
                                                    {p.strength && ` (${p.strength})`}
                                                </td>
                                                <td className="py-2 px-2">{p.frequency || '-'}</td>
                                                <td className="py-2 px-2">{p.duration || '-'}</td>
                                                <td className="py-2 px-2">{p.quantity || '-'}</td>
                                                <td className="py-2 px-2">
                                                    <Badge variant={getStatusBadgeVariant(p.status)}>
                                                        {p.status}
                                                    </Badge>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    <EmptyState
                        icon={Pill}
                        title="No Prescriptions"
                        description="No prescription records found"
                    />
                )}
            </TabsContent>

            {/* Lab Results Tab */}
            <TabsContent value="labs" className="space-y-4">
                {medicalHistory?.lab_results &&
                medicalHistory.lab_results.length > 0 ? (
                    <div className="space-y-4">
                        {medicalHistory.lab_results.map((lab) => (
                            <Card key={lab.id}>
                                <CardHeader className="pb-3">
                                    <div className="flex items-start justify-between">
                                        <div>
                                            <CardTitle className="text-base flex items-center gap-2">
                                                {lab.is_imaging ? (
                                                    <FileText className="h-4 w-4" />
                                                ) : (
                                                    <Beaker className="h-4 w-4" />
                                                )}
                                                {lab.service_name}
                                                {lab.code && (
                                                    <span className="text-muted-foreground text-sm font-normal">
                                                        ({lab.code})
                                                    </span>
                                                )}
                                            </CardTitle>
                                            <CardDescription className="mt-1">
                                                {formatDateTime(lab.result_entered_at)}
                                                {lab.ordered_by && ` • Ordered by ${lab.ordered_by}`}
                                            </CardDescription>
                                        </div>
                                        <Badge variant={lab.is_imaging ? 'secondary' : 'default'}>
                                            {lab.is_imaging ? 'Imaging' : 'Laboratory'}
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    {lab.result_values &&
                                    Object.keys(lab.result_values).length > 0 ? (
                                        <div className="space-y-2">
                                            <h4 className="text-sm font-medium">Results</h4>
                                            <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                                {Object.entries(lab.result_values).map(
                                                    ([key, value]) => (
                                                        <div
                                                            key={key}
                                                            className="rounded-lg border p-2"
                                                        >
                                                            <span className="text-xs text-muted-foreground">
                                                                {key}
                                                            </span>
                                                            <p className="font-medium">
                                                                {String(value)}
                                                            </p>
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                        </div>
                                    ) : null}
                                    {lab.result_notes && (
                                        <div className="mt-3">
                                            <h4 className="text-sm font-medium mb-1">Notes</h4>
                                            <p className="text-sm text-muted-foreground whitespace-pre-wrap">
                                                {lab.result_notes}
                                            </p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                ) : (
                    <EmptyState
                        icon={Beaker}
                        title="No Lab Results"
                        description="No laboratory or imaging results found"
                    />
                )}
            </TabsContent>

            {/* Admissions Tab */}
            <TabsContent value="admissions" className="space-y-4">
                {medicalHistory?.admissions &&
                medicalHistory.admissions.length > 0 ? (
                    <div className="space-y-4">
                        {medicalHistory.admissions.map((admission) => (
                            <Card key={admission.id}>
                                <CardHeader className="pb-3">
                                    <div className="flex items-start justify-between">
                                        <div>
                                            <CardTitle className="text-base">
                                                {admission.admission_number}
                                            </CardTitle>
                                            <CardDescription className="mt-1">
                                                {formatDateTime(admission.admitted_at)}
                                                {admission.discharged_at &&
                                                    ` - ${formatDateTime(admission.discharged_at)}`}
                                            </CardDescription>
                                        </div>
                                        <Badge variant={getStatusBadgeVariant(admission.status)}>
                                            {admission.status}
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <div className="grid gap-3 sm:grid-cols-2 text-sm">
                                        {admission.ward && (
                                            <div>
                                                <span className="text-muted-foreground">Ward:</span>{' '}
                                                <span className="font-medium">{admission.ward}</span>
                                                {admission.bed && ` (Bed ${admission.bed})`}
                                            </div>
                                        )}
                                        {admission.admitting_doctor && (
                                            <div>
                                                <span className="text-muted-foreground">
                                                    Admitting Doctor:
                                                </span>{' '}
                                                <span className="font-medium">
                                                    Dr. {admission.admitting_doctor}
                                                </span>
                                            </div>
                                        )}
                                    </div>

                                    {admission.admission_reason && (
                                        <div className="text-sm">
                                            <span className="text-muted-foreground">Reason:</span>
                                            <p className="mt-1">{admission.admission_reason}</p>
                                        </div>
                                    )}

                                    {admission.diagnoses.length > 0 && (
                                        <div>
                                            <span className="text-sm text-muted-foreground">
                                                Diagnoses:
                                            </span>
                                            <div className="flex flex-wrap gap-2 mt-1">
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
                                            <span className="text-muted-foreground">
                                                Discharge Notes:
                                            </span>
                                            <p className="mt-1">{admission.discharge_notes}</p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
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
                <p className="mt-1 text-sm text-muted-foreground">{description}</p>
            </CardContent>
        </Card>
    );
}
