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
import { AlertTriangle } from 'lucide-react';

interface CccMismatchWarningDialogProps {
    open: boolean;
    onClose: () => void;
    onConfirm: () => void;
    onUseSameDayCcc: () => void;
    existingCcc: string;
    enteredCcc: string;
    department: string;
}

export default function CccMismatchWarningDialog({
    open,
    onClose,
    onConfirm,
    onUseSameDayCcc,
    existingCcc,
    enteredCcc,
    department,
}: CccMismatchWarningDialogProps) {
    return (
        <AlertDialog open={open} onOpenChange={onClose}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle className="flex items-center gap-2">
                        <AlertTriangle className="h-5 w-5 text-amber-500" />
                        Different CCC Entered
                    </AlertDialogTitle>
                    <AlertDialogDescription asChild>
                        <div className="space-y-3">
                            <p>
                                This patient already has a check-in today with a
                                different CCC:
                            </p>
                            <div className="space-y-2 rounded-md bg-muted p-3">
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">
                                        Existing CCC:
                                    </span>
                                    <span className="font-mono font-medium">
                                        {existingCcc}
                                    </span>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">
                                        From:
                                    </span>
                                    <span className="font-medium">
                                        {department}
                                    </span>
                                </div>
                                <div className="flex justify-between border-t pt-2 text-sm">
                                    <span className="text-muted-foreground">
                                        You entered:
                                    </span>
                                    <span className="font-mono font-medium">
                                        {enteredCcc}
                                    </span>
                                </div>
                            </div>
                            <p className="text-sm">
                                NHIS requires the same CCC for all visits on the
                                same day. Would you like to use the existing CCC
                                instead?
                            </p>
                        </div>
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter className="flex-col gap-2 sm:flex-row">
                    <AlertDialogCancel onClick={onClose}>
                        Cancel
                    </AlertDialogCancel>
                    <AlertDialogAction
                        onClick={onUseSameDayCcc}
                        className="bg-primary"
                    >
                        Use Existing CCC
                    </AlertDialogAction>
                    <AlertDialogAction
                        onClick={onConfirm}
                        variant="outline"
                        className="border-amber-500 text-amber-700 hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-950/20"
                    >
                        Use Different CCC Anyway
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
