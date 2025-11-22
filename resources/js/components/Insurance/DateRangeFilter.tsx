import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/**
 * Props for the DateRangeFilter component
 */
interface DateRangeFilterProps {
    /** Start date in YYYY-MM-DD format */
    dateFrom: string;
    /** End date in YYYY-MM-DD format */
    dateTo: string;
    /** Callback when start date changes */
    onDateFromChange: (date: string) => void;
    /** Callback when end date changes */
    onDateToChange: (date: string) => void;
    /** Callback when Apply button is clicked */
    onApply: () => void;
    /** Callback when Reset button is clicked */
    onReset: () => void;
}

/**
 * DateRangeFilter - Shared date range filter component for analytics reports
 * 
 * Provides a consistent interface for filtering reports by date range.
 * Used across all analytics widgets in the Insurance Analytics Dashboard.
 * 
 * @example
 * ```tsx
 * <DateRangeFilter
 *   dateFrom="2025-01-01"
 *   dateTo="2025-01-31"
 *   onDateFromChange={setDateFrom}
 *   onDateToChange={setDateTo}
 *   onApply={handleApplyFilter}
 *   onReset={handleResetFilter}
 * />
 * ```
 */
export default function DateRangeFilter({
    dateFrom,
    dateTo,
    onDateFromChange,
    onDateToChange,
    onApply,
    onReset,
}: DateRangeFilterProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Date Range Filter</CardTitle>
                <CardDescription>
                    Filter all reports by date range
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div className="flex flex-wrap items-end gap-4">
                    <div className="flex-1 space-y-2">
                        <Label htmlFor="date_from">Date From</Label>
                        <Input
                            id="date_from"
                            type="date"
                            value={dateFrom}
                            onChange={(e) => onDateFromChange(e.target.value)}
                        />
                    </div>
                    <div className="flex-1 space-y-2">
                        <Label htmlFor="date_to">Date To</Label>
                        <Input
                            id="date_to"
                            type="date"
                            value={dateTo}
                            onChange={(e) => onDateToChange(e.target.value)}
                        />
                    </div>
                    <div className="flex gap-2">
                        <Button onClick={onApply}>Apply</Button>
                        <Button variant="outline" onClick={onReset}>
                            Reset
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
