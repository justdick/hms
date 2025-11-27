import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import { Check, ChevronsUpDown, FileText } from 'lucide-react';
import { useState } from 'react';
import type { GdrgTariff } from './types';

interface GdrgSelectorProps {
    value: GdrgTariff | null;
    onChange: (tariff: GdrgTariff | null) => void;
    tariffs: GdrgTariff[];
    disabled?: boolean;
}

/**
 * GdrgSelector - Searchable dropdown for G-DRG tariff selection
 *
 * Features:
 * - Searchable by code or name
 * - Displays formatted options as "Name (Code - GHS Price)"
 * - Only shown for NHIS claims
 * - Required for NHIS claim approval
 *
 * @example
 * ```tsx
 * <GdrgSelector
 *   value={selectedGdrg}
 *   onChange={setSelectedGdrg}
 *   tariffs={gdrgTariffs}
 *   disabled={processing}
 * />
 * ```
 */
export function GdrgSelector({
    value,
    onChange,
    tariffs,
    disabled = false,
}: GdrgSelectorProps) {
    const [open, setOpen] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-GH', {
            style: 'currency',
            currency: 'GHS',
        }).format(amount);
    };

    const formatDisplayName = (tariff: GdrgTariff) => {
        return `${tariff.name} (${tariff.code} - ${formatCurrency(tariff.tariff_price)})`;
    };

    // Filter tariffs based on search query
    const filteredTariffs = tariffs.filter((tariff) => {
        const query = searchQuery.toLowerCase();
        return (
            tariff.code.toLowerCase().includes(query) ||
            tariff.name.toLowerCase().includes(query)
        );
    });

    return (
        <section aria-labelledby="gdrg-selector-heading">
            <h3
                id="gdrg-selector-heading"
                className="mb-4 flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-gray-100"
            >
                <FileText className="h-5 w-5" aria-hidden="true" />
                G-DRG Selection
                <span className="text-sm font-normal text-red-500">*</span>
            </h3>

            <div className="space-y-2">
                <Label htmlFor="gdrg-selector">
                    Select G-DRG Tariff
                    <span className="ml-1 text-xs text-gray-500">
                        (Required for NHIS claims)
                    </span>
                </Label>

                <Popover open={open} onOpenChange={setOpen}>
                    <PopoverTrigger asChild>
                        <Button
                            id="gdrg-selector"
                            variant="outline"
                            role="combobox"
                            aria-expanded={open}
                            aria-label="Select G-DRG tariff"
                            className="w-full justify-between"
                            disabled={disabled}
                        >
                            {value ? (
                                <span className="truncate">
                                    {formatDisplayName(value)}
                                </span>
                            ) : (
                                <span className="text-gray-500">
                                    Search and select G-DRG...
                                </span>
                            )}
                            <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent className="w-[500px] p-0" align="start">
                        <Command shouldFilter={false}>
                            <CommandInput
                                placeholder="Search by code or name..."
                                value={searchQuery}
                                onValueChange={setSearchQuery}
                            />
                            <CommandList>
                                <CommandEmpty>
                                    No G-DRG tariff found.
                                </CommandEmpty>
                                <CommandGroup>
                                    {filteredTariffs.map((tariff) => (
                                        <CommandItem
                                            key={tariff.id}
                                            value={tariff.code}
                                            onSelect={() => {
                                                onChange(tariff);
                                                setOpen(false);
                                                setSearchQuery('');
                                            }}
                                            className="flex items-center justify-between"
                                        >
                                            <div className="flex flex-col">
                                                <span className="font-medium">
                                                    {tariff.name}
                                                </span>
                                                <span className="text-sm text-gray-500">
                                                    {tariff.code} -{' '}
                                                    {formatCurrency(
                                                        tariff.tariff_price,
                                                    )}
                                                </span>
                                            </div>
                                            <Check
                                                className={cn(
                                                    'h-4 w-4',
                                                    value?.id === tariff.id
                                                        ? 'opacity-100'
                                                        : 'opacity-0',
                                                )}
                                            />
                                        </CommandItem>
                                    ))}
                                </CommandGroup>
                            </CommandList>
                        </Command>
                    </PopoverContent>
                </Popover>

                {/* Selected G-DRG Summary */}
                {value && (
                    <div className="mt-3 rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-800 dark:bg-blue-950">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="font-medium text-blue-900 dark:text-blue-100">
                                    {value.name}
                                </p>
                                <p className="text-sm text-blue-700 dark:text-blue-300">
                                    Code: {value.code}
                                </p>
                            </div>
                            <div className="text-right">
                                <p className="text-lg font-bold text-blue-900 dark:text-blue-100">
                                    {formatCurrency(value.tariff_price)}
                                </p>
                                <p className="text-xs text-blue-600 dark:text-blue-400">
                                    G-DRG Tariff
                                </p>
                            </div>
                        </div>
                    </div>
                )}

                {!value && (
                    <p className="text-sm text-yellow-600 dark:text-yellow-400">
                        ⚠️ G-DRG selection is required to approve this NHIS
                        claim.
                    </p>
                )}
            </div>
        </section>
    );
}
