/**
 * VitalsAlertDashboard Component
 *
 * Displays a comprehensive dashboard of all active vitals schedules with patient information,
 * status indicators, and quick action buttons. Supports filtering by ward and sorting by urgency.
 *
 * @example
 * ```tsx
 * import { VitalsAlertDashboard } from '@/components/Ward/VitalsAlertDashboard';
 *
 * // Basic usage
 * <VitalsAlertDashboard />
 *
 * // With ward filter
 * <VitalsAlertDashboard
 *   wards={[
 *     { id: 1, name: 'ICU' },
 *     { id: 2, name: 'General Ward' }
 *   ]}
 *   defaultWardId={1}
 * />
 * ```
 */

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useVitalsAlerts } from '@/hooks/use-vitals-alerts';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';
import { Activity, AlertCircle, CheckCircle, Clock, Eye } from 'lucide-react';
import { useMemo, useState } from 'react';

interface Ward {
    id: number;
    name: string;
}

interface VitalsAlertDashboardProps {
    wards?: Ward[];
    defaultWardId?: number;
    className?: string;
}

interface ScheduleWithStatus {
    id: number;
    patient_admission_id: number;
    ward_id: number;
    patient_name: string;
    bed_number: string;
    ward_name: string;
    next_due_at: string;
    interval_minutes: number;
    status: 'upcoming' | 'due' | 'overdue';
    time_until_due_minutes?: number;
    time_overdue_minutes?: number;
    urgency_score: number;
}

/**
 * Dashboard component displaying all active vitals schedules
 * Shows patient information, status, and provides quick actions
 */
