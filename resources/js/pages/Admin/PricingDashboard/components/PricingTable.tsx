import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { router } from '@inertiajs/react';
import { AlertCircle, Check, ExternalLink, History, X } from 'lucide-react';
import { KeyboardEvent, useRef, useState } from 'react';
import type { InsurancePlan, PricingItem } from '../Index';

interface PricingTableProps {
    items: PricingItem[];
    selectedPlan: InsurancePlan | null;
    isNhis: boolean;
    onViewHistory: (item: PricingItem) => void;
    onSelectionChange: (items: PricingItem[]) => void;
    selectedItems: PricingItem[];
}

interface EditingCell {
    itemId: number;
    field: 'cash_price' | 'copay_amount' | 'insurance_tariff' | 'coverage_value';
}

export function PricingTable({
    items,
    selectedPlan,
    isNhis,
    onViewHistory,
    onSelectionChange,
    selectedItems,
}: PricingTableProps) {
    const [editingCell, setEditingCell] = useState<EditingCell | null>(null);
    const [editValue, setEditValue] = useState('');
    const [saving, setSaving] = useState(false);
    const [successCell, setSuccessCell] = useState<EditingCell | null>(null);
    const [errorCell, setErrorCell] = useState<EditingCell | null>(null);
    const inputRef = useRef<HTMLInputElement>(null);

    const formatCurrency = (value: number | null): string => {
        if (value === null) return '-';
        return `GH₵ ${value.toFixed(2)}`;
    };

    const calculatePatientPays = (item: PricingItem): number | null => {
        if (!selectedPlan || isNhis) return null;
        
        const tariff = item.insurance_tariff ?? item.cash_price;
        const coverageValue = item.coverage_value ?? 0;
        const coverageType = item.coverage_type ?? 'percentage';
        const copay = item.copay_amount ?? 0;

        if (coverageType === 'percentage') {
            return tariff * ((100 - coverageValue) / 100) + copay;
        } else {
            return Math.max(0, tariff - coverageValue) + copay;
        }
    };

    const handleCellClick = (item: PricingItem, field: EditingCell['field']) => {
        // Don't allow editing copay for unmapped NHIS items
        if (isNhis && !item.is_mapped && field === 'copay_amount') {
            return;
        }
        
        // Don't allow editing NHIS tariff (read-only)
        if (isNhis && field === 'insurance_tariff') {
            return;
        }

        const currentValue = item[field];
        setEditingCell({ itemId: item.id, field });
        setEditValue(currentValue?.toString() ?? '');
        setTimeout(() => inputRef.current?.focus(), 0);
    };

    const handleSave = async (item: PricingItem) => {
        if (!editingCell) return;

        const numValue = parseFloat(editValue);
        if (isNaN(numValue) || numValue < 0) {
            setErrorCell(editingCell);
            setTimeout(() => {
                setErrorCell(null);
                setEditingCell(null);
            }, 400);
            return;
        }

        setSaving(true);

        const { field } = editingCell;
        let endpoint = '';
        let data: Record<string, any> = {};

        if (field === 'cash_price') {
            endpoint = '/admin/pricing-dashboard/cash-price';
            data = {
                item_type: item.type,
                item_id: item.id,
                price: numValue,
            };
        } else if (field === 'copay_amount' && selectedPlan) {
            endpoint = '/admin/pricing-dashboard/insurance-copay';
            data = {
                plan_id: selectedPlan.id,
                item_type: item.type,
                item_id: item.id,
                item_code: item.code,
                copay: numValue,
            };
        } else if ((field === 'insurance_tariff' || field === 'coverage_value') && selectedPlan) {
            endpoint = '/admin/pricing-dashboard/insurance-coverage';
            data = {
                plan_id: selectedPlan.id,
                item_type: item.type,
                item_id: item.id,
                item_code: item.code,
                [field === 'insurance_tariff' ? 'tariff_amount' : 'coverage_value']: numValue,
            };
        }

        router.put(endpoint, data, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                setSaving(false);
                setSuccessCell(editingCell);
                setEditingCell(null);
                setTimeout(() => setSuccessCell(null), 1000);
            },
            onError: () => {
                setSaving(false);
                setErrorCell(editingCell);
                setTimeout(() => {
                    setErrorCell(null);
                    setEditingCell(null);
                }, 400);
            },
        });
    };

    const handleCancel = () => {
        setEditingCell(null);
        setEditValue('');
    };

    const handleKeyDown = (e: KeyboardEvent<HTMLInputElement>, item: PricingItem) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            handleSave(item);
        } else if (e.key === 'Escape') {
            e.preventDefault();
            handleCancel();
        }
    };

    const isSelected = (item: PricingItem) =>
        selectedItems.some((i) => i.id === item.id && i.type === item.type);

    const handleSelectItem = (item: PricingItem, checked: boolean) => {
        if (checked) {
            onSelectionChange([...selectedItems, item]);
        } else {
            onSelectionChange(
                selectedItems.filter((i) => !(i.id === item.id && i.type === item.type)),
            );
        }
    };

    const handleSelectAll = (checked: boolean) => {
        if (checked) {
            // Only select items that can be edited (mapped for NHIS)
            const selectableItems = isNhis
                ? items.filter((item) => item.is_mapped)
                : items;
            onSelectionChange(selectableItems);
        } else {
            onSelectionChange([]);
        }
    };

    const renderEditableCell = (
        item: PricingItem,
        field: EditingCell['field'],
        value: number | null,
        disabled: boolean = false,
    ) => {
        const isEditing =
            editingCell?.itemId === item.id && editingCell?.field === field;
        const isSuccess =
            successCell?.itemId === item.id && successCell?.field === field;
        const isError =
            errorCell?.itemId === item.id && errorCell?.field === field;

        if (isEditing) {
            return (
                <div className="flex items-center gap-1">
                    <Input
                        ref={inputRef}
                        type="number"
                        value={editValue}
                        onChange={(e) => setEditValue(e.target.value)}
                        onKeyDown={(e) => handleKeyDown(e, item)}
                        onBlur={() => setTimeout(() => handleSave(item), 150)}
                        min={0}
                        step="0.01"
                        className="h-8 w-24 text-right"
                        disabled={saving}
                    />
                </div>
            );
        }

        return (
            <button
                onClick={() => !disabled && handleCellClick(item, field)}
                disabled={disabled}
                className={`inline-flex items-center gap-1 rounded px-2 py-1 text-right font-medium transition-all ${
                    disabled
                        ? 'cursor-not-allowed opacity-50'
                        : 'hover:bg-gray-100 dark:hover:bg-gray-800'
                } ${isSuccess ? 'animate-pulse text-green-600' : ''} ${
                    isError ? 'animate-shake text-red-600' : ''
                }`}
            >
                {formatCurrency(value)}
                {isSuccess && <Check className="h-3 w-3 text-green-600" />}
                {isError && <X className="h-3 w-3 text-red-600" />}
            </button>
        );
    };

    if (items.length === 0) {
        return (
            <div className="p-12 text-center">
                <AlertCircle className="mx-auto mb-4 h-16 w-16 text-gray-300 dark:text-gray-600" />
                <h3 className="mb-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                    No items found
                </h3>
                <p className="text-gray-600 dark:text-gray-400">
                    Try adjusting your filters or search criteria.
                </p>
            </div>
        );
    }

    return (
        <div className="overflow-x-auto">
            <Table>
                <TableHeader>
                    <TableRow>
                        {selectedPlan && (
                            <TableHead className="w-12">
                                <Checkbox
                                    checked={
                                        selectedItems.length > 0 &&
                                        selectedItems.length ===
                                            (isNhis
                                                ? items.filter((i) => i.is_mapped).length
                                                : items.length)
                                    }
                                    onCheckedChange={handleSelectAll}
                                />
                            </TableHead>
                        )}
                        <TableHead>Code</TableHead>
                        <TableHead>Name</TableHead>
                        <TableHead>Category</TableHead>
                        <TableHead className="text-right">Cash Price</TableHead>
                        {selectedPlan && (
                            <>
                                {isNhis ? (
                                    <>
                                        <TableHead>NHIS Code</TableHead>
                                        <TableHead className="text-right">
                                            NHIS Tariff
                                        </TableHead>
                                    </>
                                ) : (
                                    <>
                                        <TableHead className="text-right">
                                            Insurance Tariff
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Coverage %
                                        </TableHead>
                                    </>
                                )}
                                <TableHead className="text-right">
                                    Patient Copay
                                </TableHead>
                                {!isNhis && (
                                    <TableHead className="text-right">
                                        Patient Pays
                                    </TableHead>
                                )}
                            </>
                        )}
                        <TableHead className="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {items.map((item) => (
                        <TableRow
                            key={`${item.type}-${item.id}`}
                            className={
                                isNhis && !item.is_mapped
                                    ? 'bg-yellow-50 dark:bg-yellow-950/20'
                                    : ''
                            }
                        >
                            {selectedPlan && (
                                <TableCell>
                                    <Checkbox
                                        checked={isSelected(item)}
                                        onCheckedChange={(checked) =>
                                            handleSelectItem(item, checked === true)
                                        }
                                        disabled={isNhis && !item.is_mapped}
                                    />
                                </TableCell>
                            )}
                            <TableCell className="font-mono text-sm">
                                {item.code || '-'}
                            </TableCell>
                            <TableCell className="font-medium">{item.name}</TableCell>
                            <TableCell>
                                <Badge variant="outline">{item.category}</Badge>
                            </TableCell>
                            <TableCell className="text-right">
                                {renderEditableCell(item, 'cash_price', item.cash_price)}
                            </TableCell>
                            {selectedPlan && (
                                <>
                                    {isNhis ? (
                                        <>
                                            <TableCell className="font-mono text-sm">
                                                {item.is_mapped ? (
                                                    item.nhis_code
                                                ) : (
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <Badge
                                                                variant="destructive"
                                                                className="cursor-help"
                                                            >
                                                                Not Mapped
                                                            </Badge>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            <p>
                                                                This item needs to be mapped to an
                                                                NHIS tariff code
                                                            </p>
                                                        </TooltipContent>
                                                    </Tooltip>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {item.is_mapped
                                                    ? formatCurrency(item.insurance_tariff)
                                                    : '-'}
                                            </TableCell>
                                        </>
                                    ) : (
                                        <>
                                            <TableCell className="text-right">
                                                {renderEditableCell(
                                                    item,
                                                    'insurance_tariff',
                                                    item.insurance_tariff,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {renderEditableCell(
                                                    item,
                                                    'coverage_value',
                                                    item.coverage_value,
                                                )}
                                            </TableCell>
                                        </>
                                    )}
                                    <TableCell className="text-right">
                                        {renderEditableCell(
                                            item,
                                            'copay_amount',
                                            item.copay_amount,
                                            isNhis && !item.is_mapped,
                                        )}
                                    </TableCell>
                                    {!isNhis && (
                                        <TableCell className="text-right font-medium text-blue-600 dark:text-blue-400">
                                            {formatCurrency(calculatePatientPays(item))}
                                        </TableCell>
                                    )}
                                </>
                            )}
                            <TableCell className="text-right">
                                <div className="flex items-center justify-end gap-1">
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => onViewHistory(item)}
                                            >
                                                <History className="h-4 w-4" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>View price history</p>
                                        </TooltipContent>
                                    </Tooltip>
                                    {isNhis && !item.is_mapped && (
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        router.visit('/admin/nhis-mappings')
                                                    }
                                                >
                                                    <ExternalLink className="h-4 w-4" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <p>Go to NHIS Mappings</p>
                                            </TooltipContent>
                                        </Tooltip>
                                    )}
                                </div>
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}
