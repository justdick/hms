import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { router } from '@inertiajs/react';
import { AlertTriangle, XCircle } from 'lucide-react';
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

export default function VoidConfirmationModal({
    open,
    onOpenChange,
    payment,
}: Props) {
    const [reason, setReason] = useState('');
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-GH', {
            style: 'currency',
            currency: 'GHS',
        }).format(amount);
    };

    const handleVoid = () => {
        if (!payment) return;

        if (reason.length < 10) {
            setError('Please provide a reason with at least 10 characters');
            return;
        }

        setProcessing(true);
        setError(null);

        router.post(
            `/billing/accounts/charges/${payment.id}/void`,
            { reason },
            {
                onSuccess: () => {
                    setReason('');
                    onOpenChange(false);
                },
                onError: (errors) => {
                    setError(
                        errors.error ||
                            errors.reason ||
                            'Failed to void payment',
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
        setError(null);
        onOpenChange(false);
    };

    if (!payment) return null;

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-red-600">
                        <XCircle className="h-5 w-5" />
                        Void Payment
                    </DialogTitle>
                    <DialogDescription>
                        This action will mark the payment as voided. The
                        original record will be maintained for audit purposes.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    {/* Warning */}
                    <div className="flex items-start gap-3 rounded-lg bg-red-50 p-4 dark:bg-red-900/20">
                        <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-red-600" />
                        <div className="text-sm text-red-800 dark:text-red-200">
                            <p className="font-medium">
                                Warning: This action cannot be undone
                            </p>
                            <p className="mt-1">
                                Voiding this payment will mark it as invalid.
                                The patient may need to make a new payment.
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
                                Amount
                            </span>
                            <span className="font-medium text-red-600">
                                {formatCurrency(payment.paid_amount)}
                            </span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">
                                Description
                            </span>
                            <span className="max-w-[200px] truncate text-right font-medium">
                                {payment.description}
                            </span>
                        </div>
                    </div>

                    {/* Reason Input */}
                    <div className="space-y-2">
                        <Label htmlFor="void-reason">
                            Reason for voiding{' '}
                            <span className="text-red-500">*</span>
                        </Label>
                        <Textarea
                            id="void-reason"
                            placeholder="Please provide a detailed reason for voiding this payment (minimum 10 characters)"
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
                        variant="destructive"
                        onClick={handleVoid}
                        disabled={processing || reason.length < 10}
                    >
                        {processing ? 'Voiding...' : 'Void Payment'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
