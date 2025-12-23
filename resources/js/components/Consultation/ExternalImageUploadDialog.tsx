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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';
import {
    AlertCircle,
    Building2,
    Calendar,
    Check,
    ChevronsUpDown,
    FileText,
    Image,
    Loader2,
    Scan,
    Upload,
    X,
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

interface FileWithPreview {
    file: File;
    id: string;
    preview: string | null;
    description: string;
    error?: string;
}

interface ExternalImageUploadDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    consultationId: number;
}

const MAX_FILE_SIZE = 50; // 50MB
const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'application/pdf'];

export function ExternalImageUploadDialog({
    open,
    onOpenChange,
    consultationId,
}: ExternalImageUploadDialogProps) {
    const [serviceSelectOpen, setServiceSelectOpen] = useState(false);
    const [search, setSearch] = useState('');
    const [services, setServices] = useState<ImagingService[]>([]);
    const [loading, setLoading] = useState(false);
    const [selectedService, setSelectedService] = useState<ImagingService | null>(null);
    const [facilityName, setFacilityName] = useState('');
    const [studyDate, setStudyDate] = useState('');
    const [notes, setNotes] = useState('');
    const [files, setFiles] = useState<FileWithPreview[]>([]);
    const [isDragging, setIsDragging] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const fileInputRef = useRef<HTMLInputElement>(null);
    const debounceRef = useRef<NodeJS.Timeout | null>(null);

    const searchServices = useCallback(async (query: string) => {
        if (query.length < 2) {
            setServices([]);
            setLoading(false);
            return;
        }

        setLoading(true);
        try {
            const url = `/lab/services/search?q=${encodeURIComponent(query)}&type=imaging`;
            const response = await fetch(url);
            const data = await response.json();
            setServices(data);
        } catch (error) {
            console.error('Failed to search imaging services:', error);
            setServices([]);
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
                searchServices(value);
            }, 300);
        },
        [searchServices]
    );

    const handleSelectService = (service: ImagingService) => {
        setSelectedService(service);
        setServiceSelectOpen(false);
        setSearch('');
        setErrors((prev) => ({ ...prev, lab_service_id: '' }));
    };

    const formatFileSize = (bytes: number) => {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    const validateFile = (file: File): string | null => {
        if (!ACCEPTED_TYPES.includes(file.type)) {
            return 'Invalid file type. Only JPEG, PNG, and PDF files are allowed.';
        }
        const maxSizeBytes = MAX_FILE_SIZE * 1024 * 1024;
        if (file.size > maxSizeBytes) {
            return `File too large. Maximum size: ${MAX_FILE_SIZE}MB`;
        }
        return null;
    };

    const createFilePreview = (file: File): string | null => {
        if (file.type.startsWith('image/')) {
            return URL.createObjectURL(file);
        }
        return null;
    };

    const addFiles = (newFiles: FileList | File[]) => {
        const fileArray = Array.from(newFiles);
        const newFileItems: FileWithPreview[] = fileArray.map((file) => {
            const error = validateFile(file);
            return {
                file,
                id: `${file.name}-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
                preview: createFilePreview(file),
                description: '',
                error: error || undefined,
            };
        });
        setFiles((prev) => [...prev, ...newFileItems]);
        setErrors((prev) => ({ ...prev, files: '' }));
    };

    const removeFile = (id: string) => {
        setFiles((prev) => {
            const file = prev.find((f) => f.id === id);
            if (file?.preview) {
                URL.revokeObjectURL(file.preview);
            }
            return prev.filter((f) => f.id !== id);
        });
    };

    const updateFileDescription = (id: string, description: string) => {
        setFiles((prev) =>
            prev.map((f) => (f.id === id ? { ...f, description } : f))
        );
    };

    const handleDragEnter = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(true);
    };

    const handleDragLeave = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(false);
    };

    const handleDragOver = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
    };

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(false);

        const droppedFiles = e.dataTransfer.files;
        if (droppedFiles.length > 0) {
            addFiles(droppedFiles);
        }
    };

    const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
        const selectedFiles = e.target.files;
        if (selectedFiles && selectedFiles.length > 0) {
            addFiles(selectedFiles);
        }
        e.target.value = '';
    };

    const validateForm = (): boolean => {
        const newErrors: Record<string, string> = {};

        if (!selectedService) {
            newErrors.lab_service_id = 'Please select an imaging study type.';
        }

        if (!facilityName.trim()) {
            newErrors.external_facility_name = 'External facility name is required.';
        }

        if (!studyDate) {
            newErrors.external_study_date = 'Study date is required.';
        } else {
            const selectedDate = new Date(studyDate);
            const today = new Date();
            today.setHours(23, 59, 59, 999);
            if (selectedDate > today) {
                newErrors.external_study_date = 'Study date cannot be in the future.';
            }
        }

        const validFiles = files.filter((f) => !f.error);
        if (validFiles.length === 0) {
            newErrors.files = 'Please select at least one valid file to upload.';
        }

        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!validateForm()) {
            return;
        }

        setIsSubmitting(true);

        const formData = new FormData();
        formData.append('lab_service_id', selectedService!.id.toString());
        formData.append('external_facility_name', facilityName.trim());
        formData.append('external_study_date', studyDate);
        if (notes.trim()) {
            formData.append('notes', notes.trim());
        }

        const validFiles = files.filter((f) => !f.error);
        validFiles.forEach((fileItem, index) => {
            formData.append('files[]', fileItem.file);
            if (fileItem.description.trim()) {
                formData.append(`descriptions[${index}]`, fileItem.description.trim());
            }
        });

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            
            const response = await fetch(`/consultation/${consultationId}/external-images`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: formData,
            });

            if (response.ok) {
                // Success - close dialog and refresh page
                handleClose();
                router.reload({ only: ['consultation'] });
            } else {
                // Handle validation errors
                const data = await response.json();
                if (data.errors) {
                    const newErrors: Record<string, string> = {};
                    Object.entries(data.errors).forEach(([key, value]) => {
                        newErrors[key] = Array.isArray(value) ? value[0] : String(value);
                    });
                    setErrors(newErrors);
                } else if (data.message) {
                    setErrors({ general: data.message });
                }
            }
        } catch (error) {
            console.error('Upload error:', error);
            setErrors({ general: 'Failed to upload files. Please try again.' });
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleClose = () => {
        // Cleanup previews
        files.forEach((f) => {
            if (f.preview) {
                URL.revokeObjectURL(f.preview);
            }
        });
        // Reset state
        setSelectedService(null);
        setFacilityName('');
        setStudyDate('');
        setNotes('');
        setFiles([]);
        setSearch('');
        setServices([]);
        setErrors({});
        onOpenChange(false);
    };

    // Reset state when dialog opens/closes
    useEffect(() => {
        if (!open) {
            setSearch('');
            setServices([]);
        }
    }, [open]);

    // Cleanup previews on unmount
    useEffect(() => {
        return () => {
            files.forEach((f) => {
                if (f.preview) {
                    URL.revokeObjectURL(f.preview);
                }
            });
        };
    }, []);

    const validFilesCount = files.filter((f) => !f.error).length;
    const hasFileErrors = files.some((f) => f.error);

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Upload className="h-5 w-5" />
                        Upload External Imaging Study
                    </DialogTitle>
                    <DialogDescription>
                        Upload imaging results from an external facility. These images will be
                        added to the patient's record for reference.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-5">
                    {/* General Error */}
                    {errors.general && (
                        <Alert variant="destructive">
                            <AlertCircle className="h-4 w-4" />
                            <AlertTitle>Error</AlertTitle>
                            <AlertDescription>{errors.general}</AlertDescription>
                        </Alert>
                    )}

                    {/* Imaging Study Type Selection */}
                    <div className="space-y-2">
                        <Label>
                            Imaging Study Type <span className="text-destructive">*</span>
                        </Label>
                        <Popover open={serviceSelectOpen} onOpenChange={setServiceSelectOpen}>
                            <PopoverTrigger asChild>
                                <Button
                                    variant="outline"
                                    role="combobox"
                                    aria-expanded={serviceSelectOpen}
                                    className={cn(
                                        'w-full justify-between',
                                        errors.lab_service_id && 'border-destructive'
                                    )}
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
                                                        onSelect={() => handleSelectService(service)}
                                                        className="cursor-pointer"
                                                    >
                                                        <Check
                                                            className={cn(
                                                                'mr-2 h-4 w-4',
                                                                selectedService?.id === service.id
                                                                    ? 'opacity-100'
                                                                    : 'opacity-0'
                                                            )}
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
                                                            <span className="text-xs text-muted-foreground">
                                                                {service.modality || service.category}
                                                            </span>
                                                        </div>
                                                    </CommandItem>
                                                ))}
                                            </CommandGroup>
                                        )}
                                    </CommandList>
                                </Command>
                            </PopoverContent>
                        </Popover>
                        {selectedService && (
                            <div className="mt-2 flex items-center gap-2 rounded-md bg-muted p-3 text-sm">
                                <Scan className="h-4 w-4 text-purple-600" />
                                <div>
                                    <span className="font-medium">{selectedService.name}</span>
                                    <span className="ml-2 text-muted-foreground">
                                        ({selectedService.code})
                                    </span>
                                </div>
                            </div>
                        )}
                        {errors.lab_service_id && (
                            <p className="text-sm text-destructive">{errors.lab_service_id}</p>
                        )}
                    </div>

                    {/* External Facility Name */}
                    <div className="space-y-2">
                        <Label htmlFor="facility-name">
                            <Building2 className="mr-1 inline h-4 w-4" />
                            External Facility Name <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="facility-name"
                            placeholder="Enter the name of the facility where the study was performed"
                            value={facilityName}
                            onChange={(e) => {
                                setFacilityName(e.target.value);
                                setErrors((prev) => ({ ...prev, external_facility_name: '' }));
                            }}
                            className={cn(errors.external_facility_name && 'border-destructive')}
                        />
                        {errors.external_facility_name && (
                            <p className="text-sm text-destructive">{errors.external_facility_name}</p>
                        )}
                    </div>

                    {/* Study Date */}
                    <div className="space-y-2">
                        <Label htmlFor="study-date">
                            <Calendar className="mr-1 inline h-4 w-4" />
                            Study Date <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="study-date"
                            type="date"
                            value={studyDate}
                            onChange={(e) => {
                                setStudyDate(e.target.value);
                                setErrors((prev) => ({ ...prev, external_study_date: '' }));
                            }}
                            max={new Date().toISOString().split('T')[0]}
                            className={cn(errors.external_study_date && 'border-destructive')}
                        />
                        {errors.external_study_date && (
                            <p className="text-sm text-destructive">{errors.external_study_date}</p>
                        )}
                    </div>

                    {/* File Upload Area */}
                    <div className="space-y-2">
                        <Label>
                            Image Files <span className="text-destructive">*</span>
                        </Label>
                        <div
                            className={cn(
                                'relative rounded-lg border-2 border-dashed p-6 text-center transition-colors',
                                isDragging
                                    ? 'border-primary bg-primary/5'
                                    : 'border-muted-foreground/25 hover:border-muted-foreground/50',
                                errors.files && 'border-destructive'
                            )}
                            onDragEnter={handleDragEnter}
                            onDragLeave={handleDragLeave}
                            onDragOver={handleDragOver}
                            onDrop={handleDrop}
                        >
                            <input
                                ref={fileInputRef}
                                type="file"
                                multiple
                                accept={ACCEPTED_TYPES.join(',')}
                                onChange={handleFileSelect}
                                className="hidden"
                            />
                            <div className="flex flex-col items-center gap-2">
                                <Upload className="h-8 w-8 text-muted-foreground" />
                                <div>
                                    <p className="text-sm font-medium">
                                        Drag and drop files here, or{' '}
                                        <button
                                            type="button"
                                            className="text-primary underline hover:no-underline"
                                            onClick={() => fileInputRef.current?.click()}
                                        >
                                            browse
                                        </button>
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        JPEG, PNG, PDF up to {MAX_FILE_SIZE}MB each (max 10 files)
                                    </p>
                                </div>
                            </div>
                        </div>
                        {errors.files && (
                            <p className="text-sm text-destructive">{errors.files}</p>
                        )}
                    </div>

                    {/* File List */}
                    {files.length > 0 && (
                        <div className="space-y-3">
                            <Label>Selected Files ({files.length})</Label>
                            <div className="max-h-[200px] space-y-2 overflow-y-auto">
                                {files.map((fileItem) => (
                                    <div
                                        key={fileItem.id}
                                        className={cn(
                                            'flex items-start gap-3 rounded-lg border p-3',
                                            fileItem.error && 'border-destructive bg-destructive/5'
                                        )}
                                    >
                                        {/* Preview */}
                                        <div className="h-14 w-14 flex-shrink-0 overflow-hidden rounded-md bg-muted">
                                            {fileItem.preview ? (
                                                <img
                                                    src={fileItem.preview}
                                                    alt={fileItem.file.name}
                                                    className="h-full w-full object-cover"
                                                />
                                            ) : (
                                                <div className="flex h-full w-full items-center justify-center">
                                                    <FileText className="h-6 w-6 text-muted-foreground" />
                                                </div>
                                            )}
                                        </div>

                                        {/* File Info */}
                                        <div className="min-w-0 flex-1 space-y-2">
                                            <div className="flex items-start justify-between gap-2">
                                                <div className="min-w-0">
                                                    <p
                                                        className="truncate text-sm font-medium"
                                                        title={fileItem.file.name}
                                                    >
                                                        {fileItem.file.name}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {formatFileSize(fileItem.file.size)}
                                                    </p>
                                                </div>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="h-6 w-6"
                                                    onClick={() => removeFile(fileItem.id)}
                                                >
                                                    <X className="h-4 w-4" />
                                                </Button>
                                            </div>

                                            {/* Description Input */}
                                            {!fileItem.error && (
                                                <Input
                                                    placeholder="Description (e.g., PA View, Lateral View)"
                                                    value={fileItem.description}
                                                    onChange={(e) =>
                                                        updateFileDescription(fileItem.id, e.target.value)
                                                    }
                                                    className="h-8 text-sm"
                                                />
                                            )}

                                            {/* Error Message */}
                                            {fileItem.error && (
                                                <p className="text-xs text-destructive">{fileItem.error}</p>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                            {hasFileErrors && (
                                <p className="text-sm text-amber-600 dark:text-amber-400">
                                    Some files have errors and will not be uploaded.
                                </p>
                            )}
                        </div>
                    )}

                    {/* Notes */}
                    <div className="space-y-2">
                        <Label htmlFor="notes">Notes (Optional)</Label>
                        <Textarea
                            id="notes"
                            placeholder="Any additional notes about this external imaging study..."
                            value={notes}
                            onChange={(e) => setNotes(e.target.value)}
                            rows={3}
                        />
                    </div>

                    {/* Info Alert */}
                    <Alert className="border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-950/30">
                        <AlertCircle className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                        <AlertTitle className="text-blue-800 dark:text-blue-200">
                            External Images
                        </AlertTitle>
                        <AlertDescription className="text-blue-700 dark:text-blue-300">
                            External imaging studies are for reference only and will not generate
                            billing charges. They will be clearly marked as external in the
                            patient's record.
                        </AlertDescription>
                    </Alert>

                    {/* Actions */}
                    <div className="flex gap-2 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={handleClose}
                            className="flex-1"
                            disabled={isSubmitting}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={isSubmitting || validFilesCount === 0}
                            className="flex-1"
                        >
                            {isSubmitting ? (
                                <>
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    Uploading...
                                </>
                            ) : (
                                <>
                                    <Upload className="mr-2 h-4 w-4" />
                                    Upload {validFilesCount > 0 ? `${validFilesCount} File${validFilesCount !== 1 ? 's' : ''}` : 'Files'}
                                </>
                            )}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default ExternalImageUploadDialog;
