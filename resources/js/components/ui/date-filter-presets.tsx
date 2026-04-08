'use client';

import { format } from 'date-fns';
import { X } from 'lucide-react';
import * as React from 'react';
import { type DateRange } from 'react-day-picker';

import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export interface DateFilterValue {
    from?: string;
    to?: string;
    preset?: string;
}

interface DateFilterPresetsProps {
    value: DateFilterValue;
    onChange: (value: DateFilterValue) => void;
    className?: string;
    variant?: 'default' | 'primary';
}

const presets = [
    { label: 'Today', value: 'today' },
    { label: 'Yesterday', value: 'yesterday' },
    { label: 'This Week', value: 'this_week' },
    { label: 'Last Week', value: 'last_week' },
    { label: 'This Month', value: 'this_month' },
    { label: 'Last Month', value: 'last_month' },
    { label: 'Custom Range', value: 'custom' },
] as const;

/**
 * Calculate date range for a given preset
 * @param preset - The preset identifier
 * @returns Object with from and to dates in YYYY-MM-DD format
 */
export function calculateDateRange(preset: string): { from: string; to: string } {
    const today = new Date();
    const formatDate = (d: Date) =>
        `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

    switch (preset) {
        case 'today':
            return { from: formatDate(today), to: formatDate(today) };

        case 'yesterday': {
            const yesterday = new Date(today);
            yesterday.setDate(yesterday.getDate() - 1);
            return { from: formatDate(yesterday), to: formatDate(yesterday) };
        }

        case 'this_week': {
            // Week starts on Sunday (getDay() returns 0 for Sunday)
            const weekStart = new Date(today);
            weekStart.setDate(today.getDate() - today.getDay());
            return { from: formatDate(weekStart), to: formatDate(today) };
        }

        case 'last_week': {
            // Last week: Sunday to Saturday of the previous week
            const lastWeekEnd = new Date(today);
            lastWeekEnd.setDate(today.getDate() - today.getDay() - 1);
            const lastWeekStart = new Date(lastWeekEnd);
            lastWeekStart.setDate(lastWeekEnd.getDate() - 6);
            return { from: formatDate(lastWeekStart), to: formatDate(lastWeekEnd) };
        }

        case 'this_month': {
            const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
            return { from: formatDate(monthStart), to: formatDate(today) };
        }

        case 'last_month': {
            const lastMonthEnd = new Date(today.getFullYear(), today.getMonth(), 0);
            const lastMonthStart = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            return { from: formatDate(lastMonthStart), to: formatDate(lastMonthEnd) };
        }

        default:
            return { from: '', to: '' };
    }
}

export function DateFilterPresets({
    value,
    onChange,
    className,
    variant = 'default',
}: DateFilterPresetsProps) {
    const [showCustom, setShowCustom] = React.useState(value.preset === 'custom');
    const [dateRange, setDateRange] = React.useState<DateRange | undefined>(() => {
        if (value.from && value.to) {
            return { from: new Date(value.from + 'T00:00:00'), to: new Date(value.to + 'T00:00:00') };
        }
        return undefined;
    });
    const calendarRef = React.useRef<HTMLDivElement>(null);
    const clickCountRef = React.useRef(0);

    // Close calendar on outside click
    React.useEffect(() => {
        if (!showCustom) return;

        const handlePointerDown = (e: PointerEvent) => {
            if (calendarRef.current && !calendarRef.current.contains(e.target as Node)) {
                setShowCustom(false);
            }
        };

        const timer = setTimeout(() => {
            document.addEventListener('pointerdown', handlePointerDown);
        }, 100);

        return () => {
            clearTimeout(timer);
            document.removeEventListener('pointerdown', handlePointerDown);
        };
    }, [showCustom]);

    const handlePresetChange = (preset: string) => {
        if (preset === 'custom') {
            setDateRange(undefined);
            clickCountRef.current = 0;
            setTimeout(() => setShowCustom(true), 0);
            return;
        }

        setShowCustom(false);
        const range = calculateDateRange(preset);
        onChange({ ...range, preset });
    };

    const handleDateRangeSelect = (range: DateRange | undefined) => {
        clickCountRef.current += 1;
        setDateRange(range);

        // Only submit on the second click (when user has picked both start and end)
        if (clickCountRef.current >= 2 && range?.from && range?.to) {
            const from = format(range.from, 'yyyy-MM-dd');
            const to = format(range.to, 'yyyy-MM-dd');
            onChange({ from, to, preset: 'custom' });
            setShowCustom(false);
            clickCountRef.current = 0;
        }
    };

    const handleClear = () => {
        setShowCustom(false);
        setDateRange(undefined);
        onChange({});
    };

    const hasActiveFilter = value.preset || value.from || value.to;

    const getDisplayLabel = () => {
        if (value.preset === 'custom' && value.from && value.to) {
            const start = new Date(value.from + 'T00:00:00');
            const end = new Date(value.to + 'T00:00:00');
            return `${format(start, 'MMM d')} – ${format(end, 'MMM d, yyyy')}`;
        }
        const preset = presets.find((p) => p.value === value.preset);
        return preset?.label || 'Filter by date';
    };

    const triggerClassName = variant === 'primary'
        ? 'w-[200px] border-blue-500 bg-blue-50 text-blue-700 hover:bg-blue-100 dark:border-blue-400 dark:bg-blue-950 dark:text-blue-300 dark:hover:bg-blue-900'
        : 'w-[200px]';

    return (
        <div className={`relative flex items-center gap-2 ${className || ''}`}>
            <Select
                value={value.preset || ''}
                onValueChange={handlePresetChange}
            >
                <SelectTrigger className={triggerClassName}>
                    <SelectValue placeholder="Filter by date">
                        {getDisplayLabel()}
                    </SelectValue>
                </SelectTrigger>
                <SelectContent>
                    {presets.map((preset) => (
                        <SelectItem key={preset.value} value={preset.value}>
                            {preset.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            {showCustom && (
                <div
                    ref={calendarRef}
                    className="absolute top-full right-0 z-50 mt-2 rounded-md border border-slate-200 bg-white p-0 shadow-md dark:border-slate-800 dark:bg-slate-950"
                >
                    <Calendar
                        mode="range"
                        selected={dateRange}
                        onSelect={handleDateRangeSelect}
                        numberOfMonths={2}
                        defaultMonth={dateRange?.from || new Date()}
                    />
                </div>
            )}

            {hasActiveFilter && (
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={handleClear}
                    aria-label="Clear date filter"
                >
                    <X className="h-4 w-4" />
                </Button>
            )}
        </div>
    );
}
