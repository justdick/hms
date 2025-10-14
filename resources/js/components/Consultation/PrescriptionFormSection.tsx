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
import { Check, ChevronsUpDown, Pill, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface Drug {
    id: number;
    name: string;
    form: string;
    strength?: string;
    generic_name?: string;
    brand_name?: string;
    unit_type: string;
}

interface Prescription {
    id: number;
    medication_name: string;
    dosage: string;
    frequency: string;
    duration: string;
    instructions?: string;
    status: string;
}

interface Props {
    drugs: Drug[];
    prescriptions: Prescription[];
    prescriptionData: any;
    setPrescriptionData: (field: string, value: any) => void;
    onSubmit: (e: React.FormEvent) => void;
    onDelete: (id: number) => void;
    processing: boolean;
    consultationStatus: string;
}

export default function PrescriptionFormSection({
    drugs,
    prescriptions,
    prescriptionData,
    setPrescriptionData,
    onSubmit,
    onDelete,
    processing,
    consultationStatus,
}: Props) {
    const [drugComboOpen, setDrugComboOpen] = useState(false);

    const selectedDrug = drugs.find((d) => d.id === prescriptionData.drug_id);

    return (
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {/* Left Column: Add New Prescription Form */}
            {consultationStatus === 'in_progress' && (
                <div className="rounded-lg border border-green-200 bg-gradient-to-br from-green-50 to-emerald-50 p-6 dark:border-green-800 dark:from-green-950/20 dark:to-emerald-950/20">
                    <h3 className="mb-4 flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                        <Plus className="h-5 w-5" />
                        Add New Prescription
                    </h3>
                    <form onSubmit={onSubmit} className="space-y-4">
                        {/* Drug Selection with Combobox */}
                        <div className="space-y-2">
                            <Label>Drug *</Label>
                            <Popover
                                open={drugComboOpen}
                                onOpenChange={setDrugComboOpen}
                            >
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
                                        {selectedDrug ? (
                                            <span className="flex items-center gap-2 truncate">
                                                <span className="font-medium">
                                                    {selectedDrug.name}
                                                </span>
                                                {selectedDrug.strength && (
                                                    <span className="text-sm text-gray-600 dark:text-gray-400">
                                                        {selectedDrug.strength}
                                                    </span>
                                                )}
                                                <Badge
                                                    variant="secondary"
                                                    className="text-xs"
                                                >
                                                    {selectedDrug.form}
                                                </Badge>
                                            </span>
                                        ) : (
                                            'Select drug...'
                                        )}
                                        <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent
                                    className="w-[500px] p-0"
                                    align="start"
                                >
                                    <Command>
                                        <CommandInput placeholder="Search drugs..." />
                                        <CommandList>
                                            <CommandEmpty>
                                                No drug found.
                                            </CommandEmpty>
                                            <CommandGroup>
                                                {drugs.map((drug) => (
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
                                                            setDrugComboOpen(
                                                                false,
                                                            );
                                                        }}
                                                    >
                                                        <Check
                                                            className={cn(
                                                                'mr-2 h-4 w-4',
                                                                prescriptionData.drug_id ===
                                                                    drug.id
                                                                    ? 'opacity-100'
                                                                    : 'opacity-0',
                                                            )}
                                                        />
                                                        <div className="flex min-w-0 flex-1 items-center gap-2">
                                                            <span className="truncate font-medium">
                                                                {drug.name}
                                                            </span>
                                                            {drug.strength && (
                                                                <span className="shrink-0 text-sm text-gray-600 dark:text-gray-400">
                                                                    {
                                                                        drug.strength
                                                                    }
                                                                </span>
                                                            )}
                                                            <Badge
                                                                variant="secondary"
                                                                className="shrink-0 text-xs"
                                                            >
                                                                {drug.form}
                                                            </Badge>
                                                        </div>
                                                    </CommandItem>
                                                ))}
                                            </CommandGroup>
                                        </CommandList>
                                    </Command>
                                </PopoverContent>
                            </Popover>
                        </div>

                        {/* Frequency and Duration in two columns */}
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="frequency">Frequency *</Label>
                                <Select
                                    value={prescriptionData.frequency}
                                    onValueChange={(value) =>
                                        setPrescriptionData('frequency', value)
                                    }
                                    required
                                >
                                    <SelectTrigger id="frequency">
                                        <SelectValue placeholder="Select frequency" />
                                    </SelectTrigger>
                                    <SelectContent>
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
                                        <SelectItem value="As needed (PRN)">
                                            As needed (PRN)
                                        </SelectItem>
                                        <SelectItem value="Before meals">
                                            Before meals
                                        </SelectItem>
                                        <SelectItem value="After meals">
                                            After meals
                                        </SelectItem>
                                        <SelectItem value="At bedtime">
                                            At bedtime
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="duration">Duration *</Label>
                                <Select
                                    value={prescriptionData.duration}
                                    onValueChange={(value) =>
                                        setPrescriptionData('duration', value)
                                    }
                                    required
                                >
                                    <SelectTrigger id="duration">
                                        <SelectValue placeholder="Select duration" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="3 days">
                                            3 days
                                        </SelectItem>
                                        <SelectItem value="5 days">
                                            5 days
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
                                        <SelectItem value="30 days">
                                            30 days
                                        </SelectItem>
                                        <SelectItem value="60 days">
                                            60 days
                                        </SelectItem>
                                        <SelectItem value="90 days">
                                            90 days
                                        </SelectItem>
                                        <SelectItem value="Until review">
                                            Until review
                                        </SelectItem>
                                        <SelectItem value="Ongoing">
                                            Ongoing
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        {/* Instructions */}
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

                        <Button
                            type="submit"
                            disabled={processing || !prescriptionData.drug_id}
                            className="w-full"
                        >
                            <Plus className="mr-2 h-4 w-4" />
                            {processing ? 'Adding...' : 'Add Prescription'}
                        </Button>
                    </form>
                </div>
            )}

            {/* Right Column: Current Prescriptions */}
            <div
                className={cn(
                    'space-y-4',
                    consultationStatus !== 'in_progress' && 'lg:col-span-2',
                )}
            >
                <h3 className="flex items-center gap-2 text-lg font-semibold">
                    <Pill className="h-5 w-5" />
                    Current Prescriptions
                    <Badge variant="secondary" className="ml-auto">
                        {prescriptions.length}
                    </Badge>
                </h3>

                {prescriptions.length > 0 ? (
                    <div className="space-y-3">
                        {prescriptions.map((prescription) => (
                            <div
                                key={prescription.id}
                                className="rounded-lg border bg-gray-50 p-4 dark:bg-gray-800"
                            >
                                <div className="mb-2 flex items-start justify-between">
                                    <div className="flex-1">
                                        <h4 className="font-semibold text-gray-900 dark:text-gray-100">
                                            {prescription.medication_name}
                                        </h4>
                                        <div className="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-400">
                                            <p>
                                                <strong>Frequency:</strong>{' '}
                                                {prescription.frequency}
                                            </p>
                                            <p>
                                                <strong>Duration:</strong>{' '}
                                                {prescription.duration}
                                            </p>
                                        </div>
                                        {prescription.instructions && (
                                            <div className="mt-2 rounded bg-blue-50 p-2 text-xs dark:bg-blue-900/30">
                                                <strong>Instructions:</strong>{' '}
                                                {prescription.instructions}
                                            </div>
                                        )}
                                    </div>
                                    <div className="flex items-center gap-2">
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
                                        {consultationStatus === 'in_progress' &&
                                            prescription.status ===
                                                'prescribed' && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        onDelete(
                                                            prescription.id,
                                                        )
                                                    }
                                                    className="text-red-600 hover:bg-red-50 hover:text-red-700"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            )}
                                    </div>
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