export function VitalsAlertDashboard({
    wards = [],
    defaultWardId,
    className,
}: VitalsAlertDashboardProps) {
    const [selectedWardId, setSelectedWardId] = useState<number | undefined>(
        defaultWardId,
    );

    const { alerts, loading, error } = useVitalsAlerts({
        wardId: selectedWardId,
        enabled: true,
    });

    // Transform alerts into schedules with status
    const schedules = useMemo<ScheduleWithStatus[]>(() => {
        return alerts.map((alert) => {
            const now = new Date();
            const dueAt = new Date(alert.due_at);
            const diffMinutes = Math.floor(
                (dueAt.getTime() - now.getTime()) / (1000 * 60),
            );

            const GRACE_PERIOD_MINUTES = 15;

            let status: 'upcoming' | 'due' | 'overdue';
            let time_until_due_minutes: number | undefined;
            let time_overdue_minutes: number | undefined;
            let urgency_score: number;

            if (diffMinutes > GRACE_PERIOD_MINUTES) {
                status = 'upcoming';
                time_until_due_minutes = diffMinutes;
                urgency_score = 1000 + diffMinutes; // Lower urgency
            } else if (diffMinutes >= -GRACE_PERIOD_MINUTES) {
                status = 'due';
                if (diffMinutes > 0) {
                    time_until_due_minutes = diffMinutes;
                } else {
                    time_overdue_minutes = Math.abs(diffMinutes);
                }
                urgency_score = 500 - Math.abs(diffMinutes); // Medium urgency
            } else {
                status = 'overdue';
                time_overdue_minutes = Math.abs(diffMinutes);
                urgency_score = -Math.abs(diffMinutes); // Highest urgency
            }

            return {
                id: alert.id,
                patient_admission_id: alert.patient_admission_id,
                ward_id: alert.ward_id,
                patient_name: alert.patient_name,
                bed_number: alert.bed_number,
                ward_name: alert.ward_name,
                next_due_at: alert.due_at,
                interval_minutes: 0, // Not provided in alert, could be added to API
                status,
                time_until_due_minutes,
                time_overdue_minutes,
                urgency_score,
            };
        });
    }, [alerts]);

    // Sort by urgency (overdue first, then due, then upcoming)
    const sortedSchedules = useMemo(() => {
        return [...schedules].sort((a, b) => a.urgency_score - b.urgency_score);
    }, [schedules]);

    // Calculate statistics
    const stats = useMemo(() => {
        const overdue = schedules.filter((s) => s.status === 'overdue').length;
        const due = schedules.filter((s) => s.status === 'due').length;
        const upcoming = schedules.filter(
            (s) => s.status === 'upcoming',
        ).length;

        return { overdue, due, upcoming, total: schedules.length };
    }, [schedules]);

    const handleRecordVitals = (schedule: ScheduleWithStatus) => {
        router.visit(
            `/wards/${schedule.ward_id}/patients/${schedule.patient_admission_id}`,
        );
    };

    const handleViewPatient = (schedule: ScheduleWithStatus) => {
        router.visit(
            `/wards/${schedule.ward_id}/patients/${schedule.patient_admission_id}`,
        );
    };

    return (
        <Card className={cn('w-full', className)}>
            <CardHeader>
                <div className="flex items-start justify-between gap-4">
                    <div className="space-y-1.5">
                        <CardTitle>Vitals Monitoring Dashboard</CardTitle>
                        <CardDescription>
                            Active vitals schedules and alerts across all wards
                        </CardDescription>
                    </div>

                    {wards.length > 0 && (
                        <Select
                            value={selectedWardId?.toString() || 'all'}
                            onValueChange={(value) =>
                                setSelectedWardId(
                                    value === 'all' ? undefined : Number(value),
                                )
                            }
                        >
                            <SelectTrigger className="w-[200px]">
                                <SelectValue placeholder="Filter by ward" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Wards</SelectItem>
                                {wards.map((ward) => (
                                    <SelectItem
                                        key={ward.id}
                                        value={ward.id.toString()}
                                    >
                                        {ward.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}
                </div>

                {/* Statistics */}
                <div className="flex gap-4 pt-4">
                    <div className="flex items-center gap-2">
                        <Badge
                            variant="outline"
                            className="border-red-500 text-red-700 dark:border-red-600 dark:text-red-400"
                        >
                            <AlertCircle className="mr-1 h-3 w-3" />
                            {stats.overdue} Overdue
                        </Badge>
                    </div>
                    <div className="flex items-center gap-2">
                        <Badge
                            variant="outline"
                            className="border-yellow-500 text-yellow-700 dark:border-yellow-600 dark:text-yellow-400"
                        >
                            <Clock className="mr-1 h-3 w-3" />
                            {stats.due} Due
                        </Badge>
                    </div>
                    <div className="flex items-center gap-2">
                        <Badge
                            variant="outline"
                            className="border-green-500 text-green-700 dark:border-green-600 dark:text-green-400"
                        >
                            <CheckCircle className="mr-1 h-3 w-3" />
                            {stats.upcoming} Upcoming
                        </Badge>
                    </div>
                    <div className="flex items-center gap-2">
                        <Badge variant="outline">
                            <Activity className="mr-1 h-3 w-3" />
                            {stats.total} Total
                        </Badge>
                    </div>
                </div>
            </CardHeader>

            <CardContent>
                {error && (
                    <div className="rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">
                        <p className="font-semibold">Error loading alerts</p>
                        <p>{error}</p>
                    </div>
                )}

                {loading && !error && (
                    <div className="flex items-center justify-center py-8">
                        <div className="flex items-center gap-2 text-muted-foreground">
                            <Activity className="h-5 w-5 animate-spin" />
                            <span>Loading vitals schedules...</span>
                        </div>
                    </div>
                )}

                {!loading && !error && sortedSchedules.length === 0 && (
                    <div className="flex flex-col items-center justify-center py-12 text-center">
                        <CheckCircle className="mb-4 h-12 w-12 text-green-500" />
                        <p className="text-lg font-semibold">
                            No active vitals schedules
                        </p>
                        <p className="text-sm text-muted-foreground">
                            {selectedWardId
                                ? 'No patients in this ward have active vitals schedules'
                                : 'No patients have active vitals schedules'}
                        </p>
                    </div>
                )}

                {!loading && !error && sortedSchedules.length > 0 && (
                    <div className="rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Patient</TableHead>
                                    <TableHead>Bed</TableHead>
                                    <TableHead>Ward</TableHead>
                                    <TableHead>Next Due</TableHead>
                                    <TableHead>Time</TableHead>
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {sortedSchedules.map((schedule) => (
                                    <TableRow
                                        key={schedule.id}
                                        className={cn(
                                            schedule.status === 'overdue' &&
                                                'bg-red-50 dark:bg-red-950/20',
                                            schedule.status === 'due' &&
                                                'bg-yellow-50 dark:bg-yellow-950/20',
                                        )}
                                    >
                                        <TableCell>
                                            <StatusBadge
                                                status={schedule.status}
                                            />
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {schedule.patient_name}
                                        </TableCell>
                                        <TableCell>
                                            {schedule.bed_number}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {schedule.ward_name}
                                        </TableCell>
                                        <TableCell>
                                            {formatDateTime(
                                                schedule.next_due_at,
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <TimeDisplay schedule={schedule} />
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-2">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        handleViewPatient(
                                                            schedule,
                                                        )
                                                    }
                                                >
                                                    <Eye className="mr-1 h-3 w-3" />
                                                    View
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant={
                                                        schedule.status ===
                                                        'overdue'
                                                            ? 'destructive'
                                                            : schedule.status ===
                                                                'due'
                                                              ? 'default'
                                                              : 'outline'
                                                    }
                                                    onClick={() =>
                                                        handleRecordVitals(
                                                            schedule,
                                                        )
                                                    }
                                                >
                                                    <Activity className="mr-1 h-3 w-3" />
                                                    Record Vitals
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

/**
 * Status badge component
 */
function StatusBadge({ status }: { status: 'upcoming' | 'due' | 'overdue' }) {
    const config = {
        upcoming: {
            icon: CheckCircle,
            label: 'Upcoming',
            className:
                'border-green-500 text-green-700 dark:border-green-600 dark:text-green-400',
        },
        due: {
            icon: Clock,
            label: 'Due',
            className:
                'border-yellow-500 text-yellow-700 dark:border-yellow-600 dark:text-yellow-400',
        },
        overdue: {
            icon: AlertCircle,
            label: 'Overdue',
            className:
                'border-red-500 text-red-700 dark:border-red-600 dark:text-red-400 animate-pulse',
        },
    };

    const { icon: Icon, label, className } = config[status];

    return (
        <Badge variant="outline" className={cn('gap-1', className)}>
            <Icon className="h-3 w-3" />
            {label}
        </Badge>
    );
}

/**
 * Time display component
 */
function TimeDisplay({ schedule }: { schedule: ScheduleWithStatus }) {
    if (schedule.status === 'upcoming' && schedule.time_until_due_minutes) {
        return (
            <span className="text-sm text-muted-foreground">
                in {formatTimeRemaining(schedule.time_until_due_minutes)}
            </span>
        );
    }

    if (schedule.status === 'due') {
        if (schedule.time_until_due_minutes) {
            return (
                <span className="text-sm text-yellow-700 dark:text-yellow-400">
                    in {formatTimeRemaining(schedule.time_until_due_minutes)}
                </span>
            );
        }
        if (schedule.time_overdue_minutes) {
            return (
                <span className="text-sm text-yellow-700 dark:text-yellow-400">
                    +{formatTimeRemaining(schedule.time_overdue_minutes)}
                </span>
            );
        }
        return (
            <span className="text-sm text-yellow-700 dark:text-yellow-400">
                now
            </span>
        );
    }

    if (schedule.status === 'overdue' && schedule.time_overdue_minutes) {
        return (
            <span className="text-sm font-semibold text-red-700 dark:text-red-400">
                +{formatTimeRemaining(schedule.time_overdue_minutes)}
            </span>
        );
    }

    return null;
}

/**
 * Format time remaining in human-readable format
 */
function formatTimeRemaining(minutes: number): string {
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
