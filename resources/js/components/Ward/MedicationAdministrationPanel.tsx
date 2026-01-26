import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
import { router } from '@inertiajs/react';
import { format } from 'date-fns';
import {
    AlertTriangle,
    Ban,
    CheckCircle2,
    Clock,
    Hand,
    Pill,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

// Helper to get current datetime in local format for datetime-local input
function getCurrentDateTimeLocal(): string {
    const now = new Date();
    return now.toISOString().slice(0, 16);
}

interface Drug {
    id: number;
    name: string;
    strength?: string;
    form?: string;
}

interface Prescription {
    id: number;
    medication_name: string;
    dosage?: string;
    dose_quantity?: string;
    frequency?: string;
    duration?: string;
    route?: string;
    instructions?: string;
    status?: string;
    drug?: Drug;
    discontinued_at?: string;
    completed_at?: string;
    today_administration_count?: number;
    expected_doses_per_day?: number;
    created_at?: string;
}

interface MedicationAdministration {
    id: number;
    prescription_id: number;
    status: 'given' | 'held' | 'refused' | 'omitted';
    dosage_given?: string;
    route?: string;
    notes?: string;
    administered_at: string;
    administered_by?: {
        id: number;
        name: string;
    };
}

interface Patient {
    id: number;
    first_name: string;
    last_name: string;
}

interface Bed {
    id: number;
    bed_number: string;
}

interface PatientAdmission {
    id: number;
    patient: Patient;
    bed?: Bed;
    admission_number?: string;
}

interface MedicationAdministrationPanelProps {
    admission: PatientAdmission;
    prescriptions: Prescription[];
    todayAdministrations: MedicationAdministration[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
    canEditTimestamp?: boolean;
}

export function MedicationAdministrationPanel({
    admission,
    prescriptions,
    todayAdministrations,
    open,
    onOpenChange,
    canEditTimestamp = false,
}: MedicationAdministrationPanelProps) {
    const [selectedPrescription, setSelectedPrescription] =
        useState<Prescription | null>(null);
    const [actionType, setActionType] = useState<
        'give' | 'hold' | 'refuse' | 'omit' | null
    >(null);

    // Filter active prescriptions (not discontinued or completed)
    const activePrescriptions = prescriptions.filter(
        (p) => !p.discontinued_at && !p.completed_at && p.status !== 'discontinued' && p.status !== 'completed',
    );

    // Get today's administration count for each prescription
    const getAdminCountToday = (prescriptionId: number) => {
        return todayAdministrations.filter(
            (a) => a.prescription_id === prescriptionId && a.status === 'given',
        ).length;
    };

    const handleAction = (
        prescription: Prescription,
        action: 'give' | 'hold' | 'refuse' | 'omit',
    ) => {
        setSelectedPrescription(prescription);
        setActionType(action);
    };

    const closeDialog = () => {
        setSelectedPrescription(null);
        setActionType(null);
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
                            {admission.bed?.bed_number || 'Unassigned'}
                        </SheetDescription>
                    </SheetHeader>

                    <div className="mt-6 space-y-4">
                        {activePrescriptions.length === 0 ? (
                            <div className="rounded-lg border-2 border-dashed border-muted-foreground/25 p-8 text-center">
                                <Pill className="mx-auto mb-3 h-12 w-12 text-muted-foreground/50" />
                                <p className="text-sm text-muted-foreground">
                                    No active prescriptions for this patient
                                </p>
                            </div>
                        ) : (
                            activePrescriptions.map((prescription) => {
                                const adminCount = getAdminCountToday(
                                    prescription.id,
                                );
                                const expectedDoses =
                                    prescription.expected_doses_per_day || 0;
                                const isPrn =
                                    prescription.frequency?.toUpperCase() ===
                                    'PRN';

                                return (
                                    <Card key={prescription.id}>
                                        <CardHeader className="pb-2">
                                            <div className="flex items-start justify-between">
                                                <div>
                                                    <CardTitle className="text-base">
                                                        {prescription.drug
                                                            ?.name ||
                                                            prescription.medication_name}
                                                    </CardTitle>
                                                    <p className="mt-1 text-sm text-muted-foreground">
                                                        {prescription.dose_quantity ||
                                                            prescription.dosage}{' '}
                                                        •{' '}
                                                        {prescription.frequency}{' '}
                                                        •{' '}
                                                        {prescription.duration}
                                                    </p>
                                                    {prescription.created_at && (
                                                        <p className="mt-1 text-xs">
                                                            <span className="rounded bg-blue-100 px-1.5 py-0.5 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300">
                                                                Prescribed:{' '}
                                                                {format(
                                                                    new Date(
                                                                        prescription.created_at,
                                                                    ),
                                                                    'MMM d, yyyy',
                                                                )}
                                                            </span>
                                                        </p>
                                                    )}
                                                    {prescription.instructions && (
                                                        <p className="mt-1 text-xs text-muted-foreground italic">
                                                            {
                                                                prescription.instructions
                                                            }
                                                        </p>
                                                    )}
                                                </div>
                                                <div className="text-right">
                                                    {isPrn ? (
                                                        <Badge variant="outline">
                                                            PRN
                                                        </Badge>
                                                    ) : (
                                                        <Badge
                                                            variant={
                                                                adminCount >=
                                                                expectedDoses
                                                                    ? 'default'
                                                                    : 'secondary'
                                                            }
                                                            className={
                                                                adminCount >=
                                                                expectedDoses
                                                                    ? 'bg-green-600'
                                                                    : ''
                                                            }
                                                        >
                                                            {adminCount}/
                                                            {expectedDoses}{' '}
                                                            today
                                                        </Badge>
                                                    )}
                                                </div>
                                            </div>
                                        </CardHeader>
                                        <CardContent className="pt-2">
                                            <div className="flex flex-wrap gap-2">
                                                <Button
                                                    size="sm"
                                                    className="bg-green-600 hover:bg-green-700"
                                                    onClick={() =>
                                                        handleAction(
                                                            prescription,
                                                            'give',
                                                        )
                                                    }
                                                >
                                                    <CheckCircle2 className="mr-1 h-4 w-4" />
                                                    Give
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        handleAction(
                                                            prescription,
                                                            'hold',
                                                        )
                                                    }
                                                >
                                                    <Hand className="mr-1 h-4 w-4" />
                                                    Hold
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        handleAction(
                                                            prescription,
                                                            'refuse',
                                                        )
                                                    }
                                                >
                                                    <X className="mr-1 h-4 w-4" />
                                                    Refused
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        handleAction(
                                                            prescription,
                                                            'omit',
                                                        )
                                                    }
                                                >
                                                    <Ban className="mr-1 h-4 w-4" />
                                                    Omit
                                                </Button>
                                            </div>
                                        </CardContent>
                                    </Card>
                                );
                            })
                        )}

                        {/* Today's Administration History */}
                        {todayAdministrations.length > 0 && (
                            <div className="mt-6">
                                <h3 className="mb-3 flex items-center gap-2 text-sm font-semibold text-muted-foreground">
                                    <Clock className="h-4 w-4" />
                                    Recent Administration Log
                                </h3>
                                <div className="space-y-2">
                                    {todayAdministrations.map((admin) => {
                                        const prescription = prescriptions.find(
                                            (p) =>
                                                p.id === admin.prescription_id,
                                        );
                                        const adminDate = new Date(
                                            admin.administered_at,
                                        );
                                        const isToday =
                                            adminDate.toDateString() ===
                                            new Date().toDateString();
                                        return (
                                            <div
                                                key={admin.id}
                                                className="flex items-center justify-between rounded-lg border p-3 text-sm"
                                            >
                                                <div>
                                                    <span className="font-medium">
                                                        {prescription?.drug
                                                            ?.name ||
                                                            prescription?.medication_name ||
                                                            'Unknown'}
                                                    </span>
                                                    {admin.dosage_given && (
                                                        <span className="ml-2 text-muted-foreground">
                                                            {admin.dosage_given}
                                                        </span>
                                                    )}
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <StatusBadge
                                                        status={admin.status}
                                                    />
                                                    <span className="text-xs text-muted-foreground">
                                                        {isToday
                                                            ? format(
                                                                  adminDate,
                                                                  'HH:mm',
                                                              )
                                                            : format(
                                                                  adminDate,
                                                                  'MMM d, HH:mm',
                                                              )}
                                                    </span>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        )}
                    </div>
                </SheetContent>
            </Sheet>

            {/* Action Dialogs */}
            {selectedPrescription && actionType === 'give' && (
                <GiveMedicationDialog
                    admission={admission}
                    prescription={selectedPrescription}
                    open={true}
                    onClose={closeDialog}
                    canEditTimestamp={canEditTimestamp}
                />
            )}
            {selectedPrescription && actionType === 'hold' && (
                <HoldMedicationDialog
                    admission={admission}
                    prescription={selectedPrescription}
                    open={true}
                    onClose={closeDialog}
                    canEditTimestamp={canEditTimestamp}
                />
            )}
            {selectedPrescription && actionType === 'refuse' && (
                <RefuseMedicationDialog
                    admission={admission}
                    prescription={selectedPrescription}
                    open={true}
                    onClose={closeDialog}
                    canEditTimestamp={canEditTimestamp}
                />
            )}
            {selectedPrescription && actionType === 'omit' && (
                <OmitMedicationDialog
                    admission={admission}
                    prescription={selectedPrescription}
                    open={true}
                    onClose={closeDialog}
                    canEditTimestamp={canEditTimestamp}
                />
            )}
        </>
    );
}

function StatusBadge({ status }: { status: string }) {
    const variants: Record<
        string,
        {
            variant: 'default' | 'secondary' | 'destructive' | 'outline';
            className?: string;
        }
    > = {
        given: { variant: 'default', className: 'bg-green-600' },
        held: { variant: 'secondary' },
        refused: { variant: 'destructive' },
        omitted: { variant: 'outline' },
    };
    const config = variants[status] || { variant: 'outline' as const };
    return (
        <Badge variant={config.variant} className={config.className}>
            {status}
        </Badge>
    );
}

interface ActionDialogProps {
    admission: PatientAdmission;
    prescription: Prescription;
    open: boolean;
    onClose: () => void;
    canEditTimestamp?: boolean;
}

function GiveMedicationDialog({
    admission,
    prescription,
    open,
    onClose,
    canEditTimestamp = false,
}: ActionDialogProps) {
    const [dosageGiven, setDosageGiven] = useState(
        prescription.dose_quantity || prescription.dosage || '',
    );
    const [route, setRoute] = useState(prescription.route || 'oral');
    const [notes, setNotes] = useState('');
    const [useCustomTime, setUseCustomTime] = useState(false);
    const [administeredAt, setAdministeredAt] = useState(
        getCurrentDateTimeLocal(),
    );
    const [submitting, setSubmitting] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setSubmitting(true);

        router.post(
            `/admissions/${admission.id}/medications`,
            {
                prescription_id: prescription.id,
                dosage_given: dosageGiven,
                route,
                notes: notes || undefined,
                administered_at:
                    canEditTimestamp && useCustomTime
                        ? administeredAt
                        : undefined,
            },
            {
                onSuccess: () => {
                    toast.success('Medication recorded as given');
                    onClose();
                },
                onError: (errors) => {
                    toast.error(
                        (Object.values(errors)[0] as string) ||
                            'Failed to record medication',
                    );
                },
                onFinish: () => setSubmitting(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent>
                <form onSubmit={handleSubmit}>
                    <DialogHeader>
                        <DialogTitle>Give Medication</DialogTitle>
                        <DialogDescription>
                            {prescription.drug?.name ||
                                prescription.medication_name}
                            {(prescription.dose_quantity ||
                                prescription.dosage) &&
                                ` - ${prescription.dose_quantity || prescription.dosage}`}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4 py-4">
                        <div>
                            <Label htmlFor="dosage_given">Dosage Given *</Label>
                            <Input
                                id="dosage_given"
                                value={dosageGiven}
                                onChange={(e) => setDosageGiven(e.target.value)}
                                placeholder="e.g., 2 tablets, 500mg"
                                required
                            />
                        </div>

                        <div>
                            <Label htmlFor="route">Route *</Label>
                            <Select value={route} onValueChange={setRoute}>
                                <SelectTrigger>
                                    <SelectValue />
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
                                    <SelectItem value="sublingual">
                                        Sublingual
                                    </SelectItem>
                                    <SelectItem value="rectal">
                                        Rectal
                                    </SelectItem>
                                    <SelectItem value="inhaled">
                                        Inhaled
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div>
                            <Label htmlFor="notes">Notes (optional)</Label>
                            <Textarea
                                id="notes"
                                value={notes}
                                onChange={(e) => setNotes(e.target.value)}
                                placeholder="Any observations or notes..."
                                rows={2}
                            />
                        </div>

                        {/* Custom date/time option - only shown if user has permission */}
                        {canEditTimestamp && (
                            <div className="space-y-3 rounded-lg border bg-muted/30 p-3">
                                <div className="flex items-center space-x-2">
                                    <Checkbox
                                        id="use_custom_time"
                                        checked={useCustomTime}
                                        onCheckedChange={(checked) =>
                                            setUseCustomTime(checked === true)
                                        }
                                    />
                                    <Label
                                        htmlFor="use_custom_time"
                                        className="cursor-pointer text-sm font-normal"
                                    >
                                        Record for a different date/time
                                    </Label>
                                </div>
                                {useCustomTime && (
                                    <div>
                                        <Label
                                            htmlFor="administered_at"
                                            className="text-xs text-muted-foreground"
                                        >
                                            When was this medication given?
                                        </Label>
                                        <Input
                                            id="administered_at"
                                            type="datetime-local"
                                            value={administeredAt}
                                            onChange={(e) =>
                                                setAdministeredAt(
                                                    e.target.value,
                                                )
                                            }
                                            className="mt-1"
                                        />
                                    </div>
                                )}
                            </div>
                        )}
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={submitting}
                            className="bg-green-600 hover:bg-green-700"
                        >
                            {submitting ? 'Recording...' : 'Record Given'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function HoldMedicationDialog({
    admission,
    prescription,
    open,
    onClose,
    canEditTimestamp = false,
}: ActionDialogProps) {
    const [notes, setNotes] = useState('');
    const [useCustomTime, setUseCustomTime] = useState(false);
    const [administeredAt, setAdministeredAt] = useState(
        getCurrentDateTimeLocal(),
    );
    const [submitting, setSubmitting] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setSubmitting(true);

        router.post(
            `/admissions/${admission.id}/medications/hold`,
            {
                prescription_id: prescription.id,
                notes,
                administered_at:
                    canEditTimestamp && useCustomTime
                        ? administeredAt
                        : undefined,
            },
            {
                onSuccess: () => {
                    toast.success('Medication recorded as held');
                    onClose();
                },
                onError: (errors) => {
                    toast.error(
                        (Object.values(errors)[0] as string) ||
                            'Failed to record',
                    );
                },
                onFinish: () => setSubmitting(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent>
                <form onSubmit={handleSubmit}>
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <AlertTriangle className="h-5 w-5 text-yellow-500" />
                            Hold Medication
                        </DialogTitle>
                        <DialogDescription>
                            {prescription.drug?.name ||
                                prescription.medication_name}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4 py-4">
                        <div>
                            <Label htmlFor="notes">Reason for holding *</Label>
                            <Textarea
                                id="notes"
                                value={notes}
                                onChange={(e) => setNotes(e.target.value)}
                                placeholder="e.g., Patient NPO for surgery, Low blood pressure..."
                                rows={3}
                                required
                                minLength={10}
                            />
                            <p className="mt-1 text-xs text-muted-foreground">
                                Minimum 10 characters required
                            </p>
                        </div>

                        {/* Custom date/time option - only shown if user has permission */}
                        {canEditTimestamp && (
                            <div className="space-y-3 rounded-lg border bg-muted/30 p-3">
                                <div className="flex items-center space-x-2">
                                    <Checkbox
                                        id="hold_use_custom_time"
                                        checked={useCustomTime}
                                        onCheckedChange={(checked) =>
                                            setUseCustomTime(checked === true)
                                        }
                                    />
                                    <Label
                                        htmlFor="hold_use_custom_time"
                                        className="cursor-pointer text-sm font-normal"
                                    >
                                        Record for a different date/time
                                    </Label>
                                </div>
                                {useCustomTime && (
                                    <div>
                                        <Label
                                            htmlFor="hold_administered_at"
                                            className="text-xs text-muted-foreground"
                                        >
                                            When was this medication held?
                                        </Label>
                                        <Input
                                            id="hold_administered_at"
                                            type="datetime-local"
                                            value={administeredAt}
                                            onChange={(e) =>
                                                setAdministeredAt(
                                                    e.target.value,
                                                )
                                            }
                                            className="mt-1"
                                        />
                                    </div>
                                )}
                            </div>
                        )}
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={submitting || notes.length < 10}
                        >
                            {submitting ? 'Recording...' : 'Record Held'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function RefuseMedicationDialog({
    admission,
    prescription,
    open,
    onClose,
    canEditTimestamp = false,
}: ActionDialogProps) {
    const [notes, setNotes] = useState('');
    const [useCustomTime, setUseCustomTime] = useState(false);
    const [administeredAt, setAdministeredAt] = useState(
        getCurrentDateTimeLocal(),
    );
    const [submitting, setSubmitting] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setSubmitting(true);

        router.post(
            `/admissions/${admission.id}/medications/refuse`,
            {
                prescription_id: prescription.id,
                notes,
                administered_at:
                    canEditTimestamp && useCustomTime
                        ? administeredAt
                        : undefined,
            },
            {
                onSuccess: () => {
                    toast.success('Medication recorded as refused');
                    onClose();
                },
                onError: (errors) => {
                    toast.error(
                        (Object.values(errors)[0] as string) ||
                            'Failed to record',
                    );
                },
                onFinish: () => setSubmitting(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent>
                <form onSubmit={handleSubmit}>
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <X className="h-5 w-5 text-red-500" />
                            Patient Refused Medication
                        </DialogTitle>
                        <DialogDescription>
                            {prescription.drug?.name ||
                                prescription.medication_name}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4 py-4">
                        <div>
                            <Label htmlFor="notes">Reason (optional)</Label>
                            <Textarea
                                id="notes"
                                value={notes}
                                onChange={(e) => setNotes(e.target.value)}
                                placeholder="e.g., Patient states medication causes nausea..."
                                rows={3}
                            />
                        </div>

                        {/* Custom date/time option - only shown if user has permission */}
                        {canEditTimestamp && (
                            <div className="space-y-3 rounded-lg border bg-muted/30 p-3">
                                <div className="flex items-center space-x-2">
                                    <Checkbox
                                        id="refuse_use_custom_time"
                                        checked={useCustomTime}
                                        onCheckedChange={(checked) =>
                                            setUseCustomTime(checked === true)
                                        }
                                    />
                                    <Label
                                        htmlFor="refuse_use_custom_time"
                                        className="cursor-pointer text-sm font-normal"
                                    >
                                        Record for a different date/time
                                    </Label>
                                </div>
                                {useCustomTime && (
                                    <div>
                                        <Label
                                            htmlFor="refuse_administered_at"
                                            className="text-xs text-muted-foreground"
                                        >
                                            When did the patient refuse?
                                        </Label>
                                        <Input
                                            id="refuse_administered_at"
                                            type="datetime-local"
                                            value={administeredAt}
                                            onChange={(e) =>
                                                setAdministeredAt(
                                                    e.target.value,
                                                )
                                            }
                                            className="mt-1"
                                        />
                                    </div>
                                )}
                            </div>
                        )}
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={submitting}
                            variant="destructive"
                        >
                            {submitting ? 'Recording...' : 'Record Refused'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function OmitMedicationDialog({
    admission,
    prescription,
    open,
    onClose,
    canEditTimestamp = false,
}: ActionDialogProps) {
    const [notes, setNotes] = useState('');
    const [useCustomTime, setUseCustomTime] = useState(false);
    const [administeredAt, setAdministeredAt] = useState(
        getCurrentDateTimeLocal(),
    );
    const [submitting, setSubmitting] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setSubmitting(true);

        router.post(
            `/admissions/${admission.id}/medications/omit`,
            {
                prescription_id: prescription.id,
                notes,
                administered_at:
                    canEditTimestamp && useCustomTime
                        ? administeredAt
                        : undefined,
            },
            {
                onSuccess: () => {
                    toast.success('Medication recorded as omitted');
                    onClose();
                },
                onError: (errors) => {
                    toast.error(
                        (Object.values(errors)[0] as string) ||
                            'Failed to record',
                    );
                },
                onFinish: () => setSubmitting(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent>
                <form onSubmit={handleSubmit}>
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Ban className="h-5 w-5 text-gray-500" />
                            Omit Medication
                        </DialogTitle>
                        <DialogDescription>
                            {prescription.drug?.name ||
                                prescription.medication_name}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4 py-4">
                        <div>
                            <Label htmlFor="notes">Reason *</Label>
                            <Textarea
                                id="notes"
                                value={notes}
                                onChange={(e) => setNotes(e.target.value)}
                                placeholder="e.g., Medication not available, Patient discharged..."
                                rows={3}
                                required
                                minLength={10}
                            />
                            <p className="mt-1 text-xs text-muted-foreground">
                                Minimum 10 characters required
                            </p>
                        </div>

                        {/* Custom date/time option - only shown if user has permission */}
                        {canEditTimestamp && (
                            <div className="space-y-3 rounded-lg border bg-muted/30 p-3">
                                <div className="flex items-center space-x-2">
                                    <Checkbox
                                        id="omit_use_custom_time"
                                        checked={useCustomTime}
                                        onCheckedChange={(checked) =>
                                            setUseCustomTime(checked === true)
                                        }
                                    />
                                    <Label
                                        htmlFor="omit_use_custom_time"
                                        className="cursor-pointer text-sm font-normal"
                                    >
                                        Record for a different date/time
                                    </Label>
                                </div>
                                {useCustomTime && (
                                    <div>
                                        <Label
                                            htmlFor="omit_administered_at"
                                            className="text-xs text-muted-foreground"
                                        >
                                            When was this medication omitted?
                                        </Label>
                                        <Input
                                            id="omit_administered_at"
                                            type="datetime-local"
                                            value={administeredAt}
                                            onChange={(e) =>
                                                setAdministeredAt(
                                                    e.target.value,
                                                )
                                            }
                                            className="mt-1"
                                        />
                                    </div>
                                )}
                            </div>
                        )}
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={submitting || notes.length < 10}
                        >
                            {submitting ? 'Recording...' : 'Record Omitted'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
