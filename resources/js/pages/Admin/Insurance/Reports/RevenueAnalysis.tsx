import { Badge } from '@/components/ui/badge';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Banknote,
    DollarSign,
    TrendingUp,
    Wallet,
} from 'lucide-react';
import { useState } from 'react';

interface MonthlyTrend {
    month: string;
    insurance: number;
    cash: number;
    total: number;
}

interface RevenueAnalysisData {
    insurance_revenue: number;
    cash_revenue: number;
    total_revenue: number;
    insurance_percentage: number;
    cash_percentage: number;
    monthly_trend: MonthlyTrend[];
    insurance_by_status: Record<string, { status: string; total_paid: number }>;
}

interface Props {
    data: RevenueAnalysisData;
    filters: {
        date_from: string;
        date_to: string;
    };
}

export default function RevenueAnalysis({ data, filters }: Props) {
    const [dateFrom, setDateFrom] = useState(filters.date_from);
    const [dateTo, setDateTo] = useState(filters.date_to);

    const formatCurrency = (amount: number) => {
        return `GHS ${amount.toFixed(2)}`;
    };

    const handleFilter = () => {
        router.get(
            '/admin/insurance/reports/revenue-analysis',
            {
                date_from: dateFrom,
                date_to: dateTo,
            },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const handleReset = () => {
        const now = new Date();
        const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
        const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);

        setDateFrom(firstDay.toISOString().split('T')[0]);
        setDateTo(lastDay.toISOString().split('T')[0]);

        router.get(
            '/admin/insurance/reports/revenue-analysis',
            {},
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                {
                    title: 'Insurance Reports',
                    href: '/admin/insurance/reports',
                },
                { title: 'Revenue Analysis', href: '' },
            ]}
        >
            <Head title="Revenue Analysis Report" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <div className="mb-2">
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() =>
                                    router.visit('/admin/insurance/reports')
                                }
                            >
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Back to Reports
                            </Button>
                        </div>
                        <h1 className="flex items-center gap-2 text-3xl font-bold text-gray-900 dark:text-gray-100">
                            <TrendingUp className="h-8 w-8" />
                            Revenue Analysis Report
                        </h1>
                        <p className="mt-2 text-gray-600 dark:text-gray-400">
                            Compare insurance vs cash revenue with monthly
                            trends
                        </p>
                    </div>
                </div>

                {/* Filters */}
                <Card>
                    <CardHeader>
                        <CardTitle>Filters</CardTitle>
                        <CardDescription>
                            Filter revenue data by date range
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                            <div className="space-y-2">
                                <Label htmlFor="date_from">Date From</Label>
                                <Input
                                    id="date_from"
                                    type="date"
                                    value={dateFrom}
                                    onChange={(e) =>
                                        setDateFrom(e.target.value)
                                    }
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="date_to">Date To</Label>
                                <Input
                                    id="date_to"
                                    type="date"
                                    value={dateTo}
                                    onChange={(e) => setDateTo(e.target.value)}
                                />
                            </div>
                            <div className="flex items-end gap-2">
                                <Button onClick={handleFilter}>
                                    Apply Filters
                                </Button>
                                <Button variant="outline" onClick={handleReset}>
                                    Reset
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Summary Cards */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Total Revenue
                                    </p>
                                    <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                        {formatCurrency(data.total_revenue)}
                                    </p>
                                </div>
                                <DollarSign className="h-8 w-8 text-purple-600 dark:text-purple-400" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Insurance Revenue
                                    </p>
                                    <p className="text-2xl font-bold text-green-600 dark:text-green-400">
                                        {formatCurrency(data.insurance_revenue)}
                                    </p>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                        {data.insurance_percentage.toFixed(2)}%
                                        of total
                                    </p>
                                </div>
                                <Wallet className="h-8 w-8 text-green-600 dark:text-green-400" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Cash Revenue
                                    </p>
                                    <p className="text-2xl font-bold text-blue-600 dark:text-blue-400">
                                        {formatCurrency(data.cash_revenue)}
                                    </p>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                        {data.cash_percentage.toFixed(2)}% of
                                        total
                                    </p>
                                </div>
                                <Banknote className="h-8 w-8 text-blue-600 dark:text-blue-400" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Revenue Mix
                                    </p>
                                    <div className="mt-2 flex gap-2">
                                        <Badge
                                            variant="default"
                                            className="bg-green-600"
                                        >
                                            {data.insurance_percentage.toFixed(
                                                0,
                                            )}
                                            % INS
                                        </Badge>
                                        <Badge
                                            variant="secondary"
                                            className="bg-blue-600 text-white"
                                        >
                                            {data.cash_percentage.toFixed(0)}%
                                            CASH
                                        </Badge>
                                    </div>
                                </div>
                                <TrendingUp className="h-8 w-8 text-orange-600 dark:text-orange-400" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Monthly Trend */}
                <Card>
                    <CardHeader>
                        <CardTitle>
                            Monthly Revenue Trend (Last 6 Months)
                        </CardTitle>
                        <CardDescription>
                            Breakdown of insurance and cash revenue by month
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {data.monthly_trend.length > 0 ? (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Month</TableHead>
                                            <TableHead className="text-right">
                                                Insurance Revenue
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Cash Revenue
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Total Revenue
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Insurance %
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {data.monthly_trend.map((trend) => {
                                            const insurancePercent =
                                                trend.total > 0
                                                    ? (trend.insurance /
                                                          trend.total) *
                                                      100
                                                    : 0;

                                            return (
                                                <TableRow key={trend.month}>
                                                    <TableCell className="font-medium">
                                                        {trend.month}
                                                    </TableCell>
                                                    <TableCell className="text-right text-green-600 dark:text-green-400">
                                                        {formatCurrency(
                                                            trend.insurance,
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-right text-blue-600 dark:text-blue-400">
                                                        {formatCurrency(
                                                            trend.cash,
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-right font-semibold">
                                                        {formatCurrency(
                                                            trend.total,
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        <Badge variant="outline">
                                                            {insurancePercent.toFixed(
                                                                2,
                                                            )}
                                                            %
                                                        </Badge>
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })}
                                    </TableBody>
                                </Table>
                            </div>
                        ) : (
                            <div className="py-12 text-center">
                                <TrendingUp className="mx-auto mb-4 h-16 w-16 text-gray-300 dark:text-gray-600" />
                                <h3 className="mb-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    No trend data available
                                </h3>
                                <p className="text-gray-600 dark:text-gray-400">
                                    No revenue data found for the last 6 months.
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Revenue Comparison */}
                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Insurance Revenue Details</CardTitle>
                            <CardDescription>
                                Total insurance revenue breakdown
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                <div className="flex items-center justify-between rounded-lg border p-4 dark:border-gray-700">
                                    <div>
                                        <p className="text-sm text-gray-600 dark:text-gray-400">
                                            Total Insurance Revenue
                                        </p>
                                        <p className="text-2xl font-bold text-green-600 dark:text-green-400">
                                            {formatCurrency(
                                                data.insurance_revenue,
                                            )}
                                        </p>
                                    </div>
                                    <Wallet className="h-10 w-10 text-green-600 dark:text-green-400" />
                                </div>
                                <div className="rounded-lg bg-green-50 p-4 dark:bg-green-950/20">
                                    <p className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Percentage of Total Revenue
                                    </p>
                                    <p className="text-3xl font-bold text-green-600 dark:text-green-400">
                                        {data.insurance_percentage.toFixed(2)}%
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Cash Revenue Details</CardTitle>
                            <CardDescription>
                                Total cash payment breakdown
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                <div className="flex items-center justify-between rounded-lg border p-4 dark:border-gray-700">
                                    <div>
                                        <p className="text-sm text-gray-600 dark:text-gray-400">
                                            Total Cash Revenue
                                        </p>
                                        <p className="text-2xl font-bold text-blue-600 dark:text-blue-400">
                                            {formatCurrency(data.cash_revenue)}
                                        </p>
                                    </div>
                                    <Banknote className="h-10 w-10 text-blue-600 dark:text-blue-400" />
                                </div>
                                <div className="rounded-lg bg-blue-50 p-4 dark:bg-blue-950/20">
                                    <p className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Percentage of Total Revenue
                                    </p>
                                    <p className="text-3xl font-bold text-blue-600 dark:text-blue-400">
                                        {data.cash_percentage.toFixed(2)}%
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
