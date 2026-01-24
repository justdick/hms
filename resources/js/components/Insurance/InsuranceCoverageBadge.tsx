import { Badge } from '@/components/ui/badge';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { formatCurrency } from '@/lib/utils';
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
    const numAmount =
        typeof amount === 'number' ? amount : parseFloat(String(amount)) || 0;
    const numInsuranceCovered =
        typeof insuranceCoveredAmount === 'number'
            ? insuranceCoveredAmount
            : parseFloat(String(insuranceCoveredAmount)) || 0;
    const numPatientCopay =
        typeof patientCopayAmount === 'number'
            ? patientCopayAmount
            : parseFloat(String(patientCopayAmount)) || 0;

    // Total cost = tariff amount + patient copay (what insurance pays + what patient pays)
    const totalCost = numInsuranceCovered + numPatientCopay;

    // Coverage percentage based on total cost, not just tariff amount
    const coveragePercentage =
        totalCost > 0 ? Math.round((numInsuranceCovered / totalCost) * 100) : 0;

    // "Fully Covered" should only show when patient pays nothing
    const isFullyCovered = numPatientCopay === 0 && numInsuranceCovered > 0;

    const getBadgeVariant = () => {
        if (isFullyCovered) return 'default';
        if (coveragePercentage > 0) return 'secondary';
        return 'outline';
    };

    const getIcon = () => {
        if (isFullyCovered) return <ShieldCheck className="size-3" />;
        if (coveragePercentage > 0) return <ShieldAlert className="size-3" />;
        return <ShieldOff className="size-3" />;
    };

    const getBadgeText = () => {
        if (isFullyCovered) return 'Fully Covered';
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
                                Insurance Pays:
                            </span>
                            <span className="font-medium text-green-600 dark:text-green-400">
                                {formatCurrency(numInsuranceCovered)}
                            </span>
                        </div>
                        <div className="flex justify-between gap-4">
                            <span className="text-muted-foreground">
                                Patient Copay:
                            </span>
                            <span className="font-medium text-orange-600 dark:text-orange-400">
                                {formatCurrency(numPatientCopay)}
                            </span>
                        </div>
                        <div className="flex justify-between gap-4 border-t pt-1">
                            <span className="text-muted-foreground">
                                Total Cost:
                            </span>
                            <span className="font-medium">
                                {formatCurrency(totalCost)}
                            </span>
                        </div>
                        <div className="flex justify-between gap-4">
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
