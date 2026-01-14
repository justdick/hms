import { router } from '@inertiajs/react';
import { format } from 'date-fns';
import { Calendar, CalendarDays, ChevronDown } from 'lucide-react';
import { useState } from 'react';
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
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

export type DatePreset = 'today' | 'week' | 'month' | 'year' | 'custom';

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
    week: 'This Week',
    month: 'This Month',
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

    const handlePresetChange = (preset: DatePreset) => {
        if (preset === 'custom') {
            setIsCalendarOpen(true);
            return;
        }

        const newFilter: DateFilterState = {
            preset,
            startDate: null,
            endDate: null,
        };

        // Navigate with new filter
        router.get(
            '/dashboard',
            { date_preset: preset },
            { preserveState: true, preserveScroll: true },
        );

        onChange?.(newFilter);
    };

    const handleDateRangeSelect = (range: DateRange | undefined) => {
        setDateRange(range);

        if (range?.from && range?.to) {
            const startDate = format(range.from, 'yyyy-MM-dd');
            const endDate = format(range.to, 'yyyy-MM-dd');

            const newFilter: DateFilterState = {
                preset: 'custom',
                startDate,
                endDate,
            };

            // Navigate with custom date range
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
        <div className={cn('flex items-center gap-2', className)}>
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
                        onClick={() => handlePresetChange('year')}
                        className={cn(value.preset === 'year' && 'bg-accent')}
                    >
                        This Year
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <Popover
                        open={isCalendarOpen}
                        onOpenChange={setIsCalendarOpen}
                    >
                        <PopoverTrigger asChild>
                            <DropdownMenuItem
                                onSelect={(e) => e.preventDefault()}
                                className={cn(
                                    'gap-2',
                                    value.preset === 'custom' && 'bg-accent',
                                )}
                            >
                                <Calendar className="h-4 w-4" />
                                Custom Range...
                            </DropdownMenuItem>
                        </PopoverTrigger>
                        <PopoverContent
                            className="w-auto p-0"
                            align="end"
                            side="left"
                        >
                            <CalendarComponent
                                mode="range"
                                selected={dateRange}
                                onSelect={handleDateRangeSelect}
                                numberOfMonths={2}
                                defaultMonth={dateRange?.from || new Date()}
                            />
                        </PopoverContent>
                    </Popover>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}
