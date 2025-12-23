import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
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
    AlertCircle,
    ArrowLeft,
    Building2,
    Download,
    Filter,
    TrendingDown,
    X,
    XCircle,
} from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';

interface RejectionReason {
    reason: string;
    count: number;
    total_amount: number;
}

interface ProviderRejection {
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
    rejections_by_provider: ProviderRejection[];
    rejection_trends: RejectionTrend[];
}

interface InsuranceProvider {
    id: number;
    name: string;
}

interface Filters {
    date_from: string;
    date_to: string;
    provider_id: string | null;
}

interface Props {
    data: RejectionAnalysisData;
    providers: InsuranceProvider[];
    filters: Filters;
}

export default function RejectionAnalysis({ data, providers, filters }: Props) {
    const [showFilters, setShowFilters] = useState(false);
    const [localFilters, setLocalFilters] = useState<Filters>(filters);

    useEffect(() => {
        setLocalFilters(filters);
    }, [filters]);

    const handleFilterChange = (key: keyof Filters, value: string) => {
        setLocalFilters((prev) => ({
            ...prev,
            [key]: value === 'all' || !value ? null : value,
        }));
    };

    const handleApplyFilters = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            '/admin/insurance/reports/rejection-analysis',
            localFilters as any,
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const handleClearFilters = () => {
        const now = new Date();
        const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
        const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);
        const defaultFilters = {
            date_from: firstDay.toISOString().split('T')[0],
            date_to: lastDay.toISOString().split('T')[0],
            provider_id: null,
        };
        setLocalFilters(defaultFilters);
        router.get(
            '/admin/insurance/reports/rejection-analysis',
            defaultFilters as any,
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const handleExport = () => {
        const params = new URLSearchParams();
        if (localFilters.date_from)
            params.append('date_from', localFilters.date_from);
        if (localFilters.date_to)
            params.append('date_to', localFilters.date_to);
        if (localFilters.provider_id)
            params.append('provider_id', localFilters.provider_id);
        window.location.href = `/admin/insurance/reports/rejection-analysis/export?${params.toString()}`;
    };

    const formatCurrency = (amount: number | string) => {
        const numAmount =
            typeof amount === 'string' ? parseFloat(amount) : amount;
        return new Intl.NumberFormat('en-GH', {
            style: 'currency',
            currency: 'GHS',
        }).format(numAmount);
    };

    const hasActiveFilters = filters.provider_id !== null;

    // Calculate total for percentage calculations
    const totalRejectionCount = data.rejection_reasons.reduce(
        (sum, reason) => sum + reason.count,
        0,
    );

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                { title: 'Insurance', href: '/admin/insurance' },
                { title: 'Reports', href: '/admin/insurance/reports' },
                { title: 'Rejection Analysis', href: '' },
            ]}
        >
            <Head title="Rejection Analysis Report" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() =>
                                router.visit('/admin/insurance/reports')
                            }
                        >
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back
                        </Button>
                        <div>
                            <h1 className="flex items-center gap-2 text-3xl font-bold text-gray-900 dark:text-gray-100">
                                <XCircle className="h-8 w-8" />
                                Rejection Analysis Report
                            </h1>
                            <p className="mt-1 text-gray-600 dark:text-gray-400">
                                Rejected claims grouped by reason with trends
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        {hasActiveFilters && (
                            <Badge variant="secondary">Filters active</Badge>
                        )}
                        <Button
                            variant="outline"
                            onClick={() => setShowFilters(!showFilters)}
                        >
                            <Filter className="mr-2 h-4 w-4" />
                            {showFilters ? 'Hide Filters' : 'Show Filters'}
                        </Button>
                        <Button onClick={handleExport}>
                            <Download className="mr-2 h-4 w-4" />
                            Export Excel
                        </Button>
                    </div>
                </div>

                {/* Filters Panel */}
                {showFilters && (
                    <Card>
                        <CardContent className="p-6">
                            <form
                                onSubmit={handleApplyFilters}
                                className="space-y-4"
                            >
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                    <div className="space-y-2">
                                        <Label htmlFor="date_from">
                                            Date From
                                        </Label>
                                        <Input
                                            id="date_from"
                                            type="date"
                                            value={localFilters.date_from || ''}
                                            onChange={(e) =>
                                                handleFilterChange(
                                                    'date_from',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="date_to">Date To</Label>
                                        <Input
                                            id="date_to"
                                            type="date"
                                            value={localFilters.date_to || ''}
                                            onChange={(e) =>
                                                handleFilterChange(
                                                    'date_to',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="provider">
                                            Insurance Provider
                                        </Label>
                                        <Select
                                            value={
                                                localFilters.provider_id ||
                                                'all'
                                            }
                                            onValueChange={(value) =>
                                                handleFilterChange(
                                                    'provider_id',
                                                    value,
                                                )
                                            }
                                        >
                                            <SelectTrigger id="provider">
                                                <SelectValue placeholder="All providers" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">
                                                    All providers
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
                                </div>
                                <div className="flex items-center gap-2">
                                    <Button type="submit">Apply Filters</Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={handleClearFilters}
                                    >
                                        <X className="mr-2 h-4 w-4" />
                                        Reset to Default
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {/* Summary Cards */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Total Rejected Claims
                                    </p>
                                    <p className="text-3xl font-bold text-red-600">
                                        {data.total_rejected}
                                    </p>
                                </div>
                                <XCircle className="h-10 w-10 text-red-600" />
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
                                    <p className="text-3xl font-bold text-red-600">
                                        {formatCurrency(
                                            data.total_rejected_amount,
                                        )}
                                    </p>
                                </div>
                                <AlertCircle className="h-10 w-10 text-red-600" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Rejection Reasons */}
                <Card>
                    <CardHeader>
                        <CardTitle>Rejection Reasons</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {data.rejection_reasons.length > 0 ? (
                            <div className="space-y-4">
                                {data.rejection_reasons.map((reason, index) => {
                                    const percentage =
                                        totalRejectionCount > 0
                                            ? (reason.count /
                                                  totalRejectionCount) *
                                              100
                                            : 0;
                                    return (
                                        <div
                                            key={index}
                                            className="rounded-lg border p-4 dark:border-gray-700"
                                        >
                                            <div className="mb-2 flex items-center justify-between">
                                                <div className="flex items-center gap-2">
                                                    <AlertCircle className="h-4 w-4 text-red-500" />
                                                    <span className="font-medium">
                                                        {reason.reason}
                                                    </span>
                                                </div>
                                                <Badge variant="outline">
                                                    {reason.count} claims
                                                </Badge>
                                            </div>
                                            <div className="mb-2 flex items-center justify-between text-sm text-gray-600 dark:text-gray-400">
                                                <span>
                                                    {formatCurrency(
                                                        reason.total_amount,
                                                    )}
                                                </span>
                                                <span>
                                                    {percentage.toFixed(1)}% of
                                                    rejections
                                                </span>
                                            </div>
                                            <Progress
                                                value={percentage}
                                                className="h-2"
                                            />
                                        </div>
                                    );
                                })}
                            </div>
                        ) : (
                            <div className="py-8 text-center text-gray-500">
                                No rejected claims found for the selected period
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Rejections by Provider */}
                <Card>
                    <CardHeader>
                        <CardTitle>Rejections by Provider</CardTitle>
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
                                            <TableHead className="text-right">
                                                % of Total
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {data.rejections_by_provider.map(
                                            (provider, index) => {
                                                const percentage =
                                                    data.total_rejected > 0
                                                        ? (provider.count /
                                                              data.total_rejected) *
                                                          100
                                                        : 0;
                                                return (
                                                    <TableRow key={index}>
                                                        <TableCell className="font-medium">
                                                            <div className="flex items-center gap-2">
                                                                <Building2 className="h-4 w-4 text-gray-500" />
                                                                {
                                                                    provider.provider
                                                                }
                                                            </div>
                                                        </TableCell>
                                                        <TableCell className="text-right">
                                                            <Badge variant="destructive">
                                                                {provider.count}
                                                            </Badge>
                                                        </TableCell>
                                                        <TableCell className="text-right font-semibold text-red-600">
                                                            {formatCurrency(
                                                                provider.total_amount,
                                                            )}
                                                        </TableCell>
                                                        <TableCell className="text-right">
                                                            {percentage.toFixed(
                                                                1,
                                                            )}
                                                            %
                                                        </TableCell>
                                                    </TableRow>
                                                );
                                            },
                                        )}
                                    </TableBody>
                                </Table>
                            </div>
                        ) : (
                            <div className="py-8 text-center text-gray-500">
                                No rejections by provider found
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Rejection Trends */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <TrendingDown className="h-5 w-5" />
                            Rejection Trends (Last 6 Months)
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {data.rejection_trends.length > 0 ? (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Month</TableHead>
                                            <TableHead className="text-right">
                                                Total Claims
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Rejected
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Rejection Rate
                                            </TableHead>
                                            <TableHead className="w-48">
                                                Trend
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
                                                    <TableCell className="text-right">
                                                        {trend.total}
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        <Badge variant="destructive">
                                                            {trend.rejected}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        <span
                                                            className={
                                                                trend.rejection_rate >
                                                                10
                                                                    ? 'font-semibold text-red-600'
                                                                    : trend.rejection_rate >
                                                                        5
                                                                      ? 'text-orange-600'
                                                                      : 'text-green-600'
                                                            }
                                                        >
                                                            {trend.rejection_rate.toFixed(
                                                                1,
                                                            )}
                                                            %
                                                        </span>
                                                    </TableCell>
                                                    <TableCell>
                                                        <Progress
                                                            value={Math.min(
                                                                trend.rejection_rate *
                                                                    5,
                                                                100,
                                                            )}
                                                            className="h-2"
                                                        />
                                                    </TableCell>
                                                </TableRow>
                                            ),
                                        )}
                                    </TableBody>
                                </Table>
                            </div>
                        ) : (
                            <div className="py-8 text-center text-gray-500">
                                No trend data available
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
