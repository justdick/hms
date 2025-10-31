import { cn } from '@/lib/utils';

interface InlineCoverageDisplayProps {
    isInsuranceClaim: boolean;
    insuranceCoveredAmount: number;
    patientCopayAmount: number;
    amount: number;
    compact?: boolean;
    className?: string;
}

export function InlineCoverageDisplay({
    isInsuranceClaim,
    insuranceCoveredAmount,
    patientCopayAmount,
    amount,
    compact = false,
    className,
}: InlineCoverageDisplayProps) {
    if (!isInsuranceClaim) {
        return (
            <div className={cn('text-sm', className)}>
                <span className="font-medium">GHS {amount.toFixed(2)}</span>
            </div>
        );
    }

    if (compact) {
        return (
            <div className={cn('space-y-0.5 text-sm', className)}>
                <div className="font-medium">GHS {amount.toFixed(2)}</div>
                <div className="text-xs text-muted-foreground">
                    Insurance: GHS {insuranceCoveredAmount.toFixed(2)} | Copay:
                    GHS {patientCopayAmount.toFixed(2)}
                </div>
            </div>
        );
    }

    return (
        <div className={cn('space-y-1 text-sm', className)}>
            <div className="flex justify-between">
                <span className="text-muted-foreground">Total:</span>
                <span className="font-medium">GHS {amount.toFixed(2)}</span>
            </div>
            <div className="flex justify-between">
                <span className="text-muted-foreground">Insurance:</span>
                <span className="font-medium text-green-600 dark:text-green-400">
                    GHS {insuranceCoveredAmount.toFixed(2)}
                </span>
            </div>
            <div className="flex justify-between">
                <span className="text-muted-foreground">Patient Copay:</span>
                <span className="font-medium text-orange-600 dark:text-orange-400">
                    GHS {patientCopayAmount.toFixed(2)}
                </span>
            </div>
        </div>
    );
}
