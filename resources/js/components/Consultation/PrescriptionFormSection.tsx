import type { ParsedPrescription } from '@/components/Prescription/InterpretationPanel';
import {
    ModeToggle,
    type PrescriptionMode,
} from '@/components/Prescription/ModeToggle';
import { SmartPrescriptionInput } from '@/components/Prescription/SmartPrescriptionInput';
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
import {
    AlertTriangle,
    Check,
    ChevronsUpDown,
    ExternalLink,
    Pencil,
    Pill,
    Plus,
    RefreshCw,
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

// Helper to extract pack size from drug name (e.g., "(24's)", "(6 tabs)", "(12`s)")
function extractPackSize(name: string): {
    baseName: string;
    packSize: string | null;
} {
    const packMatch = name.match(/\s*(\(\d+['`]?s?\)|\(\d+\s*tabs?\))\s*$/i);
    if (packMatch) {
        return {
            baseName: name.slice(0, packMatch.index).trim(),
            packSize: packMatch[1],
        };
    }
    return { baseName: name, packSize: null };
}

interface Prescription {
    id: number;
    medication_name: string;
    frequency: string;
    duration: string;
    dose_quantity?: string;
    quantity_to_dispense?: number;
    instructions?: string;
    status: string;
    drug_id?: number;
    refilled_from_prescription_id?: number;
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
        doctor: {
            id: number;
            name: string;
        };
        patient_checkin: {
            department: {
                id: number;
                name: string;
            };
        };
    };
}

interface Props {
    drugs: Drug[];
    prescriptions: Prescription[];
    prescriptionData: any;
    setPrescriptionData: (field: string, value: any) => void;
    onSubmit: (e: React.FormEvent) => void;
    onDelete: (id: number) => void;
    onEdit: (prescription: Prescription) => void;
    onCancelEdit: () => void;
    onUpdate: (e: React.FormEvent) => void;
    editingPrescription: Prescription | null;
    processing: boolean;
    consultationId: number;
    isEditable?: boolean;
    consultationStatus?: string;
    previousPrescriptions?: PreviousPrescription[];
    headerExtra?: React.ReactNode;
}

// Helper function to parse frequency to get daily count
function parseFrequency(frequency: string): number | null {
    const frequencyMap: { [key: string]: number } = {
        'STAT (Immediately)': 1, // Single dose
        'Once daily': 1,
        'Twice daily (BID)': 2,
        'Three times daily (TID)': 3,
        'Four times daily (QID)': 4,
        'Every 4 hours': 6,
        'Every 6 hours': 4,
        'Every 8 hours': 3,
        'Every 12 hours': 2,
        // Note: 'At 0, 12, 24 hours' is NOT included here because it's a one-time
        // schedule (3 doses total), not a daily recurring pattern like TDS
        'At night (Nocte)': 1, // Once daily at night
        'As needed (PRN)': 4, // PRN: assume max 4 times per day for quantity calculation
    };
    return frequencyMap[frequency] || null;
}

// Helper function to parse duration to get number of days
function parseDuration(duration: string): number | null {
    const match = duration.match(/^(\d+)\s+days?$/i);
    if (match) {
        return parseInt(match[1], 10);
    }
    return null;
}

// Helper function to estimate bottles/vials needed for syrups
function estimateBottlesNeeded(
    frequency: string,
    duration: string,
    unitType: string,
    doseQuantity?: string,
    actualBottleSize?: number,
): number {
    const dailyCount = parseFrequency(frequency);
    const days = parseDuration(duration);

    if (!dailyCount || !days) {
        return 1; // Default to 1 if can't calculate
    }

    // Parse dose quantity (e.g., "5ml", "10ml") or default to 5ml
    const mlPerDose = doseQuantity ? parseFloat(doseQuantity) : 5;
    const totalMlNeeded = dailyCount * days * mlPerDose;

    // Use actual bottle size from drug data, or fallback to defaults
    const bottleSize = actualBottleSize || (unitType === 'bottle' ? 100 : 10);

    // Calculate bottles needed (round up)
    return Math.ceil(totalMlNeeded / bottleSize);
}

// Topical preparations - no frequency/duration calculation needed
const TOPICAL_FORMS = ['cream', 'ointment', 'gel', 'lotion'];

function isTopicalPreparation(drug: Drug | undefined): boolean {
    if (!drug) return false;
    return TOPICAL_FORMS.includes(drug.form.toLowerCase());
}

export default function PrescriptionFormSection({
    drugs,
    prescriptions,
    prescriptionData,
    setPrescriptionData,
    onSubmit,
    onDelete,
    onEdit,
    onCancelEdit,
    onUpdate,
    editingPrescription,
    processing,
    consultationId,
    isEditable,
    consultationStatus,
    previousPrescriptions = [],
    headerExtra,
}: Props) {
    // isEditable takes precedence (used by consultations with 24hr edit window)
    // consultationStatus is fallback for ward rounds (only in_progress is editable)
    const canEdit = isEditable ?? consultationStatus === 'in_progress';

    const [drugComboOpen, setDrugComboOpen] = useState(false);
    const [manuallyEdited, setManuallyEdited] = useState(false);
    // Smart mode is temporarily disabled - always use classic mode
    const [mode, setMode] = useState<PrescriptionMode>('classic');
    const smartModeEnabled = false; // Set to true to re-enable smart mode toggle
    const [smartInput, setSmartInput] = useState('');
    const [parsedResult, setParsedResult] = useState<ParsedPrescription | null>(
        null,
    );
    const [showRefillModal, setShowRefillModal] = useState(false);

    const isEditing = editingPrescription !== null;

    const selectedDrug = drugs.find((d) => d.id === prescriptionData.drug_id);

    // When editing, convert to smart mode with values converted to smart format
    // Only if smart mode is enabled
    useEffect(() => {
        if (isEditing && prescriptionData.frequency && smartModeEnabled) {
            setMode('smart');
            // Generate smart input from existing values
            const parts: string[] = [];
            if (prescriptionData.dose_quantity) {
                parts.push(prescriptionData.dose_quantity);
            }
            if (prescriptionData.frequency) {
                parts.push(frequencyToSmartFormat(prescriptionData.frequency));
            }
            if (prescriptionData.duration) {
                const durationMatch =
                    prescriptionData.duration.match(/^(\d+)\s+days?$/i);
                if (durationMatch) {
                    parts.push(`x ${durationMatch[1]}D`);
                }
            }
            setSmartInput(parts.join(' '));
            setParsedResult(null);
        }
    }, [isEditing, smartModeEnabled]);

    // Helper to convert frequency to smart input format
    const frequencyToSmartFormat = (frequency: string): string => {
        const map: { [key: string]: string } = {
            'STAT (Immediately)': 'STAT',
            'Once daily': 'OD',
            'Twice daily (BID)': 'BD',
            'Three times daily (TID)': 'TDS',
            'Four times daily (QID)': 'QID',
            'Every 4 hours': 'Q4H',
            'Every 6 hours': 'Q6H',
            'Every 8 hours': 'Q8H',
            'Every 12 hours': 'Q12H',
            'At 0, 12, 24 hours': '0-12-24H',
            'At night (Nocte)': 'Nocte',
            'As needed (PRN)': 'PRN',
        };
        return map[frequency] || frequency;
    };

    // Generate smart input string from current form values
    const generateSmartInputFromValues = useCallback((): string => {
        const parts: string[] = [];

        if (prescriptionData.dose_quantity) {
            parts.push(prescriptionData.dose_quantity);
        }

        if (prescriptionData.frequency) {
            parts.push(frequencyToSmartFormat(prescriptionData.frequency));
        }

        if (prescriptionData.duration) {
            // Convert "X days" to "x D" format
            const durationMatch =
                prescriptionData.duration.match(/^(\d+)\s+days?$/i);
            if (durationMatch) {
                parts.push(`x ${durationMatch[1]}D`);
            } else if (prescriptionData.duration !== 'As directed') {
                parts.push(prescriptionData.duration);
            }
        }

        return parts.join(' ');
    }, [
        prescriptionData.dose_quantity,
        prescriptionData.frequency,
        prescriptionData.duration,
    ]);

    // Handle mode switching - preserve values when editing
    const handleModeChange = useCallback(
        (newMode: PrescriptionMode) => {
            if (isEditing) {
                // When editing, preserve values and convert between modes
                if (newMode === 'smart') {
                    // Generate smart input from current classic values
                    const smartText = generateSmartInputFromValues();
                    setSmartInput(smartText);
                }
                // Values are already in prescriptionData, just switch mode
                setMode(newMode);
                setParsedResult(null);
                setManuallyEdited(false);
            } else {
                // Not editing - clear fields when switching (original behavior)
                setMode(newMode);
                setPrescriptionData('dose_quantity', '');
                setPrescriptionData('frequency', '');
                setPrescriptionData('duration', '');
                setPrescriptionData('quantity_to_dispense', '');
                setPrescriptionData('schedule_pattern', null);
                setSmartInput('');
                setParsedResult(null);
                setManuallyEdited(false);
            }
        },
        [isEditing, generateSmartInputFromValues, setPrescriptionData],
    );

    // Handle switching to classic mode from interpretation panel
    const handleSwitchToClassic = useCallback(() => {
        handleModeChange('classic');
    }, [handleModeChange]);

    // Handle parsed result from smart input
    const handleParsedResult = useCallback(
        (result: ParsedPrescription | null) => {
            setParsedResult(result);
            if (result?.isValid) {
                // Update form data with parsed values
                if (result.doseQuantity) {
                    setPrescriptionData('dose_quantity', result.doseQuantity);
                }
                if (result.frequency) {
                    setPrescriptionData('frequency', result.frequency);
                }
                if (result.duration) {
                    setPrescriptionData('duration', result.duration);
                }
                if (result.quantityToDispense !== null) {
                    setPrescriptionData(
                        'quantity_to_dispense',
                        result.quantityToDispense,
                    );
                }
                if (result.schedulePattern) {
                    setPrescriptionData(
                        'schedule_pattern',
                        result.schedulePattern,
                    );
                }
            }
        },
        [setPrescriptionData],
    );

    // Handle smart mode form submission
    const handleSmartSubmit = useCallback(
        (e: React.FormEvent) => {
            e.preventDefault();
            if (!parsedResult?.isValid || !selectedDrug) {
                return;
            }
            // Call the original onSubmit - form data is already populated from handleParsedResult
            onSubmit(e);
            // Clear smart input after successful submission (mode is maintained)
            setSmartInput('');
            setParsedResult(null);
        },
        [parsedResult, selectedDrug, onSubmit],
    );

    // Auto-calculate quantity based on frequency, duration, and dose_quantity (Classic mode only)
    useEffect(() => {
        // Skip auto-calculation in smart mode - it's handled by the parser
        if (mode === 'smart') {
            return;
        }

        if (
            !selectedDrug ||
            !prescriptionData.frequency ||
            !prescriptionData.duration
        ) {
            return;
        }

        // Auto-calculate for piece-based drugs (tablets/capsules)
        if (selectedDrug.unit_type === 'piece') {
            const dailyCount = parseFrequency(prescriptionData.frequency);
            const days = parseDuration(prescriptionData.duration);
            const doseQuantity = prescriptionData.dose_quantity
                ? parseInt(prescriptionData.dose_quantity)
                : 1;

            if (dailyCount && days) {
                const calculatedQuantity = doseQuantity * dailyCount * days;
                setPrescriptionData('quantity_to_dispense', calculatedQuantity);
            }
        }
        // For bottles (syrups): always 1 bottle regardless of dose
        else if (selectedDrug.unit_type === 'bottle') {
            setPrescriptionData('quantity_to_dispense', 1);
        }
        // For vials (injections): set to null, pharmacy will determine quantity at dispensing
        else if (selectedDrug.unit_type === 'vial') {
            setPrescriptionData('quantity_to_dispense', null);
        }
        // For tubes and other types - default to 1 if not manually edited
        else if (!manuallyEdited) {
            if (!prescriptionData.quantity_to_dispense) {
                setPrescriptionData('quantity_to_dispense', 1);
            }
        }
    }, [
        mode,
        prescriptionData.frequency,
        prescriptionData.duration,
        prescriptionData.dose_quantity,
        selectedDrug,
        manuallyEdited,
        setPrescriptionData,
    ]);

    // Reset manual edit flag when drug changes
    useEffect(() => {
        setManuallyEdited(false);
    }, [selectedDrug?.id]);

    // Track previous frequency to detect changes from STAT or 0-12-24H
    const [prevFrequency, setPrevFrequency] = useState<string>('');

    // Auto-set duration when STAT or 0-12-24H is selected, clear when switching away
    useEffect(() => {
        // Frequencies that have fixed duration (no duration selection needed)
        const fixedDurationFrequencies = ['STAT (Immediately)', 'At 0, 12, 24 hours'];

        if (prescriptionData.frequency === 'STAT (Immediately)') {
            setPrescriptionData('duration', 'Single dose');

            // For STAT doses, calculate quantity based on unit type
            const doseQuantity = prescriptionData.dose_quantity
                ? parseFloat(prescriptionData.dose_quantity)
                : 1;

            if (selectedDrug) {
                if (selectedDrug.unit_type === 'bottle') {
                    // For bottles (syrups): always 1 bottle
                    setPrescriptionData('quantity_to_dispense', 1);
                } else if (selectedDrug.unit_type === 'vial') {
                    // For vials (injections): set to null, pharmacy will determine at dispensing
                    setPrescriptionData('quantity_to_dispense', null);
                } else {
                    // For tablets/capsules, quantity = dose quantity
                    setPrescriptionData(
                        'quantity_to_dispense',
                        Math.ceil(doseQuantity),
                    );
                }
            } else {
                // No drug selected yet, default to 1
                setPrescriptionData('quantity_to_dispense', 1);
            }
        } else if (prescriptionData.frequency === 'At 0, 12, 24 hours') {
            // For 0-12-24H: duration is not applicable - the frequency defines the schedule
            setPrescriptionData('duration', 'N/A');

            // Quantity is left to pharmacy for vials
            if (selectedDrug?.unit_type === 'vial') {
                setPrescriptionData('quantity_to_dispense', null);
            }
        } else if (
            fixedDurationFrequencies.includes(prevFrequency) &&
            !fixedDurationFrequencies.includes(prescriptionData.frequency)
        ) {
            // Clear duration when switching away from fixed-duration frequencies
            setPrescriptionData('duration', '');
            setPrescriptionData('quantity_to_dispense', '');
        }

        setPrevFrequency(prescriptionData.frequency);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        prescriptionData.frequency,
        prescriptionData.dose_quantity,
        selectedDrug,
        setPrescriptionData,
    ]);

    // Drug selector component (shared between modes)
    const DrugSelector = (
        <div className="space-y-2 md:col-span-2">
            <Label>Drug *</Label>
            <Popover open={drugComboOpen} onOpenChange={setDrugComboOpen}>
                <PopoverTrigger asChild>
                    <Button
                        variant="outline"
                        role="combobox"
                        aria-expanded={drugComboOpen}
                        className={cn(
                            'w-full justify-between',
                            !prescriptionData.drug_id &&
                            'text-muted-foreground',
                        )}
                    >
                        {selectedDrug
                            ? (() => {
                                const { baseName, packSize } =
                                    extractPackSize(selectedDrug.name);
                                return (
                                    <span className="flex items-center gap-2 truncate">
                                        <span className="truncate font-medium">
                                            {baseName}
                                        </span>
                                        {packSize && (
                                            <Badge
                                                variant="outline"
                                                className="shrink-0 text-xs font-semibold text-blue-600 dark:text-blue-400"
                                            >
                                                {packSize}
                                            </Badge>
                                        )}
                                        <Badge
                                            variant="secondary"
                                            className="shrink-0 text-xs"
                                        >
                                            {selectedDrug.form}
                                        </Badge>
                                    </span>
                                );
                            })()
                            : 'Select drug...'}
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
                            const startsWithMatch = valueWords.some((word) =>
                                word.startsWith(searchLower),
                            );
                            if (startsWithMatch) return 0.8;

                            const searchWords = searchLower
                                .split(/\s+/)
                                .filter(Boolean);
                            if (searchWords.length > 1) {
                                const allWordsMatch = searchWords.every(
                                    (word) => valueLower.includes(word),
                                );
                                if (allWordsMatch) return 0.6;
                            }

                            return 0;
                        }}
                    >
                        <CommandInput placeholder="Search drugs..." />
                        <CommandList>
                            <CommandEmpty>No drug found.</CommandEmpty>
                            <CommandGroup>
                                {drugs.map((drug) => {
                                    const { baseName, packSize } =
                                        extractPackSize(drug.name);
                                    return (
                                        <CommandItem
                                            key={drug.id}
                                            value={`${drug.name} ${drug.form} ${drug.strength || ''} ${drug.generic_name || ''}`}
                                            onSelect={() => {
                                                setPrescriptionData(
                                                    'drug_id',
                                                    drug.id,
                                                );
                                                setPrescriptionData(
                                                    'medication_name',
                                                    drug.name,
                                                );
                                                setDrugComboOpen(false);
                                            }}
                                        >
                                            <Check
                                                className={cn(
                                                    'mr-2 h-4 w-4 shrink-0',
                                                    prescriptionData.drug_id ===
                                                        drug.id
                                                        ? 'opacity-100'
                                                        : 'opacity-0',
                                                )}
                                            />
                                            <div className="flex min-w-0 flex-1 items-center gap-2">
                                                <span className="truncate font-medium">
                                                    {baseName}
                                                </span>
                                                {packSize && (
                                                    <Badge
                                                        variant="outline"
                                                        className="shrink-0 text-xs font-semibold text-blue-600 dark:text-blue-400"
                                                    >
                                                        {packSize}
                                                    </Badge>
                                                )}
                                                <Badge
                                                    variant="secondary"
                                                    className="shrink-0 text-xs"
                                                >
                                                    {drug.form}
                                                </Badge>
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
    );

    // Helper to check if selected drug is unpriced
    const isSelectedDrugUnpriced =
        selectedDrug &&
        (selectedDrug.unit_price === null || selectedDrug.unit_price === 0);

    // Unpriced drug warning component (shared between modes)
    const UnpricedDrugWarning = isSelectedDrugUnpriced ? (
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
    ) : null;

    return (
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {/* Left Column: Add New Prescription Form */}
            {canEdit && (
                <div className="rounded-lg border border-green-200 bg-gradient-to-br from-green-50 to-emerald-50 p-6 dark:border-green-800 dark:from-green-950/20 dark:to-emerald-950/20">
                    <div className="mb-4 flex items-center justify-between">
                        <h3 className="flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {isEditing ? (
                                <>
                                    <Pencil className="h-5 w-5" />
                                    Edit Prescription
                                </>
                            ) : (
                                <>
                                    <Plus className="h-5 w-5" />
                                    Add New Prescription
                                </>
                            )}
                        </h3>
                        <div className="flex flex-wrap items-center gap-2">
                            {headerExtra}
                            {!isEditing && previousPrescriptions.length > 0 && (
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
                            {isEditing ? (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={onCancelEdit}
                                >
                                    <X className="mr-1.5 h-4 w-4" />
                                    Cancel
                                </Button>
                            ) : (
                                smartModeEnabled && (
                                    <ModeToggle
                                        mode={mode}
                                        onChange={handleModeChange}
                                    />
                                )
                            )}
                        </div>
                    </div>

                    {mode === 'smart' ? (
                        /* Smart Mode Form */
                        <form
                            onSubmit={handleSmartSubmit}
                            className="space-y-4"
                        >
                            {/* Drug Selection */}
                            <div className="grid grid-cols-1 gap-4">
                                {DrugSelector}
                            </div>

                            {/* Unpriced Drug Warning */}
                            {UnpricedDrugWarning}

                            {/* Smart Prescription Input */}
                            <SmartPrescriptionInput
                                drug={selectedDrug || null}
                                value={smartInput}
                                onChange={setSmartInput}
                                onParsedResult={handleParsedResult}
                                onSwitchToClassic={handleSwitchToClassic}
                                disabled={processing}
                            />

                            {/* Manual Quantity Input for liquids without bottle_size in Smart mode */}
                            {parsedResult?.isValid &&
                                (parsedResult.quantityToDispense === null ||
                                    parsedResult.quantityToDispense === 0) &&
                                selectedDrug &&
                                (selectedDrug.unit_type === 'bottle' ||
                                    selectedDrug.unit_type === 'vial') && (
                                    <div className="space-y-2">
                                        <Label htmlFor="quantity_bottles_smart">
                                            Number of{' '}
                                            {selectedDrug.unit_type === 'bottle'
                                                ? 'Bottles'
                                                : 'Vials'}{' '}
                                            *
                                        </Label>
                                        <div className="flex items-center gap-2">
                                            <input
                                                type="number"
                                                id="quantity_bottles_smart"
                                                min="1"
                                                placeholder="1"
                                                value={
                                                    prescriptionData.quantity_to_dispense ||
                                                    ''
                                                }
                                                onChange={(e) => {
                                                    setPrescriptionData(
                                                        'quantity_to_dispense',
                                                        e.target.value
                                                            ? parseInt(
                                                                e.target
                                                                    .value,
                                                            )
                                                            : '',
                                                    );
                                                }}
                                                className="flex h-10 w-24 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                                required
                                            />
                                            <span className="text-sm text-gray-600 dark:text-gray-400">
                                                {selectedDrug.unit_type ===
                                                    'bottle'
                                                    ? (prescriptionData.quantity_to_dispense ||
                                                        1) === 1
                                                        ? 'bottle'
                                                        : 'bottles'
                                                    : (prescriptionData.quantity_to_dispense ||
                                                        1) === 1
                                                        ? 'vial'
                                                        : 'vials'}
                                            </span>
                                        </div>
                                        <p className="text-xs text-amber-600 dark:text-amber-400">
                                            Bottle size not configured. Enter
                                            the number of{' '}
                                            {selectedDrug.unit_type === 'bottle'
                                                ? 'bottles'
                                                : 'vials'}{' '}
                                            to dispense.
                                        </p>
                                    </div>
                                )}

                            {/* Instructions */}
                            <div className="space-y-2">
                                <Label htmlFor="instructions-smart">
                                    Instructions (Optional)
                                </Label>
                                <Textarea
                                    id="instructions-smart"
                                    placeholder="Special instructions for the patient..."
                                    value={prescriptionData.instructions}
                                    onChange={(e) =>
                                        setPrescriptionData(
                                            'instructions',
                                            e.target.value,
                                        )
                                    }
                                    rows={3}
                                />
                            </div>

                            <Button
                                type="submit"
                                disabled={
                                    processing ||
                                    !prescriptionData.drug_id ||
                                    !parsedResult?.isValid ||
                                    ((parsedResult.quantityToDispense ===
                                        null ||
                                        parsedResult.quantityToDispense ===
                                        0) &&
                                        !prescriptionData.quantity_to_dispense)
                                }
                                className="w-full"
                            >
                                <Plus className="mr-2 h-4 w-4" />
                                {processing ? 'Adding...' : 'Add Prescription'}
                            </Button>
                        </form>
                    ) : (
                        /* Classic Mode Form */
                        <form
                            onSubmit={isEditing ? onUpdate : onSubmit}
                            className="space-y-4"
                        >
                            {/* Drug Selection - Full width */}
                            <div className="grid grid-cols-1 gap-4">
                                {DrugSelector}
                            </div>

                            {/* Unpriced Drug Warning */}
                            {UnpricedDrugWarning}

                            {/* Topical Preparations - Simplified form */}
                            {selectedDrug &&
                                isTopicalPreparation(selectedDrug) && (
                                    <div className="space-y-4">
                                        <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-950/20">
                                            <p className="text-sm text-amber-800 dark:text-amber-200">
                                                <strong>
                                                    Topical preparation
                                                </strong>{' '}
                                                - Enter quantity and application
                                                instructions below.
                                            </p>
                                        </div>

                                        {/* Quantity Input */}
                                        <div className="space-y-2">
                                            <Label htmlFor="quantity_topical">
                                                Quantity *
                                            </Label>
                                            <div className="flex items-center gap-2">
                                                <input
                                                    type="number"
                                                    id="quantity_topical"
                                                    min="1"
                                                    placeholder="1"
                                                    value={
                                                        prescriptionData.quantity_to_dispense ||
                                                        ''
                                                    }
                                                    onChange={(e) => {
                                                        setPrescriptionData(
                                                            'quantity_to_dispense',
                                                            e.target.value
                                                                ? parseInt(
                                                                    e.target
                                                                        .value,
                                                                )
                                                                : '',
                                                        );
                                                    }}
                                                    className="flex h-10 w-24 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                                    required
                                                />
                                                <span className="text-sm text-gray-600 dark:text-gray-400">
                                                    {selectedDrug.unit_type ===
                                                        'tube'
                                                        ? (prescriptionData.quantity_to_dispense ||
                                                            1) === 1
                                                            ? 'tube'
                                                            : 'tubes'
                                                        : selectedDrug.unit_type ===
                                                            'piece'
                                                            ? (prescriptionData.quantity_to_dispense ||
                                                                1) === 1
                                                                ? 'unit'
                                                                : 'units'
                                                            : selectedDrug.unit_type}
                                                </span>
                                            </div>
                                        </div>

                                        {/* Instructions - Required for topicals */}
                                        <div className="space-y-2">
                                            <Label htmlFor="instructions_topical">
                                                Application Instructions *
                                            </Label>
                                            <Textarea
                                                id="instructions_topical"
                                                placeholder="e.g., Apply to affected area twice daily"
                                                value={
                                                    prescriptionData.instructions
                                                }
                                                onChange={(e) =>
                                                    setPrescriptionData(
                                                        'instructions',
                                                        e.target.value,
                                                    )
                                                }
                                                rows={3}
                                                required
                                            />
                                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                                Include application site,
                                                frequency, and any special
                                                instructions
                                            </p>
                                        </div>
                                    </div>
                                )}

                            {/* Non-topical drugs - Dose, Frequency, Duration in same row */}
                            {selectedDrug &&
                                !isTopicalPreparation(selectedDrug) && (
                                    <div className="grid grid-cols-3 gap-4">
                                        {/* Dose Quantity */}
                                        <div className="space-y-2">
                                            <Label htmlFor="dose_quantity">
                                                Dose *
                                            </Label>
                                            <div className="flex items-center gap-2">
                                                <input
                                                    type="text"
                                                    id="dose_quantity"
                                                    placeholder="1"
                                                    value={
                                                        prescriptionData.dose_quantity ||
                                                        ''
                                                    }
                                                    onChange={(e) =>
                                                        setPrescriptionData(
                                                            'dose_quantity',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className="flex h-10 w-16 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                                    required
                                                />
                                                <span className="text-xs text-gray-600 dark:text-gray-400">
                                                    {selectedDrug.form ===
                                                        'tablet' ||
                                                        selectedDrug.form ===
                                                        'capsule'
                                                        ? `${selectedDrug.form}(s)`
                                                        : selectedDrug.form ===
                                                            'injection' ||
                                                            selectedDrug.unit_type ===
                                                            'vial'
                                                            ? 'mg'
                                                            : selectedDrug.unit_type ===
                                                                'bottle' ||
                                                                selectedDrug.form ===
                                                                'syrup' ||
                                                                selectedDrug.form ===
                                                                'suspension'
                                                                ? 'ml'
                                                                : selectedDrug.form}
                                                </span>
                                            </div>
                                        </div>

                                        {/* Frequency */}
                                        <div className="space-y-2">
                                            <Label htmlFor="frequency">
                                                Frequency *
                                            </Label>
                                            <Select
                                                value={
                                                    prescriptionData.frequency
                                                }
                                                onValueChange={(value) =>
                                                    setPrescriptionData(
                                                        'frequency',
                                                        value,
                                                    )
                                                }
                                                required
                                            >
                                                <SelectTrigger id="frequency">
                                                    <SelectValue placeholder="Select" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="STAT (Immediately)">
                                                        STAT (Immediately)
                                                    </SelectItem>
                                                    <SelectItem value="Once daily">
                                                        Once daily
                                                    </SelectItem>
                                                    <SelectItem value="Twice daily (BID)">
                                                        Twice daily (BID)
                                                    </SelectItem>
                                                    <SelectItem value="Three times daily (TID)">
                                                        Three times daily (TID)
                                                    </SelectItem>
                                                    <SelectItem value="Four times daily (QID)">
                                                        Four times daily (QID)
                                                    </SelectItem>
                                                    <SelectItem value="Every 4 hours">
                                                        Every 4 hours
                                                    </SelectItem>
                                                    <SelectItem value="Every 6 hours">
                                                        Every 6 hours
                                                    </SelectItem>
                                                    <SelectItem value="Every 8 hours">
                                                        Every 8 hours
                                                    </SelectItem>
                                                    <SelectItem value="Every 12 hours">
                                                        Every 12 hours
                                                    </SelectItem>
                                                    {/* Injectable-only: 0-12-24H for malaria drugs */}
                                                    {selectedDrug?.form === 'injection' && (
                                                        <SelectItem value="At 0, 12, 24 hours">
                                                            At 0, 12, 24 hours
                                                        </SelectItem>
                                                    )}
                                                    <SelectItem value="At night (Nocte)">
                                                        At night (Nocte)
                                                    </SelectItem>
                                                    <SelectItem value="As needed (PRN)">
                                                        As needed (PRN)
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        {/* Duration - disabled for STAT */}
                                        <div className="space-y-2">
                                            <Label htmlFor="duration">
                                                Duration *
                                            </Label>
                                            <Select
                                                value={
                                                    prescriptionData.duration
                                                }
                                                onValueChange={(value) =>
                                                    setPrescriptionData(
                                                        'duration',
                                                        value,
                                                    )
                                                }
                                                required
                                                disabled={
                                                    prescriptionData.frequency ===
                                                    'STAT (Immediately)' ||
                                                    prescriptionData.frequency ===
                                                    'At 0, 12, 24 hours'
                                                }
                                            >
                                                <SelectTrigger id="duration">
                                                    <SelectValue placeholder="Select" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {/* N/A only shows when 0-12-24H is selected since schedule is self-contained */}
                                                    {prescriptionData.frequency === 'At 0, 12, 24 hours' && (
                                                        <SelectItem value="N/A">
                                                            N/A
                                                        </SelectItem>
                                                    )}
                                                    <SelectItem value="Single dose">
                                                        Single dose
                                                    </SelectItem>
                                                    <SelectItem value="1 day">
                                                        1 day
                                                    </SelectItem>
                                                    <SelectItem value="2 days">
                                                        2 days
                                                    </SelectItem>
                                                    <SelectItem value="3 days">
                                                        3 days
                                                    </SelectItem>
                                                    <SelectItem value="4 days">
                                                        4 days
                                                    </SelectItem>
                                                    <SelectItem value="5 days">
                                                        5 days
                                                    </SelectItem>
                                                    <SelectItem value="6 days">
                                                        6 days
                                                    </SelectItem>
                                                    <SelectItem value="7 days">
                                                        7 days
                                                    </SelectItem>
                                                    <SelectItem value="10 days">
                                                        10 days
                                                    </SelectItem>
                                                    <SelectItem value="14 days">
                                                        14 days
                                                    </SelectItem>
                                                    <SelectItem value="21 days">
                                                        21 days
                                                    </SelectItem>
                                                    <SelectItem value="28 days">
                                                        28 days
                                                    </SelectItem>
                                                    <SelectItem value="30 days">
                                                        30 days
                                                    </SelectItem>
                                                    <SelectItem value="42 days">
                                                        42 days
                                                    </SelectItem>
                                                    <SelectItem value="60 days">
                                                        60 days
                                                    </SelectItem>
                                                    <SelectItem value="90 days">
                                                        90 days
                                                    </SelectItem>
                                                    <SelectItem value="120 days">
                                                        120 days
                                                    </SelectItem>
                                                    <SelectItem value="180 days">
                                                        180 days
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>
                                )}

                            {/* Quantity Display - Auto-calculated for tablets (non-topical only) */}
                            {prescriptionData.quantity_to_dispense &&
                                selectedDrug &&
                                !isTopicalPreparation(selectedDrug) &&
                                selectedDrug.unit_type === 'piece' && (
                                    <div className="rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-800 dark:bg-blue-950/20">
                                        <p className="text-sm text-blue-800 dark:text-blue-200">
                                            <strong>Quantity to dispense:</strong>{' '}
                                            {prescriptionData.quantity_to_dispense}{' '}
                                            {selectedDrug.form === 'tablet'
                                                ? 'tablets'
                                                : selectedDrug.form === 'capsule'
                                                    ? 'capsules'
                                                    : 'pieces'}
                                            <span className="ml-2 text-xs text-blue-600 dark:text-blue-400">
                                                (auto-calculated)
                                            </span>
                                        </p>
                                    </div>
                                )}

                            {/* Calculated Quantity Display for bottles WITH bottle_size configured */}
                            {/* Note: Vials are excluded since quantity is entered at dispensing */}
                            {prescriptionData.quantity_to_dispense &&
                                selectedDrug &&
                                !isTopicalPreparation(selectedDrug) &&
                                selectedDrug.unit_type === 'bottle' &&
                                selectedDrug.bottle_size && (
                                    <div className="rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-800 dark:bg-blue-950/20">
                                        <p className="text-sm text-blue-800 dark:text-blue-200">
                                            <strong>Quantity to dispense:</strong>{' '}
                                            {prescriptionData.quantity_to_dispense}{' '}
                                            {prescriptionData.quantity_to_dispense === 1
                                                ? 'bottle'
                                                : 'bottles'}
                                            <span className="ml-2 text-xs text-blue-600 dark:text-blue-400">
                                                (auto-calculated)
                                            </span>
                                        </p>
                                    </div>
                                )}

                            {/* Manual Quantity Input for bottles WITHOUT bottle_size configured */}
                            {/* Note: Vials are excluded since quantity is entered at dispensing */}
                            {selectedDrug &&
                                !isTopicalPreparation(selectedDrug) &&
                                selectedDrug.unit_type === 'bottle' &&
                                !selectedDrug.bottle_size &&
                                prescriptionData.frequency &&
                                prescriptionData.duration && (
                                    <div className="space-y-2">
                                        <Label htmlFor="quantity_bottles">
                                            Number of{' '}
                                            {selectedDrug.unit_type === 'bottle'
                                                ? 'Bottles'
                                                : 'Vials'}{' '}
                                            *
                                        </Label>
                                        <div className="flex items-center gap-2">
                                            <input
                                                type="number"
                                                id="quantity_bottles"
                                                min="1"
                                                placeholder="1"
                                                value={
                                                    prescriptionData.quantity_to_dispense ||
                                                    ''
                                                }
                                                onChange={(e) => {
                                                    setManuallyEdited(true);
                                                    setPrescriptionData(
                                                        'quantity_to_dispense',
                                                        e.target.value
                                                            ? parseInt(
                                                                e.target
                                                                    .value,
                                                            )
                                                            : '',
                                                    );
                                                }}
                                                className="flex h-10 w-24 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                                required
                                            />
                                            <span className="text-sm text-gray-600 dark:text-gray-400">
                                                {selectedDrug.unit_type ===
                                                    'bottle'
                                                    ? (prescriptionData.quantity_to_dispense ||
                                                        1) === 1
                                                        ? 'bottle'
                                                        : 'bottles'
                                                    : (prescriptionData.quantity_to_dispense ||
                                                        1) === 1
                                                        ? 'vial'
                                                        : 'vials'}
                                            </span>
                                        </div>
                                        <p className="text-xs text-amber-600 dark:text-amber-400">
                                            Bottle size not configured for this
                                            drug. Enter the number of{' '}
                                            {selectedDrug.unit_type === 'bottle'
                                                ? 'bottles'
                                                : 'vials'}{' '}
                                            to dispense.
                                        </p>
                                    </div>
                                )}

                            {/* Manual Quantity Input for tubes and other types (non-topical) */}
                            {selectedDrug &&
                                !isTopicalPreparation(selectedDrug) &&
                                selectedDrug.unit_type !== 'piece' &&
                                selectedDrug.unit_type !== 'bottle' &&
                                selectedDrug.unit_type !== 'vial' &&
                                prescriptionData.frequency &&
                                prescriptionData.duration && (
                                    <div className="space-y-2">
                                        <Label htmlFor="quantity_to_dispense">
                                            Quantity to Dispense *
                                        </Label>
                                        <div className="flex items-center gap-2">
                                            <input
                                                type="number"
                                                id="quantity_to_dispense"
                                                min="1"
                                                placeholder="1"
                                                value={
                                                    prescriptionData.quantity_to_dispense ||
                                                    ''
                                                }
                                                onChange={(e) => {
                                                    setManuallyEdited(true);
                                                    setPrescriptionData(
                                                        'quantity_to_dispense',
                                                        e.target.value
                                                            ? parseInt(
                                                                e.target
                                                                    .value,
                                                            )
                                                            : '',
                                                    );
                                                }}
                                                className="flex h-10 w-24 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                                required
                                            />
                                            <span className="text-sm text-gray-600 dark:text-gray-400">
                                                {selectedDrug.unit_type ===
                                                    'tube'
                                                    ? (prescriptionData.quantity_to_dispense ||
                                                        1) === 1
                                                        ? 'tube'
                                                        : 'tubes'
                                                    : selectedDrug.unit_type}
                                            </span>
                                        </div>
                                        <p className="text-xs text-gray-500 dark:text-gray-400">
                                            Enter the number of{' '}
                                            {selectedDrug.unit_type === 'tube'
                                                ? 'tubes'
                                                : 'units'}{' '}
                                            needed for this prescription
                                        </p>
                                    </div>
                                )}

                            {/* Instructions (for non-topical drugs - topicals have their own instructions field) */}
                            {(!selectedDrug ||
                                !isTopicalPreparation(selectedDrug)) && (
                                    <div className="space-y-2">
                                        <Label htmlFor="instructions">
                                            Instructions (Optional)
                                        </Label>
                                        <Textarea
                                            id="instructions"
                                            placeholder="Special instructions for the patient..."
                                            value={prescriptionData.instructions}
                                            onChange={(e) =>
                                                setPrescriptionData(
                                                    'instructions',
                                                    e.target.value,
                                                )
                                            }
                                            rows={3}
                                        />
                                    </div>
                                )}

                            <Button
                                type="submit"
                                disabled={
                                    processing || !prescriptionData.drug_id
                                }
                                className="w-full"
                            >
                                {isEditing ? (
                                    <>
                                        <Pencil className="mr-2 h-4 w-4" />
                                        {processing
                                            ? 'Updating...'
                                            : 'Update Prescription'}
                                    </>
                                ) : (
                                    <>
                                        <Plus className="mr-2 h-4 w-4" />
                                        {processing
                                            ? 'Adding...'
                                            : 'Add Prescription'}
                                    </>
                                )}
                            </Button>
                        </form>
                    )}
                </div>
            )}

            {/* Refill Modal */}
            <RefillPrescriptionModal
                open={showRefillModal}
                onOpenChange={setShowRefillModal}
                consultationId={consultationId}
                previousPrescriptions={previousPrescriptions}
            />

            {/* Right Column: Current Prescriptions */}
            <div className={cn('space-y-4', !canEdit && 'lg:col-span-2')}>
                <h3 className="flex items-center gap-2 text-lg font-semibold">
                    <Pill className="h-5 w-5" />
                    Current Prescriptions
                    <Badge variant="secondary" className="ml-auto">
                        {prescriptions.length}
                    </Badge>
                </h3>

                {prescriptions.length > 0 ? (
                    <div className="space-y-3">
                        {[...prescriptions]
                            .sort((a, b) => b.id - a.id)
                            .map((prescription) => (
                                <div
                                    key={prescription.id}
                                    className="flex items-center justify-between rounded-lg border bg-gray-50 p-3 dark:bg-gray-800"
                                >
                                    <div className="min-w-0 flex-1">
                                        <h4 className="font-semibold text-gray-900 dark:text-gray-100">
                                            {prescription.medication_name}
                                        </h4>
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
                                            <p className="mt-1 text-xs text-gray-500 italic">
                                                {prescription.instructions}
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex shrink-0 items-center gap-2">
                                        {prescription.refilled_from_prescription_id && (
                                            <Badge
                                                variant="outline"
                                                className="text-xs"
                                            >
                                                <RefreshCw className="mr-1 h-3 w-3" />
                                                Refill
                                            </Badge>
                                        )}
                                        <Badge
                                            variant={
                                                prescription.status ===
                                                    'prescribed'
                                                    ? 'default'
                                                    : prescription.status ===
                                                        'dispensed'
                                                        ? 'outline'
                                                        : 'destructive'
                                            }
                                        >
                                            {prescription.status.toUpperCase()}
                                        </Badge>
                                        {canEdit &&
                                            prescription.status ===
                                            'prescribed' && (
                                                <>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() =>
                                                            onEdit(
                                                                prescription,
                                                            )
                                                        }
                                                        className="h-8 w-8 text-blue-600 hover:bg-blue-50 hover:text-blue-700"
                                                        title="Edit prescription"
                                                    >
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() =>
                                                            onDelete(
                                                                prescription.id,
                                                            )
                                                        }
                                                        className="h-8 w-8 text-red-600 hover:bg-red-50 hover:text-red-700"
                                                        title="Delete prescription"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </>
                                            )}
                                    </div>
                                </div>
                            ))}
                    </div>
                ) : (
                    <div className="py-12 text-center text-gray-500">
                        <Pill className="mx-auto mb-4 h-12 w-12 text-gray-300" />
                        <p>No prescriptions recorded</p>
                    </div>
                )}
            </div>
        </div>
    );
}
