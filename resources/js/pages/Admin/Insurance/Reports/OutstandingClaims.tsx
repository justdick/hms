import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
    AlertCircle,
    ArrowLeft,
    Building2,
    Clock,
    FileBarChart,
} from 'lucide-react';
import { useState } from 'react';

interface InsuranceProvider {
    id: number;
    name: string;
}

interface AgingBucket {
    count: number;
    amount: number;
}

interface ProviderOutstanding {
    count: number;
    amount: number;
    oldest_claim_days: number;
}

interface OutstandingClaimsData {
    total_outstanding: number;
    total_claims: number;
    aging_analysis: Record<string, AgingBucket>;
    by_provider: Record<string, ProviderOutstanding>;
}

interface Props {
    data: OutstandingClaimsData;
    providers: InsuranceProvider[];
    filters: {
        provider_id?: number;
    };
}

export default function OutstandingClaims({ data, providers, filters }: Props) {
    const [providerId, setProviderId] = useState<string>(
        filters.provider_id?.toString() || 'all',
    );

    const formatCurrency = (amount: number) => {
        return `GHS ${amount.toFixed(2)}`;
    };

    const handleFilter = () => {
        const params: Record<string, string> = {};

        if (providerId && providerId !== 'all') {
            params.provider_id = providerId;
        }

        router.get('/admin/insurance/reports/outstanding-claims', params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleReset = () => {
        setProviderId('all');

        router.get(
            '/admin/insurance/reports/outstanding-claims',
            {},
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const getAgingBadgeVariant = (bucket: string) => {
        switch (bucket) {
            case '0-30':
                return 'default';
            case '31-60':
                return 'secondary';
            case '61-90':
                return 'destructive';
            case '90+':
                return 'destructive';
            default:
                return 'outline';
        }
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                {
                    title: 'Insurance Reports',
                    href: '/admin/insurance/reports',
                },
                { title: 'Outstanding Claims', href: '' },
            ]}
        >
            <Head title="Outstanding Claims Report" />

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
                            <FileBarChart className="h-8 w-8" />
                            Outstanding Claims Report
                        </h1>
                        <p className="mt-2 text-gray-600 dark:text-gray-400">
                            Track unpaid claims with aging analysis (30/60/90
                            days)
                        </p>
                    </div>
                </div>

                {/* Filters */}
                <Card>
                    <CardHeader>
                        <CardTitle>Filters</CardTitle>
                        <CardDescription>
                            Filter outstanding claims by provider
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
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
                                        Total Outstanding Claims
                                    </p>
                                    <p className="text-3xl font-bold text-gray-900 dark:text-gray-100">
                                        {data.total_claims}
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
                                        Total Outstanding Amount
                                    </p>
                                    <p className="text-3xl font-bold text-red-600 dark:text-red-400">
                                        {formatCurrency(data.total_outstanding)}
                                    </p>
                                </div>
                                <AlertCircle className="h-8 w-8 text-red-600 dark:text-red-400" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Aging Analysis */}
                <Card>
                    <CardHeader>
                        <CardTitle>Aging Analysis</CardTitle>
                        <CardDescription>
                            Claims breakdown by outstanding period
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                            {Object.entries(data.aging_analysis).map(
                                ([bucket, data]) => (
                                    <div
                                        key={bucket}
                                        className="rounded-lg border p-6 dark:border-gray-700"
                                    >
                                        <div className="mb-2 flex items-center justify-between">
                                            <Badge
                                                variant={getAgingBadgeVariant(
                                                    bucket,
                                                )}
                                            >
                                                {bucket} Days
                                            </Badge>
                                            <Clock className="h-5 w-5 text-gray-400" />
                                        </div>
                                        <p className="text-3xl font-bold text-gray-900 dark:text-gray-100">
                                            {data.count}
                                        </p>
                                        <p className="text-sm text-gray-600 dark:text-gray-400">
                                            {formatCurrency(data.amount)}
                                        </p>
                                    </div>
                                ),
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Outstanding by Provider */}
                <Card>
                    <CardHeader>
                        <CardTitle>Outstanding Claims by Provider</CardTitle>
                        <CardDescription>
                            Provider breakdown with oldest claim tracking
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {Object.keys(data.by_provider).length > 0 ? (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Provider</TableHead>
                                            <TableHead className="text-right">
                                                Outstanding Claims
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Outstanding Amount
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Oldest Claim (Days)
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {Object.entries(data.by_provider).map(
                                            ([provider, providerData]) => (
                                                <TableRow key={provider}>
                                                    <TableCell className="font-medium">
                                                        <div className="flex items-center gap-2">
                                                            <Building2 className="h-4 w-4 text-gray-500" />
                                                            {provider}
                                                        </div>
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        {providerData.count}
                                                    </TableCell>
                                                    <TableCell className="text-right font-semibold text-red-600 dark:text-red-400">
                                                        {formatCurrency(
                                                            providerData.amount,
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        <Badge
                                                            variant={
                                                                providerData.oldest_claim_days >
                                                                90
                                                                    ? 'destructive'
                                                                    : providerData.oldest_claim_days >
                                                                        60
                                                                      ? 'secondary'
                                                                      : 'outline'
                                                            }
                                                        >
                                                            {
                                                                providerData.oldest_claim_days
                                                            }{' '}
                                                            days
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
                                <Building2 className="mx-auto mb-4 h-16 w-16 text-gray-300 dark:text-gray-600" />
                                <h3 className="mb-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    No outstanding claims
                                </h3>
                                <p className="text-gray-600 dark:text-gray-400">
                                    All claims have been paid or there are no
                                    claims for the selected filters.
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
