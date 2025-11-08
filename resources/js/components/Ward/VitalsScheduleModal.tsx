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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useVitalsSchedule } from '@/hooks/use-vitals-schedule';
import { AlertCircle, Calendar, Clock } from 'lucide-react';
import { useEffect, useState } from 'react';

interface VitalsScheduleModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    wardId: number;
    admissionId: number;
    scheduleId?: number;
    currentInterval?: number;
    patientName?: string;
}

const PRESET_INTERVALS = [
    { label: '1 hour', value: 60 },
    { label: '2 hours', value: 120 },
    { label: '4 hours', value: 240 },
    { label: '6 hours', value: 360 },
    { label: '8 hours', value: 480 },
    { label: '12 hours', value: 720 },
];

export function VitalsScheduleModal({
    open,
    onOpenChange,
    wardId,
    admissionId,
    scheduleId,
    currentInterval,
    patientName,
}: VitalsScheduleModalProps) {
    const [selectedPreset, setSelectedPreset] = useState<string>('');
    const [customInterval, setCustomInterval] = useState<string>('');
    const [error, setError] = useState<string>('');

    const { creating, updating, createSchedule, updateSchedule } =
        useVitalsSchedule({
            wardId,
            admissionId,
            scheduleId,
        });

    const isLoading = creating || updating;
    const isEditing = !!scheduleId;

    // Initialize form with current interval if editing
    useEffect(() => {
        if (open && currentInterval) {
            const preset = PRESET_INTERVALS.find(
                (p) => p.value === currentInterval,
            );
            if (preset) {
                setSelectedPreset(preset.value.toString());
                setCustomInterval('');
            } else {
                setSelectedPreset('custom');
                setCustomInterval(currentInterval.toString());
            }
        } else if (open) {
            // Default to 4 hours for new schedules
            setSelectedPreset('240');
            setCustomInterval('');
        }
        setError('');
    }, [open, currentInterval]);

    const getIntervalMinutes = (): number | null => {
        if (selectedPreset === 'custom') {
            const minutes = parseInt(customInterval, 10);
            if (isNaN(minutes) || minutes < 15 || minutes > 1440) {
                return null;
            }
            return minutes;
        }

        const minutes = parseInt(selectedPreset, 10);
        return isNaN(minutes) ? null : minutes;
    };

    const calculateNextDueTimes = (intervalMinutes: number): Date[] => {
        const now = new Date();
        const times: Date[] = [];

        for (let i = 1; i <= 3; i++) {
            const nextTime = new Date(now.getTime() + intervalMinutes * 60000 * i);
            times.push(nextTime);
        }

        return times;
    };

    const formatDateTime = (date: Date): string => {
        return date.toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const formatDuration = (minutes: number): string => {
        if (minutes < 60) {
            return `${minutes} minute${minutes !== 1 ? 's' : ''}`;
        }
        const hours = Math.floor(minutes / 60);
        const remainingMinutes = minutes % 60;
        if (remainingMinutes === 0) {
            return `${hours} hour${hours !== 1 ? 's' : ''}`;
        }
        return `${hours}h ${remainingMinutes}m`;
    };

    const handleSave = async () => {
        setError('');

        const intervalMinutes = getIntervalMinutes();

        if (!intervalMinutes) {
            setError(
                'Please enter a valid interval between 15 minutes and 24 hours (1440 minutes)',
            );
            return;
        }

        try {
            if (isEditing) {
                await updateSchedule({ interval_minutes: intervalMinutes });
            } else {
                await createSchedule({ interval_minutes: intervalMinutes });
            }
            onOpenChange(false);
        } catch (err) {
            setError(
                err instanceof Error
                    ? err.message
                    : 'Failed to save vitals schedule',
            );
        }
    };

    const intervalMinutes = getIntervalMinutes();
    const nextDueTimes =
        intervalMinutes && intervalMinutes >= 15 && intervalMinutes <= 1440
            ? calculateNextDueTimes(intervalMinutes)
            : [];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? 'Edit' : 'Create'} Vitals Schedule
                    </DialogTitle>
                    <DialogDescription>
                        {patientName
                            ? `Set the vitals recording interval for ${patientName}`
                            : 'Set how often vitals should be recorded for this patient'}
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-6 py-4">
                    {/* Preset Interval Selection */}
                    <div className="space-y-2">
                        <Label htmlFor="interval-preset">
                            Recording Interval
                        </Label>
                        <Select
                            value={selectedPreset}
                            onValueChange={(value) => {
                                setSelectedPreset(value);
                                if (value !== 'custom') {
                                    setCustomInterval('');
                                }
                                setError('');
                            }}
                        >
                            <SelectTrigger id="interval-preset">
                                <SelectValue placeholder="Select interval" />
                            </SelectTrigger>
                            <SelectContent>
                                {PRESET_INTERVALS.map((preset) => (
                                    <SelectItem
                                        key={preset.value}
                                        value={preset.value.toString()}
                                    >
                                        {preset.label}
                                    </SelectItem>
                                ))}
                                <SelectItem value="custom">
                                    Custom interval
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Custom Interval Input */}
                    {selectedPreset === 'custom' && (
                        <div className="space-y-2">
                            <Label htmlFor="custom-interval">
                                Custom Interval (minutes)
                            </Label>
                            <Input
                                id="custom-interval"
                                type="number"
                                min="15"
                                max="1440"
                                placeholder="Enter minutes (15-1440)"
                                value={customInterval}
                                onChange={(e) => {
                                    setCustomInterval(e.target.value);
                                    setError('');
                                }}
                            />
                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                Enter a value between 15 minutes and 24 hours
                                (1440 minutes)
                            </p>
                        </div>
                    )}

                    {/* Error Message */}
                    {error && (
                        <div className="flex items-start gap-2 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/20 dark:text-red-200">
                            <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
                            <span>{error}</span>
                        </div>
                    )}

                    {/* Preview of Next Due Times */}
                    {nextDueTimes.length > 0 && (
                        <div className="space-y-3 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
                            <div className="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                <Calendar className="h-4 w-4" />
                                <span>Next 3 scheduled times</span>
                            </div>
                            <div className="space-y-2">
                                {nextDueTimes.map((time, index) => (
                                    <div
                                        key={index}
                                        className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400"
                                    >
                                        <Clock className="h-3.5 w-3.5" />
                                        <span className="font-mono">
                                            {formatDateTime(time)}
                                        </span>
                                        <span className="text-xs text-gray-500 dark:text-gray-500">
                                            (in{' '}
                                            {formatDuration(
                                                intervalMinutes! * (index + 1),
                                            )}
                                            )
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={isLoading}
                    >
                        Cancel
                    </Button>
                    <Button onClick={handleSave} disabled={isLoading}>
                        {isLoading
                            ? isEditing
                                ? 'Updating...'
                                : 'Creating...'
                            : isEditing
                              ? 'Update Schedule'
                              : 'Create Schedule'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
