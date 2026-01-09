import { InlineCoverageDisplay } from '@/components/Insurance/InlineCoverageDisplay';
import { InsuranceCoverageBadge } from '@/components/Insurance/InsuranceCoverageBadge';
import { PrescriptionStatusBadge } from '@/components/Pharmacy/PrescriptionStatusBadge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { router } from '@inertiajs/react';
import { AlertTriangle, CheckCircle, Package, Pill, User } from 'lucide-react';
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
    dose_quantity?: string;
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

interface InsuranceCoverage {
    has_insurance: boolean;
    coverage_percentage: number;
    insurance_amount: number;
    patient_amount: number;
    total_amount: number;
}

interface PrescriptionData {
    prescription: Prescription;
    payment_status: string;
    can_dispense: boolean;
    available_batches: Batch[];
    insurance_coverage: InsuranceCoverage | null;
}

interface Patient {
    id: number;
    patient_number: string;
    full_name: string;
}

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    patient: Patient;
    prescriptionsData: PrescriptionData[];
    suppliesData?: any[]; // TODO: Add proper type when supplies dispensing is implemented
}

export function DispenseModal({
    open,
    onOpenChange,
    patient,
    prescriptionsData,
    suppliesData,
}: Props) {
    const [dispensing, setDispensing] = useState<Set<number>>(new Set());

    const allPaid = prescriptionsData.every((pd) => pd.can_dispense);
    const anyCanDispense = prescriptionsData.some((pd) => pd.can_dispense);
    const allDispensed = prescriptionsData.every(
        (pd) => pd.prescription.status === 'dispensed',
    );

    const handleDispense = (prescriptionId: number) => {
        setDispensing((prev) => new Set(prev).add(prescriptionId));

        router.post(
            `/pharmacy/prescriptions/${prescriptionId}/dispense`,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => {
                    setDispensing((prev) => {
                        const next = new Set(prev);
                        next.delete(prescriptionId);
                        return next;
                    });
                },
            },
        );
    };

    const handleDispenseAll = () => {
        const dispensable = prescriptionsData.filter((pd) => pd.can_dispense);
        dispensable.forEach((pd) => handleDispense(pd.prescription.id));
    };

    const handleComplete = () => {
        // Get the patient's latest checkin ID for billing
        router.visit(`/billing/patients/${patient.id}`);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] w-[95vw] max-w-[95vw] sm:max-w-[95vw]">
                <DialogHeader>
                    <DialogTitle>Dispense Medications</DialogTitle>
                    <DialogDescription>
                        <div className="flex items-center gap-2">
                            <User className="h-4 w-4" />
                            {patient.full_name} ({patient.patient_number})
                        </div>
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    {/* Payment Status Alert */}
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

                    {allPaid && !allDispensed && (
                        <Alert className="border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950/20">
                            <CheckCircle className="h-4 w-4 text-green-600 dark:text-green-400" />
                            <AlertDescription className="text-green-600 dark:text-green-400">
                                All medications have been paid for. You can
                                proceed with dispensing.
                            </AlertDescription>
                        </Alert>
                    )}

                    {/* Prescriptions Table */}
                    <ScrollArea className="h-[calc(90vh-20rem)]">
                        <div className="rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow className="bg-muted/50 dark:bg-gray-900/50">
                                        <TableHead className="w-[250px]">
                                            Drug
                                        </TableHead>
                                        <TableHead className="w-[200px]">
                                            Instructions
                                        </TableHead>
                                        <TableHead className="w-[100px]">
                                            Quantity
                                        </TableHead>
                                        <TableHead className="w-[150px]">
                                            Amount
                                        </TableHead>
                                        <TableHead className="w-[120px]">
                                            Payment
                                        </TableHead>
                                        <TableHead className="w-[120px]">
                                            Batches
                                        </TableHead>
                                        <TableHead className="w-[100px]">
                                            Status
                                        </TableHead>
                                        <TableHead className="w-[120px]">
                                            Action
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {prescriptionsData.map((pd) => (
                                        <TableRow key={pd.prescription.id}>
                                            {/* Drug Name */}
                                            <TableCell className="font-medium">
                                                <div>
                                                    <div className="flex items-center gap-2">
                                                        <Pill className="h-4 w-4 text-primary" />
                                                        <span className="text-sm font-semibold">
                                                            {
                                                                pd.prescription
                                                                    .drug.name
                                                            }
                                                        </span>
                                                    </div>
                                                    {pd.prescription.drug
                                                        .strength && (
                                                        <div className="text-xs text-muted-foreground">
                                                            {
                                                                pd.prescription
                                                                    .drug
                                                                    .strength
                                                            }
                                                        </div>
                                                    )}
                                                    <div className="mt-1 text-xs text-muted-foreground">
                                                        {
                                                            pd.prescription.drug
                                                                .form
                                                        }
                                                    </div>
                                                </div>
                                            </TableCell>

                                            {/* Instructions */}
                                            <TableCell>
                                                <div className="space-y-0.5 text-sm">
                                                    {pd.prescription
                                                        .dose_quantity && (
                                                        <div className="font-medium">
                                                            {
                                                                pd.prescription
                                                                    .dose_quantity
                                                            }{' '}
                                                            {pd.prescription
                                                                .drug
                                                                .unit_type ===
                                                            'piece'
                                                                ? pd
                                                                      .prescription
                                                                      .drug.form
                                                                : pd
                                                                        .prescription
                                                                        .drug
                                                                        .unit_type ===
                                                                        'bottle' ||
                                                                    pd
                                                                        .prescription
                                                                        .drug
                                                                        .unit_type ===
                                                                        'vial'
                                                                  ? 'ml'
                                                                  : pd
                                                                        .prescription
                                                                        .drug
                                                                        .unit_type}
                                                            {' per dose'}
                                                        </div>
                                                    )}
                                                    <div className="text-xs text-muted-foreground">
                                                        {
                                                            pd.prescription
                                                                .frequency
                                                        }
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {
                                                            pd.prescription
                                                                .duration
                                                        }
                                                    </div>
                                                </div>
                                            </TableCell>

                                            {/* Quantity */}
                                            <TableCell>
                                                <div className="text-sm font-semibold">
                                                    {pd.prescription
                                                        .quantity_to_dispense ||
                                                        pd.prescription
                                                            .quantity}
                                                    <span className="ml-1 text-xs text-muted-foreground">
                                                        {
                                                            pd.prescription.drug
                                                                .unit_type
                                                        }
                                                    </span>
                                                </div>
                                            </TableCell>

                                            {/* Amount with Insurance Coverage */}
                                            <TableCell>
                                                <div className="space-y-1.5">
                                                    {pd.insurance_coverage ? (
                                                        <>
                                                            <InlineCoverageDisplay
                                                                isInsuranceClaim={
                                                                    pd
                                                                        .insurance_coverage
                                                                        .has_insurance
                                                                }
                                                                insuranceCoveredAmount={
                                                                    pd
                                                                        .insurance_coverage
                                                                        .insurance_amount
                                                                }
                                                                patientCopayAmount={
                                                                    pd
                                                                        .insurance_coverage
                                                                        .patient_amount
                                                                }
                                                                amount={
                                                                    pd
                                                                        .insurance_coverage
                                                                        .total_amount
                                                                }
                                                                compact
                                                            />
                                                            {pd
                                                                .insurance_coverage
                                                                .has_insurance && (
                                                                <InsuranceCoverageBadge
                                                                    isInsuranceClaim={
                                                                        pd
                                                                            .insurance_coverage
                                                                            .has_insurance
                                                                    }
                                                                    insuranceCoveredAmount={
                                                                        pd
                                                                            .insurance_coverage
                                                                            .insurance_amount
                                                                    }
                                                                    patientCopayAmount={
                                                                        pd
                                                                            .insurance_coverage
                                                                            .patient_amount
                                                                    }
                                                                    amount={
                                                                        pd
                                                                            .insurance_coverage
                                                                            .total_amount
                                                                    }
                                                                    className="text-xs"
                                                                />
                                                            )}
                                                        </>
                                                    ) : (
                                                        <div className="text-sm text-muted-foreground">
                                                            N/A
                                                        </div>
                                                    )}
                                                </div>
                                            </TableCell>

                                            {/* Payment Status */}
                                            <TableCell>
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
                                                    {pd.payment_status ===
                                                    'paid'
                                                        ? 'Paid'
                                                        : 'Pending'}
                                                </Badge>
                                            </TableCell>

                                            {/* Batches */}
                                            <TableCell>
                                                <div className="flex items-center gap-1">
                                                    <Package className="h-4 w-4 text-muted-foreground" />
                                                    <span className="text-sm">
                                                        {
                                                            pd.available_batches
                                                                .length
                                                        }{' '}
                                                        available
                                                    </span>
                                                </div>
                                                {pd.available_batches.length >
                                                    0 && (
                                                    <div className="mt-1 text-xs text-muted-foreground">
                                                        Latest exp:{' '}
                                                        {new Date(
                                                            pd.available_batches[0].expiry_date,
                                                        ).toLocaleDateString()}
                                                    </div>
                                                )}
                                            </TableCell>

                                            {/* Status */}
                                            <TableCell>
                                                <PrescriptionStatusBadge
                                                    status={
                                                        pd.prescription
                                                            .status as any
                                                    }
                                                />
                                            </TableCell>

                                            {/* Action */}
                                            <TableCell>
                                                <Button
                                                    onClick={() =>
                                                        handleDispense(
                                                            pd.prescription.id,
                                                        )
                                                    }
                                                    disabled={
                                                        !pd.can_dispense ||
                                                        dispensing.has(
                                                            pd.prescription.id,
                                                        ) ||
                                                        pd.prescription
                                                            .status ===
                                                            'dispensed'
                                                    }
                                                    size="sm"
                                                >
                                                    {dispensing.has(
                                                        pd.prescription.id,
                                                    )
                                                        ? 'Dispensing...'
                                                        : pd.prescription
                                                                .status ===
                                                            'dispensed'
                                                          ? 'Dispensed'
                                                          : 'Dispense'}
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </ScrollArea>

                    {/* Summary Card */}
                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <div className="flex gap-8">
                                    <div>
                                        <p className="text-sm text-muted-foreground">
                                            Total Items
                                        </p>
                                        <p className="text-2xl font-bold">
                                            {prescriptionsData.length}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-muted-foreground">
                                            Paid
                                        </p>
                                        <p className="text-2xl font-bold text-green-600 dark:text-green-400">
                                            {
                                                prescriptionsData.filter(
                                                    (pd) => pd.can_dispense,
                                                ).length
                                            }
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-muted-foreground">
                                            Dispensed
                                        </p>
                                        <p className="text-2xl font-bold text-blue-600 dark:text-blue-400">
                                            {
                                                prescriptionsData.filter(
                                                    (pd) =>
                                                        pd.prescription
                                                            .status ===
                                                        'dispensed',
                                                ).length
                                            }
                                        </p>
                                    </div>
                                </div>
                                <div className="flex gap-2">
                                    {anyCanDispense && !allDispensed && (
                                        <Button
                                            onClick={handleDispenseAll}
                                            disabled={dispensing.size > 0}
                                        >
                                            Dispense All Paid
                                        </Button>
                                    )}
                                    {allDispensed && (
                                        <Button
                                            onClick={handleComplete}
                                            className="bg-green-600 hover:bg-green-700"
                                        >
                                            Complete & Go to Billing
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </DialogContent>
        </Dialog>
    );
}
