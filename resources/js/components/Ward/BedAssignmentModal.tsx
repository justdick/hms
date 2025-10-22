import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Form } from '@inertiajs/react';
import { AlertCircle, Bed, Info } from 'lucide-react';
import { useState } from 'react';

interface Patient {
    id: number;
    first_name: string;
    last_name: string;
}

interface BedType {
    id: number;
    bed_number: string;
    status: string;
    type: string;
    currentAdmission?: {
        patient: Patient;
    };
}

interface PatientAdmission {
    id: number;
    bed_id?: number;
    is_overflow_patient?: boolean;
}

interface Props {
    open: boolean;
    onClose: () => void;
    admission: PatientAdmission;
    availableBeds: BedType[];
    allBeds?: BedType[];
    hasAvailableBeds: boolean;
    isChangingBed?: boolean;
}

export function BedAssignmentModal({
    open,
    onClose,
    admission,
    availableBeds,
    allBeds = [],
    hasAvailableBeds,
    isChangingBed = false,
}: Props) {
    const [selectedBedId, setSelectedBedId] = useState<string>('');
    const [markAsOverflow, setMarkAsOverflow] = useState(false);
    const [notes, setNotes] = useState('');

    const actionUrl = `/admissions/${admission.id}/bed-assignment`;
    const method = isChangingBed ? 'put' : 'post';

    const occupiedBedsCount = allBeds.length - availableBeds.length;

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {isChangingBed ? 'Change Bed Assignment' : 'Assign Bed'}
                    </DialogTitle>
                    <DialogDescription>
                        {hasAvailableBeds
                            ? 'Select an available bed to assign to this patient.'
                            : 'No beds are currently available. You can mark the patient as overflow and assign a bed later.'}
                    </DialogDescription>
                </DialogHeader>

                <Form
                    action={actionUrl}
                    method={method}
                    resetOnSuccess
                    onSuccess={onClose}
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="space-y-4 py-4">
                                {hasAvailableBeds ? (
                                    <>
                                        <div className="space-y-2">
                                            <Label htmlFor="bed_id">
                                                Select Bed
                                            </Label>
                                            <Select
                                                value={selectedBedId}
                                                onValueChange={setSelectedBedId}
                                            >
                                                <SelectTrigger id="bed_id">
                                                    <SelectValue placeholder="Choose an available bed..." />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {availableBeds.map(
                                                        (bed) => (
                                                            <SelectItem
                                                                key={bed.id}
                                                                value={bed.id.toString()}
                                                            >
                                                                <div className="flex items-center gap-2">
                                                                    <Bed className="h-4 w-4" />
                                                                    <span>
                                                                        Bed{' '}
                                                                        {
                                                                            bed.bed_number
                                                                        }
                                                                    </span>
                                                                    <Badge
                                                                        variant="outline"
                                                                        className="ml-2"
                                                                    >
                                                                        {
                                                                            bed.type
                                                                        }
                                                                    </Badge>
                                                                </div>
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                            <input
                                                type="hidden"
                                                name="bed_id"
                                                value={selectedBedId}
                                            />
                                            {errors.bed_id && (
                                                <p className="text-sm text-red-600 dark:text-red-400">
                                                    {errors.bed_id}
                                                </p>
                                            )}
                                        </div>

                                        {/* Bed Stats */}
                                        <div className="rounded-md border border-blue-200 bg-blue-50 p-3 dark:border-blue-900 dark:bg-blue-950/20">
                                            <div className="flex items-start gap-2">
                                                <Info className="mt-0.5 h-4 w-4 text-blue-600 dark:text-blue-400" />
                                                <div className="text-sm text-blue-900 dark:text-blue-100">
                                                    <p className="font-medium">
                                                        Ward Bed Status
                                                    </p>
                                                    <p className="mt-1 text-blue-700 dark:text-blue-300">
                                                        {availableBeds.length}{' '}
                                                        available,{' '}
                                                        {occupiedBedsCount}{' '}
                                                        occupied
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </>
                                ) : (
                                    <div className="rounded-lg border-2 border-dashed border-orange-300 bg-orange-50 p-6 text-center dark:border-orange-900 dark:bg-orange-950/20">
                                        <AlertCircle className="mx-auto mb-3 h-12 w-12 text-orange-500" />
                                        <h3 className="mb-2 text-lg font-semibold text-orange-900 dark:text-orange-100">
                                            No Beds Available
                                        </h3>
                                        <p className="mb-4 text-sm text-orange-700 dark:text-orange-300">
                                            All {allBeds.length} beds in this
                                            ward are currently occupied. The
                                            patient can still be admitted and
                                            will be marked as overflow until a
                                            bed becomes available.
                                        </p>
                                        <div className="flex items-center justify-center gap-2">
                                            <input
                                                type="checkbox"
                                                id="mark_as_overflow"
                                                name="mark_as_overflow"
                                                checked={markAsOverflow}
                                                onChange={(e) =>
                                                    setMarkAsOverflow(
                                                        e.target.checked,
                                                    )
                                                }
                                                className="h-4 w-4 rounded border-gray-300"
                                            />
                                            <Label htmlFor="mark_as_overflow">
                                                Mark patient as overflow
                                            </Label>
                                        </div>
                                    </div>
                                )}

                                <div className="space-y-2">
                                    <Label htmlFor="notes">
                                        Notes (Optional)
                                    </Label>
                                    <Textarea
                                        id="notes"
                                        name="notes"
                                        placeholder="Add any relevant notes about the bed assignment..."
                                        value={notes}
                                        onChange={(e) =>
                                            setNotes(e.target.value)
                                        }
                                        rows={3}
                                    />
                                </div>
                            </div>

                            <DialogFooter>
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
                                    disabled={
                                        processing ||
                                        (!selectedBedId &&
                                            !markAsOverflow &&
                                            !hasAvailableBeds)
                                    }
                                >
                                    {processing
                                        ? isChangingBed
                                            ? 'Changing Bed...'
                                            : 'Assigning Bed...'
                                        : isChangingBed
                                          ? 'Change Bed'
                                          : 'Assign Bed'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
