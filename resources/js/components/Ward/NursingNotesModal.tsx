import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
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
import {
    AlertCircle,
    ClipboardList,
    Eye,
    Loader2,
    LogIn,
    Stethoscope,
    UserCheck,
} from 'lucide-react';
import { toast } from 'sonner';

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
    admission_number: string;
    patient: Patient;
    bed?: Bed;
    admitted_at: string;
}

interface NursingNotesModalProps {
    open: boolean;
    onClose: () => void;
    admission: PatientAdmission | null;
}

const NOTE_TYPES = [
    { value: 'admission', label: 'Admission', icon: LogIn },
    { value: 'assessment', label: 'Assessment', icon: Stethoscope },
    { value: 'care', label: 'Care', icon: UserCheck },
    { value: 'observation', label: 'Observation', icon: Eye },
    { value: 'incident', label: 'Incident', icon: AlertCircle },
    { value: 'handover', label: 'Handover', icon: ClipboardList },
];

export function NursingNotesModal({
    open,
    onClose,
    admission,
}: NursingNotesModalProps) {
    const handleSuccess = () => {
        toast.success('Nursing note added successfully');
        onClose(); // Auto-close modal after adding note
    };

    if (!admission) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Add Nursing Note</DialogTitle>
                    <DialogDescription>
                        Add a new nursing note for{' '}
                        {admission.patient.first_name}{' '}
                        {admission.patient.last_name}
                    </DialogDescription>
                </DialogHeader>

                <Form
                    action={`/admissions/${admission.id}/nursing-notes`}
                    method="post"
                    onSuccess={handleSuccess}
                    onError={() => {
                        toast.error('Failed to add nursing note');
                    }}
                    resetOnSuccess
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            {/* Note Type */}
                            <div className="space-y-2">
                                <Label htmlFor="type">
                                    Note Type{' '}
                                    <span className="text-red-500">*</span>
                                </Label>
                                <Select name="type" required>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select note type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {NOTE_TYPES.map((type) => {
                                            const Icon = type.icon;
                                            return (
                                                <SelectItem
                                                    key={type.value}
                                                    value={type.value}
                                                >
                                                    <div className="flex items-center gap-2">
                                                        <Icon className="h-4 w-4" />
                                                        {type.label}
                                                    </div>
                                                </SelectItem>
                                            );
                                        })}
                                    </SelectContent>
                                </Select>
                                {errors.type && (
                                    <p className="text-sm text-destructive">
                                        {errors.type}
                                    </p>
                                )}
                            </div>

                            {/* Note Content */}
                            <div className="space-y-2">
                                <Label htmlFor="note">
                                    Note <span className="text-red-500">*</span>
                                </Label>
                                <Textarea
                                    name="note"
                                    id="note"
                                    placeholder="Enter detailed nursing note..."
                                    rows={6}
                                    required
                                    minLength={10}
                                />
                                <p className="text-xs text-gray-500 dark:text-gray-400">
                                    Minimum 10 characters required
                                </p>
                                {errors.note && (
                                    <p className="text-sm text-destructive">
                                        {errors.note}
                                    </p>
                                )}
                            </div>

                            {/* Action Buttons */}
                            <div className="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={onClose}
                                >
                                    Close
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing && (
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    )}
                                    Add Note
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
