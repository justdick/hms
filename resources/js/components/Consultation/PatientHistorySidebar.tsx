import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import {
    Activity,
    Bandage,
    Calendar,
    ChevronRight,
    FileText,
    History,
    Pill,
    TestTube,
    User,
} from 'lucide-react';
import { useState } from 'react';
import { PreviousVisitModal } from './PreviousVisitModal';

interface VitalSigns {
    id: number;
    temperature: number;
    blood_pressure_systolic: number;
    blood_pressure_diastolic: number;
    pulse_rate: number;
    respiratory_rate: number;
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
        price: number;
        sample_type: string;
    };
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

interface MinorProcedureSupply {
    id: number;
    drug: {
        id: number;
        name: string;
        form: string;
        strength: string;
    };
    quantity: number;
    dispensed: boolean;
}

interface MinorProcedureDiagnosis {
    id: number;
    diagnosis: string;
    code: string;
    icd_10: string;
    g_drg: string;
}

interface MinorProcedure {
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
    diagnoses: MinorProcedureDiagnosis[];
    supplies: MinorProcedureSupply[];
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
    previousConsultations: PreviousConsultation[];
    previousMinorProcedures?: MinorProcedure[];
    allergies: string[];
}

export function PatientHistorySidebar({
    previousConsultations,
    previousMinorProcedures = [],
    allergies,
}: Props) {
    const [isOpen, setIsOpen] = useState(false);
    const [selectedVisit, setSelectedVisit] =
        useState<PreviousConsultation | null>(null);
    const [showVisitModal, setShowVisitModal] = useState(false);

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
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

    const handleVisitClick = (visit: PreviousConsultation) => {
        setSelectedVisit(visit);
        setShowVisitModal(true);
    };

    return (
        <>
            <Sheet open={isOpen} onOpenChange={setIsOpen}>
                <SheetTrigger asChild>
                    <Button variant="outline" className="gap-2">
                        <History className="h-4 w-4" />
                        Previous Visits ({previousConsultations.length + previousMinorProcedures.length})
                    </Button>
                </SheetTrigger>
                <SheetContent
                    side="right"
                    className="w-[400px] sm:w-[500px] dark:border-gray-800 dark:bg-gray-950"
                >
                    <SheetHeader>
                        <SheetTitle className="dark:text-gray-100">
                            Patient History
                        </SheetTitle>
                        <SheetDescription className="dark:text-gray-400">
                            Review previous consultations and medical records
                        </SheetDescription>
                    </SheetHeader>

                    <ScrollArea className="mt-6 h-[calc(100vh-120px)] pr-4">
                        {/* Medical Alerts */}
                        {allergies && allergies.length > 0 && (
                            <div className="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950/30">
                                <h3 className="mb-2 flex items-center gap-2 text-sm font-semibold text-red-900 dark:text-red-200">
                                    <Activity className="h-4 w-4" />
                                    ⚠️ Allergies & Alerts
                                </h3>
                                <div className="flex flex-wrap gap-2">
                                    {allergies.map((allergy, index) => (
                                        <Badge
                                            key={index}
                                            variant="destructive"
                                            className="dark:bg-red-900 dark:text-red-100"
                                        >
                                            {allergy}
                                        </Badge>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* Minor Procedures Section */}
                        {previousMinorProcedures.length > 0 && (
                            <div className="mb-6 space-y-3">
                                <h3 className="mb-3 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    Minor Procedures
                                </h3>

                                {previousMinorProcedures.map((procedure) => (
                                    <div
                                        key={procedure.id}
                                        className="rounded-lg border bg-purple-50/50 p-4 dark:border-purple-900/30 dark:bg-purple-950/20"
                                    >
                                        {/* Procedure Header */}
                                        <div className="mb-3 flex items-start justify-between">
                                            <div className="flex-1">
                                                <div className="mb-1 flex items-center gap-2">
                                                    <Bandage className="h-3.5 w-3.5 text-purple-600 dark:text-purple-400" />
                                                    <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                        {
                                                            procedure
                                                                .procedure_type
                                                                .name
                                                        }
                                                    </p>
                                                </div>
                                                <div className="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                                                    <Calendar className="h-3 w-3" />
                                                    <span>
                                                        {formatDate(
                                                            procedure.performed_at,
                                                        )}
                                                    </span>
                                                    <span>•</span>
                                                    <User className="h-3 w-3" />
                                                    <span>
                                                        {procedure.nurse.name}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        {/* Procedure Notes */}
                                        {procedure.procedure_notes && (
                                            <div className="mb-3 rounded bg-purple-100/50 p-2 text-xs dark:bg-purple-950/30">
                                                <p className="mb-1 font-medium text-purple-900 dark:text-purple-300">
                                                    Notes:
                                                </p>
                                                <p className="line-clamp-2 text-purple-800 dark:text-purple-400">
                                                    {procedure.procedure_notes}
                                                </p>
                                            </div>
                                        )}

                                        {/* Diagnoses */}
                                        {procedure.diagnoses.length > 0 && (
                                            <div className="mb-2">
                                                <p className="mb-1 text-xs font-medium text-gray-700 dark:text-gray-300">
                                                    Diagnoses:
                                                </p>
                                                <div className="flex flex-wrap gap-1">
                                                    {procedure.diagnoses.map(
                                                        (diagnosis) => (
                                                            <Badge
                                                                key={
                                                                    diagnosis.id
                                                                }
                                                                variant="secondary"
                                                                className="text-xs dark:bg-gray-800 dark:text-gray-300"
                                                            >
                                                                {diagnosis.code}
                                                            </Badge>
                                                        ),
                                                    )}
                                                </div>
                                            </div>
                                        )}

                                        {/* Supplies Used */}
                                        {procedure.supplies.length > 0 && (
                                            <div className="border-t pt-2 dark:border-purple-900/30">
                                                <p className="mb-1 text-xs font-medium text-gray-700 dark:text-gray-300">
                                                    Supplies Used:
                                                </p>
                                                <div className="space-y-1">
                                                    {procedure.supplies.map(
                                                        (supply) => (
                                                            <div
                                                                key={supply.id}
                                                                className="flex items-center justify-between text-xs text-gray-600 dark:text-gray-400"
                                                            >
                                                                <span>
                                                                    {
                                                                        supply
                                                                            .drug
                                                                            .name
                                                                    }{' '}
                                                                    {supply.drug
                                                                        .strength &&
                                                                        `(${supply.drug.strength})`}
                                                                </span>
                                                                <span className="font-medium">
                                                                    ×
                                                                    {
                                                                        supply.quantity
                                                                    }
                                                                </span>
                                                            </div>
                                                        ),
                                                    )}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}

                        {/* Previous Visits List */}
                        <div className="space-y-3">
                            <h3 className="mb-3 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                Recent Consultations
                            </h3>

                            {previousConsultations.length === 0 ? (
                                <div className="py-12 text-center text-gray-500 dark:text-gray-400">
                                    <History className="mx-auto mb-3 h-12 w-12 text-gray-300 dark:text-gray-600" />
                                    <p className="text-sm">
                                        No previous consultations found
                                    </p>
                                </div>
                            ) : (
                                previousConsultations.map((visit) => (
                                    <div
                                        key={visit.id}
                                        onClick={() => handleVisitClick(visit)}
                                        className="cursor-pointer rounded-lg border p-4 transition-colors hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-900"
                                    >
                                        {/* Visit Header */}
                                        <div className="mb-3 flex items-start justify-between">
                                            <div className="flex-1">
                                                <div className="mb-1 flex items-center gap-2">
                                                    <Calendar className="h-3.5 w-3.5 text-gray-500 dark:text-gray-400" />
                                                    <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                        {formatDate(
                                                            visit.started_at,
                                                        )}
                                                    </p>
                                                </div>
                                                <div className="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                                                    <User className="h-3 w-3" />
                                                    <span>
                                                        Dr. {visit.doctor.name}
                                                    </span>
                                                    <span>•</span>
                                                    <span>
                                                        {
                                                            visit
                                                                .patient_checkin
                                                                .department.name
                                                        }
                                                    </span>
                                                </div>
                                            </div>
                                            <ChevronRight className="h-4 w-4 text-gray-400 dark:text-gray-500" />
                                        </div>

                                        {/* Presenting Complaint */}
                                        {visit.presenting_complaint && (
                                            <div className="mb-3 rounded bg-blue-50 p-2 text-xs dark:bg-blue-950/30">
                                                <p className="mb-1 font-medium text-blue-900 dark:text-blue-300">
                                                    Presenting Complaint:
                                                </p>
                                                <p className="line-clamp-2 text-blue-800 dark:text-blue-400">
                                                    {visit.presenting_complaint}
                                                </p>
                                            </div>
                                        )}

                                        {/* Quick Summary */}
                                        <div className="grid grid-cols-3 gap-2 text-xs">
                                            <div className="flex items-center gap-1 text-gray-600 dark:text-gray-400">
                                                <FileText className="h-3 w-3" />
                                                <span>
                                                    {visit.diagnoses.length} Dx
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-1 text-gray-600 dark:text-gray-400">
                                                <Pill className="h-3 w-3" />
                                                <span>
                                                    {visit.prescriptions.length}{' '}
                                                    Rx
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-1 text-gray-600 dark:text-gray-400">
                                                <TestTube className="h-3 w-3" />
                                                <span>
                                                    {visit.lab_orders?.length ||
                                                        0}{' '}
                                                    Labs
                                                </span>
                                            </div>
                                        </div>

                                        {/* Top Diagnoses */}
                                        {visit.diagnoses.length > 0 && (
                                            <div className="mt-3 border-t pt-3 dark:border-gray-800">
                                                <p className="mb-1 text-xs font-medium text-gray-700 dark:text-gray-300">
                                                    Diagnoses:
                                                </p>
                                                <div className="flex flex-wrap gap-1">
                                                    {visit.diagnoses
                                                        .slice(0, 2)
                                                        .map((diagnosis) => (
                                                            <Badge
                                                                key={
                                                                    diagnosis.id
                                                                }
                                                                variant={
                                                                    diagnosis.is_primary
                                                                        ? 'default'
                                                                        : 'secondary'
                                                                }
                                                                className="text-xs dark:bg-gray-800 dark:text-gray-300"
                                                            >
                                                                {
                                                                    diagnosis.icd_code
                                                                }
                                                            </Badge>
                                                        ))}
                                                    {visit.diagnoses.length >
                                                        2 && (
                                                        <Badge
                                                            variant="outline"
                                                            className="text-xs dark:border-gray-700 dark:text-gray-400"
                                                        >
                                                            +
                                                            {visit.diagnoses
                                                                .length -
                                                                2}{' '}
                                                            more
                                                        </Badge>
                                                    )}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                ))
                            )}
                        </div>
                    </ScrollArea>
                </SheetContent>
            </Sheet>

            {/* Visit Detail Modal */}
            {selectedVisit && (
                <PreviousVisitModal
                    visit={selectedVisit}
                    open={showVisitModal}
                    onOpenChange={setShowVisitModal}
                />
            )}
        </>
    );
}
