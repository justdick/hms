import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { FileText, Pill, Stethoscope, TestTube, User } from 'lucide-react';

interface Doctor {
    id: number;
    name: string;
}

interface Drug {
    id: number;
    name: string;
    generic_name?: string;
    strength?: string;
    form?: string;
}

interface Prescription {
    id: number;
    medication_name: string;
    drug?: Drug;
    dose_quantity?: string;
    frequency: string;
    duration: string;
    instructions?: string;
    status: string;
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
    priority: string;
    status: string;
    special_instructions?: string;
    ordered_at: string;
    result_values?: any;
    result_notes?: string;
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

interface ProcedureType {
    id: number;
    name: string;
    code: string;
    type: 'minor' | 'major';
    category: string;
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
    day_number: number;
    round_type: string;
    round_datetime: string;
    presenting_complaint?: string;
    history_presenting_complaint?: string;
    on_direct_questioning?: string;
    examination_findings?: string;
    assessment_notes?: string;
    plan_notes?: string;
    status: string;
    created_at: string;
    doctor?: Doctor;
    diagnoses?: WardRoundDiagnosis[];
    prescriptions?: Prescription[];
    lab_orders?: LabOrder[];
    procedures?: WardRoundProcedure[];
}

interface Props {
    open: boolean;
    onClose: () => void;
    wardRound: WardRound | null;
    patientName?: string;
}

export function WardRoundViewModal({
    open,
    onClose,
    wardRound,
    patientName,
}: Props) {
    if (!wardRound) return null;

    const formatDateTime = (dateString: string) => {
        return new Date(dateString).toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-h-[90vh] w-[95vw] max-w-[95vw] overflow-hidden sm:max-w-[95vw]">
                <DialogHeader>
                    <div className="flex items-center justify-between pr-8">
                        <div>
                            <DialogTitle className="text-2xl">
                                Ward Round - Day {wardRound.day_number}
                            </DialogTitle>
                            {patientName && (
                                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    {patientName} •{' '}
                                    {formatDateTime(wardRound.round_datetime)}
                                </p>
                            )}
                        </div>
                        <Badge
                            variant={
                                wardRound.status === 'completed'
                                    ? 'default'
                                    : 'secondary'
                            }
                        >
                            {wardRound.status === 'completed'
                                ? 'Completed'
                                : 'In Progress'}
                        </Badge>
                    </div>
                </DialogHeader>

                <ScrollArea className="max-h-[calc(90vh-120px)]">
                    <div className="space-y-6 pr-4">
                        {/* Doctor Info */}
                        {wardRound.doctor && (
                            <div className="flex items-center gap-2 rounded-lg bg-gray-50 p-3 dark:bg-gray-900">
                                <User className="h-4 w-4 text-gray-600 dark:text-gray-400" />
                                <span className="text-sm text-gray-600 dark:text-gray-400">
                                    Doctor:
                                </span>
                                <span className="font-medium text-gray-900 dark:text-gray-100">
                                    {wardRound.doctor.name}
                                </span>
                            </div>
                        )}

                        <Tabs defaultValue="notes" className="w-full">
                            <TabsList className="grid w-full grid-cols-5">
                                <TabsTrigger value="notes">
                                    <FileText className="mr-2 h-4 w-4" />
                                    Consultation Notes
                                </TabsTrigger>
                                <TabsTrigger value="diagnoses">
                                    <Stethoscope className="mr-2 h-4 w-4" />
                                    Diagnoses{' '}
                                    {(wardRound.diagnoses?.length ?? 0) > 0 && (
                                        <Badge
                                            variant="secondary"
                                            className="ml-1"
                                        >
                                            {wardRound.diagnoses?.length}
                                        </Badge>
                                    )}
                                </TabsTrigger>
                                <TabsTrigger value="prescriptions">
                                    <Pill className="mr-2 h-4 w-4" />
                                    Prescriptions{' '}
                                    {(wardRound.prescriptions?.length ?? 0) >
                                        0 && (
                                        <Badge
                                            variant="secondary"
                                            className="ml-1"
                                        >
                                            {wardRound.prescriptions?.length}
                                        </Badge>
                                    )}
                                </TabsTrigger>
                                <TabsTrigger value="labs">
                                    <TestTube className="mr-2 h-4 w-4" />
                                    Lab Orders{' '}
                                    {(wardRound.lab_orders?.length ?? 0) >
                                        0 && (
                                        <Badge
                                            variant="secondary"
                                            className="ml-1"
                                        >
                                            {wardRound.lab_orders?.length}
                                        </Badge>
                                    )}
                                </TabsTrigger>
                                <TabsTrigger value="theatre">
                                    <Stethoscope className="mr-2 h-4 w-4" />
                                    Theatre{' '}
                                    {(wardRound.procedures?.length ?? 0) >
                                        0 && (
                                        <Badge
                                            variant="secondary"
                                            className="ml-1"
                                        >
                                            {wardRound.procedures?.length}
                                        </Badge>
                                    )}
                                </TabsTrigger>
                            </TabsList>

                            {/* Clinical Notes Tab - Sub-tabbed like consultation */}
                            <TabsContent value="notes" className="space-y-4">
                                <Tabs defaultValue="pc" className="w-full">
                                    <TabsList className="grid w-full grid-cols-8">
                                        <TabsTrigger value="pc">PC</TabsTrigger>
                                        <TabsTrigger value="hpc">
                                            HPC
                                        </TabsTrigger>
                                        <TabsTrigger value="odq">
                                            ODQ
                                        </TabsTrigger>
                                        <TabsTrigger value="exam">
                                            Exam
                                        </TabsTrigger>
                                        <TabsTrigger value="assessment">
                                            Assessment
                                        </TabsTrigger>
                                        <TabsTrigger value="plan">
                                            Plan
                                        </TabsTrigger>
                                    </TabsList>

                                    <TabsContent value="pc" className="mt-4">
                                        {wardRound.presenting_complaint ? (
                                            <p className="rounded-lg bg-gray-50 p-4 text-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                                {wardRound.presenting_complaint}
                                            </p>
                                        ) : (
                                            <div className="py-8 text-center">
                                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                                    No presenting complaint
                                                    recorded
                                                </p>
                                            </div>
                                        )}
                                    </TabsContent>

                                    <TabsContent value="hpc" className="mt-4">
                                        {wardRound.history_presenting_complaint ? (
                                            <p className="rounded-lg bg-gray-50 p-4 text-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                                {
                                                    wardRound.history_presenting_complaint
                                                }
                                            </p>
                                        ) : (
                                            <div className="py-8 text-center">
                                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                                    No history recorded
                                                </p>
                                            </div>
                                        )}
                                    </TabsContent>

                                    <TabsContent value="odq" className="mt-4">
                                        {wardRound.on_direct_questioning ? (
                                            <p className="rounded-lg bg-gray-50 p-4 text-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                                {
                                                    wardRound.on_direct_questioning
                                                }
                                            </p>
                                        ) : (
                                            <div className="py-8 text-center">
                                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                                    No direct questioning notes
                                                </p>
                                            </div>
                                        )}
                                    </TabsContent>

