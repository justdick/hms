import { Badge } from '@/components/ui/badge';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { formatCurrency } from '@/lib/utils';
import { CreditCard, Star } from 'lucide-react';

interface PatientCreditBadgeProps {
    isCreditEligible: boolean;
    totalOwing?: number;
    creditReason?: string | null;
    creditAuthorizedBy?: string | null;
    creditAuthorizedAt?: string | null;
    showTooltip?: boolean;
    className?: string;
}

export function PatientCreditBadge({
    isCreditEligible,
    totalOwing = 0,
    creditReason,
    creditAuthorizedBy,
    creditAuthorizedAt,
    showTooltip = true,
    className,
}: PatientCreditBadgeProps) {
    if (!isCreditEligible) {
        return null;
    }

    const numTotalOwing =
        typeof totalOwing === 'number'
            ? totalOwing
            : parseFloat(String(totalOwing)) || 0;

    const badge = (
        <Badge
            variant="default"
            className={`bg-amber-500 text-white hover:bg-amber-600 ${className || ''}`}
        >
            <Star className="size-3 fill-current" />
            Credit Account
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
                    <div className="flex items-center gap-2 font-semibold">
                        <CreditCard className="size-4" />
                        Credit Account Details
                    </div>
                    <div className="grid gap-1 text-sm">
                        {numTotalOwing > 0 && (
                            <div className="flex justify-between gap-4">
                                <span className="text-muted-foreground">
                                    Total Owing:
                                </span>
                                <span className="font-medium text-orange-600 dark:text-orange-400">
                                    {formatCurrency(numTotalOwing)}
                                </span>
                            </div>
                        )}
                        {creditReason && (
                            <div className="flex justify-between gap-4">
                                <span className="text-muted-foreground">
                                    Reason:
                                </span>
                                <span className="max-w-[200px] truncate font-medium">
                                    {creditReason}
                                </span>
                            </div>
                        )}
                        {creditAuthorizedBy && (
                            <div className="flex justify-between gap-4">
                                <span className="text-muted-foreground">
                                    Authorized By:
                                </span>
                                <span className="font-medium">
                                    {creditAuthorizedBy}
                                </span>
                            </div>
                        )}
                        {creditAuthorizedAt && (
                            <div className="flex justify-between gap-4">
                                <span className="text-muted-foreground">
                                    Authorized At:
                                </span>
                                <span className="font-medium">
                                    {new Date(
                                        creditAuthorizedAt,
                                    ).toLocaleDateString()}
                                </span>
                            </div>
                        )}
                    </div>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}
