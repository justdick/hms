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
import { AlertTriangle, CreditCard, Loader2, Star, XCircle } from 'lucide-react';
import { useState } from 'react';

interface ManageCreditModalProps {
    isOpen: boolean;
    onClose: () => void;
    patientId: number;
    patientName: string;
    isCreditEligible: boolean;
    totalOwing?: number;
    formatCurrency?: (amount: number) => string;
}

export function ManageCreditModal({
    isOpen,
    onClose,
    patientId,
    patientName,
    isCreditEligible,
    totalOwing = 0,
    formatCurrency = (amount) => `GHS ${amount.toFixed(2)}`,
}: ManageCreditModalProps) {
    const [reason, setReason] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [showConfirmation, setShowConfirmation] = useState(false);

    const isAdding = !isCreditEligible;
    const actionText = isAdding ? 'Add Credit Tag' : 'Remove Credit Tag';
    const actionDescription = isAdding
        ? 'Allow this patient to receive services without immediate payment.'
        : 'Remove credit privileges from this patient.';

    const handleSubmit = () => {
        if (reason.length < 10) {
            return;
        }
        setShowConfirmation(true);
    };

    const handleConfirm = () => {
        setIsSubmitting(true);

        const endpoint = isAdding
            ? `/billing/patients/${patientId}/credit-tag`
            : `/billing/patients/${patientId}/credit-tag`;

        const method = isAdding ? 'post' : 'delete';

        router[method](
            endpoint,
            { reason },
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
                            Confirm {actionText}
                        </DialogTitle>
                        <DialogDescription>
                            Please confirm you want to {isAdding ? 'add' : 'remove'} the
                            credit tag.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4 py-4">
                        <div
                            className={`rounded-lg border p-4 ${
                                isAdding
                                    ? 'border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950'
                                    : 'border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950'
                            }`}
                        >
                            <p
                                className={`text-sm ${
                                    isAdding
                                        ? 'text-amber-800 dark:text-amber-200'
                                        : 'text-red-800 dark:text-red-200'
                                }`}
                            >
                                {isAdding ? (
                                    <>
                                        You are about to grant{' '}
                                        <span className="font-semibold">{patientName}</span>{' '}
                                        credit account privileges. They will be able to
                                        receive services without immediate payment.
                                    </>
                                ) : (
                                    <>
                                        You are about to remove credit privileges from{' '}
                                        <span className="font-semibold">{patientName}</span>.
                                        {totalOwing > 0 && (
                                            <>
                                                {' '}
                                                They currently owe{' '}
                                                <span className="font-semibold">
                                                    {formatCurrency(totalOwing)}
                                                </span>
                                                .
                                            </>
                                        )}
                                    </>
                                )}
                            </p>
                            <p
                                className={`mt-2 text-sm ${
                                    isAdding
                                        ? 'text-amber-700 dark:text-amber-300'
                                        : 'text-red-700 dark:text-red-300'
                                }`}
                            >
                                This action will be logged for audit purposes.
                            </p>
                        </div>

                        <div>
                            <Label className="text-sm font-medium">Reason provided:</Label>
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
                            variant={isAdding ? 'default' : 'destructive'}
                            onClick={handleConfirm}
                            disabled={isSubmitting}
                            className={isAdding ? 'bg-amber-600 hover:bg-amber-700' : ''}
                        >
                            {isSubmitting ? (
                                <>
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    {isAdding ? 'Adding...' : 'Removing...'}
                                </>
                            ) : (
                                `Confirm ${isAdding ? 'Add' : 'Remove'}`
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
                        {isAdding ? (
                            <Star className="h-5 w-5 text-amber-600" />
                        ) : (
                            <XCircle className="h-5 w-5 text-red-600" />
                        )}
                        {actionText}
                    </DialogTitle>
                    <DialogDescription>{actionDescription}</DialogDescription>
                </DialogHeader>

                <div className="space-y-4 py-4">
                    {/* Patient Summary */}
                    <div className="rounded-lg border bg-muted/50 p-4">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10">
                                <CreditCard className="h-5 w-5 text-primary" />
                            </div>
                            <div>
                                <p className="font-semibold">{patientName}</p>
                                <p className="text-sm text-muted-foreground">
                                    {isCreditEligible ? (
                                        <span className="text-amber-600">
                                            Currently has credit privileges
                                        </span>
                                    ) : (
                                        <span>No credit privileges</span>
                                    )}
                                </p>
                            </div>
                        </div>

                        {!isAdding && totalOwing > 0 && (
                            <div className="mt-3 rounded-md border border-orange-200 bg-orange-50 p-2 dark:border-orange-900 dark:bg-orange-950">
                                <p className="text-sm text-orange-800 dark:text-orange-200">
                                    <span className="font-medium">Outstanding Balance:</span>{' '}
                                    {formatCurrency(totalOwing)}
                                </p>
                            </div>
                        )}
                    </div>

                    {/* Reason Input */}
                    <div className="space-y-2">
                        <Label htmlFor="reason">
                            Reason <span className="text-destructive">*</span>
                        </Label>
                        <Textarea
                            id="reason"
                            placeholder={
                                isAdding
                                    ? 'Enter the reason for granting credit privileges (e.g., VIP patient, corporate account, emergency case)...'
                                    : 'Enter the reason for removing credit privileges...'
                            }
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
                                Please provide a more detailed reason (at least 10 characters)
                            </p>
                        )}
                    </div>

                    {/* Info Note */}
                    <div
                        className={`rounded-lg border p-3 ${
                            isAdding
                                ? 'border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950'
                                : 'border-muted bg-muted/50'
                        }`}
                    >
                        <p
                            className={`text-xs ${
                                isAdding
                                    ? 'text-amber-800 dark:text-amber-200'
                                    : 'text-muted-foreground'
                            }`}
                        >
                            {isAdding ? (
                                <>
                                    <strong>Note:</strong> Patients with credit privileges
                                    can receive all services without payment blocking. All
                                    charges will be automatically marked as "owing" and will
                                    appear in outstanding balance reports.
                                </>
                            ) : (
                                <>
                                    <strong>Note:</strong> Removing credit privileges will
                                    require the patient to pay for services before receiving
                                    them. Existing owing charges will remain until paid.
                                </>
                            )}
                        </p>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={handleClose} disabled={isSubmitting}>
                        Cancel
                    </Button>
                    <Button
                        onClick={handleSubmit}
                        disabled={reason.length < 10 || isSubmitting}
                        variant={isAdding ? 'default' : 'destructive'}
                        className={isAdding ? 'bg-amber-600 hover:bg-amber-700' : ''}
                    >
                        Continue
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