                                    <TabsContent value="exam" className="mt-4">
                                        {wardRound.examination_findings ? (
                                            <p className="rounded-lg bg-gray-50 p-4 text-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                                {wardRound.examination_findings}
                                            </p>
                                        ) : (
                                            <div className="py-8 text-center">
                                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                                    No examination findings
                                                </p>
                                            </div>
                                        )}
                                    </TabsContent>

                                    <TabsContent
                                        value="assessment"
                                        className="mt-4"
                                    >
                                        {wardRound.assessment_notes ? (
                                            <p className="rounded-lg bg-gray-50 p-4 text-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                                {wardRound.assessment_notes}
                                            </p>
                                        ) : (
                                            <div className="py-8 text-center">
                                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                                    No assessment notes
                                                </p>
                                            </div>
                                        )}
                                    </TabsContent>

                                    <TabsContent value="plan" className="mt-4">
                                        {wardRound.plan_notes ? (
                                            <p className="rounded-lg bg-gray-50 p-4 text-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                                {wardRound.plan_notes}
                                            </p>
                                        ) : (
                                            <div className="py-8 text-center">
                                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                                    No plan notes
                                                </p>
                                            </div>
                                        )}
                                    </TabsContent>
                                </Tabs>
                            </TabsContent>

                            {/* Diagnoses Tab - Compact Table View */}
                            <TabsContent value="diagnoses" className="mt-4">
                                {wardRound.diagnoses &&
                                wardRound.diagnoses.length > 0 ? (
                                    <div className="overflow-hidden rounded-lg border dark:border-gray-700">
                                        <table className="w-full">
                                            <thead className="bg-gray-50 dark:bg-gray-900">
                                                <tr>
                                                    <th className="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                        Diagnosis
                                                    </th>
                                                    <th className="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                        ICD Code
                                                    </th>
                                                    <th className="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                        Type
                                                    </th>
                                                    <th className="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                        Diagnosed By
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y dark:divide-gray-700">
                                                {wardRound.diagnoses.map(
                                                    (diagnosis) => (
                                                        <tr
                                                            key={diagnosis.id}
                                                            className="hover:bg-gray-50 dark:hover:bg-gray-900"
                                                        >
                                                            <td className="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                                                                {
                                                                    diagnosis.diagnosis_name
                                                                }
                                                            </td>
                                                            <td className="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                                                {
                                                                    diagnosis.icd_code
                                                                }
                                                            </td>
                                                            <td className="px-4 py-3">
                                                                <Badge
                                                                    variant="outline"
                                                                    className="text-xs"
                                                                >
                                                                    {diagnosis.diagnosis_type
                                                                        .replace(
                                                                            '_',
                                                                            ' ',
                                                                        )
                                                                        .replace(
                                                                            /\b\w/g,
                                                                            (
                                                                                l,
                                                                            ) =>
                                                                                l.toUpperCase(),
                                                                        )}
                                                                </Badge>
                                                            </td>
                                                            <td className="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                                                {diagnosis
                                                                    .diagnosed_by
                                                                    ?.name ||
                                                                    '-'}
                                                            </td>
                                                        </tr>
                                                    ),
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                ) : (
                                    <div className="py-8 text-center">
                                        <Stethoscope className="mx-auto mb-2 h-12 w-12 text-gray-300 dark:text-gray-600" />
                                        <p className="text-gray-500 dark:text-gray-400">
                                            No diagnoses recorded
                                        </p>
                                    </div>
                                )}
                            </TabsContent>

                            {/* Prescriptions Tab - Compact Table View */}
                            <TabsContent value="prescriptions" className="mt-4">
                                {wardRound.prescriptions &&
                                wardRound.prescriptions.length > 0 ? (
                                    <div className="overflow-hidden rounded-lg border dark:border-gray-700">
                                        <table className="w-full">
                                            <thead className="bg-gray-50 dark:bg-gray-900">
                                                <tr>
                                                    <th className="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                        Medication
                                                    </th>
                                                    <th className="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                        Dose
                                                    </th>
                                                    <th className="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                        Frequency
                                                    </th>
                                                    <th className="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                        Duration
                                                    </th>
                                                    <th className="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                        Instructions
                                                    </th>
                                                    <th className="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                        Status
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y dark:divide-gray-700">
                                                {wardRound.prescriptions.map(
                                                    (prescription) => (
                                                        <tr
                                                            key={
                                                                prescription.id
                                                            }
                                                            className="hover:bg-gray-50 dark:hover:bg-gray-900"
                                                        >
                                                            <td className="px-4 py-3">
                                                                <div>
                                                                    <p className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                                        {prescription
                                                                            .drug
                                                                            ?.name ||
                                                                            prescription.medication_name}
                                                                    </p>
                                                                    {prescription
                                                                        .drug
                                                                        ?.strength && (
                                                                        <p className="text-xs text-gray-500 dark:text-gray-400">
                                                                            {
                                                                                prescription
                                                                                    .drug
                                                                                    .strength
                                                                            }
                                                                            {prescription
                                                                                .drug
                                                                                .form &&
                                                                                ` (${prescription.drug.form})`}
                                                                        </p>
                                                                    )}
                                                                </div>
                                                            </td>
                                                            <td className="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                                                {prescription.dose_quantity ||
                                                                    '-'}
                                                            </td>
                                                            <td className="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                                                {
                                                                    prescription.frequency
                                                                }
                                                            </td>
                                                            <td className="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                                                {
                                                                    prescription.duration
                                                                }
                                                            </td>
                                                            <td className="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                                                {prescription.instructions ? (
                                                                    <span className="italic">
                                                                        {
                                                                            prescription.instructions
                                                                        }
                                                                    </span>
                                                                ) : (
                                                                    '-'
                                                                )}
                                                            </td>
                                                            <td className="px-4 py-3">
                                                                <Badge
                                                                    variant={
                                                                        prescription.status ===
                                                                        'prescribed'
                                                                            ? 'secondary'
                                                                            : 'default'
                                                                    }
                                                                    className="text-xs"
                                                                >
                                                                    {prescription.status
                                                                        .replace(
                                                                            '_',
                                                                            ' ',
                                                                        )
                                                                        .replace(
                                                                            /\b\w/g,
                                                                            (
                                                                                l,
                                                                            ) =>
                                                                                l.toUpperCase(),
                                                                        )}
                                                                </Badge>
                                                            </td>
                                                        </tr>
                                                    ),
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                ) : (
                                    <div className="py-8 text-center">
                                        <Pill className="mx-auto mb-2 h-12 w-12 text-gray-300 dark:text-gray-600" />
                                        <p className="text-gray-500 dark:text-gray-400">
                                            No prescriptions recorded
                                        </p>
                                    </div>
                                )}
                            </TabsContent>

                            {/* Lab Orders Tab - Compact Table with Results */}
                            <TabsContent value="labs" className="mt-4">
                                {wardRound.lab_orders &&
                                wardRound.lab_orders.length > 0 ? (
                                    <div className="overflow-hidden rounded-lg border dark:border-gray-700">
                                        <table className="w-full">
                                            <thead className="bg-gray-50 dark:bg-gray-900">
                                                <tr>
                                                    <th className="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                        Test
                                                    </th>
                                                    <th className="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                        Code
                                                    </th>
                                                    <th className="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                        Priority
                                                    </th>
                                                    <th className="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                        Status
                                                    </th>
                                                    <th className="px-4 py-3 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                        Result
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y dark:divide-gray-700">
                                                {wardRound.lab_orders.map(
                                                    (order) => (
                                                        <tr
                                                            key={order.id}
                                                            className="hover:bg-gray-50 dark:hover:bg-gray-900"
                                                        >
                                                            <td className="px-4 py-3">
                                                                <p className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                                    {
                                                                        order
                                                                            .lab_service
                                                                            ?.name
                                                                    }
                                                                </p>
                                                                {order.special_instructions && (
                                                                    <p className="text-xs text-gray-500 italic dark:text-gray-400">
                                                                        {
                                                                            order.special_instructions
                                                                        }
                                                                    </p>
                                                                )}
                                                            </td>
                                                            <td className="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                                                                {
                                                                    order
                                                                        .lab_service
                                                                        ?.code
                                                                }
                                                            </td>
                                                            <td className="px-4 py-3">
                                                                <Badge
                                                                    variant={
                                                                        order.priority ===
                                                                        'stat'
                                                                            ? 'destructive'
                                                                            : order.priority ===
                                                                                'urgent'
                                                                              ? 'default'
                                                                              : 'secondary'
                                                                    }
                                                                    className="text-xs"
                                                                >
                                                                    {order.priority.toUpperCase()}
                                                                </Badge>
                                                            </td>
                                                            <td className="px-4 py-3">
                                                                <Badge
                                                                    variant={
                                                                        order.status ===
                                                                        'completed'
                                                                            ? 'default'
                                                                            : 'secondary'
                                                                    }
                                                                    className="text-xs"
                                                                >
                                                                    {order.status
                                                                        .replace(
                                                                            '_',
                                                                            ' ',
                                                                        )
                                                                        .replace(
                                                                            /\b\w/g,
                                                                            (
                                                                                l,
                                                                            ) =>
                                                                                l.toUpperCase(),
                                                                        )}
                                                                </Badge>
                                                            </td>
                                                            <td className="px-4 py-3">
                                                                {order.status ===
                                                                'completed' ? (
                                                                    order.result_notes ? (
                                                                        <div className="max-w-md">
                                                                            <Badge
                                                                                variant="default"
                                                                                className="mb-1 bg-green-600 text-white"
                                                                            >
                                                                                ✓
                                                                                Available
                                                                            </Badge>
                                                                            <p className="text-sm text-gray-700 dark:text-gray-300">
                                                                                {
                                                                                    order.result_notes
                                                                                }
                                                                            </p>
                                                                        </div>
                                                                    ) : (
                                                                        <Badge
                                                                            variant="outline"
                                                                            className="bg-yellow-50 text-yellow-700 dark:bg-yellow-950 dark:text-yellow-300"
                                                                        >
                                                                            Pending
                                                                            Entry
                                                                        </Badge>
                                                                    )
                                                                ) : (
                                                                    <span className="text-sm text-gray-500 dark:text-gray-400">
                                                                        -
                                                                    </span>
                                                                )}
                                                            </td>
                                                        </tr>
                                                    ),
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                ) : (
                                    <div className="py-8 text-center">
                                        <TestTube className="mx-auto mb-2 h-12 w-12 text-gray-300 dark:text-gray-600" />
                                        <p className="text-gray-500 dark:text-gray-400">
                                            No lab orders recorded
                                        </p>
                                    </div>
                                )}
                            </TabsContent>

                            {/* Theatre Procedures Tab */}
                            <TabsContent value="theatre" className="mt-4">
                                {wardRound.procedures &&
                                wardRound.procedures.length > 0 ? (
                                    <div className="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                                        <table className="w-full">
                                            <thead className="bg-gray-50 dark:bg-gray-800">
                                                <tr>
                                                    <th className="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-700 uppercase dark:text-gray-300">
                                                        Procedure
                                                    </th>
                                                    <th className="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-700 uppercase dark:text-gray-300">
                                                        Type
                                                    </th>
                                                    <th className="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-700 uppercase dark:text-gray-300">
                                                        Comments
                                                    </th>
                                                    <th className="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-700 uppercase dark:text-gray-300">
                                                        Performed By
                                                    </th>
                                                    <th className="px-4 py-3 text-left text-xs font-medium tracking-wider text-gray-700 uppercase dark:text-gray-300">
                                                        Date/Time
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                                                {wardRound.procedures.map(
                                                    (procedure) => (
                                                        <tr
                                                            key={procedure.id}
                                                            className="hover:bg-gray-50 dark:hover:bg-gray-800"
                                                        >
                                                            <td className="px-4 py-3">
                                                                <div className="font-medium text-gray-900 dark:text-gray-100">
                                                                    {
                                                                        procedure
                                                                            .procedure_type
                                                                            .name
                                                                    }
                                                                </div>
                                                                <div className="text-xs text-gray-500 dark:text-gray-400">
                                                                    {
                                                                        procedure
                                                                            .procedure_type
                                                                            .code
                                                                    }
                                                                </div>
                                                            </td>
                                                            <td className="px-4 py-3">
                                                                <Badge
                                                                    variant="outline"
                                                                    className={
                                                                        procedure
                                                                            .procedure_type
                                                                            .type ===
                                                                        'major'
                                                                            ? 'border-purple-200 bg-purple-100 text-purple-700 dark:bg-purple-900/20 dark:text-purple-400'
                                                                            : 'border-blue-200 bg-blue-100 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400'
                                                                    }
                                                                >
                                                                    {procedure
                                                                        .procedure_type
                                                                        .type ===
                                                                    'major'
                                                                        ? 'Major'
                                                                        : 'Minor'}
                                                                </Badge>
                                                            </td>
                                                            <td className="px-4 py-3">
                                                                {procedure.comments ? (
                                                                    <p className="text-sm text-gray-700 dark:text-gray-300">
                                                                        {
                                                                            procedure.comments
                                                                        }
                                                                    </p>
                                                                ) : (
                                                                    <span className="text-sm text-gray-400 dark:text-gray-500">
                                                                        No
                                                                        comments
                                                                    </span>
                                                                )}
                                                            </td>
                                                            <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                                                {
                                                                    procedure
                                                                        .doctor
                                                                        .name
                                                                }
                                                            </td>
                                                            <td className="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                                                {formatDateTime(
                                                                    procedure.performed_at,
                                                                )}
                                                            </td>
                                                        </tr>
                                                    ),
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                ) : (
                                    <div className="py-8 text-center">
                                        <Stethoscope className="mx-auto mb-2 h-12 w-12 text-gray-300 dark:text-gray-600" />
                                        <p className="text-gray-500 dark:text-gray-400">
                                            No procedures documented
                                        </p>
                                    </div>
                                )}
                            </TabsContent>
                        </Tabs>
                    </div>
                </ScrollArea>
            </DialogContent>
        </Dialog>
    );
}
