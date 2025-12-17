import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import { router } from '@inertiajs/react';
import { Calendar, Pill, RefreshCw, User } from 'lucide-react';
import { useMemo, useState } from 'react';

interface Drug {
    id: number;
    name: string;
    form: string;
    strength?: string;
    unit_type: string;
    bottle_size?: number;
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
    open: boolean;
    onOpenChange: (open: boolean) => void;
    consultationId: number;
    previousPrescriptions: PreviousPrescription[];
}

interface GroupedVisit {
    consultationId: number;
    date: string;
    doctorName: string;
    departmentName: string;
    prescriptions: PreviousPrescription[];
}

export function RefillPrescriptionModal({
    open,
    onOpenChange,
    consultationId,
    previousPrescriptions,
}: Props) {
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [isSubmitting, setIsSubmitting] = useState(false);

    // Group prescriptions by consultation (visit) and limit to last 5 visits
    const groupedVisits = useMemo(() => {
        const visitMap = new Map<number, GroupedVisit>();

        previousPrescriptions.forEach((prescription) => {
            const consultId = prescription.consultation.id;
            if (!visitMap.has(consultId)) {
                visitMap.set(consultId, {
                    consultationId: consultId,
                    date: prescription.consultation.started_at,
                    doctorName: prescription.consultation.doctor.name,
                    departmentName: prescription.consultation.patient_checkin.department.name,
                    prescriptions: [],
                });
            }
            visitMap.get(consultId)!.prescriptions.push(prescription);
        });

        // Sort by date descending and take last 5 visits
        return Array.from(visitMap.values())
            .sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime())
            .slice(0, 5);
    }, [previousPrescriptions]);

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    };

    const handleToggle = (prescriptionId: number) => {
        setSelectedIds((prev) =>
            prev.includes(prescriptionId)
                ? prev.filter((id) => id !== prescriptionId)
                : [...prev, prescriptionId]
        );
    };

    const handleToggleVisit = (visit: GroupedVisit) => {
        const visitPrescriptionIds = visit.prescriptions.map((p) => p.id);
        const allSelected = visitPrescriptionIds.every((id) => selectedIds.includes(id));

        if (allSelected) {
            setSelectedIds((prev) => prev.filter((id) => !visitPrescriptionIds.includes(id)));
        } else {
            setSelectedIds((prev) => [...new Set([...prev, ...visitPrescriptionIds])]);
        }
    };

    const handleRefill = () => {
        if (selectedIds.length === 0) return;

        setIsSubmitting(true);
        router.post(
            `/consultation/${consultationId}/prescriptions/refill`,
            { prescription_ids: selectedIds },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSelectedIds([]);
                    onOpenChange(false);
                },
                onFinish: () => {
                    setIsSubmitting(false);
                },
            }
        );
    };

    const handleClose = () => {
        setSelectedIds([]);
        onOpenChange(false);
    };

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <RefreshCw className="h-5 w-5" />
                        Refill from Previous Prescriptions
                    </DialogTitle>
                    <DialogDescription>
                        Select prescriptions from previous visits to refill. Last 5 visits are shown.
                    </DialogDescription>
                </DialogHeader>

                <ScrollArea className="max-h-[60vh]">
                    {groupedVisits.length > 0 ? (
                        <div className="space-y-6 pr-4">
                            {groupedVisits.map((visit) => {
                                const visitPrescriptionIds = visit.prescriptions.map((p) => p.id);
                                const allSelected = visitPrescriptionIds.every((id) =>
                                    selectedIds.includes(id)
                                );
                                const someSelected =
                                    !allSelected &&
                                    visitPrescriptionIds.some((id) => selectedIds.includes(id));

                                return (
                                    <div
                                        key={visit.consultationId}
                                        className="rounded-lg border bg-gray-50 dark:bg-gray-900"
                                    >
                                        {/* Visit Header */}
                                        <div
                                            className="flex cursor-pointer items-center gap-3 border-b p-3 hover:bg-gray-100 dark:hover:bg-gray-800"
                                            onClick={() => handleToggleVisit(visit)}
                                        >
                                            <Checkbox
                                                checked={allSelected}
                                                ref={(el) => {
                                                    if (el) {
                                                        (el as HTMLButtonElement & { indeterminate: boolean }).indeterminate = someSelected;
                                                    }
                                                }}
                                                onCheckedChange={() => handleToggleVisit(visit)}
                                            />
                                            <div className="flex flex-1 items-center gap-4 text-sm">
                                                <div className="flex items-center gap-1.5 font-medium">
                                                    <Calendar className="h-4 w-4 text-blue-600" />
                                                    {formatDate(visit.date)}
                                                </div>
                                                <div className="flex items-center gap-1.5 text-gray-600 dark:text-gray-400">
                                                    <User className="h-4 w-4" />
                                                    Dr. {visit.doctorName}
                                                </div>
                                                <Badge variant="outline" className="text-xs">
                                                    {visit.departmentName}
                                                </Badge>
                                            </div>
                                            <span className="text-xs text-gray-500">
                                                {visit.prescriptions.length} prescription
                                                {visit.prescriptions.length !== 1 ? 's' : ''}
                                            </span>
                                        </div>

                                        {/* Prescriptions */}
                                        <div className="divide-y">
                                            {visit.prescriptions.map((prescription) => (
                                                <label
                                                    key={prescription.id}
                                                    className="flex cursor-pointer items-start gap-3 p-3 hover:bg-gray-100 dark:hover:bg-gray-800"
                                                >
                                                    <Checkbox
                                                        checked={selectedIds.includes(prescription.id)}
                                                        onCheckedChange={() => handleToggle(prescription.id)}
                                                        className="mt-0.5"
                                                    />
                                                    <div className="flex-1 space-y-1">
                                                        <div className="flex items-center gap-2">
                                                            <Pill className="h-4 w-4 text-green-600" />
                                                            <span className="font-medium">
                                                                {prescription.medication_name}
                                                            </span>
                                                            {prescription.drug?.strength && (
                                                                <span className="text-sm text-gray-500">
                                                                    {prescription.drug.strength}
                                                                </span>
                                                            )}
                                                            {prescription.drug?.form && (
                                                                <Badge variant="secondary" className="text-xs">
                                                                    {prescription.drug.form}
                                                                </Badge>
                                                            )}
                                                        </div>
                                                        <div className="flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-600 dark:text-gray-400">
                                                            {prescription.dose_quantity && (
                                                                <span>Dose: {prescription.dose_quantity}</span>
                                                            )}
                                                            <span>Freq: {prescription.frequency}</span>
                                                            <span>Duration: {prescription.duration}</span>
                                                        </div>
                                                        {prescription.instructions && (
                                                            <p className="text-xs text-gray-500 italic">
                                                                {prescription.instructions}
                                                            </p>
                                                        )}
                                                    </div>
                                                </label>
                                            ))}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    ) : (
                        <div className="py-12 text-center text-gray-500">
                            <Pill className="mx-auto mb-4 h-12 w-12 text-gray-300" />
                            <p>No previous prescriptions found</p>
                            <p className="mt-1 text-sm">
                                This patient has no prescription history from previous visits.
                            </p>
                        </div>
                    )}
                </ScrollArea>

                <DialogFooter className="gap-2 sm:gap-0">
                    <Button variant="outline" onClick={handleClose} disabled={isSubmitting}>
                        Cancel
                    </Button>
                    <Button
                        onClick={handleRefill}
                        disabled={selectedIds.length === 0 || isSubmitting}
                    >
                        <RefreshCw className={`mr-2 h-4 w-4 ${isSubmitting ? 'animate-spin' : ''}`} />
                        {isSubmitting
                            ? 'Refilling...'
                            : `Refill ${selectedIds.length} Selected`}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
