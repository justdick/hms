import { InsuranceCoverageBadge } from '@/components/Insurance/InsuranceCoverageBadge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { useEffect, useState } from 'react';

interface ChargeItem {
    id: number;
    description: string;
    amount: number;
    is_insurance_claim: boolean;
    insurance_covered_amount: number;
    patient_copay_amount: number;
    service_type: string;
    charged_at: string;
}

interface BillingPermissions {
    canProcessPayment: boolean;
    canWaiveCharges: boolean;
    canAdjustCharges: boolean;
    canOverrideServices: boolean;
    canCancelCharges: boolean;
}

interface ChargesListProps {
    charges: ChargeItem[];
    selectedCharges: number[];
    onChargeSelectionChange: (chargeIds: number[]) => void;
    permissions: BillingPermissions;
    formatCurrency: (amount: number) => string;
    onQuickPay?: (chargeId: number) => void;
    onWaiveCharge?: (chargeId: number) => void;
    onAdjustCharge?: (chargeId: number) => void;
}

export function ChargesList({
    charges,
    selectedCharges,
    onChargeSelectionChange,
    permissions,
    formatCurrency,
    onQuickPay,
    onWaiveCharge,
    onAdjustCharge,
}: ChargesListProps) {
    const [localSelectedCharges, setLocalSelectedCharges] =
        useState<number[]>(selectedCharges);

    // Sync with parent when selectedCharges prop changes
    useEffect(() => {
        setLocalSelectedCharges(selectedCharges);
    }, [selectedCharges]);

    const handleChargeSelection = (chargeId: number, checked: boolean) => {
        let newSelectedCharges: number[];
        if (checked) {
            newSelectedCharges = [...localSelectedCharges, chargeId];
        } else {
            newSelectedCharges = localSelectedCharges.filter(
                (id) => id !== chargeId,
            );
        }
        setLocalSelectedCharges(newSelectedCharges);
        onChargeSelectionChange(newSelectedCharges);
    };

    const handleSelectAll = (checked: boolean) => {
        if (checked) {
            const allChargeIds = charges.map((c) => c.id);
            setLocalSelectedCharges(allChargeIds);
            onChargeSelectionChange(allChargeIds);
        } else {
            setLocalSelectedCharges([]);
            onChargeSelectionChange([]);
        }
    };

    // Calculate totals for selected charges
    const selectedChargesTotal = charges
        .filter((charge) => localSelectedCharges.includes(charge.id))
        .reduce((sum, charge) => sum + charge.amount, 0);

    const selectedChargesCopay = charges
        .filter((charge) => localSelectedCharges.includes(charge.id))
        .reduce(
            (sum, charge) =>
                sum +
                (charge.is_insurance_claim
                    ? charge.patient_copay_amount
                    : charge.amount),
            0,
        );

    const selectedInsuranceCovered = charges
        .filter((charge) => localSelectedCharges.includes(charge.id))
        .reduce(
            (sum, charge) =>
                sum +
                (charge.is_insurance_claim
                    ? charge.insurance_covered_amount
                    : 0),
            0,
        );

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

    const allSelected =
        charges.length > 0 && localSelectedCharges.length === charges.length;
    const someSelected =
        localSelectedCharges.length > 0 &&
        localSelectedCharges.length < charges.length;

    return (
        <div className="space-y-4">
            {/* Header with Select All */}
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <Checkbox
                        checked={allSelected}
                        onCheckedChange={handleSelectAll}
                        aria-label="Select all charges"
                        className={
                            someSelected ? 'data-[state=checked]:bg-muted' : ''
                        }
                    />
                    <span className="text-sm font-medium">
                        {localSelectedCharges.length > 0
                            ? `${localSelectedCharges.length} of ${charges.length} selected`
                            : 'Select all charges'}
                    </span>
                </div>
                {localSelectedCharges.length > 0 && (
                    <div className="text-sm text-muted-foreground">
                        Total: {formatCurrency(selectedChargesCopay)}
                    </div>
                )}
            </div>

            {/* Charges List */}
            <div className="space-y-2">
                {charges.map((charge) => (
                    <div
                        key={charge.id}
                        className={`flex items-start gap-3 rounded-lg border p-3 transition-colors ${
                            localSelectedCharges.includes(charge.id)
                                ? 'border-primary bg-primary/5'
                                : 'bg-card hover:bg-muted/50'
                        }`}
                    >
                        {/* Checkbox */}
                        <div className="pt-0.5">
                            <Checkbox
                                checked={localSelectedCharges.includes(
                                    charge.id,
                                )}
                                onCheckedChange={(checked) =>
                                    handleChargeSelection(
                                        charge.id,
                                        checked as boolean,
                                    )
                                }
                                aria-label={`Select ${charge.description}`}
                            />
                        </div>

                        {/* Charge Details */}
                        <div className="flex-1 space-y-2">
                            <div className="flex items-start justify-between gap-2">
                                <div className="flex-1">
                                    <div className="flex items-center gap-2">
                                        <span className="font-medium">
                                            {charge.description}
                                        </span>
                                        {charge.is_insurance_claim && (
                                            <InsuranceCoverageBadge
                                                isInsuranceClaim={
                                                    charge.is_insurance_claim
                                                }
                                                insuranceCoveredAmount={
                                                    charge.insurance_covered_amount
                                                }
                                                patientCopayAmount={
                                                    charge.patient_copay_amount
                                                }
                                                amount={charge.amount}
                                                className="text-xs"
                                            />
                                        )}
                                    </div>
                                    <div className="mt-1 flex items-center gap-2">
                                        <Badge
                                            variant="outline"
                                            className="text-xs"
                                        >
                                            {formatServiceType(
                                                charge.service_type,
                                            )}
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
                                            <div className="text-sm font-medium text-orange-600">
                                                Pay:{' '}
                                                {formatCurrency(
                                                    charge.patient_copay_amount,
                                                )}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                of{' '}
                                                {formatCurrency(charge.amount)}
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="text-sm font-medium">
                                            {formatCurrency(charge.amount)}
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Insurance Breakdown */}
                            {charge.is_insurance_claim && (
                                <div className="rounded-md bg-muted/50 p-2 text-xs">
                                    <div className="flex items-center justify-between">
                                        <span className="text-muted-foreground">
                                            Total Charge:
                                        </span>
                                        <span className="font-medium">
                                            {formatCurrency(charge.amount)}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-green-600">
                                            Insurance Covers:
                                        </span>
                                        <span className="font-medium text-green-600">
                                            {formatCurrency(
                                                charge.insurance_covered_amount,
                                            )}
                                        </span>
                                    </div>
                                    <div className="flex items-center justify-between border-t border-border/50 pt-1">
                                        <span className="text-orange-600">
                                            Patient Copay:
                                        </span>
                                        <span className="font-medium text-orange-600">
                                            {formatCurrency(
                                                charge.patient_copay_amount,
                                            )}
                                        </span>
                                    </div>
                                </div>
                            )}

                            {/* Action Buttons */}
                            <div className="flex flex-wrap gap-2">
                                {permissions.canProcessPayment &&
                                    onQuickPay && (
                                        <Button
                                            size="sm"
                                            variant="default"
                                            onClick={() =>
                                                onQuickPay(charge.id)
                                            }
                                        >
                                            Quick Pay
                                        </Button>
                                    )}
                                {permissions.canWaiveCharges &&
                                    onWaiveCharge && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                onWaiveCharge(charge.id)
                                            }
                                        >
                                            Waive
                                        </Button>
                                    )}
                                {permissions.canAdjustCharges &&
                                    onAdjustCharge && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                onAdjustCharge(charge.id)
                                            }
                                        >
                                            Adjust
                                        </Button>
                                    )}
                            </div>
                        </div>
                    </div>
                ))}
            </div>

            {/* Summary Footer */}
            {localSelectedCharges.length > 0 && (
                <div className="rounded-lg border border-primary/20 bg-primary/5 p-4">
                    <div className="space-y-2">
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-muted-foreground">
                                Selected Charges Total:
                            </span>
                            <span className="font-medium">
                                {formatCurrency(selectedChargesTotal)}
                            </span>
                        </div>
                        {selectedInsuranceCovered > 0 && (
                            <div className="flex items-center justify-between text-sm">
                                <span className="text-green-600">
                                    Insurance Will Cover:
                                </span>
                                <span className="font-medium text-green-600">
                                    {formatCurrency(selectedInsuranceCovered)}
                                </span>
                            </div>
                        )}
                        <div className="flex items-center justify-between border-t border-border/50 pt-2">
                            <span className="font-medium text-orange-600">
                                Patient Must Pay:
                            </span>
                            <span className="text-lg font-bold text-orange-600">
                                {formatCurrency(selectedChargesCopay)}
                            </span>
                        </div>
                    </div>
                </div>
            )}

            {/* Empty State */}
            {charges.length === 0 && (
                <div className="rounded-lg border border-dashed p-8 text-center">
                    <p className="text-sm text-muted-foreground">
                        No charges to display
                    </p>
                </div>
            )}
        </div>
    );
}
