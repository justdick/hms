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
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Activity, Plus, Star, Trash2 } from 'lucide-react';
import { useCallback, useState } from 'react';
import type { Diagnosis } from './types';

interface DiagnosisOption {
    id: number;
    name: string;
    icd_code: string;
}

interface DiagnosesManagerProps {
    diagnoses: Diagnosis[];
    onChange: (diagnoses: Diagnosis[]) => void;
    disabled?: boolean;
}

/**
 * DiagnosesManager - Component for managing claim diagnoses
 *
 * Features:
 * - Display pre-populated diagnoses from consultation
 * - Add new diagnoses with searchable dropdown
 * - Remove diagnoses from claim
 * - Set primary diagnosis
 * - Changes only affect the claim, not the original consultation
 *
 * @example
 * ```tsx
 * <DiagnosesManager
 *   diagnoses={diagnoses}
 *   onChange={handleDiagnosesChange}
 *   disabled={processing}
 * />
 * ```
 */
export function DiagnosesManager({
    diagnoses,
    onChange,
    disabled = false,
}: DiagnosesManagerProps) {
    const [open, setOpen] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [searchResults, setSearchResults] = useState<DiagnosisOption[]>([]);
    const [searching, setSearching] = useState(false);

    // Search for diagnoses
    const handleSearch = useCallback(async (query: string) => {
        setSearchQuery(query);

        if (query.length < 2) {
            setSearchResults([]);
            return;
        }

        setSearching(true);

        try {
            const response = await fetch(
                `/api/diagnoses/search?q=${encodeURIComponent(query)}`,
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                },
            );

            if (response.ok) {
                const data = await response.json();
                setSearchResults(data.data || data);
            }
        } catch (error) {
            console.error('Failed to search diagnoses:', error);
        } finally {
            setSearching(false);
        }
    }, []);

    const handleAddDiagnosis = (diagnosis: DiagnosisOption) => {
        // Check if already added
        if (diagnoses.some((d) => d.diagnosis_id === diagnosis.id)) {
            return;
        }

        const newDiagnosis: Diagnosis = {
            id: null,
            diagnosis_id: diagnosis.id,
            name: diagnosis.name,
            icd_code: diagnosis.icd_code,
            is_primary: diagnoses.length === 0, // First diagnosis is primary
        };

        onChange([...diagnoses, newDiagnosis]);
        setOpen(false);
        setSearchQuery('');
        setSearchResults([]);
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

                <Popover open={open} onOpenChange={setOpen}>
                    <PopoverTrigger asChild>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={disabled}
                            aria-label="Add diagnosis"
                        >
                            <Plus className="mr-1 h-4 w-4" />
                            Add Diagnosis
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent className="w-[400px] p-0" align="end">
                        <Command shouldFilter={false}>
                            <CommandInput
                                placeholder="Search by name or ICD-10 code..."
                                value={searchQuery}
                                onValueChange={handleSearch}
                            />
                            <CommandList>
                                {searching ? (
                                    <div className="p-4 text-center text-sm text-gray-500">
                                        Searching...
                                    </div>
                                ) : searchQuery.length < 2 ? (
                                    <div className="p-4 text-center text-sm text-gray-500">
                                        Type at least 2 characters to search
                                    </div>
                                ) : searchResults.length === 0 ? (
                                    <CommandEmpty>
                                        No diagnosis found.
                                    </CommandEmpty>
                                ) : (
                                    <CommandGroup>
                                        {searchResults.map((diagnosis) => {
                                            const isAdded = diagnoses.some(
                                                (d) =>
                                                    d.diagnosis_id ===
                                                    diagnosis.id,
                                            );
                                            return (
                                                <CommandItem
                                                    key={diagnosis.id}
                                                    value={diagnosis.icd_code}
                                                    onSelect={() =>
                                                        handleAddDiagnosis(
                                                            diagnosis,
                                                        )
                                                    }
                                                    disabled={isAdded}
                                                    className={
                                                        isAdded
                                                            ? 'opacity-50'
                                                            : ''
                                                    }
                                                >
                                                    <div className="flex flex-col">
                                                        <span className="font-medium">
                                                            {diagnosis.name}
                                                        </span>
                                                        <span className="text-sm text-gray-500">
                                                            ICD-10:{' '}
                                                            {diagnosis.icd_code}
                                                        </span>
                                                    </div>
                                                    {isAdded && (
                                                        <Badge
                                                            variant="secondary"
                                                            className="ml-auto"
                                                        >
                                                            Added
                                                        </Badge>
                                                    )}
                                                </CommandItem>
                                            );
                                        })}
                                    </CommandGroup>
                                )}
                            </CommandList>
                        </Command>
                    </PopoverContent>
                </Popover>
            </div>

            {/* Diagnoses List */}
            {diagnoses.length === 0 ? (
                <div className="rounded-lg border border-dashed border-gray-300 p-6 text-center dark:border-gray-700">
                    <Activity className="mx-auto mb-2 h-8 w-8 text-gray-400" />
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        No diagnoses added. Click "Add Diagnosis" to add one.
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
                                            handleSetPrimary(
                                                diagnosis.diagnosis_id,
                                            )
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
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() =>
                                        handleRemoveDiagnosis(
                                            diagnosis.diagnosis_id,
                                        )
                                    }
                                    disabled={disabled}
                                    className="text-red-500 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950"
                                    aria-label={`Remove ${diagnosis.name}`}
                                >
                                    <Trash2 className="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">
                Changes to diagnoses only affect this claim, not the original
                consultation.
            </p>
        </section>
    );
}
