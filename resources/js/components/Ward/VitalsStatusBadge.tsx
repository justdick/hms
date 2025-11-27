import { Badge } from '@/components/ui/badge';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useTimeRemaining } from '@/hooks/use-time-remaining';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';
import { AlertCircle, CheckCircle, Clock } from 'lucide-react';
import { useMemo } from 'react';

export interface VitalsSchedule {
    id: number;
    patient_admission_id: number;
    interval_minutes: number;
    next_due_at: string;
    last_recorded_at?: string;
    is_active: boolean;
}

export interface VitalsScheduleStatus {
    status: 'upcoming' | 'due' | 'overdue';
    time_until_due_minutes?: number;
    time_overdue_minutes?: number;
    next_due_at: string;
    interval_minutes: number;
}

interface VitalsStatusBadgeProps {
    schedule?: VitalsSchedule;
    status?: VitalsScheduleStatus;
    admissionId: number;
    wardId: number;
    className?: string;
    onClick?: () => void;
}

/**
 * Badge component displaying vitals schedule status with color coding
 * - Green: Upcoming (more than 15 minutes until due)
 * - Yellow: Due (within 15 minutes or at due time)
 * - Red: Overdue (past due time + 15 minute grace period)
 */
export function VitalsStatusBadge({
    schedule,
    status,
    admissionId,
    wardId,
    className,
    onClick,
}: VitalsStatusBadgeProps) {
    // If no schedule or status provided, don't render
    if (!schedule && !status) {
        return null;
    }

    // Get real-time minutes remaining
    const minutesRemaining = useTimeRemaining(
        schedule?.next_due_at || status?.next_due_at || null,
    );

    // Calculate status from schedule if not provided, recalculating when minutesRemaining changes
    const scheduleStatus = useMemo(() => {
        if (status) return status;
        if (!schedule) return null;
        return calculateStatus(schedule);
    }, [status, schedule, minutesRemaining]);

    const handleClick = () => {
        if (onClick) {
            onClick();
        } else {
            // Default behavior: navigate to vitals recording
            router.visit(`/wards/${wardId}/patients/${admissionId}`);
        }
    };

    const {
        variant,
        icon: Icon,
        text,
        timeText,
    } = getStatusConfig(scheduleStatus);

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Badge
                    variant={variant}
                    className={cn(
                        'cursor-pointer gap-1.5 transition-all hover:scale-105',
                        className,
                    )}
                    onClick={handleClick}
                    asChild
                >
                    <button type="button">
                        <Icon className="h-3 w-3" />
                        <span>{text}</span>
                        {timeText && (
                            <span className="font-normal">({timeText})</span>
                        )}
                    </button>
                </Badge>
            </TooltipTrigger>
            <TooltipContent>
                <div className="space-y-1">
                    <p className="font-semibold">Vitals Schedule</p>
                    <p className="text-xs">
                        Interval:{' '}
                        {formatInterval(scheduleStatus.interval_minutes)}
                    </p>
                    <p className="text-xs">
                        Next due: {formatDateTime(scheduleStatus.next_due_at)}
                    </p>
                    {scheduleStatus.status === 'upcoming' &&
                        scheduleStatus.time_until_due_minutes !== undefined && (
                            <p className="text-xs">
                                Due in{' '}
                                {formatTimeRemaining(
                                    scheduleStatus.time_until_due_minutes,
                                )}
                            </p>
                        )}
                    {scheduleStatus.status === 'overdue' &&
                        scheduleStatus.time_overdue_minutes !== undefined && (
                            <p className="text-xs text-red-200">
                                Overdue by{' '}
                                {formatTimeRemaining(
                                    scheduleStatus.time_overdue_minutes,
                                )}
                            </p>
                        )}
                    <p className="text-xs italic">Click to record vitals</p>
                </div>
            </TooltipContent>
        </Tooltip>
    );
}

/**
 * Calculate status from schedule data
 */
