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
    ArrowLeft,
    Building2,
    CheckCircle2,
    Clock,
    FileText,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';

interface InsuranceProvider {
    id: number;
    name: string;
}

interface StatusBreakdown {
    status: string;
    count: number;
    total_amount: number;
}

interface ProviderData {
    id: number;
    name: string;
    claim_count: number;
    total_claimed: number;
    total_approved: number;
    total_paid: number;
}

interface ClaimsSummaryData {
    total_claims: number;
    total_claimed_amount: number;
    total_approved_amount: number;
    total_paid_amount: number;
    outstanding_amount: number;
    status_breakdown: Record<string, StatusBreakdown>;
    claims_by_provider: ProviderData[];
}

interface Props {
    data: ClaimsSummaryData;
    providers: InsuranceProvider[];
    filters: {
        date_from: string;
        date_to: string;
        provider_id?: number;
    };
}

export default function ClaimsSummary({ data, providers, filters }: Props) {
    const [dateFrom, setDateFrom] = useState(filters.date_from);
    const [dateTo, setDateTo] = useState(filters.date_to);
    const [providerId, setProviderId] = useState<string>(
        filters.provider_id?.toString() || 'all',
    );

    const formatCurrency = (amount: number | string) => {
        const numAmount =
            typeof amount === 'string' ? parseFloat(amount) : amount;
        return `GHS ${numAmount.toFixed(2)}`;
    };

    const getStatusBadgeVariant = (status: string) => {
        switch (status) {
            case 'paid':
                return 'default';
            case 'approved':
                return 'default';
            case 'submitted':
                return 'secondary';
            case 'vetted':
                return 'secondary';
            case 'rejected':
                return 'destructive';
            default:
                return 'outline';
        }
    };

    const getStatusLabel = (status: string) => {
        return status.charAt(0).toUpperCase() + status.slice(1);
    };

    const handleFilter = () => {
        const params: Record<string, string> = {
            date_from: dateFrom,
            date_to: dateTo,
        };

        if (providerId && providerId !== 'all') {
            params.provider_id = providerId;
        }

        router.get('/admin/insurance/reports/claims-summary', params, {
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
            '/admin/insurance/reports/claims-summary',
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
                { title: 'Claims Summary', href: '' },
            ]}
        >
            <Head title="Claims Summary Report" />

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
                            <FileText className="h-8 w-8" />
                            Claims Summary Report
                        </h1>
                        <p className="mt-2 text-gray-600 dark:text-gray-400">
                            Overview of all insurance claims with status
                            breakdown and provider totals
                        </p>
                    </div>
                </div>

                {/* Filters */}
                <Card>
                    <CardHeader>
                        <CardTitle>Filters</CardTitle>
                        <CardDescription>
                            Filter claims by date range and provider
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
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Total Claims
                                    </p>
                                    <p className="text-3xl font-bold text-gray-900 dark:text-gray-100">
                                        {data.total_claims}
                                    </p>
                                </div>
                                <FileText className="h-8 w-8 text-blue-600 dark:text-blue-400" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Total Claimed
                                    </p>
                                    <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                        {formatCurrency(
                                            data.total_claimed_amount,
                                        )}
                                    </p>
                                </div>
                                <Clock className="h-8 w-8 text-orange-600 dark:text-orange-400" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Total Approved
                                    </p>
                                    <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                        {formatCurrency(
                                            data.total_approved_amount,
                                        )}
                                    </p>
                                </div>
                                <CheckCircle2 className="h-8 w-8 text-green-600 dark:text-green-400" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Outstanding Amount
                                    </p>
                                    <p className="text-2xl font-bold text-red-600 dark:text-red-400">
                                        {formatCurrency(
                                            data.outstanding_amount,
                                        )}
                                    </p>
                                </div>
                                <XCircle className="h-8 w-8 text-red-600 dark:text-red-400" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Status Breakdown */}
                <Card>
                    <CardHeader>
                        <CardTitle>Status Breakdown</CardTitle>
                        <CardDescription>
                            Claims distribution by status
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {Object.keys(data.status_breakdown).length > 0 ? (
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3 lg:grid-cols-5">
                                {Object.entries(data.status_breakdown).map(
                                    ([status, breakdown]) => (
                                        <div
                                            key={status}
                                            className="rounded-lg border p-4 dark:border-gray-700"
                                        >
                                            <Badge
                                                variant={getStatusBadgeVariant(
                                                    status,
                                                )}
                                                className="mb-2"
                                            >
                                                {getStatusLabel(status)}
                                            </Badge>
                                            <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                                {breakdown.count}
                                            </p>
                                            <p className="text-sm text-gray-600 dark:text-gray-400">
                                                {formatCurrency(
                                                    breakdown.total_amount,
                                                )}
                                            </p>
                                        </div>
                                    ),
                                )}
                            </div>
                        ) : (
                            <p className="text-center text-gray-600 dark:text-gray-400">
                                No status data available
                            </p>
                        )}
                    </CardContent>
                </Card>

                {/* Claims by Provider */}
                <Card>
                    <CardHeader>
                        <CardTitle>Claims by Provider</CardTitle>
                        <CardDescription>
                            Breakdown of claims and amounts by insurance
                            provider
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {data.claims_by_provider.length > 0 ? (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Provider</TableHead>
                                            <TableHead className="text-right">
                                                Claims
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Total Claimed
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Total Approved
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Total Paid
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Outstanding
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {data.claims_by_provider.map(
                                            (provider) => (
                                                <TableRow key={provider.id}>
                                                    <TableCell className="font-medium">
                                                        <div className="flex items-center gap-2">
                                                            <Building2 className="h-4 w-4 text-gray-500" />
                                                            {provider.name}
                                                        </div>
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        {provider.claim_count}
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        {formatCurrency(
                                                            provider.total_claimed,
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        {formatCurrency(
                                                            provider.total_approved,
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        {formatCurrency(
                                                            provider.total_paid,
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-right font-semibold text-red-600 dark:text-red-400">
                                                        {formatCurrency(
                                                            provider.total_approved -
                                                                provider.total_paid,
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
                                    No claims data available
                                </h3>
                                <p className="text-gray-600 dark:text-gray-400">
                                    No claims found for the selected filters.
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
