import { Badge } from '@/components/ui/badge';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

export type PricingStatus =
    | 'priced'
    | 'unpriced'
    | 'nhis_mapped'
    | 'flexible_copay'
    | 'not_mapped';

interface PricingStatusBadgeProps {
    status: PricingStatus;
    showTooltip?: boolean;
    className?: string;
}

const statusConfig: Record<
    PricingStatus,
    {
        label: string;
        description: string;
        variant: 'default' | 'secondary' | 'destructive' | 'outline';
        className: string;
    }
> = {
    priced: {
        label: 'Priced',
        description: 'Item has a cash price set',
        variant: 'default',
        className: 'bg-green-600 hover:bg-green-700 text-white',
    },
    unpriced: {
        label: 'Unpriced',
        description: 'Item needs a cash price to be set',
        variant: 'destructive',
        className: '',
    },
    nhis_mapped: {
        label: 'NHIS Mapped',
        description: 'Item is mapped to an NHIS tariff code',
        variant: 'default',
        className: 'bg-blue-600 hover:bg-blue-700 text-white',
    },
    flexible_copay: {
        label: 'Flexible Copay',
        description: 'Unmapped item with custom copay set for NHIS patients',
        variant: 'secondary',
        className: 'bg-purple-600 hover:bg-purple-700 text-white',
    },
    not_mapped: {
        label: 'Not Mapped',
        description: 'Item is not mapped to NHIS tariff - patient pays full cash price',
        variant: 'outline',
        className: 'border-yellow-500 text-yellow-700 dark:text-yellow-400',
    },
};

export function PricingStatusBadge({
    status,
    showTooltip = true,
    className,
}: PricingStatusBadgeProps) {
    const config = statusConfig[status];

    const badge = (
        <Badge
            variant={config.variant}
            className={cn(config.className, className)}
        >
            {config.label}
        </Badge>
    );

    if (!showTooltip) {
        return badge;
    }

    return (
        <Tooltip>
            <TooltipTrigger asChild>{badge}</TooltipTrigger>
            <TooltipContent>
                <p>{config.description}</p>
            </TooltipContent>
        </Tooltip>
    );
}

/**
 * Determine the pricing status for an item based on its properties.
 */
export function determinePricingStatus(item: {
    cash_price: number | null;
    is_mapped?: boolean;
    has_flexible_copay?: boolean;
    is_nhis?: boolean;
}): PricingStatus {
    // Check if unpriced first (applies to all items)
    if (item.cash_price === null || item.cash_price <= 0) {
        return 'unpriced';
    }

    // For NHIS items, check mapping status
    if (item.is_nhis !== undefined) {
        if (item.is_mapped) {
            return 'nhis_mapped';
        }
        if (item.has_flexible_copay) {
            return 'flexible_copay';
        }
        return 'not_mapped';
    }

    // Default to priced for non-NHIS items with a price
    return 'priced';
}
