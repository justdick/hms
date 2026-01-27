import AsyncDiagnosisSelect from '@/components/Consultation/AsyncDiagnosisSelect';
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
import { Form } from '@inertiajs/react';
import {
    Activity,
    Check,
    ChevronsUpDown,
    FileText,
    Pill,
    Plus,
    Trash2,
    User,
} from 'lucide-react';
import { useState } from 'react';

interface Patient {
    id: number;
    patient_number: string;
    first_name: string;
    last_name: string;
    date_of_birth: string;
    phone_number: string | null;
}

interface VitalSign {
    id: number;
    temperature: number | null;
    blood_pressure_systolic: number | null;
    blood_pressure_diastolic: number | null;
    pulse_rate: number | null;
    respiratory_rate: number | null;
    weight: number | null;
    height: number | null;
    bmi: number | null;
    recorded_at: string;
}

interface Department {
    id: number;
    name: string;
}

interface PatientCheckin {
    id: number;
    patient: Patient;
    department: Department;
    status: string;
    checked_in_at: string;
    vitals_taken_at: string | null;
    vital_signs: VitalSign[];
}

interface ProcedureType {
    id: number;
    name: string;
    code: string;
    category: string;
    description: string | null;
    price: number;
    is_active: boolean;
}

interface Drug {
    id: number;
    name: string;
    generic_name: string | null;
    brand_name: string | null;
    drug_code: string;
    form: string;
    strength: string | null;
    unit_price: number;
    unit_type: string;
}

interface Diagnosis {
    id: number;
    diagnosis: string;
    code: string | null;
    g_drg: string | null;
    icd_10: string | null;
}

interface Supply {
    drug_id: number;
    quantity: number;
    drug: Drug;
}

interface SelectedDiagnosis {
    id: number;
    name: string;
    icd_code: string;
}

interface Props {
    open: boolean;
    onClose: () => void;
    patientCheckin: PatientCheckin;
    procedureTypes: ProcedureType[];
    availableDrugs: Drug[];
    onSuccess: () => void;
}

