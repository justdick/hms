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
import { CheckCircle2, User } from 'lucide-react';

interface Patient {
    id: number;
    patient_number: string;
    full_name: string;
    age: number;
    gender: string;
    phone_number: string | null;
}

interface CheckinPromptDialogProps {
    open: boolean;
    onClose: () => void;
    patient: Patient | null;
    onCheckinNow: () => void;
}

export default function CheckinPromptDialog({
    open,
    onClose,
    patient,
    onCheckinNow,
}: CheckinPromptDialogProps) {
    if (!patient) {
        return null;
    }

    return (
        <AlertDialog open={open} onOpenChange={onClose}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle className="flex items-center gap-2">
                        <CheckCircle2 className="h-5 w-5 text-green-600" />
                        Patient Registered Successfully!
                    </AlertDialogTitle>
                </AlertDialogHeader>
                
                <div className="space-y-4">
                    <div className="rounded-lg border bg-muted/50 p-4">
                        <div className="mb-2 flex items-center gap-2 text-sm font-medium text-foreground">
                            <User className="h-4 w-4" />
                            Patient Information
                        </div>
                        <div className="space-y-1 text-sm">
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">
                                    Name:
                                </span>
                                <span className="font-medium text-foreground">
                                    {patient.full_name}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">
                                    Patient Number:
                                </span>
                                <span className="font-medium text-foreground">
                                    {patient.patient_number}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">
                                    Age & Gender:
                                </span>
                                <span className="font-medium text-foreground">
                                    {patient.age} years, {patient.gender}
                                </span>
                            </div>
                        </div>
                    </div>

                    <p className="text-center text-foreground">
                        Would you like to check in this patient for
                        consultation now?
                    </p>
                </div>
                <AlertDialogFooter>
                    <AlertDialogCancel onClick={onClose}>Later</AlertDialogCancel>
                    <AlertDialogAction onClick={(e) => {
                        e.preventDefault();
                        onCheckinNow();
                    }}>
                        Check-in Now
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
