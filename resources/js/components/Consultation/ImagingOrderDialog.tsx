'use client';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    Check,
    ChevronsUpDown,
    ExternalLink,
    Loader2,
    Scan,
} from 'lucide-react';
import * as React from 'react';
import { useCallback, useEffect, useRef, useState } from 'react';

interface ImagingService {
    id: number;
    name: string;
    code: string;
    category: string;
    modality: string | null;
    price: number | null;
}

interface ImagingOrderDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    consultationId: number;
    excludeIds?: number[];
}

const MODALITIES = [
    { value: 'all', label: 'All Modalities' },
    { value: 'X-Ray', label: 'X-Ray' },
    { value: 'CT', label: 'CT Scan' },
    { value: 'MRI', label: 'MRI' },
    { value: 'Ultrasound', label: 'Ultrasound' },
    { value: 'Mammography', label: 'Mammography' },
    { value: 'Fluoroscopy', label: 'Fluoroscopy' },
    { value: 'Nuclear Medicine', label: 'Nuclear Medicine' },
];

export function ImagingOrderDialog({
    open,
    onOpenChange,
    consultationId,
    excludeIds = [],
}: ImagingOrderDialogProps) {
    const [serviceSelectOpen, setServiceSelectOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [services, setServices] = useState<ImagingService[]>([]);
    const [loading, setLoading] = useState(false);
    const [selectedService, setSelectedService] = useState<ImagingService | null>(null);
    const [modalityFilter, setModalityFilter] = useState('all');
    const debounceRef = useRef<NodeJS.Timeout | null>(null);

    const {
        data,
        setData,
        post,
        processing,
        reset,
        errors,
    } = useForm({
        lab_service_id: '',
        priority: 'routine',
        special_instructions: '',
    });

    const searchServices = useCallback(
        async (query: string, modality: string) => {
            if (query.length < 2) {
                setServices([]);
                setLoading(false);
                return;
            }

            setLoading(true);
            try {
                let url = `/lab/services/search?q=${encodeURIComponent(query)}&type=imaging`;
                if (modality !== 'all') {
                    url += `&modality=${encodeURIComponent(modality)}`;
                }
                const response = await fetch(url);
                const data = await response.json();
                const filtered = data.filter(
                    (s: ImagingService) => !excludeIds.includes(s.id)
                );
                setServices(filtered);
            } catch (error) {
                console.error('Failed to search imaging services:', error);
                setServices([]);
            } finally {
                setLoading(false);
            }
        },
        [excludeIds]
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
                searchServices(value, modalityFilter);
            }, 300);
        },
        [searchServices, modalityFilter]
    );

    const handleModalityChange = (modality: string) => {
        setModalityFilter(modality);
        // Re-search with new modality filter if there's a search query
        if (search.length >= 2) {
            searchServices(search, modality);
        }
    };

    const handleSelect = (service: ImagingService) => {
        setSelectedService(service);
        setData('lab_service_id', service.id.toString());
        setServiceSelectOpen(false);
        setSearch('');
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/consultation/${consultationId}/lab-orders`, {
            onSuccess: () => {
                reset();
                setSelectedService(null);
                setModalityFilter('all');
                onOpenChange(false);
            },
        });
    };

    const handleClose = () => {
        reset();
        setSelectedService(null);
        setSearch('');
        setServices([]);
        setModalityFilter('all');
        onOpenChange(false);
    };

    // Reset state when dialog opens/closes
    useEffect(() => {
        if (!open) {
            setSearch('');
            setServices([]);
            setSelectedService(null);
            setModalityFilter('all');
        }
    }, [open]);

    const isUnpriced = selectedService && (selectedService.price === null || selectedService.price === 0);

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Scan className="h-5 w-5" />
                        Order Imaging Study
                    </DialogTitle>
                    <DialogDescription>
                        Select an imaging study type and provide clinical indication.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    {/* Modality Filter */}
                    <div>
                        <Label htmlFor="modality-filter">Filter by Modality</Label>
                        <Select value={modalityFilter} onValueChange={handleModalityChange}>
                            <SelectTrigger id="modality-filter">
                                <SelectValue placeholder="Select modality" />
                            </SelectTrigger>
                            <SelectContent>
                                {MODALITIES.map((modality) => (
                                    <SelectItem key={modality.value} value={modality.value}>
                                        {modality.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Imaging Study Selection */}
                    <div>
                        <Label>Imaging Study</Label>
                        <Popover open={serviceSelectOpen} onOpenChange={setServiceSelectOpen}>
                            <PopoverTrigger asChild>
                                <Button
                                    variant="outline"
                                    role="combobox"
                                    aria-expanded={serviceSelectOpen}
                                    className="w-full justify-between"
                                >
                                    <span className="truncate">
                                        {selectedService
                                            ? selectedService.name
                                            : 'Search imaging studies...'}
                                    </span>
                                    <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent className="w-[450px] p-0" align="start">
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
                                        {!loading && search.length >= 2 && services.length === 0 && (
                                            <CommandEmpty>
                                                No imaging study found for "{search}"
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
                                                        <Check
                                                            className={`mr-2 h-4 w-4 ${
                                                                selectedService?.id === service.id
                                                                    ? 'opacity-100'
                                                                    : 'opacity-0'
                                                            }`}
                                                        />
                                                        <div className="flex flex-1 flex-col gap-1">
                                                            <div className="flex items-center gap-2">
                                                                <span className="font-medium">
                                                                    {service.name}
                                                                </span>
                                                                <Badge variant="outline" className="text-xs">
                                                                    {service.code}
                                                                </Badge>
                                                            </div>
                                                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                                                <span>
                                                                    {service.modality || service.category}
                                                                </span>
                                                                {service.price !== null && service.price > 0 && (
                                                                    <>
                                                                        <span>•</span>
                                                                        <span>
                                                                            GH₵{service.price.toFixed(2)}
                                                                        </span>
                                                                    </>
                                                                )}
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

                        {/* Selected Service Display */}
                        {selectedService && (
                            <div className="mt-2 rounded-md bg-muted p-3 text-sm">
                                <div className="flex items-center gap-2">
                                    <Scan className="h-4 w-4 text-purple-600" />
                                    <span className="font-medium">{selectedService.name}</span>
                                </div>
                                <p className="mt-1 text-muted-foreground">
                                    {selectedService.code} •{' '}
                                    {selectedService.modality || selectedService.category}
                                </p>
                            </div>
                        )}

                        {/* Unpriced Service Warning */}
                        {isUnpriced && (
                            <Alert className="mt-3 border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30">
                                <AlertTriangle className="h-4 w-4 text-amber-600 dark:text-amber-400" />
                                <AlertTitle className="text-amber-800 dark:text-amber-200">
                                    Unpriced Study - External Referral
                                </AlertTitle>
                                <AlertDescription className="text-amber-700 dark:text-amber-300">
                                    <p>This imaging study has no price configured in the system.</p>
                                    <p className="mt-1 flex items-center gap-1">
                                        <ExternalLink className="h-3 w-3" />
                                        Patient will need to do this study at an external facility.
                                    </p>
                                </AlertDescription>
                            </Alert>
                        )}

                        {errors.lab_service_id && (
                            <p className="mt-1 text-sm text-red-500">{errors.lab_service_id}</p>
                        )}
                    </div>

                    {/* Priority Selection */}
                    <div>
                        <Label htmlFor="priority">Priority</Label>
                        <Select
                            value={data.priority}
                            onValueChange={(value) => setData('priority', value)}
                        >
                            <SelectTrigger id="priority">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="routine">Routine</SelectItem>
                                <SelectItem value="urgent">Urgent</SelectItem>
                                <SelectItem value="stat">STAT (Immediate)</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Clinical Indication */}
                    <div>
                        <Label htmlFor="clinical-indication">Clinical Indication</Label>
                        <Textarea
                            id="clinical-indication"
                            placeholder="Describe the clinical indication for this imaging study..."
                            value={data.special_instructions}
                            onChange={(e) => setData('special_instructions', e.target.value)}
                            rows={4}
                        />
                        <p className="mt-1 text-xs text-muted-foreground">
                            Provide relevant clinical history and reason for the study.
                        </p>
                    </div>

                    {/* Actions */}
                    <div className="flex gap-2 pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={handleClose}
                            className="flex-1"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing || !data.lab_service_id}
                            className="flex-1"
                        >
                            {processing ? 'Ordering...' : 'Order Study'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default ImagingOrderDialog;
