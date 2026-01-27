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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import { Check, ChevronsUpDown, Loader2, Plus } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

interface Diagnosis {
    id: number;
    name: string;
    icd_code: string | null;
    is_custom?: boolean;
}

interface Props {
    value: number | null;
    onChange: (value: number | null) => void;
    onSelect?: (id: number) => void;
    onSelectDiagnosis?: (diagnosis: Diagnosis) => void;
    excludeIds?: number[];
    placeholder?: string;
    disabled?: boolean;
}

interface CustomDiagnosisFormProps {
    search: string;
    customIcdCode: string;
    setCustomIcdCode: (value: string) => void;
    customError: string | null;
    setCustomError: (value: string | null) => void;
    creating: boolean;
    onCancel: () => void;
    onSubmit: () => void;
}

function CustomDiagnosisForm({
    search,
    customIcdCode,
    setCustomIcdCode,
    customError,
    setCustomError,
    creating,
    onCancel,
    onSubmit,
}: CustomDiagnosisFormProps) {
    return (
        <div className="space-y-3">
            <div className="text-sm font-medium">Add Custom Diagnosis</div>
            <div className="space-y-2">
                <div>
                    <Label className="text-xs text-muted-foreground">
                        Diagnosis Name
                    </Label>
                    <div className="mt-1 text-sm font-medium">{search}</div>
                </div>
                <div>
                    <Label htmlFor="icd-code" className="text-xs">
                        ICD-10 Code <span className="text-destructive">*</span>
                    </Label>
                    <Input
                        id="icd-code"
                        placeholder="e.g., A00.0"
                        value={customIcdCode}
                        onChange={(e) => {
                            setCustomIcdCode(e.target.value.toUpperCase());
                            setCustomError(null);
                        }}
                        className="mt-1 h-8"
                        autoFocus
                    />
                    {customError && (
                        <p className="mt-1 text-xs text-destructive">
                            {customError}
                        </p>
                    )}
                </div>
            </div>
            <div className="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    className="flex-1"
                    onClick={onCancel}
                    disabled={creating}
                >
                    Cancel
                </Button>
                <Button
                    size="sm"
                    className="flex-1"
                    onClick={onSubmit}
                    disabled={creating || !customIcdCode.trim()}
                >
                    {creating ? (
                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                    ) : (
                        <Plus className="mr-2 h-4 w-4" />
                    )}
                    Add
                </Button>
            </div>
        </div>
    );
}

