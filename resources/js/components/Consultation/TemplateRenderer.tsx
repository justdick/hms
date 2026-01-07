import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useCallback, useEffect, useMemo, useState } from 'react';

export interface TemplateVariable {
    key: string;
    label: string;
    options: string[];
}

interface Props {
    templateText: string;
    variables: TemplateVariable[];
    initialSelections?: Record<string, string>;
    onChange: (composedText: string, selections: Record<string, string>) => void;
    disabled?: boolean;
}

interface TemplatePart {
    type: 'text' | 'variable';
    content: string;
    variableKey?: string;
}

/**
 * TemplateRenderer Component
 * 
 * Parses template text containing {{variable}} placeholders and renders
 * inline Select components for each variable. Maintains selection state
 * and generates composed text with all selections.
 * 
 * Requirements: 3.2, 3.3
 */
export default function TemplateRenderer({
    templateText,
    variables,
    initialSelections = {},
    onChange,
    disabled = false,
}: Props) {
    const [selections, setSelections] = useState<Record<string, string>>(initialSelections);

    // Create a map of variable key to variable definition for quick lookup
    const variableMap = useMemo(() => {
        const map = new Map<string, TemplateVariable>();
        variables.forEach((v) => map.set(v.key, v));
        return map;
    }, [variables]);

    // Parse template text into parts (text segments and variable placeholders)
    const templateParts = useMemo((): TemplatePart[] => {
        const parts: TemplatePart[] = [];
        const regex = /\{\{(\w+)\}\}/g;
        let lastIndex = 0;
        let match;

        while ((match = regex.exec(templateText)) !== null) {
            // Add text before the variable
            if (match.index > lastIndex) {
                parts.push({
                    type: 'text',
                    content: templateText.slice(lastIndex, match.index),
                });
            }

            // Add the variable placeholder
            parts.push({
                type: 'variable',
                content: match[0],
                variableKey: match[1],
            });

            lastIndex = regex.lastIndex;
        }

        // Add remaining text after the last variable
        if (lastIndex < templateText.length) {
            parts.push({
                type: 'text',
                content: templateText.slice(lastIndex),
            });
        }

        return parts;
    }, [templateText]);

    // Generate composed text by replacing placeholders with selected values
    const generateComposedText = useCallback(
        (currentSelections: Record<string, string>): string => {
            let composed = templateText;
            
            for (const [key, value] of Object.entries(currentSelections)) {
                if (value) {
                    composed = composed.replace(new RegExp(`\\{\\{${key}\\}\\}`, 'g'), value);
                }
            }
            
            return composed;
        },
        [templateText]
    );

    // Handle selection change for a variable
    const handleSelectionChange = useCallback(
        (variableKey: string, value: string) => {
            const newSelections = {
                ...selections,
                [variableKey]: value,
            };
            setSelections(newSelections);
            
            const composedText = generateComposedText(newSelections);
            onChange(composedText, newSelections);
        },
        [selections, generateComposedText, onChange]
    );

    // Sync selections when initialSelections change (e.g., when loading saved data)
    useEffect(() => {
        const hasInitialSelections = Object.keys(initialSelections).length > 0;
        if (hasInitialSelections) {
            setSelections(initialSelections);
        }
    }, [initialSelections]);

    return (
        <div className="rounded-md border bg-muted/30 p-4 text-sm leading-relaxed">
            <div className="whitespace-pre-wrap">
                {templateParts.map((part, index) => {
                    if (part.type === 'text') {
                        return <span key={index}>{part.content}</span>;
                    }

                    const variableKey = part.variableKey!;
                    const variable = variableMap.get(variableKey);

                    if (!variable) {
                        // Variable not found in definitions, show placeholder as-is
                        return (
                            <span
                                key={index}
                                className="rounded bg-destructive/20 px-1 text-destructive"
                            >
                                {part.content}
                            </span>
                        );
                    }

                    const selectedValue = selections[variableKey];

                    return (
                        <span key={index} className="inline-block align-middle">
                            <Select
                                value={selectedValue || ''}
                                onValueChange={(value) => handleSelectionChange(variableKey, value)}
                                disabled={disabled}
                            >
                                <SelectTrigger
                                    className="inline-flex h-7 min-w-[120px] max-w-[200px] gap-1 border-primary/30 bg-primary/10 px-2 py-0.5 text-xs font-medium hover:bg-primary/20"
                                    aria-label={variable.label}
                                >
                                    <SelectValue placeholder={`[${variable.label}]`} />
                                </SelectTrigger>
                                <SelectContent>
                                    {variable.options.map((option) => (
                                        <SelectItem key={option} value={option}>
                                            {option}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </span>
                    );
                })}
            </div>
        </div>
    );
}
