import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Form } from '@inertiajs/react';
import axios from 'axios';
import { Loader2, Search } from 'lucide-react';
import { useEffect, useState } from 'react';

interface CoverageRule {
    id: number;
    coverage_category: string;
    item_code?: string;
    item_description?: string;
    coverage_type: string;
    coverage_value?: string;
    patient_copay_percentage?: string;
    is_active: boolean;
}

interface SearchItem {
    code: string;
    name: string;
    description: string;
    price: number;
    has_rule: boolean;
}

interface Props {
    open: boolean;
    onClose: () => void;
    planId: number;
    category: string;
    ruleType: 'general' | 'specific';
    editingRule: CoverageRule | null;
}

export default function CoverageRuleModal({
    open,
    onClose,
    planId,
    category,
    ruleType,
    editingRule,
}: Props) {
    const [searchQuery, setSearchQuery] = useState('');
    const [searchResults, setSearchResults] = useState<SearchItem[]>([]);
    const [searching, setSearching] = useState(false);
    const [selectedItem, setSelectedItem] = useState<SearchItem | null>(null);

    useEffect(() => {
        if (editingRule && editingRule.item_code) {
            setSelectedItem({
                code: editingRule.item_code,
                name: editingRule.item_description || '',
                description: editingRule.item_description || '',
                price: 0,
                has_rule: true,
            });
        } else {
            setSelectedItem(null);
        }
    }, [editingRule]);

    useEffect(() => {
        if (!open) {
            setSearchQuery('');
            setSearchResults([]);
            setSelectedItem(null);
        }
    }, [open]);

    const handleSearch = async (query: string) => {
        setSearchQuery(query);

        if (query.length < 2) {
            setSearchResults([]);
            return;
        }

        setSearching(true);
        try {
            const response = await axios.get(
                `/admin/insurance/coverage-rules/search-items/${category}`,
                {
                    params: { search: query, plan_id: planId },
                },
            );
            setSearchResults(response.data);
        } catch (error) {
            console.error('Search failed:', error);
            setSearchResults([]);
        } finally {
            setSearching(false);
        }
    };

    const handleSelectItem = (item: SearchItem) => {
        setSelectedItem(item);
        setSearchQuery('');
        setSearchResults([]);
    };

    const getFormAction = () => {
        if (editingRule) {
            return `/admin/insurance/coverage-rules/${editingRule.id}`;
        }
        return '/admin/insurance/coverage-rules';
    };

    const getFormMethod = () => {
        return editingRule ? 'put' : 'post';
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {editingRule
                            ? 'Edit Coverage Rule'
                            : `Add ${ruleType === 'general' ? 'General' : 'Item-Specific'} Coverage Rule`}
                    </DialogTitle>
                    <DialogDescription>
                        {ruleType === 'general'
                            ? 'Set the default coverage for all items in this category.'
                            : 'Set coverage for a specific item, overriding the general rule.'}
                    </DialogDescription>
                </DialogHeader>

                <Form
                    action={getFormAction()}
                    method={getFormMethod()}
                    onSuccess={onClose}
                    className="space-y-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <input
                                type="hidden"
                                name="insurance_plan_id"
                                value={planId}
                            />
                            <input
                                type="hidden"
                                name="coverage_category"
                                value={category}
                            />

                            {/* Item Search for Specific Rules */}
                            {ruleType === 'specific' && !editingRule && (
                                <div className="space-y-2">
                                    <Label htmlFor="item-search">
                                        Search Item
                                    </Label>
                                    <div className="relative">
                                        <Search className="absolute top-2.5 left-3 h-4 w-4 text-gray-400" />
                                        <Input
                                            id="item-search"
                                            type="text"
                                            placeholder="Search by name or code..."
                                            value={searchQuery}
                                            onChange={(e) =>
                                                handleSearch(e.target.value)
                                            }
                                            className="pl-9"
                                        />
                                        {searching && (
                                            <Loader2 className="absolute top-2.5 right-3 h-4 w-4 animate-spin text-gray-400" />
                                        )}
                                    </div>

                                    {/* Search Results */}
                                    {searchResults.length > 0 && (
                                        <div className="max-h-48 overflow-y-auto rounded-md border">
                                            {searchResults.map((item) => (
                                                <button
                                                    key={item.code}
                                                    type="button"
                                                    onClick={() =>
                                                        handleSelectItem(item)
                                                    }
                                                    className="flex w-full items-start gap-3 border-b p-3 text-left transition-colors last:border-b-0 hover:bg-gray-50 dark:hover:bg-gray-800"
                                                >
                                                    <div className="flex-1">
                                                        <div className="flex items-center gap-2">
                                                            <span className="font-mono text-sm text-gray-600 dark:text-gray-400">
                                                                {item.code}
                                                            </span>
                                                            {item.has_rule && (
                                                                <span className="rounded bg-yellow-100 px-2 py-0.5 text-xs text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                                                    Has Rule
                                                                </span>
                                                            )}
                                                        </div>
                                                        <p className="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100">
                                                            {item.name}
                                                        </p>
                                                        {item.description !==
                                                            item.name && (
                                                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                                                {
                                                                    item.description
                                                                }
                                                            </p>
                                                        )}
                                                    </div>
                                                    <div className="text-right">
                                                        <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                            ${item.price}
                                                        </p>
                                                    </div>
                                                </button>
                                            ))}
                                        </div>
                                    )}

                                    {errors.item_code && (
                                        <p className="text-sm text-red-600">
                                            {errors.item_code}
                                        </p>
                                    )}
                                </div>
                            )}

                            {/* Selected Item Display */}
                            {selectedItem && (
                                <div className="rounded-lg border border-green-200 bg-green-50 p-3 dark:border-green-800 dark:bg-green-950">
                                    <p className="text-sm font-medium text-green-900 dark:text-green-100">
                                        Selected Item
                                    </p>
                                    <div className="mt-1 flex items-center gap-2">
                                        <span className="font-mono text-sm text-green-700 dark:text-green-300">
                                            {selectedItem.code}
                                        </span>
                                        <span className="text-sm text-green-800 dark:text-green-200">
                                            {selectedItem.name}
                                        </span>
                                    </div>
                                    <input
                                        type="hidden"
                                        name="item_code"
                                        value={selectedItem.code}
                                    />
                                    <input
                                        type="hidden"
                                        name="item_description"
                                        value={selectedItem.description}
                                    />
                                </div>
                            )}

                            {/* Coverage Type */}
                            <div className="space-y-2">
                                <Label htmlFor="coverage_type">
                                    Coverage Type
                                </Label>
                                <Select
                                    name="coverage_type"
                                    defaultValue={
                                        editingRule?.coverage_type || 'full'
                                    }
                                >
                                    <SelectTrigger id="coverage_type">
                                        <SelectValue placeholder="Select coverage type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="full">
                                            Full Coverage (100%)
                                        </SelectItem>
                                        <SelectItem value="percentage">
                                            Percentage Coverage
                                        </SelectItem>
                                        <SelectItem value="fixed">
                                            Fixed Amount
                                        </SelectItem>
                                        <SelectItem value="excluded">
                                            Not Covered
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                {errors.coverage_type && (
                                    <p className="text-sm text-red-600">
                                        {errors.coverage_type}
                                    </p>
                                )}
                            </div>

                            {/* Coverage Value */}
                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="coverage_value">
                                        Coverage Value
                                    </Label>
                                    <Input
                                        id="coverage_value"
                                        name="coverage_value"
                                        type="number"
                                        step="0.01"
                                        placeholder="e.g., 80 or 50.00"
                                        defaultValue={
                                            editingRule?.coverage_value || ''
                                        }
                                    />
                                    <p className="text-xs text-gray-500">
                                        Percentage (0-100) or fixed amount
                                    </p>
                                    {errors.coverage_value && (
                                        <p className="text-sm text-red-600">
                                            {errors.coverage_value}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="patient_copay_percentage">
                                        Patient Copay %
                                    </Label>
                                    <Input
                                        id="patient_copay_percentage"
                                        name="patient_copay_percentage"
                                        type="number"
                                        step="0.01"
                                        placeholder="e.g., 20"
                                        defaultValue={
                                            editingRule?.patient_copay_percentage ||
                                            ''
                                        }
                                    />
                                    <p className="text-xs text-gray-500">
                                        Percentage (0-100)
                                    </p>
                                    {errors.patient_copay_percentage && (
                                        <p className="text-sm text-red-600">
                                            {errors.patient_copay_percentage}
                                        </p>
                                    )}
                                </div>
                            </div>

                            {/* Active Status */}
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="is_active"
                                    name="is_active"
                                    defaultChecked={
                                        editingRule?.is_active ?? true
                                    }
                                />
                                <Label
                                    htmlFor="is_active"
                                    className="cursor-pointer"
                                >
                                    Active
                                </Label>
                            </div>

                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={onClose}
                                    disabled={processing}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={
                                        processing ||
                                        (ruleType === 'specific' &&
                                            !selectedItem &&
                                            !editingRule)
                                    }
                                >
                                    {processing && (
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    )}
                                    {editingRule ? 'Update' : 'Create'} Rule
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
