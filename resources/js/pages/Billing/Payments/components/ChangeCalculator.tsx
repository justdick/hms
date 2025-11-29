import { Alert, AlertDescription } from '@/components/ui/alert';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { AlertTriangle, ArrowRight, Calculator, CheckCircle2, Minus } from 'lucide-react';
import { useEffect, useState } from 'react';

export interface ChangeCalculationResult {
    amountTendered: number;
    amountDue: number;
    change: number;
    isValid: boolean;
    isSufficient: boolean;
}

interface ChangeCalculatorProps {
    amountDue: number;
    formatCurrency: (amount: number) => string;
    onAmountTenderedChange?: (amount: number) => void;
    initialAmountTendered?: number;
    showCard?: boolean;
}

/**
 * ChangeCalculator Component
 * 
 * Displays an amount tendered input field for cash payments.
 * Calculates and displays change to return when tendered exceeds due.
 * Shows warning when tendered is less than due.
 * Shows calculation breakdown (Tendered - Due = Change).
 * 
 * Requirements: 4.1, 4.2, 4.3, 4.4
 */
export function ChangeCalculator({
    amountDue,
    formatCurrency,
    onAmountTenderedChange,
    initialAmountTendered,
    showCard = true,
}: ChangeCalculatorProps) {
    const [amountTendered, setAmountTendered] = useState<number>(
        initialAmountTendered ?? amountDue
    );

    // Update amount tendered when amountDue changes
    useEffect(() => {
        if (initialAmountTendered === undefined) {
            setAmountTendered(amountDue);
        }
    }, [amountDue, initialAmountTendered]);

    const calculation = calculateChange(amountTendered, amountDue);

    const handleAmountChange = (value: string) => {
        const numValue = parseFloat(value) || 0;
        setAmountTendered(numValue);
        onAmountTenderedChange?.(numValue);
    };

    const content = (
        <div className="space-y-4">
            {/* Amount Tendered Input - Requirement 4.1 */}
            <div className="space-y-2">
                <Label htmlFor="amount-tendered" className="flex items-center gap-2">
                    <Calculator className="h-4 w-4" />
                    Amount Tendered by Patient
                </Label>
                <Input
                    id="amount-tendered"
                    type="number"
                    value={amountTendered || ''}
                    onChange={(e) => handleAmountChange(e.target.value)}
                    placeholder="0.00"
                    step="0.01"
                    min="0"
                    className="text-lg font-semibold"
                    aria-describedby="amount-tendered-help"
                />
                <p id="amount-tendered-help" className="text-xs text-muted-foreground">
                    Enter the cash amount received from the patient
                </p>
            </div>

            {/* Calculation Breakdown - Requirement 4.4 */}
            {amountTendered > 0 && (
                <div className="rounded-lg border bg-muted/30 p-4">
                    <div className="space-y-2">
                        {/* Tendered */}
                        <div className="flex items-center justify-between text-sm">
                            <span className="text-muted-foreground">Amount Tendered:</span>
                            <span className="font-medium">{formatCurrency(amountTendered)}</span>
                        </div>
                        
                        {/* Due */}
                        <div className="flex items-center justify-between text-sm">
                            <span className="flex items-center gap-1 text-muted-foreground">
                                <Minus className="h-3 w-3" />
                                Amount Due:
                            </span>
                            <span className="font-medium">{formatCurrency(amountDue)}</span>
                        </div>
                        
                        {/* Divider */}
                        <div className="border-t border-border/50 my-2" />
                        
                        {/* Change Result */}
                        <div className="flex items-center justify-between">
                            <span className="flex items-center gap-1 font-medium">
                                <ArrowRight className="h-4 w-4" />
                                {calculation.isSufficient ? 'Change to Return:' : 'Amount Short:'}
                            </span>
                            <span
                                className={`text-lg font-bold ${
                                    calculation.isSufficient
                                        ? 'text-green-600 dark:text-green-400'
                                        : 'text-red-600 dark:text-red-400'
                                }`}
                            >
                                {calculation.isSufficient
                                    ? formatCurrency(calculation.change)
                                    : formatCurrency(Math.abs(calculation.change))}
                            </span>
                        </div>
                    </div>
                </div>
            )}

            {/* Status Messages - Requirements 4.2, 4.3 */}
            {amountTendered > 0 && (
                <>
                    {/* Sufficient Payment - Requirement 4.2 */}
                    {calculation.isSufficient && calculation.change > 0 && (
                        <Alert className="border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950">
                            <CheckCircle2 className="h-4 w-4 text-green-600 dark:text-green-400" />
                            <AlertDescription className="text-green-700 dark:text-green-300">
                                Return <span className="font-bold">{formatCurrency(calculation.change)}</span> change to the patient
                            </AlertDescription>
                        </Alert>
                    )}

                    {/* Exact Payment */}
                    {calculation.isSufficient && calculation.change === 0 && (
                        <Alert className="border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-950">
                            <CheckCircle2 className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                            <AlertDescription className="text-blue-700 dark:text-blue-300">
                                Exact amount received - no change required
                            </AlertDescription>
                        </Alert>
                    )}

                    {/* Insufficient Payment - Requirement 4.3 */}
                    {!calculation.isSufficient && (
                        <Alert variant="destructive" className="border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950">
                            <AlertTriangle className="h-4 w-4" />
                            <AlertDescription>
                                Insufficient payment! Patient needs to provide{' '}
                                <span className="font-bold">{formatCurrency(Math.abs(calculation.change))}</span> more
                            </AlertDescription>
                        </Alert>
                    )}
                </>
            )}

            {/* Quick Amount Buttons */}
            <div className="flex flex-wrap gap-2">
                <QuickAmountButton
                    label="Exact"
                    amount={amountDue}
                    onClick={() => handleAmountChange(amountDue.toString())}
                    formatCurrency={formatCurrency}
                />
                {amountDue > 0 && (
                    <>
                        <QuickAmountButton
                            label="Round Up"
                            amount={Math.ceil(amountDue / 10) * 10}
                            onClick={() => handleAmountChange((Math.ceil(amountDue / 10) * 10).toString())}
                            formatCurrency={formatCurrency}
                        />
                        <QuickAmountButton
                            label="Round Up 50"
                            amount={Math.ceil(amountDue / 50) * 50}
                            onClick={() => handleAmountChange((Math.ceil(amountDue / 50) * 50).toString())}
                            formatCurrency={formatCurrency}
                        />
                        <QuickAmountButton
                            label="Round Up 100"
                            amount={Math.ceil(amountDue / 100) * 100}
                            onClick={() => handleAmountChange((Math.ceil(amountDue / 100) * 100).toString())}
                            formatCurrency={formatCurrency}
                        />
                    </>
                )}
            </div>
        </div>
    );

    if (!showCard) {
        return content;
    }

    return (
        <Card className="border-amber-200 bg-amber-50/50 dark:border-amber-800 dark:bg-amber-950/30">
            <CardHeader className="pb-3">
                <CardTitle className="flex items-center gap-2 text-base text-amber-700 dark:text-amber-300">
                    <Calculator className="h-4 w-4" />
                    Cash Change Calculator
                </CardTitle>
            </CardHeader>
            <CardContent>{content}</CardContent>
        </Card>
    );
}

/**
 * Quick amount button component
 */
function QuickAmountButton({
    label,
    amount,
    onClick,
    formatCurrency,
}: {
    label: string;
    amount: number;
    onClick: () => void;
    formatCurrency: (amount: number) => string;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className="rounded-md border border-border bg-background px-3 py-1.5 text-xs font-medium transition-colors hover:bg-muted"
        >
            {label}: {formatCurrency(amount)}
        </button>
    );
}

/**
 * Utility function to calculate change
 * Can be used independently for testing or other components
 * 
 * Property 7: Change calculation accuracy
 * For any cash payment where tendered amount exceeds due amount,
 * the calculated change SHALL equal tendered minus due.
 * Validates: Requirements 4.2
 */
export function calculateChange(
    amountTendered: number,
    amountDue: number
): ChangeCalculationResult {
    const change = amountTendered - amountDue;
    const isSufficient = amountTendered >= amountDue;
    const isValid = amountTendered > 0 && amountDue >= 0;

    return {
        amountTendered,
        amountDue,
        change,
        isValid,
        isSufficient,
    };
}
