import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { PrintableLabResult } from '@/components/Lab/PrintableLabResult';
import AppLayout from '@/layouts/app-layout';
import lab from '@/routes/lab';
import { Head, router } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    CheckCircle,
    FileText,
    Printer,
    TestTube,
    Timer,
} from 'lucide-react';
import { useRef } from 'react';
import {
    ConsultationTest,
    consultationTestColumns,
} from './consultation-tests-columns';
import { LabOrdersDataTable } from './lab-orders-data-table';

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
    patient_number: string;
    first_name: string;
    last_name: string;
    phone_number?: string;
    date_of_birth: string;
    gender: string;
    active_insurance?: PatientInsurance | null;
}

interface Ward {
    id: number;
    name: string;
    code: string;
}

interface PatientAdmission {
    patient: Patient;
    ward: Ward;
}

interface Doctor {
    id: number;
    name: string;
}

interface WardRound {
    id: number;
    patient_admission: PatientAdmission;
    doctor: Doctor;
    day_number: number;
    round_type: string;
    presenting_complaint?: string;
    round_datetime: string;
}

interface HospitalInfo {
    name: string;
    logo_url?: string;
}

interface Props {
    wardRound: WardRound;
    labOrders: ConsultationTest[];
    hospital: HospitalInfo;
}

const statusConfig = {
    ordered: {
        label: 'Ordered',
        icon: FileText,
        className:
            'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
    },
    sample_collected: {
        label: 'Sample Collected',
        icon: TestTube,
        className:
            'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
    },
    in_progress: {
        label: 'In Progress',
        icon: Timer,
        className:
            'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300',
    },
    completed: {
        label: 'Completed',
        icon: CheckCircle,
        className:
            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    },
    cancelled: {
        label: 'Cancelled',
        icon: AlertCircle,
        className: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
    },
};

export default function WardRoundShow({ wardRound, labOrders, hospital }: Props) {
    const patient = wardRound.patient_admission.patient;
    const ward = wardRound.patient_admission.ward;
    const printRef = useRef<HTMLDivElement>(null);

    const statusCounts = labOrders.reduce(
        (acc, order) => {
            acc[order.status] = (acc[order.status] || 0) + 1;
            return acc;
        },
        {} as Record<string, number>,
    );

    // Get completed tests for printing
    const completedTests = labOrders.filter(order => order.status === 'completed');
    const hasCompletedTests = completedTests.length > 0;

    const handlePrint = () => {
        window.print();
    };

    // Transform completed tests to print format
    const printableResults = completedTests.map(test => ({
        id: test.id,
        test_name: test.lab_service.name,
        test_code: test.lab_service.code,
        category: test.lab_service.category || 'General',
        result_values: test.result_values,
        result_notes: test.result_notes,
    }));

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Laboratory', href: lab.index.url() },
                {
                    title: `Ward Round - Day ${wardRound.day_number}`,
                    href: '#',
                },
            ]}
        >
            <Head
                title={`Lab Tests - ${patient.first_name} ${patient.last_name}`}
            />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => router.visit(lab.index.url())}
                        >
                            <ArrowLeft className="mr-1 h-4 w-4" />
                            Back to Lab Dashboard
                        </Button>
                        <div>
                            <h1 className="text-2xl font-bold">
                                Lab Tests - {patient.first_name}{' '}
                                {patient.last_name}
                            </h1>
                            <div className="flex items-center gap-2 text-muted-foreground">
                                <span>
                                    Ward Round Day {wardRound.day_number} •{' '}
                                    {ward.name} • {labOrders.length} test
                                    {labOrders.length !== 1 ? 's' : ''} ordered
                                </span>
                                <span>•</span>
                                {patient.active_insurance ? (
                                    <Badge
                                        variant="outline"
                                        className="bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300"
                                    >
                                        {
                                            patient.active_insurance.plan
                                                .provider.code
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
                    </div>
                    <div className="flex items-center gap-2">
                        {Object.entries(statusCounts).map(([status, count]) => {
                            const config =
                                statusConfig[
                                status as keyof typeof statusConfig
                                ];
                            if (!config) return null;
                            const Icon = config.icon;
                            return (
                                <Badge
                                    key={status}
                                    className={config.className}
                                    variant="outline"
                                >
                                    <Icon className="mr-1 h-3 w-3" />
                                    {count} {config.label}
                                </Badge>
                            );
                        })}
                        {hasCompletedTests && (
                            <Button
                                size="sm"
                                onClick={handlePrint}
                                className="bg-green-600 hover:bg-green-700 text-white"
                            >
                                <Printer className="mr-1 h-4 w-4" />
                                Print Results
                            </Button>
                        )}
                    </div>
                </div>

                {/* Lab Tests DataTable */}
                <Card>
                    <CardHeader>
                        <CardTitle>Ordered Tests</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <LabOrdersDataTable
                            columns={consultationTestColumns}
                            data={labOrders}
                        />
                    </CardContent>
                </Card>
            </div>

            {/* Printable Component - Uses Portal to render directly in body */}
            {hasCompletedTests && (
                <PrintableLabResult
                    ref={printRef}
                    hospital={hospital}
                    patient={patient}
                    results={printableResults}
                />
            )}
        </AppLayout>
    );
}
