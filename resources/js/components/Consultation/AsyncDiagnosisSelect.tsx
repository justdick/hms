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
import { cn } from '@/lib/utils';
import { Check, ChevronsUpDown, Loader2 } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

interface Diagnosis {
    id: number;
    name: string;
    icd_code: string;
}

interface Props {
    value: number | null;
    onChange: (value: number | null) => void;
    excludeIds?: number[];
    placeholder?: string;
    disabled?: boolean;
}

export default function AsyncDiagnosisSelect({
    value,
    onChange,
    excludeIds = [],
    placeholder = 'Search diagnoses...',
    disabled = false,
}: Props) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [diagnoses, setDiagnoses] = useState<Diagnosis[]>([]);
    const [loading, setLoading] = useState(false);
    const [selectedDiagnosis, setSelectedDiagnosis] =
        useState<Diagnosis | null>(null);
    const debounceRef = useRef<NodeJS.Timeout | null>(null);

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
        }
    }, [open]);

    const handleSelect = (diagnosis: Diagnosis) => {
        setSelectedDiagnosis(diagnosis);
        onChange(diagnosis.id);
        setOpen(false);
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
                                <CommandEmpty>
                                    No diagnosis found.
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
                                                <Badge
                                                    variant="outline"
                                                    className="text-xs"
                                                >
                                                    {diagnosis.icd_code}
                                                </Badge>
                                            </div>
                                        </div>
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                        )}
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
