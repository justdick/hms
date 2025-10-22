import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import lab from '@/routes/lab';
import { Head, router } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    CheckCircle,
    FileText,
    TestTube,
    Timer,
} from 'lucide-react';
import {
    ConsultationTest,
    consultationTestColumns,
} from './consultation-tests-columns';
import { LabOrdersDataTable } from './lab-orders-data-table';

interface Patient {
    id: number;
    patient_number: string;
    first_name: string;
    last_name: string;
    phone_number?: string;
    date_of_birth: string;
    gender: string;
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

interface Props {
    wardRound: WardRound;
    labOrders: ConsultationTest[];
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

export default function WardRoundShow({ wardRound, labOrders }: Props) {
    const patient = wardRound.patient_admission.patient;
    const ward = wardRound.patient_admission.ward;
    const statusCounts = labOrders.reduce(
        (acc, order) => {
            acc[order.status] = (acc[order.status] || 0) + 1;
            return acc;
        },
        {} as Record<string, number>,
    );

    return (
        <AppLayout
            breadcrumbs={[
                { label: 'Laboratory', href: lab.index.url() },
                {
                    label: `Ward Round - Day ${wardRound.day_number}`,
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
                            <p className="text-muted-foreground">
                                Ward Round Day {wardRound.day_number} •{' '}
                                {ward.name} • {labOrders.length} test
                                {labOrders.length !== 1 ? 's' : ''} ordered
                            </p>
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
        </AppLayout>
    );
}