export default function ProcedureForm({
    open,
    onClose,
    patientCheckin,
    procedureTypes,
    availableDrugs,
    onSuccess,
}: Props) {
    const [procedureTypeOpen, setProcedureTypeOpen] = useState(false);
    const [selectedProcedureType, setSelectedProcedureType] = useState<
        number | null
    >(null);

    const [selectedDiagnosis, setSelectedDiagnosis] = useState<number | null>(
        null,
    );
    const [selectedDiagnoses, setSelectedDiagnoses] = useState<
        SelectedDiagnosis[]
    >([]);

    const [supplyOpen, setSupplyOpen] = useState(false);
    const [selectedSupply, setSelectedSupply] = useState<number | null>(null);
    const [supplies, setSupplies] = useState<Supply[]>([]);
    const [supplyQuantity, setSupplyQuantity] = useState<string>('1');

    const calculateAge = (dateOfBirth: string) => {
        const today = new Date();
        const birthDate = new Date(dateOfBirth);
        let age = today.getFullYear() - birthDate.getFullYear();
        const monthDiff = today.getMonth() - birthDate.getMonth();
        if (
            monthDiff < 0 ||
            (monthDiff === 0 && today.getDate() < birthDate.getDate())
        ) {
            age--;
        }
        return age;
    };

    const handleRemoveDiagnosis = (diagnosisId: number) => {
        setSelectedDiagnoses(
            selectedDiagnoses.filter((d) => d.id !== diagnosisId),
        );
    };

    // Callback when a diagnosis is selected from async search
    const handleDiagnosisSelected = (diagnosis: {
        id: number;
        name: string;
        icd_code: string | null;
    }) => {
        // Check if already added
        if (selectedDiagnoses.find((d) => d.id === diagnosis.id)) {
            return;
        }

        setSelectedDiagnoses([
            ...selectedDiagnoses,
            {
                id: diagnosis.id,
                name: diagnosis.name,
                icd_code: diagnosis.icd_code || '',
            },
        ]);
    };

    const handleAddSupply = () => {
        if (selectedSupply && parseFloat(supplyQuantity) > 0) {
            const drug = availableDrugs.find((d) => d.id === selectedSupply);
            if (drug) {
                // Check if supply already exists
                const existingIndex = supplies.findIndex(
                    (s) => s.drug_id === drug.id,
                );
                if (existingIndex >= 0) {
                    // Update quantity
                    const updatedSupplies = [...supplies];
                    updatedSupplies[existingIndex].quantity =
                        parseFloat(supplyQuantity);
                    setSupplies(updatedSupplies);
                } else {
                    // Add new supply
                    setSupplies([
                        ...supplies,
                        {
                            drug_id: drug.id,
                            quantity: parseFloat(supplyQuantity),
                            drug,
                        },
                    ]);
                }
            }
            setSelectedSupply(null);
            setSupplyQuantity('1');
        }
    };

    const handleRemoveSupply = (drugId: number) => {
        setSupplies(supplies.filter((s) => s.drug_id !== drugId));
    };

    const handleUpdateSupplyQuantity = (drugId: number, quantity: string) => {
        const updatedSupplies = supplies.map((s) =>
            s.drug_id === drugId
                ? { ...s, quantity: parseFloat(quantity) || 0 }
                : s,
        );
        setSupplies(updatedSupplies);
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-h-[90vh] max-w-5xl sm:max-w-5xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Perform Minor Procedure</DialogTitle>
                    <DialogDescription>
                        Record procedure details for{' '}
                        {patientCheckin.patient.first_name}{' '}
                        {patientCheckin.patient.last_name}
                    </DialogDescription>
                </DialogHeader>

                <Form
                    action="/minor-procedures"
                    method="post"
                    onSuccess={onSuccess}
                >
                    {({ errors, processing }) => (
                        <div className="space-y-6">
                            {/* Patient Information */}
                            <div className="space-y-4 rounded-lg border bg-muted/50 p-4">
                                <h3 className="flex items-center gap-2 font-medium">
                                    <User className="h-4 w-4" />
                                    Patient Information
                                </h3>
                                <div className="grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <p className="text-muted-foreground">
                                            Name
                                        </p>
                                        <p className="font-medium">
                                            {patientCheckin.patient.first_name}{' '}
                                            {patientCheckin.patient.last_name}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground">
                                            Patient Number
                                        </p>
                                        <p className="font-medium">
                                            {
                                                patientCheckin.patient
                                                    .patient_number
                                            }
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground">
                                            Age
                                        </p>
                                        <p className="font-medium">
                                            {calculateAge(
                                                patientCheckin.patient
                                                    .date_of_birth,
                                            )}{' '}
                                            years
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground">
                                            Phone
                                        </p>
                                        <p className="font-medium">
                                            {patientCheckin.patient
                                                .phone_number || 'Not provided'}
                                        </p>
                                    </div>
                                </div>

                                {/* Vitals */}
                                {patientCheckin.vital_signs &&
                                    patientCheckin.vital_signs.length > 0 && (
                                        <div className="mt-4 border-t pt-4">
                                            <h4 className="mb-2 flex items-center gap-2 text-sm font-medium">
                                                <Activity className="h-4 w-4 text-green-600" />
                                                Latest Vitals
                                            </h4>
                                            <div className="grid grid-cols-3 gap-2 text-xs">
                                                {patientCheckin.vital_signs[0]
                                                    .temperature && (
                                                    <div>
                                                        <span className="text-muted-foreground">
                                                            Temp:
                                                        </span>{' '}
                                                        {
                                                            patientCheckin
                                                                .vital_signs[0]
                                                                .temperature
                                                        }
                                                        °C
                                                    </div>
                                                )}
                                                {patientCheckin.vital_signs[0]
                                                    .blood_pressure_systolic && (
                                                    <div>
                                                        <span className="text-muted-foreground">
                                                            BP:
                                                        </span>{' '}
                                                        {
                                                            patientCheckin
                                                                .vital_signs[0]
                                                                .blood_pressure_systolic
                                                        }
                                                        /
                                                        {
                                                            patientCheckin
                                                                .vital_signs[0]
                                                                .blood_pressure_diastolic
                                                        }
                                                    </div>
                                                )}
                                                {patientCheckin.vital_signs[0]
                                                    .pulse_rate && (
                                                    <div>
                                                        <span className="text-muted-foreground">
                                                            Pulse:
                                                        </span>{' '}
                                                        {
                                                            patientCheckin
                                                                .vital_signs[0]
                                                                .pulse_rate
                                                        }{' '}
                                                        bpm
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    )}
                            </div>

                            {/* Hidden field for patient_checkin_id */}
                            <input
                                type="hidden"
                                name="patient_checkin_id"
                                value={patientCheckin.id}
                            />

                            {/* Procedure Type */}
                            <div className="space-y-2">
                                <Label>Procedure Type *</Label>
                                <Popover
                                    open={procedureTypeOpen}
                                    onOpenChange={setProcedureTypeOpen}
                                >
                                    <PopoverTrigger asChild>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            role="combobox"
                                            aria-expanded={procedureTypeOpen}
                                            className="w-full justify-between"
                                        >
                                            {selectedProcedureType
                                                ? procedureTypes.find(
                                                      (t) =>
                                                          t.id ===
                                                          selectedProcedureType,
                                                  )?.name
                                                : 'Select procedure type...'}
                                            <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent
                                        className="w-[500px] p-0"
                                        align="start"
                                    >
                                        <Command>
                                            <CommandInput placeholder="Search procedure types..." />
                                            <CommandList>
                                                <CommandEmpty>
                                                    No procedure type found.
                                                </CommandEmpty>
                                                <CommandGroup>
                                                    {procedureTypes.map(
                                                        (type) => (
                                                            <CommandItem
                                                                key={type.id}
                                                                value={`${type.name} ${type.code} ${type.category}`}
                                                                onSelect={() => {
                                                                    setSelectedProcedureType(
                                                                        type.id,
                                                                    );
                                                                    setProcedureTypeOpen(
                                                                        false,
                                                                    );
                                                                }}
                                                            >
                                                                <Check
                                                                    className={cn(
                                                                        'mr-2 h-4 w-4',
                                                                        selectedProcedureType ===
                                                                            type.id
                                                                            ? 'opacity-100'
                                                                            : 'opacity-0',
                                                                    )}
                                                                />
                                                                <div className="flex flex-1 items-center justify-between">
                                                                    <div className="flex flex-col gap-1">
                                                                        <div className="flex items-center gap-2">
                                                                            <span className="font-medium">
                                                                                {
                                                                                    type.name
                                                                                }
                                                                            </span>
                                                                            <Badge
                                                                                variant="outline"
                                                                                className="text-xs"
                                                                            >
                                                                                {
                                                                                    type.code
                                                                                }
                                                                            </Badge>
                                                                        </div>
                                                                        <div className="text-xs text-muted-foreground">
                                                                            Age
                                                                            category:{' '}
                                                                            {
                                                                                type.category
                                                                            }
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </CommandItem>
                                                        ),
                                                    )}
                                                </CommandGroup>
                                            </CommandList>
                                        </Command>
                                    </PopoverContent>
                                </Popover>
                                <input
                                    type="hidden"
                                    name="minor_procedure_type_id"
                                    value={selectedProcedureType || ''}
                                    required
                                />
                                {errors.minor_procedure_type_id && (
                                    <p className="text-sm text-destructive">
                                        {errors.minor_procedure_type_id}
                                    </p>
                                )}
                            </div>

                            {/* Procedure Notes */}
                            <div className="space-y-2">
                                <Label htmlFor="procedure_notes">
                                    Procedure Notes (Optional)
                                </Label>
                                <Textarea
                                    id="procedure_notes"
                                    name="procedure_notes"
                                    placeholder="Describe the procedure performed, findings, and any relevant details..."
                                    rows={4}
                                />
                                {errors.procedure_notes && (
                                    <p className="text-sm text-destructive">
                                        {errors.procedure_notes}
                                    </p>
                                )}
                            </div>

                            {/* Diagnoses */}
                            <div className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <h3 className="flex items-center gap-2 text-lg font-semibold">
                                        <FileText className="h-5 w-5 text-blue-600" />
                                        Diagnoses (Optional)
                                        {selectedDiagnoses.length > 0 && (
                                            <Badge
                                                variant="secondary"
                                                className="ml-2"
                                            >
                                                {selectedDiagnoses.length}
                                            </Badge>
                                        )}
                                    </h3>
                                </div>

                                {/* Add Diagnosis */}
                                <div className="rounded-lg border border-blue-200 bg-gradient-to-br from-blue-50 to-indigo-50 p-4 dark:border-blue-800 dark:from-blue-950/20 dark:to-indigo-950/20">
                                    <Label className="mb-2 block text-sm font-medium">
                                        Add Diagnosis
                                    </Label>
                                    <div className="flex gap-2">
                                        <div className="flex-1">
                                            <AsyncDiagnosisSelect
                                                value={null}
                                                onChange={() => {}}
                                                onSelectDiagnosis={
                                                    handleDiagnosisSelected
                                                }
                                                excludeIds={selectedDiagnoses.map(
                                                    (d) => d.id,
                                                )}
                                                placeholder="Search diagnoses..."
                                            />
                                        </div>
                                    </div>
                                </div>

                                {/* Selected Diagnoses */}
                                {selectedDiagnoses.length > 0 && (
                                    <div className="space-y-2">
                                        {selectedDiagnoses.map((diagnosis) => (
                                            <div
                                                key={diagnosis.id}
                                                className="flex items-center justify-between rounded-lg border bg-card p-3"
                                            >
                                                <div className="flex-1">
                                                    <div className="flex items-center gap-2">
                                                        <p className="font-medium">
                                                            {diagnosis.name}
                                                        </p>
                                                        {diagnosis.icd_code && (
                                                            <Badge
                                                                variant="outline"
                                                                className="text-xs"
                                                            >
                                                                {
                                                                    diagnosis.icd_code
                                                                }
                                                            </Badge>
                                                        )}
                                                    </div>
                                                </div>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        handleRemoveDiagnosis(
                                                            diagnosis.id,
                                                        )
                                                    }
                                                >
                                                    <Trash2 className="h-4 w-4 text-destructive" />
                                                </Button>
                                                <input
                                                    type="hidden"
                                                    name="diagnoses[]"
                                                    value={diagnosis.id}
                                                />
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>

                            {/* Supplies */}
                            <div className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <h3 className="flex items-center gap-2 text-lg font-semibold">
                                        <Pill className="h-5 w-5 text-purple-600" />
                                        Supplies Used (Optional)
                                        {supplies.length > 0 && (
                                            <Badge
                                                variant="secondary"
                                                className="ml-2"
                                            >
                                                {supplies.length}
                                            </Badge>
                                        )}
                                    </h3>
                                </div>

                                {/* Add Supply */}
                                <div className="rounded-lg border border-purple-200 bg-gradient-to-br from-purple-50 to-pink-50 p-4 dark:border-purple-800 dark:from-purple-950/20 dark:to-pink-950/20">
                                    <Label className="mb-2 block text-sm font-medium">
                                        Add Supply
                                    </Label>
                                    <div className="flex gap-2">
                                        <Popover
                                            open={supplyOpen}
                                            onOpenChange={setSupplyOpen}
                                        >
                                            <PopoverTrigger asChild>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    role="combobox"
                                                    aria-expanded={supplyOpen}
                                                    className="min-w-0 flex-1 justify-between"
                                                >
                                                    <span className="truncate">
                                                        {selectedSupply
                                                            ? availableDrugs.find(
                                                                  (d) =>
                                                                      d.id ===
                                                                      selectedSupply,
                                                              )?.name
                                                            : 'Select supply...'}
                                                    </span>
                                                    <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                                </Button>
                                            </PopoverTrigger>
                                            <PopoverContent
                                                className="w-[500px] p-0"
                                                align="start"
                                            >
                                                <Command>
                                                    <CommandInput placeholder="Search supplies..." />
                                                    <CommandList>
                                                        <CommandEmpty>
                                                            No supply found.
                                                        </CommandEmpty>
                                                        <CommandGroup>
                                                            {availableDrugs.map(
                                                                (drug) => (
                                                                    <CommandItem
                                                                        key={
                                                                            drug.id
                                                                        }
                                                                        value={`${drug.name} ${drug.generic_name} ${drug.brand_name} ${drug.drug_code}`}
                                                                        onSelect={() => {
                                                                            setSelectedSupply(
                                                                                drug.id,
                                                                            );
                                                                            setSupplyOpen(
                                                                                false,
                                                                            );
                                                                        }}
                                                                    >
                                                                        <Check
                                                                            className={cn(
                                                                                'mr-2 h-4 w-4',
                                                                                selectedSupply ===
                                                                                    drug.id
                                                                                    ? 'opacity-100'
                                                                                    : 'opacity-0',
                                                                            )}
                                                                        />
                                                                        <div className="flex flex-1 flex-col gap-1">
                                                                            <div className="flex items-center gap-2">
                                                                                <span className="font-medium">
                                                                                    {
                                                                                        drug.name
                                                                                    }
                                                                                </span>
                                                                                <Badge
                                                                                    variant="outline"
                                                                                    className="text-xs"
                                                                                >
                                                                                    {
                                                                                        drug.drug_code
                                                                                    }
                                                                                </Badge>
                                                                            </div>
                                                                            <div className="text-xs text-gray-600 dark:text-gray-400">
                                                                                {
                                                                                    drug.form
                                                                                }
                                                                                {drug.strength &&
                                                                                    ` • ${drug.strength}`}{' '}
                                                                                •
                                                                                KES{' '}
                                                                                {
                                                                                    drug.unit_price
                                                                                }

                                                                                /
                                                                                {
                                                                                    drug.unit_type
                                                                                }
                                                                            </div>
                                                                        </div>
                                                                    </CommandItem>
                                                                ),
                                                            )}
                                                        </CommandGroup>
                                                    </CommandList>
                                                </Command>
                                            </PopoverContent>
                                        </Popover>
                                        <Input
                                            type="number"
                                            placeholder="Qty"
                                            value={supplyQuantity}
                                            onChange={(e) =>
                                                setSupplyQuantity(
                                                    e.target.value,
                                                )
                                            }
                                            min="0.01"
                                            step="0.01"
                                            className="w-24"
                                        />
                                        <Button
                                            type="button"
                                            onClick={handleAddSupply}
                                            disabled={
                                                !selectedSupply ||
                                                parseFloat(supplyQuantity) <= 0
                                            }
                                        >
                                            Add
                                        </Button>
                                    </div>
                                    {selectedSupply && (
                                        <p className="mt-2 text-xs text-amber-600 dark:text-amber-400">
                                            ⚠️ Click "Add" to include this supply in the procedure
                                        </p>
                                    )}
                                </div>

                                {/* Selected Supplies */}
                                {supplies.length > 0 && (
                                    <div className="space-y-2">
                                        {supplies.map((supply) => (
                                            <div
                                                key={supply.drug_id}
                                                className="flex items-center justify-between rounded-lg border bg-card p-3"
                                            >
                                                <div className="flex-1">
                                                    <div className="flex items-center gap-2">
                                                        <p className="font-medium">
                                                            {supply.drug.name}
                                                        </p>
                                                        <Badge
                                                            variant="outline"
                                                            className="text-xs"
                                                        >
                                                            {
                                                                supply.drug
                                                                    .drug_code
                                                            }
                                                        </Badge>
                                                    </div>
                                                    <p className="text-xs text-muted-foreground">
                                                        {supply.drug.form}
                                                        {supply.drug.strength &&
                                                            ` • ${supply.drug.strength}`}
                                                    </p>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <Input
                                                        type="number"
                                                        value={supply.quantity}
                                                        onChange={(e) =>
                                                            handleUpdateSupplyQuantity(
                                                                supply.drug_id,
                                                                e.target.value,
                                                            )
                                                        }
                                                        min="0.01"
                                                        step="0.01"
                                                        className="w-20"
                                                    />
                                                    <span className="text-sm text-muted-foreground">
                                                        {supply.drug.unit_type}
                                                    </span>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            handleRemoveSupply(
                                                                supply.drug_id,
                                                            )
                                                        }
                                                    >
                                                        <Trash2 className="h-4 w-4 text-destructive" />
                                                    </Button>
                                                </div>
                                                <input
                                                    type="hidden"
                                                    name="supplies[][drug_id]"
                                                    value={supply.drug_id}
                                                />
                                                <input
                                                    type="hidden"
                                                    name="supplies[][quantity]"
                                                    value={supply.quantity}
                                                />
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>

                            {/* Action Buttons */}
                            <div className="flex justify-end gap-2 border-t pt-4">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={onClose}
                                    disabled={processing}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={processing || !!selectedSupply}
                                    title={selectedSupply ? 'Please add or clear the selected supply first' : undefined}
                                >
                                    {processing
                                        ? 'Completing Procedure...'
                                        : 'Complete Procedure'}
                                </Button>
                            </div>
                        </div>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
