import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePrescriptionParser } from '@/hooks/use-prescription-parser';
import { InterpretationPanel, type ParsedPrescription } from './InterpretationPanel';
import { useEffect, useState } from 'react';
import { Sparkles } from 'lucide-react';

interface Drug {
    id: number;
    name: string;
    form: string;
    strength?: string;
    unit_type: string;
    bottle_size?: number;
}

interface SmartPrescriptionInputProps {
    drug: Drug | null;
    value: string;
    onChange: (value: string) => void;
    onParsedResult: (result: ParsedPrescription | null) => void;
    onSwitchToClassic?: () => void;
    disabled?: boolean;
}

const EXAMPLE_PATTERNS = [
    { pattern: '2 BD x 5 days', description: '2 tablets twice daily for 5 days' },
    { pattern: '1 TDS x 7/7', description: '1 tablet three times daily for 7 days' },
    { pattern: '1-0-1 x 30 days', description: 'Split dose: 1 morning, skip noon, 1 evening' },
    { pattern: '5ml TDS x 5 days', description: '5ml three times daily for 5 days' },
    { pattern: 'STAT', description: 'Single immediate dose' },
    { pattern: '2 PRN', description: '2 tablets as needed' },
    { pattern: '4-3-2-1 taper', description: 'Taper: 4 day 1, 3 day 2, etc.' },
];

export function SmartPrescriptionInput({
    drug,
    value,
    onChange,
    onParsedResult,
    onSwitchToClassic,
    disabled = false,
}: SmartPrescriptionInputProps) {
    const [showExamples, setShowExamples] = useState(false);
    
    const { result, isLoading, parse, clearResult } = usePrescriptionParser({
        debounceMs: 300,
        drugId: drug?.id,
    });

    // Parse input when value changes
    useEffect(() => {
        if (value.trim()) {
            parse(value);
        } else {
            clearResult();
        }
    }, [value, parse, clearResult]);

    // Notify parent of parsed result changes
    useEffect(() => {
        onParsedResult(result);
    }, [result, onParsedResult]);

    const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        onChange(e.target.value);
    };

    const handleExampleClick = (pattern: string) => {
        onChange(pattern);
        setShowExamples(false);
    };

    const handleFocus = () => {
        if (!value.trim()) {
            setShowExamples(true);
        }
    };

    const handleBlur = () => {
        // Delay hiding to allow click on examples
        setTimeout(() => setShowExamples(false), 200);
    };

    return (
        <div className="space-y-3">
            <div className="space-y-2">
                <Label htmlFor="smart-prescription-input" className="flex items-center gap-2">
                    <Sparkles className="h-4 w-4 text-emerald-500" />
                    Smart Prescription Input
                </Label>
                <div className="relative">
                    <Input
                        id="smart-prescription-input"
                        type="text"
                        placeholder="e.g., 2 BD x 5 days, 1-0-1 x 30 days, STAT"
                        value={value}
                        onChange={handleInputChange}
                        onFocus={handleFocus}
                        onBlur={handleBlur}
                        disabled={disabled || !drug}
                        className="pr-10"
                        autoComplete="off"
                    />
                    {isLoading && (
                        <div className="absolute right-3 top-1/2 -translate-y-1/2">
                            <div className="h-4 w-4 animate-spin rounded-full border-2 border-gray-300 border-t-emerald-600 dark:border-gray-600 dark:border-t-emerald-400" />
                        </div>
                    )}
                </div>
                
                {/* Example patterns dropdown */}
                {showExamples && !value.trim() && drug && (
                    <div className="rounded-lg border border-gray-200 bg-white p-3 shadow-lg dark:border-gray-700 dark:bg-gray-800">
                        <p className="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                            Example formats:
                        </p>
                        <div className="space-y-1">
                            {EXAMPLE_PATTERNS.map((example) => (
                                <button
                                    key={example.pattern}
                                    type="button"
                                    onClick={() => handleExampleClick(example.pattern)}
                                    className="flex w-full items-center justify-between rounded px-2 py-1.5 text-left text-sm hover:bg-gray-100 dark:hover:bg-gray-700"
                                >
                                    <code className="font-mono text-emerald-600 dark:text-emerald-400">
                                        {example.pattern}
                                    </code>
                                    <span className="ml-2 text-xs text-gray-500 dark:text-gray-400">
                                        {example.description}
                                    </span>
                                </button>
                            ))}
                        </div>
                    </div>
                )}

                {/* No drug selected message */}
                {!drug && (
                    <p className="text-xs text-amber-600 dark:text-amber-400">
                        Please select a drug first to enable smart input
                    </p>
                )}
            </div>

            {/* Interpretation Panel */}
            {drug && value.trim() && (
                <InterpretationPanel
                    result={result}
                    drug={drug}
                    isLoading={isLoading}
                    onSwitchToClassic={onSwitchToClassic}
                />
            )}
        </div>
    );
}
