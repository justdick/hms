import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Check, CheckCircle2, Copy, FileText, User } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

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
    const [copied, setCopied] = useState(false);

    if (!patient) {
        return null;
    }

    const handleCopyFolderNumber = async () => {
        try {
            await navigator.clipboard.writeText(patient.patient_number);
            setCopied(true);
            toast.success('Folder number copied to clipboard');
            setTimeout(() => setCopied(false), 2000);
        } catch {
            toast.error('Failed to copy');
        }
    };

    return (
        <AlertDialog open={open} onOpenChange={onClose}>
            <AlertDialogContent className="sm:max-w-md">
                <AlertDialogHeader>
                    <AlertDialogTitle className="flex items-center gap-2">
                        <CheckCircle2 className="h-5 w-5 text-green-600" />
                        Patient Registered Successfully!
                    </AlertDialogTitle>
                </AlertDialogHeader>

                <div className="space-y-4">
                    {/* Prominent Folder Number Display */}
                    <div className="rounded-lg border-2 border-primary/30 bg-primary/5 p-4 dark:bg-primary/10">
                        <div className="mb-2 flex items-center gap-2 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            <FileText className="h-3.5 w-3.5" />
                            Folder Number
                        </div>
                        <div className="flex items-center justify-between gap-2">
                            <span className="text-2xl font-bold tracking-wide text-primary">
                                {patient.patient_number}
                            </span>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={handleCopyFolderNumber}
                                className="shrink-0 gap-1.5"
                            >
                                {copied ? (
                                    <>
                                        <Check className="h-3.5 w-3.5 text-green-600" />
                                        Copied
                                    </>
                                ) : (
                                    <>
                                        <Copy className="h-3.5 w-3.5" />
                                        Copy
                                    </>
                                )}
                            </Button>
                        </div>
                    </div>

                    {/* Patient Details */}
                    <div className="rounded-lg border bg-muted/50 p-4">
                        <div className="mb-2 flex items-center gap-2 text-sm font-medium text-foreground">
                            <User className="h-4 w-4" />
                            Patient Details
                        </div>
                        <div className="space-y-1.5 text-sm">
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
                                    Age & Gender:
                                </span>
                                <span className="font-medium text-foreground">
                                    {patient.age} years, {patient.gender}
                                </span>
                            </div>
                            {patient.phone_number && (
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Phone:
                                    </span>
                                    <span className="font-medium text-foreground">
                                        {patient.phone_number}
                                    </span>
                                </div>
                            )}
                        </div>
                    </div>

                    <p className="text-center text-sm text-muted-foreground">
                        Would you like to check in this patient now?
                    </p>
                </div>

                <AlertDialogFooter className="gap-2 sm:gap-0">
                    <AlertDialogCancel onClick={onClose}>
                        Close
                    </AlertDialogCancel>
                    <AlertDialogAction
                        onClick={(e) => {
                            e.preventDefault();
                            onCheckinNow();
                        }}
                    >
                        Check-in Now
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
