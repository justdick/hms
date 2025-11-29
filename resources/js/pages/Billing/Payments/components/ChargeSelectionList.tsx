import { InsuranceCoverageBadge } from '@/components/Insurance/InsuranceCoverageBadge';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { CheckSquare, DollarSign, Receipt } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

export interface ChargeItem {
    id: number;
    description: string;
    amount: number;
    is_insurance_claim: boolean;
    insurance_covered_amount: number;
    patient_copay_amount: number;
    service_type: string;
    charged_at: string;
    status?: string;
}

export interface ChargeSelectionSummary {
    selectedCount: number;
    totalCount: number;
    selectedAmount: number;
    totalAmount: number;
    selectedPatientOwes: number;
    totalPatientOwes: number;
    selectedInsuranceCovered: number;
    totalInsuranceCovered: number;
    remainingUnpaidAmount: number;
}

interface ChargeSelectionListProps {
    charges: ChargeItem[];
    selectedChargeIds: number[];
    onSelectionChange: (selectedIds: number[]) => void;
    formatCurrency: (amount: number) => string;
    showSummary?: boolean;
}

/**
 * ChargeSelectionList Component
 * 
 * Displays a list of charges with checkboxes for selection.
 * All charges are selected by default.
 * Calculates and displays totals based on selection.
 * 
 * Requirements: 1.1, 1.2, 1.5
 */
