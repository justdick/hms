import { ReviewPrescriptionsModal } from '@/components/Pharmacy/ReviewPrescriptionsModal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { AlertCircle, ArrowLeft, Hash, Phone, Pill, User } from 'lucide-react';
import { useState } from 'react';
import { DataTable } from './dispensing-data-table';

interface Patient {
    id: number;
    patient_number: string;
    first_name: string;
    last_name: string;
    middle_name?: string;
    phone_number?: string;
    date_of_birth?: string;
    gender?: string;
    full_name: string;
}

interface Drug {
    id: number;
    name: string;
    form: string;
    unit_type: string;
}

interface Prescription {
    id: number;
    consultation_id: number;
    drug_id?: number;
    drug?: Drug;
    medication_name: string;
    dosage: string;
    frequency: string;
    duration: string;
    quantity: number;
    dosage_form?: string;
    instructions?: string;
    status: 'prescribed' | 'dispensed' | 'cancelled';
    created_at: string;
    updated_at: string;
}

interface StockStatus {
    available: boolean;
    in_stock: number;
    shortage: number;
    quantity_pending?: boolean; // For injections where pharmacist determines quantity
}

interface PrescriptionData {
    prescription: Prescription;
    stock_status: StockStatus;
    can_dispense_full: boolean;
    max_dispensable: number;
}

interface Props {
    patient: Patient;
    prescriptions: Prescription[];
    prescriptionsData?: PrescriptionData[];
}

const statusConfig = {
    prescribed: {
        label: 'Pending',
        className:
            'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
    },
    dispensed: {
        label: 'Dispensed',
        className:
            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    },
    cancelled: {
        label: 'Cancelled',
        className: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
    },
};

const prescriptionColumns: ColumnDef<Prescription>[] = [
    {
        accessorKey: 'medication_name',
        header: 'Medication',
        cell: ({ row }) => {
            const prescription = row.original;
            const drugName =
                prescription.drug?.name || prescription.medication_name;
            return (
                <div className="space-y-1">
                    <div className="flex items-center gap-2 font-medium">
                        <Pill className="h-4 w-4 text-blue-600" />
                        {drugName}
                    </div>
                    {prescription.drug && (
                        <div className="text-sm text-muted-foreground">
                            {prescription.drug.form} • {prescription.dosage}
                        </div>
                    )}
                </div>
            );
        },
    },
    {
        id: 'prescription_details',
        header: 'Details',
        cell: ({ row }) => {
            const prescription = row.original;
            return (
                <div className="space-y-1 text-sm">
                    <div className="flex items-center gap-2">
                        <span className="font-medium">Qty:</span>
                        <Badge variant="outline" className="text-xs">
                            {prescription.quantity}{' '}
                            {prescription.drug?.unit_type || 'units'}
                        </Badge>
                    </div>
                    <div className="text-muted-foreground">
                        <div>{prescription.frequency}</div>
                        <div>Duration: {prescription.duration}</div>
                    </div>
                </div>
            );
        },
    },
    {
        accessorKey: 'instructions',
        header: 'Instructions',
        cell: ({ row }) => {
            const instructions = row.getValue('instructions') as
                | string
                | undefined;
            if (!instructions) {
                return (
                    <span className="text-sm text-muted-foreground">None</span>
                );
            }
            return (
                <div className="flex max-w-xs items-start gap-2">
                    <AlertCircle className="mt-0.5 h-4 w-4 flex-shrink-0 text-blue-600" />
                    <span className="text-sm">{instructions}</span>
                </div>
            );
        },
    },
    {
        accessorKey: 'status',
        header: 'Status',
        cell: ({ row }) => {
            const status = row.getValue('status') as Prescription['status'];
            const config = statusConfig[status];
            return <Badge className={config.className}>{config.label}</Badge>;
        },
    },
];

export default function DispensingShow({
    patient,
    prescriptions,
    prescriptionsData,
}: Props) {
    const pendingPrescriptions = prescriptions.filter(
        (p) => p.status === 'prescribed',
    );
    const [reviewModalOpen, setReviewModalOpen] = useState(false);

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Pharmacy', href: '/pharmacy' },
                { title: 'Dispensing', href: '/pharmacy/dispensing' },
                {
                    title: patient.full_name,
                    href: `/pharmacy/dispensing/patients/${patient.id}`,
                },
            ]}
        >
            <Head title={`Dispense - ${patient.full_name}`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="ghost" size="sm" asChild>
                            <Link href="/pharmacy/dispensing">
                                <ArrowLeft className="mr-1 h-4 w-4" />
                                Back to Search
                            </Link>
                        </Button>
                        <div>
                            <h1 className="flex items-center gap-2 text-2xl font-bold">
                                <Pill className="h-6 w-6" />
                                Dispense Medications
                            </h1>
                            <p className="text-muted-foreground">
                                Review and dispense prescriptions for{' '}
                                {patient.full_name}
                            </p>
                        </div>
                    </div>
                </div>

                {/* Patient Information Card */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <User className="h-5 w-5" />
                            Patient Information
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div className="flex items-start gap-3">
                                <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-primary/10">
                                    <User className="h-5 w-5 text-primary" />
                                </div>
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        Patient Name
                                    </p>
                                    <p className="font-medium">
                                        {patient.full_name}
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-start gap-3">
                                <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-primary/10">
                                    <Hash className="h-5 w-5 text-primary" />
                                </div>
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        MRN
                                    </p>
                                    <p className="font-medium">
                                        {patient.patient_number}
                                    </p>
                                </div>
                            </div>
                            {patient.phone_number && (
                                <div className="flex items-start gap-3">
                                    <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-primary/10">
                                        <Phone className="h-5 w-5 text-primary" />
                                    </div>
                                    <div>
                                        <p className="text-sm text-muted-foreground">
                                            Phone
                                        </p>
                                        <p className="font-medium">
                                            {patient.phone_number}
                                        </p>
                                    </div>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Prescriptions Data Table */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center justify-between">
                            <span className="flex items-center gap-2">
                                <Pill className="h-5 w-5" />
                                Pending Prescriptions (
                                {pendingPrescriptions.length})
                            </span>
                            {pendingPrescriptions.length > 0 && (
                                <Button
                                    onClick={() => setReviewModalOpen(true)}
                                >
                                    Start Review
                                </Button>
                            )}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {pendingPrescriptions.length > 0 ? (
                            <DataTable
                                columns={prescriptionColumns}
                                data={pendingPrescriptions}
                            />
                        ) : (
                            <div className="py-12 text-center">
                                <Pill className="mx-auto mb-4 h-16 w-16 text-muted-foreground opacity-50" />
                                <h3 className="mb-2 text-lg font-medium">
                                    No Pending Prescriptions
                                </h3>
                                <p className="mb-4 text-muted-foreground">
                                    This patient has no pending prescriptions to
                                    dispense.
                                </p>
                                <Button variant="outline" asChild>
                                    <Link href="/pharmacy/dispensing">
                                        Back to Search
                                    </Link>
                                </Button>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Review Modal */}
                {prescriptionsData && (
                    <ReviewPrescriptionsModal
                        open={reviewModalOpen}
                        onOpenChange={setReviewModalOpen}
                        patientId={patient.id}
                        prescriptionsData={prescriptionsData}
                    />
                )}
            </div>
        </AppLayout>
    );
}
