import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { Form } from '@inertiajs/react';
import { format, formatDistanceToNow, isAfter, isPast } from 'date-fns';
import {
    AlertCircle,
    AlertTriangle,
    CheckCircle2,
    Clock,
    Pill,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

// Type definitions
interface Patient {
    id: number;
    first_name: string;
    last_name: string;
    date_of_birth?: string;
    gender?: string;
}

interface Bed {
    id: number;
    bed_number: string;
}

interface PatientAdmission {
    id: number;
    patient: Patient;
    bed?: Bed;
    admission_number: string;
    admitted_at: string;
}

interface Drug {
    id: number;
    name: string;
    strength?: string;
}

interface Prescription {
    id: number;
    medication_name: string;
    dosage?: string;
    dose_quantity?: string;
    frequency?: string;
    duration?: string;
    route?: string;
    drug?: Drug;
}

interface MedicationAdministration {
    id: number;
    prescription: Prescription;
    scheduled_time: string;
    status:
        | 'scheduled'
        | 'given'
        | 'held'
        | 'refused'
        | 'omitted'
        | 'cancelled';
    dosage_given?: string;
    route?: string;
    notes?: string;
    administered_at?: string;
    administered_by?: {
        id: number;
        name: string;
    };
    is_adjusted?: boolean;
}

interface PatientAdmissionWithMeds extends PatientAdmission {
    medication_administrations?: MedicationAdministration[];
}

interface MedicationAdministrationPanelProps {
    admission: PatientAdmissionWithMeds | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

export function MedicationAdministrationPanel({
    admission,
    open,
    onOpenChange,
}: MedicationAdministrationPanelProps) {
    const [administerDialogOpen, setAdministerDialogOpen] = useState(false);
    const [holdDialogOpen, setHoldDialogOpen] = useState(false);
    const [selectedMed, setSelectedMed] =
        useState<MedicationAdministration | null>(null);

    if (!admission) return null;

    // Group medications by date
    const medicationsByDate = (
        admission.medication_administrations || []
    ).reduce(
        (acc, med) => {
            const date = format(new Date(med.scheduled_time), 'yyyy-MM-dd');
            if (!acc[date]) {
                acc[date] = [];
            }
            acc[date].push(med);
            return acc;
        },
        {} as Record<string, MedicationAdministration[]>,
    );

    const today = format(new Date(), 'yyyy-MM-dd');
    const todayMeds = medicationsByDate[today] || [];

    const dueNow = todayMeds.filter(
        (med) =>
            med.status === 'scheduled' &&
            (isPast(new Date(med.scheduled_time)) ||
                isAfter(new Date(), new Date(med.scheduled_time))),
    );

    const upcoming = todayMeds.filter(
        (med) =>
            med.status === 'scheduled' &&
            !isPast(new Date(med.scheduled_time)) &&
            !isAfter(new Date(), new Date(med.scheduled_time)),
    );

    const administered = todayMeds.filter((med) => med.status === 'given');

    const openAdministerDialog = (med: MedicationAdministration) => {
        setSelectedMed(med);
        setAdministerDialogOpen(true);
    };

    const openHoldDialog = (med: MedicationAdministration) => {
        setSelectedMed(med);
        setHoldDialogOpen(true);
    };

    const handleRefuse = (med: MedicationAdministration) => {
        if (confirm('Mark this medication as refused by patient?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `/admissions/${med.id}/refuse`;
            document.body.appendChild(form);
            form.submit();
        }
    };

    return (
        <>
            <Sheet open={open} onOpenChange={onOpenChange}>
                <SheetContent
                    side="right"
                    className="w-full overflow-y-auto sm:max-w-2xl"
                >
                    <SheetHeader>
                        <SheetTitle className="flex items-center gap-2">
                            <Pill className="h-5 w-5" />
                            Medication Administration
                        </SheetTitle>
                        <SheetDescription>
                            {admission.patient.first_name}{' '}
                            {admission.patient.last_name} • Bed{' '}
                            {admission.bed?.bed_number || 'Unassigned'} •{' '}
                            {dueNow.length > 0 && (
                                <span className="font-semibold text-red-600">
                                    {dueNow.length} due now
                                </span>
                            )}
                        </SheetDescription>
                    </SheetHeader>

                    <div className="mt-6 space-y-6">
                        {todayMeds.length === 0 && (
                            <div className="rounded-lg border-2 border-dashed border-muted-foreground/25 p-8 text-center">
                                <Pill className="mx-auto mb-3 h-12 w-12 text-muted-foreground/50" />
                                <p className="text-sm text-muted-foreground">
                                    No medications scheduled for today
                                </p>
                            </div>
                        )}

                        {dueNow.length > 0 && (
                            <div>
                                <h3 className="mb-3 flex items-center gap-2 text-lg font-semibold text-red-600 dark:text-red-500">
                                    <AlertCircle className="h-5 w-5" />
                                    Due Now ({dueNow.length})
                                </h3>
                                <div className="space-y-3">
                                    {dueNow.map((med) => (
                                        <MedicationCard
                                            key={med.id}
                                            medication={med}
                                            priority="high"
                                            onAdminister={openAdministerDialog}
                                            onHold={openHoldDialog}
                                            onRefuse={handleRefuse}
                                        />
                                    ))}
                                </div>
                            </div>
                        )}

                        {upcoming.length > 0 && (
                            <div>
                                <h3 className="mb-3 flex items-center gap-2 text-lg font-semibold">
                                    <Clock className="h-5 w-5" />
                                    Scheduled Today ({upcoming.length})
                                </h3>
                                <div className="space-y-3">
                                    {upcoming.map((med) => (
                                        <MedicationCard
                                            key={med.id}
                                            medication={med}
                                            priority="normal"
                                            onAdminister={openAdministerDialog}
                                            onHold={openHoldDialog}
                                            onRefuse={handleRefuse}
                                        />
                                    ))}
                                </div>
                            </div>
                        )}

                        {administered.length > 0 && (
                            <div>
                                <h3 className="mb-3 flex items-center gap-2 text-lg font-semibold text-green-600 dark:text-green-500">
                                    <CheckCircle2 className="h-5 w-5" />
                                    Administered Today ({administered.length})
                                </h3>
                                <div className="space-y-3">
                                    {administered.map((med) => (
                                        <MedicationCard
                                            key={med.id}
                                            medication={med}
                                            priority="completed"
                                            onAdminister={openAdministerDialog}
                                            onHold={openHoldDialog}
                                            onRefuse={handleRefuse}
                                        />
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                </SheetContent>
            </Sheet>

            {selectedMed && (
                <>
                    <AdministerMedicationDialog
                        medication={selectedMed}
                        open={administerDialogOpen}
                        onOpenChange={setAdministerDialogOpen}
                    />
                    <HoldMedicationDialog
                        medication={selectedMed}
                        open={holdDialogOpen}
                        onOpenChange={setHoldDialogOpen}
                    />
                </>
            )}
        </>
    );
}

interface MedicationCardProps {
    medication: MedicationAdministration;
    priority: 'high' | 'normal' | 'completed';
    onAdminister: (med: MedicationAdministration) => void;
    onHold: (med: MedicationAdministration) => void;
    onRefuse: (med: MedicationAdministration) => void;
}

function MedicationCard({
    medication,
    priority,
    onAdminister,
    onHold,
    onRefuse,
}: MedicationCardProps) {
    const scheduledTime = new Date(medication.scheduled_time);
    const now = new Date();
    const isOverdue =
        isPast(scheduledTime) && medication.status === 'scheduled';

    // Check if medication is due (past scheduled time or within 30 minutes)
    const isDue =
        medication.status === 'scheduled' &&
        (isPast(scheduledTime) ||
            scheduledTime.getTime() - now.getTime() <= 30 * 60 * 1000);

    return (
        <Card
            className={
                priority === 'high'
                    ? 'border-red-300 bg-red-50 dark:border-red-800 dark:bg-red-950/20'
                    : priority === 'completed'
                      ? 'border-green-300 bg-green-50 dark:border-green-800 dark:bg-green-950/20'
                      : ''
            }
        >
            <CardContent className="p-4">
                <div className="flex items-start justify-between">
                    <div className="flex-1">
                        <div className="mb-1 flex items-start gap-2">
                            <h4 className="font-semibold">
                                {medication.prescription.drug?.name ||
                                    medication.prescription.medication_name}
                                {medication.prescription.drug?.strength && (
                                    <span className="ml-1 text-sm font-normal text-muted-foreground">
                                        {medication.prescription.drug.strength}
                                    </span>
                                )}
                            </h4>
                            {isOverdue && (
                                <Badge
                                    variant="destructive"
                                    className="text-xs"
                                >
                                    Overdue
                                </Badge>
                            )}
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Dose:{' '}
                            {calculateTotalDosage(
                                medication.prescription.drug?.strength,
                                medication.prescription.dosage ||
                                    medication.prescription.dose_quantity,
                            ) ||
                                medication.prescription.dosage ||
                                medication.prescription.dose_quantity ||
                                'Not specified'}{' '}
                            •{' '}
                            {medication.route ||
                                medication.prescription.route ||
                                'PO'}
                        </p>
                        <p className="mt-1 flex items-center gap-1 text-sm text-muted-foreground">
                            <Clock className="h-3 w-3" />
                            Scheduled: {format(scheduledTime, 'HH:mm')}
                            {isOverdue && (
                                <span className="ml-1 text-red-600 dark:text-red-400">
                                    (
                                    {formatDistanceToNow(scheduledTime, {
                                        addSuffix: true,
                                    })}
                                    )
                                </span>
                            )}
                        </p>
                        {medication.administered_at && (
                            <p className="mt-1 text-sm text-green-600 dark:text-green-400">
                                Administered at{' '}
                                {format(
                                    new Date(medication.administered_at),
                                    'HH:mm',
                                )}{' '}
                                by {medication.administered_by?.name}
                            </p>
                        )}
                        {medication.notes && (
                            <p className="mt-2 text-sm text-muted-foreground italic">
                                Note: {medication.notes}
                            </p>
                        )}
                    </div>

                    {medication.status === 'scheduled' && isDue && (
                        <div className="flex gap-2">
                            <Button
                                size="sm"
                                onClick={() => onAdminister(medication)}
                                className="bg-green-600 hover:bg-green-700"
                            >
                                <CheckCircle2 className="mr-1 h-4 w-4" />
                                Give
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => onHold(medication)}
                            >
                                <AlertTriangle className="mr-1 h-4 w-4" />
                                Hold
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => onRefuse(medication)}
                            >
                                <X className="mr-1 h-4 w-4" />
                                Refuse
                            </Button>
                        </div>
                    )}

                    {medication.status === 'scheduled' && !isDue && (
                        <Badge variant="outline" className="text-xs">
                            <Clock className="mr-1 h-3 w-3" />
                            Scheduled
                        </Badge>
                    )}

                    {medication.status !== 'scheduled' && (
                        <Badge
                            variant={
                                medication.status === 'given'
                                    ? 'default'
                                    : medication.status === 'held'
                                      ? 'secondary'
                                      : 'destructive'
                            }
                            className="capitalize"
                        >
                            {medication.status}
                        </Badge>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

interface AdministerMedicationDialogProps {
    medication: MedicationAdministration;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

// Helper function to calculate total dosage
function calculateTotalDosage(
    strength?: string,
    quantity?: string,
): string | null {
    if (!strength || !quantity) return null;

    // Extract numeric value and unit from strength (e.g., "500mg" -> 500, "mg")
    const strengthMatch = strength.match(/^(\d+(?:\.\d+)?)\s*(\w+)$/);
    if (!strengthMatch) return null;

    const strengthValue = parseFloat(strengthMatch[1]);
    const unit = strengthMatch[2].toLowerCase();

    // Extract numeric quantity (e.g., "2" or "2 tablets")
    const quantityMatch = quantity.match(/^(\d+(?:\.\d+)?)/);
    if (!quantityMatch) return null;

    const quantityValue = parseFloat(quantityMatch[1]);

    // Calculate total
    const total = strengthValue * quantityValue;

    // Format the quantity text (preserve "tablets", "capsules", etc. if present)
    const quantityText = quantity.trim();

    // Convert to grams if >= 1000mg
    if (unit === 'mg' && total >= 1000) {
        const grams = total / 1000;
        // Format grams nicely (remove unnecessary decimals)
        const gramsFormatted =
            grams % 1 === 0 ? grams.toString() : grams.toFixed(2);
        return `${quantityText} (${total}mg / ${gramsFormatted}g)`;
    }

    // Convert to mg if >= 1000mcg (micrograms)
    if ((unit === 'mcg' || unit === 'μg') && total >= 1000) {
        const mg = total / 1000;
        const mgFormatted = mg % 1 === 0 ? mg.toString() : mg.toFixed(2);
        return `${quantityText} (${total}${unit} / ${mgFormatted}mg)`;
    }

    // For other units or smaller amounts, just show the total
    return `${quantityText} (${total}${unit})`;
}

function AdministerMedicationDialog({
    medication,
    open,
    onOpenChange,
}: AdministerMedicationDialogProps) {
    const [route, setRoute] = useState(
        medication.route || medication.prescription.route || 'oral',
    );

    // Calculate the total dosage display
    const calculatedDosage = calculateTotalDosage(
        medication.prescription.drug?.strength,
        medication.prescription.dosage || medication.prescription.dose_quantity,
    );

    const defaultDosageValue =
        calculatedDosage ||
        medication.prescription.dosage ||
        medication.prescription.dose_quantity ||
        '';

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <Form
                    action={`/admissions/${medication.id}/administer`}
                    method="post"
                    onSuccess={() => {
                        onOpenChange(false);
                        toast.success('Medication administered successfully');
                    }}
                    onError={() => {
                        toast.error('Failed to administer medication');
                    }}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Administer Medication</DialogTitle>
                                <DialogDescription>
                                    <div className="space-y-1">
                                        <div className="font-semibold">
                                            {medication.prescription.drug
                                                ?.name ||
                                                medication.prescription
                                                    .medication_name}
                                        </div>
                                        {medication.prescription.drug
                                            ?.strength && (
                                            <div className="text-sm text-muted-foreground">
                                                Strength:{' '}
                                                {
                                                    medication.prescription.drug
                                                        .strength
                                                }
                                            </div>
                                        )}
                                    </div>
                                </DialogDescription>
                            </DialogHeader>

                            <div className="space-y-4">
                                {/* Prescription Info Card */}
                                <div className="rounded-lg bg-muted p-3">
                                    <div className="space-y-1 text-sm">
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">
                                                Prescribed Dose:
                                            </span>
                                            <span className="font-medium">
                                                {medication.prescription
                                                    .dosage ||
                                                    medication.prescription
                                                        .dose_quantity ||
                                                    'Not specified'}
                                            </span>
                                        </div>
                                        {medication.prescription.drug
                                            ?.strength && (
                                            <div className="flex justify-between">
                                                <span className="text-muted-foreground">
                                                    Drug Strength:
                                                </span>
                                                <span className="font-medium">
                                                    {
                                                        medication.prescription
                                                            .drug.strength
                                                    }
                                                </span>
                                            </div>
                                        )}
                                        {calculatedDosage && (
                                            <div className="flex justify-between">
                                                <span className="text-muted-foreground">
                                                    Total Dosage:
                                                </span>
                                                <span className="font-medium text-primary">
                                                    {calculatedDosage}
                                                </span>
                                            </div>
                                        )}
                                        {medication.prescription.frequency && (
                                            <div className="flex justify-between">
                                                <span className="text-muted-foreground">
                                                    Frequency:
                                                </span>
                                                <span className="font-medium">
                                                    {
                                                        medication.prescription
                                                            .frequency
                                                    }
                                                </span>
                                            </div>
                                        )}
                                        {medication.prescription.duration && (
                                            <div className="flex justify-between">
                                                <span className="text-muted-foreground">
                                                    Duration:
                                                </span>
                                                <span className="font-medium">
                                                    {
                                                        medication.prescription
                                                            .duration
                                                    }
                                                </span>
                                            </div>
                                        )}
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">
                                                Scheduled Time:
                                            </span>
                                            <span className="font-medium">
                                                {format(
                                                    new Date(
                                                        medication.scheduled_time,
                                                    ),
                                                    'MMM d, yyyy HH:mm',
                                                )}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <Label htmlFor="dosage_given">
                                        Dosage Given{' '}
                                        <span className="text-red-500">*</span>
                                    </Label>
                                    <Input
                                        id="dosage_given"
                                        name="dosage_given"
                                        defaultValue={defaultDosageValue}
                                        placeholder="e.g., 2 (1000mg or 1g)"
                                        required
                                    />
                                    {errors.dosage_given && (
                                        <p className="mt-1 text-sm text-red-500">
                                            {errors.dosage_given}
                                        </p>
                                    )}
                                    {calculatedDosage ? (
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            Calculated from{' '}
                                            {
                                                medication.prescription.drug
                                                    ?.strength
                                            }{' '}
                                            strength
                                        </p>
                                    ) : (
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            Verify the dose matches the
                                            prescription above
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="route">
                                        Route{' '}
                                        <span className="text-red-500">*</span>
                                    </Label>
                                    <Select
                                        value={route}
                                        onValueChange={setRoute}
                                        name="route"
                                        required
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select route" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="oral">
                                                Oral (PO)
                                            </SelectItem>
                                            <SelectItem value="IV">
                                                Intravenous (IV)
                                            </SelectItem>
                                            <SelectItem value="IM">
                                                Intramuscular (IM)
                                            </SelectItem>
                                            <SelectItem value="SC">
                                                Subcutaneous (SC)
                                            </SelectItem>
                                            <SelectItem value="topical">
                                                Topical
                                            </SelectItem>
                                            <SelectItem value="rectal">
                                                Rectal (PR)
                                            </SelectItem>
                                            <SelectItem value="sublingual">
                                                Sublingual (SL)
                                            </SelectItem>
                                            <SelectItem value="inhalation">
                                                Inhalation
                                            </SelectItem>
                                            <SelectItem value="ophthalmic">
                                                Ophthalmic
                                            </SelectItem>
                                            <SelectItem value="otic">
                                                Otic
                                            </SelectItem>
                                            <SelectItem value="nasal">
                                                Nasal
                                            </SelectItem>
                                            <SelectItem value="transdermal">
                                                Transdermal
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <input
                                        type="hidden"
                                        name="route"
                                        value={route}
                                    />
                                    {errors.route && (
                                        <p className="mt-1 text-sm text-red-500">
                                            {errors.route}
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="notes">
                                        Notes (Optional)
                                    </Label>
                                    <Textarea
                                        id="notes"
                                        name="notes"
                                        placeholder="Patient response, site of injection, etc..."
                                        rows={3}
                                    />
                                    {errors.notes && (
                                        <p className="mt-1 text-sm text-red-500">
                                            {errors.notes}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => onOpenChange(false)}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="bg-green-600 hover:bg-green-700"
                                >
                                    {processing
                                        ? 'Saving...'
                                        : 'Confirm Administration'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

interface HoldMedicationDialogProps {
    medication: MedicationAdministration;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

function HoldMedicationDialog({
    medication,
    open,
    onOpenChange,
}: HoldMedicationDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <Form
                    action={`/admissions/${medication.id}/hold`}
                    method="post"
                    onSuccess={() => {
                        onOpenChange(false);
                        toast.success('Medication held');
                    }}
                    onError={() => {
                        toast.error('Failed to hold medication');
                    }}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Hold Medication</DialogTitle>
                                <DialogDescription>
                                    {medication.prescription.drug?.name ||
                                        medication.prescription.medication_name}
                                </DialogDescription>
                            </DialogHeader>

                            <div className="space-y-4">
                                <div>
                                    <Label htmlFor="notes">
                                        Reason for Holding{' '}
                                        <span className="text-red-500">*</span>
                                    </Label>
                                    <Textarea
                                        id="notes"
                                        name="notes"
                                        placeholder="e.g., Patient NPO for surgery, clinical contraindication, adverse reaction..."
                                        rows={4}
                                        required
                                    />
                                    {errors.notes && (
                                        <p className="mt-1 text-sm text-red-500">
                                            {errors.notes}
                                        </p>
                                    )}
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Minimum 10 characters required
                                    </p>
                                </div>
                            </div>

                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => onOpenChange(false)}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    variant="destructive"
                                >
                                    {processing
                                        ? 'Saving...'
                                        : 'Hold Medication'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
