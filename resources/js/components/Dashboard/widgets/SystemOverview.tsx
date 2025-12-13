import { Link } from '@inertiajs/react';
import {
    Activity,
    ArrowRight,
    Building2,
    Clock,
} from 'lucide-react';

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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';

export interface DepartmentActivity {
    id: number;
    name: string;
    code: string;
    type: string;
    checkins_today: number;
    consultations_today: number;
    revenue_today: number;
    waiting_patients: number;
}

export interface SystemOverviewProps {
    departmentActivitySummary: DepartmentActivity[];
    viewAllHref?: string;
    className?: string;
}

/**
 * Format currency amount for display.
 */
function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('en-GH', {
        style: 'currency',
        currency: 'GHS',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount);
}

/**
 * Get badge variant based on department type.
 */
function getDepartmentTypeVariant(
    type: string,
): 'default' | 'secondary' | 'outline' {
    switch (type) {
        case 'opd':
            return 'default';
        case 'ipd':
            return 'secondary';
        default:
            return 'outline';
    }
}

/**
 * Format department type for display.
 */
function formatDepartmentType(type: string): string {
    const typeMap: Record<string, string> = {
        opd: 'OPD',
        ipd: 'IPD',
        lab: 'Lab',
        pharmacy: 'Pharmacy',
        radiology: 'Radiology',
        emergency: 'Emergency',
    };
    return typeMap[type?.toLowerCase()] || type?.toUpperCase() || 'Other';
}

export function SystemOverview({
    departmentActivitySummary,
    viewAllHref,
    className,
}: SystemOverviewProps) {
    // Calculate totals for summary
    const totals = departmentActivitySummary.reduce(
        (acc, dept) => ({
            checkins: acc.checkins + dept.checkins_today,
            consultations: acc.consultations + dept.consultations_today,
            revenue: acc.revenue + dept.revenue_today,
            waiting: acc.waiting + dept.waiting_patients,
        }),
        { checkins: 0, consultations: 0, revenue: 0, waiting: 0 },
    );

    // Find max values for progress bars
    const maxCheckins = Math.max(
        ...departmentActivitySummary.map((d) => d.checkins_today),
        1,
    );

    return (
        <Card className={cn('', className)}>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
                <div className="flex items-center gap-2">
                    <Activity className="h-5 w-5 text-primary" />
                    <div>
                        <CardTitle className="text-base font-semibold">
                            System Overview
                        </CardTitle>
                        <CardDescription>
                            Department activity summary for today
                        </CardDescription>
                    </div>
                </div>
                {viewAllHref && (
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={viewAllHref}>
                            View All
                            <ArrowRight className="ml-1 h-4 w-4" />
                        </Link>
                    </Button>
                )}
            </CardHeader>
            <CardContent>
                {departmentActivitySummary.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-8 text-muted-foreground">
                        <Building2 className="mb-2 h-8 w-8 opacity-50" />
                        <span>No department activity today</span>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {/* Summary Stats */}
                        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 border-b pb-4">
                            <div className="flex flex-col items-center">
                                <span className="text-xl sm:text-2xl font-bold">
                                    {totals.checkins}
                                </span>
                                <span className="text-[10px] sm:text-xs text-muted-foreground">
                                    Check-ins
                                </span>
                            </div>
                            <div className="flex flex-col items-center">
                                <span className="text-xl sm:text-2xl font-bold">
                                    {totals.consultations}
                                </span>
                                <span className="text-[10px] sm:text-xs text-muted-foreground">
                                    Consultations
                                </span>
                            </div>
                            <div className="flex flex-col items-center">
                                <span className="text-xl sm:text-2xl font-bold text-amber-600">
                                    {totals.waiting}
                                </span>
                                <span className="text-[10px] sm:text-xs text-muted-foreground">
                                    Waiting
                                </span>
                            </div>
                            <div className="flex flex-col items-center">
                                <span className="text-sm sm:text-lg font-bold text-green-600">
                                    {formatCurrency(totals.revenue)}
                                </span>
                                <span className="text-[10px] sm:text-xs text-muted-foreground">
                                    Revenue
                                </span>
                            </div>
                        </div>

                        {/* Department Table */}
                        <div className="-mx-6 overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Department</TableHead>
                                        <TableHead className="text-center">
                                            Check-ins
                                        </TableHead>
                                        <TableHead className="text-center hidden sm:table-cell">
                                            Consultations
                                        </TableHead>
                                        <TableHead className="text-center">
                                            Waiting
                                        </TableHead>
                                        <TableHead className="text-right hidden md:table-cell">
                                            Revenue
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {departmentActivitySummary.map((dept) => (
                                        <TableRow key={dept.id}>
                                            <TableCell>
                                                <div className="flex flex-col gap-1">
                                                    <div className="flex items-center gap-2">
                                                        <span className="font-medium">
                                                            {dept.name}
                                                        </span>
                                                        <Badge
                                                            variant={getDepartmentTypeVariant(
                                                                dept.type,
                                                            )}
                                                            className="text-xs"
                                                        >
                                                            {formatDepartmentType(
                                                                dept.type,
                                                            )}
                                                        </Badge>
                                                    </div>
                                                    {/* Activity bar */}
                                                    <div className="h-1.5 w-full max-w-[100px] overflow-hidden rounded-full bg-secondary">
                                                        <div
                                                            className="h-full rounded-full bg-primary transition-all"
                                                            style={{
                                                                width: `${(dept.checkins_today / maxCheckins) * 100}%`,
                                                            }}
                                                        />
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-center">
                                                <span className="font-medium">
                                                    {dept.checkins_today}
                                                </span>
                                            </TableCell>
                                            <TableCell className="text-center hidden sm:table-cell">
                                                <span className="font-medium">
                                                    {dept.consultations_today}
                                                </span>
                                            </TableCell>
                                            <TableCell className="text-center">
                                                {dept.waiting_patients > 0 ? (
                                                    <Badge
                                                        variant="outline"
                                                        className="bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-400"
                                                    >
                                                        <Clock className="mr-1 h-3 w-3" />
                                                        {dept.waiting_patients}
                                                    </Badge>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        0
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right hidden md:table-cell">
                                                <span className="font-medium text-green-600">
                                                    {formatCurrency(
                                                        dept.revenue_today,
                                                    )}
                                                </span>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
