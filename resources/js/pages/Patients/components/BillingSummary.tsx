import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { router } from '@inertiajs/react';
import { AlertCircle, ArrowRight, CreditCard, DollarSign, Shield } from 'lucide-react';

interface Payment {
    date: string;
    amount: number;
    method: string;
    description: string;
}

interface BillingSummaryData {
    total_outstanding: number;
    insurance_covered: number;
    patient_owes: number;
    recent_payments: Payment[];
    has_active_overrides: boolean;
}

interface BillingSummaryProps {
    billingSummary: BillingSummaryData | null;
    canProcessPayment: boolean;
}

export default function BillingSummary({
    billingSummary,
    canProcessPayment,
}: BillingSummaryProps) {
    if (!billingSummary) {
        return null;
    }

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-GH', {
            style: 'currency',
            currency: 'GHS',
            minimumFractionDigits: 2,
        }).format(amount);
    };

    const hasOutstanding = billingSummary.total_outstanding > 0;

    const handleProcessPayment = () => {
        router.visit('/billing');
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <DollarSign className="h-5 w-5" />
                    Billing Summary
                </CardTitle>
                <CardDescription>
                    Outstanding balance and payment history
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
                {hasOutstanding ? (
                    <>
                        {/* Outstanding Balance Section */}
                        <div className="space-y-4">
                            <div className="rounded-lg border-2 border-orange-200 bg-orange-50 p-4 dark:border-orange-900 dark:bg-orange-950/20">
                                <div className="flex items-start gap-3">
                                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-orange-100 dark:bg-orange-900/50">
                                        <AlertCircle className="h-5 w-5 text-orange-600 dark:text-orange-500" />
                                    </div>
                                    <div className="flex-1 space-y-3">
                                        <div>
                                            <p className="text-sm font-medium text-orange-900 dark:text-orange-100">
                                                Outstanding Balance
                                            </p>
                                            <p className="text-2xl font-bold text-orange-700 dark:text-orange-300">
                                                {formatCurrency(
                                                    billingSummary.total_outstanding,
                                                )}
                                            </p>
                                        </div>

                                        {billingSummary.insurance_covered > 0 && (
                                            <div className="grid gap-3 sm:grid-cols-2">
                                                <div className="rounded-lg bg-white/50 p-3 dark:bg-black/20">
                                                    <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                                        <Shield className="h-3 w-3" />
                                                        Insurance Covered
                                                    </div>
                                                    <p className="mt-1 text-sm font-semibold">
                                                        {formatCurrency(
                                                            billingSummary.insurance_covered,
                                                        )}
                                                    </p>
                                                </div>
                                                <div className="rounded-lg bg-white/50 p-3 dark:bg-black/20">
                                                    <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                                        <CreditCard className="h-3 w-3" />
                                                        Patient Owes
                                                    </div>
                                                    <p className="mt-1 text-sm font-semibold">
                                                        {formatCurrency(
                                                            billingSummary.patient_owes,
                                                        )}
                                                    </p>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>

                            {billingSummary.has_active_overrides && (
                                <div className="rounded-lg border border-yellow-200 bg-yellow-50 p-3 dark:border-yellow-900 dark:bg-yellow-950/20">
                                    <p className="text-xs font-medium text-yellow-800 dark:text-yellow-200">
                                        ⚠️ Active service access override in place
                                    </p>
                                </div>
                            )}
                        </div>

                        {canProcessPayment && (
                            <>
                                <Separator />
                                <Button
                                    onClick={handleProcessPayment}
                                    className="w-full gap-2"
                                >
                                    Process Payment
                                    <ArrowRight className="h-4 w-4" />
                                </Button>
                            </>
                        )}
                    </>
                ) : (
                    <div className="rounded-lg border-2 border-green-200 bg-green-50 p-4 dark:border-green-900 dark:bg-green-950/20">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/50">
                                <DollarSign className="h-5 w-5 text-green-600 dark:text-green-500" />
                            </div>
                            <div>
                                <p className="text-sm font-medium text-green-900 dark:text-green-100">
                                    No Outstanding Balance
                                </p>
                                <p className="text-xs text-green-700 dark:text-green-300">
                                    All charges have been paid
                                </p>
                            </div>
                        </div>
                    </div>
                )}

                {/* Recent Payments Section */}
                {billingSummary.recent_payments.length > 0 && (
                    <>
                        <Separator />
                        <div className="space-y-3">
                            <h4 className="text-sm font-semibold">
                                Recent Payments
                            </h4>
                            <div className="space-y-2">
                                {billingSummary.recent_payments.map(
                                    (payment, index) => (
                                        <div
                                            key={index}
                                            className="flex items-center justify-between rounded-lg border bg-card p-3 text-sm"
                                        >
                                            <div className="flex-1">
                                                <p className="font-medium">
                                                    {payment.description}
                                                </p>
                                                <div className="mt-1 flex items-center gap-2 text-xs text-muted-foreground">
                                                    <span>{payment.date}</span>
                                                    <span>•</span>
                                                    <span className="capitalize">
                                                        {payment.method}
                                                    </span>
                                                </div>
                                            </div>
                                            <p className="font-semibold">
                                                {formatCurrency(payment.amount)}
                                            </p>
                                        </div>
                                    ),
                                )}
                            </div>
                        </div>
                    </>
                )}

                {billingSummary.recent_payments.length === 0 &&
                    !hasOutstanding && (
                        <div className="flex flex-col items-center justify-center py-8">
                            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-muted">
                                <CreditCard className="h-8 w-8 text-muted-foreground/50" />
                            </div>
                            <p className="mt-3 text-sm font-medium">
                                No Payment History
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                No payments have been recorded yet
                            </p>
                        </div>
                    )}
            </CardContent>
        </Card>
    );
}
