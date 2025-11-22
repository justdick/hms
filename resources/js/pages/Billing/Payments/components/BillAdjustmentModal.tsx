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
import { useForm } from '@inertiajs/react';
import { Calculator, Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';

interface ChargeItem {
    id: number;
    description: string;
    amount: number;
}

interface BillAdjustmentModalProps {
    charge: ChargeItem;
    isOpen: boolean;
    onClose: () => void;
    formatCurrency: (amount: number) => string;
    onSuccess?: () => void;
}

export function BillAdjustmentModal({
    charge,
    isOpen,
    onClose,
    formatCurrency,
    onSuccess,
}: BillAdjustmentModalProps) {
    const [previewAmount, setPreviewAmount] = useState<number>(charge.amount);

    const { data, setData, post, processing, errors, reset } = useForm({
        adjustment_type: 'discount_percentage' as
            | 'discount_percentage'
            | 'discount_fixed',
        adjustment_value: 0,
        reason: '',
    });

    // Calculate preview amount whenever adjustment changes
    useEffect(() => {
        if (data.adjustment_value <= 0) {
            setPreviewAmount(charge.amount);
            return;
        }

        let newAmount = charge.amount;

        if (data.adjustment_type === 'discount_percentage') {
            const discountAmount =
                (charge.amount * data.adjustment_value) / 100;
            newAmount = charge.amount - discountAmount;
        } else if (data.adjustment_type === 'discount_fixed') {
            newAmount = charge.amount - data.adjustment_value;
        }

        // Ensure amount doesn't go below 0
        setPreviewAmount(Math.max(0, newAmount));
    }, [data.adjustment_type, data.adjustment_value, charge.amount]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        post(`/billing/charges/${charge.id}/adjust`, {
            onSuccess: () => {
                reset();
                onClose();
                onSuccess?.();
            },
        });
    };

    const handleClose = () => {
        reset();
        setPreviewAmount(charge.amount);
        onClose();
    };

    const isReasonValid = data.reason.trim().length >= 10;
    const isAdjustmentValid =
        data.adjustment_value > 0 &&
        (data.adjustment_type === 'discount_percentage'
            ? data.adjustment_value <= 100
            : data.adjustment_value <= charge.amount);

    const discountAmount = charge.amount - previewAmount;

    return (
        <Dialog open={isOpen} onOpenChange={handleClose}>
            <DialogContent className="sm:max-w-[550px]">
                <form onSubmit={handleSubmit}>
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Calculator className="h-5 w-5 text-blue-600" />
                            Adjust Charge Amount
                        </DialogTitle>
                        <DialogDescription>
                            Apply a discount or reduction to this charge. The
                            adjustment will be logged in the audit trail.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4 py-4">
                        {/* Charge Details */}
                        <div className="rounded-lg border bg-muted/30 p-4">
                            <div className="space-y-1">
                                <span className="text-sm font-medium">
                                    Charge:
                                </span>
                                <p className="text-sm text-muted-foreground">
                                    {charge.description}
                                </p>
                            </div>
                        </div>

                        {/* Adjustment Type */}
                        <div className="space-y-2">
                            <Label htmlFor="adjustment-type">
                                Adjustment Type{' '}
                                <span className="text-destructive">*</span>
                            </Label>
                            <Select
                                value={data.adjustment_type}
                                onValueChange={(value) =>
                                    setData(
                                        'adjustment_type',
                                        value as
                                            | 'discount_percentage'
                                            | 'discount_fixed',
                                    )
                                }
                            >
                                <SelectTrigger id="adjustment-type">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="discount_percentage">
                                        Percentage Discount (%)
                                    </SelectItem>
                                    <SelectItem value="discount_fixed">
                                        Fixed Amount Discount
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Adjustment Value */}
                        <div className="space-y-2">
                            <Label htmlFor="adjustment-value">
                                {data.adjustment_type === 'discount_percentage'
                                    ? 'Discount Percentage'
                                    : 'Discount Amount'}{' '}
                                <span className="text-destructive">*</span>
                            </Label>
                            <div className="relative">
                                <Input
                                    id="adjustment-value"
                                    type="number"
                                    value={data.adjustment_value || ''}
                                    onChange={(e) =>
                                        setData(
                                            'adjustment_value',
                                            parseFloat(e.target.value) || 0,
                                        )
                                    }
                                    placeholder={
                                        data.adjustment_type ===
                                        'discount_percentage'
                                            ? 'Enter percentage (0-100)'
                                            : 'Enter amount'
                                    }
                                    step={
                                        data.adjustment_type ===
                                        'discount_percentage'
                                            ? '1'
                                            : '0.01'
                                    }
                                    min="0"
                                    max={
                                        data.adjustment_type ===
                                        'discount_percentage'
                                            ? '100'
                                            : charge.amount.toString()
                                    }
                                    className={
                                        errors.adjustment_value
                                            ? 'border-destructive'
                                            : ''
                                    }
                                />
                                {data.adjustment_type ===
                                    'discount_percentage' && (
                                    <span className="absolute top-1/2 right-3 -translate-y-1/2 text-sm text-muted-foreground">
                                        %
                                    </span>
                                )}
                            </div>
                            {errors.adjustment_value && (
                                <p className="text-sm text-destructive">
                                    {errors.adjustment_value}
                                </p>
                            )}
                            {data.adjustment_type === 'discount_percentage' &&
                                data.adjustment_value > 100 && (
                                    <p className="text-sm text-destructive">
                                        Percentage cannot exceed 100%
                                    </p>
                                )}
                            {data.adjustment_type === 'discount_fixed' &&
                                data.adjustment_value > charge.amount && (
                                    <p className="text-sm text-destructive">
                                        Discount cannot exceed charge amount
                                    </p>
                                )}
                        </div>

                        {/* Preview */}
                        {data.adjustment_value > 0 && isAdjustmentValid && (
                            <div className="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-950/20">
                                <div className="space-y-2">
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm font-medium">
                                            Original Amount:
                                        </span>
                                        <span className="text-sm font-medium line-through">
                                            {formatCurrency(charge.amount)}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm font-medium text-orange-600">
                                            Discount:
                                        </span>
                                        <span className="text-sm font-medium text-orange-600">
                                            -{formatCurrency(discountAmount)}
                                            {data.adjustment_type ===
                                                'discount_percentage' &&
                                                ` (${data.adjustment_value}%)`}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between border-t border-blue-200 pt-2 dark:border-blue-800">
                                        <span className="font-medium">
                                            New Amount:
                                        </span>
                                        <span className="text-lg font-bold text-green-600">
                                            {formatCurrency(previewAmount)}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Reason Input */}
                        <div className="space-y-2">
                            <Label htmlFor="adjustment-reason">
                                Reason for Adjustment{' '}
                                <span className="text-destructive">*</span>
                            </Label>
                            <Textarea
                                id="adjustment-reason"
                                value={data.reason}
                                onChange={(e) =>
                                    setData('reason', e.target.value)
                                }
                                placeholder="Provide a detailed reason for this adjustment (minimum 10 characters)..."
                                rows={3}
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

                        {/* Warning */}
                        <div className="rounded-lg border border-orange-200 bg-orange-50 p-3 dark:border-orange-800 dark:bg-orange-950/20">
                            <p className="text-xs text-muted-foreground">
                                This adjustment will be permanently logged in
                                the audit trail with your user ID and timestamp.
                            </p>
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
                            disabled={
                                processing ||
                                !isReasonValid ||
                                !isAdjustmentValid
                            }
                        >
                            {processing ? (
                                <>
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    Processing...
                                </>
                            ) : (
                                <>
                                    <Calculator className="mr-2 h-4 w-4" />
                                    Apply Adjustment
                                </>
                            )}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
