import { Badge } from '@/components/ui/badge';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { ShieldAlert, ShieldCheck, ShieldOff } from 'lucide-react';

interface InsuranceCoverageBadgeProps {
    isInsuranceClaim: boolean;
    insuranceCoveredAmount: number;
    patientCopayAmount: number;
    amount: number;
    showTooltip?: boolean;
    className?: string;
}

export function InsuranceCoverageBadge({
    isInsuranceClaim,
    insuranceCoveredAmount,
    patientCopayAmount,
    amount,
    showTooltip = true,
    className,
}: InsuranceCoverageBadgeProps) {
    if (!isInsuranceClaim) {
        return null;
    }

    // Ensure all values are numbers
    const numAmount = typeof amount === 'number' ? amount : parseFloat(String(amount)) || 0;
    const numInsuranceCovered = typeof insuranceCoveredAmount === 'number' ? insuranceCoveredAmount : parseFloat(String(insuranceCoveredAmount)) || 0;
    const numPatientCopay = typeof patientCopayAmount === 'number' ? patientCopayAmount : parseFloat(String(patientCopayAmount)) || 0;

    const coveragePercentage =
        numAmount > 0 ? Math.round((numInsuranceCovered / numAmount) * 100) : 0;

    const getBadgeVariant = () => {
        if (coveragePercentage >= 100) return 'default';
        if (coveragePercentage > 0) return 'secondary';
        return 'outline';
    };

    const getIcon = () => {
        if (coveragePercentage >= 100)
            return <ShieldCheck className="size-3" />;
        if (coveragePercentage > 0) return <ShieldAlert className="size-3" />;
        return <ShieldOff className="size-3" />;
    };

    const getBadgeText = () => {
        if (coveragePercentage >= 100) return 'Fully Covered';
        if (coveragePercentage > 0) return `${coveragePercentage}% Covered`;
        return 'Not Covered';
    };

    const badge = (
        <Badge variant={getBadgeVariant()} className={className}>
            {getIcon()}
            {getBadgeText()}
        </Badge>
    );

    if (!showTooltip) {
        return badge;
    }

    return (
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger asChild>{badge}</TooltipTrigger>
                <TooltipContent className="space-y-1">
                    <div className="font-semibold">
                        Insurance Coverage Breakdown
                    </div>
                    <div className="grid gap-1 text-sm">
                        <div className="flex justify-between gap-4">
                            <span className="text-muted-foreground">
                                Total Amount:
                            </span>
                            <span className="font-medium">
                                GHS {numAmount.toFixed(2)}
                            </span>
                        </div>
                        <div className="flex justify-between gap-4">
                            <span className="text-muted-foreground">
                                Insurance Pays:
                            </span>
                            <span className="font-medium text-green-600 dark:text-green-400">
                                GHS {numInsuranceCovered.toFixed(2)}
                            </span>
                        </div>
                        <div className="flex justify-between gap-4">
                            <span className="text-muted-foreground">
                                Patient Pays:
                            </span>
                            <span className="font-medium text-orange-600 dark:text-orange-400">
                                GHS {numPatientCopay.toFixed(2)}
                            </span>
                        </div>
                        <div className="flex justify-between gap-4 border-t pt-1">
                            <span className="text-muted-foreground">
                                Coverage:
                            </span>
                            <span className="font-medium">
                                {coveragePercentage}%
                            </span>
                        </div>
                    </div>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}
