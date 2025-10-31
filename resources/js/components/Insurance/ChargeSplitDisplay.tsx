import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { ShieldCheck } from 'lucide-react';

interface ChargeSplitDisplayProps {
    isInsuranceClaim: boolean;
    insuranceCoveredAmount: number;
    patientCopayAmount: number;
    amount: number;
    insurancePlanName?: string;
    className?: string;
}

export function ChargeSplitDisplay({
    isInsuranceClaim,
    insuranceCoveredAmount,
    patientCopayAmount,
    amount,
    insurancePlanName,
    className,
}: ChargeSplitDisplayProps) {
    if (!isInsuranceClaim) {
        return (
            <Card className={className}>
                <CardHeader>
                    <CardTitle className="text-lg">
                        Payment Information
                    </CardTitle>
                    <CardDescription>
                        This charge is not covered by insurance
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="flex items-center justify-between">
                        <span className="text-muted-foreground">
                            Total Amount:
                        </span>
                        <span className="text-2xl font-bold">
                            GHS {amount.toFixed(2)}
                        </span>
                    </div>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Full payment required from patient
                    </p>
                </CardContent>
            </Card>
        );
    }

    const coveragePercentage =
        amount > 0 ? Math.round((insuranceCoveredAmount / amount) * 100) : 0;

    return (
        <Card className={className}>
            <CardHeader>
                <div className="flex items-center gap-2">
                    <ShieldCheck className="size-5 text-green-600 dark:text-green-400" />
                    <div>
                        <CardTitle className="text-lg">
                            Insurance Coverage Applied
                        </CardTitle>
                        {insurancePlanName && (
                            <CardDescription>
                                {insurancePlanName}
                            </CardDescription>
                        )}
                    </div>
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="space-y-2">
                    <div className="flex items-center justify-between">
                        <span className="text-muted-foreground">
                            Total Charge:
                        </span>
                        <span className="font-medium">
                            GHS {amount.toFixed(2)}
                        </span>
                    </div>

                    <Separator />

                    <div className="flex items-center justify-between">
                        <span className="text-muted-foreground">
                            Insurance Covers:
                        </span>
                        <div className="text-right">
                            <div className="font-medium text-green-600 dark:text-green-400">
                                GHS {insuranceCoveredAmount.toFixed(2)}
                            </div>
                            <div className="text-xs text-muted-foreground">
                                ({coveragePercentage}%)
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center justify-between">
                        <span className="text-muted-foreground">
                            Patient Copay:
                        </span>
                        <div className="text-right">
                            <div className="font-medium text-orange-600 dark:text-orange-400">
                                GHS {patientCopayAmount.toFixed(2)}
                            </div>
                            <div className="text-xs text-muted-foreground">
                                ({100 - coveragePercentage}%)
                            </div>
                        </div>
                    </div>

                    <Separator />

                    <div className="flex items-center justify-between pt-2">
                        <span className="font-semibold">Patient Owes:</span>
                        <span className="text-2xl font-bold text-orange-600 dark:text-orange-400">
                            GHS {patientCopayAmount.toFixed(2)}
                        </span>
                    </div>
                </div>

                {coveragePercentage >= 100 ? (
                    <div className="rounded-lg bg-green-50 p-3 text-sm text-green-800 dark:bg-green-950/20 dark:text-green-200">
                        This service is fully covered by insurance. No payment
                        required from patient.
                    </div>
                ) : coveragePercentage > 0 ? (
                    <div className="rounded-lg bg-orange-50 p-3 text-sm text-orange-800 dark:bg-orange-950/20 dark:text-orange-200">
                        Patient is responsible for {100 - coveragePercentage}%
                        copayment. Insurance covers the remaining{' '}
                        {coveragePercentage}%.
                    </div>
                ) : (
                    <div className="rounded-lg bg-red-50 p-3 text-sm text-red-800 dark:bg-red-950/20 dark:text-red-200">
                        This service is not covered by the insurance plan. Full
                        payment required from patient.
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
