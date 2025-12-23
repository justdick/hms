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
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Textarea } from '@/components/ui/textarea';
import { router } from '@inertiajs/react';
import { Loader2, Minus, Plus } from 'lucide-react';
import { useState } from 'react';

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
    currentBalance: number;
    formatCurrency: (amount: number | string) => string;
}

export function AdjustmentModal({
    isOpen,
    onClose,
    patient,
    currentBalance,
    formatCurrency,
}: Props) {
    const [adjustmentType, setAdjustmentType] = useState<'credit' | 'debit'>(
        'credit',
    );
    const [amount, setAmount] = useState('');
    const [reason, setReason] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const handleSubmit = () => {
        setErrors({});

        const numAmount = Number(amount);
        if (!amount || numAmount <= 0) {
            setErrors({ amount: 'Please enter a valid amount greater than 0' });
            return;
        }

        // For debit adjustments, check if balance is sufficient
        if (adjustmentType === 'debit' && numAmount > currentBalance) {
            setErrors({
                amount: `Cannot debit more than current balance (${formatCurrency(currentBalance)})`,
            });
            return;
        }

        setIsSubmitting(true);

        // Send positive for credit, negative for debit
        const finalAmount =
            adjustmentType === 'credit' ? numAmount : -numAmount;

        router.post(
            `/billing/patient-accounts/patient/${patient.id}/adjustment`,
            {
                amount: finalAmount,
                reason,
            },
            {
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
            },
        );
    };

    const resetForm = () => {
        setAdjustmentType('credit');
        setAmount('');
        setReason('');
        setErrors({});
    };

    const handleClose = () => {
        resetForm();
        onClose();
    };

    const previewBalance = () => {
        const numAmount = Number(amount) || 0;
        if (adjustmentType === 'credit') {
            return currentBalance + numAmount;
        }
        return currentBalance - numAmount;
    };

    return (
        <Dialog open={isOpen} onOpenChange={handleClose}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Make Adjustment</DialogTitle>
                    <DialogDescription>
                        Adjust the account balance for {patient.first_name}{' '}
                        {patient.last_name}
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4 py-4">
                    <div className="rounded-lg border bg-gray-50 p-4 dark:bg-gray-800">
                        <div className="text-sm text-gray-500">
                            Current Balance
                        </div>
                        <div
                            className={`text-xl font-bold ${currentBalance >= 0 ? 'text-green-600' : 'text-red-600'}`}
                        >
                            {formatCurrency(currentBalance)}
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label>Adjustment Type</Label>
                        <RadioGroup
                            value={adjustmentType}
                            onValueChange={(value) =>
                                setAdjustmentType(value as 'credit' | 'debit')
                            }
                            className="flex gap-4"
                        >
                            <div className="flex items-center space-x-2">
                                <RadioGroupItem value="credit" id="credit" />
                                <Label
                                    htmlFor="credit"
                                    className="flex cursor-pointer items-center gap-1"
                                >
                                    <Plus className="h-4 w-4 text-green-600" />
                                    Credit (Add)
                                </Label>
                            </div>
                            <div className="flex items-center space-x-2">
                                <RadioGroupItem value="debit" id="debit" />
                                <Label
                                    htmlFor="debit"
                                    className="flex cursor-pointer items-center gap-1"
                                >
                                    <Minus className="h-4 w-4 text-red-600" />
                                    Debit (Subtract)
                                </Label>
                            </div>
                        </RadioGroup>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="amount">Amount (GHS)</Label>
                        <Input
                            id="amount"
                            type="number"
                            min="0.01"
                            step="0.01"
                            placeholder="0.00"
                            value={amount}
                            onChange={(e) => setAmount(e.target.value)}
                        />
                        {errors.amount && (
                            <p className="text-sm text-red-500">
                                {errors.amount}
                            </p>
                        )}
                    </div>

                    {amount && Number(amount) > 0 && (
                        <div className="rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-800 dark:bg-blue-900/20">
                            <div className="text-sm text-blue-700 dark:text-blue-300">
                                <span className="font-medium">Preview:</span>{' '}
                                Balance will be{' '}
                                <span
                                    className={`font-bold ${previewBalance() >= 0 ? 'text-green-600' : 'text-red-600'}`}
                                >
                                    {formatCurrency(previewBalance())}
                                </span>
                            </div>
                        </div>
                    )}

                    <div className="space-y-2">
                        <Label htmlFor="reason">
                            Reason for Adjustment (Optional)
                        </Label>
                        <Textarea
                            id="reason"
                            placeholder="Explain why this adjustment is being made (e.g., correction for billing error, goodwill credit, etc.)..."
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                            rows={3}
                        />
                        {errors.reason && (
                            <p className="text-sm text-red-500">
                                {errors.reason}
                            </p>
                        )}
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
                    <Button onClick={handleSubmit} disabled={isSubmitting}>
                        {isSubmitting ? (
                            <>
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                Processing...
                            </>
                        ) : (
                            'Make Adjustment'
                        )}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
