import { router } from '@inertiajs/react';
import { format } from 'date-fns';
import { Calendar, CalendarDays, ChevronDown } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { type DateRange } from 'react-day-picker';

import { Button } from '@/components/ui/button';
import { Calendar as CalendarComponent } from '@/components/ui/calendar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

export type DatePreset = 'today' | 'yesterday' | 'week' | 'month' | 'last_month' | 'year' | 'custom';

export interface DateFilterState {
    preset: DatePreset;
    startDate: string | null;
    endDate: string | null;
}

interface DateRangeFilterProps {
    value: DateFilterState;
    onChange?: (filter: DateFilterState) => void;
    className?: string;
}

const presetLabels: Record<DatePreset, string> = {
    today: 'Today',
    yesterday: 'Yesterday',
    week: 'This Week',
    month: 'This Month',
    last_month: 'Last Month',
    year: 'This Year',
    custom: 'Custom Range',
};

export function DateRangeFilter({
    value,
    onChange,
    className,
}: DateRangeFilterProps) {
    const [isCalendarOpen, setIsCalendarOpen] = useState(false);
    const [dateRange, setDateRange] = useState<DateRange | undefined>(() => {
        if (value.startDate && value.endDate) {
            return {
                from: new Date(value.startDate),
                to: new Date(value.endDate),
            };
        }
        return undefined;
    });
    const calendarRef = useRef<HTMLDivElement>(null);

    // Close calendar on outside click
    useEffect(() => {
        if (!isCalendarOpen) return;

        const handlePointerDown = (e: PointerEvent) => {
            if (calendarRef.current && !calendarRef.current.contains(e.target as Node)) {
                setIsCalendarOpen(false);
            }
        };

        // Delay attaching so the dropdown's closing pointer event doesn't immediately trigger it
        const timer = setTimeout(() => {
            document.addEventListener('pointerdown', handlePointerDown);
        }, 100);

        return () => {
            clearTimeout(timer);
            document.removeEventListener('pointerdown', handlePointerDown);
        };
    }, [isCalendarOpen]);

    const handlePresetChange = (preset: DatePreset) => {
        if (preset === 'custom') {
            // Open the calendar popover after the dropdown closes
            setTimeout(() => setIsCalendarOpen(true), 0);
            return;
        }

        const newFilter: DateFilterState = {
            preset,
            startDate: null,
            endDate: null,
        };

        router.get(
            '/dashboard',
            { date_preset: preset },
            { preserveState: true, preserveScroll: true },
        );

        onChange?.(newFilter);
    };

    const handleDateRangeSelect = (range: DateRange | undefined) => {
        setDateRange(range);

        // Only submit once the user has selected two distinct dates (a real range).
        // react-day-picker sets from === to on the first click, so we wait for
        // the second click where from and to differ.
        if (range?.from && range?.to && range.from.getTime() !== range.to.getTime()) {
            const startDate = format(range.from, 'yyyy-MM-dd');
            const endDate = format(range.to, 'yyyy-MM-dd');

            const newFilter: DateFilterState = {
                preset: 'custom',
                startDate,
                endDate,
            };

            router.get(
                '/dashboard',
                {
                    date_preset: 'custom',
                    start_date: startDate,
                    end_date: endDate,
                },
                { preserveState: true, preserveScroll: true },
            );

            onChange?.(newFilter);
            setIsCalendarOpen(false);
        }
    };

    const getDisplayLabel = () => {
        if (value.preset === 'custom' && value.startDate && value.endDate) {
            const start = new Date(value.startDate);
            const end = new Date(value.endDate);
            return `${format(start, 'MMM d')} - ${format(end, 'MMM d, yyyy')}`;
        }
        return presetLabels[value.preset] || 'Today';
    };

    return (
        <div className={cn('relative flex items-center gap-2', className)}>
            {/* Preset dropdown */}
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="default"
                        size="sm"
                        className="h-9 gap-2 bg-blue-600 px-3 text-white hover:bg-blue-700 dark:bg-blue-600 dark:hover:bg-blue-700"
                    >
                        <CalendarDays className="h-4 w-4" />
                        <span className="hidden sm:inline">
                            {getDisplayLabel()}
                        </span>
                        <span className="sm:hidden">
                            {value.preset === 'custom'
                                ? 'Custom'
                                : getDisplayLabel()}
                        </span>
                        <ChevronDown className="h-3 w-3 opacity-70" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-48">
                    <DropdownMenuItem
                        onClick={() => handlePresetChange('today')}
                        className={cn(value.preset === 'today' && 'bg-accent')}
                    >
                        Today
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        onClick={() => handlePresetChange('yesterday')}
                        className={cn(value.preset === 'yesterday' && 'bg-accent')}
                    >
                        Yesterday
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        onClick={() => handlePresetChange('week')}
                        className={cn(value.preset === 'week' && 'bg-accent')}
                    >
                        This Week
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        onClick={() => handlePresetChange('month')}
                        className={cn(value.preset === 'month' && 'bg-accent')}
                    >
                        This Month
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        onClick={() => handlePresetChange('last_month')}
                        className={cn(value.preset === 'last_month' && 'bg-accent')}
                    >
                        Last Month
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        onClick={() => handlePresetChange('year')}
                        className={cn(value.preset === 'year' && 'bg-accent')}
                    >
                        This Year
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        onClick={() => handlePresetChange('custom')}
                        className={cn(
                            'gap-2',
                            value.preset === 'custom' && 'bg-accent',
                        )}
                    >
                        <Calendar className="h-4 w-4" />
                        Custom Range...
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            {/* Calendar panel — rendered as a positioned div to avoid Radix dismiss conflicts */}
            {isCalendarOpen && (
                <div
                    ref={calendarRef}
                    className="absolute right-0 top-full z-50 mt-2 rounded-md border border-slate-200 bg-white p-0 shadow-md dark:border-slate-800 dark:bg-slate-950"
                >
                    <CalendarComponent
                        mode="range"
                        selected={dateRange}
                        onSelect={handleDateRangeSelect}
                        numberOfMonths={2}
                        defaultMonth={dateRange?.from || new Date()}
                    />
                </div>
            )}
        </div>
    );
}
