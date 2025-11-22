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
import { Form } from '@inertiajs/react';
import { AlertTriangle, Clock, ShieldAlert } from 'lucide-react';
import { useState } from 'react';

interface ChargeItem {
    id: number;
    description: string;
    amount: number;
    patient_copay_amount: number;
}

interface ServiceAccessOverrideModalProps {
    isOpen: boolean;
    onClose: () => void;
    serviceType: string;
    checkinId: number;
    pendingCharges: ChargeItem[];
    formatCurrency: (amount: number) => string;
    expiryHours?: number;
}

export function ServiceAccessOverrideModal({
    isOpen,
    onClose,
    serviceType,
    checkinId,
    pendingCharges,
    formatCurrency,
    expiryHours = 2,
}: ServiceAccessOverrideModalProps) {
    const [reason, setReason] = useState('');
    const [reasonError, setReasonError] = useState('');

    const formatServiceType = (type: string) => {
        return type
            .split('_')
            .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
            .join(' ');
    };

    const totalPending = pendingCharges.reduce(
        (sum, charge) => sum + charge.patient_copay_amount,
        0,
    );

    const handleReasonChange = (value: string) => {
        setReason(value);
        if (value.length < 20) {
            setReasonError('Reason must be at least 20 characters');
        } else {
            setReasonError('');
        }
    };

    const handleClose = () => {
        setReason('');
        setReasonError('');
        onClose();
    };

    return (
        <Dialog open={isOpen} onOpenChange={handleClose}>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <ShieldAlert className="h-5 w-5 text-yellow-600" />
                        Activate Service Access Override
                    </DialogTitle>
                    <DialogDescription>
                        Grant temporary access to blocked service despite unpaid
                        charges
                    </DialogDescription>
                </DialogHeader>

                <Form
                    action={`/billing/checkin/${checkinId}/override`}
                    method="post"
                    onSuccess={handleClose}
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="space-y-4">
                                {/* Service Type Display */}
                                <div className="rounded-lg border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-800 dark:bg-yellow-950/20">
                                    <div className="flex items-center gap-2">
                                        <AlertTriangle className="h-5 w-5 text-yellow-600" />
                                        <div>
                                            <p className="font-medium text-yellow-900 dark:text-yellow-100">
                                                Service Type:{' '}
                                                {formatServiceType(serviceType)}
                                            </p>
                                            <p className="text-sm text-yellow-700 dark:text-yellow-300">
                                                This service is currently blocked
                                                due to unpaid charges
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                {/* Hidden input for service type */}
                                <input
                                    type="hidden"
                                    name="service_type"
                                    value={serviceType}
                                />

                                {/* Pending Charges Causing Block */}
                                <div className="space-y-2">
                                    <Label className="text-sm font-medium">
                                        Pending Charges Causing Block
                                    </Label>
                                    <div className="max-h-48 space-y-2 overflow-y-auto rounded-lg border bg-muted/30 p-3">
                                        {pendingCharges.map((charge) => (
                                            <div
                                                key={charge.id}
                                                className="flex items-center justify-between text-sm"
                                            >
                                                <span className="text-muted-foreground">
                                                    {charge.description}
                                                </span>
                                                <span className="font-medium text-orange-600">
                                                    {formatCurrency(
                                                        charge.patient_copay_amount,
                                                    )}
                                                </span>
                                            </div>
                                        ))}
                                        <div className="flex items-center justify-between border-t border-border pt-2 font-medium">
                                            <span>Total Outstanding:</span>
                                            <span className="text-orange-600">
                                                {formatCurrency(totalPending)}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                {/* Reason Input */}
                                <div className="space-y-2">
                                    <Label htmlFor="reason">
                                        Reason for Override{' '}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </Label>
                                    <Textarea
                                        id="reason"
                                        name="reason"
                                        value={reason}
                                        onChange={(e) =>
                                            handleReasonChange(e.target.value)
                                        }
                                        placeholder="Provide a detailed reason for this emergency override (minimum 20 characters)..."
                                        rows={4}
                                        className={
                                            errors.reason || reasonError
                                                ? 'border-destructive'
                                                : ''
                                        }
                                        required
                                    />
                                    <div className="flex items-center justify-between">
                                        <p className="text-xs text-muted-foreground">
                                            {reason.length}/20 characters
                                            minimum
                                        </p>
                                        {(errors.reason || reasonError) && (
                                            <p className="text-xs text-destructive">
                                                {errors.reason || reasonError}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                {/* Expiry Information */}
                                <div className="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-950/20">
                                    <div className="flex items-start gap-2">
                                        <Clock className="mt-0.5 h-4 w-4 text-blue-600" />
                                        <div className="text-sm">
                                            <p className="font-medium text-blue-900 dark:text-blue-100">
                                                Override Duration
                                            </p>
                                            <p className="text-blue-700 dark:text-blue-300">
                                                This override will expire after{' '}
                                                {expiryHours} hour
                                                {expiryHours !== 1 ? 's' : ''}.
                                                The service will be blocked again
                                                unless payment is received.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                {/* Warning Message */}
                                <div className="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-950/20">
                                    <div className="flex items-start gap-2">
                                        <AlertTriangle className="mt-0.5 h-4 w-4 text-red-600" />
                                        <div className="text-sm">
                                            <p className="font-medium text-red-900 dark:text-red-100">
                                                Important Notice
                                            </p>
                                            <ul className="mt-1 list-inside list-disc space-y-1 text-red-700 dark:text-red-300">
                                                <li>
                                                    This action will be logged in
                                                    the audit trail
                                                </li>
                                                <li>
                                                    Your name and reason will be
                                                    recorded
                                                </li>
                                                <li>
                                                    Use only for genuine
                                                    emergencies
                                                </li>
                                                <li>
                                                    Patient should be directed to
                                                    billing after service
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <DialogFooter className="mt-6">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={handleClose}
                                    disabled={processing}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={
                                        processing ||
                                        reason.length < 20 ||
                                        !!reasonError
                                    }
                                    className="bg-yellow-600 hover:bg-yellow-700"
                                >
                                    {processing
                                        ? 'Activating Override...'
                                        : 'Activate Override'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
