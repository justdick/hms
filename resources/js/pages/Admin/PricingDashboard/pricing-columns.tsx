'use client';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { ArrowUpDown, Check, ExternalLink, History, X } from 'lucide-react';
import { KeyboardEvent, useRef, useState } from 'react';
import { PricingStatusBadge } from './components/PricingStatusBadge';
import type { InsurancePlan, PricingItem } from './Index';

interface EditingCell {
    itemId: number;
    itemType: string;
    field:
        | 'cash_price'
        | 'copay_amount'
        | 'insurance_tariff'
        | 'coverage_value';
}

// Shared state for editing cells
let editingCell: EditingCell | null = null;
let setEditingCellFn: ((cell: EditingCell | null) => void) | null = null;

export function useEditingState() {
    const [cell, setCell] = useState<EditingCell | null>(null);
    editingCell = cell;
    setEditingCellFn = setCell;
    return [cell, setCell] as const;
}

const formatCurrency = (value: number | null): string => {
    if (value === null || value === undefined) return '-';
    return `GH₵ ${value.toFixed(2)}`;
};

// Editable cell component
function EditableCell({
    item,
    field,
    value,
    disabled = false,
    selectedPlan,
    isNhis = false,
    isUnmappedCopay = false,
}: {
    item: PricingItem;
    field: EditingCell['field'];
    value: number | null;
    disabled?: boolean;
    selectedPlan: InsurancePlan | null;
    isNhis?: boolean;
    isUnmappedCopay?: boolean;
}) {
    const [isEditing, setIsEditing] = useState(false);
    const [editValue, setEditValue] = useState('');
    const [saving, setSaving] = useState(false);
    const [showSuccess, setShowSuccess] = useState(false);
    const [showError, setShowError] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);

    const handleClick = () => {
        if (disabled) return;
        setIsEditing(true);
        setEditValue(value?.toString() ?? '');
        setTimeout(() => inputRef.current?.focus(), 0);
    };

    const handleSave = async () => {
        const numValue = parseFloat(editValue);
        if (isNaN(numValue) || numValue < 0) {
            setShowError(true);
            setTimeout(() => {
                setShowError(false);
                setIsEditing(false);
            }, 400);
            return;
        }

        setSaving(true);

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
            // Use flexible copay endpoint for unmapped NHIS items
            if (isUnmappedCopay) {
                endpoint = '/admin/pricing-dashboard/flexible-copay';
                data = {
                    plan_id: selectedPlan.id,
                    item_type: item.type,
                    item_id: item.id,
                    item_code: item.code,
                    copay_amount: numValue,
                };
            } else {
                endpoint = '/admin/pricing-dashboard/insurance-copay';
                data = {
                    plan_id: selectedPlan.id,
                    item_type: item.type,
                    item_id: item.id,
                    item_code: item.code,
                    copay: numValue,
                };
            }
        } else if (
            (field === 'insurance_tariff' || field === 'coverage_value') &&
            selectedPlan
        ) {
            endpoint = '/admin/pricing-dashboard/insurance-coverage';
            data = {
                plan_id: selectedPlan.id,
                item_type: item.type,
                item_id: item.id,
                item_code: item.code,
                [field === 'insurance_tariff'
                    ? 'tariff_amount'
                    : 'coverage_value']: numValue,
            };
        }

        router.put(endpoint, data, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                setSaving(false);
                setShowSuccess(true);
                setIsEditing(false);
                setTimeout(() => setShowSuccess(false), 1000);
            },
            onError: () => {
                setSaving(false);
                setShowError(true);
                setTimeout(() => {
                    setShowError(false);
                    setIsEditing(false);
                }, 400);
            },
        });
    };

    const handleKeyDown = (e: KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            handleSave();
        } else if (e.key === 'Escape') {
            e.preventDefault();
            setIsEditing(false);
        }
    };

    if (isEditing) {
        return (
            <Input
                ref={inputRef}
                type="number"
                value={editValue}
                onChange={(e) => setEditValue(e.target.value)}
                onKeyDown={handleKeyDown}
                onBlur={() => setTimeout(handleSave, 150)}
                min={0}
                step="0.01"
                className="h-8 w-24 text-right"
                disabled={saving}
            />
        );
    }

    return (
        <button
            onClick={handleClick}
            disabled={disabled}
            className={`inline-flex items-center gap-1 rounded px-2 py-1 text-right font-medium transition-all ${
                disabled
                    ? 'cursor-not-allowed opacity-50'
                    : 'hover:bg-gray-100 dark:hover:bg-gray-800'
            } ${showSuccess ? 'animate-pulse text-green-600' : ''} ${
                showError ? 'animate-shake text-red-600' : ''
            }`}
        >
            {formatCurrency(value)}
            {showSuccess && <Check className="h-3 w-3 text-green-600" />}
            {showError && <X className="h-3 w-3 text-red-600" />}
        </button>
    );
}

