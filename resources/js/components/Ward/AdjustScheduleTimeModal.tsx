import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { router } from '@inertiajs/react';
import { ArrowRight, Clock, Loader2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

interface Drug {
    id: number;
    name: string;
    strength?: string;
}

interface Prescription {
    id: number;
    drug?: Drug;
    medication_name: string;
}

interface MedicationAdministration {
    id: number;
    scheduled_time: string;
    prescription: Prescription;
    dosage_given?: string;
    route?: string;
}

interface AdjustScheduleTimeModalProps {
    administration: MedicationAdministration | null;
    isOpen: boolean;
    onClose: () => void;
}

export function AdjustScheduleTimeModal({
    administration,
    isOpen,
    onClose,
}: AdjustScheduleTimeModalProps) {
    const [newTime, setNewTime] = useState('');
    const [reason, setReason] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const handleSubmit = () => {
        if (!administration || !newTime) {
            toast.error('Please select a new time');
            return;
        }

        // Validate that new time is in the future
        const scheduledDateTime = new Date(
            `${new Date().toISOString().split('T')[0]}T${newTime}`,
        );
        const now = new Date();

        if (scheduledDateTime <= now) {
            toast.error('New time must be in the future');
            return;
        }

        setSubmitting(true);

        router.patch(
            `/api/medication-administrations/${administration.id}/adjust-time`,
            {
                scheduled_time: scheduledDateTime.toISOString(),
                reason: reason || undefined,
            },
            {
                onSuccess: () => {
                    toast.success('Schedule time adjusted successfully');
                    setNewTime('');
                    setReason('');
                    onClose();
                },
                onError: (errors) => {
                    toast.error(
                        errors.scheduled_time || 'Failed to adjust time',
                    );
                },
                onFinish: () => {
                    setSubmitting(false);
                },
            },
        );
    };

    const formatTime = (dateString: string) => {
        return new Date(dateString).toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: true,
        });
    };

    if (!administration) return null;

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>Adjust Medication Time</DialogTitle>
                    <DialogDescription>
                        {administration.prescription.drug?.name ||
                            administration.prescription.medication_name}
                        {administration.dosage_given &&
                            ` - ${administration.dosage_given}`}
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    {/* Current vs New Time Display */}
                    <div className="flex items-center justify-center gap-4 rounded-lg border bg-muted/50 p-4 dark:bg-muted/20">
                        <div className="text-center">
                            <p className="text-xs text-muted-foreground">
                                Current Time
                            </p>
                            <p className="text-lg font-semibold">
                                {formatTime(administration.scheduled_time)}
                            </p>
                        </div>
                        <ArrowRight className="h-5 w-5 text-muted-foreground" />
                        <div className="text-center">
                            <p className="text-xs text-muted-foreground">
                                New Time
                            </p>
                            <p className="text-lg font-semibold">
                                {newTime
                                    ? new Date(
                                          `${new Date().toISOString().split('T')[0]}T${newTime}`,
                                      ).toLocaleTimeString('en-US', {
                                          hour: '2-digit',
                                          minute: '2-digit',
                                          hour12: true,
                                      })
                                    : '--:--'}
                            </p>
                        </div>
                    </div>

                    {/* New Time Input */}
                    <div className="space-y-2">
                        <Label htmlFor="new-time">
                            New Scheduled Time{' '}
                            <span className="text-red-500">*</span>
                        </Label>
                        <div className="relative">
                            <Clock className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                id="new-time"
                                type="time"
                                value={newTime}
                                onChange={(e) => setNewTime(e.target.value)}
                                className="pl-10"
                                required
                            />
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Must be a future time
                        </p>
                    </div>

                    {/* Reason (Optional) */}
                    <div className="space-y-2">
                        <Label htmlFor="reason">Reason (Optional)</Label>
                        <Textarea
                            id="reason"
                            value={reason}
                            onChange={(e) => setReason(e.target.value)}
                            placeholder="Enter reason for time adjustment..."
                            rows={3}
                        />
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
                        <Button onClick={handleSubmit} disabled={submitting}>
                            {submitting && (
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            )}
                            Adjust Time
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
