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
import { Check, ChevronsUpDown, FileText, Loader2 } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

export interface Procedure {
    id: number;
    name: string;
    code: string;
    type: 'minor' | 'major';
    category: string;
    has_template: boolean;
}

interface Props {
    onSelect: (procedure: Procedure) => void;
    selectedProcedure?: Procedure | null;
    placeholder?: string;
    disabled?: boolean;
}

export default function AsyncProcedureSearch({
    onSelect,
    selectedProcedure = null,
    placeholder = 'Search procedures...',
    disabled = false,
}: Props) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [procedures, setProcedures] = useState<Procedure[]>([]);
    const [loading, setLoading] = useState(false);
    const debounceRef = useRef<NodeJS.Timeout | null>(null);

    const searchProcedures = useCallback(async (query: string) => {
        if (query.length < 2) {
            setProcedures([]);
            setLoading(false);
            return;
        }

        setLoading(true);
        try {
            const response = await fetch(
                `/procedures/search?q=${encodeURIComponent(query)}`,
            );
            const data = await response.json();
            setProcedures(data.procedures || []);
        } catch (error) {
            console.error('Failed to search procedures:', error);
            setProcedures([]);
        } finally {
            setLoading(false);
        }
    }, []);

    const handleSearchChange = useCallback(
        (value: string) => {
            setSearch(value);
            if (value.length >= 2) {
                setLoading(true);
            }

            if (debounceRef.current) {
                clearTimeout(debounceRef.current);
            }

            debounceRef.current = setTimeout(() => {
                searchProcedures(value);
            }, 300);
        },
        [searchProcedures],
    );

    useEffect(() => {
        if (!open) {
            setSearch('');
            setProcedures([]);
        }
    }, [open]);

    const handleSelect = (procedure: Procedure) => {
        onSelect(procedure);
        setOpen(false);
        setSearch('');
    };

    const getDisplayText = () => {
        if (selectedProcedure) {
            return (
                <span className="flex items-center gap-2">
                    <span className="truncate">{selectedProcedure.name}</span>
                    <Badge variant="outline" className="text-xs shrink-0">
                        {selectedProcedure.code}
                    </Badge>
                </span>
            );
        }
        return <span className="text-muted-foreground">{placeholder}</span>;
    };

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
                    {getDisplayText()}
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
                            procedures.length === 0 && (
                                <CommandEmpty>
                                    No procedure found for "{search}"
                                </CommandEmpty>
                            )}
                        {!loading && procedures.length > 0 && (
                            <CommandGroup>
                                {procedures.map((procedure) => (
                                    <CommandItem
                                        key={procedure.id}
                                        value={procedure.id.toString()}
                                        onSelect={() => handleSelect(procedure)}
                                        className="cursor-pointer"
                                    >
                                        <Check
                                            className={`mr-2 h-4 w-4 ${
                                                selectedProcedure?.id ===
                                                procedure.id
                                                    ? 'opacity-100'
                                                    : 'opacity-0'
                                            }`}
                                        />
                                        <div className="flex flex-1 flex-col gap-1">
                                            <div className="flex items-center gap-2">
                                                <span className="font-medium">
                                                    {procedure.name}
                                                </span>
                                                <Badge
                                                    variant="outline"
                                                    className="text-xs"
                                                >
                                                    {procedure.code}
                                                </Badge>
                                                <Badge
                                                    variant="outline"
                                                    className={`text-xs ${
                                                        procedure.type ===
                                                        'major'
                                                            ? 'border-purple-200 bg-purple-100 text-purple-700 dark:border-purple-800 dark:bg-purple-900/30 dark:text-purple-300'
                                                            : 'border-blue-200 bg-blue-100 text-blue-700 dark:border-blue-800 dark:bg-blue-900/30 dark:text-blue-300'
                                                    }`}
                                                >
                                                    {procedure.type === 'major'
                                                        ? 'Major'
                                                        : 'Minor'}
                                                </Badge>
                                                {procedure.has_template && (
                                                    <Badge
                                                        variant="secondary"
                                                        className="text-xs gap-1"
                                                    >
                                                        <FileText className="h-3 w-3" />
                                                        Template
                                                    </Badge>
                                                )}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {procedure.category}
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
