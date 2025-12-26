import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

/**
 * Copy text to clipboard with fallback for non-secure contexts (e.g., rgh.local)
 * The Clipboard API requires a secure context (HTTPS or localhost).
 * This function provides a fallback using execCommand for HTTP domains.
 */
export async function copyToClipboard(text: string): Promise<boolean> {
    // Try the modern Clipboard API first (works on HTTPS and localhost)
    if (navigator.clipboard?.writeText) {
        try {
            await navigator.clipboard.writeText(text);
            return true;
        } catch {
            // Clipboard API failed, try fallback
        }
    }

    // Fallback for non-secure contexts (HTTP on non-localhost domains like rgh.local)
    try {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.left = '-9999px';
        textarea.style.top = '-9999px';
        textarea.style.opacity = '0';
        textarea.setAttribute('readonly', ''); // Prevent keyboard on mobile
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();

        const success = document.execCommand('copy');
        document.body.removeChild(textarea);
        return success;
    } catch {
        return false;
    }
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
    } = {},
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

    return showSymbol
        ? `GH₵ ${formattedWithSeparators}`
        : formattedWithSeparators;
}
