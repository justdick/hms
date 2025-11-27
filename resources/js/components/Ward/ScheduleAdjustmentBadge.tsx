import { Badge } from '@/components/ui/badge';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { Edit3 } from 'lucide-react';

interface User {
    id: number;
    name: string;
}

interface MedicationScheduleAdjustment {
    id: number;
    original_time: string;
    adjusted_time: string;
    reason?: string;
    adjusted_by: User;
    created_at: string;
}

interface ScheduleAdjustmentBadgeProps {
    adjustments: MedicationScheduleAdjustment[];
}

export function ScheduleAdjustmentBadge({
    adjustments,
}: ScheduleAdjustmentBadgeProps) {
    if (!adjustments || adjustments.length === 0) {
        return null;
    }

    const formatDateTime = (dateString: string) => {
        return new Date(dateString).toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true,
        });
    };

    const formatTime = (dateString: string) => {
        return new Date(dateString).toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: true,
        });
    };

    // Sort adjustments by created_at (most recent first)
    const sortedAdjustments = [...adjustments].sort(
        (a, b) =>
            new Date(b.created_at).getTime() - new Date(a.created_at).getTime(),
    );

    const latestAdjustment = sortedAdjustments[0];

    return (
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger asChild>
                    <Badge
                        variant="outline"
                        className="cursor-help gap-1 border-blue-500 bg-blue-50 text-blue-700 dark:border-blue-600 dark:bg-blue-950/30 dark:text-blue-400"
                    >
                        <Edit3 className="h-3 w-3" />
                        Adjusted
                    </Badge>
                </TooltipTrigger>
                <TooltipContent className="max-w-sm">
                    <div className="space-y-2">
                        <p className="font-semibold">Adjustment History</p>
                        {sortedAdjustments.map((adjustment, index) => (
                            <div
                                key={adjustment.id}
                                className="space-y-1 border-t pt-2 text-xs first:border-t-0 first:pt-0"
                            >
                                <p className="font-medium">
                                    {index === 0
                                        ? 'Latest'
                                        : `Change ${sortedAdjustments.length - index}`}
                                </p>
                                <p>
                                    <span className="text-muted-foreground">
                                        From:
                                    </span>{' '}
                                    {formatTime(adjustment.original_time)}
                                </p>
                                <p>
                                    <span className="text-muted-foreground">
                                        To:
                                    </span>{' '}
                                    {formatTime(adjustment.adjusted_time)}
                                </p>
                                <p>
                                    <span className="text-muted-foreground">
                                        By:
                                    </span>{' '}
                                    {adjustment.adjusted_by.name}
                                </p>
                                <p>
                                    <span className="text-muted-foreground">
                                        When:
                                    </span>{' '}
                                    {formatDateTime(adjustment.created_at)}
                                </p>
                                {adjustment.reason && (
                                    <p>
                                        <span className="text-muted-foreground">
                                            Reason:
                                        </span>{' '}
                                        {adjustment.reason}
                                    </p>
                                )}
                            </div>
                        ))}
                    </div>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}
