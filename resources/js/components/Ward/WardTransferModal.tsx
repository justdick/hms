import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { router } from '@inertiajs/react';
import { ArrowRightLeft, Hospital } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

interface Ward {
    id: number;
    name: string;
    code: string;
    available_beds: number;
}

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    admissionId: number;
    currentWardName: string;
    patientName: string;
    availableWards: Ward[];
}

export function WardTransferModal({
    open,
    onOpenChange,
    admissionId,
    currentWardName,
    patientName,
    availableWards,
}: Props) {
    const [selectedWardId, setSelectedWardId] = useState<string>('');
    const [transferReason, setTransferReason] = useState('');
    const [transferNotes, setTransferNotes] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);

    const selectedWard = availableWards.find(
        (w) => w.id.toString() === selectedWardId,
    );

    const handleSubmit = () => {
        if (!selectedWardId) {
            toast.error('Please select a destination ward');
            return;
        }
        if (!transferReason.trim()) {
            toast.error('Please provide a transfer reason');
            return;
        }

        setIsSubmitting(true);
        router.post(
            `/admissions/${admissionId}/transfer`,
            {
                to_ward_id: selectedWardId,
                transfer_reason: transferReason,
                transfer_notes: transferNotes || null,
            },
            {
                onSuccess: () => {
                    toast.success(
                        `Patient transferred to ${selectedWard?.name} successfully`,
                    );
                    handleClose();
                },
                onError: (errors: any) => {
                    const errorMessage =
                        errors.to_ward_id ||
                        errors.transfer_reason ||
                        errors.error ||
                        'Failed to transfer patient';
                    toast.error(errorMessage);
                },
                onFinish: () => {
                    setIsSubmitting(false);
                },
            },
        );
    };

    const handleClose = () => {
        setSelectedWardId('');
        setTransferReason('');
        setTransferNotes('');
        onOpenChange(false);
    };

    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent className="max-w-md">
                <AlertDialogHeader>
                    <AlertDialogTitle className="flex items-center gap-2">
                        <ArrowRightLeft className="h-5 w-5" />
                        Transfer Patient
                    </AlertDialogTitle>
                    <AlertDialogDescription>
                        Transfer <strong>{patientName}</strong> from{' '}
                        <strong>{currentWardName}</strong> to another ward.
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <div className="space-y-4 py-4">
                    <div className="space-y-2">
                        <Label htmlFor="to_ward_id">Destination Ward *</Label>
                        <Select
                            value={selectedWardId}
                            onValueChange={setSelectedWardId}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select ward..." />
                            </SelectTrigger>
                            <SelectContent>
                                {availableWards.length === 0 ? (
                                    <div className="p-2 text-center text-sm text-gray-500">
                                        No wards with available beds
                                    </div>
                                ) : (
                                    availableWards.map((ward) => (
                                        <SelectItem
                                            key={ward.id}
                                            value={ward.id.toString()}
                                        >
                                            <div className="flex items-center gap-2">
                                                <Hospital className="h-4 w-4" />
                                                <span>
                                                    {ward.name} ({ward.code})
                                                </span>
                                                <span className="text-xs text-gray-500">
                                                    - {ward.available_beds} beds
                                                    available
                                                </span>
                                            </div>
                                        </SelectItem>
                                    ))
                                )}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="transfer_reason">
                            Transfer Reason *
                        </Label>
                        <Textarea
                            id="transfer_reason"
                            placeholder="e.g., Patient condition improved, stepping down to general ward..."
                            value={transferReason}
                            onChange={(e) => setTransferReason(e.target.value)}
                            rows={3}
                        />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="transfer_notes">
                            Additional Notes (Optional)
                        </Label>
                        <Textarea
                            id="transfer_notes"
                            placeholder="Any additional notes for the receiving ward..."
                            value={transferNotes}
                            onChange={(e) => setTransferNotes(e.target.value)}
                            rows={2}
                        />
                    </div>

                    {selectedWard && (
                        <div className="rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm dark:border-blue-800 dark:bg-blue-950">
                            <p className="text-blue-800 dark:text-blue-200">
                                <strong>Note:</strong> The patient's current bed
                                will be released. A nurse at{' '}
                                <strong>{selectedWard.name}</strong> will assign
                                a new bed upon arrival.
                            </p>
                        </div>
                    )}
                </div>

                <AlertDialogFooter>
                    <AlertDialogCancel onClick={handleClose}>
                        Cancel
                    </AlertDialogCancel>
                    <AlertDialogAction
                        onClick={handleSubmit}
                        disabled={
                            isSubmitting ||
                            !selectedWardId ||
                            !transferReason.trim()
                        }
                        className="bg-blue-600 hover:bg-blue-700"
                    >
                        {isSubmitting ? 'Transferring...' : 'Transfer Patient'}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
