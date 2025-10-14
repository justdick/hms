import { PrescriptionStatusBadge } from '@/components/Pharmacy/PrescriptionStatusBadge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    Calendar,
    CheckCircle,
    Pill,
    User,
} from 'lucide-react';
import { useState } from 'react';

interface Drug {
    id: number;
    name: string;
    form: string;
    unit_type: string;
    strength?: string;
}

interface Prescription {
    id: number;
    drug_id: number;
    drug: Drug;
    quantity: number;
    quantity_to_dispense: number;
    dosage: string;
    frequency: string;
    duration: string;
    status: string;
    reviewed_by: number;
    reviewed_at: string;
    dispensing_notes?: string;
}

interface Charge {
    id: number;
    amount: number;
    status: 'pending' | 'paid' | 'voided';
}

interface Batch {
    id: number;
    batch_number: string;
    expiry_date: string;
    available_quantity: number;
}

interface PrescriptionData {
    prescription: Prescription;
    payment_status: string;
    can_dispense: boolean;
    available_batches: Batch[];
}

interface Patient {
    id: number;
    patient_number: string;
    full_name: string;
}

interface Props {
    patient: Patient;
    checkin: {
        id: number;
        created_at: string;
    };
    prescriptionsData: PrescriptionData[];
}

export default function Dispense({
    patient,
    checkin,
    prescriptionsData,
}: Props) {
    const [processing, setProcessing] = useState<number | null>(null);

    const handleDispense = (prescriptionId: number) => {
        if (!confirm('Are you sure you want to dispense this medication?')) {
            return;
        }

        setProcessing(prescriptionId);

        router.post(
            `/pharmacy/prescriptions/${prescriptionId}/dispense`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setProcessing(null),
            },
        );
    };

    const allPaid = prescriptionsData.every((pd) => pd.can_dispense);
    const anyCanDispense = prescriptionsData.some((pd) => pd.can_dispense);

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Pharmacy', href: '/pharmacy' },
                { title: 'Dispensing', href: '/pharmacy/dispensing' },
                { title: 'Dispense Medications' },
            ]}
        >
            <Head title={`Dispense Medications - ${patient.full_name}`} />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="ghost" size="sm" asChild>
                            <Link href="/pharmacy/dispensing">
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-2xl font-bold">
                                Dispense Medications
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                Patient: {patient.full_name} (
                                {patient.patient_number})
                            </p>
                        </div>
                    </div>
                </div>

                {!allPaid && (
                    <Alert variant="destructive">
                        <AlertTriangle className="h-4 w-4" />
                        <AlertDescription>
                            {anyCanDispense
                                ? 'Some medications are not yet paid for. Please ensure payment before dispensing.'
                                : 'Payment required before dispensing. Please direct patient to billing/cashier.'}
                        </AlertDescription>
                    </Alert>
                )}

                {allPaid && (
                    <Alert className="border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950/20">
                        <CheckCircle className="h-4 w-4 text-green-600 dark:text-green-400" />
                        <AlertDescription className="text-green-600 dark:text-green-400">
                            All medications have been paid for. You can proceed
                            with dispensing.
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="space-y-4 lg:col-span-2">
                        {prescriptionsData.map((pd) => (
                            <Card key={pd.prescription.id}>
                                <CardHeader>
                                    <div className="flex items-start justify-between">
                                        <div className="flex items-start gap-3">
                                            <div className="rounded-lg bg-primary/10 p-2">
                                                <Pill className="h-5 w-5 text-primary" />
                                            </div>
                                            <div>
                                                <CardTitle className="text-lg">
                                                    {pd.prescription.drug.name}
                                                    {pd.prescription.drug
                                                        .strength && (
                                                        <span className="ml-2 text-sm font-normal text-muted-foreground">
                                                            {
                                                                pd.prescription
                                                                    .drug
                                                                    .strength
                                                            }
                                                        </span>
                                                    )}
                                                </CardTitle>
                                                <CardDescription>
                                                    {pd.prescription.dosage} -{' '}
                                                    {pd.prescription.frequency}{' '}
                                                    - {pd.prescription.duration}
                                                </CardDescription>
                                            </div>
                                        </div>
                                        <PrescriptionStatusBadge
                                            status={
                                                pd.prescription.status as any
                                            }
                                        />
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid gap-4 md:grid-cols-3">
                                        <div>
                                            <p className="text-xs text-muted-foreground">
                                                Quantity to Dispense
                                            </p>
                                            <p className="text-lg font-semibold">
                                                {pd.prescription
                                                    .quantity_to_dispense ||
                                                    pd.prescription
                                                        .quantity}{' '}
                                                {pd.prescription.drug.unit_type}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-xs text-muted-foreground">
                                                Payment Status
                                            </p>
                                            <Badge
                                                variant={
                                                    pd.can_dispense
                                                        ? 'default'
                                                        : 'destructive'
                                                }
                                                className={
                                                    pd.can_dispense
                                                        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                        : ''
                                                }
                                            >
                                                {pd.payment_status === 'paid'
                                                    ? 'Paid'
                                                    : 'Pending'}
                                            </Badge>
                                        </div>
                                        <div>
                                            <p className="text-xs text-muted-foreground">
                                                Available Batches
                                            </p>
                                            <p className="text-lg font-semibold">
                                                {pd.available_batches.length}
                                            </p>
                                        </div>
                                    </div>

                                    {pd.prescription.dispensing_notes && (
                                        <Alert>
                                            <AlertDescription>
                                                <strong>Review Notes:</strong>{' '}
                                                {
                                                    pd.prescription
                                                        .dispensing_notes
                                                }
                                            </AlertDescription>
                                        </Alert>
                                    )}

                                    {pd.available_batches.length > 0 && (
                                        <div className="space-y-2">
                                            <p className="text-sm font-medium">
                                                Available Batches:
                                            </p>
                                            <div className="space-y-1">
                                                {pd.available_batches.map(
                                                    (batch) => (
                                                        <div
                                                            key={batch.id}
                                                            className="flex items-center justify-between rounded-lg border p-2 text-sm"
                                                        >
                                                            <div>
                                                                <span className="font-medium">
                                                                    {
                                                                        batch.batch_number
                                                                    }
                                                                </span>
                                                                <span className="ml-2 text-muted-foreground">
                                                                    Qty:{' '}
                                                                    {
                                                                        batch.available_quantity
                                                                    }
                                                                </span>
                                                            </div>
                                                            <span className="text-xs text-muted-foreground">
                                                                Exp:{' '}
                                                                {new Date(
                                                                    batch.expiry_date,
                                                                ).toLocaleDateString()}
                                                            </span>
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                        </div>
                                    )}

                                    <div className="flex justify-end">
                                        <Button
                                            onClick={() =>
                                                handleDispense(
                                                    pd.prescription.id,
                                                )
                                            }
                                            disabled={
                                                !pd.can_dispense ||
                                                processing ===
                                                    pd.prescription.id
                                            }
                                        >
                                            {processing === pd.prescription.id
                                                ? 'Dispensing...'
                                                : 'Dispense'}
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>

                    <div className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Patient Information
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div className="flex items-center gap-2">
                                    <User className="h-4 w-4 text-muted-foreground" />
                                    <div>
                                        <p className="text-sm font-medium">
                                            {patient.full_name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {patient.patient_number}
                                        </p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Calendar className="h-4 w-4 text-muted-foreground" />
                                    <div>
                                        <p className="text-sm font-medium">
                                            Visit Date
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {new Date(
                                                checkin.created_at,
                                            ).toLocaleDateString()}
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Summary
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">
                                        Total Medications:
                                    </span>
                                    <span className="font-semibold">
                                        {prescriptionsData.length}
                                    </span>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">
                                        Paid:
                                    </span>
                                    <span className="font-semibold text-green-600 dark:text-green-400">
                                        {
                                            prescriptionsData.filter(
                                                (pd) => pd.can_dispense,
                                            ).length
                                        }
                                    </span>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">
                                        Pending Payment:
                                    </span>
                                    <span className="font-semibold text-yellow-600 dark:text-yellow-400">
                                        {
                                            prescriptionsData.filter(
                                                (pd) => !pd.can_dispense,
                                            ).length
                                        }
                                    </span>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
