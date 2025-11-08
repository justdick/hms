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
import { Calendar, Clock, Loader2, Plus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

interface Drug {
    id: number;
    name: string;
    strength?: string;
    form?: string;
}

interface Prescription {
    id: number;
    drug?: Drug;
    medication_name: string;
    frequency?: string;
    duration?: string;
    schedule_pattern?: {
        day_1?: string[];
        day_2?: string[];
        subsequent?: string[];
        [key: string]: string[] | undefined;
    };
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
                    const frequency = prescription.frequency?.toLowerCase() || '';
                    let defaultTimes: string[] = ['09:00'];
                    
                    if (frequency.includes('twice') || frequency.includes('bid') || frequency.includes('2')) {
                        defaultTimes = ['09:00', '21:00'];
                    } else if (frequency.includes('three') || frequency.includes('tid') || frequency.includes('3')) {
                        defaultTimes = ['09:00', '14:00', '21:00'];
                    } else if (frequency.includes('four') || frequency.includes('qid') || frequency.includes('4')) {
                        defaultTimes = ['09:00', '13:00', '17:00', '21:00'];
                    } else if (frequency.includes('six') || frequency.includes('6')) {
                        defaultTimes = ['06:00', '10:00', '14:00', '18:00', '22:00', '02:00'];
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
            // Load existing pattern for reconfiguration
            if (prescription.schedule_pattern) {
                const existingPattern = { ...prescription.schedule_pattern };
                const customDayNumbers: number[] = [];

                // Extract custom days (day_2, day_3, etc.)
                Object.keys(existingPattern).forEach((key) => {
                    const match = key.match(/^day_(\d+)$/);
                    if (match && parseInt(match[1]) > 1) {
                        customDayNumbers.push(parseInt(match[1]));
                    }
                });

                setPattern({
                    day_1:
                        existingPattern.day_1 && existingPattern.day_1.length > 0
                            ? existingPattern.day_1
                            : [''],
                    subsequent:
                        existingPattern.subsequent &&
                        existingPattern.subsequent.length > 0
                            ? existingPattern.subsequent
                            : [''],
                    ...existingPattern,
                });
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
        breakdown.push(`Day 1: ${pattern.day_1.filter((t) => t).join(', ')} (${day1Doses} doses)`);

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
                        {prescription.drug?.name || prescription.medication_name}{' '}
                        - {prescription.frequency}
                        {prescription.duration && ` for ${prescription.duration}`}
                    </DialogDescription>
                </DialogHeader>

                {/* Helpful hint */}
                <div className="rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-800 dark:bg-blue-950/20">
                    <div className="flex items-start gap-2">
                        <Clock className="mt-0.5 h-4 w-4 flex-shrink-0 text-blue-600 dark:text-blue-400" />
                        <p className="text-sm text-blue-900 dark:text-blue-200">
                            <span className="font-medium">Tip:</span> Click on the <Clock className="mx-1 inline h-3.5 w-3.5 text-primary" /> clock icon to open a time picker, or type the time directly in HH:MM format.
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
                                            <Clock className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-primary transition-colors group-hover:text-primary/80" />
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
                                                    <Clock className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-primary transition-colors group-hover:text-primary/80" />
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
                                            <Clock className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-primary transition-colors group-hover:text-primary/80" />
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
                                        <p key={index} className="text-muted-foreground">
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
