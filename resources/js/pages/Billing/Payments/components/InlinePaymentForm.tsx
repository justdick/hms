import { Button } from '@/components/ui/button';
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
import { CreditCard } from 'lucide-react';
import { useEffect, useState } from 'react';
import { ChangeCalculator } from './ChangeCalculator';

interface InlinePaymentFormProps {
    checkinId: number;
    selectedCharges: number[];
    totalAmount: number;
    formatCurrency: (amount: number) => string;
    onSuccess?: () => void;
}

export function InlinePaymentForm({
    checkinId,
    selectedCharges,
    totalAmount,
    formatCurrency,
    onSuccess,
}: InlinePaymentFormProps) {
    const { data, setData, post, processing, errors, reset } = useForm({
        charges: selectedCharges,
        payment_method: 'cash',
        amount_paid: totalAmount,
        notes: '',
    });

    // Track amount tendered for cash payments (for change calculation)
    const [amountTendered, setAmountTendered] = useState<number>(totalAmount);

    // Update form data when selected charges or total amount changes
    useEffect(() => {
        setData({
            ...data,
            charges: selectedCharges,
            amount_paid: totalAmount,
        });
        // Also update amount tendered when total changes
        setAmountTendered(totalAmount);
    }, [selectedCharges, totalAmount]);

    // Check if cash payment has sufficient amount tendered
    const isCashPayment = data.payment_method === 'cash';
    const isCashSufficient = !isCashPayment || amountTendered >= totalAmount;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/billing/checkin/${checkinId}/payment`, {
            onSuccess: () => {
                reset();
                onSuccess?.();
            },
        });
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            {/* Payment Method */}
            <div className="space-y-2">
                <Label htmlFor="payment-method">Payment Method</Label>
                <Select
                    value={data.payment_method}
                    onValueChange={(value) => setData('payment_method', value)}
                >
                    <SelectTrigger id="payment-method">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="cash">Cash</SelectItem>
                        <SelectItem value="card">Card</SelectItem>
                        <SelectItem value="mobile_money">
                            Mobile Money
                        </SelectItem>
                        <SelectItem value="insurance">Insurance</SelectItem>
                        <SelectItem value="bank_transfer">
                            Bank Transfer
                        </SelectItem>
                    </SelectContent>
                </Select>
                {errors.payment_method && (
                    <p className="text-sm text-destructive">
                        {errors.payment_method}
                    </p>
                )}
            </div>

            {/* Cash Change Calculator - Requirement 4.1, 4.2, 4.3, 4.4 */}
            {isCashPayment && totalAmount > 0 && (
                <ChangeCalculator
                    amountDue={totalAmount}
                    formatCurrency={formatCurrency}
                    onAmountTenderedChange={setAmountTendered}
                    initialAmountTendered={amountTendered}
                />
            )}

            {/* Amount Input */}
            <div className="space-y-2">
                <Label htmlFor="amount">Amount to Collect from Patient</Label>
                <Input
                    id="amount"
                    type="number"
                    value={data.amount_paid}
                    onChange={(e) =>
                        setData('amount_paid', parseFloat(e.target.value) || 0)
                    }
                    placeholder="0.00"
                    step="0.01"
                    min="0"
                    max={totalAmount}
                />
                <p className="text-xs text-muted-foreground">
                    Patient owes: {formatCurrency(totalAmount)} (copay amount)
                </p>
                {errors.amount_paid && (
                    <p className="text-sm text-destructive">
                        {errors.amount_paid}
                    </p>
                )}

                {/* Quick Amount Buttons */}
                <div className="flex gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => setData('amount_paid', totalAmount)}
                    >
                        Full Amount
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            setData(
                                'amount_paid',
                                parseFloat((totalAmount / 2).toFixed(2)),
                            )
                        }
                    >
                        Half Amount
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            setData(
                                'amount_paid',
                                parseFloat((totalAmount / 4).toFixed(2)),
                            )
                        }
                    >
                        Quarter
                    </Button>
                </div>
            </div>

            {/* Notes */}
            <div className="space-y-2">
                <Label htmlFor="notes">Notes (Optional)</Label>
                <Textarea
                    id="notes"
                    value={data.notes}
                    onChange={(e) => setData('notes', e.target.value)}
                    placeholder="Payment notes or reference..."
                    rows={3}
                />
                {errors.notes && (
                    <p className="text-sm text-destructive">{errors.notes}</p>
                )}
            </div>

            {/* Submit Button */}
            <Button
                type="submit"
                disabled={
                    processing ||
                    selectedCharges.length === 0 ||
                    !data.amount_paid ||
                    data.amount_paid <= 0 ||
                    !isCashSufficient
                }
                className="w-full"
            >
                {processing ? (
                    <>
                        <div className="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                        Processing Payment...
                    </>
                ) : (
                    <>
                        <CreditCard className="mr-2 h-4 w-4" />
                        Process Payment ({formatCurrency(data.amount_paid || 0)}
                        )
                    </>
                )}
            </Button>

            {/* General Error Display */}
            {errors.charges && (
                <p className="text-sm text-destructive">{errors.charges}</p>
            )}
        </form>
    );
}
