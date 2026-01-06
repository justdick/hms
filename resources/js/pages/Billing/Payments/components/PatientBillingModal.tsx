import { InsuranceCoverageBadge } from '@/components/Insurance/InsuranceCoverageBadge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { router } from '@inertiajs/react';
import {
    Banknote,
    CheckCircle2,
    ChevronDown,
    ChevronRight,
    CreditCard,
    Eye,
    Loader2,
    Phone,
    Printer,
    Receipt,
    Shield,
    ShieldOff,
    Smartphone,
    User,
    Wallet,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { ChangeCalculator } from './ChangeCalculator';

interface Patient {
    id: number;
    first_name: string;
    last_name: string;
    patient_number: string;
    phone_number: string;
    is_insured?: boolean;
    insurance_plan?: {
        name: string;
        provider: string;
    } | null;
}

interface Department {
    id: number;
    name: string;
}

interface ChargeItem {
    id: number;
    description: string;
    amount: number;
    is_insurance_claim: boolean;
    insurance_covered_amount: number;
    patient_copay_amount: number;
    service_type: string;
    charged_at: string;
}

interface Visit {
    checkin_id: number;
    department: Department;
    checked_in_at: string;
    total_pending: number;
    patient_copay: number;
    insurance_covered: number;
    charges_count: number;
    charges: ChargeItem[];
}

interface PatientSearchResult {
    patient_id: number;
    patient: Patient;
    total_pending: number;
    total_patient_owes: number;
    total_insurance_covered: number;
    total_charges: number;
    visits_with_charges: number;
    visits: Visit[];
}

interface BillingPermissions {
    canProcessPayment: boolean;
    canWaiveCharges: boolean;
    canAdjustCharges: boolean;
    canOverrideServices: boolean;
    canCancelCharges: boolean;
}

interface PatientBillingModalProps {
    isOpen: boolean;
    onClose: () => void;
    patient: PatientSearchResult | null;
    permissions: BillingPermissions;
    formatCurrency: (amount: number) => string;
    onWaiveCharge?: (chargeId: number) => void;
    onAdjustCharge?: (chargeId: number) => void;
    onPaymentSuccess?: (chargeIds: number[]) => void;
    onPrintReceipt?: (chargeIds: number[]) => void;
    onViewReceipt?: (chargeIds: number[]) => void;
}

type ModalStep = 'charges' | 'payment' | 'success';

/**
 * PatientBillingModal Component
 *
 * Consolidated billing modal with:
 * - Patient info header
 * - Accordion-style visits with charges
 * - Charge selection with waive/adjust actions
 * - Payment processing
 * - Success state with receipt option
 */
export function PatientBillingModal({
    isOpen,
    onClose,
    patient,
    permissions,
    formatCurrency,
    onWaiveCharge,
    onAdjustCharge,
    onPaymentSuccess,
    onPrintReceipt,
    onViewReceipt,
}: PatientBillingModalProps) {
    const [step, setStep] = useState<ModalStep>('charges');
    const [expandedVisits, setExpandedVisits] = useState<Set<number>>(
        new Set(),
    );
    const [selectedCharges, setSelectedCharges] = useState<Set<number>>(
        new Set(),
    );
    const [paymentMethod, setPaymentMethod] = useState('cash');
    const [amountTendered, setAmountTendered] = useState(0);
    const [notes, setNotes] = useState('');
    const [isProcessing, setIsProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    // Get all charges from all visits
    const allCharges = patient?.visits.flatMap((v) => v.charges) ?? [];

    // Get selected charges
    const selectedChargesList = allCharges.filter((c) =>
        selectedCharges.has(c.id),
    );

    // Calculate totals for selected charges (parse strings to numbers)
    const totalAmount = selectedChargesList.reduce(
        (sum, c) => sum + Number(c.amount),
        0,
    );
    const totalPatientOwes = selectedChargesList.reduce(
        (sum, c) =>
            sum +
            Number(c.is_insurance_claim ? c.patient_copay_amount : c.amount),
        0,
    );
    const totalInsuranceCovered = selectedChargesList.reduce(
        (sum, c) =>
            sum + Number(c.is_insurance_claim ? c.insurance_covered_amount : 0),
        0,
    );

    // Reset state when modal opens or patient changes
    useEffect(() => {
        if (isOpen && patient) {
            setStep('charges');
            // Expand first visit by default
            if (patient.visits.length > 0) {
                setExpandedVisits(new Set([patient.visits[0].checkin_id]));
            }
            // Select all charges by default
            const allChargeIds = patient.visits.flatMap((v) =>
                v.charges.map((c) => c.id),
            );
            setSelectedCharges(new Set(allChargeIds));
            setPaymentMethod('cash');
            setNotes('');
            setError(null);
        }
    }, [isOpen, patient]);

    // Update amount tendered when selection changes
    useEffect(() => {
        setAmountTendered(totalPatientOwes);
    }, [totalPatientOwes]);

    const toggleVisit = (checkinId: number) => {
        setExpandedVisits((prev) => {
            const newSet = new Set(prev);
            if (newSet.has(checkinId)) {
                newSet.delete(checkinId);
            } else {
                newSet.add(checkinId);
            }
            return newSet;
        });
    };

    const toggleCharge = (chargeId: number) => {
        setSelectedCharges((prev) => {
            const newSet = new Set(prev);
            if (newSet.has(chargeId)) {
                newSet.delete(chargeId);
            } else {
                newSet.add(chargeId);
            }
            return newSet;
        });
    };

    const toggleAllCharges = () => {
        if (selectedCharges.size === allCharges.length) {
            setSelectedCharges(new Set());
        } else {
            setSelectedCharges(new Set(allCharges.map((c) => c.id)));
        }
    };

    const handleProceedToPayment = () => {
        if (selectedCharges.size === 0) {
            setError('Please select at least one charge');
            return;
        }
        setError(null);
        setStep('payment');
    };

    const handleProcessPayment = () => {
        if (paymentMethod === 'cash' && amountTendered < totalPatientOwes) {
            setError('Amount tendered is less than amount due');
            return;
        }

        if (!patient) return;

        setIsProcessing(true);
        setError(null);

        const checkinId = patient.visits[0].checkin_id;
        const chargeIds = Array.from(selectedCharges);

        router.post(
            `/billing/checkin/${checkinId}/payment`,
            {
                charges: chargeIds,
                payment_method: paymentMethod,
                amount_paid: totalPatientOwes,
                notes: notes || undefined,
            },
            {
                onSuccess: () => {
                    setIsProcessing(false);
                    setStep('success');
                },
                onError: (errors) => {
                    setIsProcessing(false);
                    setError(
                        Object.values(errors).flat().join(', ') ||
                            'Payment processing failed',
                    );
                },
            },
        );
    };

    const handleClose = () => {
        if (step === 'success') {
            onPaymentSuccess?.(Array.from(selectedCharges));
        }
        onClose();
    };

    const handlePrintReceipt = () => {
        const chargeIds = Array.from(selectedCharges);
        onPrintReceipt?.(chargeIds);
    };

    const handleViewReceipt = () => {
        const chargeIds = Array.from(selectedCharges);
        onViewReceipt?.(chargeIds);
    };

    const formatServiceType = (serviceType: string) => {
        return serviceType
            .split('_')
            .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
            .join(' ');
    };

    const formatDateTime = (dateString: string) => {
        const date = new Date(dateString);
        return date.toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
        });
    };

    if (!patient) return null;

    return (
        <Dialog open={isOpen} onOpenChange={handleClose}>
            <DialogContent className="flex max-h-[90vh] max-w-2xl flex-col overflow-hidden">
                <DialogHeader className="flex-shrink-0">
                    <DialogTitle>
                        {step === 'charges' && 'Patient Billing'}
                        {step === 'payment' && 'Process Payment'}
                        {step === 'success' && 'Payment Successful'}
                    </DialogTitle>
                    <DialogDescription>
                        {step === 'charges' &&
                            'Select charges and process payment'}
                        {step === 'payment' &&
                            'Complete the payment transaction'}
                        {step === 'success' &&
                            'Payment has been processed successfully'}
                    </DialogDescription>
                </DialogHeader>

                <div className="flex-1 overflow-y-auto py-4">
                    {/* Charges Selection Step */}
                    {step === 'charges' && (
                        <div className="space-y-4">
                            {/* Patient Info Header */}
                            <div className="flex items-start justify-between rounded-lg border bg-muted/30 p-4">
                                <div className="flex items-start gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10">
                                        <User className="h-5 w-5 text-primary" />
                                    </div>
                                    <div>
                                        <h3 className="font-semibold">
                                            {patient.patient.first_name}{' '}
                                            {patient.patient.last_name}
                                        </h3>
                                        <p className="text-sm text-muted-foreground">
                                            {patient.patient.patient_number}
                                        </p>
                                        {patient.patient.phone_number && (
                                            <p className="flex items-center gap-1 text-sm text-muted-foreground">
                                                <Phone className="h-3 w-3" />
                                                {patient.patient.phone_number}
                                            </p>
                                        )}
                                    </div>
                                </div>
                                {/* Insurance Status */}
                                <div className="text-right">
                                    {patient.patient.is_insured ? (
                                        <div className="inline-flex items-center gap-1.5 rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                            <Shield className="h-3 w-3" />
                                            Insured
                                        </div>
                                    ) : (
                                        <div className="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                            <ShieldOff className="h-3 w-3" />
                                            Not Insured
                                        </div>
                                    )}
                                    {patient.patient.insurance_plan && (
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {patient.patient.insurance_plan.provider} - {patient.patient.insurance_plan.name}
                                        </p>
                                    )}
                                </div>
                            </div>

                            {/* Summary Cards */}
                            <div className="grid grid-cols-2 gap-3">
                                <div className="rounded-lg border border-orange-200 bg-orange-50 p-3 dark:border-orange-800 dark:bg-orange-950/30">
                                    <p className="text-xs text-muted-foreground">
                                        Patient Owes
                                    </p>
                                    <p className="text-xl font-bold text-orange-600">
                                        {formatCurrency(totalPatientOwes)}
                                    </p>
                                </div>
                                {totalInsuranceCovered > 0 && (
                                    <div className="rounded-lg border border-green-200 bg-green-50 p-3 dark:border-green-800 dark:bg-green-950/30">
                                        <p className="text-xs text-muted-foreground">
                                            Insurance Covers
                                        </p>
                                        <p className="text-xl font-bold text-green-600">
                                            {formatCurrency(
                                                totalInsuranceCovered,
                                            )}
                                        </p>
                                    </div>
                                )}
                            </div>

                            {/* Select All */}
                            <div className="flex items-center justify-between border-b pb-2">
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="select-all"
                                        checked={
                                            selectedCharges.size ===
                                            allCharges.length
                                        }
                                        onCheckedChange={toggleAllCharges}
                                    />
                                    <Label
                                        htmlFor="select-all"
                                        className="cursor-pointer text-sm font-medium"
                                    >
                                        {selectedCharges.size} of{' '}
                                        {allCharges.length} charges selected
                                    </Label>
                                </div>
                                <span className="text-sm text-muted-foreground">
                                    {patient.visits_with_charges} visit
                                    {patient.visits_with_charges !== 1
                                        ? 's'
                                        : ''}
                                </span>
                            </div>

                            {/* Visits Accordion */}
                            <div className="space-y-2">
                                {patient.visits.map((visit) => (
                                    <Collapsible
                                        key={visit.checkin_id}
                                        open={expandedVisits.has(
                                            visit.checkin_id,
                                        )}
                                        onOpenChange={() =>
                                            toggleVisit(visit.checkin_id)
                                        }
                                    >
                                        <CollapsibleTrigger className="flex w-full items-center justify-between rounded-lg border bg-card p-3 hover:bg-muted/50">
                                            <div className="flex items-center gap-2">
                                                {expandedVisits.has(
                                                    visit.checkin_id,
                                                ) ? (
                                                    <ChevronDown className="h-4 w-4" />
                                                ) : (
                                                    <ChevronRight className="h-4 w-4" />
                                                )}
                                                <div className="text-left">
                                                    <p className="text-sm font-medium">
                                                        {visit.department.name}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {visit.checked_in_at} •{' '}
                                                        {visit.charges_count}{' '}
                                                        charge
                                                        {visit.charges_count !==
                                                        1
                                                            ? 's'
                                                            : ''}
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="text-right">
                                                <p className="text-sm font-semibold text-orange-600">
                                                    {formatCurrency(
                                                        visit.patient_copay,
                                                    )}
                                                </p>
                                                {visit.insurance_covered >
                                                    0 && (
                                                    <p className="text-xs text-green-600">
                                                        Ins:{' '}
                                                        {formatCurrency(
                                                            visit.insurance_covered,
                                                        )}
                                                    </p>
                                                )}
                                            </div>
                                        </CollapsibleTrigger>
                                        <CollapsibleContent>
                                            <div className="mt-1 space-y-1 pl-6">
                                                {visit.charges.map((charge) => (
                                                    <div
                                                        key={charge.id}
                                                        className={`flex items-center gap-3 rounded-lg border p-3 transition-colors ${
                                                            selectedCharges.has(
                                                                charge.id,
                                                            )
                                                                ? 'border-primary/50 bg-primary/5'
                                                                : 'bg-muted/20'
                                                        }`}
                                                    >
                                                        <Checkbox
                                                            checked={selectedCharges.has(
                                                                charge.id,
                                                            )}
                                                            onCheckedChange={() =>
                                                                toggleCharge(
                                                                    charge.id,
                                                                )
                                                            }
                                                        />
                                                        <div className="min-w-0 flex-1">
                                                            <div className="flex flex-wrap items-center gap-2">
                                                                <span className="truncate text-sm font-medium">
                                                                    {
                                                                        charge.description
                                                                    }
                                                                </span>
                                                                <InsuranceCoverageBadge
                                                                    isInsuranceClaim={
                                                                        charge.is_insurance_claim
                                                                    }
                                                                    insuranceCoveredAmount={
                                                                        charge.insurance_covered_amount
                                                                    }
                                                                    patientCopayAmount={
                                                                        charge.patient_copay_amount
                                                                    }
                                                                    amount={
                                                                        charge.amount
                                                                    }
                                                                    className="text-xs"
                                                                />
                                                            </div>
                                                            <p className="text-xs text-muted-foreground">
                                                                {formatServiceType(
                                                                    charge.service_type,
                                                                )}{' '}
                                                                •{' '}
                                                                {formatDateTime(
                                                                    charge.charged_at,
                                                                )}
                                                            </p>
                                                        </div>
                                                        <div className="flex items-center gap-2">
                                                            <div className="text-right">
                                                                <p className="text-sm font-semibold text-orange-600">
                                                                    {formatCurrency(
                                                                        charge.is_insurance_claim
                                                                            ? charge.patient_copay_amount
                                                                            : charge.amount,
                                                                    )}
                                                                </p>
                                                                {charge.is_insurance_claim && (
                                                                    <p className="text-[10px] text-muted-foreground">
                                                                        of{' '}
                                                                        {formatCurrency(
                                                                            charge.amount,
                                                                        )}
                                                                    </p>
                                                                )}
                                                            </div>
                                                            {/* Waive/Adjust Actions */}
                                                            {(permissions.canWaiveCharges ||
                                                                permissions.canAdjustCharges) && (
                                                                <div className="flex gap-1">
                                                                    {permissions.canWaiveCharges &&
                                                                        onWaiveCharge && (
                                                                            <Button
                                                                                size="sm"
                                                                                variant="ghost"
                                                                                className="h-7 px-2 text-xs"
                                                                                onClick={(
                                                                                    e,
                                                                                ) => {
                                                                                    e.stopPropagation();
                                                                                    onWaiveCharge(
                                                                                        charge.id,
                                                                                    );
                                                                                }}
                                                                            >
                                                                                Waive
                                                                            </Button>
                                                                        )}
                                                                    {permissions.canAdjustCharges &&
                                                                        onAdjustCharge && (
                                                                            <Button
                                                                                size="sm"
                                                                                variant="ghost"
                                                                                className="h-7 px-2 text-xs"
                                                                                onClick={(
                                                                                    e,
                                                                                ) => {
                                                                                    e.stopPropagation();
                                                                                    onAdjustCharge(
                                                                                        charge.id,
                                                                                    );
                                                                                }}
                                                                            >
                                                                                Adjust
                                                                            </Button>
                                                                        )}
                                                                </div>
                                                            )}
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </CollapsibleContent>
                                    </Collapsible>
                                ))}
                            </div>

                            {/* Error Display */}
                            {error && (
                                <Alert variant="destructive">
                                    <AlertDescription>{error}</AlertDescription>
                                </Alert>
                            )}
                        </div>
                    )}

                    {/* Payment Step */}
                    {step === 'payment' && (
                        <div className="space-y-4">
                            {/* Amount to Collect */}
                            <div className="rounded-lg border border-orange-200 bg-orange-50 p-4 text-center dark:border-orange-800 dark:bg-orange-950/30">
                                <p className="text-sm text-muted-foreground">
                                    Amount to Collect
                                </p>
                                <p className="text-3xl font-bold text-orange-600">
                                    {formatCurrency(totalPatientOwes)}
                                </p>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {selectedCharges.size} charge
                                    {selectedCharges.size !== 1 ? 's' : ''}{' '}
                                    selected
                                </p>
                            </div>

                            {/* Payment Method Selection */}
                            <div className="space-y-2">
                                <Label>Payment Method</Label>
                                <Select
                                    value={paymentMethod}
                                    onValueChange={setPaymentMethod}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="cash">
                                            <div className="flex items-center gap-2">
                                                <Banknote className="h-4 w-4" />
                                                Cash
                                            </div>
                                        </SelectItem>
                                        <SelectItem value="card">
                                            <div className="flex items-center gap-2">
                                                <CreditCard className="h-4 w-4" />
                                                Card
                                            </div>
                                        </SelectItem>
                                        <SelectItem value="mobile_money">
                                            <div className="flex items-center gap-2">
                                                <Smartphone className="h-4 w-4" />
                                                Mobile Money
                                            </div>
                                        </SelectItem>
                                        <SelectItem value="bank_transfer">
                                            <div className="flex items-center gap-2">
                                                <Wallet className="h-4 w-4" />
                                                Bank Transfer
                                            </div>
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* Cash Change Calculator */}
                            {paymentMethod === 'cash' && (
                                <ChangeCalculator
                                    amountDue={totalPatientOwes}
                                    formatCurrency={formatCurrency}
                                    onAmountTenderedChange={setAmountTendered}
                                    initialAmountTendered={amountTendered}
                                    showCard={false}
                                />
                            )}

                            {/* Notes */}
                            <div className="space-y-2">
                                <Label htmlFor="payment-notes">
                                    Notes (Optional)
                                </Label>
                                <Textarea
                                    id="payment-notes"
                                    value={notes}
                                    onChange={(e) => setNotes(e.target.value)}
                                    placeholder="Payment reference or notes..."
                                    rows={2}
                                />
                            </div>

                            {/* Error Display */}
                            {error && (
                                <Alert variant="destructive">
                                    <AlertDescription>{error}</AlertDescription>
                                </Alert>
                            )}
                        </div>
                    )}

                    {/* Success Step */}
                    {step === 'success' && (
                        <div className="space-y-6 py-8 text-center">
                            <div className="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                                <CheckCircle2 className="h-12 w-12 text-green-600" />
                            </div>
                            <div>
                                <p className="text-2xl font-bold text-green-600">
                                    {formatCurrency(totalPatientOwes)}
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Payment received via{' '}
                                    {paymentMethod.replace('_', ' ')}
                                </p>
                            </div>
                        </div>
                    )}
                </div>

                <DialogFooter className="flex-shrink-0 gap-2 sm:gap-0">
                    {step === 'charges' && (
                        <>
                            <Button variant="outline" onClick={handleClose}>
                                Cancel
                            </Button>
                            <Button
                                onClick={handleProceedToPayment}
                                disabled={selectedCharges.size === 0}
                            >
                                <Receipt className="mr-2 h-4 w-4" />
                                Pay {formatCurrency(totalPatientOwes)}
                            </Button>
                        </>
                    )}

                    {step === 'payment' && (
                        <>
                            <Button
                                variant="outline"
                                onClick={() => setStep('charges')}
                            >
                                Back
                            </Button>
                            <Button
                                onClick={handleProcessPayment}
                                disabled={
                                    isProcessing ||
                                    (paymentMethod === 'cash' &&
                                        amountTendered < totalPatientOwes)
                                }
                            >
                                {isProcessing ? (
                                    <>
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                        Processing...
                                    </>
                                ) : (
                                    <>
                                        <Receipt className="mr-2 h-4 w-4" />
                                        Complete Payment
                                    </>
                                )}
                            </Button>
                        </>
                    )}

                    {step === 'success' && (
                        <div className="flex w-full gap-2">
                            <Button
                                variant="default"
                                onClick={handlePrintReceipt}
                                className="flex-1"
                            >
                                <Printer className="mr-2 h-4 w-4" />
                                Print
                            </Button>
                            <Button
                                variant="outline"
                                onClick={handleViewReceipt}
                            >
                                <Eye className="mr-2 h-4 w-4" />
                                View
                            </Button>
                            <Button variant="ghost" onClick={handleClose}>
                                Done
                            </Button>
                        </div>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default PatientBillingModal;
