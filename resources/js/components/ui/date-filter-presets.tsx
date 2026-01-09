'use client';

import { X } from 'lucide-react';
import * as React from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
    const formatDate = (d: Date) => d.toISOString().split('T')[0];

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
}: DateFilterPresetsProps) {
    const [showCustom, setShowCustom] = React.useState(value.preset === 'custom');

    const handlePresetChange = (preset: string) => {
        if (preset === 'custom') {
            setShowCustom(true);
            onChange({ preset: 'custom', from: value.from, to: value.to });
            return;
        }

        setShowCustom(false);
        const range = calculateDateRange(preset);
        onChange({ ...range, preset });
    };

    const handleFromChange = (from: string) => {
        onChange({ ...value, from, preset: 'custom' });
    };

    const handleToChange = (to: string) => {
        onChange({ ...value, to, preset: 'custom' });
    };

    const handleClear = () => {
        setShowCustom(false);
        onChange({});
    };

    const hasActiveFilter = value.preset || value.from || value.to;

    return (
        <div className={`flex items-center gap-2 ${className || ''}`}>
            <Select
                value={value.preset || ''}
                onValueChange={handlePresetChange}
            >
                <SelectTrigger className="w-[160px]">
                    <SelectValue placeholder="Filter by date" />
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
                <div className="flex items-center gap-2">
                    <Input
                        type="date"
                        value={value.from || ''}
                        onChange={(e) => handleFromChange(e.target.value)}
                        className="w-[140px]"
                        aria-label="From date"
                    />
                    <span className="text-muted-foreground">to</span>
                    <Input
                        type="date"
                        value={value.to || ''}
                        onChange={(e) => handleToChange(e.target.value)}
                        className="w-[140px]"
                        aria-label="To date"
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
