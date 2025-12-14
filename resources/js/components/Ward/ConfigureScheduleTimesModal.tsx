import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { router } from '@inertiajs/react';
import axios from 'axios';
import { Calendar, Clock, Info, Loader2, Plus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

interface Drug {
    id: number;
    name: string;
    strength?: string;
    form?: string;
}

interface SmartSchedulePattern {
    type: 'standard' | 'split_dose' | 'custom_interval' | 'taper';
    // Standard pattern
    frequency_code?: string;
    times_per_day?: number;
    // Split dose pattern
    pattern?: {
        morning: number;
        noon: number;
        evening: number;
    };
    daily_total?: number;
    // Custom interval pattern
    intervals_hours?: number[];
    dose_per_interval?: number;
    total_doses?: number;
    // Taper pattern
    doses?: number[];
    duration_days?: number;
}

// MAR schedule pattern (used for configuring administration times)
interface MARSchedulePattern {
    day_1?: string[];
    day_2?: string[];
    subsequent?: string[];
    [key: string]: string[] | undefined;
}

interface Prescription {
    id: number;
    drug?: Drug;
    medication_name: string;
    frequency?: string;
    duration?: string;
    dose_quantity?: string;
    // Smart input pattern (from prescription creation)
    schedule_pattern?: SmartSchedulePattern;
    // MAR schedule pattern (from schedule configuration) - stored separately
    mar_schedule_pattern?: MARSchedulePattern;
}

interface SchedulePattern {
    day_1: string[];
    subsequent: string[];
    [key: string]: string[];
}

interface ConfigureScheduleTimesModalProps {
    prescription: Prescription | null;
    isOpen: boolean;
    onClose: () => void;
    isReconfigure?: boolean;
}

/**
 * Check if the schedule pattern is a smart input pattern (from Smart Mode prescription)
 */
function isSmartSchedulePattern(
    pattern: Prescription['schedule_pattern'],
): pattern is SmartSchedulePattern {
    if (!pattern) return false;
    return (
        'type' in pattern &&
        ['standard', 'split_dose', 'custom_interval', 'taper'].includes(
            pattern.type as string,
        )
    );
}

/**
 * Format smart schedule pattern for display
 */
function formatSmartPattern(pattern: SmartSchedulePattern): string {
    switch (pattern.type) {
        case 'split_dose':
            if (pattern.pattern) {
                const { morning, noon, evening } = pattern.pattern;
                return `${morning}-${noon}-${evening} (${pattern.daily_total}/day)`;
            }
            return 'Split dose pattern';

        case 'custom_interval':
            if (pattern.intervals_hours) {
                const intervals = pattern.intervals_hours
                    .map((h) => `${h}h`)
                    .join(', ');
                return `${pattern.dose_per_interval} at ${intervals} (${pattern.total_doses} doses total)`;
            }
            return 'Custom interval schedule';

        case 'taper':
            if (pattern.doses) {
                return `${pattern.doses.join('-')} taper over ${pattern.duration_days} days`;
            }
            return 'Taper schedule';

        case 'standard':
            return `${pattern.frequency_code} (${pattern.times_per_day}x daily)`;

        default:
            return 'Custom schedule';
    }
}

/**
 * Get suggested times based on smart schedule pattern
 */
function getSuggestedTimesFromPattern(
    pattern: SmartSchedulePattern,
): string[] | null {
    switch (pattern.type) {
        case 'split_dose':
            // Suggest morning, noon, evening times based on pattern
            if (pattern.pattern) {
                const times: string[] = [];
                if (pattern.pattern.morning > 0) times.push('08:00');
                if (pattern.pattern.noon > 0) times.push('13:00');
                if (pattern.pattern.evening > 0) times.push('20:00');
                return times.length > 0 ? times : null;
            }
            return null;

        case 'custom_interval':
            // Convert hour intervals to suggested times starting from 08:00
            if (pattern.intervals_hours) {
                const baseHour = 8; // Start at 8 AM
                return pattern.intervals_hours.map((h) => {
                    const totalHours = baseHour + h;
                    const hour = totalHours % 24;
                    return `${hour.toString().padStart(2, '0')}:00`;
                });
            }
            return null;

        case 'taper':
            // For taper, suggest once daily
            return ['09:00'];

        case 'standard':
            // Use standard times based on frequency
            switch (pattern.times_per_day) {
                case 1:
                    return ['09:00'];
                case 2:
                    return ['09:00', '21:00'];
                case 3:
                    return ['09:00', '14:00', '21:00'];
                case 4:
                    return ['09:00', '13:00', '17:00', '21:00'];
                default:
                    return null;
            }

        default:
            return null;
    }
}

/**
 * Component to display the original prescription pattern from Smart Mode
 */
function SmartPatternReference({
    prescription,
    onUseSuggestedTimes,
}: {
    prescription: Prescription;
    onUseSuggestedTimes?: (times: string[]) => void;
}) {
    const pattern = prescription.schedule_pattern;

    if (!isSmartSchedulePattern(pattern)) {
        return null;
    }

    const suggestedTimes = getSuggestedTimesFromPattern(pattern);
    const patternDescription = formatSmartPattern(pattern);

    return (
        <div className="rounded-lg border border-purple-200 bg-purple-50 p-3 dark:border-purple-800 dark:bg-purple-950/20">
            <div className="flex items-start gap-2">
                <Info className="mt-0.5 h-4 w-4 flex-shrink-0 text-purple-600 dark:text-purple-400" />
                <div className="flex-1 space-y-2">
                    <div>
                        <p className="text-sm font-medium text-purple-900 dark:text-purple-200">
                            Original Prescription Pattern
                        </p>
                        <p className="text-sm text-purple-700 dark:text-purple-300">
                            {prescription.dose_quantity && (
                                <span className="font-medium">
                                    {prescription.dose_quantity}{' '}
                                </span>
                            )}
                            {patternDescription}
                        </p>
                    </div>

                    {pattern.type === 'custom_interval' &&
                        pattern.intervals_hours && (
                            <div className="mt-2">
                                <p className="text-xs font-medium text-purple-800 dark:text-purple-300">
                                    Prescribed intervals:
                                </p>
                                <div className="mt-1 flex flex-wrap gap-1">
                                    {pattern.intervals_hours.map((h, idx) => (
                                        <Badge
                                            key={idx}
                                            variant="outline"
                                            className="border-purple-300 bg-purple-100 text-purple-800 dark:border-purple-700 dark:bg-purple-900/50 dark:text-purple-200"
                                        >
                                            {h}h
                                        </Badge>
                                    ))}
                                </div>
                            </div>
                        )}

                    {pattern.type === 'split_dose' && pattern.pattern && (
                        <div className="mt-2">
                            <p className="text-xs font-medium text-purple-800 dark:text-purple-300">
                                Dose distribution:
                            </p>
                            <div className="mt-1 flex flex-wrap gap-1">
                                {pattern.pattern.morning > 0 && (
                                    <Badge
                                        variant="outline"
                                        className="border-purple-300 bg-purple-100 text-purple-800 dark:border-purple-700 dark:bg-purple-900/50 dark:text-purple-200"
                                    >
                                        Morning: {pattern.pattern.morning}
                                    </Badge>
                                )}
                                {pattern.pattern.noon > 0 && (
                                    <Badge
                                        variant="outline"
                                        className="border-purple-300 bg-purple-100 text-purple-800 dark:border-purple-700 dark:bg-purple-900/50 dark:text-purple-200"
                                    >
                                        Noon: {pattern.pattern.noon}
                                    </Badge>
                                )}
                                {pattern.pattern.evening > 0 && (
                                    <Badge
                                        variant="outline"
                                        className="border-purple-300 bg-purple-100 text-purple-800 dark:border-purple-700 dark:bg-purple-900/50 dark:text-purple-200"
                                    >
                                        Evening: {pattern.pattern.evening}
                                    </Badge>
                                )}
                            </div>
                        </div>
                    )}

                    {pattern.type === 'taper' && pattern.doses && (
                        <div className="mt-2">
                            <p className="text-xs font-medium text-purple-800 dark:text-purple-300">
                                Daily doses:
                            </p>
                            <div className="mt-1 flex flex-wrap gap-1">
                                {pattern.doses.map((dose, idx) => (
                                    <Badge
                                        key={idx}
                                        variant="outline"
                                        className="border-purple-300 bg-purple-100 text-purple-800 dark:border-purple-700 dark:bg-purple-900/50 dark:text-purple-200"
                                    >
                                        Day {idx + 1}: {dose}
                                    </Badge>
                                ))}
                            </div>
                        </div>
                    )}

                    {suggestedTimes &&
                        suggestedTimes.length > 0 &&
                        onUseSuggestedTimes && (
                            <div className="mt-2 flex items-center gap-2">
                                <span className="text-xs text-purple-700 dark:text-purple-300">
                                    Suggested times:{' '}
                                    {suggestedTimes.join(', ')}
                                </span>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="h-6 border-purple-300 px-2 text-xs text-purple-700 hover:bg-purple-100 dark:border-purple-700 dark:text-purple-300 dark:hover:bg-purple-900/50"
                                    onClick={() =>
                                        onUseSuggestedTimes(suggestedTimes)
                                    }
                                >
                                    Use suggested
                                </Button>
                            </div>
                        )}
                </div>
            </div>
        </div>
    );
}

export function ConfigureScheduleTimesModal({
    prescription,
    isOpen,
    onClose,
    isReconfigure = false,
}: ConfigureScheduleTimesModalProps) {
    const [loading, setLoading] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [pattern, setPattern] = useState<SchedulePattern>({
        day_1: [''],
        subsequent: [''],
    });
    const [customDays, setCustomDays] = useState<number[]>([]);

    // Handler to apply suggested times from smart pattern
    const handleUseSuggestedTimes = (suggestedTimes: string[]) => {
        if (suggestedTimes.length > 0) {
            setPattern((prev) => ({
                ...prev,
                day_1: [...suggestedTimes],
                subsequent: [...suggestedTimes],
            }));
        }
    };

    // Fetch smart defaults when modal opens
    useEffect(() => {
        if (isOpen && prescription && !isReconfigure) {
            setLoading(true);

            // Use axios which is already configured with CSRF tokens
            axios
                .get(`/api/prescriptions/${prescription.id}/smart-defaults`)
                .then((response) => {
                    const data = response.data.defaults;
                    setPattern({
                        day_1: data.day_1.length > 0 ? data.day_1 : [''],
                        subsequent:
                            data.subsequent.length > 0 ? data.subsequent : [''],
                    });
                })
                .catch(() => {
                    // API not available yet, use basic defaults
                    // Parse frequency to determine number of doses
                    const frequency =
                        prescription.frequency?.toLowerCase() || '';
                    let defaultTimes: string[] = ['09:00'];

                    if (
                        frequency.includes('twice') ||
                        frequency.includes('bid') ||
                        frequency.includes('2')
                    ) {
                        defaultTimes = ['09:00', '21:00'];
                    } else if (
                        frequency.includes('three') ||
                        frequency.includes('tid') ||
                        frequency.includes('3')
                    ) {
                        defaultTimes = ['09:00', '14:00', '21:00'];
                    } else if (
                        frequency.includes('four') ||
                        frequency.includes('qid') ||
                        frequency.includes('4')
                    ) {
                        defaultTimes = ['09:00', '13:00', '17:00', '21:00'];
                    } else if (
                        frequency.includes('six') ||
                        frequency.includes('6')
                    ) {
                        defaultTimes = [
                            '06:00',
                            '10:00',
                            '14:00',
                            '18:00',
                            '22:00',
                            '02:00',
                        ];
                    }

                    setPattern({
                        day_1: defaultTimes,
                        subsequent: defaultTimes,
                    });
                })
                .finally(() => {
                    setLoading(false);
                });
        } else if (isOpen && prescription && isReconfigure) {
            // Load existing MAR pattern for reconfiguration
            if (prescription.mar_schedule_pattern) {
                const existingPattern = { ...prescription.mar_schedule_pattern };
                const customDayNumbers: number[] = [];

                // Extract custom days (day_2, day_3, etc.)
                Object.keys(existingPattern).forEach((key) => {
                    const match = key.match(/^day_(\d+)$/);
                    if (match && parseInt(match[1]) > 1) {
                        customDayNumbers.push(parseInt(match[1]));
                    }
                });

                // Build the pattern object, ensuring day_1 and subsequent have defaults
                const newPattern: SchedulePattern = {
                    day_1:
                        existingPattern.day_1 &&
                        existingPattern.day_1.length > 0
                            ? existingPattern.day_1
                            : [''],
                    subsequent:
                        existingPattern.subsequent &&
                        existingPattern.subsequent.length > 0
                            ? existingPattern.subsequent
                            : [''],
                };

                // Add custom day patterns
                customDayNumbers.forEach((dayNum) => {
                    const dayKey = `day_${dayNum}`;
                    if (existingPattern[dayKey]) {
                        newPattern[dayKey] = existingPattern[dayKey]!;
                    }
                });

                setPattern(newPattern);
                setCustomDays(customDayNumbers.sort((a, b) => a - b));
            }
        }
    }, [isOpen, prescription, isReconfigure]);

    const handleSubmit = () => {
        if (!prescription) return;

        // Validate that all times are filled
        const allTimes = [
            ...pattern.day_1,
            ...pattern.subsequent,
            ...customDays.flatMap((day) => pattern[`day_${day}`] || []),
        ];

        if (allTimes.some((time) => !time || time.trim() === '')) {
            toast.error('Please fill in all time fields');
            return;
        }

        setSubmitting(true);

        const endpoint = isReconfigure
            ? `/api/prescriptions/${prescription.id}/reconfigure-schedule`
            : `/api/prescriptions/${prescription.id}/configure-schedule`;

        router.post(
            endpoint,
            { schedule_pattern: pattern },
            {
                onSuccess: () => {
                    toast.success(
                        isReconfigure
                            ? 'Schedule reconfigured successfully'
                            : 'Schedule configured successfully',
                    );
                    onClose();
                },
                onError: (errors) => {
                    toast.error(
                        errors.schedule_pattern ||
                            'Failed to configure schedule',
                    );
                },
                onFinish: () => {
                    setSubmitting(false);
                },
            },
        );
    };

    const updateTime = (day: string, index: number, value: string) => {
        setPattern((prev) => ({
            ...prev,
            [day]: prev[day].map((time, i) => (i === index ? value : time)),
        }));
    };

    const addDose = (day: string) => {
        setPattern((prev) => ({
            ...prev,
            [day]: [...prev[day], ''],
        }));
    };

    const removeDose = (day: string, index: number) => {
        setPattern((prev) => ({
            ...prev,
            [day]: prev[day].filter((_, i) => i !== index),
        }));
    };

    const addCustomDay = () => {
        const nextDay = customDays.length > 0 ? Math.max(...customDays) + 1 : 2;
        setCustomDays([...customDays, nextDay]);
        setPattern((prev) => ({
            ...prev,
            [`day_${nextDay}`]: [''],
        }));
    };

    const removeCustomDay = (dayNumber: number) => {
        setCustomDays(customDays.filter((d) => d !== dayNumber));
        setPattern((prev) => {
            const newPattern = { ...prev };
            delete newPattern[`day_${dayNumber}`];
            return newPattern;
        });
    };

    const calculatePreview = () => {
        if (!prescription?.duration) return null;

        const durationMatch = prescription.duration.match(/(\d+)/);
        if (!durationMatch) return null;

        const totalDays = parseInt(durationMatch[0]);
        let totalDoses = 0;
        const breakdown: string[] = [];

        // Day 1
        const day1Doses = pattern.day_1.filter((t) => t).length;
        totalDoses += day1Doses;
        breakdown.push(
            `Day 1: ${pattern.day_1.filter((t) => t).join(', ')} (${day1Doses} doses)`,
        );

        // Custom days
        customDays.forEach((dayNum) => {
            const dayKey = `day_${dayNum}`;
            const dayDoses = (pattern[dayKey] || []).filter((t) => t).length;
            if (dayDoses > 0) {
                totalDoses += dayDoses;
                breakdown.push(
                    `Day ${dayNum}: ${(pattern[dayKey] || []).filter((t) => t).join(', ')} (${dayDoses} doses)`,
                );
            }
        });

        // Subsequent days
        const subsequentDoses = pattern.subsequent.filter((t) => t).length;
        const customDayCount = customDays.length;
        const subsequentDayCount = totalDays - 1 - customDayCount;

        if (subsequentDayCount > 0) {
            totalDoses += subsequentDoses * subsequentDayCount;
            breakdown.push(
                `Days ${2 + customDayCount}-${totalDays}: ${pattern.subsequent.filter((t) => t).join(', ')} (${subsequentDoses} doses/day × ${subsequentDayCount} days)`,
            );
        }

        return { totalDoses, breakdown };
    };

    const preview = calculatePreview();

    if (!prescription) return null;

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-h-[90vh] max-w-3xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>
                        {isReconfigure ? 'Reconfigure' : 'Configure'} Medication
                        Schedule
                    </DialogTitle>
                    <DialogDescription>
                        {prescription.drug?.name ||
                            prescription.medication_name}{' '}
                        - {prescription.frequency}
                        {prescription.duration &&
                            ` for ${prescription.duration}`}
                    </DialogDescription>
                </DialogHeader>

                {/* Smart Pattern Reference - show original prescription pattern from Smart Mode */}
                {prescription &&
                    isSmartSchedulePattern(prescription.schedule_pattern) && (
                        <SmartPatternReference
                            prescription={prescription}
                            onUseSuggestedTimes={handleUseSuggestedTimes}
                        />
                    )}

                {/* Helpful hint */}
                <div className="rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-800 dark:bg-blue-950/20">
                    <div className="flex items-start gap-2">
                        <Clock className="mt-0.5 h-4 w-4 flex-shrink-0 text-blue-600 dark:text-blue-400" />
                        <p className="text-sm text-blue-900 dark:text-blue-200">
                            <span className="font-medium">Tip:</span> Click on
                            the{' '}
                            <Clock className="mx-1 inline h-3.5 w-3.5 text-primary" />{' '}
                            clock icon to open a time picker, or type the time
                            directly in HH:MM format.
                        </p>
                    </div>
                </div>

                {loading ? (
                    <div className="flex items-center justify-center py-8">
                        <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
                    </div>
                ) : (
                    <div className="space-y-6">
                        {/* Day 1 Section */}
                        <div className="space-y-3 rounded-lg border p-4">
                            <div className="flex items-center justify-between">
                                <Label className="flex items-center gap-2 text-base font-semibold">
                                    <Calendar className="h-4 w-4" />
                                    Day 1 (Today - Admission)
                                </Label>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => addDose('day_1')}
                                >
                                    <Plus className="mr-1 h-3 w-3" />
                                    Add Dose
                                </Button>
                            </div>

                            <div className="space-y-2">
                                {pattern.day_1.map((time, index) => (
                                    <div
                                        key={index}
                                        className="flex items-center gap-2"
                                    >
                                        <Label className="w-16 text-sm">
                                            Dose {index + 1}:
                                        </Label>
                                        <div className="group relative flex-1">
                                            <Clock className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-primary transition-colors group-hover:text-primary/80" />
                                            <Input
                                                type="time"
                                                value={time}
                                                onChange={(e) =>
                                                    updateTime(
                                                        'day_1',
                                                        index,
                                                        e.target.value,
                                                    )
                                                }
                                                className="cursor-pointer pl-10 transition-all hover:border-primary focus:border-primary"
                                                placeholder="Click to select time"
                                            />
                                        </div>
                                        {pattern.day_1.length > 1 && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    removeDose('day_1', index)
                                                }
                                            >
                                                <Trash2 className="h-4 w-4 text-red-500" />
                                            </Button>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Custom Days */}
                        {customDays.map((dayNumber) => (
                            <div
                                key={dayNumber}
                                className="space-y-3 rounded-lg border p-4"
                            >
                                <div className="flex items-center justify-between">
                                    <Label className="flex items-center gap-2 text-base font-semibold">
                                        <Calendar className="h-4 w-4" />
                                        Day {dayNumber}
                                    </Label>
                                    <div className="flex gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                addDose(`day_${dayNumber}`)
                                            }
                                        >
                                            <Plus className="mr-1 h-3 w-3" />
                                            Add Dose
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                removeCustomDay(dayNumber)
                                            }
                                        >
                                            <Trash2 className="h-4 w-4 text-red-500" />
                                        </Button>
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    {(pattern[`day_${dayNumber}`] || []).map(
                                        (time, index) => (
                                            <div
                                                key={index}
                                                className="flex items-center gap-2"
                                            >
                                                <Label className="w-16 text-sm">
                                                    Dose {index + 1}:
                                                </Label>
                                                <div className="group relative flex-1">
                                                    <Clock className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-primary transition-colors group-hover:text-primary/80" />
                                                    <Input
                                                        type="time"
                                                        value={time}
                                                        onChange={(e) =>
                                                            updateTime(
                                                                `day_${dayNumber}`,
                                                                index,
                                                                e.target.value,
                                                            )
                                                        }
                                                        className="cursor-pointer pl-10 transition-all hover:border-primary focus:border-primary"
                                                        placeholder="Click to select time"
                                                    />
                                                </div>
                                                {(
                                                    pattern[
                                                        `day_${dayNumber}`
                                                    ] || []
                                                ).length > 1 && (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            removeDose(
                                                                `day_${dayNumber}`,
                                                                index,
                                                            )
                                                        }
                                                    >
                                                        <Trash2 className="h-4 w-4 text-red-500" />
                                                    </Button>
                                                )}
                                            </div>
                                        ),
                                    )}
                                </div>
                            </div>
                        ))}

                        {/* Add Custom Day Button */}
                        <Button
                            type="button"
                            variant="outline"
                            onClick={addCustomDay}
                            className="w-full"
                        >
                            <Plus className="mr-2 h-4 w-4" />
                            Add Another Custom Day
                        </Button>

                        {/* Subsequent Days Section */}
                        <div className="space-y-3 rounded-lg border p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <Label className="flex items-center gap-2 text-base font-semibold">
                                        <Calendar className="h-4 w-4" />
                                        Subsequent Days
                                    </Label>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        These times will repeat for remaining
                                        days
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => addDose('subsequent')}
                                >
                                    <Plus className="mr-1 h-3 w-3" />
                                    Add Dose
                                </Button>
                            </div>

                            <div className="space-y-2">
                                {pattern.subsequent.map((time, index) => (
                                    <div
                                        key={index}
                                        className="flex items-center gap-2"
                                    >
                                        <Label className="w-16 text-sm">
                                            Dose {index + 1}:
                                        </Label>
                                        <div className="group relative flex-1">
                                            <Clock className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-primary transition-colors group-hover:text-primary/80" />
                                            <Input
                                                type="time"
                                                value={time}
                                                onChange={(e) =>
                                                    updateTime(
                                                        'subsequent',
                                                        index,
                                                        e.target.value,
                                                    )
                                                }
                                                className="cursor-pointer pl-10 transition-all hover:border-primary focus:border-primary"
                                                placeholder="Click to select time"
                                            />
                                        </div>
                                        {pattern.subsequent.length > 1 && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    removeDose(
                                                        'subsequent',
                                                        index,
                                                    )
                                                }
                                            >
                                                <Trash2 className="h-4 w-4 text-red-500" />
                                            </Button>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Schedule Preview */}
                        {preview && (
                            <div className="rounded-lg border bg-muted/50 p-4 dark:bg-muted/20">
                                <h4 className="mb-2 font-semibold">
                                    📊 Schedule Preview
                                </h4>
                                <div className="space-y-1 text-sm">
                                    {preview.breakdown.map((line, index) => (
                                        <p
                                            key={index}
                                            className="text-muted-foreground"
                                        >
                                            • {line}
                                        </p>
                                    ))}
                                    <p className="mt-2 font-medium">
                                        Total: {preview.totalDoses} doses
                                    </p>
                                </div>
                            </div>
                        )}

                        {/* Action Buttons */}
                        <div className="flex justify-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={onClose}
                            >
                                Cancel
                            </Button>
                            <Button
                                onClick={handleSubmit}
                                disabled={submitting}
                            >
                                {submitting && (
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                )}
                                {isReconfigure
                                    ? 'Reconfigure Schedule'
                                    : 'Generate Schedule'}
                            </Button>
                        </div>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
