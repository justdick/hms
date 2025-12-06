import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import { Activity, Check, ChevronsUpDown, Plus, Star, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { AvailableDiagnosis, Diagnosis } from './types';

interface DiagnosesManagerProps {
    diagnoses: Diagnosis[];
    availableDiagnoses: AvailableDiagnosis[];
    onChange: (diagnoses: Diagnosis[]) => void;
    disabled?: boolean;
}

/**
 * DiagnosesManager - Component for managing claim diagnoses
 *
 * Uses pre-loaded diagnoses list with client-side filtering (like consultation page)
 */
export function DiagnosesManager({
    diagnoses,
    availableDiagnoses,
    onChange,
    disabled = false,
}: DiagnosesManagerProps) {
    const [open, setOpen] = useState(false);
    const [selectedDiagnosis, setSelectedDiagnosis] = useState<number | null>(null);

    // Get IDs of already added diagnoses
    const addedDiagnosisIds = diagnoses.map((d) => d.diagnosis_id);

    // Filter available diagnoses to exclude already added ones
    const filteredDiagnoses = availableDiagnoses.filter(
        (d) => !addedDiagnosisIds.includes(d.id),
    );

    const handleAddDiagnosis = () => {
        if (!selectedDiagnosis) return;

        const diagnosis = availableDiagnoses.find((d) => d.id === selectedDiagnosis);
        if (!diagnosis) return;

        // Check if already added
        if (diagnoses.some((d) => d.diagnosis_id === diagnosis.id)) {
            return;
        }

        const newDiagnosis: Diagnosis = {
            id: null,
            diagnosis_id: diagnosis.id,
            name: diagnosis.diagnosis,
            icd_code: diagnosis.icd_10,
            is_primary: diagnoses.length === 0, // First diagnosis is primary
        };

        onChange([...diagnoses, newDiagnosis]);
        setSelectedDiagnosis(null);
        setOpen(false);
    };

    const handleRemoveDiagnosis = (diagnosisId: number) => {
        const updated = diagnoses.filter((d) => d.diagnosis_id !== diagnosisId);

        // If we removed the primary, make the first one primary
        if (updated.length > 0 && !updated.some((d) => d.is_primary)) {
            updated[0].is_primary = true;
        }

        onChange(updated);
    };

    const handleSetPrimary = (diagnosisId: number) => {
        const updated = diagnoses.map((d) => ({
            ...d,
            is_primary: d.diagnosis_id === diagnosisId,
        }));
        onChange(updated);
    };

    return (
        <section aria-labelledby="diagnoses-heading">
            <div className="mb-4 flex items-center justify-between">
                <h3
                    id="diagnoses-heading"
                    className="flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-gray-100"
                >
                    <Activity className="h-5 w-5" aria-hidden="true" />
                    Diagnoses
                    <Badge variant="secondary" className="ml-2">
                        {diagnoses.length}
                    </Badge>
                </h3>
            </div>

            {/* Add Diagnosis */}
            {!disabled && (
                <div className="mb-4 rounded-lg border border-blue-200 bg-gradient-to-br from-blue-50 to-indigo-50 p-4 dark:border-blue-800 dark:from-blue-950/20 dark:to-indigo-950/20">
                    <Label className="mb-2 block text-sm font-medium">
                        Add Diagnosis
                    </Label>
                    <div className="flex gap-2">
                        <Popover open={open} onOpenChange={setOpen}>
                            <PopoverTrigger asChild>
                                <Button
                                    variant="outline"
                                    role="combobox"
                                    aria-expanded={open}
                                    className="flex-1 justify-between"
                                    disabled={disabled}
                                >
                                    {selectedDiagnosis
                                        ? availableDiagnoses.find(
                                              (d) => d.id === selectedDiagnosis,
                                          )?.diagnosis
                                        : 'Select diagnosis...'}
                                    <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent
                                className="w-[500px] p-0"
                                align="start"
                            >
                                <Command>
                                    <CommandInput placeholder="Search diagnoses..." />
                                    <CommandList>
                                        <CommandEmpty>
                                            No diagnosis found.
                                        </CommandEmpty>
                                        <CommandGroup>
                                            {filteredDiagnoses.map((diagnosis) => (
                                                <CommandItem
                                                    key={diagnosis.id}
                                                    value={`${diagnosis.diagnosis} ${diagnosis.code} ${diagnosis.icd_10}`}
                                                    onSelect={() => {
                                                        setSelectedDiagnosis(diagnosis.id);
                                                        setOpen(false);
                                                    }}
                                                >
                                                    <Check
                                                        className={cn(
                                                            'mr-2 h-4 w-4',
                                                            selectedDiagnosis === diagnosis.id
                                                                ? 'opacity-100'
                                                                : 'opacity-0',
                                                        )}
                                                    />
                                                    <div className="flex flex-1 flex-col gap-1">
                                                        <div className="flex items-center gap-2">
                                                            <span className="font-medium">
                                                                {diagnosis.diagnosis}
                                                            </span>
                                                            <Badge
                                                                variant="outline"
                                                                className="text-xs"
                                                            >
                                                                {diagnosis.code}
                                                            </Badge>
                                                        </div>
                                                        <div className="text-xs text-gray-600 dark:text-gray-400">
                                                            ICD-10: {diagnosis.icd_10} • Group: {diagnosis.g_drg}
                                                        </div>
                                                    </div>
                                                </CommandItem>
                                            ))}
                                        </CommandGroup>
                                    </CommandList>
                                </Command>
                            </PopoverContent>
                        </Popover>
                        <Button
                            onClick={handleAddDiagnosis}
                            disabled={!selectedDiagnosis || disabled}
                            size="icon"
                        >
                            <Plus className="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            )}

            {/* Diagnoses List */}
            {diagnoses.length === 0 ? (
                <div className="rounded-lg border border-dashed border-gray-300 p-6 text-center dark:border-gray-700">
                    <Activity className="mx-auto mb-2 h-8 w-8 text-gray-400" />
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        No diagnoses added. Select a diagnosis above to add one.
                    </p>
                </div>
            ) : (
                <div className="space-y-2">
                    {diagnoses.map((diagnosis) => (
                        <div
                            key={diagnosis.diagnosis_id}
                            className="flex items-center justify-between rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900"
                        >
                            <div className="flex items-center gap-3">
                                {diagnosis.is_primary ? (
                                    <Star
                                        className="h-5 w-5 fill-yellow-400 text-yellow-400"
                                        aria-label="Primary diagnosis"
                                    />
                                ) : (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            handleSetPrimary(diagnosis.diagnosis_id)
                                        }
                                        disabled={disabled}
                                        className="text-gray-400 hover:text-yellow-400 disabled:cursor-not-allowed disabled:opacity-50"
                                        aria-label="Set as primary diagnosis"
                                        title="Set as primary"
                                    >
                                        <Star className="h-5 w-5" />
                                    </button>
                                )}
                                <div>
                                    <p className="font-medium text-gray-900 dark:text-gray-100">
                                        {diagnosis.name}
                                    </p>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                        ICD-10: {diagnosis.icd_code}
                                    </p>
                                </div>
                            </div>

                            <div className="flex items-center gap-2">
                                {diagnosis.is_primary && (
                                    <Badge className="bg-yellow-500">
                                        Primary
                                    </Badge>
                                )}
                                {!disabled && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() =>
                                            handleRemoveDiagnosis(diagnosis.diagnosis_id)
                                        }
                                        className="text-red-500 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950"
                                        aria-label={`Remove ${diagnosis.name}`}
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">
                Changes to diagnoses only affect this claim, not the original consultation.
            </p>
        </section>
    );
}
