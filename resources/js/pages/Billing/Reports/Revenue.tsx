import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
    ArrowDown,
    ArrowUp,
    BarChart3,
    Calendar,
    DollarSign,
    FileSpreadsheet,
    FileText,
    Filter,
    TrendingUp,
} from 'lucide-react';
import { useState } from 'react';
import {
    Area,
    AreaChart,
    Bar,
    BarChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

interface Department {
    id: number;
    name: string;
}

interface GroupedDataItem {
    key: string | number;
    label: string;
    total: number;
    count: number;
    average: number;
}

interface DailyTrendItem {
    date: string;
    label: string;
    total: number;
    count: number;
}

interface Summary {
    total_revenue: number;
    transaction_count: number;
    average_transaction: number;
    period_start: string;
    period_end: string;
}

interface Comparison {
    previous_total: number;
    previous_count: number;
    percentage_change: number;
    previous_period_start: string;
    previous_period_end: string;
}


interface Report {
    grouped_data: GroupedDataItem[];
    summary: Summary;
    comparison: Comparison;
    daily_trend: DailyTrendItem[];
}

interface Filters {
    start_date: string;
    end_date: string;
    group_by: string;
}

interface Props {
    report: Report;
    departments: Department[];
    filters: Filters;
}

export default function RevenueReport({ report, departments, filters }: Props) {
    const [localFilters, setLocalFilters] = useState<Filters>(filters);

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-GH', {
            style: 'currency',
            currency: 'GHS',
        }).format(amount);
    };

    const handleFilterChange = (key: keyof Filters, value: string) => {
        setLocalFilters((prev) => ({ ...prev, [key]: value }));
    };

    const applyFilters = () => {
        router.get('/billing/accounts/reports/revenue', {
            start_date: localFilters.start_date,
            end_date: localFilters.end_date,
            group_by: localFilters.group_by,
        }, {
            preserveState: true,
        });
    };

    const setQuickDateRange = (days: number) => {
        const endDate = new Date();
        const startDate = new Date();
        startDate.setDate(startDate.getDate() - days + 1);

        setLocalFilters((prev) => ({
            ...prev,
            start_date: startDate.toISOString().split('T')[0],
            end_date: endDate.toISOString().split('T')[0],
        }));
    };

    const exportToExcel = () => {
        const queryParams = new URLSearchParams();
        queryParams.append('start_date', localFilters.start_date);
        queryParams.append('end_date', localFilters.end_date);
        queryParams.append('group_by', localFilters.group_by);

        window.location.href = `/billing/accounts/reports/revenue/export/excel?${queryParams.toString()}`;
    };

    const exportToPdf = () => {
        const queryParams = new URLSearchParams();
        queryParams.append('start_date', localFilters.start_date);
        queryParams.append('end_date', localFilters.end_date);
        queryParams.append('group_by', localFilters.group_by);

        window.location.href = `/billing/accounts/reports/revenue/export/pdf?${queryParams.toString()}`;
    };

    const getGroupByLabel = (groupBy: string) => {
        const labels: Record<string, string> = {
            date: 'Date',
            department: 'Department',
            service_type: 'Service Type',
            payment_method: 'Payment Method',
            cashier: 'Cashier',
        };
        return labels[groupBy] || 'Category';
    };

    const isPositiveChange = report.comparison.percentage_change >= 0;

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Billing', href: '/billing' },
                { title: 'Accounts', href: '/billing/accounts' },
                { title: 'Revenue Report', href: '/billing/accounts/reports/revenue' },
            ]}
        >
            <Head title="Revenue Report" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                            Revenue Report
                        </h1>
                        <p className="text-gray-600 dark:text-gray-400">
                            Analyze hospital revenue by various dimensions
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={exportToExcel}>
                            <FileSpreadsheet className="mr-2 h-4 w-4" />
                            Export Excel
                        </Button>
                        <Button variant="outline" onClick={exportToPdf}>
                            <FileText className="mr-2 h-4 w-4" />
                            Export PDF
                        </Button>
                    </div>
                </div>

                {/* Summary Cards */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Total Revenue
                            </CardTitle>
                            <DollarSign className="h-4 w-4 text-green-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600">
                                {formatCurrency(report.summary.total_revenue)}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                {report.summary.period_start} to {report.summary.period_end}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Transactions
                            </CardTitle>
                            <BarChart3 className="h-4 w-4 text-blue-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-blue-600">
                                {report.summary.transaction_count.toLocaleString()}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Total payments processed
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Average Transaction
                            </CardTitle>
                            <TrendingUp className="h-4 w-4 text-purple-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-purple-600">
                                {formatCurrency(report.summary.average_transaction)}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Per transaction
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                vs Previous Period
                            </CardTitle>
                            {isPositiveChange ? (
                                <ArrowUp className="h-4 w-4 text-green-600" />
                            ) : (
                                <ArrowDown className="h-4 w-4 text-red-600" />
                            )}
                        </CardHeader>
                        <CardContent>
                            <div
                                className={`text-2xl font-bold ${
                                    isPositiveChange ? 'text-green-600' : 'text-red-600'
                                }`}
                            >
                                {isPositiveChange ? '+' : ''}
                                {report.comparison.percentage_change.toFixed(1)}%
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Previous: {formatCurrency(report.comparison.previous_total)}
                            </p>
                        </CardContent>
                    </Card>
                </div>


                {/* Daily Trend Chart */}
                {report.daily_trend.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Daily Revenue Trend</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="h-[300px]">
                                <ResponsiveContainer width="100%" height="100%">
                                    <AreaChart data={report.daily_trend}>
                                        <defs>
                                            <linearGradient id="colorRevenue" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="5%" stopColor="#16a34a" stopOpacity={0.3} />
                                                <stop offset="95%" stopColor="#16a34a" stopOpacity={0} />
                                            </linearGradient>
                                        </defs>
                                        <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
                                        <XAxis
                                            dataKey="label"
                                            tick={{ fontSize: 12 }}
                                            tickLine={false}
                                            axisLine={false}
                                        />
                                        <YAxis
                                            tick={{ fontSize: 12 }}
                                            tickLine={false}
                                            axisLine={false}
                                            tickFormatter={(value) => `${(value / 1000).toFixed(0)}k`}
                                        />
                                        <Tooltip
                                            formatter={(value: number) => [formatCurrency(value), 'Revenue']}
                                            labelFormatter={(label) => `Date: ${label}`}
                                            contentStyle={{
                                                backgroundColor: 'hsl(var(--background))',
                                                border: '1px solid hsl(var(--border))',
                                                borderRadius: '8px',
                                            }}
                                        />
                                        <Area
                                            type="monotone"
                                            dataKey="total"
                                            stroke="#16a34a"
                                            strokeWidth={2}
                                            fillOpacity={1}
                                            fill="url(#colorRevenue)"
                                        />
                                    </AreaChart>
                                </ResponsiveContainer>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Filters */}
                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Filter className="h-4 w-4" />
                            Filters
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-wrap items-end gap-4">
                            <div className="min-w-[150px]">
                                <label className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Start Date
                                </label>
                                <input
                                    type="date"
                                    value={localFilters.start_date}
                                    onChange={(e) =>
                                        handleFilterChange('start_date', e.target.value)
                                    }
                                    className="mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                                />
                            </div>

                            <div className="min-w-[150px]">
                                <label className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    End Date
                                </label>
                                <input
                                    type="date"
                                    value={localFilters.end_date}
                                    onChange={(e) =>
                                        handleFilterChange('end_date', e.target.value)
                                    }
                                    className="mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                                />
                            </div>

                            <div className="min-w-[180px]">
                                <label className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Group By
                                </label>
                                <Select
                                    value={localFilters.group_by}
                                    onValueChange={(value) =>
                                        handleFilterChange('group_by', value)
                                    }
                                >
                                    <SelectTrigger className="mt-1">
                                        <SelectValue placeholder="Select grouping" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="date">Date</SelectItem>
                                        <SelectItem value="department">Department</SelectItem>
                                        <SelectItem value="service_type">Service Type</SelectItem>
                                        <SelectItem value="payment_method">Payment Method</SelectItem>
                                        <SelectItem value="cashier">Cashier</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <Button onClick={applyFilters}>Apply Filters</Button>

                            <div className="flex gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setQuickDateRange(7)}
                                >
                                    Last 7 Days
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setQuickDateRange(30)}
                                >
                                    Last 30 Days
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setQuickDateRange(90)}
                                >
                                    Last 90 Days
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>


                {/* Grouped Data Visualization */}
                {localFilters.group_by !== 'date' && report.grouped_data.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Revenue by {getGroupByLabel(localFilters.group_by)}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="h-[300px]">
                                <ResponsiveContainer width="100%" height="100%">
                                    <BarChart data={report.grouped_data} layout="vertical">
                                        <CartesianGrid strokeDasharray="3 3" className="stroke-muted" />
                                        <XAxis
                                            type="number"
                                            tick={{ fontSize: 12 }}
                                            tickLine={false}
                                            axisLine={false}
                                            tickFormatter={(value) => `${(value / 1000).toFixed(0)}k`}
                                        />
                                        <YAxis
                                            type="category"
                                            dataKey="label"
                                            tick={{ fontSize: 12 }}
                                            tickLine={false}
                                            axisLine={false}
                                            width={120}
                                        />
                                        <Tooltip
                                            formatter={(value: number) => [formatCurrency(value), 'Revenue']}
                                            contentStyle={{
                                                backgroundColor: 'hsl(var(--background))',
                                                border: '1px solid hsl(var(--border))',
                                                borderRadius: '8px',
                                            }}
                                        />
                                        <Bar dataKey="total" fill="#3b82f6" radius={[0, 4, 4, 0]} />
                                    </BarChart>
                                </ResponsiveContainer>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Data Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>Revenue by {getGroupByLabel(localFilters.group_by)}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {report.grouped_data.length > 0 ? (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>{getGroupByLabel(localFilters.group_by)}</TableHead>
                                        <TableHead className="text-right">Total Revenue</TableHead>
                                        <TableHead className="text-center">Transactions</TableHead>
                                        <TableHead className="text-right">Average</TableHead>
                                        <TableHead className="text-right">% of Total</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {report.grouped_data.map((item, index) => {
                                        const percentOfTotal =
                                            report.summary.total_revenue > 0
                                                ? (item.total / report.summary.total_revenue) * 100
                                                : 0;

                                        return (
                                            <TableRow key={item.key || index}>
                                                <TableCell className="font-medium">
                                                    {item.label}
                                                </TableCell>
                                                <TableCell className="text-right font-semibold text-green-600">
                                                    {formatCurrency(item.total)}
                                                </TableCell>
                                                <TableCell className="text-center">
                                                    {item.count.toLocaleString()}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    {formatCurrency(item.average)}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <Badge
                                                        variant={
                                                            percentOfTotal >= 20
                                                                ? 'default'
                                                                : percentOfTotal >= 10
                                                                  ? 'secondary'
                                                                  : 'outline'
                                                        }
                                                    >
                                                        {percentOfTotal.toFixed(1)}%
                                                    </Badge>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                    {/* Totals Row */}
                                    <TableRow className="bg-muted/50 font-bold">
                                        <TableCell>Total</TableCell>
                                        <TableCell className="text-right text-green-600">
                                            {formatCurrency(report.summary.total_revenue)}
                                        </TableCell>
                                        <TableCell className="text-center">
                                            {report.summary.transaction_count.toLocaleString()}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {formatCurrency(report.summary.average_transaction)}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Badge>100%</Badge>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        ) : (
                            <div className="text-center py-12 text-muted-foreground">
                                <BarChart3 className="mx-auto h-12 w-12 mb-4 opacity-50" />
                                <p>No revenue data found for the selected period.</p>
                                <p className="text-sm mt-2">
                                    Try adjusting the date range or filters.
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Period Comparison Card */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Period Comparison</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="rounded-lg border p-4">
                                <div className="text-sm font-medium text-muted-foreground">
                                    Current Period
                                </div>
                                <div className="text-xs text-muted-foreground mb-2">
                                    {report.summary.period_start} to {report.summary.period_end}
                                </div>
                                <div className="text-2xl font-bold text-green-600">
                                    {formatCurrency(report.summary.total_revenue)}
                                </div>
                                <div className="text-sm text-muted-foreground">
                                    {report.summary.transaction_count.toLocaleString()} transactions
                                </div>
                            </div>
                            <div className="rounded-lg border p-4">
                                <div className="text-sm font-medium text-muted-foreground">
                                    Previous Period
                                </div>
                                <div className="text-xs text-muted-foreground mb-2">
                                    {report.comparison.previous_period_start} to{' '}
                                    {report.comparison.previous_period_end}
                                </div>
                                <div className="text-2xl font-bold text-gray-600">
                                    {formatCurrency(report.comparison.previous_total)}
                                </div>
                                <div className="text-sm text-muted-foreground">
                                    {report.comparison.previous_count.toLocaleString()} transactions
                                </div>
                            </div>
                        </div>
                        <div className="mt-4 p-4 rounded-lg bg-muted/50">
                            <div className="flex items-center gap-2">
                                {isPositiveChange ? (
                                    <ArrowUp className="h-5 w-5 text-green-600" />
                                ) : (
                                    <ArrowDown className="h-5 w-5 text-red-600" />
                                )}
                                <span
                                    className={`text-lg font-semibold ${
                                        isPositiveChange ? 'text-green-600' : 'text-red-600'
                                    }`}
                                >
                                    {isPositiveChange ? '+' : ''}
                                    {report.comparison.percentage_change.toFixed(1)}% change
                                </span>
                                <span className="text-muted-foreground">
                                    ({isPositiveChange ? '+' : ''}
                                    {formatCurrency(
                                        report.summary.total_revenue - report.comparison.previous_total
                                    )}
                                    )
                                </span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
