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

interface PatientCheckin {
    patient: Patient;
}

interface Consultation {
    id: number;
    patient_checkin: PatientCheckin;
    chief_complaint: string;
    subjective_notes?: string;
    created_at: string;
}

interface HospitalInfo {
    name: string;
    logo_url?: string;
}

interface Props {
    consultation: Consultation;
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

export default function ConsultationShow({ consultation, labOrders, hospital }: Props) {
    const patient = consultation.patient_checkin.patient;
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
                    title: `Consultation #${consultation.id}`,
                    href: lab.consultations.show.url({
                        consultation: consultation.id,
                    }),
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
                            size="icon"
                            onClick={() => router.visit(lab.index.url())}
                        >
                            <ArrowLeft className="h-5 w-5" />
                        </Button>
                        <div>
                            <h1 className="text-2xl font-bold">
                                Lab Tests for {patient.first_name}{' '}
                                {patient.last_name}
                            </h1>
                            <p className="text-muted-foreground">
                                {labOrders.length} test
                                {labOrders.length !== 1 ? 's' : ''} ordered
                                {patient.active_insurance && (
                                    <span className="ml-2">
                                        •{' '}
                                        <Badge variant="outline">
                                            {
                                                patient.active_insurance.plan
                                                    .provider.name
                                            }
                                        </Badge>
                                    </span>
                                )}
                            </p>
                        </div>
                    </div>
                    {/* Status Summary and Print Button */}
                    <div className="flex flex-wrap items-center gap-2">
                        {Object.entries(statusCounts).map(([status, count]) => {
                            const config =
                                statusConfig[
                                status as keyof typeof statusConfig
                                ];
                            if (!config) return null;
                            return (
                                <Badge
                                    key={status}
                                    variant="outline"
                                    className={config.className}
                                >
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
