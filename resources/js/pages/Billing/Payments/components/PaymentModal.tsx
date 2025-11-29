import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
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
    CreditCard,
    Loader2,
    Receipt,
    Smartphone,
    Wallet,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { ChangeCalculator } from './ChangeCalculator';
import { type ChargeItem } from './ChargeSelectionList';

interface PaymentModalProps {
    isOpen: boolean;
    onClose: () => void;
    checkinId: number;
    charges: ChargeItem[];
    patientName: string;
    patientNumber: string;
    formatCurrency: (amount: number) => string;
    onSuccess?: (chargeIds: number[]) => void;
}

type PaymentStep = 'summary' | 'payment' | 'success';

const paymentMethodIcons: Record<string, React.ReactNode> = {
    cash: <Banknote className="h-4 w-4" />,
    card: <CreditCard className="h-4 w-4" />,
    mobile_money: <Smartphone className="h-4 w-4" />,
    bank_transfer: <Wallet className="h-4 w-4" />,
};

/**
 * PaymentModal Component
 * 
 * Focused payment workflow modal with:
 * - Selected charges summary
 * - Payment method selection
 * - Change calculator for cash
 * - Success state with print option
 * 
 * Requirements: 11.7
 */
export function PaymentModal({
    isOpen,
    onClose,
    checkinId,
    charges,
    patientName,
    patientNumber,
    formatCurrency,
    onSuccess,
}: PaymentModalProps) {
    const [step, setStep] = useState<PaymentStep>('summary');
    const [paymentMethod, setPaymentMethod] = useState('cash');
    const [amountTendered, setAmountTendered] = useState(0);
    const [notes, setNotes] = useState('');
    const [isProcessing, setIsProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    // Calculate totals
    const totalAmount = charges.reduce((sum, c) => sum + c.amount, 0);
    const totalPatientOwes = charges.reduce(
        (sum, c) => sum + (c.is_insurance_claim ? c.patient_copay_amount : c.amount),
        0,
    );
    const totalInsuranceCovered = charges.reduce(
        (sum, c) => sum + (c.is_insurance_claim ? c.insurance_covered_amount : 0),
        0,
    );

    // Reset state when modal opens
    useEffect(() => {
        if (isOpen) {
            setStep('summary');
            setPaymentMethod('cash');
            setAmountTendered(totalPatientOwes);
            setNotes('');
            setError(null);
        }
    }, [isOpen, totalPatientOwes]);

    const handleProceedToPayment = () => {
        setStep('payment');
    };

    const handleProcessPayment = () => {
        if (paymentMethod === 'cash' && amountTendered < totalPatientOwes) {
            setError('Amount tendered is less than amount due');
            return;
        }

        setIsProcessing(true);
        setError(null);

        const chargeIds = charges.map((c) => c.id);

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
            onSuccess?.(charges.map((c) => c.id));
        }
        onClose();
    };

    const formatServiceType = (serviceType: string) => {
        return serviceType
            .split('_')
            .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
            .join(' ');
    };

    return (
        <Dialog open={isOpen} onOpenChange={handleClose}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {step === 'summary' && 'Payment Summary'}
                        {step === 'payment' && 'Process Payment'}
                        {step === 'success' && 'Payment Successful'}
                    </DialogTitle>
                    <DialogDescription>
                        {step === 'summary' && `Review charges for ${patientName}`}
                        {step === 'payment' && 'Select payment method and complete transaction'}
                        {step === 'success' && 'Payment has been processed successfully'}
                    </DialogDescription>
                </DialogHeader>

                <div className="py-4">
                    {/* Summary Step */}
                    {step === 'summary' && (
                        <div className="space-y-4">
                            {/* Patient Info */}
                            <div className="rounded-lg bg-muted/30 p-3">
                                <p className="font-medium">{patientName}</p>
                                <p className="text-sm text-muted-foreground">{patientNumber}</p>
                            </div>

                            {/* Charges List */}
                            <div className="max-h-60 space-y-2 overflow-y-auto">
                                {charges.map((charge) => (
                                    <div
                                        key={charge.id}
                                        className="flex items-center justify-between rounded-lg border p-3"
                                    >
                                        <div className="flex-1">
                                            <p className="text-sm font-medium">{charge.description}</p>
                                            <Badge variant="outline" className="mt-1 text-xs">
                                                {formatServiceType(charge.service_type)}
                                            </Badge>
                                        </div>
                                        <div className="text-right">
                                            {charge.is_insurance_claim ? (
                                                <div>
                                                    <p className="text-sm font-semibold text-orange-600">
                                                        {formatCurrency(charge.patient_copay_amount)}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        of {formatCurrency(charge.amount)}
                                                    </p>
                                                </div>
                                            ) : (
                                                <p className="text-sm font-semibold">
                                                    {formatCurrency(charge.amount)}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>

                            {/* Totals */}
                            <div className="space-y-2 rounded-lg border bg-muted/30 p-4">
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">Total Charges:</span>
                                    <span>{formatCurrency(totalAmount)}</span>
                                </div>
                                {totalInsuranceCovered > 0 && (
                                    <div className="flex justify-between text-sm text-green-600">
                                        <span>Insurance Covers:</span>
                                        <span>-{formatCurrency(totalInsuranceCovered)}</span>
                                    </div>
                                )}
                                <div className="flex justify-between border-t pt-2 font-semibold">
                                    <span className="text-orange-600">Patient Owes:</span>
                                    <span className="text-lg text-orange-600">
                                        {formatCurrency(totalPatientOwes)}
                                    </span>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Payment Step */}
                    {step === 'payment' && (
                        <div className="space-y-4">
                            {/* Amount to Collect */}
                            <div className="rounded-lg border border-orange-200 bg-orange-50 p-4 text-center dark:border-orange-800 dark:bg-orange-950/30">
                                <p className="text-sm text-muted-foreground">Amount to Collect</p>
                                <p className="text-3xl font-bold text-orange-600">
                                    {formatCurrency(totalPatientOwes)}
                                </p>
                            </div>

                            {/* Payment Method Selection */}
                            <div className="space-y-2">
                                <Label>Payment Method</Label>
                                <Select value={paymentMethod} onValueChange={setPaymentMethod}>
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

                            {/* Cash Change Calculator - Requirements 4.1-4.4 */}
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
                                <Label htmlFor="payment-notes">Notes (Optional)</Label>
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
                        <div className="space-y-4 text-center">
                            <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                                <CheckCircle2 className="h-10 w-10 text-green-600" />
                            </div>
                            <div>
                                <p className="text-lg font-semibold">Payment Processed!</p>
                                <p className="text-sm text-muted-foreground">
                                    {formatCurrency(totalPatientOwes)} received from {patientName}
                                </p>
                            </div>
                            <div className="rounded-lg bg-muted/30 p-4">
                                <p className="text-sm text-muted-foreground">
                                    {charges.length} charge{charges.length !== 1 ? 's' : ''} paid via{' '}
                                    {paymentMethod.replace('_', ' ')}
                                </p>
                            </div>
                        </div>
                    )}
                </div>

                <DialogFooter className="gap-2 sm:gap-0">
                    {step === 'summary' && (
                        <>
                            <Button variant="outline" onClick={handleClose}>
                                Cancel
                            </Button>
                            <Button onClick={handleProceedToPayment}>
                                Proceed to Payment
                            </Button>
                        </>
                    )}

                    {step === 'payment' && (
                        <>
                            <Button variant="outline" onClick={() => setStep('summary')}>
                                Back
                            </Button>
                            <Button
                                onClick={handleProcessPayment}
                                disabled={isProcessing || (paymentMethod === 'cash' && amountTendered < totalPatientOwes)}
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
                        <Button onClick={handleClose} className="w-full">
                            Done
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default PaymentModal;
