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
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { useForm } from '@inertiajs/react';
import axios from 'axios';
import { Check, ChevronsUpDown, Loader2 } from 'lucide-react';
import { FormEvent, useCallback, useEffect, useState } from 'react';

interface NhisTariff {
    id: number;
    nhis_code: string;
    name: string;
    category: string;
    price: number;
    formatted_price: string;
    display_name: string;
}

interface UnmappedItem {
    id: number;
    code: string;
    name: string;
}

interface MappingFormProps {
    isOpen: boolean;
    onClose: () => void;
    itemTypes: string[];
}

const itemTypeLabels: Record<string, string> = {
    drug: 'Drug',
    lab_service: 'Lab Service',
    procedure: 'Procedure',
    consumable: 'Consumable',
};

export function MappingForm({ isOpen, onClose, itemTypes }: MappingFormProps) {
    const [tariffOpen, setTariffOpen] = useState(false);
    const [itemOpen, setItemOpen] = useState(false);
    const [tariffs, setTariffs] = useState<NhisTariff[]>([]);
    const [unmappedItems, setUnmappedItems] = useState<UnmappedItem[]>([]);
    const [tariffSearch, setTariffSearch] = useState('');
    const [itemSearch, setItemSearch] = useState('');
    const [loadingTariffs, setLoadingTariffs] = useState(false);
    const [loadingItems, setLoadingItems] = useState(false);
    const [selectedTariff, setSelectedTariff] = useState<NhisTariff | null>(
        null,
    );
    const [selectedItem, setSelectedItem] = useState<UnmappedItem | null>(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        item_type: 'drug',
        item_id: 0,
        nhis_tariff_id: 0,
    });

    // Search tariffs
    const searchTariffs = useCallback(async (search: string) => {
        setLoadingTariffs(true);
        try {
            const response = await axios.get('/api/nhis-tariffs/search', {
                params: { search, limit: 50 },
            });
            setTariffs(
                response.data.tariffs?.data || response.data.tariffs || [],
            );
        } catch (error) {
            console.error('Error searching tariffs:', error);
            setTariffs([]);
        } finally {
            setLoadingTariffs(false);
        }
    }, []);

    // Load unmapped items for selected type
    const loadUnmappedItems = useCallback(
        async (itemType: string, search?: string) => {
            setLoadingItems(true);
            try {
                const response = await axios.get(
                    '/admin/nhis-mappings/unmapped',
                    {
                        params: { item_type: itemType, search },
                        headers: { Accept: 'application/json' },
                    },
                );
                setUnmappedItems(response.data.items || []);
            } catch (error) {
                console.error('Error loading unmapped items:', error);
                setUnmappedItems([]);
            } finally {
                setLoadingItems(false);
            }
        },
        [],
    );

    // Load initial data when modal opens
    useEffect(() => {
        if (isOpen) {
            searchTariffs('');
            loadUnmappedItems(data.item_type);
        }
    }, [isOpen, searchTariffs, loadUnmappedItems, data.item_type]);

    // Debounced tariff search
    useEffect(() => {
        const timer = setTimeout(() => {
            if (tariffOpen) {
                searchTariffs(tariffSearch);
            }
        }, 300);
        return () => clearTimeout(timer);
    }, [tariffSearch, tariffOpen, searchTariffs]);

    // Debounced item search
    useEffect(() => {
        const timer = setTimeout(() => {
            if (itemOpen) {
                loadUnmappedItems(data.item_type, itemSearch);
            }
        }, 300);
        return () => clearTimeout(timer);
    }, [itemSearch, itemOpen, data.item_type, loadUnmappedItems]);

    const handleItemTypeChange = (value: string) => {
        setData('item_type', value);
        setSelectedItem(null);
        setData('item_id', 0);
        loadUnmappedItems(value);
    };

    const handleTariffSelect = (tariff: NhisTariff) => {
        setSelectedTariff(tariff);
        setData('nhis_tariff_id', tariff.id);
        setTariffOpen(false);
    };

    const handleItemSelect = (item: UnmappedItem) => {
        setSelectedItem(item);
        setData('item_id', item.id);
        setItemOpen(false);
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/nhis-mappings', {
            onSuccess: () => {
                handleClose();
            },
        });
    };

    const handleClose = () => {
        onClose();
        reset();
        setSelectedTariff(null);
        setSelectedItem(null);
        setTariffSearch('');
        setItemSearch('');
    };

    return (
        <Dialog open={isOpen} onOpenChange={handleClose}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Add NHIS Mapping</DialogTitle>
                    <DialogDescription>
                        Map a hospital item to an NHIS tariff code.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    {/* Item Type Selection */}
                    <div className="space-y-2">
                        <Label htmlFor="item_type">Item Type *</Label>
                        <Select
                            value={data.item_type}
                            onValueChange={handleItemTypeChange}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select item type" />
                            </SelectTrigger>
                            <SelectContent>
                                {itemTypes.map((type) => (
                                    <SelectItem key={type} value={type}>
                                        {itemTypeLabels[type] || type}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.item_type && (
                            <p className="text-sm text-red-600">
                                {errors.item_type}
                            </p>
                        )}
                    </div>

                    {/* Item Selection */}
                    <div className="space-y-2">
                        <Label>Hospital Item *</Label>
                        <Popover open={itemOpen} onOpenChange={setItemOpen}>
                            <PopoverTrigger asChild>
                                <Button
                                    variant="outline"
                                    role="combobox"
                                    aria-expanded={itemOpen}
                                    className="w-full justify-between"
                                >
                                    {selectedItem
                                        ? `${selectedItem.name} (${selectedItem.code})`
                                        : 'Select unmapped item...'}
                                    <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent
                                className="w-[400px] p-0"
                                align="start"
                            >
                                <Command shouldFilter={false}>
                                    <CommandInput
                                        placeholder="Search items..."
                                        value={itemSearch}
                                        onValueChange={setItemSearch}
                                    />
                                    <CommandList>
                                        {loadingItems ? (
                                            <div className="flex items-center justify-center py-6">
                                                <Loader2 className="h-4 w-4 animate-spin" />
                                                <span className="ml-2 text-sm text-gray-500">
                                                    Loading...
                                                </span>
                                            </div>
                                        ) : unmappedItems.length === 0 ? (
                                            <CommandEmpty>
                                                No unmapped items found.
                                            </CommandEmpty>
                                        ) : (
                                            <CommandGroup>
                                                {unmappedItems.map((item) => (
                                                    <CommandItem
                                                        key={item.id}
                                                        value={item.id.toString()}
                                                        onSelect={() =>
                                                            handleItemSelect(
                                                                item,
                                                            )
                                                        }
                                                    >
                                                        <Check
                                                            className={cn(
                                                                'mr-2 h-4 w-4',
                                                                selectedItem?.id ===
                                                                    item.id
                                                                    ? 'opacity-100'
                                                                    : 'opacity-0',
                                                            )}
                                                        />
                                                        <div className="flex flex-col">
                                                            <span>
                                                                {item.name}
                                                            </span>
                                                            <span className="font-mono text-xs text-gray-500">
                                                                {item.code}
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
                        {errors.item_id && (
                            <p className="text-sm text-red-600">
                                {errors.item_id}
                            </p>
                        )}
                    </div>

                    {/* NHIS Tariff Selection */}
                    <div className="space-y-2">
                        <Label>NHIS Tariff *</Label>
                        <Popover open={tariffOpen} onOpenChange={setTariffOpen}>
                            <PopoverTrigger asChild>
                                <Button
                                    variant="outline"
                                    role="combobox"
                                    aria-expanded={tariffOpen}
                                    className="w-full justify-between"
                                >
                                    {selectedTariff
                                        ? `${selectedTariff.name} (${selectedTariff.nhis_code})`
                                        : 'Select NHIS tariff...'}
                                    <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent
                                className="w-[400px] p-0"
                                align="start"
                            >
                                <Command shouldFilter={false}>
                                    <CommandInput
                                        placeholder="Search tariffs..."
                                        value={tariffSearch}
                                        onValueChange={setTariffSearch}
                                    />
                                    <CommandList>
                                        {loadingTariffs ? (
                                            <div className="flex items-center justify-center py-6">
                                                <Loader2 className="h-4 w-4 animate-spin" />
                                                <span className="ml-2 text-sm text-gray-500">
                                                    Loading...
                                                </span>
                                            </div>
                                        ) : tariffs.length === 0 ? (
                                            <CommandEmpty>
                                                No tariffs found.
                                            </CommandEmpty>
                                        ) : (
                                            <CommandGroup>
                                                {tariffs.map((tariff) => (
                                                    <CommandItem
                                                        key={tariff.id}
                                                        value={tariff.id.toString()}
                                                        onSelect={() =>
                                                            handleTariffSelect(
                                                                tariff,
                                                            )
                                                        }
                                                    >
                                                        <Check
                                                            className={cn(
                                                                'mr-2 h-4 w-4',
                                                                selectedTariff?.id ===
                                                                    tariff.id
                                                                    ? 'opacity-100'
                                                                    : 'opacity-0',
                                                            )}
                                                        />
                                                        <div className="flex flex-col">
                                                            <span>
                                                                {tariff.name}
                                                            </span>
                                                            <span className="text-xs text-gray-500">
                                                                <span className="font-mono">
                                                                    {
                                                                        tariff.nhis_code
                                                                    }
                                                                </span>
                                                                {' · '}
                                                                {
                                                                    tariff.formatted_price
                                                                }
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
                        {errors.nhis_tariff_id && (
                            <p className="text-sm text-red-600">
                                {errors.nhis_tariff_id}
                            </p>
                        )}
                    </div>

                    {/* Selected Summary */}
                    {selectedItem && selectedTariff && (
                        <div className="rounded-lg border bg-green-50 p-4 dark:border-green-800 dark:bg-green-950/30">
                            <h4 className="mb-2 font-medium text-green-900 dark:text-green-100">
                                Mapping Summary
                            </h4>
                            <div className="space-y-1 text-sm text-green-800 dark:text-green-200">
                                <p>
                                    <strong>Item:</strong> {selectedItem.name} (
                                    {selectedItem.code})
                                </p>
                                <p>
                                    <strong>NHIS Tariff:</strong>{' '}
                                    {selectedTariff.name} (
                                    {selectedTariff.nhis_code})
                                </p>
                                <p>
                                    <strong>NHIS Price:</strong>{' '}
                                    {selectedTariff.formatted_price}
                                </p>
                            </div>
                        </div>
                    )}

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={handleClose}
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={
                                processing || !selectedItem || !selectedTariff
                            }
                        >
                            {processing ? 'Creating...' : 'Create Mapping'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