export default function AsyncDiagnosisSelect({
    value,
    onChange,
    onSelect,
    onSelectDiagnosis,
    excludeIds = [],
    placeholder = 'Search diagnoses...',
    disabled = false,
}: Props) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [diagnoses, setDiagnoses] = useState<Diagnosis[]>([]);
    const [loading, setLoading] = useState(false);
    const [creating, setCreating] = useState(false);
    const [showCustomForm, setShowCustomForm] = useState(false);
    const [customIcdCode, setCustomIcdCode] = useState('');
    const [customError, setCustomError] = useState<string | null>(null);
    const [selectedDiagnosis, setSelectedDiagnosis] =
        useState<Diagnosis | null>(null);
    const debounceRef = useRef<NodeJS.Timeout | null>(null);

    // Check if exact match exists in results (case-insensitive)
    const hasExactMatch = useCallback(
        (searchTerm: string, results: Diagnosis[]) => {
            const normalizedSearch = searchTerm.toLowerCase().trim();
            return results.some(
                (d) => d.name.toLowerCase().trim() === normalizedSearch,
            );
        },
        [],
    );

    // Search function
    const searchDiagnoses = useCallback(
        async (query: string) => {
            if (query.length < 2) {
                setDiagnoses([]);
                setLoading(false);
                return;
            }

            setLoading(true);
            try {
                const response = await fetch(
                    `/consultation/diagnoses/search?q=${encodeURIComponent(query)}`,
                );
                const data = await response.json();
                // Filter out excluded IDs
                const filtered = data.filter(
                    (d: Diagnosis) => !excludeIds.includes(d.id),
                );
                setDiagnoses(filtered);
            } catch (error) {
                console.error('Failed to search diagnoses:', error);
                setDiagnoses([]);
            } finally {
                setLoading(false);
            }
        },
        [excludeIds],
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

    // Clear search when popover closes
    useEffect(() => {
        if (!open) {
            setSearch('');
            setDiagnoses([]);
            setShowCustomForm(false);
            setCustomIcdCode('');
            setCustomError(null);
        }
    }, [open]);

    const handleSelect = (diagnosis: Diagnosis) => {
        if (onSelectDiagnosis) {
            // Pass full diagnosis object
            onSelectDiagnosis(diagnosis);
        } else if (onSelect) {
            // Direct add mode - call onSelect immediately and don't store value
            onSelect(diagnosis.id);
        } else {
            // Standard mode - store selection for parent to use
            setSelectedDiagnosis(diagnosis);
            onChange(diagnosis.id);
        }
        setOpen(false);
    };

    const handleCreateCustom = async () => {
        if (search.length < 2 || creating || !customIcdCode.trim()) {
            if (!customIcdCode.trim()) {
                setCustomError('ICD-10 code is required');
            }
            return;
        }

        setCreating(true);
        setCustomError(null);
        try {
            const response = await fetch('/consultation/diagnoses/custom', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        document.querySelector<HTMLMetaElement>(
                            'meta[name="csrf-token"]',
                        )?.content || '',
                },
                body: JSON.stringify({
                    diagnosis: search,
                    icd_10: customIcdCode.trim(),
                }),
            });

            if (!response.ok) {
                const error = await response.json();
                console.error('Failed to create custom diagnosis:', error);
                setCustomError(error.message || 'Failed to create diagnosis');
                return;
            }

            const newDiagnosis: Diagnosis = await response.json();
            handleSelect(newDiagnosis);
            setShowCustomForm(false);
            setCustomIcdCode('');
        } catch (error) {
            console.error('Failed to create custom diagnosis:', error);
            setCustomError('Failed to create diagnosis');
        } finally {
            setCreating(false);
        }
    };

    const handleShowCustomForm = () => {
        setShowCustomForm(true);
        setCustomIcdCode('');
        setCustomError(null);
    };

    const displayValue = selectedDiagnosis?.name || placeholder;

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    className="w-full justify-between"
                    disabled={disabled}
                >
                    <span className="truncate">
                        {value ? displayValue : placeholder}
                    </span>
                    <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-[500px] p-0" align="start">
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
                                Type at least 2 characters to search
                            </div>
                        )}
                        {!loading &&
                            search.length >= 2 &&
                            diagnoses.length === 0 && (
                                <CommandEmpty className="px-2 py-2">
                                    {!showCustomForm ? (
                                        <>
                                            <div className="mb-2 text-center text-sm text-muted-foreground">
                                                No diagnosis found for "{search}
                                                "
                                            </div>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="w-full"
                                                onClick={handleShowCustomForm}
                                            >
                                                <Plus className="mr-2 h-4 w-4" />
                                                Add as custom diagnosis
                                            </Button>
                                        </>
                                    ) : (
                                        <CustomDiagnosisForm
                                            search={search}
                                            customIcdCode={customIcdCode}
                                            setCustomIcdCode={setCustomIcdCode}
                                            customError={customError}
                                            setCustomError={setCustomError}
                                            creating={creating}
                                            onCancel={() => {
                                                setShowCustomForm(false);
                                                setCustomIcdCode('');
                                                setCustomError(null);
                                            }}
                                            onSubmit={handleCreateCustom}
                                        />
                                    )}
                                </CommandEmpty>
                            )}
                        {!loading && diagnoses.length > 0 && (
                            <CommandGroup>
                                {diagnoses.map((diagnosis) => (
                                    <CommandItem
                                        key={diagnosis.id}
                                        value={diagnosis.id.toString()}
                                        onSelect={() => handleSelect(diagnosis)}
                                    >
                                        <Check
                                            className={cn(
                                                'mr-2 h-4 w-4',
                                                value === diagnosis.id
                                                    ? 'opacity-100'
                                                    : 'opacity-0',
                                            )}
                                        />
                                        <div className="flex flex-1 flex-col gap-1">
                                            <div className="flex items-center gap-2">
                                                <span className="font-medium">
                                                    {diagnosis.name}
                                                </span>
                                                {diagnosis.is_custom ? (
                                                    <Badge
                                                        variant="secondary"
                                                        className="bg-amber-100 text-xs text-amber-800 dark:bg-amber-900 dark:text-amber-200"
                                                    >
                                                        Custom
                                                    </Badge>
                                                ) : diagnosis.icd_code ? (
                                                    <Badge
                                                        variant="outline"
                                                        className="text-xs"
                                                    >
                                                        {diagnosis.icd_code}
                                                    </Badge>
                                                ) : null}
                                            </div>
                                        </div>
                                    </CommandItem>
                                ))}
                                {/* Show "Add custom" option when no exact match found */}
                                {!hasExactMatch(search, diagnoses) && (
                                    <>
                                        <div className="my-2 border-t" />
                                        {!showCustomForm ? (
                                            <CommandItem
                                                onSelect={handleShowCustomForm}
                                                className="text-blue-600 dark:text-blue-400"
                                            >
                                                <Plus className="mr-2 h-4 w-4" />
                                                <span>
                                                    Add "{search}" as custom
                                                    diagnosis
                                                </span>
                                            </CommandItem>
                                        ) : (
                                            <div className="p-2">
                                                <CustomDiagnosisForm
                                                    search={search}
                                                    customIcdCode={
                                                        customIcdCode
                                                    }
                                                    setCustomIcdCode={
                                                        setCustomIcdCode
                                                    }
                                                    customError={customError}
                                                    setCustomError={
                                                        setCustomError
                                                    }
                                                    creating={creating}
                                                    onCancel={() => {
                                                        setShowCustomForm(
                                                            false,
                                                        );
                                                        setCustomIcdCode('');
                                                        setCustomError(null);
                                                    }}
                                                    onSubmit={
                                                        handleCreateCustom
                                                    }
                                                />
                                            </div>
                                        )}
                                    </>
                                )}
                            </CommandGroup>
                        )}
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