function calculateStatus(schedule: VitalsSchedule): VitalsScheduleStatus {
    const now = new Date();
    const nextDue = new Date(schedule.next_due_at);
    const diffMinutes = Math.floor(
        (nextDue.getTime() - now.getTime()) / (1000 * 60),
    );

    // Grace period is 15 minutes
    const GRACE_PERIOD_MINUTES = 15;

    let status: 'upcoming' | 'due' | 'overdue';
    let time_until_due_minutes: number | undefined;
    let time_overdue_minutes: number | undefined;

    if (diffMinutes > GRACE_PERIOD_MINUTES) {
        // More than 15 minutes until due
        status = 'upcoming';
        time_until_due_minutes = diffMinutes;
    } else if (diffMinutes >= -GRACE_PERIOD_MINUTES) {
        // Within 15 minutes before or after due time (grace period)
        status = 'due';
        if (diffMinutes > 0) {
            time_until_due_minutes = diffMinutes;
        } else {
            time_overdue_minutes = Math.abs(diffMinutes);
        }
    } else {
        // More than 15 minutes past due time
        status = 'overdue';
        time_overdue_minutes = Math.abs(diffMinutes);
    }

    return {
        status,
        time_until_due_minutes,
        time_overdue_minutes,
        next_due_at: schedule.next_due_at,
        interval_minutes: schedule.interval_minutes,
    };
}

/**
 * Get badge configuration based on status
 */
function getStatusConfig(status: VitalsScheduleStatus) {
    switch (status.status) {
        case 'upcoming':
            return {
                variant: 'outline' as const,
                icon: CheckCircle,
                text: 'Upcoming',
                timeText: status.time_until_due_minutes
                    ? formatTimeShort(status.time_until_due_minutes)
                    : null,
                className:
                    'border-green-500 text-green-700 dark:border-green-600 dark:text-green-400 hover:bg-green-50 dark:hover:bg-green-950',
            };
        case 'due':
            return {
                variant: 'outline' as const,
                icon: Clock,
                text: 'Due',
                timeText:
                    status.time_until_due_minutes !== undefined
                        ? formatTimeShort(status.time_until_due_minutes)
                        : status.time_overdue_minutes !== undefined
                          ? `+${formatTimeShort(status.time_overdue_minutes)}`
                          : null,
                className:
                    'border-yellow-500 text-yellow-700 dark:border-yellow-600 dark:text-yellow-400 hover:bg-yellow-50 dark:hover:bg-yellow-950',
            };
        case 'overdue':
            return {
                variant: 'outline' as const,
                icon: AlertCircle,
                text: 'Overdue',
                timeText: status.time_overdue_minutes
                    ? `+${formatTimeShort(status.time_overdue_minutes)}`
                    : null,
                className:
                    'border-red-500 text-red-700 dark:border-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950 animate-pulse',
            };
    }
}

/**
 * Format interval in human-readable format
 */
function formatInterval(minutes: number): string {
    if (minutes < 60) {
        return `${minutes} min`;
    }
    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;
    if (remainingMinutes === 0) {
        return `${hours}h`;
    }
    return `${hours}h ${remainingMinutes}m`;
}

/**
 * Format time remaining in short format
 */
function formatTimeShort(minutes: number): string {
    if (minutes < 60) {
        return `${minutes}m`;
    }
    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;
    if (remainingMinutes === 0) {
        return `${hours}h`;
    }
    return `${hours}h ${remainingMinutes}m`;
}

/**
 * Format time remaining in long format
 */
function formatTimeRemaining(minutes: number): string {
    if (minutes < 60) {
        return `${minutes} minute${minutes !== 1 ? 's' : ''}`;
    }
    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;
    if (remainingMinutes === 0) {
        return `${hours} hour${hours !== 1 ? 's' : ''}`;
    }
    return `${hours} hour${hours !== 1 ? 's' : ''} ${remainingMinutes} minute${remainingMinutes !== 1 ? 's' : ''}`;
}

/**
 * Format date time for display
 */
function formatDateTime(dateString: string): string {
    return new Date(dateString).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
