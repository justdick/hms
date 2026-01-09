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
import { AlertTriangle, Building2, Calendar, Hash } from 'lucide-react';

interface AdmissionDetails {
    id: number;
    admission_number: string;
    ward: string;
    admitted_at: string;
}

interface AdmissionWarningDialogProps {
    open: boolean;
    onClose: () => void;
    onConfirm: () => void;
    admission: AdmissionDetails | null;
    isSubmitting?: boolean;
}

/**
 * Format a date string to a human-readable format
 */
function formatDate(dateString: string): string {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

export default function AdmissionWarningDialog({
    open,
    onClose,
    onConfirm,
    admission,
    isSubmitting = false,
}: AdmissionWarningDialogProps) {
    if (!admission) {
        return null;
    }

    return (
        <AlertDialog open={open} onOpenChange={onClose}>
            <AlertDialogContent className="sm:max-w-md">
                <AlertDialogHeader>
                    <AlertDialogTitle className="flex items-center gap-2">
                        <AlertTriangle className="h-5 w-5 text-amber-500" />
                        Patient Has Active Admission
                    </AlertDialogTitle>
                    <AlertDialogDescription asChild>
                        <div className="space-y-3">
                            <p>
                                This patient is currently admitted to a ward.
                                Creating an OPD check-in will generate a
                                separate outpatient visit.
                            </p>

                            {/* Admission Details Card */}
                            <div className="rounded-lg border bg-muted/50 p-3">
                                <div className="space-y-2 text-sm">
                                    <div className="flex items-center gap-2">
                                        <Hash className="h-4 w-4 text-muted-foreground" />
                                        <span className="text-muted-foreground">
                                            Admission:
                                        </span>
                                        <span className="font-medium text-foreground">
                                            {admission.admission_number}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Building2 className="h-4 w-4 text-muted-foreground" />
                                        <span className="text-muted-foreground">
                                            Ward:
                                        </span>
                                        <span className="font-medium text-foreground">
                                            {admission.ward}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Calendar className="h-4 w-4 text-muted-foreground" />
                                        <span className="text-muted-foreground">
                                            Admitted:
                                        </span>
                                        <span className="font-medium text-foreground">
                                            {formatDate(admission.admitted_at)}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <p className="text-sm">
                                Do you want to proceed with the OPD check-in
                                anyway?
                            </p>
                        </div>
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter className="gap-2 sm:gap-0">
                    <AlertDialogCancel onClick={onClose} disabled={isSubmitting}>
                        Cancel
                    </AlertDialogCancel>
                    <AlertDialogAction
                        onClick={(e) => {
                            e.preventDefault();
                            onConfirm();
                        }}
                        disabled={isSubmitting}
                        className="bg-amber-600 hover:bg-amber-700"
                    >
                        {isSubmitting ? 'Checking in...' : 'Proceed with Check-in'}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
