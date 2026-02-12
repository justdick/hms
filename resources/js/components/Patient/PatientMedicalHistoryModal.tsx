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
    ClipboardList,
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
                                <TabsList className="grid w-full grid-cols-4">
                                    <TabsTrigger
                                        value="background"
                                        className="gap-1.5 text-xs"
                                    >
                                        <FileText className="h-3.5 w-3.5" />
                                        <span className="hidden sm:inline">
                                            Background
                                        </span>
                                    </TabsTrigger>
                                    <TabsTrigger
                                        value="consultations"
                                        className="gap-1.5 text-xs"
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
                                        className="gap-1.5 text-xs"
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
                                        className="gap-1.5 text-xs"
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
                                        data.medical_history.consultations.map(
                                            (c) => (
                                                <Card key={c.id}>
                                                    <CardHeader className="pb-3">
                                                        <div className="flex items-start justify-between">
                                                            <div>
                                                                <CardTitle className="text-base">
                                                                    {c.department ||
                                                                        'Consultation'}
                                                                </CardTitle>
                                                                <CardDescription className="mt-1 flex items-center gap-2">
                                                                    <Calendar className="h-3.5 w-3.5" />
                                                                    {formatDateTime(
                                                                        c.date,
                                                                    )}
                                                                    {c.doctor && (
                                                                        <span>
                                                                            •
                                                                            Dr.{' '}
                                                                            {
                                                                                c.doctor
                                                                            }
                                                                        </span>
                                                                    )}
                                                                </CardDescription>
                                                            </div>
                                                        </div>
                                                    </CardHeader>
                                                    <CardContent className="space-y-4">
                                                        {/* Vitals */}
                                                        {c.vitals && (
                                                            <div className="rounded-lg bg-muted/50 p-3">
                                                                <h4 className="mb-2 flex items-center gap-2 text-sm font-medium">
                                                                    <Thermometer className="h-3.5 w-3.5" />
                                                                    Vitals
                                                                </h4>
                                                                <div className="grid grid-cols-2 gap-2 text-sm sm:grid-cols-4">
                                                                    {c.vitals
                                                                        .blood_pressure && (
                                                                        <div>
                                                                            <span className="text-muted-foreground">
                                                                                BP:
                                                                            </span>{' '}
                                                                            <span className="font-medium">
                                                                                {
                                                                                    c
                                                                                        .vitals
                                                                                        .blood_pressure
                                                                                }
                                                                            </span>
                                                                        </div>
                                                                    )}
                                                                    {c.vitals
                                                                        .temperature && (
                                                                        <div>
                                                                            <span className="text-muted-foreground">
                                                                                Temp:
                                                                            </span>{' '}
                                                                            <span className="font-medium">
                                                                                {
                                                                                    c
                                                                                        .vitals
                                                                                        .temperature
                                                                                }
                                                                                °C
                                                                            </span>
                                                                        </div>
                                                                    )}
                                                                    {c.vitals
                                                                        .pulse_rate && (
                                                                        <div>
                                                                            <span className="text-muted-foreground">
                                                                                Pulse:
                                                                            </span>{' '}
                                                                            <span className="font-medium">
                                                                                {
                                                                                    c
                                                                                        .vitals
                                                                                        .pulse_rate
                                                                                }
                                                                            </span>
                                                                        </div>
                                                                    )}
                                                                    {c.vitals
                                                                        .oxygen_saturation && (
                                                                        <div>
                                                                            <span className="text-muted-foreground">
                                                                                SpO2:
                                                                            </span>{' '}
                                                                            <span className="font-medium">
                                                                                {
                                                                                    c
                                                                                        .vitals
                                                                                        .oxygen_saturation
                                                                                }
                                                                                %
                                                                            </span>
                                                                        </div>
                                                                    )}
                                                                </div>
                                                            </div>
                                                        )}

                                                        {/* Diagnoses */}
                                                        {c.diagnoses.length >
                                                            0 && (
                                                            <div>
                                                                <h4 className="mb-2 text-sm font-medium">
                                                                    Diagnoses
                                                                </h4>
                                                                <div className="flex flex-wrap gap-2">
                                                                    {c.diagnoses.map(
                                                                        (
                                                                            d,
                                                                            idx,
                                                                        ) => (
                                                                            <Badge
                                                                                key={
                                                                                    idx
                                                                                }
                                                                                variant={
                                                                                    d.type ===
                                                                                    'principal'
                                                                                        ? 'default'
                                                                                        : 'secondary'
                                                                                }
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

                                                        {/* SOAP Notes */}
                                                        <div className="grid gap-3 text-sm">
                                                            {c.presenting_complaint && (
                                                                <div>
                                                                    <span className="text-muted-foreground font-medium">
                                                                        Presenting
                                                                        Complaint:
                                                                    </span>
                                                                    <p className="mt-1">
                                                                        {
                                                                            c.presenting_complaint
                                                                        }
                                                                    </p>
                                                                </div>
                                                            )}
                                                            {c.examination_findings && (
                                                                <div>
                                                                    <span className="text-muted-foreground font-medium">
                                                                        Examination:
                                                                    </span>
                                                                    <p className="mt-1">
                                                                        {
                                                                            c.examination_findings
                                                                        }
                                                                    </p>
                                                                </div>
                                                            )}
                                                            {c.assessment_notes && (
                                                                <div>
                                                                    <span className="text-muted-foreground font-medium">
                                                                        Assessment:
                                                                    </span>
                                                                    <p className="mt-1">
                                                                        {
                                                                            c.assessment_notes
                                                                        }
                                                                    </p>
                                                                </div>
                                                            )}
                                                            {c.plan_notes && (
                                                                <div>
                                                                    <span className="text-muted-foreground font-medium">
                                                                        Plan:
                                                                    </span>
                                                                    <p className="mt-1">
                                                                        {
                                                                            c.plan_notes
                                                                        }
                                                                    </p>
                                                                </div>
                                                            )}
                                                        </div>

                                                        {/* Prescriptions */}
                                                        {c.prescriptions
                                                            .length > 0 && (
                                                            <div>
                                                                <h4 className="mb-2 text-sm font-medium">
                                                                    Prescriptions
                                                                </h4>
                                                                <div className="space-y-1">
                                                                    {c.prescriptions.map(
                                                                        (
                                                                            p,
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
                                                                                        p.drug_name
                                                                                    }
                                                                                    {p.strength &&
                                                                                        ` ${p.strength}`}
                                                                                    {p.dose_quantity &&
                                                                                        ` - ${p.dose_quantity}`}
                                                                                    {p.frequency &&
                                                                                        ` ${p.frequency}`}
                                                                                    {p.duration &&
                                                                                        ` x ${p.duration}`}
                                                                                </span>
                                                                            </div>
                                                                        ),
                                                                    )}
                                                                </div>
                                                            </div>
                                                        )}

                                                        {/* Lab Orders */}
                                                        {c.lab_orders.length >
                                                            0 && (
                                                            <div>
                                                                <h4 className="mb-2 text-sm font-medium">
                                                                    Lab /
                                                                    Imaging
                                                                    Orders
                                                                </h4>
                                                                <div className="space-y-1">
                                                                    {c.lab_orders.map(
                                                                        (
                                                                            l,
                                                                            idx,
                                                                        ) => (
                                                                            <div
                                                                                key={
                                                                                    idx
                                                                                }
                                                                                className="flex items-center gap-2 text-sm"
                                                                            >
                                                                                <Beaker className="text-muted-foreground h-3.5 w-3.5" />
                                                                                <span>
                                                                                    {
                                                                                        l.service_name
                                                                                    }
                                                                                </span>
                                                                                <Badge
                                                                                    variant={
                                                                                        l.status ===
                                                                                        'completed'
                                                                                            ? 'default'
                                                                                            : 'secondary'
                                                                                    }
                                                                                    className="text-xs"
                                                                                >
                                                                                    {
                                                                                        l.status
                                                                                    }
                                                                                </Badge>
                                                                            </div>
                                                                        ),
                                                                    )}
                                                                </div>
                                                            </div>
                                                        )}

                                                        {/* Procedures */}
                                                        {c.procedures.length >
                                                            0 && (
                                                            <div>
                                                                <h4 className="mb-2 text-sm font-medium">
                                                                    Procedures
                                                                </h4>
                                                                <div className="space-y-1">
                                                                    {c.procedures.map(
                                                                        (
                                                                            p,
                                                                            idx,
                                                                        ) => (
                                                                            <div
                                                                                key={
                                                                                    idx
                                                                                }
                                                                                className="flex items-center gap-2 text-sm"
                                                                            >
                                                                                <Scissors className="text-muted-foreground h-3.5 w-3.5" />
                                                                                <span>
                                                                                    {
                                                                                        p.name
                                                                                    }
                                                                                </span>
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
                                        data.medical_history.admissions.map(
                                            (a) => (
                                                <Card key={a.id}>
                                                    <CardHeader className="pb-3">
                                                        <CardTitle className="text-base">
                                                            {a.ward || 'Ward'}{' '}
                                                            {a.bed &&
                                                                `- Bed ${a.bed}`}
                                                        </CardTitle>
                                                        <CardDescription className="flex items-center gap-2">
                                                            <Calendar className="h-3.5 w-3.5" />
                                                            {formatDateTime(
                                                                a.admitted_at,
                                                            )}
                                                            {a.discharged_at &&
                                                                ` → ${formatDateTime(a.discharged_at)}`}
                                                            {a.admitting_doctor && (
                                                                <span>
                                                                    • Dr.{' '}
                                                                    {
                                                                        a.admitting_doctor
                                                                    }
                                                                </span>
                                                            )}
                                                        </CardDescription>
                                                    </CardHeader>
                                                    <CardContent className="space-y-3">
                                                        <Badge
                                                            variant={
                                                                a.status ===
                                                                'discharged'
                                                                    ? 'outline'
                                                                    : 'secondary'
                                                            }
                                                        >
                                                            {a.status}
                                                        </Badge>
                                                        {a.admission_reason && (
                                                            <div className="text-sm">
                                                                <span className="text-muted-foreground font-medium">
                                                                    Reason:
                                                                </span>
                                                                <p className="mt-1">
                                                                    {
                                                                        a.admission_reason
                                                                    }
                                                                </p>
                                                            </div>
                                                        )}
                                                        {a.diagnoses.length >
                                                            0 && (
                                                            <div className="flex flex-wrap gap-2">
                                                                {a.diagnoses.map(
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
                                                        )}

                                                        {/* Ward Rounds */}
                                                        {a.ward_rounds &&
                                                            a.ward_rounds
                                                                .length >
                                                                0 && (
                                                                <div className="mt-4 space-y-3">
                                                                    <h4 className="flex items-center gap-2 text-sm font-medium">
                                                                        <Stethoscope className="h-3.5 w-3.5" />
                                                                        Ward
                                                                        Rounds (
                                                                        {
                                                                            a
                                                                                .ward_rounds
                                                                                .length
                                                                        }
                                                                        )
                                                                    </h4>
                                                                    {a.ward_rounds.map(
                                                                        (
                                                                            wr,
                                                                        ) => (
                                                                            <div
                                                                                key={
                                                                                    wr.id
                                                                                }
                                                                                className="rounded-lg border border-gray-200 p-3 dark:border-gray-700"
                                                                            >
                                                                                <div className="mb-2 flex items-center justify-between">
                                                                                    <span className="text-sm font-medium">
                                                                                        Day{' '}
                                                                                        {wr.day_number ||
                                                                                            '—'}
                                                                                        {wr.round_type &&
                                                                                            ` (${wr.round_type})`}
                                                                                    </span>
                                                                                    <span className="text-muted-foreground text-xs">
                                                                                        {formatDateTime(
                                                                                            wr.date,
                                                                                        )}
                                                                                        {wr.doctor &&
                                                                                            ` • Dr. ${wr.doctor}`}
                                                                                    </span>
                                                                                </div>

                                                                                {/* SOAP notes */}
                                                                                <div className="grid gap-2 text-sm">
                                                                                    {wr.presenting_complaint && (
                                                                                        <div>
                                                                                            <span className="text-muted-foreground font-medium">
                                                                                                S:
                                                                                            </span>{' '}
                                                                                            {
                                                                                                wr.presenting_complaint
                                                                                            }
                                                                                        </div>
                                                                                    )}
                                                                                    {wr.examination_findings && (
                                                                                        <div>
                                                                                            <span className="text-muted-foreground font-medium">
                                                                                                O:
                                                                                            </span>{' '}
                                                                                            {
                                                                                                wr.examination_findings
                                                                                            }
                                                                                        </div>
                                                                                    )}
                                                                                    {wr.assessment_notes && (
                                                                                        <div>
                                                                                            <span className="text-muted-foreground font-medium">
                                                                                                A:
                                                                                            </span>{' '}
                                                                                            {
                                                                                                wr.assessment_notes
                                                                                            }
                                                                                        </div>
                                                                                    )}
                                                                                    {wr.plan_notes && (
                                                                                        <div>
                                                                                            <span className="text-muted-foreground font-medium">
                                                                                                P:
                                                                                            </span>{' '}
                                                                                            {
                                                                                                wr.plan_notes
                                                                                            }
                                                                                        </div>
                                                                                    )}
                                                                                </div>

                                                                                {/* Prescriptions */}
                                                                                {wr
                                                                                    .prescriptions
                                                                                    .length >
                                                                                    0 && (
                                                                                    <div className="mt-2">
                                                                                        <h5 className="mb-1 text-xs font-medium text-gray-500">
                                                                                            Prescriptions
                                                                                        </h5>
                                                                                        <div className="space-y-0.5">
                                                                                            {wr.prescriptions.map(
                                                                                                (
                                                                                                    p,
                                                                                                    idx,
                                                                                                ) => (
                                                                                                    <div
                                                                                                        key={
                                                                                                            idx
                                                                                                        }
                                                                                                        className="flex items-center gap-2 text-sm"
                                                                                                    >
                                                                                                        <Pill className="text-muted-foreground h-3 w-3" />
                                                                                                        <span>
                                                                                                            {
                                                                                                                p.drug_name
                                                                                                            }
                                                                                                            {p.strength &&
                                                                                                                ` ${p.strength}`}
                                                                                                            {p.dose_quantity &&
                                                                                                                ` - ${p.dose_quantity}`}
                                                                                                            {p.frequency &&
                                                                                                                ` ${p.frequency}`}
                                                                                                            {p.duration &&
                                                                                                                ` x ${p.duration}`}
                                                                                                        </span>
                                                                                                    </div>
                                                                                                ),
                                                                                            )}
                                                                                        </div>
                                                                                    </div>
                                                                                )}

                                                                                {/* Lab Orders */}
                                                                                {wr
                                                                                    .lab_orders
                                                                                    .length >
                                                                                    0 && (
                                                                                    <div className="mt-2">
                                                                                        <h5 className="mb-1 text-xs font-medium text-gray-500">
                                                                                            Lab
                                                                                            /
                                                                                            Imaging
                                                                                        </h5>
                                                                                        <div className="space-y-0.5">
                                                                                            {wr.lab_orders.map(
                                                                                                (
                                                                                                    l,
                                                                                                    idx,
                                                                                                ) => (
                                                                                                    <div
                                                                                                        key={
                                                                                                            idx
                                                                                                        }
                                                                                                        className="flex items-center gap-2 text-sm"
                                                                                                    >
                                                                                                        <Beaker className="text-muted-foreground h-3 w-3" />
                                                                                                        <span>
                                                                                                            {
                                                                                                                l.service_name
                                                                                                            }
                                                                                                        </span>
                                                                                                        <Badge
                                                                                                            variant={
                                                                                                                l.status ===
                                                                                                                'completed'
                                                                                                                    ? 'default'
                                                                                                                    : 'secondary'
                                                                                                            }
                                                                                                            className="text-xs"
                                                                                                        >
                                                                                                            {
                                                                                                                l.status
                                                                                                            }
                                                                                                        </Badge>
                                                                                                    </div>
                                                                                                ),
                                                                                            )}
                                                                                        </div>
                                                                                    </div>
                                                                                )}

                                                                                {/* Procedures */}
                                                                                {wr
                                                                                    .procedures
                                                                                    .length >
                                                                                    0 && (
                                                                                    <div className="mt-2">
                                                                                        <h5 className="mb-1 text-xs font-medium text-gray-500">
                                                                                            Procedures
                                                                                        </h5>
                                                                                        <div className="space-y-0.5">
                                                                                            {wr.procedures.map(
                                                                                                (
                                                                                                    p,
                                                                                                    idx,
                                                                                                ) => (
                                                                                                    <div
                                                                                                        key={
                                                                                                            idx
                                                                                                        }
                                                                                                        className="flex items-center gap-2 text-sm"
                                                                                                    >
                                                                                                        <Scissors className="text-muted-foreground h-3 w-3" />
                                                                                                        <span>
                                                                                                            {
                                                                                                                p.name
                                                                                                            }
                                                                                                        </span>
                                                                                                    </div>
                                                                                                ),
                                                                                            )}
                                                                                        </div>
                                                                                    </div>
                                                                                )}
                                                                            </div>
                                                                        ),
                                                                    )}
                                                                </div>
                                                            )}
                                                    </CardContent>
                                                </Card>
                                            ),
                                        )
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
            </DialogContent>
        </Dialog>
    );
}