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
    AlertTriangle,
    ArrowLeft,
    Building2,
    TrendingDown,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';

interface InsuranceProvider {
    id: number;
    name: string;
}

interface RejectionReason {
    reason: string;
    count: number;
    total_amount: number;
}

interface RejectionByProvider {
    provider: string;
    count: number;
    total_amount: number;
}

interface RejectionTrend {
    month: string;
    rejected: number;
    total: number;
    rejection_rate: number;
}

interface RejectionAnalysisData {
    total_rejected: number;
    total_rejected_amount: number;
    rejection_reasons: RejectionReason[];
    rejections_by_provider: RejectionByProvider[];
    rejection_trends: RejectionTrend[];
}

interface Props {
    data: RejectionAnalysisData;
    providers: InsuranceProvider[];
    filters: {
        date_from: string;
        date_to: string;
        provider_id?: number;
    };
}

export default function RejectionAnalysis({ data, providers, filters }: Props) {
    const [dateFrom, setDateFrom] = useState(filters.date_from);
    const [dateTo, setDateTo] = useState(filters.date_to);
    const [providerId, setProviderId] = useState<string>(
        filters.provider_id?.toString() || 'all',
    );

    const formatCurrency = (amount: number) => {
        return `GHS ${amount.toFixed(2)}`;
    };

    const handleFilter = () => {
        const params: Record<string, string> = {
            date_from: dateFrom,
            date_to: dateTo,
        };

        if (providerId && providerId !== 'all') {
            params.provider_id = providerId;
        }

        router.get('/admin/insurance/reports/rejection-analysis', params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleReset = () => {
        const now = new Date();
        const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
        const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);

        setDateFrom(firstDay.toISOString().split('T')[0]);
        setDateTo(lastDay.toISOString().split('T')[0]);
        setProviderId('all');

        router.get(
            '/admin/insurance/reports/rejection-analysis',
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
                { title: 'Rejection Analysis', href: '' },
            ]}
        >
            <Head title="Rejection Analysis Report" />

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
                            <TrendingDown className="h-8 w-8" />
                            Rejection Analysis Report
                        </h1>
                        <p className="mt-2 text-gray-600 dark:text-gray-400">
                            Review rejection reasons, trends, and patterns
                        </p>
                    </div>
                </div>

                {/* Filters */}
                <Card>
                    <CardHeader>
                        <CardTitle>Filters</CardTitle>
                        <CardDescription>
                            Filter rejection data by date range and provider
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
                            <div className="space-y-2">
                                <Label htmlFor="provider">Provider</Label>
                                <Select
                                    value={providerId}
                                    onValueChange={setProviderId}
                                >
                                    <SelectTrigger id="provider">
                                        <SelectValue placeholder="All Providers" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All Providers
                                        </SelectItem>
                                        {providers.map((provider) => (
                                            <SelectItem
                                                key={provider.id}
                                                value={provider.id.toString()}
                                            >
                                                {provider.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
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
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Total Rejected Claims
                                    </p>
                                    <p className="text-3xl font-bold text-red-600 dark:text-red-400">
                                        {data.total_rejected}
                                    </p>
                                </div>
                                <XCircle className="h-8 w-8 text-red-600 dark:text-red-400" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Total Rejected Amount
                                    </p>
                                    <p className="text-3xl font-bold text-red-600 dark:text-red-400">
                                        {formatCurrency(
                                            data.total_rejected_amount,
                                        )}
                                    </p>
                                </div>
                                <AlertTriangle className="h-8 w-8 text-red-600 dark:text-red-400" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Rejection Reasons */}
                <Card>
                    <CardHeader>
                        <CardTitle>Rejection Reasons Breakdown</CardTitle>
                        <CardDescription>
                            Most common reasons for claim rejections
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {data.rejection_reasons.length > 0 ? (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Rank</TableHead>
                                            <TableHead>
                                                Rejection Reason
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Count
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Total Amount
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Percentage
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {data.rejection_reasons.map(
                                            (reason, index) => {
                                                const percentage =
                                                    data.total_rejected > 0
                                                        ? (
                                                              (reason.count /
                                                                  data.total_rejected) *
                                                              100
                                                          ).toFixed(2)
                                                        : '0.00';

                                                return (
                                                    <TableRow key={index}>
                                                        <TableCell>
                                                            <Badge
                                                                variant={
                                                                    index === 0
                                                                        ? 'destructive'
                                                                        : index <
                                                                            3
                                                                          ? 'secondary'
                                                                          : 'outline'
                                                                }
                                                            >
                                                                #{index + 1}
                                                            </Badge>
                                                        </TableCell>
                                                        <TableCell className="font-medium">
                                                            <div className="flex items-center gap-2">
                                                                <AlertTriangle className="h-4 w-4 text-red-500" />
                                                                {reason.reason}
                                                            </div>
                                                        </TableCell>
                                                        <TableCell className="text-right">
                                                            <Badge variant="outline">
                                                                {reason.count}
                                                            </Badge>
                                                        </TableCell>
                                                        <TableCell className="text-right text-red-600 dark:text-red-400">
                                                            {formatCurrency(
                                                                reason.total_amount,
                                                            )}
                                                        </TableCell>
                                                        <TableCell className="text-right">
                                                            <Badge variant="secondary">
                                                                {percentage}%
                                                            </Badge>
                                                        </TableCell>
                                                    </TableRow>
                                                );
                                            },
                                        )}
                                    </TableBody>
                                </Table>
                            </div>
                        ) : (
                            <div className="py-12 text-center">
                                <XCircle className="mx-auto mb-4 h-16 w-16 text-gray-300 dark:text-gray-600" />
                                <h3 className="mb-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    No rejection data available
                                </h3>
                                <p className="text-gray-600 dark:text-gray-400">
                                    No rejected claims found for the selected
                                    filters.
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Rejections by Provider */}
                <Card>
                    <CardHeader>
                        <CardTitle>Rejections by Provider</CardTitle>
                        <CardDescription>
                            Breakdown of rejections by insurance provider
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {data.rejections_by_provider.length > 0 ? (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Provider</TableHead>
                                            <TableHead className="text-right">
                                                Rejected Claims
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Total Amount
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {data.rejections_by_provider.map(
                                            (provider, index) => (
                                                <TableRow key={index}>
                                                    <TableCell className="font-medium">
                                                        <div className="flex items-center gap-2">
                                                            <Building2 className="h-4 w-4 text-gray-500" />
                                                            {provider.provider}
                                                        </div>
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        <Badge variant="destructive">
                                                            {provider.count}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell className="text-right text-red-600 dark:text-red-400">
                                                        {formatCurrency(
                                                            provider.total_amount,
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            ),
                                        )}
                                    </TableBody>
                                </Table>
                            </div>
                        ) : (
                            <div className="py-12 text-center">
                                <Building2 className="mx-auto mb-4 h-16 w-16 text-gray-300 dark:text-gray-600" />
                                <h3 className="mb-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    No provider rejection data
                                </h3>
                                <p className="text-gray-600 dark:text-gray-400">
                                    No rejections found for the selected
                                    filters.
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Rejection Trends */}
                <Card>
                    <CardHeader>
                        <CardTitle>Rejection Trends (Last 6 Months)</CardTitle>
                        <CardDescription>
                            Monthly rejection counts and rates over time
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {data.rejection_trends.length > 0 ? (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Month</TableHead>
                                            <TableHead className="text-right">
                                                Rejected Claims
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Total Claims
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Rejection Rate
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {data.rejection_trends.map(
                                            (trend, index) => (
                                                <TableRow key={index}>
                                                    <TableCell className="font-medium">
                                                        {trend.month}
                                                    </TableCell>
                                                    <TableCell className="text-right text-red-600 dark:text-red-400">
                                                        {trend.rejected}
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        {trend.total}
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        <Badge
                                                            variant={
                                                                trend.rejection_rate >
                                                                20
                                                                    ? 'destructive'
                                                                    : trend.rejection_rate >
                                                                        10
                                                                      ? 'secondary'
                                                                      : 'outline'
                                                            }
                                                        >
                                                            {trend.rejection_rate.toFixed(
                                                                2,
                                                            )}
                                                            %
                                                        </Badge>
                                                    </TableCell>
                                                </TableRow>
                                            ),
                                        )}
                                    </TableBody>
                                </Table>
                            </div>
                        ) : (
                            <div className="py-12 text-center">
                                <TrendingDown className="mx-auto mb-4 h-16 w-16 text-gray-300 dark:text-gray-600" />
                                <h3 className="mb-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    No trend data available
                                </h3>
                                <p className="text-gray-600 dark:text-gray-400">
                                    No rejection trends available for the last 6
                                    months.
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Key Insights */}
                {data.rejection_reasons.length > 0 && (
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                        <Card>
                            <CardHeader>
                                <CardTitle>Top Rejection Reason</CardTitle>
                                <CardDescription>
                                    Most common rejection cause
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-2">
                                    <p className="font-medium text-gray-900 dark:text-gray-100">
                                        {data.rejection_reasons[0].reason}
                                    </p>
                                    <p className="text-3xl font-bold text-red-600 dark:text-red-400">
                                        {data.rejection_reasons[0].count} claims
                                    </p>
                                    <p className="text-sm text-gray-600 dark:text-gray-400">
                                        {formatCurrency(
                                            data.rejection_reasons[0]
                                                .total_amount,
                                        )}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Average Rejection Rate</CardTitle>
                                <CardDescription>
                                    Based on 6-month trend
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-2">
                                    <p className="text-3xl font-bold text-orange-600 dark:text-orange-400">
                                        {data.rejection_trends.length > 0
                                            ? (
                                                  data.rejection_trends.reduce(
                                                      (sum, t) =>
                                                          sum +
                                                          t.rejection_rate,
                                                      0,
                                                  ) /
                                                  data.rejection_trends.length
                                              ).toFixed(2)
                                            : '0.00'}
                                        %
                                    </p>
                                    <p className="text-sm text-gray-600 dark:text-gray-400">
                                        monthly average
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Total Rejection Reasons</CardTitle>
                                <CardDescription>
                                    Unique rejection categories
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-2">
                                    <p className="text-3xl font-bold text-purple-600 dark:text-purple-400">
                                        {data.rejection_reasons.length}
                                    </p>
                                    <p className="text-sm text-gray-600 dark:text-gray-400">
                                        distinct reasons
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
