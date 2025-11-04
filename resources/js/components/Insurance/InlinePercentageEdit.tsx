import { Input } from '@/components/ui/input';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { router } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import { useState, useRef, useEffect, KeyboardEvent } from 'react';

interface InlinePercentageEditProps {
    value: number;
    ruleId: number;
    onSave?: (newValue: number) => void;
    min?: number;
    max?: number;
    className?: string;
}

export function InlinePercentageEdit({
    value,
    ruleId,
    onSave,
    min = 0,
    max = 100,
    className = '',
}: InlinePercentageEditProps) {
    const [isEditing, setIsEditing] = useState(false);
    const [editValue, setEditValue] = useState(value.toString());
    const [isSaving, setIsSaving] = useState(false);
    const [showSuccess, setShowSuccess] = useState(false);
    const [showError, setShowError] = useState(false);
    const [displayValue, setDisplayValue] = useState(value);
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (isEditing && inputRef.current) {
            inputRef.current.focus();
            inputRef.current.select();
        }
    }, [isEditing]);

    const handleClick = () => {
        if (!isEditing) {
            setIsEditing(true);
            setEditValue(displayValue.toString());
        }
    };

    const handleSave = async () => {
        const numValue = parseFloat(editValue);

        // Validate
        if (isNaN(numValue)) {
            setShowError(true);
            setTimeout(() => {
                setShowError(false);
                setEditValue(displayValue.toString());
                setIsEditing(false);
            }, 400);
            return;
        }

        if (numValue < min || numValue > max) {
            setShowError(true);
            setTimeout(() => {
                setShowError(false);
                setEditValue(displayValue.toString());
                setIsEditing(false);
            }, 400);
            return;
        }

        // Optimistic update
        const previousValue = displayValue;
        setDisplayValue(numValue);
        setIsEditing(false);
        setIsSaving(true);

        // Make API call
        router.patch(
            `/admin/insurance/coverage-rules/${ruleId}/quick-update`,
            { coverage_value: numValue },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setIsSaving(false);
                    setShowSuccess(true);
                    setTimeout(() => setShowSuccess(false), 1000);
                    onSave?.(numValue);
                },
                onError: () => {
                    // Rollback on error
                    setDisplayValue(previousValue);
                    setEditValue(previousValue.toString());
                    setIsSaving(false);
                    setShowError(true);
                    setTimeout(() => setShowError(false), 400);
                },
            },
        );
    };

    const handleCancel = () => {
        setEditValue(displayValue.toString());
        setIsEditing(false);
    };

    const handleKeyDown = (e: KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            handleSave();
        } else if (e.key === 'Escape') {
            e.preventDefault();
            handleCancel();
        }
    };

    const handleBlur = () => {
        // Small delay to allow clicking the check/cancel buttons
        setTimeout(() => {
            if (isEditing) {
                handleSave();
            }
        }, 150);
    };

    return (
        <div className={`inline-flex items-center gap-2 ${className}`}>
            {isEditing ? (
                <div className="flex items-center gap-1">
                    <Input
                        ref={inputRef}
                        type="number"
                        value={editValue}
                        onChange={(e) => setEditValue(e.target.value)}
                        onKeyDown={handleKeyDown}
                        onBlur={handleBlur}
                        min={min}
                        max={max}
                        step="0.01"
                        className="h-8 w-20 text-center"
                        aria-label="Coverage percentage value"
                        aria-describedby="coverage-help"
                    />
                    <span id="coverage-help" className="text-sm text-gray-600 dark:text-gray-400">
                        %
                    </span>
                </div>
            ) : (
                <Tooltip>
                    <TooltipTrigger asChild>
                        <button
                            onClick={handleClick}
                            disabled={isSaving}
                            aria-label={`Edit coverage percentage, current value ${displayValue}%`}
                            aria-live="polite"
                            aria-atomic="true"
                            className={`
                                inline-flex items-center gap-1 rounded px-2 py-1 text-sm font-medium
                                transition-all duration-200
                                hover:bg-gray-100 dark:hover:bg-gray-800
                                focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2
                                disabled:cursor-not-allowed disabled:opacity-50
                                ${showSuccess ? 'animate-success scale-110' : ''}
                                ${showError ? 'animate-shake' : ''}
                            `}
                        >
                            <span>{displayValue}%</span>
                            {showSuccess && (
                                <Check className="h-4 w-4 text-green-600 dark:text-green-400" aria-hidden="true" />
                            )}
                            {showError && (
                                <X className="h-4 w-4 text-red-600 dark:text-red-400" aria-hidden="true" />
                            )}
                        </button>
                    </TooltipTrigger>
                    <TooltipContent>
                        <p className="text-sm">
                            Click to edit coverage percentage
                        </p>
                    </TooltipContent>
                </Tooltip>
            )}
        </div>
    );
}
