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
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';
import {
    AlertTriangle,
    Check,
    ChevronsUpDown,
    ExternalLink,
    Pencil,
    Plus,
    RefreshCw,
    Save,
    Trash2,
    X,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { RefillPrescriptionModal } from './RefillPrescriptionModal';

interface Drug {
    id: number;
    name: string;
    form: string;
    strength?: string;
    generic_name?: string;
    brand_name?: string;
    unit_type: string;
    bottle_size?: number;
    unit_price?: number | null;
}

interface PendingPrescription {
    id: string; // Temporary client-side ID
    drug_id: number;
    medication_name: string;
    dose_quantity: string;
    frequency: string;
    duration: string;
    quantity_to_dispense: number | null;
    instructions: string;
    drug: Drug;
}

interface ExistingPrescription {
    id: number;
    medication_name: string;
    frequency: string;
    duration: string;
    dose_quantity?: string;
    quantity_to_dispense?: number;
    instructions?: string;
    status: string;
    drug_id?: number;
}

interface Props {
    drugs: Drug[];
    existingPrescriptions: ExistingPrescription[];
    prescribableType: 'consultation' | 'ward_round';
    prescribableId: number;
    admissionId?: number; // Required for ward_round type
    roundDatetime?: string; // Ward round date for date sync
    isEditable?: boolean;
    onDelete?: (id: number) => void;
    onEdit?: (prescription: ExistingPrescription) => void;
    headerExtra?: React.ReactNode;
    previousPrescriptions?: PreviousPrescription[];
    consultationId?: number;
}

interface PreviousPrescription {
    id: number;
    medication_name: string;
    dose_quantity?: string;
    frequency: string;
    duration: string;
    instructions?: string;
    status: string;
    drug?: Drug;
    consultation: {
        id: number;
        started_at: string;
        doctor: { id: number; name: string };
        patient_checkin: { department: { id: number; name: string } };
    };
}

// Helper to extract pack size from drug name
function extractPackSize(name: string): { baseName: string; packSize: string | null } {
    const packMatch = name.match(/\s*(\(\d+['`]?s?\)|\(\d+\s*tabs?\))\s*$/i);
    if (packMatch) {
        return { baseName: name.slice(0, packMatch.index).trim(), packSize: packMatch[1] };
    }
    return { baseName: name, packSize: null };
}

// Topical preparations - no frequency/duration calculation needed
const TOPICAL_FORMS = ['cream', 'ointment', 'gel', 'lotion'];

function isTopicalPreparation(drug: Drug | undefined): boolean {
    if (!drug) return false;
    return TOPICAL_FORMS.includes(drug.form.toLowerCase());
}

// Helper function to parse frequency to get daily count
function parseFrequency(frequency: string): number | null {
    const frequencyMap: { [key: string]: number } = {
        'STAT (Immediately)': 1,
        'Once daily': 1,
        'Twice daily (BID)': 2,
        'Three times daily (TID)': 3,
        'Four times daily (QID)': 4,
        'Every 4 hours': 6,
        'Every 6 hours': 4,
        'Every 8 hours': 3,
        'Every 12 hours': 2,
        'At night (Nocte)': 1,
        'As needed (PRN)': 4,
    };
    return frequencyMap[frequency] || null;
}

// Helper function to parse duration to get number of days
function parseDuration(duration: string): number | null {
    const match = duration.match(/^(\d+)\s+days?$/i);
    if (match) return parseInt(match[1], 10);
    return null;
}

const FREQUENCY_OPTIONS = [
    { value: 'STAT (Immediately)', label: 'STAT (Immediately)' },
    { value: 'Once daily', label: 'Once daily' },
    { value: 'Twice daily (BID)', label: 'Twice daily (BID)' },
    { value: 'Three times daily (TID)', label: 'Three times daily (TID)' },
    { value: 'Four times daily (QID)', label: 'Four times daily (QID)' },
    { value: 'Every 4 hours', label: 'Every 4 hours' },
    { value: 'Every 6 hours', label: 'Every 6 hours' },
    { value: 'Every 8 hours', label: 'Every 8 hours' },
    { value: 'Every 12 hours', label: 'Every 12 hours' },
    { value: 'At night (Nocte)', label: 'At night (Nocte)' },
    { value: 'As needed (PRN)', label: 'As needed (PRN)' },
];

const DURATION_OPTIONS = [
    { value: 'Single dose', label: 'Single dose' },
    { value: '1 day', label: '1 day' },
    { value: '2 days', label: '2 days' },
    { value: '3 days', label: '3 days' },
    { value: '4 days', label: '4 days' },
    { value: '5 days', label: '5 days' },
    { value: '6 days', label: '6 days' },
    { value: '7 days', label: '7 days' },
    { value: '10 days', label: '10 days' },
    { value: '14 days', label: '14 days' },
    { value: '21 days', label: '21 days' },
    { value: '28 days', label: '28 days' },
    { value: '30 days', label: '30 days' },
];

export default function BatchPrescriptionForm({
    drugs,
    existingPrescriptions,
    prescribableType,
    prescribableId,
    admissionId,
    roundDatetime,
    isEditable = true,
    onDelete,
    onEdit,
    headerExtra,
    previousPrescriptions = [],
    consultationId,
}: Props) {
    // Pending prescriptions (not yet saved to server)
    const [pendingPrescriptions, setPendingPrescriptions] = useState<PendingPrescription[]>([]);
    const [isSaving, setIsSaving] = useState(false);
    const [showRefillModal, setShowRefillModal] = useState(false);
    
    // Form state for adding new prescription
    const [drugComboOpen, setDrugComboOpen] = useState(false);
    const [selectedDrug, setSelectedDrug] = useState<Drug | null>(null);
    const [formData, setFormData] = useState({
        dose_quantity: '',
        frequency: '',
        duration: '',
        quantity_to_dispense: '' as string | number,
        instructions: '',
    });
    
    // Editing state for pending prescriptions
    const [editingPendingId, setEditingPendingId] = useState<string | null>(null);

    // Calculate quantity based on frequency, duration, and dose
    const calculateQuantity = useCallback((
        drug: Drug,
        frequency: string,
        duration: string,
        doseQuantity: string
    ): number | null => {
        if (!frequency || !duration) return null;
        
        if (drug.unit_type === 'piece') {
            const dailyCount = parseFrequency(frequency);
            const days = parseDuration(duration);
            const dose = doseQuantity ? parseInt(doseQuantity) : 1;
            if (dailyCount && days) {
                return dose * dailyCount * days;
            }
        } else if (drug.unit_type === 'bottle') {
            return 1;
        } else if (drug.unit_type === 'vial') {
            return null; // Pharmacy determines
        }
        return 1;
    }, []);

    // Auto-calculate quantity when form fields change
    useEffect(() => {
        if (!selectedDrug || isTopicalPreparation(selectedDrug)) return;
        
        if (formData.frequency === 'STAT (Immediately)') {
            setFormData(prev => ({ ...prev, duration: 'Single dose' }));
            const dose = formData.dose_quantity ? parseInt(formData.dose_quantity) : 1;
            if (selectedDrug.unit_type === 'piece') {
                setFormData(prev => ({ ...prev, quantity_to_dispense: dose }));
            } else if (selectedDrug.unit_type === 'bottle') {
                setFormData(prev => ({ ...prev, quantity_to_dispense: 1 }));
            } else if (selectedDrug.unit_type === 'vial') {
                setFormData(prev => ({ ...prev, quantity_to_dispense: '' }));
            }
            return;
        }
        
        const qty = calculateQuantity(
            selectedDrug,
            formData.frequency,
            formData.duration,
            formData.dose_quantity
        );
        if (qty !== null) {
            setFormData(prev => ({ ...prev, quantity_to_dispense: qty }));
        }
    }, [selectedDrug, formData.frequency, formData.duration, formData.dose_quantity, calculateQuantity]);

    // Reset form
    const resetForm = () => {
        setSelectedDrug(null);
        setFormData({
            dose_quantity: '',
            frequency: '',
            duration: '',
            quantity_to_dispense: '',
            instructions: '',
        });
        setEditingPendingId(null);
    };

    // Add prescription to pending list
    const handleAddToPending = () => {
        if (!selectedDrug) return;
        
        const newPrescription: PendingPrescription = {
            id: crypto.randomUUID(),
            drug_id: selectedDrug.id,
            medication_name: selectedDrug.name,
            dose_quantity: formData.dose_quantity,
            frequency: formData.frequency,
            duration: formData.duration,
            quantity_to_dispense: formData.quantity_to_dispense ? Number(formData.quantity_to_dispense) : null,
            instructions: formData.instructions,
            drug: selectedDrug,
        };
        
        if (editingPendingId) {
            // Update existing pending prescription
            setPendingPrescriptions(prev => 
                prev.map(p => p.id === editingPendingId ? newPrescription : p)
            );
        } else {
            // Add new
            setPendingPrescriptions(prev => [...prev, newPrescription]);
        }
        
        resetForm();
    };

    // Edit a pending prescription
    const handleEditPending = (prescription: PendingPrescription) => {
        setSelectedDrug(prescription.drug);
        setFormData({
            dose_quantity: prescription.dose_quantity,
            frequency: prescription.frequency,
            duration: prescription.duration,
            quantity_to_dispense: prescription.quantity_to_dispense ?? '',
            instructions: prescription.instructions,
        });
        setEditingPendingId(prescription.id);
    };

    // Remove from pending list
    const handleRemovePending = (id: string) => {
        setPendingPrescriptions(prev => prev.filter(p => p.id !== id));
        if (editingPendingId === id) {
            resetForm();
        }
    };

    // Save all pending prescriptions to server
    const handleSaveAll = () => {
        if (pendingPrescriptions.length === 0) return;
        
        setIsSaving(true);
        
        const endpoint = prescribableType === 'consultation'
            ? `/consultation/${prescribableId}/prescriptions/batch`
            : `/admissions/${admissionId}/ward-rounds/${prescribableId}/prescriptions/batch`;
        
        router.post(endpoint, {
            prescriptions: pendingPrescriptions.map(p => ({
                drug_id: p.drug_id,
                medication_name: p.medication_name,
                dose_quantity: p.dose_quantity,
                frequency: p.frequency,
                duration: p.duration,
                quantity_to_dispense: p.quantity_to_dispense,
                instructions: p.instructions,
            })),
            ...(roundDatetime ? { round_datetime: roundDatetime } : {}),
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setPendingPrescriptions([]);
                setIsSaving(false);
            },
            onError: () => {
                setIsSaving(false);
            },
        });
    };

    // Check if form is valid for adding
    const isFormValid = () => {
        if (!selectedDrug) return false;
        if (isTopicalPreparation(selectedDrug)) {
            return formData.quantity_to_dispense && formData.instructions;
        }
        return formData.dose_quantity && formData.frequency && formData.duration;
    };

    const isSelectedDrugUnpriced = selectedDrug && 
        (selectedDrug.unit_price === null || selectedDrug.unit_price === 0);

    return (
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {/* Left Column: Add New Prescription Form */}
            {isEditable && (
                <div className="rounded-lg border border-green-200 bg-gradient-to-br from-green-50 to-emerald-50 p-6 dark:border-green-800 dark:from-green-950/20 dark:to-emerald-950/20">
                    <div className="mb-4 flex items-center justify-between">
                        <h3 className="flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {editingPendingId ? (
                                <>
                                    <Pencil className="h-5 w-5" />
                                    Edit Prescription
                                </>
                            ) : (
                                <>
                                    <Plus className="h-5 w-5" />
                                    Add Prescription
                                </>
                            )}
                        </h3>
                        <div className="flex flex-wrap items-center gap-2">
                            {headerExtra}
                            {!editingPendingId && previousPrescriptions.length > 0 && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setShowRefillModal(true)}
                                >
                                    <RefreshCw className="mr-1.5 h-4 w-4" />
                                    Refill
                                </Button>
                            )}
                            {editingPendingId && (
                                <Button type="button" variant="ghost" size="sm" onClick={resetForm}>
                                    <X className="mr-1.5 h-4 w-4" />
                                    Cancel
                                </Button>
                            )}
                        </div>
                    </div>

                    <div className="space-y-4">
                        {/* Drug Selection */}
                        <div className="space-y-2">
                            <Label>Drug *</Label>
                            <Popover open={drugComboOpen} onOpenChange={setDrugComboOpen}>
                                <PopoverTrigger asChild>
                                    <Button
                                        variant="outline"
                                        role="combobox"
                                        aria-expanded={drugComboOpen}
                                        className={cn(
                                            'w-full justify-between',
                                            !selectedDrug && 'text-muted-foreground',
                                        )}
                                    >
                                        {selectedDrug ? (
                                            <span className="flex items-center gap-2 truncate">
                                                <span className="truncate font-medium">
                                                    {extractPackSize(selectedDrug.name).baseName}
                                                </span>
                                                {extractPackSize(selectedDrug.name).packSize && (
                                                    <Badge variant="outline" className="shrink-0 text-xs font-semibold text-blue-600 dark:text-blue-400">
                                                        {extractPackSize(selectedDrug.name).packSize}
                                                    </Badge>
                                                )}
                                                <Badge variant="secondary" className="shrink-0 text-xs">
                                                    {selectedDrug.form}
                                                </Badge>
                                            </span>
                                        ) : 'Select drug...'}
                                        <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent className="w-[500px] p-0" align="start">
                                    <Command
                                        filter={(value, search) => {
                                            const searchLower = search.toLowerCase().trim();
                                            const valueLower = value.toLowerCase();
                                            if (!searchLower) return 1;
                                            if (valueLower.includes(searchLower)) return 1;
                                            const valueWords = valueLower.split(/\s+/);
                                            const startsWithMatch = valueWords.some(word => word.startsWith(searchLower));
                                            if (startsWithMatch) return 0.8;
                                            return 0;
                                        }}
                                    >
                                        <CommandInput placeholder="Search drugs..." />
                                        <CommandList>
                                            <CommandEmpty>No drug found.</CommandEmpty>
                                            <CommandGroup>
                                                {drugs.map((drug) => {
                                                    const { baseName, packSize } = extractPackSize(drug.name);
                                                    return (
                                                        <CommandItem
                                                            key={drug.id}
                                                            value={`${drug.name} ${drug.form} ${drug.strength || ''} ${drug.generic_name || ''}`}
                                                            onSelect={() => {
                                                                setSelectedDrug(drug);
                                                                setDrugComboOpen(false);
                                                            }}
                                                        >
                                                            <Check className={cn('mr-2 h-4 w-4 shrink-0', selectedDrug?.id === drug.id ? 'opacity-100' : 'opacity-0')} />
                                                            <div className="flex min-w-0 flex-1 items-center gap-2">
                                                                <span className="truncate font-medium">{baseName}</span>
                                                                {packSize && (
                                                                    <Badge variant="outline" className="shrink-0 text-xs font-semibold text-blue-600 dark:text-blue-400">
                                                                        {packSize}
                                                                    </Badge>
                                                                )}
                                                                <Badge variant="secondary" className="shrink-0 text-xs">{drug.form}</Badge>
                                                            </div>
                                                        </CommandItem>
                                                    );
                                                })}
                                            </CommandGroup>
                                        </CommandList>
                                    </Command>
                                </PopoverContent>
                            </Popover>
                        </div>

                        {/* Unpriced Drug Warning */}
                        {isSelectedDrugUnpriced && (
                            <Alert className="border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30">
                                <AlertTriangle className="h-4 w-4 text-amber-600 dark:text-amber-400" />
                                <AlertTitle className="text-amber-800 dark:text-amber-200">
                                    Unpriced Drug - External Dispensing
                                </AlertTitle>
                                <AlertDescription className="text-amber-700 dark:text-amber-300">
                                    <p>This drug has no price configured in the system.</p>
                                    <p className="mt-1 flex items-center gap-1">
                                        <ExternalLink className="h-3 w-3" />
                                        Patient will need to purchase this medication externally.
                                    </p>
                                </AlertDescription>
                            </Alert>
                        )}

                        {/* Topical Preparations - Simplified form */}
                        {selectedDrug && isTopicalPreparation(selectedDrug) && (
                            <div className="space-y-4">
                                <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-950/20">
                                    <p className="text-sm text-amber-800 dark:text-amber-200">
                                        <strong>Topical preparation</strong> - Enter quantity and application instructions below.
                                    </p>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="quantity_topical">Quantity *</Label>
                                    <div className="flex items-center gap-2">
                                        <input
                                            type="number"
                                            id="quantity_topical"
                                            min="1"
                                            placeholder="1"
                                            value={formData.quantity_to_dispense}
                                            onChange={(e) => setFormData(prev => ({ ...prev, quantity_to_dispense: e.target.value ? parseInt(e.target.value) : '' }))}
                                            className="flex h-10 w-24 rounded-md border border-input bg-background px-3 py-2 text-sm"
                                            required
                                        />
                                        <span className="text-sm text-gray-600 dark:text-gray-400">
                                            {selectedDrug.unit_type === 'tube' ? 'tube(s)' : 'unit(s)'}
                                        </span>
                                    </div>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="instructions_topical">Application Instructions *</Label>
                                    <Textarea
                                        id="instructions_topical"
                                        placeholder="e.g., Apply to affected area twice daily"
                                        value={formData.instructions}
                                        onChange={(e) => setFormData(prev => ({ ...prev, instructions: e.target.value }))}
                                        rows={3}
                                        required
                                    />
                                </div>
                            </div>
                        )}

                        {/* Non-topical drugs - Dose, Frequency, Duration */}
                        {selectedDrug && !isTopicalPreparation(selectedDrug) && (
                            <div className="grid grid-cols-3 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="dose_quantity">Dose *</Label>
                                    <div className="flex items-center gap-2">
                                        <input
                                            type="text"
                                            id="dose_quantity"
                                            placeholder="1"
                                            value={formData.dose_quantity}
                                            onChange={(e) => setFormData(prev => ({ ...prev, dose_quantity: e.target.value }))}
                                            className="flex h-10 w-16 rounded-md border border-input bg-background px-3 py-2 text-sm"
                                            required
                                        />
                                        <span className="text-xs text-gray-600 dark:text-gray-400">
                                            {selectedDrug.form === 'tablet' || selectedDrug.form === 'capsule'
                                                ? `${selectedDrug.form}(s)`
                                                : selectedDrug.form === 'injection' || selectedDrug.unit_type === 'vial'
                                                    ? 'mg'
                                                    : selectedDrug.unit_type === 'bottle' ? 'ml' : selectedDrug.form}
                                        </span>
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="frequency">Frequency *</Label>
                                    <Select
                                        value={formData.frequency}
                                        onValueChange={(value) => setFormData(prev => ({ ...prev, frequency: value }))}
                                    >
                                        <SelectTrigger id="frequency">
                                            <SelectValue placeholder="Select" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {FREQUENCY_OPTIONS.map(opt => (
                                                <SelectItem key={opt.value} value={opt.value}>{opt.label}</SelectItem>
                                            ))}
                                            {selectedDrug?.form === 'injection' && (
                                                <SelectItem value="At 0, 12, 24 hours">At 0, 12, 24 hours</SelectItem>
                                            )}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="duration">Duration *</Label>
                                    <Select
                                        value={formData.duration}
                                        onValueChange={(value) => setFormData(prev => ({ ...prev, duration: value }))}
                                        disabled={formData.frequency === 'STAT (Immediately)'}
                                    >
                                        <SelectTrigger id="duration">
                                            <SelectValue placeholder="Select" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {DURATION_OPTIONS.map(opt => (
                                                <SelectItem key={opt.value} value={opt.value}>{opt.label}</SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        )}

                        {/* Quantity Display for non-topical */}
                        {formData.quantity_to_dispense && selectedDrug && !isTopicalPreparation(selectedDrug) && (
                            <div className="rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-800 dark:bg-blue-950/20">
                                <p className="text-sm text-blue-800 dark:text-blue-200">
                                    <strong>Quantity to dispense:</strong> {formData.quantity_to_dispense}{' '}
                                    {selectedDrug.unit_type === 'piece' ? (Number(formData.quantity_to_dispense) === 1 ? selectedDrug.form : `${selectedDrug.form}s`) :
                                     selectedDrug.unit_type === 'bottle' ? (Number(formData.quantity_to_dispense) === 1 ? 'bottle' : 'bottles') :
                                     selectedDrug.unit_type === 'vial' ? (Number(formData.quantity_to_dispense) === 1 ? 'vial' : 'vials') :
                                     selectedDrug.unit_type}
                                </p>
                            </div>
                        )}

                        {/* Instructions (optional for non-topical) */}
                        {selectedDrug && !isTopicalPreparation(selectedDrug) && (
                            <div className="space-y-2">
                                <Label htmlFor="instructions">Instructions (Optional)</Label>
                                <Textarea
                                    id="instructions"
                                    placeholder="Special instructions for the patient..."
                                    value={formData.instructions}
                                    onChange={(e) => setFormData(prev => ({ ...prev, instructions: e.target.value }))}
                                    rows={2}
                                />
                            </div>
                        )}

                        {/* Add to List Button */}
                        <Button
                            type="button"
                            onClick={handleAddToPending}
                            disabled={!isFormValid()}
                            className="w-full bg-green-600 hover:bg-green-700 text-white"
                        >
                            <Plus className="mr-2 h-4 w-4" />
                            {editingPendingId ? 'Update in List' : 'Add to List'}
                        </Button>
                    </div>
                </div>
            )}

            {/* Right Column: Pending & Existing Prescriptions */}
            <div className="space-y-4">
                {/* Pending Prescriptions (not yet saved) */}
                {pendingPrescriptions.length > 0 && (
                    <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/20">
                        <div className="mb-3 flex items-center justify-between">
                            <h4 className="flex items-center gap-2 font-semibold text-amber-800 dark:text-amber-200">
                                <AlertTriangle className="h-4 w-4" />
                                Pending ({pendingPrescriptions.length}) - Not yet saved
                            </h4>
                            <Button
                                onClick={handleSaveAll}
                                disabled={isSaving}
                                size="sm"
                                className="bg-green-600 hover:bg-green-700"
                            >
                                <Save className="mr-1.5 h-4 w-4" />
                                {isSaving ? 'Saving...' : 'Save All'}
                            </Button>
                        </div>
                        <div className="space-y-2">
                            {[...pendingPrescriptions]
                                .reverse()
                                .map((prescription) => {
                                const { baseName, packSize } = extractPackSize(prescription.medication_name);
                                return (
                                    <div
                                        key={prescription.id}
                                        className={cn(
                                            'flex items-center justify-between rounded-md border bg-white p-3 dark:bg-gray-900',
                                            editingPendingId === prescription.id && 'ring-2 ring-amber-500'
                                        )}
                                    >
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-2">
                                                <span className="font-medium">{baseName}</span>
                                                {packSize && (
                                                    <Badge variant="outline" className="text-xs">{packSize}</Badge>
                                                )}
                                                <Badge variant="secondary" className="text-xs">{prescription.drug.form}</Badge>
                                            </div>
                                            <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                                {prescription.dose_quantity && (
                                                    <><strong>Dose:</strong> {prescription.dose_quantity} · </>
                                                )}
                                                <strong>Frequency:</strong> {prescription.frequency}
                                                {prescription.duration && (
                                                    <> · <strong>Duration:</strong> {prescription.duration}</>
                                                )}
                                                {prescription.quantity_to_dispense && (
                                                    <> · <strong>Qty:</strong> {prescription.quantity_to_dispense}</>
                                                )}
                                            </p>
                                            {prescription.instructions && (
                                                <p className="mt-1 text-xs text-gray-500 italic">{prescription.instructions}</p>
                                            )}
                                        </div>
                                        <div className="flex items-center gap-1">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => handleEditPending(prescription)}
                                            >
                                                <Pencil className="h-4 w-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => handleRemovePending(prescription.id)}
                                                className="text-red-600 hover:text-red-700"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}

                {/* Existing Prescriptions (already saved) */}
                <div className="rounded-lg border p-4">
                    <h4 className="mb-3 font-semibold text-gray-900 dark:text-gray-100">
                        Saved Prescriptions ({existingPrescriptions.length})
                    </h4>
                    {existingPrescriptions.length === 0 ? (
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            No prescriptions yet. Add prescriptions using the form.
                        </p>
                    ) : (
                        <div className="space-y-2">
                            {[...existingPrescriptions]
                                .sort((a, b) => b.id - a.id)
                                .map((prescription) => {
                                const { baseName, packSize } = extractPackSize(prescription.medication_name);
                                const canModify = prescription.status === 'prescribed' && isEditable;
                                return (
                                    <div
                                        key={prescription.id}
                                        className="flex items-center justify-between rounded-md border bg-gray-50 p-3 dark:bg-gray-800"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-2">
                                                <span className="font-medium">{baseName}</span>
                                                {packSize && (
                                                    <Badge variant="outline" className="text-xs">{packSize}</Badge>
                                                )}
                                                <Badge
                                                    variant={prescription.status === 'prescribed' ? 'default' : 'secondary'}
                                                    className="text-xs"
                                                >
                                                    {prescription.status}
                                                </Badge>
                                            </div>
                                            <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                                {prescription.dose_quantity && (
                                                    <><strong>Dose:</strong> {prescription.dose_quantity} · </>
                                                )}
                                                <strong>Frequency:</strong> {prescription.frequency}
                                                {prescription.duration && (
                                                    <> · <strong>Duration:</strong> {prescription.duration}</>
                                                )}
                                                {prescription.quantity_to_dispense && (
                                                    <> · <strong>Qty:</strong> {prescription.quantity_to_dispense}</>
                                                )}
                                            </p>
                                            {prescription.instructions && (
                                                <p className="mt-1 text-xs text-gray-500 italic">{prescription.instructions}</p>
                                            )}
                                        </div>
                                        {canModify && (
                                            <div className="flex items-center gap-1">
                                                {onEdit && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => onEdit(prescription)}
                                                    >
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                )}
                                                {onDelete && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => onDelete(prescription.id)}
                                                        className="text-red-600 hover:text-red-700"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>

            {/* Refill Modal */}
            {consultationId && previousPrescriptions.length > 0 && (
                <RefillPrescriptionModal
                    open={showRefillModal}
                    onOpenChange={setShowRefillModal}
                    previousPrescriptions={previousPrescriptions}
                    consultationId={consultationId}
                />
            )}
        </div>
    );
}