export function ChargeSelectionList({
    charges,
    selectedChargeIds,
    onSelectionChange,
    formatCurrency,
    showSummary = true,
}: ChargeSelectionListProps) {
    // Initialize with all charges selected by default
    const [localSelectedIds, setLocalSelectedIds] = useState<number[]>(() => {
        // If selectedChargeIds is provided and not empty, use it
        // Otherwise, select all charges by default
        if (selectedChargeIds.length > 0) {
            return selectedChargeIds;
        }
        return charges.map((c) => c.id);
    });

    // Sync with parent when selectedChargeIds prop changes
    useEffect(() => {
        if (selectedChargeIds.length > 0) {
            setLocalSelectedIds(selectedChargeIds);
        }
    }, [selectedChargeIds]);

    // Auto-select all charges when charges change and no selection exists
    useEffect(() => {
        if (charges.length > 0 && localSelectedIds.length === 0) {
            const allIds = charges.map((c) => c.id);
            setLocalSelectedIds(allIds);
            onSelectionChange(allIds);
        }
    }, [charges]);

    // Calculate summary based on selection
    const summary: ChargeSelectionSummary = useMemo(() => {
        const selectedCharges = charges.filter((c) =>
            localSelectedIds.includes(c.id),
        );
        const unselectedCharges = charges.filter(
            (c) => !localSelectedIds.includes(c.id),
        );

        const calculatePatientOwes = (chargeList: ChargeItem[]) =>
            chargeList.reduce(
                (sum, c) =>
                    sum +
                    (c.is_insurance_claim ? c.patient_copay_amount : c.amount),
                0,
            );

        const calculateInsuranceCovered = (chargeList: ChargeItem[]) =>
            chargeList.reduce(
                (sum, c) =>
                    sum + (c.is_insurance_claim ? c.insurance_covered_amount : 0),
                0,
            );

        return {
            selectedCount: selectedCharges.length,
            totalCount: charges.length,
            selectedAmount: selectedCharges.reduce((sum, c) => sum + c.amount, 0),
            totalAmount: charges.reduce((sum, c) => sum + c.amount, 0),
            selectedPatientOwes: calculatePatientOwes(selectedCharges),
            totalPatientOwes: calculatePatientOwes(charges),
            selectedInsuranceCovered: calculateInsuranceCovered(selectedCharges),
            totalInsuranceCovered: calculateInsuranceCovered(charges),
            remainingUnpaidAmount: calculatePatientOwes(unselectedCharges),
        };
    }, [charges, localSelectedIds]);

    const handleChargeToggle = (chargeId: number, checked: boolean) => {
        let newSelectedIds: number[];
        if (checked) {
            newSelectedIds = [...localSelectedIds, chargeId];
        } else {
            newSelectedIds = localSelectedIds.filter((id) => id !== chargeId);
        }
        setLocalSelectedIds(newSelectedIds);
        onSelectionChange(newSelectedIds);
    };

    const handleSelectAll = (checked: boolean) => {
        const newSelectedIds = checked ? charges.map((c) => c.id) : [];
        setLocalSelectedIds(newSelectedIds);
        onSelectionChange(newSelectedIds);
    };

    const allSelected =
        charges.length > 0 && localSelectedIds.length === charges.length;
    const someSelected =
        localSelectedIds.length > 0 &&
        localSelectedIds.length < charges.length;

    const formatServiceType = (serviceType: string) => {
        return serviceType
            .split('_')
            .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
            .join(' ');
    };

    const formatDateTime = (dateString: string) => {
        const date = new Date(dateString);
        return date.toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
        });
    };

    if (charges.length === 0) {
        return (
            <div className="rounded-lg border border-dashed p-8 text-center">
                <p className="text-sm text-muted-foreground">
                    No pending charges to display
                </p>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            {/* Header with Select All */}
            <div className="flex items-center justify-between rounded-lg bg-muted/30 p-3">
                <div className="flex items-center gap-3">
                    <Checkbox
                        id="select-all-charges"
                        checked={allSelected}
                        onCheckedChange={handleSelectAll}
                        aria-label="Select all charges"
                        className={someSelected ? 'data-[state=checked]:bg-muted' : ''}
                    />
                    <label
                        htmlFor="select-all-charges"
                        className="flex cursor-pointer items-center gap-2 text-sm font-medium"
                    >
                        <CheckSquare className="h-4 w-4" />
                        {localSelectedIds.length > 0
                            ? `${localSelectedIds.length} of ${charges.length} charges selected`
                            : 'Select all charges'}
                    </label>
                </div>
                {localSelectedIds.length > 0 && (
                    <Badge variant="secondary" className="text-sm">
                        {formatCurrency(summary.selectedPatientOwes)} to collect
                    </Badge>
                )}
            </div>

            {/* Charges List */}
            <div className="space-y-2">
                {charges.map((charge) => {
                    const isSelected = localSelectedIds.includes(charge.id);
                    return (
                        <div
                            key={charge.id}
                            className={`flex items-start gap-3 rounded-lg border p-3 transition-all ${
                                isSelected
                                    ? 'border-primary/50 bg-primary/5 shadow-sm'
                                    : 'border-border bg-card hover:bg-muted/30'
                            }`}
                        >
                            {/* Checkbox */}
                            <div className="pt-0.5">
                                <Checkbox
                                    id={`charge-${charge.id}`}
                                    checked={isSelected}
                                    onCheckedChange={(checked) =>
                                        handleChargeToggle(charge.id, checked as boolean)
                                    }
                                    aria-label={`Select ${charge.description}`}
                                />
                            </div>

                            {/* Charge Details */}
                            <label
                                htmlFor={`charge-${charge.id}`}
                                className="flex flex-1 cursor-pointer items-start justify-between gap-2"
                            >
                                <div className="flex-1 space-y-1">
                                    <div className="flex items-center gap-2">
                                        <span className="font-medium">
                                            {charge.description}
                                        </span>
                                        {charge.is_insurance_claim && (
                                            <InsuranceCoverageBadge
                                                isInsuranceClaim={charge.is_insurance_claim}
                                                insuranceCoveredAmount={
                                                    charge.insurance_covered_amount
                                                }
                                                patientCopayAmount={charge.patient_copay_amount}
                                                amount={charge.amount}
                                                className="text-xs"
                                            />
                                        )}
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Badge variant="outline" className="text-xs">
                                            {formatServiceType(charge.service_type)}
                                        </Badge>
                                        <span className="text-xs text-muted-foreground">
                                            {formatDateTime(charge.charged_at)}
                                        </span>
                                    </div>
                                </div>

                                {/* Amount Display */}
                                <div className="text-right">
                                    {charge.is_insurance_claim ? (
                                        <div className="space-y-0.5">
                                            <div className="text-sm font-semibold text-orange-600">
                                                {formatCurrency(charge.patient_copay_amount)}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                of {formatCurrency(charge.amount)}
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="text-sm font-semibold">
                                            {formatCurrency(charge.amount)}
                                        </div>
                                    )}
                                </div>
                            </label>
                        </div>
                    );
                })}
            </div>

            {/* Summary Section - Requirements 1.5 */}
            {showSummary && localSelectedIds.length > 0 && (
                <Card className="border-primary/20 bg-primary/5">
                    <CardHeader className="pb-2">
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Receipt className="h-4 w-4" />
                            Payment Summary
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {/* Selected charges count */}
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-muted-foreground">
                                Selected Charges:
                            </span>
                            <span className="font-medium">
                                {summary.selectedCount} of {summary.totalCount}
                            </span>
                        </div>

                        {/* Total selected amount */}
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-muted-foreground">
                                Total Selected Amount:
                            </span>
                            <span className="font-medium">
                                {formatCurrency(summary.selectedAmount)}
                            </span>
                        </div>

                        {/* Insurance coverage (if applicable) */}
                        {summary.selectedInsuranceCovered > 0 && (
                            <div className="flex items-center justify-between text-sm">
                                <span className="text-green-600">
                                    Insurance Covers:
                                </span>
                                <span className="font-medium text-green-600">
                                    -{formatCurrency(summary.selectedInsuranceCovered)}
                                </span>
                            </div>
                        )}

                        {/* Patient owes (copay) */}
                        <div className="flex items-center justify-between border-t border-border/50 pt-2">
                            <span className="flex items-center gap-2 font-medium text-orange-600">
                                <DollarSign className="h-4 w-4" />
                                Amount to Collect:
                            </span>
                            <span className="text-lg font-bold text-orange-600">
                                {formatCurrency(summary.selectedPatientOwes)}
                            </span>
                        </div>

                        {/* Remaining unpaid amount */}
                        {summary.remainingUnpaidAmount > 0 && (
                            <div className="flex items-center justify-between rounded-md bg-muted/50 p-2 text-sm">
                                <span className="text-muted-foreground">
                                    Remaining Unpaid:
                                </span>
                                <span className="font-medium text-muted-foreground">
                                    {formatCurrency(summary.remainingUnpaidAmount)}
                                </span>
                            </div>
                        )}
                    </CardContent>
                </Card>
            )}
        </div>
    );
}

