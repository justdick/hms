import { router } from '@inertiajs/react';
import { AlertTriangle, CheckCircle, Database, Loader2 } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';

interface Backup {
    id: number;
    filename: string;
    file_size: number;
    created_at: string;
}

interface Props {
    backup: Backup | null;
    onClose: () => void;
}

function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleString();
}

function formatFileSize(bytes: number): string {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

type RestoreStatus = 'idle' | 'restoring' | 'success' | 'error';

export default function RestoreConfirmationModal({ backup, onClose }: Props) {
    const [confirmText, setConfirmText] = useState('');
    const [status, setStatus] = useState<RestoreStatus>('idle');
    const [progress, setProgress] = useState(0);
    const [statusMessage, setStatusMessage] = useState('');
    const [errorMessage, setErrorMessage] = useState('');

    const isConfirmed = confirmText === 'RESTORE';
    const isRestoring = status === 'restoring';

    const handleRestore = () => {
        if (!backup || !isConfirmed) return;

        setStatus('restoring');
        setProgress(10);
        setStatusMessage('Creating pre-restore backup...');

        // Simulate progress updates since we can't get real-time feedback
        const progressInterval = setInterval(() => {
            setProgress((prev) => {
                if (prev >= 90) return prev;
                const increment = Math.random() * 15;
                const newProgress = Math.min(prev + increment, 90);
                
                // Update status message based on progress
                if (newProgress > 30 && newProgress <= 50) {
                    setStatusMessage('Decompressing backup file...');
                } else if (newProgress > 50 && newProgress <= 70) {
                    setStatusMessage('Restoring database...');
                } else if (newProgress > 70) {
                    setStatusMessage('Finalizing restore...');
                }
                
                return newProgress;
            });
        }, 500);

        router.post(
            `/admin/backups/${backup.id}/restore`,
            { confirm: true },
            {
                onSuccess: () => {
                    clearInterval(progressInterval);
                    setProgress(100);
                    setStatus('success');
                    setStatusMessage('Database restored successfully!');
                    
                    // Force full page reload after short delay to show success
                    setTimeout(() => {
                        window.location.href = '/admin/backups';
                    }, 1500);
                },
                onError: (errors) => {
                    clearInterval(progressInterval);
                    setStatus('error');
                    setErrorMessage(
                        typeof errors === 'object' && errors !== null
                            ? Object.values(errors).flat().join(', ')
                            : 'Restore failed. Please try again.'
                    );
                },
                onFinish: () => {
                    // Only reset if not success (success will redirect)
                    if (status !== 'success') {
                        clearInterval(progressInterval);
                    }
                },
            },
        );
    };

    const handleClose = () => {
        if (isRestoring || status === 'success') return;
        setConfirmText('');
        setStatus('idle');
        setProgress(0);
        setStatusMessage('');
        setErrorMessage('');
        onClose();
    };

    return (
        <Dialog open={backup !== null} onOpenChange={handleClose}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-red-600">
                        {status === 'success' ? (
                            <CheckCircle className="h-5 w-5 text-green-600" />
                        ) : (
                            <AlertTriangle className="h-5 w-5" />
                        )}
                        {status === 'success' ? 'Restore Complete' : 'Confirm Database Restore'}
                    </DialogTitle>
                    <DialogDescription>
                        {status === 'success'
                            ? 'The database has been restored. Redirecting...'
                            : 'This action will replace the current database with the backup data.'}
                    </DialogDescription>
                </DialogHeader>

                {backup && (
                    <div className="space-y-4">
                        {/* Progress indicator during restore */}
                        {(status === 'restoring' || status === 'success') && (
                            <div className="space-y-3">
                                <Progress value={progress} className="h-2" />
                                <div className="flex items-center gap-2 text-sm">
                                    {status === 'restoring' ? (
                                        <Loader2 className="h-4 w-4 animate-spin text-blue-600" />
                                    ) : (
                                        <CheckCircle className="h-4 w-4 text-green-600" />
                                    )}
                                    <span className={status === 'success' ? 'text-green-600 font-medium' : 'text-gray-600'}>
                                        {statusMessage}
                                    </span>
                                </div>
                            </div>
                        )}

                        {/* Error message */}
                        {status === 'error' && (
                            <div className="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-950">
                                <div className="flex items-start gap-3">
                                    <AlertTriangle className="mt-0.5 h-5 w-5 text-red-600" />
                                    <div className="text-sm text-red-800 dark:text-red-200">
                                        <p className="font-semibold">Restore Failed</p>
                                        <p className="mt-1">{errorMessage}</p>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Warning - only show when idle */}
                        {status === 'idle' && (
                            <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950">
                                <div className="flex items-start gap-3">
                                    <AlertTriangle className="mt-0.5 h-5 w-5 text-amber-600" />
                                    <div className="text-sm text-amber-800 dark:text-amber-200">
                                        <p className="font-semibold">Warning: This action cannot be undone!</p>
                                        <ul className="mt-2 list-inside list-disc space-y-1">
                                            <li>All current data will be replaced</li>
                                            <li>A pre-restore backup will be created automatically</li>
                                            <li>Users may experience brief downtime</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Backup info */}
                        <div className="rounded-lg border bg-gray-50 p-4 dark:bg-gray-900">
                            <div className="flex items-center gap-3">
                                <Database className="h-8 w-8 text-blue-600" />
                                <div>
                                    <p className="font-medium text-gray-900 dark:text-gray-100">
                                        {backup.filename}
                                    </p>
                                    <p className="text-sm text-gray-500">
                                        {formatFileSize(backup.file_size)} • Created {formatDate(backup.created_at)}
                                    </p>
                                </div>
                            </div>
                        </div>

                        {/* Confirmation input - only show when idle or error */}
                        {(status === 'idle' || status === 'error') && (
                            <div className="space-y-2">
                                <Label htmlFor="confirm">
                                    Type <span className="font-mono font-bold">RESTORE</span> to confirm
                                </Label>
                                <Input
                                    id="confirm"
                                    value={confirmText}
                                    onChange={(e) => setConfirmText(e.target.value)}
                                    placeholder="Type RESTORE"
                                    disabled={isRestoring}
                                />
                            </div>
                        )}
                    </div>
                )}

                <DialogFooter className="gap-2 sm:gap-0">
                    {status !== 'success' && (
                        <Button variant="outline" onClick={handleClose} disabled={isRestoring}>
                            Cancel
                        </Button>
                    )}
                    {(status === 'idle' || status === 'error') && (
                        <Button
                            variant="destructive"
                            onClick={handleRestore}
                            disabled={!isConfirmed || isRestoring}
                        >
                            {status === 'error' ? 'Retry Restore' : 'Restore Database'}
                        </Button>
                    )}
                    {status === 'restoring' && (
                        <Button variant="secondary" disabled>
                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            Restoring...
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
