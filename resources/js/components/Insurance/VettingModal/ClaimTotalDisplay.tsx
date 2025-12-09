import { formatCurrency } from '@/lib/utils';
import { AlertTriangle, Calculator } from 'lucide-react';
import type { ClaimTotals } from './types';

interface ClaimTotalDisplayProps {
    totals: ClaimTotals;
    isNhis: boolean;
}

/**
 * ClaimTotalDisplay - Displays the claim total breakdown
 *
 * Features:
 * - Shows breakdown by category (G-DRG, Investigations, Prescriptions, Procedures)
 * - Updates dynamically when G-DRG selection changes
 * - Shows warning for unmapped items
 * - Displays grand total prominently
 *
 * @example
 * ```tsx
 * <ClaimTotalDisplay
 *   totals={calculatedTotals}
 *   isNhis={vettingData.is_nhis}
 * />
 * ```
 */
export function ClaimTotalDisplay({ totals, isNhis }: ClaimTotalDisplayProps) {

    return (
        <section aria-labelledby="claim-total-heading">
            <h3
                id="claim-total-heading"
                className="mb-4 flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-gray-100"
            >
                <Calculator className="h-5 w-5" aria-hidden="true" />
                Claim Total
            </h3>

            <div className="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                {/* Breakdown */}
                <div className="space-y-3">
                    {/* G-DRG (NHIS only) */}
                    {isNhis && (
                        <div className="flex items-center justify-between">
                            <span className="text-gray-600 dark:text-gray-400">
                                G-DRG Tariff
                            </span>
                            <span className="font-medium">
                                {totals.gdrg > 0
                                    ? formatCurrency(totals.gdrg)
                                    : '-'}
                            </span>
                        </div>
                    )}

                    {/* Investigations */}
                    <div className="flex items-center justify-between">
                        <span className="text-gray-600 dark:text-gray-400">
                            Investigations
                        </span>
                        <span className="font-medium">
                            {formatCurrency(totals.investigations)}
                        </span>
                    </div>

                    {/* Prescriptions */}
                    <div className="flex items-center justify-between">
                        <span className="text-gray-600 dark:text-gray-400">
                            Prescriptions
                        </span>
                        <span className="font-medium">
                            {formatCurrency(totals.prescriptions)}
                        </span>
                    </div>

                    {/* Procedures */}
                    <div className="flex items-center justify-between">
                        <span className="text-gray-600 dark:text-gray-400">
                            Procedures
                        </span>
                        <span className="font-medium">
                            {formatCurrency(totals.procedures)}
                        </span>
                    </div>

                    {/* Divider */}
                    <div className="border-t border-gray-200 dark:border-gray-700" />

                    {/* Grand Total */}
                    <div className="flex items-center justify-between">
                        <span className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            GRAND TOTAL
                        </span>
                        <span className="text-2xl font-bold text-green-600 dark:text-green-400">
                            {formatCurrency(totals.grand_total)}
                        </span>
                    </div>
                </div>

                {/* Unmapped Items Warning */}
                {isNhis && totals.unmapped_count > 0 && (
                    <div
                        className="mt-4 flex items-start gap-2 rounded-md border border-yellow-200 bg-yellow-50 p-3 text-sm text-yellow-800 dark:border-yellow-800 dark:bg-yellow-950 dark:text-yellow-200"
                        role="alert"
                    >
                        <AlertTriangle
                            className="mt-0.5 h-4 w-4 shrink-0"
                            aria-hidden="true"
                        />
                        <div>
                            <p className="font-medium">
                                {totals.unmapped_count} item
                                {totals.unmapped_count !== 1 ? 's' : ''} not
                                covered by NHIS
                            </p>
                            <p className="mt-1 text-xs">
                                Items without NHIS mapping are excluded from the
                                claim total. Consider mapping these items to
                                NHIS tariffs.
                            </p>
                        </div>
                    </div>
                )}

                {/* NHIS Calculation Note */}
                {isNhis && (
                    <p className="mt-4 text-xs text-gray-500 dark:text-gray-400">
                        Total calculated as: G-DRG Tariff + Investigations +
                        Prescriptions + Procedures (using NHIS tariff prices)
                    </p>
                )}
            </div>
        </section>
    );
}
