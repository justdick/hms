import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

/**
 * Format a number as Ghana Cedi currency
 * @param amount - The amount to format (can be number, string, null, or undefined)
 * @param options - Optional formatting options
 * @returns Formatted currency string with GH₵ symbol
 */
export function formatCurrency(
    amount: number | string | null | undefined,
    options: {
        showSymbol?: boolean;
        decimals?: number;
    } = {}
): string {
    const { showSymbol = true, decimals = 2 } = options;

    // Handle null/undefined values
    if (amount === null || amount === undefined) {
        return showSymbol ? 'GH₵ 0.00' : '0.00';
    }

    // Convert string to number if needed
    const numAmount = typeof amount === 'string' ? parseFloat(amount) : amount;

    // Handle NaN
    if (isNaN(numAmount)) {
        return showSymbol ? 'GH₵ 0.00' : '0.00';
    }

    // Format with specified decimal places
    const formatted = numAmount.toFixed(decimals);

    // Add thousand separators
    const parts = formatted.split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    const formattedWithSeparators = parts.join('.');

    return showSymbol ? `GH₵ ${formattedWithSeparators}` : formattedWithSeparators;
}
