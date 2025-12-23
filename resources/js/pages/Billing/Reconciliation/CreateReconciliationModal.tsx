import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { router } from '@inertiajs/react';
import { AlertTriangle, Calculator, CheckCircle, Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Cashier {
    id: number;
    name: string;
}

interface CashierAwaitingReconciliation {
    id: number;
    name: string;
    system_total: number;
}

// Ghana Cedi denominations
const DENOMINATIONS = [
    { value: 200, label: 'GHS 200' },
    { value: 100, label: 'GHS 100' },
    { value: 50, label: 'GHS 50' },
    { value: 20, label: 'GHS 20' },
    { value: 10, label: 'GHS 10' },
    { value: 5, label: 'GHS 5' },
    { value: 2, label: 'GHS 2' },
    { value: 1, label: 'GHS 1' },
    { value: 0.5, label: '50 Pesewas' },
    { value: 0.2, label: '20 Pesewas' },
    { value: 0.1, label: '10 Pesewas' },
    { value: 0.05, label: '5 Pesewas' },
];

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    cashiers: Cashier[];
    cashiersAwaitingReconciliation: CashierAwaitingReconciliation[];
}

export default function CreateReconciliationModal({
    open,
    onOpenChange,
    cashiers,
    cashiersAwaitingReconciliation,
}: Props) {
    const [cashierId, setCashierId] = useState<string>('');
    const [reconciliationDate, setReconciliationDate] = useState<string>(
        new Date().toISOString().split('T')[0],
    );
    const [systemTotal, setSystemTotal] = useState<number>(0);
    const [physicalCount, setPhysicalCount] = useState<string>('');
    const [denominationCounts, setDenominationCounts] = useState<
        Record<number, string>
    >({});
    const [varianceReason, setVarianceReason] = useState<string>('');
    const [isLoading, setIsLoading] = useState<boolean>(false);
    const [isFetchingTotal, setIsFetchingTotal] = useState<boolean>(false);
    const [alreadyReconciled, setAlreadyReconciled] = useState<boolean>(false);
    const [error, setError] = useState<string>('');
    const [useDenominations, setUseDenominations] = useState<boolean>(true);

    // Calculate physical count from denominations
    const calculatedPhysicalCount = useDenominations
        ? DENOMINATIONS.reduce((total, denom) => {
              const count =
                  parseInt(denominationCounts[denom.value] || '0', 10) || 0;
              return total + count * denom.value;
          }, 0)
        : parseFloat(physicalCount) || 0;

    // Calculate variance
    const variance = calculatedPhysicalCount - systemTotal;
    const hasVariance = Math.abs(variance) >= 0.01;
    const isOverage = variance > 0;

    // Format currency
    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-GH', {
            style: 'currency',
            currency: 'GHS',
        }).format(amount);
    };

    // Reset form when modal opens/closes
    useEffect(() => {
        if (open) {
            setCashierId('');
            setReconciliationDate(new Date().toISOString().split('T')[0]);
            setSystemTotal(0);
            setPhysicalCount('');
            setDenominationCounts({});
            setVarianceReason('');
            setAlreadyReconciled(false);
            setError('');
            setUseDenominations(true);
        }
    }, [open]);

    // Fetch system total when cashier or date changes
    useEffect(() => {
        if (cashierId && reconciliationDate) {
            fetchSystemTotal();
        } else {
            setSystemTotal(0);
            setAlreadyReconciled(false);
        }
    }, [cashierId, reconciliationDate]);

    // Check if selected cashier is in awaiting list and pre-fill system total
    useEffect(() => {
        if (cashierId) {
            const awaitingCashier = cashiersAwaitingReconciliation.find(
                (c) => c.id.toString() === cashierId,
            );
            if (
                awaitingCashier &&
                reconciliationDate === new Date().toISOString().split('T')[0]
            ) {
                setSystemTotal(awaitingCashier.system_total);
            }
        }
    }, [cashierId, cashiersAwaitingReconciliation, reconciliationDate]);

    const fetchSystemTotal = async () => {
        setIsFetchingTotal(true);
        setError('');

        try {
            const response = await fetch(
                `/billing/accounts/reconciliation/system-total?cashier_id=${cashierId}&date=${reconciliationDate}`,
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                },
            );

            if (!response.ok) {
                throw new Error('Failed to fetch system total');
            }

            const data = await response.json();
            setSystemTotal(data.system_total);
            setAlreadyReconciled(data.already_reconciled);
        } catch {
            setError('Failed to fetch system total. Please try again.');
        } finally {
            setIsFetchingTotal(false);
        }
    };

    const handleDenominationChange = (denomination: number, count: string) => {
        setDenominationCounts((prev) => ({
            ...prev,
            [denomination]: count,
        }));
    };

    const handleSubmit = () => {
        setError('');

        // Validation
        if (!cashierId) {
            setError('Please select a cashier');
            return;
        }

        if (!reconciliationDate) {
            setError('Please select a date');
            return;
        }

        if (alreadyReconciled) {
            setError('This cashier has already been reconciled for this date');
            return;
        }

        if (calculatedPhysicalCount <= 0 && systemTotal > 0) {
            setError('Please enter the physical cash count');
            return;
        }

        if (hasVariance && !varianceReason.trim()) {
            setError('A reason is required when there is a variance');
            return;
        }

        setIsLoading(true);

        // Build denomination breakdown
        const denominationBreakdown: Record<string, number> = {};
        if (useDenominations) {
            DENOMINATIONS.forEach((denom) => {
                const count =
                    parseInt(denominationCounts[denom.value] || '0', 10) || 0;
                if (count > 0) {
                    denominationBreakdown[denom.value.toString()] = count;
                }
            });
        }

        router.post(
            '/billing/accounts/reconciliation',
            {
                cashier_id: cashierId,
                reconciliation_date: reconciliationDate,
                physical_count: calculatedPhysicalCount,
                denomination_breakdown: useDenominations
                    ? denominationBreakdown
                    : null,
                variance_reason: hasVariance ? varianceReason : null,
            },
            {
                onSuccess: () => {
                    onOpenChange(false);
                },
                onError: (errors) => {
                    const firstError = Object.values(errors)[0];
                    setError(
                        typeof firstError === 'string'
                            ? firstError
                            : 'An error occurred',
                    );
                },
                onFinish: () => {
                    setIsLoading(false);
                },
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-[600px]">
                <DialogHeader>
                    <DialogTitle>New Cash Reconciliation</DialogTitle>
                    <DialogDescription>
                        Reconcile a cashier's collections against their physical
                        cash count
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-6 py-4">
                    {/* Error Alert */}
                    {error && (
                        <Alert variant="destructive">
                            <AlertTriangle className="h-4 w-4" />
                            <AlertDescription>{error}</AlertDescription>
                        </Alert>
                    )}

                    {/* Already Reconciled Warning */}
                    {alreadyReconciled && (
                        <Alert>
                            <AlertTriangle className="h-4 w-4" />
                            <AlertDescription>
                                This cashier has already been reconciled for
                                this date.
                            </AlertDescription>
                        </Alert>
                    )}

                    {/* Cashier Selection */}
                    <div className="space-y-2">
                        <Label htmlFor="cashier">Cashier</Label>
                        <Select value={cashierId} onValueChange={setCashierId}>
                            <SelectTrigger id="cashier">
                                <SelectValue placeholder="Select a cashier" />
                            </SelectTrigger>
                            <SelectContent>
                                {/* Show awaiting cashiers first */}
                                {cashiersAwaitingReconciliation.length > 0 && (
                                    <>
                                        <div className="px-2 py-1.5 text-xs font-semibold text-muted-foreground">
                                            Awaiting Reconciliation Today
                                        </div>
                                        {cashiersAwaitingReconciliation.map(
                                            (c) => (
                                                <SelectItem
                                                    key={`awaiting-${c.id}`}
                                                    value={c.id.toString()}
                                                >
                                                    <span className="flex items-center gap-2">
                                                        {c.name}
                                                        <Badge
                                                            variant="outline"
                                                            className="text-xs"
                                                        >
                                                            {formatCurrency(
                                                                c.system_total,
                                                            )}
                                                        </Badge>
                                                    </span>
                                                </SelectItem>
                                            ),
                                        )}
                                        <div className="my-1 border-t" />
                                        <div className="px-2 py-1.5 text-xs font-semibold text-muted-foreground">
                                            All Cashiers
                                        </div>
                                    </>
                                )}
                                {cashiers.map((c) => (
                                    <SelectItem
                                        key={c.id}
                                        value={c.id.toString()}
                                    >
                                        {c.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Date Selection */}
                    <div className="space-y-2">
                        <Label htmlFor="date">Reconciliation Date</Label>
                        <Input
                            id="date"
                            type="date"
                            value={reconciliationDate}
                            onChange={(e) =>
                                setReconciliationDate(e.target.value)
                            }
                            max={new Date().toISOString().split('T')[0]}
                        />
                    </div>

                    {/* System Total Display */}
                    <div className="rounded-lg border bg-muted/50 p-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    System Total (Cash Collections)
                                </p>
                                <p className="text-2xl font-bold text-blue-600">
                                    {isFetchingTotal ? (
                                        <span className="flex items-center gap-2">
                                            <Loader2 className="h-5 w-5 animate-spin" />
                                            Loading...
                                        </span>
                                    ) : (
                                        formatCurrency(systemTotal)
                                    )}
                                </p>
                            </div>
                            <Calculator className="h-8 w-8 text-muted-foreground" />
                        </div>
                    </div>

                    {/* Physical Count Entry Method Toggle */}
                    <div className="flex items-center gap-4">
                        <Label>Entry Method:</Label>
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant={
                                    useDenominations ? 'default' : 'outline'
                                }
                                size="sm"
                                onClick={() => setUseDenominations(true)}
                            >
                                By Denomination
                            </Button>
                            <Button
                                type="button"
                                variant={
                                    !useDenominations ? 'default' : 'outline'
                                }
                                size="sm"
                                onClick={() => setUseDenominations(false)}
                            >
                                Direct Entry
                            </Button>
                        </div>
                    </div>

                    {/* Denomination Breakdown */}
                    {useDenominations ? (
                        <div className="space-y-3">
                            <Label>Denomination Breakdown</Label>
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                {DENOMINATIONS.map((denom) => (
                                    <div
                                        key={denom.value}
                                        className="flex items-center gap-2"
                                    >
                                        <span className="w-20 text-sm font-medium">
                                            {denom.label}
                                        </span>
                                        <span className="text-muted-foreground">
                                            ×
                                        </span>
                                        <Input
                                            type="number"
                                            min="0"
                                            placeholder="0"
                                            className="w-20"
                                            value={
                                                denominationCounts[
                                                    denom.value
                                                ] || ''
                                            }
                                            onChange={(e) =>
                                                handleDenominationChange(
                                                    denom.value,
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                ))}
                            </div>
                        </div>
                    ) : (
                        <div className="space-y-2">
                            <Label htmlFor="physical-count">
                                Physical Cash Count
                            </Label>
                            <Input
                                id="physical-count"
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="Enter total cash amount"
                                value={physicalCount}
                                onChange={(e) =>
                                    setPhysicalCount(e.target.value)
                                }
                            />
                        </div>
                    )}

                    {/* Physical Count Total */}
                    <div className="rounded-lg border bg-muted/50 p-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    Physical Count Total
                                </p>
                                <p className="text-2xl font-bold text-green-600">
                                    {formatCurrency(calculatedPhysicalCount)}
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Variance Display */}
                    {cashierId && systemTotal > 0 && (
                        <div
                            className={`rounded-lg border p-4 ${
                                !hasVariance
                                    ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/20'
                                    : isOverage
                                      ? 'border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-900/20'
                                      : 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/20'
                            }`}
                        >
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">
                                        Variance
                                    </p>
                                    <p
                                        className={`text-2xl font-bold ${
                                            !hasVariance
                                                ? 'text-green-600'
                                                : isOverage
                                                  ? 'text-blue-600'
                                                  : 'text-red-600'
                                        }`}
                                    >
                                        {variance >= 0 ? '+' : ''}
                                        {formatCurrency(variance)}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {!hasVariance
                                            ? 'Balanced'
                                            : isOverage
                                              ? 'Overage (more cash than expected)'
                                              : 'Shortage (less cash than expected)'}
                                    </p>
                                </div>
                                {!hasVariance ? (
                                    <CheckCircle className="h-8 w-8 text-green-600" />
                                ) : (
                                    <AlertTriangle
                                        className={`h-8 w-8 ${isOverage ? 'text-blue-600' : 'text-red-600'}`}
                                    />
                                )}
                            </div>
                        </div>
                    )}

                    {/* Variance Reason (required if variance exists) */}
                    {hasVariance && (
                        <div className="space-y-2">
                            <Label htmlFor="variance-reason">
                                Variance Reason{' '}
                                <span className="text-red-500">*</span>
                            </Label>
                            <Textarea
                                id="variance-reason"
                                placeholder="Please explain the reason for the variance..."
                                value={varianceReason}
                                onChange={(e) =>
                                    setVarianceReason(e.target.value)
                                }
                                rows={3}
                            />
                            <p className="text-xs text-muted-foreground">
                                A reason is required when there is a variance
                                between system total and physical count.
                            </p>
                        </div>
                    )}
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={isLoading}
                    >
                        Cancel
                    </Button>
                    <Button
                        onClick={handleSubmit}
                        disabled={
                            isLoading || isFetchingTotal || alreadyReconciled
                        }
                    >
                        {isLoading ? (
                            <>
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                Creating...
                            </>
                        ) : (
                            'Create Reconciliation'
                        )}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
