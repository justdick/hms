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
import { cn } from '@/lib/utils';
import { Check, ChevronsUpDown, FileText, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface Diagnosis {
    id: number;
    diagnosis: string;
    code: string;
    g_drg: string;
    icd_10: string;
}

interface ConsultationDiagnosis {
    id: number;
    diagnosis: Diagnosis;
    type: 'provisional' | 'principal';
}

interface Props {
    diagnoses: Diagnosis[];
    consultationDiagnoses: ConsultationDiagnosis[];
    onAdd: (diagnosisId: number, type: 'provisional' | 'principal') => void;
    onDelete: (id: number) => void;
    processing: boolean;
    consultationStatus: string;
}

export default function DiagnosisFormSection({
    diagnoses,
    consultationDiagnoses,
    onAdd,
    onDelete,
    processing,
    consultationStatus,
}: Props) {
    const [provisionalOpen, setProvisionalOpen] = useState(false);
    const [principalOpen, setPrincipalOpen] = useState(false);
    const [selectedProvisional, setSelectedProvisional] = useState<
        number | null
    >(null);
    const [selectedPrincipal, setSelectedPrincipal] = useState<number | null>(
        null,
    );

    const provisionalDiagnoses = consultationDiagnoses.filter(
        (d) => d.type === 'provisional',
    );
    const principalDiagnoses = consultationDiagnoses.filter(
        (d) => d.type === 'principal',
    );

    // Get IDs of already added diagnoses for each type
    const addedProvisionalIds = provisionalDiagnoses.map((d) => d.diagnosis.id);
    const addedPrincipalIds = principalDiagnoses.map((d) => d.diagnosis.id);

    // Filter available diagnoses to exclude already added ones
    const availableProvisionalDiagnoses = diagnoses.filter(
        (d) => !addedProvisionalIds.includes(d.id),
    );
    const availablePrincipalDiagnoses = diagnoses.filter(
        (d) => !addedPrincipalIds.includes(d.id),
    );

    const handleAddProvisional = () => {
        if (selectedProvisional) {
            onAdd(selectedProvisional, 'provisional');
            setSelectedProvisional(null);
        }
    };

    const handleAddPrincipal = () => {
        if (selectedPrincipal) {
            onAdd(selectedPrincipal, 'principal');
            setSelectedPrincipal(null);
        }
    };

    return (
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {/* Left Column: Provisional Diagnoses */}
            <div className="space-y-4">
                <div className="flex items-center justify-between">
                    <h3 className="flex items-center gap-2 text-lg font-semibold">
                        <FileText className="h-5 w-5 text-blue-600" />
                        Provisional Diagnoses
                        <Badge variant="secondary" className="ml-2">
                            {provisionalDiagnoses.length}
                        </Badge>
                    </h3>
                </div>

                {/* Add Provisional Diagnosis */}
                {consultationStatus === 'in_progress' && (
                    <div className="rounded-lg border border-blue-200 bg-gradient-to-br from-blue-50 to-indigo-50 p-4 dark:border-blue-800 dark:from-blue-950/20 dark:to-indigo-950/20">
                        <Label className="mb-2 block text-sm font-medium">
                            Add Provisional Diagnosis
                        </Label>
                        <div className="flex gap-2">
                            <Popover
                                open={provisionalOpen}
                                onOpenChange={setProvisionalOpen}
                            >
                                <PopoverTrigger asChild>
                                    <Button
                                        variant="outline"
                                        role="combobox"
                                        aria-expanded={provisionalOpen}
                                        className="flex-1 justify-between"
                                    >
                                        {selectedProvisional
                                            ? diagnoses.find(
                                                  (d) =>
                                                      d.id ===
                                                      selectedProvisional,
                                              )?.diagnosis
                                            : 'Select diagnosis...'}
                                        <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent
                                    className="w-[500px] p-0"
                                    align="start"
                                >
                                    <Command>
                                        <CommandInput placeholder="Search diagnoses..." />
                                        <CommandList>
                                            <CommandEmpty>
                                                No diagnosis found.
                                            </CommandEmpty>
                                            <CommandGroup>
                                                {availableProvisionalDiagnoses.map(
                                                    (diagnosis) => (
                                                        <CommandItem
                                                            key={diagnosis.id}
                                                            value={`${diagnosis.diagnosis} ${diagnosis.code} ${diagnosis.icd_10}`}
                                                            onSelect={() => {
                                                                setSelectedProvisional(
                                                                    diagnosis.id,
                                                                );
                                                                setProvisionalOpen(
                                                                    false,
                                                                );
                                                            }}
                                                        >
                                                            <Check
                                                                className={cn(
                                                                    'mr-2 h-4 w-4',
                                                                    selectedProvisional ===
                                                                        diagnosis.id
                                                                        ? 'opacity-100'
                                                                        : 'opacity-0',
                                                                )}
                                                            />
                                                            <div className="flex flex-1 flex-col gap-1">
                                                                <div className="flex items-center gap-2">
                                                                    <span className="font-medium">
                                                                        {
                                                                            diagnosis.diagnosis
                                                                        }
                                                                    </span>
                                                                    <Badge
                                                                        variant="outline"
                                                                        className="text-xs"
                                                                    >
                                                                        {
                                                                            diagnosis.code
                                                                        }
                                                                    </Badge>
                                                                </div>
                                                                <div className="text-xs text-gray-600 dark:text-gray-400">
                                                                    ICD-10:{' '}
                                                                    {
                                                                        diagnosis.icd_10
                                                                    }{' '}
                                                                    • Group:{' '}
                                                                    {
                                                                        diagnosis.g_drg
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
                            <Button
                                onClick={handleAddProvisional}
                                disabled={!selectedProvisional || processing}
                                size="icon"
                            >
                                <Plus className="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                )}

                {/* Provisional Diagnoses List */}
                {provisionalDiagnoses.length > 0 ? (
                    <div className="grid grid-cols-1 gap-3 2xl:grid-cols-2">
                        {provisionalDiagnoses.map((item) => (
                            <div
                                key={item.id}
                                className="rounded-lg border bg-gray-50 p-4 dark:bg-gray-800"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div className="min-w-0 flex-1">
                                        <h4 className="line-clamp-2 font-semibold text-gray-900 dark:text-gray-100">
                                            {item.diagnosis.diagnosis}
                                        </h4>
                                        <div className="mt-2 flex flex-wrap gap-2">
                                            <Badge
                                                variant="outline"
                                                className="text-xs"
                                            >
                                                {item.diagnosis.code}
                                            </Badge>
                                            <Badge
                                                variant="outline"
                                                className="text-xs"
                                            >
                                                ICD-10: {item.diagnosis.icd_10}
                                            </Badge>
                                        </div>
                                        <p className="mt-2 text-xs text-gray-600 dark:text-gray-400">
                                            Group: {item.diagnosis.g_drg}
                                        </p>
                                    </div>
                                    {consultationStatus === 'in_progress' && (
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => onDelete(item.id)}
                                            className="shrink-0 text-red-600 hover:bg-red-50 hover:text-red-700"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="rounded-lg border py-12 text-center text-gray-500">
                        <FileText className="mx-auto mb-4 h-12 w-12 text-gray-300" />
                        <p>No provisional diagnoses</p>
                    </div>
                )}
            </div>

            {/* Right Column: Principal Diagnoses */}
            <div className="space-y-4">
                <div className="flex items-center justify-between">
                    <h3 className="flex items-center gap-2 text-lg font-semibold">
                        <FileText className="h-5 w-5 text-green-600" />
                        Principal Diagnoses
                        <Badge variant="secondary" className="ml-2">
                            {principalDiagnoses.length}
                        </Badge>
                    </h3>
                </div>

                {/* Add Principal Diagnosis */}
                {consultationStatus === 'in_progress' && (
                    <div className="rounded-lg border border-green-200 bg-gradient-to-br from-green-50 to-emerald-50 p-4 dark:border-green-800 dark:from-green-950/20 dark:to-emerald-950/20">
                        <Label className="mb-2 block text-sm font-medium">
                            Add Principal Diagnosis
                        </Label>
                        <div className="flex gap-2">
                            <Popover
                                open={principalOpen}
                                onOpenChange={setPrincipalOpen}
                            >
                                <PopoverTrigger asChild>
                                    <Button
                                        variant="outline"
                                        role="combobox"
                                        aria-expanded={principalOpen}
                                        className="flex-1 justify-between"
                                    >
                                        {selectedPrincipal
                                            ? diagnoses.find(
                                                  (d) =>
                                                      d.id ===
                                                      selectedPrincipal,
                                              )?.diagnosis
                                            : 'Select diagnosis...'}
                                        <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent
                                    className="w-[500px] p-0"
                                    align="start"
                                >
                                    <Command>
                                        <CommandInput placeholder="Search diagnoses..." />
                                        <CommandList>
                                            <CommandEmpty>
                                                No diagnosis found.
                                            </CommandEmpty>
                                            <CommandGroup>
                                                {availablePrincipalDiagnoses.map(
                                                    (diagnosis) => (
                                                        <CommandItem
                                                            key={diagnosis.id}
                                                            value={`${diagnosis.diagnosis} ${diagnosis.code} ${diagnosis.icd_10}`}
                                                            onSelect={() => {
                                                                setSelectedPrincipal(
                                                                    diagnosis.id,
                                                                );
                                                                setPrincipalOpen(
                                                                    false,
                                                                );
                                                            }}
                                                        >
                                                            <Check
                                                                className={cn(
                                                                    'mr-2 h-4 w-4',
                                                                    selectedPrincipal ===
                                                                        diagnosis.id
                                                                        ? 'opacity-100'
                                                                        : 'opacity-0',
                                                                )}
                                                            />
                                                            <div className="flex flex-1 flex-col gap-1">
                                                                <div className="flex items-center gap-2">
                                                                    <span className="font-medium">
                                                                        {
                                                                            diagnosis.diagnosis
                                                                        }
                                                                    </span>
                                                                    <Badge
                                                                        variant="outline"
                                                                        className="text-xs"
                                                                    >
                                                                        {
                                                                            diagnosis.code
                                                                        }
                                                                    </Badge>
                                                                </div>
                                                                <div className="text-xs text-gray-600 dark:text-gray-400">
                                                                    ICD-10:{' '}
                                                                    {
                                                                        diagnosis.icd_10
                                                                    }{' '}
                                                                    • Group:{' '}
                                                                    {
                                                                        diagnosis.g_drg
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
                            <Button
                                onClick={handleAddPrincipal}
                                disabled={!selectedPrincipal || processing}
                                size="icon"
                            >
                                <Plus className="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                )}

                {/* Principal Diagnoses List */}
                {principalDiagnoses.length > 0 ? (
                    <div className="grid grid-cols-1 gap-3 2xl:grid-cols-2">
                        {principalDiagnoses.map((item) => (
                            <div
                                key={item.id}
                                className="rounded-lg border bg-gray-50 p-4 dark:bg-gray-800"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div className="min-w-0 flex-1">
                                        <h4 className="line-clamp-2 font-semibold text-gray-900 dark:text-gray-100">
                                            {item.diagnosis.diagnosis}
                                        </h4>
                                        <div className="mt-2 flex flex-wrap gap-2">
                                            <Badge
                                                variant="outline"
                                                className="text-xs"
                                            >
                                                {item.diagnosis.code}
                                            </Badge>
                                            <Badge
                                                variant="outline"
                                                className="text-xs"
                                            >
                                                ICD-10: {item.diagnosis.icd_10}
                                            </Badge>
                                        </div>
                                        <p className="mt-2 text-xs text-gray-600 dark:text-gray-400">
                                            Group: {item.diagnosis.g_drg}
                                        </p>
                                    </div>
                                    {consultationStatus === 'in_progress' && (
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => onDelete(item.id)}
                                            className="shrink-0 text-red-600 hover:bg-red-50 hover:text-red-700"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="rounded-lg border py-12 text-center text-gray-500">
                        <FileText className="mx-auto mb-4 h-12 w-12 text-gray-300" />
                        <p>No principal diagnoses</p>
                    </div>
                )}
            </div>
        </div>
    );
}