/**
 * Utility function to calculate charge selection totals
 * Can be used independently for testing or other components
 */
export function calculateChargeSelectionTotals(
    charges: ChargeItem[],
    selectedIds: number[],
): ChargeSelectionSummary {
    const selectedCharges = charges.filter((c) => selectedIds.includes(c.id));
    const unselectedCharges = charges.filter((c) => !selectedIds.includes(c.id));

    const calculatePatientOwes = (chargeList: ChargeItem[]) =>
        chargeList.reduce(
            (sum, c) =>
                sum + (c.is_insurance_claim ? c.patient_copay_amount : c.amount),
            0,
        );

    const calculateInsuranceCovered = (chargeList: ChargeItem[]) =>
        chargeList.reduce(
            (sum, c) =>
                sum + (c.is_insurance_claim ? c.insurance_covered_amount : 0),
            0,
        );

    return {
        selectedCount: selectedCharges.length,
        totalCount: charges.length,
        selectedAmount: selectedCharges.reduce((sum, c) => sum + c.amount, 0),
        totalAmount: charges.reduce((sum, c) => sum + c.amount, 0),
        selectedPatientOwes: calculatePatientOwes(selectedCharges),
        totalPatientOwes: calculatePatientOwes(charges),
        selectedInsuranceCovered: calculateInsuranceCovered(selectedCharges),
        totalInsuranceCovered: calculateInsuranceCovered(charges),
        remainingUnpaidAmount: calculatePatientOwes(unselectedCharges),
    };
}
