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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { router } from '@inertiajs/react';
import { Infinity, Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Patient {
    id: number;
    first_name: string;
    last_name: string;
    patient_number: string;
}

interface Props {
    isOpen: boolean;
    onClose: () => void;
    patient: Patient;
    currentLimit: number;
    formatCurrency: (amount: number | string) => string;
}

const UNLIMITED_CREDIT_VALUE = 999999999;

export function CreditLimitModal({ isOpen, onClose, patient, currentLimit, formatCurrency }: Props) {
    const isCurrentlyUnlimited = currentLimit >= UNLIMITED_CREDIT_VALUE;
    const [creditLimit, setCreditLimit] = useState(isCurrentlyUnlimited ? '0' : String(currentLimit));
    const [isUnlimited, setIsUnlimited] = useState(isCurrentlyUnlimited);
    const [reason, setReason] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    // Reset form when modal opens with new values
    useEffect(() => {
        if (isOpen) {
            const unlimited = currentLimit >= UNLIMITED_CREDIT_VALUE;
            setIsUnlimited(unlimited);
            setCreditLimit(unlimited ? '0' : String(currentLimit));
            setReason('');
            setErrors({});
        }
    }, [isOpen, currentLimit]);

    const handleSubmit = () => {
        setErrors({});
        setIsSubmitting(true);

        const finalCreditLimit = isUnlimited ? UNLIMITED_CREDIT_VALUE : Number(creditLimit);

        router.post(`/billing/patient-accounts/patient/${patient.id}/credit-limit`, {
            credit_limit: finalCreditLimit,
            reason,
        }, {
            onSuccess: () => {
                resetForm();
                onClose();
            },
            onError: (errors) => {
                setErrors(errors as Record<string, string>);
            },
            onFinish: () => {
                setIsSubmitting(false);
            },
        });
    };

    const resetForm = () => {
        const unlimited = currentLimit >= UNLIMITED_CREDIT_VALUE;
        setIsUnlimited(unlimited);
        setCreditLimit(unlimited ? '0' : String(currentLimit));
        setReason('');
        setErrors({});
    };

    const handleClose = () => {
        resetForm();
        onClose();
    };

    const handleUnlimitedChange = (checked: boolean) => {
        setIsUnlimited(checked);
        if (checked) {
            setCreditLimit('0');
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={handleClose}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Set Credit Limit</DialogTitle>
                    <DialogDescription>
                        Set the credit limit for {patient.first_name} {patient.last_name}
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4 py-4">
                    <div className="rounded-lg border bg-gray-50 dark:bg-gray-800 p-4">
                        <div className="text-sm text-gray-500">Current Credit Limit</div>
                        <div className="text-xl font-bold text-blue-600 flex items-center gap-2">
                            {isCurrentlyUnlimited ? (
                                <>
                                    <Infinity className="h-5 w-5" />
                                    Unlimited
                                </>
                            ) : (
                                formatCurrency(currentLimit)
                            )}
                        </div>
                    </div>

                    <div className="flex items-center space-x-2">
                        <Checkbox
                            id="unlimited"
                            checked={isUnlimited}
                            onCheckedChange={handleUnlimitedChange}
                        />
                        <Label htmlFor="unlimited" className="flex items-center gap-2 cursor-pointer">
                            <Infinity className="h-4 w-4" />
                            Unlimited Credit
                        </Label>
                    </div>

                    {!isUnlimited && (
                        <div className="space-y-2">
                            <Label htmlFor="credit_limit">Credit Limit (GHS)</Label>
                            <Input
                                id="credit_limit"
                                type="number"
                                min="0"
                                step="100"
                                placeholder="0.00"
                                value={creditLimit}
                                onChange={(e) => setCreditLimit(e.target.value)}
                            />
                            <p className="text-sm text-gray-500">
                                Set to 0 to remove credit privileges
                            </p>
                            {errors.credit_limit && (
                                <p className="text-sm text-red-500">{errors.credit_limit}</p>
                            )}
                        </div>
                    )}

                    {isUnlimited && (
                        <div className="rounded-lg border border-blue-200 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-800 p-3">
                            <p className="text-sm text-blue-700 dark:text-blue-300">
                                Unlimited credit allows the patient to receive services without balance restrictions.
                                Use this for VIP patients or special arrangements.
                            </p>
                        </div>
                    )}

                    <div className="space-y-2">
                        <Label htmlFor="reason">Reason for Change (Optional)</Label>
                        <Textarea
                            id="reason"
                            placeholder="Explain why this credit limit is being set..."
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                            rows={3}
                        />
                        {errors.reason && (
                            <p className="text-sm text-red-500">{errors.reason}</p>
                        )}
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={handleClose} disabled={isSubmitting}>
                        Cancel
                    </Button>
                    <Button onClick={handleSubmit} disabled={isSubmitting}>
                        {isSubmitting ? (
                            <>
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                Saving...
                            </>
                        ) : (
                            'Save Credit Limit'
                        )}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
