import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Activity,
    Building,
    Calendar,
    ClipboardList,
    FileText,
    Microscope,
    Pill,
    TestTube,
    User,
} from 'lucide-react';
import { PreviousLabResultCard } from './PreviousLabResultCard';

interface VitalSigns {
    id: number;
    temperature: number | null;
    blood_pressure_systolic: number | null;
    blood_pressure_diastolic: number | null;
    pulse_rate: number | null;
    respiratory_rate: number | null;
    recorded_at: string;
}

interface Diagnosis {
    id: number;
    icd_code: string;
    diagnosis_description: string;
    is_primary: boolean;
}

interface Prescription {
    id: number;
    medication_name: string;
    dosage: string;
    frequency: string;
    duration: string;
    instructions?: string;
    status: string;
}

interface LabOrder {
    id: number;
    lab_service: {
        id: number;
        name: string;
        code: string;
        category: string;
        price: number | null;
        sample_type: string;
    };
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
}

interface PreviousConsultation {
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
    diagnoses: Diagnosis[];
    prescriptions: Prescription[];
    lab_orders?: LabOrder[];
}

interface Props {
    visit: PreviousConsultation;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

export function PreviousVisitModal({ visit, open, onOpenChange }: Props) {
    const formatDateTime = (dateString: string) => {
        return new Date(dateString).toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const latestVitals = visit.patient_checkin.vital_signs?.[0];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] max-w-4xl dark:border-gray-800 dark:bg-gray-950">
                <DialogHeader>
                    <DialogTitle className="text-xl dark:text-gray-100">
                        Previous Consultation Details
                    </DialogTitle>
                    <DialogDescription className="dark:text-gray-400">
                        Consultation from {formatDateTime(visit.started_at)}
                    </DialogDescription>
                </DialogHeader>

                {/* Visit Info Header */}
                <div className="grid grid-cols-3 gap-4 border-y py-4 dark:border-gray-800">
                    <div className="flex items-center gap-2">
                        <div className="rounded-lg bg-blue-100 p-2 dark:bg-blue-950">
                            <User className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div>
                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                Physician
                            </p>
                            <p className="text-sm font-medium dark:text-gray-200">
                                Dr. {visit.doctor.name}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <div className="rounded-lg bg-green-100 p-2 dark:bg-green-950">
                            <Building className="h-4 w-4 text-green-600 dark:text-green-400" />
                        </div>
                        <div>
                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                Department
                            </p>
                            <p className="text-sm font-medium dark:text-gray-200">
                                {visit.patient_checkin.department.name}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <div className="rounded-lg bg-purple-100 p-2 dark:bg-purple-950">
                            <Calendar className="h-4 w-4 text-purple-600 dark:text-purple-400" />
                        </div>
                        <div>
                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                Status
                            </p>
                            <Badge
                                variant={
                                    visit.status === 'completed'
                                        ? 'outline'
                                        : 'default'
                                }
                                className="dark:bg-gray-800 dark:text-gray-300"
                            >
                                {visit.status.toUpperCase()}
                            </Badge>
                        </div>
                    </div>
                </div>

                <ScrollArea className="max-h-[calc(90vh-280px)]">
                    <Tabs defaultValue="notes" className="w-full">
                        <TabsList className="grid w-full grid-cols-5 dark:bg-gray-900">
                            <TabsTrigger
                                value="notes"
                                className="dark:data-[state=active]:bg-gray-800"
                            >
                                <ClipboardList className="mr-2 h-4 w-4" />
                                Notes
                            </TabsTrigger>
                            <TabsTrigger
                                value="vitals"
                                className="dark:data-[state=active]:bg-gray-800"
                            >
                                <Activity className="mr-2 h-4 w-4" />
                                Vitals
                            </TabsTrigger>
                            <TabsTrigger
                                value="diagnosis"
                                className="dark:data-[state=active]:bg-gray-800"
                            >
                                <FileText className="mr-2 h-4 w-4" />
                                Diagnosis
                            </TabsTrigger>
                            <TabsTrigger
                                value="treatment"
                                className="dark:data-[state=active]:bg-gray-800"
                            >
                                <Pill className="mr-2 h-4 w-4" />
                                Treatment
                            </TabsTrigger>
                            <TabsTrigger
                                value="labs"
                                className="dark:data-[state=active]:bg-gray-800"
                            >
                                <Microscope className="mr-2 h-4 w-4" />
                                Lab Results
                            </TabsTrigger>
                        </TabsList>

                        {/* Consultation Notes Tab */}
                        <TabsContent value="notes" className="mt-4 space-y-4">
                            {visit.presenting_complaint && (
                                <div>
                                    <h4 className="mb-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                        Presenting Complaint (PC)
                                    </h4>
                                    <div className="rounded-lg bg-gray-50 p-3 text-sm whitespace-pre-wrap text-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                        {visit.presenting_complaint}
                                    </div>
                                </div>
                            )}

                            {visit.history_presenting_complaint && (
                                <div>
                                    <h4 className="mb-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                        History of Presenting Complaint (HPC)
                                    </h4>
                                    <div className="rounded-lg bg-gray-50 p-3 text-sm whitespace-pre-wrap text-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                        {visit.history_presenting_complaint}
                                    </div>
                                </div>
                            )}

                            {visit.on_direct_questioning && (
                                <div>
                                    <h4 className="mb-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                        On Direct Questioning (ODQ)
                                    </h4>
                                    <div className="rounded-lg bg-gray-50 p-3 text-sm whitespace-pre-wrap text-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                        {visit.on_direct_questioning}
                                    </div>
                                </div>
                            )}

                            {visit.examination_findings && (
                                <div>
                                    <h4 className="mb-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                        Examination Findings (Exam)
                                    </h4>
                                    <div className="rounded-lg bg-gray-50 p-3 text-sm whitespace-pre-wrap text-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                        {visit.examination_findings}
                                    </div>
                                </div>
                            )}

                            {visit.plan_notes && (
                                <div>
                                    <h4 className="mb-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                        Treatment Plan
                                    </h4>
                                    <div className="rounded-lg bg-gray-50 p-3 text-sm whitespace-pre-wrap text-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                        {visit.plan_notes}
                                    </div>
                                </div>
                            )}

                            {visit.follow_up_date && (
                                <div>
                                    <h4 className="mb-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                        Follow-up Date
                                    </h4>
                                    <div className="rounded-lg bg-blue-50 p-3 text-sm text-blue-700 dark:bg-blue-950/30 dark:text-blue-300">
                                        {new Date(
                                            visit.follow_up_date,
                                        ).toLocaleDateString('en-US', {
                                            year: 'numeric',
                                            month: 'long',
                                            day: 'numeric',
                                        })}
                                    </div>
                                </div>
                            )}

                            {!visit.presenting_complaint &&
                                !visit.history_presenting_complaint &&
                                !visit.on_direct_questioning &&
                                !visit.examination_findings &&
                                !visit.plan_notes && (
                                    <div className="py-8 text-center text-gray-500 dark:text-gray-400">
                                        <FileText className="mx-auto mb-3 h-12 w-12 text-gray-300 dark:text-gray-600" />
                                        <p className="text-sm">
                                            No consultation notes recorded
                                        </p>
                                    </div>
                                )}
                        </TabsContent>

                        {/* Vitals Tab */}
                        <TabsContent value="vitals" className="mt-4">
                            {latestVitals ? (
                                <div className="grid grid-cols-2 gap-4">
                                    <Card className="dark:border-gray-800 dark:bg-gray-900">
                                        <CardHeader className="pb-3">
                                            <CardTitle className="text-sm dark:text-gray-200">
                                                Temperature
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <p className="text-2xl font-bold text-blue-600 dark:text-blue-400">
                                                {latestVitals.temperature}°C
                                            </p>
                                            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                Normal: 36.1-37.2°C
                                            </p>
                                        </CardContent>
                                    </Card>

                                    <Card className="dark:border-gray-800 dark:bg-gray-900">
                                        <CardHeader className="pb-3">
                                            <CardTitle className="text-sm dark:text-gray-200">
                                                Blood Pressure
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <p className="text-2xl font-bold text-red-600 dark:text-red-400">
                                                {
                                                    latestVitals.blood_pressure_systolic
                                                }
                                                /
                                                {
                                                    latestVitals.blood_pressure_diastolic
                                                }
                                            </p>
                                            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                Normal: 90-120/60-80 mmHg
                                            </p>
                                        </CardContent>
                                    </Card>

                                    <Card className="dark:border-gray-800 dark:bg-gray-900">
                                        <CardHeader className="pb-3">
                                            <CardTitle className="text-sm dark:text-gray-200">
                                                Heart Rate
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <p className="text-2xl font-bold text-green-600 dark:text-green-400">
                                                {latestVitals.pulse_rate}
                                            </p>
                                            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                bpm • Normal: 60-100
                                            </p>
                                        </CardContent>
                                    </Card>

                                    <Card className="dark:border-gray-800 dark:bg-gray-900">
                                        <CardHeader className="pb-3">
                                            <CardTitle className="text-sm dark:text-gray-200">
                                                Respiratory Rate
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <p className="text-2xl font-bold text-purple-600 dark:text-purple-400">
                                                {latestVitals.respiratory_rate}
                                            </p>
                                            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                /min • Normal: 12-20
                                            </p>
                                        </CardContent>
                                    </Card>
                                </div>
                            ) : (
                                <div className="py-12 text-center text-gray-500 dark:text-gray-400">
                                    <Activity className="mx-auto mb-3 h-12 w-12 text-gray-300 dark:text-gray-600" />
                                    <p className="text-sm">
                                        No vital signs recorded
                                    </p>
                                </div>
                            )}
                        </TabsContent>

                        {/* Diagnosis Tab */}
                        <TabsContent value="diagnosis" className="mt-4">
                            {visit.diagnoses.length > 0 ? (
                                <div className="space-y-3">
                                    {visit.diagnoses.map((diagnosis) => (
                                        <Card
                                            key={diagnosis.id}
                                            className="dark:border-gray-800 dark:bg-gray-900"
                                        >
                                            <CardContent className="pt-4">
                                                <div className="flex items-start justify-between">
                                                    <div>
                                                        <h4 className="font-semibold text-gray-900 dark:text-gray-100">
                                                            {
                                                                diagnosis.diagnosis_description
                                                            }
                                                        </h4>
                                                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                                            ICD Code:{' '}
                                                            {diagnosis.icd_code}
                                                        </p>
                                                    </div>
                                                    {diagnosis.is_primary && (
                                                        <Badge
                                                            variant="default"
                                                            className="dark:bg-blue-600"
                                                        >
                                                            Primary
                                                        </Badge>
                                                    )}
                                                </div>
                                            </CardContent>
                                        </Card>
                                    ))}
                                </div>
                            ) : (
                                <div className="py-12 text-center text-gray-500 dark:text-gray-400">
                                    <FileText className="mx-auto mb-3 h-12 w-12 text-gray-300 dark:text-gray-600" />
                                    <p className="text-sm">
                                        No diagnoses recorded
                                    </p>
                                </div>
                            )}
                        </TabsContent>

                        {/* Treatment Tab (Prescriptions & Lab Orders) */}
                        <TabsContent
                            value="treatment"
                            className="mt-4 space-y-4"
                        >
                            {/* Prescriptions */}
                            <div>
                                <h4 className="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    <Pill className="h-4 w-4" />
                                    Medications Prescribed
                                </h4>
                                {visit.prescriptions.length > 0 ? (
                                    <div className="space-y-2">
                                        {visit.prescriptions.map(
                                            (prescription) => (
                                                <Card
                                                    key={prescription.id}
                                                    className="dark:border-gray-800 dark:bg-gray-900"
                                                >
                                                    <CardContent className="pt-4">
                                                        <div className="flex items-start justify-between">
                                                            <div className="flex-1">
                                                                <h5 className="font-semibold text-gray-900 dark:text-gray-100">
                                                                    {
                                                                        prescription.medication_name
                                                                    }
                                                                </h5>
                                                                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                                                    {
                                                                        prescription.dosage
                                                                    }{' '}
                                                                    •{' '}
                                                                    {
                                                                        prescription.frequency
                                                                    }{' '}
                                                                    •{' '}
                                                                    {
                                                                        prescription.duration
                                                                    }
                                                                </p>
                                                                {prescription.instructions && (
                                                                    <p className="mt-2 rounded bg-blue-50 p-2 text-sm text-gray-700 dark:bg-blue-950/30 dark:text-gray-300">
                                                                        <strong>
                                                                            Instructions:
                                                                        </strong>{' '}
                                                                        {
                                                                            prescription.instructions
                                                                        }
                                                                    </p>
                                                                )}
                                                            </div>
                                                            <Badge
                                                                variant="outline"
                                                                className="dark:border-gray-700 dark:text-gray-300"
                                                            >
                                                                {
                                                                    prescription.status
                                                                }
                                                            </Badge>
                                                        </div>
                                                    </CardContent>
                                                </Card>
                                            ),
                                        )}
                                    </div>
                                ) : (
                                    <p className="py-4 text-sm text-gray-500 dark:text-gray-400">
                                        No medications prescribed
                                    </p>
                                )}
                            </div>

                            <Separator className="dark:border-gray-800" />

                            {/* Lab Orders */}
                            <div>
                                <h4 className="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    <TestTube className="h-4 w-4" />
                                    Laboratory Tests
                                </h4>
                                {visit.lab_orders &&
                                visit.lab_orders.length > 0 ? (
                                    <div className="space-y-2">
                                        {visit.lab_orders.map((order) => (
                                            <PreviousLabResultCard
                                                key={order.id}
                                                order={order}
                                            />
                                        ))}
                                    </div>
                                ) : (
                                    <p className="py-4 text-sm text-gray-500 dark:text-gray-400">
                                        No lab tests ordered
                                    </p>
                                )}
                            </div>
                        </TabsContent>

                        {/* Lab Results Tab */}
                        <TabsContent value="labs" className="mt-4">
                            {visit.lab_orders && visit.lab_orders.length > 0 ? (
                                <div className="space-y-6">
                                    {/* Group by category */}
                                    {Object.entries(
                                        visit.lab_orders.reduce(
                                            (acc, order) => {
                                                const category =
                                                    order.lab_service.category;
                                                if (!acc[category])
                                                    acc[category] = [];
                                                acc[category].push(order);
                                                return acc;
                                            },
                                            {} as Record<
                                                string,
                                                typeof visit.lab_orders
                                            >,
                                        ),
                                    ).map(([category, orders]) => (
                                        <div key={category}>
                                            <h4 className="mb-3 flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                <Microscope className="h-4 w-4" />
                                                {category}
                                            </h4>
                                            <div className="space-y-2">
                                                {orders.map((order) => (
                                                    <PreviousLabResultCard
                                                        key={order.id}
                                                        order={order}
                                                        defaultExpanded={
                                                            order.status ===
                                                            'completed'
                                                        }
                                                    />
                                                ))}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="py-12 text-center text-gray-500 dark:text-gray-400">
                                    <Microscope className="mx-auto mb-3 h-12 w-12 text-gray-300 dark:text-gray-600" />
                                    <p className="text-sm">
                                        No lab tests ordered
                                    </p>
                                </div>
                            )}
                        </TabsContent>
                    </Tabs>
                </ScrollArea>
            </DialogContent>
        </Dialog>
    );
}