export function createPricingColumns(
    selectedPlan: InsurancePlan | null,
    isNhis: boolean,
    onViewHistory: (item: PricingItem) => void,
    onSelectionChange: (items: PricingItem[]) => void,
    selectedItems: PricingItem[],
): ColumnDef<PricingItem>[] {
    const isSelected = (item: PricingItem) =>
        selectedItems.some((i) => i.id === item.id && i.type === item.type);

    const handleSelectItem = (item: PricingItem, checked: boolean) => {
        if (checked) {
            onSelectionChange([...selectedItems, item]);
        } else {
            onSelectionChange(
                selectedItems.filter(
                    (i) => !(i.id === item.id && i.type === item.type),
                ),
            );
        }
    };

    const calculatePatientPays = (item: PricingItem): number | null => {
        if (!selectedPlan || isNhis) return null;

        const tariff = item.insurance_tariff ?? item.cash_price ?? 0;
        const coverageValue = item.coverage_value ?? 0;
        const coverageType = item.coverage_type ?? 'percentage';
        const copay = item.copay_amount ?? 0;

        if (coverageType === 'percentage') {
            return tariff * ((100 - coverageValue) / 100) + copay;
        } else {
            return Math.max(0, tariff - coverageValue) + copay;
        }
    };

    const columns: ColumnDef<PricingItem>[] = [];

    // Selection column (only when plan is selected)
    // Allow selection of ALL items including unmapped ones for bulk operations
    if (selectedPlan) {
        columns.push({
            id: 'select',
            header: ({ table }) => (
                <Checkbox
                    checked={
                        table.getFilteredSelectedRowModel().rows.length > 0 &&
                        table.getFilteredSelectedRowModel().rows.length ===
                            table.getFilteredRowModel().rows.length
                    }
                    onCheckedChange={(value) => {
                        if (value) {
                            onSelectionChange(
                                table
                                    .getFilteredRowModel()
                                    .rows.map((row) => row.original),
                            );
                        } else {
                            onSelectionChange([]);
                        }
                    }}
                    aria-label="Select all"
                />
            ),
            cell: ({ row }) => (
                <Checkbox
                    checked={isSelected(row.original)}
                    onCheckedChange={(checked) =>
                        handleSelectItem(row.original, checked === true)
                    }
                    aria-label="Select row"
                />
            ),
            enableSorting: false,
            enableHiding: false,
        });
    }

    // Code column
    columns.push({
        accessorKey: 'code',
        header: ({ column }) => (
            <Button
                variant="ghost"
                onClick={() =>
                    column.toggleSorting(column.getIsSorted() === 'asc')
                }
            >
                Code
                <ArrowUpDown className="ml-2 h-4 w-4" />
            </Button>
        ),
        cell: ({ row }) => (
            <span className="font-mono text-sm">
                {row.original.code || '-'}
            </span>
        ),
    });

    // Name column
    columns.push({
        accessorKey: 'name',
        header: ({ column }) => (
            <Button
                variant="ghost"
                onClick={() =>
                    column.toggleSorting(column.getIsSorted() === 'asc')
                }
            >
                Name
                <ArrowUpDown className="ml-2 h-4 w-4" />
            </Button>
        ),
        cell: ({ row }) => (
            <span className="font-medium">{row.original.name}</span>
        ),
    });

    // Category column
    columns.push({
        accessorKey: 'category',
        header: 'Category',
        cell: ({ row }) => (
            <Badge variant="outline">{row.original.category}</Badge>
        ),
        filterFn: (row, id, value) => {
            return value.includes(row.getValue(id));
        },
    });

    // Pricing Status column
    columns.push({
        id: 'pricing_status',
        header: 'Status',
        cell: ({ row }) => {
            const item = row.original;
            // Determine status based on item properties
            let status:
                | 'priced'
                | 'unpriced'
                | 'nhis_mapped'
                | 'flexible_copay'
                | 'not_mapped';

            if (item.cash_price === null || item.cash_price <= 0) {
                status = 'unpriced';
            } else if (selectedPlan && isNhis) {
                if (item.is_mapped) {
                    status = 'nhis_mapped';
                } else if (item.is_unmapped && item.copay_amount !== null) {
                    status = 'flexible_copay';
                } else {
                    status = 'not_mapped';
                }
            } else {
                status = 'priced';
            }

            return <PricingStatusBadge status={status} />;
        },
    });

    // Cash Price column
    columns.push({
        accessorKey: 'cash_price',
        header: ({ column }) => (
            <Button
                variant="ghost"
                onClick={() =>
                    column.toggleSorting(column.getIsSorted() === 'asc')
                }
                className="w-full justify-end"
            >
                Cash Price
                <ArrowUpDown className="ml-2 h-4 w-4" />
            </Button>
        ),
        cell: ({ row }) => (
            <div className="text-right">
                <EditableCell
                    item={row.original}
                    field="cash_price"
                    value={row.original.cash_price}
                    selectedPlan={selectedPlan}
                />
            </div>
        ),
    });

    // Insurance-specific columns
    if (selectedPlan) {
        if (isNhis) {
            // NHIS Code column
            columns.push({
                id: 'nhis_code',
                header: 'NHIS Code',
                cell: ({ row }) => {
                    const item = row.original;
                    if (item.is_mapped) {
                        return (
                            <span className="font-mono text-sm">
                                {item.nhis_code}
                            </span>
                        );
                    }
                    return (
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
                                    This item needs to be mapped to an NHIS
                                    tariff code
                                </p>
                            </TooltipContent>
                        </Tooltip>
                    );
                },
            });

            // NHIS Tariff column (read-only)
            columns.push({
                id: 'nhis_tariff',
                header: () => <div className="text-right">NHIS Tariff</div>,
                cell: ({ row }) => (
                    <div className="text-right">
                        {row.original.is_mapped
                            ? formatCurrency(row.original.insurance_tariff)
                            : '-'}
                    </div>
                ),
            });
        } else {
            // Insurance Tariff column (editable)
            columns.push({
                id: 'insurance_tariff',
                header: () => (
                    <div className="text-right">Insurance Tariff</div>
                ),
                cell: ({ row }) => (
                    <div className="text-right">
                        <EditableCell
                            item={row.original}
                            field="insurance_tariff"
                            value={row.original.insurance_tariff}
                            selectedPlan={selectedPlan}
                        />
                    </div>
                ),
            });

            // Coverage % column (editable)
            columns.push({
                id: 'coverage_value',
                header: () => <div className="text-right">Coverage %</div>,
                cell: ({ row }) => (
                    <div className="text-right">
                        <EditableCell
                            item={row.original}
                            field="coverage_value"
                            value={row.original.coverage_value}
                            selectedPlan={selectedPlan}
                        />
                    </div>
                ),
            });
        }

        // Patient Copay column
        columns.push({
            id: 'copay_amount',
            header: () => <div className="text-right">Patient Copay</div>,
            cell: ({ row }) => {
                const item = row.original;
                // For NHIS: allow editing copay for both mapped items AND unmapped items (flexible copay)
                // Unmapped items use the flexible copay endpoint
                const isUnmappedNhisItem = isNhis && !item.is_mapped;

                return (
                    <div className="text-right">
                        <EditableCell
                            item={item}
                            field="copay_amount"
                            value={item.copay_amount}
                            disabled={false} // Always allow copay editing now
                            selectedPlan={selectedPlan}
                            isNhis={isNhis}
                            isUnmappedCopay={isUnmappedNhisItem}
                        />
                    </div>
                );
            },
        });

        // Patient Pays column (calculated, only for private insurance)
        if (!isNhis) {
            columns.push({
                id: 'patient_pays',
                header: () => <div className="text-right">Patient Pays</div>,
                cell: ({ row }) => (
                    <div className="text-right font-medium text-blue-600 dark:text-blue-400">
                        {formatCurrency(calculatePatientPays(row.original))}
                    </div>
                ),
            });
        }
    }

    // Actions column
    columns.push({
        id: 'actions',
        enableHiding: false,
        cell: ({ row }) => {
            const item = row.original;
            return (
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
            );
        },
    });

    return columns;
}
