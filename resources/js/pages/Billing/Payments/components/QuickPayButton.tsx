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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { router } from '@inertiajs/react';
import { CreditCard, Loader2 } from 'lucide-react';
import { useState } from 'react';

interface QuickPayButtonProps {
    chargeId?: number;
    chargeIds?: number[];
    checkinId?: number;
    amount: number;
    formatCurrency: (amount: number) => string;
    variant?: 'default' | 'outline' | 'ghost';
    size?: 'default' | 'sm' | 'lg';
    className?: string;
    onSuccess?: () => void;
}

export function QuickPayButton({
    chargeId,
    chargeIds,
    checkinId,
    amount,
    formatCurrency,
    variant = 'default',
    size = 'default',
    className,
    onSuccess,
}: QuickPayButtonProps) {
    const [showModal, setShowModal] = useState(false);
    const [paymentMethod, setPaymentMethod] = useState('cash');
    const [processing, setProcessing] = useState(false);

    const handleQuickPay = () => {
        setProcessing(true);

        // Determine the endpoint and data based on what's provided
        let endpoint: string;
        let data: Record<string, any>;

        if (chargeId) {
            // Single charge quick pay
            endpoint = `/billing/charges/${chargeId}/quick-pay`;
            data = {
                payment_method: paymentMethod,
            };
        } else if (chargeIds && checkinId) {
            // Multiple charges quick pay
            endpoint = `/billing/charges/quick-pay-all`;
            data = {
                patient_checkin_id: checkinId,
                payment_method: paymentMethod,
                charges: chargeIds,
            };
        } else {
            console.error('Invalid QuickPayButton configuration');
            setProcessing(false);
            return;
        }

        router.post(endpoint, data, {
            onSuccess: () => {
                setShowModal(false);
                setProcessing(false);
                onSuccess?.();
            },
            onError: (errors) => {
                console.error('Payment failed:', errors);
                setProcessing(false);
            },
        });
    };

    return (
        <>
            <Button
                variant={variant}
                size={size}
                className={className}
                onClick={() => setShowModal(true)}
            >
                <CreditCard className="mr-2 h-4 w-4" />
                Quick Pay {formatCurrency(amount)}
            </Button>

            <Dialog open={showModal} onOpenChange={setShowModal}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Quick Payment</DialogTitle>
                        <DialogDescription>
                            Process payment of {formatCurrency(amount)} with one
                            click
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4 py-4">
                        <div className="space-y-2">
                            <Label htmlFor="quick-pay-method">
                                Payment Method
                            </Label>
                            <Select
                                value={paymentMethod}
                                onValueChange={setPaymentMethod}
                            >
                                <SelectTrigger id="quick-pay-method">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="cash">Cash</SelectItem>
                                    <SelectItem value="card">Card</SelectItem>
                                    <SelectItem value="mobile_money">
                                        Mobile Money
                                    </SelectItem>
                                    <SelectItem value="insurance">
                                        Insurance
                                    </SelectItem>
                                    <SelectItem value="bank_transfer">
                                        Bank Transfer
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="rounded-lg border border-primary/20 bg-primary/5 p-4">
                            <div className="flex items-center justify-between">
                                <span className="font-medium">
                                    Amount to Collect:
                                </span>
                                <span className="text-xl font-bold text-primary">
                                    {formatCurrency(amount)}
                                </span>
                            </div>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setShowModal(false)}
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                        <Button onClick={handleQuickPay} disabled={processing}>
                            {processing ? (
                                <>
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    Processing...
                                </>
                            ) : (
                                <>
                                    <CreditCard className="mr-2 h-4 w-4" />
                                    Confirm Payment
                                </>
                            )}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
