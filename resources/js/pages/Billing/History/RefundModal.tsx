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
import { Textarea } from '@/components/ui/textarea';
import { router } from '@inertiajs/react';
import { AlertTriangle, RefreshCw } from 'lucide-react';
import { useState } from 'react';

interface Payment {
    id: number;
    description: string;
    paid_amount: number;
    receipt_number: string | null;
    patient_checkin: {
        patient: {
            name: string;
            patient_number: string;
        };
    } | null;
}

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    payment: Payment | null;
}

export default function RefundModal({ open, onOpenChange, payment }: Props) {
    const [reason, setReason] = useState('');
    const [refundAmount, setRefundAmount] = useState('');
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-GH', {
            style: 'currency',
            currency: 'GHS',
        }).format(amount);
    };

    const handleRefund = () => {
        if (!payment) return;

        if (reason.length < 10) {
            setError('Please provide a reason with at least 10 characters');
            return;
        }

        const amount = refundAmount
            ? parseFloat(refundAmount)
            : payment.paid_amount;
        if (amount <= 0 || amount > payment.paid_amount) {
            setError(
                `Refund amount must be between 0.01 and ${formatCurrency(payment.paid_amount)}`,
            );
            return;
        }

        setProcessing(true);
        setError(null);

        router.post(
            `/billing/accounts/charges/${payment.id}/refund`,
            {
                reason,
                refund_amount: amount,
            },
            {
                onSuccess: () => {
                    setReason('');
                    setRefundAmount('');
                    onOpenChange(false);
                },
                onError: (errors) => {
                    setError(
                        errors.error ||
                            errors.reason ||
                            errors.refund_amount ||
                            'Failed to process refund',
                    );
                },
                onFinish: () => {
                    setProcessing(false);
                },
            },
        );
    };

    const handleClose = () => {
        setReason('');
        setRefundAmount('');
        setError(null);
        onOpenChange(false);
    };

    if (!payment) return null;

    const currentRefundAmount = refundAmount
        ? parseFloat(refundAmount)
        : payment.paid_amount;
    const isFullRefund =
        !refundAmount || currentRefundAmount >= payment.paid_amount;

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-purple-600">
                        <RefreshCw className="h-5 w-5" />
                        Process Refund
                    </DialogTitle>
                    <DialogDescription>
                        Process a full or partial refund for this payment. The
                        original record will be maintained for audit purposes.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    {/* Warning */}
                    <div className="flex items-start gap-3 rounded-lg bg-purple-50 p-4 dark:bg-purple-900/20">
                        <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-purple-600" />
                        <div className="text-sm text-purple-800 dark:text-purple-200">
                            <p className="font-medium">Refund Processing</p>
                            <p className="mt-1">
                                {isFullRefund
                                    ? 'This will process a full refund and mark the payment as refunded.'
                                    : 'This will process a partial refund. The remaining amount will stay as paid.'}
                            </p>
                        </div>
                    </div>

                    {/* Payment Details */}
                    <div className="space-y-2 rounded-lg border p-3">
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">
                                Patient
                            </span>
                            <span className="font-medium">
                                {payment.patient_checkin?.patient.name ||
                                    'Unknown'}
                            </span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">
                                Receipt #
                            </span>
                            <span className="font-mono text-sm">
                                {payment.receipt_number || '-'}
                            </span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">
                                Original Amount
                            </span>
                            <span className="font-medium">
                                {formatCurrency(payment.paid_amount)}
                            </span>
                        </div>
                    </div>

                    {/* Refund Amount Input */}
                    <div className="space-y-2">
                        <Label htmlFor="refund-amount">Refund Amount</Label>
                        <div className="relative">
                            <span className="absolute top-1/2 left-3 -translate-y-1/2 text-muted-foreground">
                                GHS
                            </span>
                            <Input
                                id="refund-amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                max={payment.paid_amount}
                                placeholder={payment.paid_amount.toFixed(2)}
                                value={refundAmount}
                                onChange={(e) =>
                                    setRefundAmount(e.target.value)
                                }
                                className="pl-12"
                            />
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Leave empty for full refund. Maximum:{' '}
                            {formatCurrency(payment.paid_amount)}
                        </p>
                    </div>

                    {/* Reason Input */}
                    <div className="space-y-2">
                        <Label htmlFor="refund-reason">
                            Reason for refund{' '}
                            <span className="text-red-500">*</span>
                        </Label>
                        <Textarea
                            id="refund-reason"
                            placeholder="Please provide a detailed reason for this refund (minimum 10 characters)"
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                            rows={3}
                            className={error ? 'border-red-500' : ''}
                        />
                        {error && (
                            <p className="text-sm text-red-500">{error}</p>
                        )}
                        <p className="text-xs text-muted-foreground">
                            {reason.length}/500 characters (minimum 10)
                        </p>
                    </div>

                    {/* Refund Summary */}
                    <div className="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                        <div className="flex justify-between text-sm">
                            <span className="text-muted-foreground">
                                Refund Amount
                            </span>
                            <span className="font-medium text-purple-600">
                                {formatCurrency(
                                    currentRefundAmount || payment.paid_amount,
                                )}
                            </span>
                        </div>
                        {!isFullRefund && (
                            <div className="mt-1 flex justify-between text-sm">
                                <span className="text-muted-foreground">
                                    Remaining Paid
                                </span>
                                <span className="font-medium text-green-600">
                                    {formatCurrency(
                                        payment.paid_amount -
                                            currentRefundAmount,
                                    )}
                                </span>
                            </div>
                        )}
                    </div>
                </div>

                <DialogFooter className="gap-2 sm:gap-0">
                    <Button
                        variant="outline"
                        onClick={handleClose}
                        disabled={processing}
                    >
                        Cancel
                    </Button>
                    <Button
                        className="bg-purple-600 hover:bg-purple-700"
                        onClick={handleRefund}
                        disabled={processing || reason.length < 10}
                    >
                        {processing
                            ? 'Processing...'
                            : `Refund ${formatCurrency(currentRefundAmount || payment.paid_amount)}`}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
