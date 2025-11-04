import { Button } from '@/components/ui/button';
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
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Textarea } from '@/components/ui/textarea';
import { Form } from '@inertiajs/react';
import axios from 'axios';
import { AlertCircle, Loader2, Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { HelpTooltip } from './HelpTooltip';
import { ValidationWarning } from './ValidationWarning';

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
    defaultCoverage?: number | null;
    onSuccess?: () => void;
}

type CoverageType = 'percentage' | 'fixed' | 'full' | 'excluded';

export default function AddExceptionModal({
    open,
    onClose,
    planId,
    category,
    defaultCoverage,
    onSuccess,
}: Props) {
    const [searchQuery, setSearchQuery] = useState('');
    const [searchResults, setSearchResults] = useState<SearchItem[]>([]);
    const [searching, setSearching] = useState(false);
    const [selectedItem, setSelectedItem] = useState<SearchItem | null>(null);
    const [coverageType, setCoverageType] = useState<CoverageType>('percentage');
    const [coverageValue, setCoverageValue] = useState('100');

    useEffect(() => {
        if (!open) {
            // Reset state when modal closes
            setSearchQuery('');
            setSearchResults([]);
            setSelectedItem(null);
            setCoverageType('percentage');
            setCoverageValue('100');
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

    const handleCoverageTypeChange = (type: CoverageType) => {
        setCoverageType(type);
        // Auto-set values based on type
        if (type === 'full') {
            setCoverageValue('100');
        } else if (type === 'excluded') {
            setCoverageValue('0');
        }
    };

    const calculatePreview = () => {
        if (!selectedItem) return null;

        const price = parseFloat(String(selectedItem.price));
        let insurancePays = 0;
        let patientPays = 0;
        let copayPercentage = 0;

        if (coverageType === 'full') {
            insurancePays = price;
            patientPays = 0;
            copayPercentage = 0;
        } else if (coverageType === 'excluded') {
            insurancePays = 0;
            patientPays = price;
            copayPercentage = 100;
        } else if (coverageType === 'percentage') {
            const percentage = parseFloat(coverageValue) || 0;
            insurancePays = (price * percentage) / 100;
            patientPays = price - insurancePays;
            copayPercentage = 100 - percentage;
        } else if (coverageType === 'fixed') {
            const fixedAmount = parseFloat(coverageValue) || 0;
            insurancePays = Math.min(fixedAmount, price);
            patientPays = price - insurancePays;
            copayPercentage = (patientPays / price) * 100;
        }

        return {
            insurancePays: insurancePays.toFixed(2),
            patientPays: patientPays.toFixed(2),
            copayPercentage: copayPercentage.toFixed(1),
            coveragePercentage:
                coverageType === 'percentage'
                    ? parseFloat(coverageValue) || 0
                    : ((insurancePays / price) * 100).toFixed(1),
        };
    };

    const preview = calculatePreview();

    const handleSuccess = () => {
        if (onSuccess) {
            onSuccess();
        }
        onClose();
    };

    // Validation warnings
    const getValidationWarnings = () => {
        const warnings: string[] = [];
        
        if (!selectedItem) return warnings;

        const price = parseFloat(String(selectedItem.price));
        const numValue = parseFloat(coverageValue) || 0;

        // Warning for 0% coverage
        if (coverageType === 'excluded' || (coverageType === 'percentage' && numValue === 0)) {
            warnings.push('This item will not be covered. Patients will pay the full price.');
        }

        // Warning for very low coverage
        if (coverageType === 'percentage' && numValue > 0 && numValue < 30) {
            warnings.push('Very low coverage. Patients will pay most of the cost.');
        }

        // Warning for expensive items with low coverage
        if (price > 500 && coverageType === 'percentage' && numValue < 50) {
            warnings.push(`This is an expensive item ($${price.toFixed(2)}). Consider higher coverage to reduce patient burden.`);
        }

        // Warning for fixed amount higher than price
        if (coverageType === 'fixed' && numValue > price) {
            warnings.push('Fixed amount is higher than the item price. Insurance will only pay up to the item price.');
        }

        // Warning when exception is lower than default
        if (defaultCoverage !== null && defaultCoverage !== undefined && coverageType === 'percentage' && numValue < defaultCoverage) {
            warnings.push(`This exception provides less coverage (${numValue}%) than the default (${defaultCoverage}%). Are you sure?`);
        }

        return warnings;
    };

    const validationWarnings = getValidationWarnings();

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto sm:max-h-[85vh]">
                <DialogHeader>
                    <DialogTitle>Add Coverage Exception</DialogTitle>
                    <DialogDescription>
                        Set custom coverage for a specific item, overriding the
                        default rule
                        {defaultCoverage !== null &&
                            ` (${defaultCoverage}% default)`}
                        .
                    </DialogDescription>
                </DialogHeader>

                <Form
                    key={open ? 'open' : 'closed'}
                    action="/admin/insurance/coverage-rules"
                    method="post"
                    onSuccess={handleSuccess}
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            {/* Global Error Display */}
                            {errors.item_code &&
                                errors.item_code.includes('already has') && (
                                    <div 
                                        className="rounded-lg border-2 border-red-300 bg-red-50 p-4 dark:border-red-700 dark:bg-red-950"
                                        role="alert"
                                        aria-live="assertive"
                                    >
                                        <div className="flex items-start gap-2">
                                            <AlertCircle className="mt-0.5 h-5 w-5 text-red-600 dark:text-red-400" aria-hidden="true" />
                                            <div className="flex-1">
                                                <p className="font-semibold text-red-900 dark:text-red-100">
                                                    Duplicate Exception
                                                </p>
                                                <p className="mt-1 text-sm text-red-800 dark:text-red-200">
                                                    {errors.item_code}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                )}

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
                            <input
                                type="hidden"
                                name="is_active"
                                value="1"
                            />

                            {/* Item Search */}
                            <div className="space-y-2">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Label htmlFor="item-search">
                                        Search for item
                                    </Label>
                                    <HelpTooltip
                                        content="Search by item name or code to find the specific item you want to add an exception for."
                                        example="Search for 'Paracetamol' or 'DRG001'"
                                    />
                                </div>
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
                                        disabled={!!selectedItem}
                                    />
                                    {searching && (
                                        <Loader2 className="absolute top-2.5 right-3 h-4 w-4 animate-spin text-gray-400" />
                                    )}
                                </div>

                                {/* Search Results - Responsive card layout */}
                                {searchResults.length > 0 && !selectedItem && (
                                    <div className="max-h-60 overflow-y-auto rounded-md border border-gray-200 dark:border-gray-700">
                                        {searchResults.map((item) => (
                                            <button
                                                key={item.code}
                                                type="button"
                                                onClick={() =>
                                                    handleSelectItem(item)
                                                }
                                                disabled={item.has_rule}
                                                className="flex w-full flex-col gap-2 border-b p-3 text-left transition-colors last:border-b-0 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 sm:flex-row sm:items-start sm:gap-3 dark:hover:bg-gray-800"
                                                aria-label={`Select ${item.name}`}
                                            >
                                                <div className="flex-1">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <span className="font-mono text-xs text-gray-600 sm:text-sm dark:text-gray-400">
                                                            {item.code}
                                                        </span>
                                                        {item.has_rule && (
                                                            <span className="rounded bg-yellow-100 px-2 py-0.5 text-xs text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                                                Already has
                                                                exception
                                                            </span>
                                                        )}
                                                    </div>
                                                    <p className="mt-1 text-sm font-medium text-gray-900 dark:text-gray-100">
                                                        {item.name}
                                                    </p>
                                                </div>
                                                <div className="text-left sm:text-right">
                                                    <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                        ${parseFloat(String(item.price)).toFixed(2)}
                                                    </p>
                                                </div>
                                            </button>
                                        ))}
                                    </div>
                                )}

                                {errors.item_code && (
                                    <p className="text-sm text-red-600" role="alert" aria-live="polite">
                                        {errors.item_code}
                                    </p>
                                )}
                            </div>

                            {/* Selected Item Display */}
                            {selectedItem && (
                                <div className="rounded-lg border-2 border-green-300 bg-green-50 p-4 dark:border-green-700 dark:bg-green-950">
                                    <div className="flex items-start justify-between">
                                        <div className="flex-1">
                                            <p className="text-sm font-medium text-green-900 dark:text-green-100">
                                                Selected Item
                                            </p>
                                            <div className="mt-1 flex items-center gap-2">
                                                <span className="font-mono text-sm text-green-700 dark:text-green-300">
                                                    {selectedItem.code}
                                                </span>
                                                <span className="text-sm font-semibold text-green-800 dark:text-green-200">
                                                    {selectedItem.name}
                                                </span>
                                            </div>
                                            <p className="mt-1 text-sm text-green-700 dark:text-green-300">
                                                Current price: $
                                                {parseFloat(String(selectedItem.price)).toFixed(2)}
                                            </p>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => setSelectedItem(null)}
                                        >
                                            Change
                                        </Button>
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

                            {/* Coverage Type Selector */}
                            {selectedItem && (
                                <>
                                    <div className="space-y-3">
                                        <div className="flex items-center gap-2">
                                            <Label>Coverage for this item</Label>
                                            <HelpTooltip
                                                content="Choose how insurance will cover this specific item. This overrides the default coverage for this category."
                                                example="Set Paracetamol to 100% if it should be fully covered"
                                            />
                                        </div>
                                        <RadioGroup
                                            value={coverageType}
                                            onValueChange={(value) =>
                                                handleCoverageTypeChange(
                                                    value as CoverageType,
                                                )
                                            }
                                        >
                                            <div className="flex items-center space-x-2">
                                                <RadioGroupItem
                                                    value="percentage"
                                                    id="percentage"
                                                />
                                                <Label
                                                    htmlFor="percentage"
                                                    className="cursor-pointer font-normal"
                                                >
                                                    Percentage
                                                </Label>
                                            </div>
                                            <div className="flex items-center space-x-2">
                                                <RadioGroupItem
                                                    value="fixed"
                                                    id="fixed"
                                                />
                                                <Label
                                                    htmlFor="fixed"
                                                    className="cursor-pointer font-normal"
                                                >
                                                    Fixed Amount
                                                </Label>
                                            </div>
                                            <div className="flex items-center space-x-2">
                                                <RadioGroupItem
                                                    value="full"
                                                    id="full"
                                                />
                                                <Label
                                                    htmlFor="full"
                                                    className="cursor-pointer font-normal"
                                                >
                                                    Fully Covered (100%)
                                                </Label>
                                            </div>
                                            <div className="flex items-center space-x-2">
                                                <RadioGroupItem
                                                    value="excluded"
                                                    id="excluded"
                                                />
                                                <Label
                                                    htmlFor="excluded"
                                                    className="cursor-pointer font-normal"
                                                >
                                                    Not Covered (0%)
                                                </Label>
                                            </div>
                                        </RadioGroup>
                                        <input
                                            type="hidden"
                                            name="coverage_type"
                                            value={coverageType}
                                        />
                                    </div>

                                    {/* Coverage Value Input */}
                                    {(coverageType === 'percentage' ||
                                        coverageType === 'fixed') && (
                                        <div className="space-y-2">
                                            <div className="flex items-center gap-2">
                                                <Label htmlFor="coverage_value">
                                                    {coverageType === 'percentage'
                                                        ? 'Coverage Percentage'
                                                        : 'Fixed Amount'}
                                                </Label>
                                                <HelpTooltip
                                                    content={
                                                        coverageType === 'percentage'
                                                            ? 'Enter the percentage (0-100) that insurance will cover. The patient pays the remaining amount.'
                                                            : 'Enter the fixed dollar amount that insurance will pay, regardless of the actual price.'
                                                    }
                                                    example={
                                                        coverageType === 'percentage'
                                                            ? '80 means insurance pays 80%, patient pays 20%'
                                                            : '$50 means insurance pays $50, patient pays the rest'
                                                    }
                                                />
                                            </div>
                                            <div className="relative">
                                                <Input
                                                    id="coverage_value"
                                                    name="coverage_value"
                                                    type="number"
                                                    step={
                                                        coverageType ===
                                                        'percentage'
                                                            ? '1'
                                                            : '0.01'
                                                    }
                                                    min="0"
                                                    max={
                                                        coverageType ===
                                                        'percentage'
                                                            ? '100'
                                                            : undefined
                                                    }
                                                    value={coverageValue}
                                                    onChange={(e) =>
                                                        setCoverageValue(
                                                            e.target.value,
                                                        )
                                                    }
                                                    className="pr-8"
                                                />
                                                <span className="absolute top-2.5 right-3 text-sm text-gray-500">
                                                    {coverageType ===
                                                    'percentage'
                                                        ? '%'
                                                        : '$'}
                                                </span>
                                            </div>
                                            {errors.coverage_value && (
                                                <p className="text-sm text-red-600">
                                                    {errors.coverage_value}
                                                </p>
                                            )}
                                        </div>
                                    )}

                                    {coverageType === 'full' && (
                                        <input
                                            type="hidden"
                                            name="coverage_value"
                                            value="100"
                                        />
                                    )}
                                    {coverageType === 'excluded' && (
                                        <input
                                            type="hidden"
                                            name="coverage_value"
                                            value="0"
                                        />
                                    )}

                                    {/* Auto-calculated copay */}
                                    <input
                                        type="hidden"
                                        name="patient_copay_percentage"
                                        value={preview?.copayPercentage || '0'}
                                    />
                                    <input
                                        type="hidden"
                                        name="is_covered"
                                        value={
                                            coverageType === 'excluded'
                                                ? '0'
                                                : '1'
                                        }
                                    />

                                    {/* Validation Warnings */}
                                    {validationWarnings.length > 0 && (
                                        <div className="space-y-2">
                                            {validationWarnings.map((warning, index) => (
                                                <ValidationWarning
                                                    key={index}
                                                    message={warning}
                                                    severity="warning"
                                                />
                                            ))}
                                        </div>
                                    )}

                                    {/* Preview */}
                                    {preview && (
                                        <div className="rounded-lg border-2 border-blue-300 bg-blue-50 p-4 dark:border-blue-700 dark:bg-blue-950">
                                            <div className="flex items-start gap-2">
                                                <AlertCircle className="mt-0.5 h-5 w-5 text-blue-600 dark:text-blue-400" />
                                                <div className="flex-1">
                                                    <p className="font-semibold text-blue-900 dark:text-blue-100">
                                                        Preview
                                                    </p>
                                                    <div className="mt-2 space-y-1 text-sm">
                                                        <div className="flex justify-between">
                                                            <span className="text-blue-800 dark:text-blue-200">
                                                                Insurance pays:
                                                            </span>
                                                            <span className="font-semibold text-blue-900 dark:text-blue-100">
                                                                $
                                                                {
                                                                    preview.insurancePays
                                                                }{' '}
                                                                (
                                                                {
                                                                    preview.coveragePercentage
                                                                }
                                                                %)
                                                            </span>
                                                        </div>
                                                        <div className="flex justify-between">
                                                            <span className="text-blue-800 dark:text-blue-200">
                                                                Patient pays:
                                                            </span>
                                                            <span className="font-semibold text-blue-900 dark:text-blue-100">
                                                                $
                                                                {
                                                                    preview.patientPays
                                                                }{' '}
                                                                (
                                                                {
                                                                    preview.copayPercentage
                                                                }
                                                                %)
                                                            </span>
                                                        </div>
                                                    </div>
                                                    {defaultCoverage !==
                                                        null && (
                                                        <p className="mt-2 text-xs text-blue-700 dark:text-blue-300">
                                                            Default coverage for
                                                            this category is{' '}
                                                            {defaultCoverage}%
                                                        </p>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    )}

                                    {/* Notes (Optional) */}
                                    <div className="space-y-2">
                                        <Label htmlFor="notes">
                                            Notes (optional)
                                        </Label>
                                        <Textarea
                                            id="notes"
                                            name="notes"
                                            placeholder="e.g., Essential diagnostic test, approved by medical director..."
                                            rows={3}
                                        />
                                        <p className="text-xs text-gray-500">
                                            Add any relevant notes about this
                                            exception
                                        </p>
                                    </div>
                                </>
                            )}

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
                                    disabled={processing || !selectedItem}
                                >
                                    {processing && (
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    )}
                                    Add Exception
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
