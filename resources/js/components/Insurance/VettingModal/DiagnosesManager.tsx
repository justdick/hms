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
import {
    Activity,
    Check,
    ChevronsUpDown,
    Loader2,
    Plus,
    Star,
    Trash2,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import type { Diagnosis } from './types';

interface SearchedDiagnosis {
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
 * Uses async server-side search for diagnoses (12,500+ records)
 */
export function DiagnosesManager({
    diagnoses,
    onChange,
    disabled = false,
}: DiagnosesManagerProps) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [searchResults, setSearchResults] = useState<SearchedDiagnosis[]>([]);
    const [loading, setLoading] = useState(false);
    const [selectedDiagnosis, setSelectedDiagnosis] =
        useState<SearchedDiagnosis | null>(null);
    const debounceRef = useRef<NodeJS.Timeout | null>(null);

    // Get IDs of already added diagnoses
    const addedDiagnosisIds = diagnoses.map((d) => d.diagnosis_id);

    // Search function
    const searchDiagnoses = useCallback(
        async (query: string) => {
            if (query.length < 2) {
                setSearchResults([]);
                setLoading(false);
                return;
            }

            setLoading(true);
            try {
                const response = await fetch(
                    `/consultation/diagnoses/search?q=${encodeURIComponent(query)}`,
                );
                const data = await response.json();
                // Filter out already added diagnoses
                const filtered = data.filter(
                    (d: SearchedDiagnosis) => !addedDiagnosisIds.includes(d.id),
                );
                setSearchResults(filtered);
            } catch (error) {
                console.error('Failed to search diagnoses:', error);
                setSearchResults([]);
            } finally {
                setLoading(false);
            }
        },
        [addedDiagnosisIds],
    );

    // Handle search input change with debounce
    const handleSearchChange = useCallback(
        (value: string) => {
            setSearch(value);
            if (value.length >= 2) {
                setLoading(true);
            }

            // Clear previous timeout
            if (debounceRef.current) {
                clearTimeout(debounceRef.current);
            }

            // Set new timeout
            debounceRef.current = setTimeout(() => {
                searchDiagnoses(value);
            }, 300);
        },
        [searchDiagnoses],
    );

    // Clear search when popover closes (but keep selected diagnosis)
    useEffect(() => {
        if (!open) {
            setSearch('');
            setSearchResults([]);
            // Don't clear selectedDiagnosis here - user needs to click "+" to add it
        }
    }, [open]);

    const handleSelectDiagnosis = (diagnosis: SearchedDiagnosis) => {
        setSelectedDiagnosis(diagnosis);
        setOpen(false); // Close popover after selection
    };

    const handleAddDiagnosis = () => {
        if (!selectedDiagnosis) return;

        // Check if already added
        if (diagnoses.some((d) => d.diagnosis_id === selectedDiagnosis.id)) {
            return;
        }

        const newDiagnosis: Diagnosis = {
            id: null,
            diagnosis_id: selectedDiagnosis.id,
            name: selectedDiagnosis.name,
            icd_code: selectedDiagnosis.icd_code,
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
                                        ? selectedDiagnosis.name
                                        : 'Search diagnoses...'}
                                    <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent
                                className="w-[500px] p-0"
                                align="start"
                            >
                                <Command shouldFilter={false}>
                                    <CommandInput
                                        placeholder="Type at least 2 characters to search..."
                                        value={search}
                                        onValueChange={handleSearchChange}
                                    />
                                    <CommandList>
                                        {loading && (
                                            <div className="flex items-center justify-center py-6">
                                                <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
                                            </div>
                                        )}
                                        {!loading && search.length < 2 && (
                                            <div className="py-6 text-center text-sm text-muted-foreground">
                                                Type at least 2 characters to
                                                search
                                            </div>
                                        )}
                                        {!loading &&
                                            search.length >= 2 &&
                                            searchResults.length === 0 && (
                                                <CommandEmpty>
                                                    No diagnosis found.
                                                </CommandEmpty>
                                            )}
                                        {!loading &&
                                            searchResults.length > 0 && (
                                                <CommandGroup>
                                                    {searchResults.map(
                                                        (diagnosis) => (
                                                            <CommandItem
                                                                key={
                                                                    diagnosis.id
                                                                }
                                                                value={diagnosis.id.toString()}
                                                                onSelect={() =>
                                                                    handleSelectDiagnosis(
                                                                        diagnosis,
                                                                    )
                                                                }
                                                            >
                                                                <Check
                                                                    className={cn(
                                                                        'mr-2 h-4 w-4',
                                                                        selectedDiagnosis?.id ===
                                                                            diagnosis.id
                                                                            ? 'opacity-100'
                                                                            : 'opacity-0',
                                                                    )}
                                                                />
                                                                <div className="flex flex-1 flex-col gap-1">
                                                                    <div className="flex items-center gap-2">
                                                                        <span className="font-medium">
                                                                            {
                                                                                diagnosis.name
                                                                            }
                                                                        </span>
                                                                        <Badge
                                                                            variant="outline"
                                                                            className="text-xs"
                                                                        >
                                                                            {
                                                                                diagnosis.icd_code
                                                                            }
                                                                        </Badge>
                                                                    </div>
                                                                </div>
                                                            </CommandItem>
                                                        ),
                                                    )}
                                                </CommandGroup>
                                            )}
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
                        No diagnoses added. Search and select a diagnosis above
                        to add one.
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
                                {!disabled && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() =>
                                            handleRemoveDiagnosis(
                                                diagnosis.diagnosis_id,
                                            )
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
                Changes to diagnoses only affect this claim, not the original
                consultation.
            </p>
        </section>
    );
}
