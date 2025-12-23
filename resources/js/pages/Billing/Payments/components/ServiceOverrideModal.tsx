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
import { AlertTriangle, Loader2, ShieldAlert } from 'lucide-react';
import { useState } from 'react';

interface Charge {
    id: number;
    description: string;
    amount: number;
    service_type: string;
    is_insurance_claim: boolean;
    patient_copay_amount: number;
}

interface ServiceOverrideModalProps {
    isOpen: boolean;
    onClose: () => void;
    checkinId: number;
    selectedCharges: Charge[];
    formatCurrency: (amount: number) => string;
    patientName: string;
}

export function ServiceOverrideModal({
    isOpen,
    onClose,
    checkinId,
    selectedCharges,
    formatCurrency,
    patientName,
}: ServiceOverrideModalProps) {
    const [reason, setReason] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [showConfirmation, setShowConfirmation] = useState(false);

    const totalAmount = selectedCharges.reduce((sum, charge) => {
        return (
            sum +
            (charge.is_insurance_claim
                ? charge.patient_copay_amount
                : charge.amount)
        );
    }, 0);

    const handleSubmit = () => {
        if (reason.length < 10) {
            return;
        }
        setShowConfirmation(true);
    };

    const handleConfirm = () => {
        setIsSubmitting(true);

        router.post(
            `/billing/checkin/${checkinId}/billing-override`,
            {
                charge_ids: selectedCharges.map((c) => c.id),
                reason: reason,
            },
            {
                onSuccess: () => {
                    setReason('');
                    setShowConfirmation(false);
                    onClose();
                },
                onError: () => {
                    setShowConfirmation(false);
                },
                onFinish: () => {
                    setIsSubmitting(false);
                },
            },
        );
    };

    const handleClose = () => {
        if (!isSubmitting) {
            setReason('');
            setShowConfirmation(false);
            onClose();
        }
    };

    if (showConfirmation) {
        return (
            <Dialog open={isOpen} onOpenChange={handleClose}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2 text-amber-600">
                            <AlertTriangle className="h-5 w-5" />
                            Confirm Override
                        </DialogTitle>
                        <DialogDescription>
                            Please confirm you want to create this billing
                            override.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4 py-4">
                        <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950">
                            <p className="text-sm text-amber-800 dark:text-amber-200">
                                You are about to mark{' '}
                                <span className="font-semibold">
                                    {selectedCharges.length} charge(s)
                                </span>{' '}
                                totaling{' '}
                                <span className="font-semibold">
                                    {formatCurrency(totalAmount)}
                                </span>{' '}
                                as <span className="font-semibold">owing</span>{' '}
                                for{' '}
                                <span className="font-semibold">
                                    {patientName}
                                </span>
                                .
                            </p>
                            <p className="mt-2 text-sm text-amber-700 dark:text-amber-300">
                                The patient will be able to proceed with
                                services without immediate payment. This action
                                will be logged for audit purposes.
                            </p>
                        </div>

                        <div>
                            <Label className="text-sm font-medium">
                                Reason provided:
                            </Label>
                            <p className="mt-1 text-sm text-muted-foreground italic">
                                "{reason}"
                            </p>
                        </div>
                    </div>

                    <DialogFooter className="gap-2 sm:gap-0">
                        <Button
                            variant="outline"
                            onClick={() => setShowConfirmation(false)}
                            disabled={isSubmitting}
                        >
                            Go Back
                        </Button>
                        <Button
                            variant="default"
                            onClick={handleConfirm}
                            disabled={isSubmitting}
                            className="bg-amber-600 hover:bg-amber-700"
                        >
                            {isSubmitting ? (
                                <>
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    Creating Override...
                                </>
                            ) : (
                                'Confirm Override'
                            )}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        );
    }

    return (
        <Dialog open={isOpen} onOpenChange={handleClose}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <ShieldAlert className="h-5 w-5 text-amber-600" />
                        Create Billing Override
                    </DialogTitle>
                    <DialogDescription>
                        Allow the patient to proceed with services without
                        immediate payment. The charges will be marked as owing.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4 py-4">
                    {/* Patient and Charges Summary */}
                    <div className="rounded-lg border bg-muted/50 p-4">
                        <div className="mb-3">
                            <p className="text-sm font-medium">Patient</p>
                            <p className="text-lg font-semibold">
                                {patientName}
                            </p>
                        </div>

                        <div className="space-y-2">
                            <p className="text-sm font-medium">
                                Selected Charges ({selectedCharges.length})
                            </p>
                            <div className="max-h-32 space-y-1 overflow-y-auto">
                                {selectedCharges.map((charge) => (
                                    <div
                                        key={charge.id}
                                        className="flex items-center justify-between text-sm"
                                    >
                                        <span className="truncate pr-2">
                                            {charge.description}
                                        </span>
                                        <span className="font-medium whitespace-nowrap">
                                            {formatCurrency(
                                                charge.is_insurance_claim
                                                    ? charge.patient_copay_amount
                                                    : charge.amount,
                                            )}
                                        </span>
                                    </div>
                                ))}
                            </div>
                            <div className="flex items-center justify-between border-t pt-2">
                                <span className="font-medium">
                                    Total Amount
                                </span>
                                <span className="text-lg font-bold text-primary">
                                    {formatCurrency(totalAmount)}
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* Reason Input */}
                    <div className="space-y-2">
                        <Label htmlFor="reason">
                            Reason for Override{' '}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Textarea
                            id="reason"
                            placeholder="Enter the reason for allowing this patient to proceed without payment (minimum 10 characters)..."
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                            rows={3}
                            className="resize-none"
                        />
                        <p className="text-xs text-muted-foreground">
                            {reason.length}/500 characters (minimum 10 required)
                        </p>
                        {reason.length > 0 && reason.length < 10 && (
                            <p className="text-xs text-destructive">
                                Please provide a more detailed reason (at least
                                10 characters)
                            </p>
                        )}
                    </div>

                    {/* Warning */}
                    <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-900 dark:bg-amber-950">
                        <p className="text-xs text-amber-800 dark:text-amber-200">
                            <strong>Note:</strong> This action will be logged
                            with your name, timestamp, and the reason provided.
                            The patient's charges will be marked as "owing" and
                            will appear in outstanding balance reports.
                        </p>
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={handleClose}
                        disabled={isSubmitting}
                    >
                        Cancel
                    </Button>
                    <Button
                        onClick={handleSubmit}
                        disabled={reason.length < 10 || isSubmitting}
                        className="bg-amber-600 hover:bg-amber-700"
                    >
                        Continue
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
