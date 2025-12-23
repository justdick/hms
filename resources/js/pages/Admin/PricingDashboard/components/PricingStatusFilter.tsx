import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { ChevronDown, CircleDollarSign } from 'lucide-react';

export type PricingStatusValue = 'all' | 'unpriced' | 'priced';

interface PricingStatusFilterProps {
    value: PricingStatusValue;
    onChange: (value: PricingStatusValue) => void;
}

const statusLabels: Record<PricingStatusValue, string> = {
    all: 'All Items',
    unpriced: 'Unpriced',
    priced: 'Priced',
};

const statusDescriptions: Record<PricingStatusValue, string> = {
    all: 'Show all items regardless of pricing status',
    unpriced: 'Items with no cash price set (null or zero)',
    priced: 'Items with a positive cash price',
};

export function PricingStatusFilter({
    value,
    onChange,
}: PricingStatusFilterProps) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" className="border-dashed">
                    <CircleDollarSign className="mr-2 h-4 w-4" />
                    Pricing Status
                    {value !== 'all' && (
                        <Badge
                            variant={
                                value === 'unpriced' ? 'destructive' : 'default'
                            }
                            className="ml-2"
                        >
                            {statusLabels[value]}
                        </Badge>
                    )}
                    <ChevronDown className="ml-2 h-4 w-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="w-56">
                <DropdownMenuLabel>Filter by Pricing Status</DropdownMenuLabel>
                <DropdownMenuSeparator />
                {(Object.keys(statusLabels) as PricingStatusValue[]).map(
                    (status) => (
                        <DropdownMenuCheckboxItem
                            key={status}
                            checked={value === status}
                            onCheckedChange={() => onChange(status)}
                        >
                            <div className="flex flex-col">
                                <span>{statusLabels[status]}</span>
                                <span className="text-xs text-muted-foreground">
                                    {statusDescriptions[status]}
                                </span>
                            </div>
                        </DropdownMenuCheckboxItem>
                    ),
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
