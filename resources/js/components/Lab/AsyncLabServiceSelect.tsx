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

interface LabService {
    id: number;
    name: string;
    code: string;
    category: string;
    sample_type: string;
    turnaround_time: string;
    price?: number | null;
    is_imaging?: boolean;
    modality?: string | null;
}

interface Props {
    onSelect: (service: LabService) => void;
    excludeIds?: number[];
    placeholder?: string;
    disabled?: boolean;
    filterType?: 'all' | 'laboratory' | 'imaging';
}

export default function AsyncLabServiceSelect({
    onSelect,
    excludeIds = [],
    placeholder = 'Search lab tests...',
    disabled = false,
    filterType = 'all',
}: Props) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [services, setServices] = useState<LabService[]>([]);
    const [loading, setLoading] = useState(false);
    const debounceRef = useRef<NodeJS.Timeout | null>(null);

    const searchServices = useCallback(
        async (query: string) => {
            if (query.length < 2) {
                setServices([]);
                setLoading(false);
                return;
            }

            setLoading(true);
            try {
                let url = `/lab/services/search?q=${encodeURIComponent(query)}`;
                if (filterType !== 'all') {
                    url += `&type=${filterType}`;
                }
                const response = await fetch(url);
                const data = await response.json();
                const filtered = data.filter(
                    (s: LabService) => !excludeIds.includes(s.id),
                );
                setServices(filtered);
            } catch (error) {
                console.error('Failed to search lab services:', error);
                setServices([]);
            } finally {
                setLoading(false);
            }
        },
        [excludeIds, filterType],
    );

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
                searchServices(value);
            }, 300);
        },
        [searchServices],
    );

    useEffect(() => {
        if (!open) {
            setSearch('');
            setServices([]);
        }
    }, [open]);

    const handleSelect = (service: LabService) => {
        onSelect(service);
        setOpen(false);
        setSearch('');
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
                    <span className="truncate">{placeholder}</span>
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
                            services.length === 0 && (
                                <CommandEmpty>
                                    No lab test found for "{search}"
                                </CommandEmpty>
                            )}
                        {!loading && services.length > 0 && (
                            <CommandGroup>
                                {services.map((service) => (
                                    <CommandItem
                                        key={service.id}
                                        value={service.id.toString()}
                                        onSelect={() => handleSelect(service)}
                                        className="cursor-pointer"
                                    >
                                        <Check className="mr-2 h-4 w-4 opacity-0" />
                                        <div className="flex flex-1 flex-col gap-1">
                                            <div className="flex items-center gap-2">
                                                <span className="font-medium">
                                                    {service.name}
                                                </span>
                                                <Badge
                                                    variant="outline"
                                                    className="text-xs"
                                                >
                                                    {service.code}
                                                </Badge>
                                            </div>
                                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                                <span>{service.category}</span>
                                                <span>•</span>
                                                <span>{service.sample_type}</span>
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
