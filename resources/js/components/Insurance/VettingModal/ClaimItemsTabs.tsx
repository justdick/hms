import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatCurrency } from '@/lib/utils';
import {
    AlertTriangle,
    Beaker,
    Loader2,
    Pill,
    Plus,
    Scissors,
    Trash2,
} from 'lucide-react';
import { useCallback, useState } from 'react';
import type { ClaimItem, NhisTariffOption } from './types';

interface ClaimItemsTabsProps {
    claimId: number;
    items: {
        investigations: ClaimItem[];
        prescriptions: ClaimItem[];
        procedures: ClaimItem[];
    };
    isNhis: boolean;
    disabled?: boolean;
    onItemsChange: (items: {
        investigations: ClaimItem[];
        prescriptions: ClaimItem[];
        procedures: ClaimItem[];
    }) => void;
}

type ItemCategory = 'investigations' | 'prescriptions' | 'procedures';

/**
 * ClaimItemsTabs - Tabbed display of claim items with add/delete functionality
 */
export function ClaimItemsTabs({
    claimId,
    items,
    isNhis,
    disabled = false,
    onItemsChange,
}: ClaimItemsTabsProps) {
    const [deletingId, setDeletingId] = useState<number | null>(null);
    const [addingCategory, setAddingCategory] = useState<ItemCategory | null>(
        null,
    );
    const [searchOpen, setSearchOpen] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [searchResults, setSearchResults] = useState<NhisTariffOption[]>([]);
    const [searching, setSearching] = useState(false);
    const [addingItem, setAddingItem] = useState(false);

    const formatCurrencyOrDash = (amount: number | null) => {
        if (amount === null) return '-';
        return formatCurrency(amount);
    };

    const calculateSubtotal = (categoryItems: ClaimItem[]) => {
        if (isNhis) {
            return categoryItems
                .filter((item) => item.is_covered)
                .reduce(
                    (sum, item) => sum + (item.nhis_price || 0) * item.quantity,
                    0,
                );
        }
        return categoryItems.reduce((sum, item) => sum + item.subtotal, 0);
    };

    const countUncovered = (categoryItems: ClaimItem[]) => {
        return categoryItems.filter((item) => !item.is_covered).length;
    };

    const getCategoryForNhis = (category: ItemCategory): string | null => {
        switch (category) {
            case 'investigations':
                return 'lab';
            case 'prescriptions':
                return 'medicine';
            case 'procedures':
                return 'procedure';
        }
    };

    const handleSearch = useCallback(
        async (query: string, category: ItemCategory) => {
            setSearchQuery(query);

            if (query.length < 2) {
                setSearchResults([]);
                return;
            }

            setSearching(true);

            try {
                // Use GDRG tariffs for investigations and procedures, NHIS for prescriptions
                const useGdrg =
                    category === 'investigations' || category === 'procedures';
                const baseUrl = useGdrg
                    ? '/api/gdrg-tariffs/search'
                    : '/api/nhis-tariffs/search';
                const url = `${baseUrl}?search=${encodeURIComponent(query)}`;

                const response = await fetch(url, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (response.ok) {
                    const data = await response.json();
                    // Handle different response formats and map GDRG to common format
                    let results =
                        data.tariffs?.data || data.tariffs || data.data || [];

                    // Map GDRG tariffs to NhisTariffOption format
                    if (useGdrg && results.length > 0) {
                        results = results.map(
                            (t: {
                                id: number;
                                code: string;
                                name: string;
                                tariff_price: number;
                            }) => ({
                                id: t.id,
                                nhis_code: t.code,
                                name: t.name,
                                category:
                                    category === 'investigations'
                                        ? 'lab'
                                        : 'procedure',
                                price: t.tariff_price,
                            }),
                        );
                    }

                    setSearchResults(results);
                }
            } catch (error) {
                console.error('Failed to search tariffs:', error);
            } finally {
                setSearching(false);
            }
        },
        [],
    );

    const handleAddItem = async (
        tariff: NhisTariffOption,
        category: ItemCategory,
    ) => {
        setAddingItem(true);

        try {
            // Use gdrg_tariff_id for investigations and procedures, nhis_tariff_id for prescriptions
            const useGdrg =
                category === 'investigations' || category === 'procedures';
            const payload = useGdrg
                ? { gdrg_tariff_id: tariff.id, quantity: 1 }
                : { nhis_tariff_id: tariff.id, quantity: 1 };

            const response = await fetch(
                `/admin/insurance/claims/${claimId}/items`,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN':
                            document.querySelector<HTMLMetaElement>(
                                'meta[name="csrf-token"]',
                            )?.content || '',
                    },
                    body: JSON.stringify(payload),
                },
            );

            if (response.ok) {
                const data = await response.json();
                const newItem: ClaimItem = data.item;

                // Add to the appropriate category
                const updatedItems = { ...items };
                updatedItems[category] = [...updatedItems[category], newItem];
                onItemsChange(updatedItems);

                setSearchOpen(false);
                setSearchQuery('');
                setSearchResults([]);
                setAddingCategory(null);
            } else {
                const error = await response.json();
                alert(error.message || 'Failed to add item');
            }
        } catch (error) {
            console.error('Failed to add item:', error);
            alert('Failed to add item');
        } finally {
            setAddingItem(false);
        }
    };

    const handleDeleteItem = async (itemId: number, category: ItemCategory) => {
        if (!confirm('Remove this item from the claim?')) return;

        setDeletingId(itemId);

        try {
            const response = await fetch(
                `/admin/insurance/claims/${claimId}/items/${itemId}`,
                {
                    method: 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN':
                            document.querySelector<HTMLMetaElement>(
                                'meta[name="csrf-token"]',
                            )?.content || '',
                    },
                },
            );

            if (response.ok) {
                // Remove from the appropriate category
                const updatedItems = { ...items };
                updatedItems[category] = updatedItems[category].filter(
                    (item) => item.id !== itemId,
                );
                onItemsChange(updatedItems);
            } else {
                const error = await response.json();
                alert(error.message || 'Failed to remove item');
            }
        } catch (error) {
            console.error('Failed to remove item:', error);
            alert('Failed to remove item');
        } finally {
            setDeletingId(null);
        }
    };

    const renderAddButton = (category: ItemCategory) => {
        if (disabled || !isNhis) return null;

        return (
            <Popover
                open={searchOpen && addingCategory === category}
                onOpenChange={(open) => {
                    setSearchOpen(open);
                    if (open) {
                        setAddingCategory(category);
                    } else {
                        setAddingCategory(null);
                        setSearchQuery('');
                        setSearchResults([]);
                    }
                }}
            >
                <PopoverTrigger asChild>
                    <Button
                        size="sm"
                        className="h-7 bg-blue-600 text-xs hover:bg-blue-700"
                    >
                        <Plus className="mr-1 h-3 w-3" />
                        Add
                    </Button>
                </PopoverTrigger>
                <PopoverContent className="w-[450px] p-0" align="end">
                    <Command shouldFilter={false}>
                        <CommandInput
                            placeholder="Search NHIS tariffs..."
                            value={searchQuery}
                            onValueChange={(q) => handleSearch(q, category)}
                        />
                        <CommandList>
                            {searching ? (
                                <div className="flex items-center justify-center p-4">
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                    <span className="ml-2 text-sm text-gray-500">
                                        Searching...
                                    </span>
                                </div>
                            ) : searchQuery.length < 2 ? (
                                <div className="p-4 text-center text-sm text-gray-500">
                                    Type at least 2 characters to search
                                </div>
                            ) : searchResults.length === 0 ? (
                                <CommandEmpty>No tariffs found.</CommandEmpty>
                            ) : (
                                <CommandGroup>
                                    {searchResults.map((tariff) => (
                                        <CommandItem
                                            key={tariff.id}
                                            value={tariff.nhis_code}
                                            onSelect={() =>
                                                handleAddItem(tariff, category)
                                            }
                                            disabled={addingItem}
                                        >
                                            <div className="flex w-full items-center justify-between">
                                                <div className="flex flex-col">
                                                    <span className="font-medium">
                                                        {tariff.name}
                                                    </span>
                                                    <span className="text-sm text-gray-500">
                                                        {tariff.nhis_code}
                                                    </span>
                                                </div>
                                                <span className="font-medium text-green-600">
                                                    {formatCurrency(
                                                        tariff.price,
                                                    )}
                                                </span>
                                            </div>
                                        </CommandItem>
                                    ))}
                                </CommandGroup>
                            )}
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>
        );
    };

    const renderItemsTable = (
        categoryItems: ClaimItem[],
        category: ItemCategory,
        emptyMessage: string,
    ) => {
        const subtotal = calculateSubtotal(categoryItems);
        const uncoveredCount = countUncovered(categoryItems);

        return (
            <div className="space-y-3">
                {/* Header with Add button */}
                <div className="flex items-center justify-end">
                    {renderAddButton(category)}
                </div>

                {categoryItems.length === 0 ? (
                    <div className="py-8 text-center text-gray-500 dark:text-gray-400">
                        {emptyMessage}
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-[40%]">
                                        Item
                                    </TableHead>
                                    {isNhis && <TableHead>NHIS Code</TableHead>}
                                    <TableHead className="text-right">
                                        Qty
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {isNhis ? 'NHIS Price' : 'Unit Price'}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Subtotal
                                    </TableHead>
                                    {!disabled && (
                                        <TableHead className="w-[60px]"></TableHead>
                                    )}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {categoryItems.map((item) => (
                                    <TableRow
                                        key={item.id}
                                        className={
                                            !item.is_covered
                                                ? 'bg-red-50 dark:bg-red-950/20'
                                                : ''
                                        }
                                    >
                                        <TableCell>
                                            <div>
                                                <p className="font-medium">
                                                    {item.name}
                                                </p>
                                                {item.code && (
                                                    <p className="text-xs text-gray-500">
                                                        Code: {item.code}
                                                    </p>
                                                )}
                                            </div>
                                        </TableCell>
                                        {isNhis && (
                                            <TableCell>
                                                {item.is_covered ? (
                                                    <span className="font-mono text-sm">
                                                        {item.nhis_code || '-'}
                                                    </span>
                                                ) : (
                                                    <Badge
                                                        variant="destructive"
                                                        className="flex w-fit items-center gap-1"
                                                    >
                                                        <AlertTriangle className="h-3 w-3" />
                                                        Not Covered
                                                    </Badge>
                                                )}
                                            </TableCell>
                                        )}
                                        <TableCell className="text-right">
                                            {item.quantity}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {isNhis
                                                ? formatCurrency(
                                                      item.nhis_price,
                                                  )
                                                : formatCurrency(
                                                      item.unit_price,
                                                  )}
                                        </TableCell>
                                        <TableCell className="text-right font-medium">
                                            {item.is_covered
                                                ? formatCurrency(
                                                      (isNhis
                                                          ? item.nhis_price || 0
                                                          : item.unit_price) *
                                                          item.quantity,
                                                  )
                                                : '-'}
                                        </TableCell>
                                        {!disabled && (
                                            <TableCell>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        handleDeleteItem(
                                                            item.id,
                                                            category,
                                                        )
                                                    }
                                                    disabled={
                                                        deletingId === item.id
                                                    }
                                                    className="text-red-500 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950"
                                                    aria-label={`Remove ${item.name}`}
                                                >
                                                    {deletingId === item.id ? (
                                                        <Loader2 className="h-4 w-4 animate-spin" />
                                                    ) : (
                                                        <Trash2 className="h-4 w-4" />
                                                    )}
                                                </Button>
                                            </TableCell>
                                        )}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                {/* Category Summary */}
                <div className="flex items-center justify-between rounded-lg bg-gray-50 px-4 py-2 dark:bg-gray-900">
                    <div className="flex items-center gap-4">
                        <span className="text-sm text-gray-600 dark:text-gray-400">
                            {categoryItems.length} item
                            {categoryItems.length !== 1 ? 's' : ''}
                        </span>
                        {isNhis && uncoveredCount > 0 && (
                            <span className="flex items-center gap-1 text-sm text-red-600 dark:text-red-400">
                                <AlertTriangle className="h-4 w-4" />
                                {uncoveredCount} not covered
                            </span>
                        )}
                    </div>
                    <div className="text-right">
                        <span className="text-sm text-gray-600 dark:text-gray-400">
                            Subtotal:{' '}
                        </span>
                        <span className="font-semibold">
                            {formatCurrency(subtotal)}
                        </span>
                    </div>
                </div>

                <p className="text-xs text-gray-500 dark:text-gray-400">
                    Changes to items only affect this claim, not the original
                    consultation.
                </p>
            </div>
        );
    };

    return (
        <section aria-labelledby="claim-items-heading">
            <h3
                id="claim-items-heading"
                className="mb-4 text-lg font-semibold text-gray-900 dark:text-gray-100"
            >
                Claim Items
            </h3>

            <Tabs defaultValue="investigations" className="w-full">
                <TabsList className="grid w-full grid-cols-3">
                    <TabsTrigger
                        value="investigations"
                        className="flex items-center gap-2"
                    >
                        <Beaker className="h-4 w-4" />
                        Investigations
                        <Badge variant="secondary" className="ml-1">
                            {items.investigations.length}
                        </Badge>
                    </TabsTrigger>
                    <TabsTrigger
                        value="prescriptions"
                        className="flex items-center gap-2"
                    >
                        <Pill className="h-4 w-4" />
                        Prescriptions
                        <Badge variant="secondary" className="ml-1">
                            {items.prescriptions.length}
                        </Badge>
                    </TabsTrigger>
                    <TabsTrigger
                        value="procedures"
                        className="flex items-center gap-2"
                    >
                        <Scissors className="h-4 w-4" />
                        Procedures
                        <Badge variant="secondary" className="ml-1">
                            {items.procedures.length}
                        </Badge>
                    </TabsTrigger>
                </TabsList>

                <TabsContent value="investigations" className="mt-4">
                    {renderItemsTable(
                        items.investigations,
                        'investigations',
                        'No investigations for this claim',
                    )}
                </TabsContent>

                <TabsContent value="prescriptions" className="mt-4">
                    {renderItemsTable(
                        items.prescriptions,
                        'prescriptions',
                        'No prescriptions for this claim',
                    )}
                </TabsContent>

                <TabsContent value="procedures" className="mt-4">
                    {renderItemsTable(
                        items.procedures,
                        'procedures',
                        'No procedures for this claim',
                    )}
                </TabsContent>
            </Tabs>
        </section>
    );
}
