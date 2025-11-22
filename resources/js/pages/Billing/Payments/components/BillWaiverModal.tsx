import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { useForm } from '@inertiajs/react';
import { AlertTriangle, Loader2 } from 'lucide-react';
import { useState } from 'react';

interface ChargeItem {
    id: number;
    description: string;
    amount: number;
}

interface BillWaiverModalProps {
    charge: ChargeItem;
    isOpen: boolean;
    onClose: () => void;
    formatCurrency: (amount: number) => string;
    onSuccess?: () => void;
}

export function BillWaiverModal({
    charge,
    isOpen,
    onClose,
    formatCurrency,
    onSuccess,
}: BillWaiverModalProps) {
    const [confirmed, setConfirmed] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        reason: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!confirmed) {
            return;
        }

        post(`/billing/charges/${charge.id}/waive`, {
            onSuccess: () => {
                reset();
                setConfirmed(false);
                onClose();
                onSuccess?.();
            },
        });
    };

    const handleClose = () => {
        reset();
        setConfirmed(false);
        onClose();
    };

    const isReasonValid = data.reason.trim().length >= 10;

    return (
        <Dialog open={isOpen} onOpenChange={handleClose}>
            <DialogContent className="sm:max-w-[500px]">
                <form onSubmit={handleSubmit}>
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <AlertTriangle className="h-5 w-5 text-orange-600" />
                            Waive Charge
                        </DialogTitle>
                        <DialogDescription>
                            This action will completely waive the charge. This
                            cannot be undone.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4 py-4">
                        {/* Charge Details */}
                        <div className="rounded-lg border border-orange-200 bg-orange-50 p-4 dark:border-orange-800 dark:bg-orange-950/20">
                            <div className="space-y-2">
                                <div>
                                    <span className="text-sm font-medium">
                                        Charge:
                                    </span>
                                    <p className="text-sm text-muted-foreground">
                                        {charge.description}
                                    </p>
                                </div>
                                <div className="flex items-center justify-between border-t border-orange-200 pt-2 dark:border-orange-800">
                                    <span className="font-medium">
                                        Original Amount:
                                    </span>
                                    <span className="text-lg font-bold text-orange-600 line-through">
                                        {formatCurrency(charge.amount)}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="font-medium">
                                        New Amount:
                                    </span>
                                    <span className="text-lg font-bold text-green-600">
                                        {formatCurrency(0)}
                                    </span>
                                </div>
                                <div className="flex items-center justify-between border-t border-orange-200 pt-2 dark:border-orange-800">
                                    <span className="font-medium text-orange-600">
                                        Impact:
                                    </span>
                                    <span className="text-lg font-bold text-orange-600">
                                        -{formatCurrency(charge.amount)}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {/* Reason Input */}
                        <div className="space-y-2">
                            <Label htmlFor="waiver-reason">
                                Reason for Waiver{' '}
                                <span className="text-destructive">*</span>
                            </Label>
                            <Textarea
                                id="waiver-reason"
                                value={data.reason}
                                onChange={(e) =>
                                    setData('reason', e.target.value)
                                }
                                placeholder="Provide a detailed reason for waiving this charge (minimum 10 characters)..."
                                rows={4}
                                className={
                                    errors.reason ? 'border-destructive' : ''
                                }
                            />
                            <div className="flex items-center justify-between">
                                <p className="text-xs text-muted-foreground">
                                    {data.reason.trim().length}/10 characters
                                    minimum
                                </p>
                                {isReasonValid && (
                                    <p className="text-xs text-green-600">
                                        ✓ Reason is valid
                                    </p>
                                )}
                            </div>
                            {errors.reason && (
                                <p className="text-sm text-destructive">
                                    {errors.reason}
                                </p>
                            )}
                        </div>

                        {/* Confirmation Checkbox */}
                        <div className="flex items-start space-x-2 rounded-lg border border-destructive/20 bg-destructive/5 p-3">
                            <Checkbox
                                id="waiver-confirm"
                                checked={confirmed}
                                onCheckedChange={(checked) =>
                                    setConfirmed(checked as boolean)
                                }
                            />
                            <div className="space-y-1">
                                <Label
                                    htmlFor="waiver-confirm"
                                    className="cursor-pointer text-sm leading-none font-medium peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                                >
                                    I confirm this waiver is authorized
                                </Label>
                                <p className="text-xs text-muted-foreground">
                                    This action will be logged in the audit
                                    trail
                                </p>
                            </div>
                        </div>
                    </div>

                    <DialogFooter>
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
                            variant="destructive"
                            disabled={
                                processing || !isReasonValid || !confirmed
                            }
                        >
                            {processing ? (
                                <>
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    Processing...
                                </>
                            ) : (
                                <>
                                    <AlertTriangle className="mr-2 h-4 w-4" />
                                    Waive Charge
                                </>
                            )}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
