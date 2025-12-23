import type { ParsedPrescription } from '@/components/Prescription/InterpretationPanel';
import { useCallback, useEffect, useRef, useState } from 'react';

interface UsePrescriptionParserOptions {
    debounceMs?: number;
    drugId?: number | null;
}

interface UsePrescriptionParserReturn {
    result: ParsedPrescription | null;
    isLoading: boolean;
    error: string | null;
    parse: (input: string) => void;
    clearResult: () => void;
}

// Simple LRU cache for recent parse results
const parseCache = new Map<string, ParsedPrescription>();
const MAX_CACHE_SIZE = 50;

function getCacheKey(input: string, drugId?: number | null): string {
    return `${input}::${drugId ?? 'no-drug'}`;
}

function addToCache(key: string, result: ParsedPrescription): void {
    if (parseCache.size >= MAX_CACHE_SIZE) {
        // Remove oldest entry
        const firstKey = parseCache.keys().next().value;
        if (firstKey) {
            parseCache.delete(firstKey);
        }
    }
    parseCache.set(key, result);
}

export function usePrescriptionParser({
    debounceMs = 300,
    drugId,
}: UsePrescriptionParserOptions = {}): UsePrescriptionParserReturn {
    const [result, setResult] = useState<ParsedPrescription | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const abortControllerRef = useRef<AbortController | null>(null);
    const debounceTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const lastInputRef = useRef<string>('');

    const clearResult = useCallback(() => {
        setResult(null);
        setError(null);
        setIsLoading(false);
    }, []);

    const fetchParsedResult = useCallback(
        async (input: string, currentDrugId?: number | null) => {
            // Cancel any pending request
            if (abortControllerRef.current) {
                abortControllerRef.current.abort();
            }

            // Check cache first
            const cacheKey = getCacheKey(input, currentDrugId);
            const cachedResult = parseCache.get(cacheKey);
            if (cachedResult) {
                setResult(cachedResult);
                setIsLoading(false);
                return;
            }

            // Create new abort controller for this request
            abortControllerRef.current = new AbortController();

            try {
                setIsLoading(true);
                setError(null);

                // Get CSRF token from meta tag
                const csrfToken = document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute('content');

                const response = await fetch(
                    '/consultation/prescriptions/parse',
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                        },
                        body: JSON.stringify({
                            input,
                            drug_id: currentDrugId,
                        }),
                        signal: abortControllerRef.current.signal,
                    },
                );

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();

                // Cache the result
                addToCache(cacheKey, data);

                setResult(data);
                setError(null);
            } catch (err) {
                if (err instanceof Error && err.name === 'AbortError') {
                    // Request was cancelled, ignore
                    return;
                }
                setError(
                    err instanceof Error
                        ? err.message
                        : 'Failed to parse prescription',
                );
                setResult(null);
            } finally {
                setIsLoading(false);
            }
        },
        [],
    );

    const parse = useCallback(
        (input: string) => {
            lastInputRef.current = input;

            // Clear any pending debounce timer
            if (debounceTimerRef.current) {
                clearTimeout(debounceTimerRef.current);
            }

            // If input is empty, clear result immediately
            // Allow single character inputs (e.g., "1" or "2" for topicals)
            if (!input || input.trim().length < 1) {
                clearResult();
                return;
            }

            // Set loading state immediately for better UX
            setIsLoading(true);

            // Debounce the actual API call
            debounceTimerRef.current = setTimeout(() => {
                // Only fetch if input hasn't changed
                if (lastInputRef.current === input) {
                    fetchParsedResult(input, drugId);
                }
            }, debounceMs);
        },
        [debounceMs, drugId, fetchParsedResult, clearResult],
    );

    // Cleanup on unmount
    useEffect(() => {
        return () => {
            if (debounceTimerRef.current) {
                clearTimeout(debounceTimerRef.current);
            }
            if (abortControllerRef.current) {
                abortControllerRef.current.abort();
            }
        };
    }, []);

    // Re-parse when drugId changes (if there's existing input)
    useEffect(() => {
        if (lastInputRef.current && lastInputRef.current.trim().length >= 2) {
            parse(lastInputRef.current);
        }
    }, [drugId, parse]);

    return {
        result,
        isLoading,
        error,
        parse,
        clearResult,
    };
}
