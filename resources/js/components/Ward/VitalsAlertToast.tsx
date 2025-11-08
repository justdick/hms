import { Button } from '@/components/ui/button';
import { useSoundAlert } from '@/hooks/use-sound-alert';
import type { VitalsAlert } from '@/hooks/use-vitals-alerts';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';
import { AlertCircle, Clock, X } from 'lucide-react';
import { useEffect } from 'react';
import { toast as sonnerToast } from 'sonner';

interface VitalsAlertToastProps {
    alert: VitalsAlert;
    onDismiss: (alertId: number) => Promise<void>;
}

/**
 * Toast notification component for vitals alerts
 * Displays patient information and provides actions to record vitals or dismiss
 */
export function VitalsAlertToast({ alert, onDismiss }: VitalsAlertToastProps) {
    const { playAlert } = useSoundAlert();

    // Play sound when toast is displayed
    useEffect(() => {
        const soundType = alert.status === 'overdue' ? 'urgent' : 'gentle';
        playAlert(soundType);
    }, [alert.status, playAlert]);

    const handleRecordVitals = () => {
        // Navigate to vitals recording form
        router.visit(`/wards/${alert.ward_id}/patients/${alert.patient_admission_id}`);
    };

    const handleDismiss = async () => {
        try {
            await onDismiss(alert.id);
        } catch (error) {
            console.error('Failed to dismiss alert:', error);
        }
    };

    const isOverdue = alert.status === 'overdue';
    const isDue = alert.status === 'due';

    return (
        <div
            className={cn(
                'flex w-full flex-col gap-3 rounded-lg border p-4',
                isOverdue &&
                    'border-red-500 bg-red-50 dark:border-red-600 dark:bg-red-950/50',
                isDue &&
                    'border-yellow-500 bg-yellow-50 dark:border-yellow-600 dark:bg-yellow-950/50',
            )}
        >
            <div className="flex items-start justify-between gap-3">
                <div className="flex items-start gap-3">
                    {isOverdue ? (
                        <AlertCircle className="mt-0.5 h-5 w-5 shrink-0 text-red-600 dark:text-red-400" />
                    ) : (
                        <Clock className="mt-0.5 h-5 w-5 shrink-0 text-yellow-600 dark:text-yellow-400" />
                    )}
                    <div className="flex-1 space-y-1">
                        <p
                            className={cn(
                                'font-semibold',
                                isOverdue &&
                                    'text-red-900 dark:text-red-100',
                                isDue &&
                                    'text-yellow-900 dark:text-yellow-100',
                            )}
                        >
                            {isOverdue ? 'Vitals Overdue' : 'Vitals Due'}
                        </p>
                        <div
                            className={cn(
                                'space-y-0.5 text-sm',
                                isOverdue &&
                                    'text-red-800 dark:text-red-200',
                                isDue &&
                                    'text-yellow-800 dark:text-yellow-200',
                            )}
                        >
                            <p>
                                <span className="font-medium">Patient:</span>{' '}
                                {alert.patient_name}
                            </p>
                            <p>
                                <span className="font-medium">Bed:</span>{' '}
                                {alert.bed_number}
                            </p>
                            <p>
                                <span className="font-medium">Ward:</span>{' '}
                                {alert.ward_name}
                            </p>
                            {isOverdue && alert.time_overdue_minutes && (
                                <p className="font-semibold">
                                    Overdue by {formatTimeOverdue(alert.time_overdue_minutes)}
                                </p>
                            )}
                            {isDue && (
                                <p>
                                    <span className="font-medium">Due at:</span>{' '}
                                    {formatDueTime(alert.due_at)}
                                </p>
                            )}
                        </div>
                    </div>
                </div>
                <Button
                    variant="ghost"
                    size="icon"
                    className={cn(
                        'h-6 w-6 shrink-0',
                        isOverdue &&
                            'text-red-600 hover:bg-red-100 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-900 dark:hover:text-red-300',
                        isDue &&
                            'text-yellow-600 hover:bg-yellow-100 hover:text-yellow-700 dark:text-yellow-400 dark:hover:bg-yellow-900 dark:hover:text-yellow-300',
                    )}
                    onClick={handleDismiss}
                >
                    <X className="h-4 w-4" />
                    <span className="sr-only">Dismiss</span>
                </Button>
            </div>

            <div className="flex gap-2">
                <Button
                    size="sm"
                    className={cn(
                        'flex-1',
                        isOverdue &&
                            'bg-red-600 text-white hover:bg-red-700 dark:bg-red-700 dark:hover:bg-red-800',
                        isDue &&
                            'bg-yellow-600 text-white hover:bg-yellow-700 dark:bg-yellow-700 dark:hover:bg-yellow-800',
                    )}
                    onClick={handleRecordVitals}
                >
                    Record Vitals
                </Button>
                <Button
                    size="sm"
                    variant="outline"
                    className={cn(
                        isOverdue &&
                            'border-red-300 text-red-700 hover:bg-red-100 dark:border-red-700 dark:text-red-300 dark:hover:bg-red-900',
                        isDue &&
                            'border-yellow-300 text-yellow-700 hover:bg-yellow-100 dark:border-yellow-700 dark:text-yellow-300 dark:hover:bg-yellow-900',
                    )}
                    onClick={handleDismiss}
                >
                    Dismiss
                </Button>
            </div>
        </div>
    );
}

/**
 * Format time overdue in human-readable format
 */
function formatTimeOverdue(minutes: number): string {
    if (minutes < 60) {
        return `${minutes} minute${minutes !== 1 ? 's' : ''}`;
    }
    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;
    if (remainingMinutes === 0) {
        return `${hours} hour${hours !== 1 ? 's' : ''}`;
    }
    return `${hours}h ${remainingMinutes}m`;
}

/**
 * Format due time for display
 */
function formatDueTime(dateString: string): string {
    return new Date(dateString).toLocaleString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
    });
}

/**
 * Show a vitals alert toast notification
 */
export function showVitalsAlertToast(
    alert: VitalsAlert,
    onDismiss: (alertId: number) => Promise<void>,
): string | number {
    const isOverdue = alert.status === 'overdue';
    const duration = isOverdue ? 15000 : 10000; // 15s for overdue, 10s for due

    return sonnerToast.custom(
        () => <VitalsAlertToast alert={alert} onDismiss={onDismiss} />,
        {
            duration,
            id: `vitals-alert-${alert.id}`,
        },
    );
}
