import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { router } from '@inertiajs/react';
import { AlertTriangle, Loader2, XCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

interface Drug {
    id: number;
    name: string;
    strength?: string;
}

interface MedicationAdministration {
    id: number;
    scheduled_time: string;
    status: string;
}

interface Prescription {
    id: number;
    drug?: Drug;
    medication_name: string;
    frequency?: string;
    duration?: string;
    medication_administrations?: MedicationAdministration[];
}

interface DiscontinueMedicationModalProps {
    prescription: Prescription | null;
    isOpen: boolean;
    onClose: () => void;
}

export function DiscontinueMedicationModal({
    prescription,
    isOpen,
    onClose,
}: DiscontinueMedicationModalProps) {
    const [reason, setReason] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [futureDoses, setFutureDoses] = useState<MedicationAdministration[]>(
        [],
    );

    useEffect(() => {
        if (isOpen && prescription?.medication_administrations) {
            const now = new Date();
            const future = prescription.medication_administrations.filter(
                (admin) =>
                    admin.status === 'scheduled' &&
                    new Date(admin.scheduled_time) > now,
            );
            setFutureDoses(future);
        }
    }, [isOpen, prescription]);

    const handleSubmit = () => {
        if (!prescription) return;

        if (!reason.trim()) {
            toast.error('Please provide a reason for discontinuation');
            return;
        }

        setSubmitting(true);

        router.post(
            `/api/prescriptions/${prescription.id}/discontinue`,
            { reason },
            {
                onSuccess: () => {
                    toast.success('Medication discontinued successfully');
                    setReason('');
                    onClose();
                },
                onError: (errors) => {
                    toast.error(errors.reason || 'Failed to discontinue medication');
                },
                onFinish: () => {
                    setSubmitting(false);
                },
            },
        );
    };

    const formatDateTime = (dateString: string) => {
        return new Date(dateString).toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true,
        });
    };

    if (!prescription) return null;

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <XCircle className="h-5 w-5 text-red-500" />
                        Discontinue Medication
                    </DialogTitle>
                    <DialogDescription>
                        {prescription.drug?.name || prescription.medication_name}
                        {prescription.drug?.strength &&
                            ` ${prescription.drug.strength}`}
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    {/* Warning Message */}
                    <div className="flex gap-3 rounded-lg border border-orange-300 bg-orange-50 p-4 dark:border-orange-800 dark:bg-orange-950/20">
                        <AlertTriangle className="h-5 w-5 shrink-0 text-orange-600 dark:text-orange-500" />
                        <div className="space-y-1 text-sm">
                            <p className="font-medium text-orange-900 dark:text-orange-200">
                                This action will cancel all future scheduled
                                doses
                            </p>
                            <p className="text-orange-700 dark:text-orange-300">
                                Doses that have already been given will be
                                preserved in the patient's record.
                            </p>
                        </div>
                    </div>

                    {/* Future Doses to be Cancelled */}
                    {futureDoses.length > 0 && (
                        <div className="space-y-2">
                            <Label className="text-sm font-medium">
                                Doses to be Cancelled ({futureDoses.length})
                            </Label>
                            <div className="max-h-32 space-y-1 overflow-y-auto rounded-lg border bg-muted/50 p-3 dark:bg-muted/20">
                                {futureDoses.slice(0, 5).map((dose) => (
                                    <p
                                        key={dose.id}
                                        className="text-sm text-muted-foreground"
                                    >
                                        • {formatDateTime(dose.scheduled_time)}
                                    </p>
                                ))}
                                {futureDoses.length > 5 && (
                                    <p className="text-sm italic text-muted-foreground">
                                        ... and {futureDoses.length - 5} more
                                    </p>
                                )}
                            </div>
                        </div>
                    )}

                    {/* Reason (Required) */}
                    <div className="space-y-2">
                        <Label htmlFor="reason">
                            Reason for Discontinuation{' '}
                            <span className="text-red-500">*</span>
                        </Label>
                        <Textarea
                            id="reason"
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                            placeholder="Enter reason for discontinuing this medication..."
                            rows={4}
                            required
                        />
                        <p className="text-xs text-muted-foreground">
                            This will be recorded in the patient's medical
                            record
                        </p>
                    </div>

                    {/* Action Buttons */}
                    <div className="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Cancel
                        </Button>
                        <Button
                            onClick={handleSubmit}
                            disabled={submitting || !reason.trim()}
                            variant="destructive"
                        >
                            {submitting && (
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            )}
                            <XCircle className="mr-2 h-4 w-4" />
                            Discontinue Medication
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
