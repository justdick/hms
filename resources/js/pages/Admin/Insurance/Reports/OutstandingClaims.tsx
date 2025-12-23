import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { StatCard } from '@/components/ui/stat-card';
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
    Calendar,
    Clock,
    Download,
    Filter,
    X,
} from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';

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

interface InsuranceProvider {
    id: number;
    name: string;
}

interface Filters {
    provider_id: string | null;
}

interface Props {
    data: OutstandingClaimsData;
    providers: InsuranceProvider[];
    filters: Filters;
}

const agingColors: Record<string, string> = {
    '0-30': 'bg-green-500',
    '31-60': 'bg-yellow-500',
    '61-90': 'bg-orange-500',
    '90+': 'bg-red-500',
};

const agingLabels: Record<string, string> = {
    '0-30': '0-30 Days',
    '31-60': '31-60 Days',
    '61-90': '61-90 Days',
    '90+': '90+ Days',
};

export default function OutstandingClaims({ data, providers, filters }: Props) {
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
            '/admin/insurance/reports/outstanding-claims',
            localFilters as any,
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const handleClearFilters = () => {
        const defaultFilters = { provider_id: null };
        setLocalFilters(defaultFilters);
        router.get(
            '/admin/insurance/reports/outstanding-claims',
            defaultFilters as any,
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const handleExport = () => {
        const params = new URLSearchParams();
        if (localFilters.provider_id)
            params.append('provider_id', localFilters.provider_id);
        window.location.href = `/admin/insurance/reports/outstanding-claims/export?${params.toString()}`;
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
    const totalAgingAmount = Object.values(data.aging_analysis).reduce(
        (sum, bucket) => sum + bucket.amount,
        0,
    );

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                { title: 'Insurance', href: '/admin/insurance' },
                { title: 'Reports', href: '/admin/insurance/reports' },
                { title: 'Outstanding Claims', href: '' },
            ]}
        >
            <Head title="Outstanding Claims Report" />

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
                                <Clock className="h-8 w-8" />
                                Outstanding Claims Report
                            </h1>
                            <p className="mt-1 text-gray-600 dark:text-gray-400">
                                Unpaid approved claims with aging analysis
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
                                    {hasActiveFilters && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={handleClearFilters}
                                        >
                                            <X className="mr-2 h-4 w-4" />
                                            Clear Filters
                                        </Button>
                                    )}
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {/* Summary Cards */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <StatCard
                        label="Total Outstanding"
                        value={formatCurrency(data.total_outstanding)}
                        icon={<AlertTriangle className="h-5 w-5" />}
                        variant="error"
                    />
                    <StatCard
                        label="Outstanding Claims"
                        value={data.total_claims}
                        icon={<Calendar className="h-5 w-5" />}
                        variant="info"
                    />
                </div>

                {/* Aging Analysis */}
                <Card>
                    <CardHeader>
                        <CardTitle>Aging Analysis</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
                            {Object.entries(data.aging_analysis).map(
                                ([bucket, bucketData]) => {
                                    const percentage =
                                        totalAgingAmount > 0
                                            ? (bucketData.amount /
                                                  totalAgingAmount) *
                                              100
                                            : 0;
                                    return (
                                        <div
                                            key={bucket}
                                            className="rounded-lg border p-4 dark:border-gray-700"
                                        >
                                            <div className="mb-3 flex items-center justify-between">
                                                <Badge
                                                    className={
                                                        agingColors[bucket]
                                                    }
                                                >
                                                    {agingLabels[bucket]}
                                                </Badge>
                                                <span className="text-sm text-gray-500">
                                                    {percentage.toFixed(1)}%
                                                </span>
                                            </div>
                                            <p className="text-2xl font-bold">
                                                {bucketData.count}
                                            </p>
                                            <p className="text-sm text-gray-600 dark:text-gray-400">
                                                claims
                                            </p>
                                            <p className="mt-2 text-lg font-semibold">
                                                {formatCurrency(
                                                    bucketData.amount,
                                                )}
                                            </p>
                                            <Progress
                                                value={percentage}
                                                className="mt-2"
                                            />
                                        </div>
                                    );
                                },
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Outstanding by Provider */}
                <Card>
                    <CardHeader>
                        <CardTitle>Outstanding by Provider</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {Object.keys(data.by_provider).length > 0 ? (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Provider</TableHead>
                                            <TableHead className="text-right">
                                                Claims
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Outstanding Amount
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Oldest Claim
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Risk Level
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {Object.entries(data.by_provider)
                                            .sort(
                                                ([, a], [, b]) =>
                                                    b.amount - a.amount,
                                            )
                                            .map(
                                                ([
                                                    providerName,
                                                    providerData,
                                                ]) => {
                                                    let riskLevel = 'Low';
                                                    let riskColor =
                                                        'bg-green-500';
                                                    if (
                                                        providerData.oldest_claim_days >
                                                        90
                                                    ) {
                                                        riskLevel = 'High';
                                                        riskColor =
                                                            'bg-red-500';
                                                    } else if (
                                                        providerData.oldest_claim_days >
                                                        60
                                                    ) {
                                                        riskLevel = 'Medium';
                                                        riskColor =
                                                            'bg-orange-500';
                                                    } else if (
                                                        providerData.oldest_claim_days >
                                                        30
                                                    ) {
                                                        riskLevel =
                                                            'Low-Medium';
                                                        riskColor =
                                                            'bg-yellow-500';
                                                    }

                                                    return (
                                                        <TableRow
                                                            key={providerName}
                                                        >
                                                            <TableCell className="font-medium">
                                                                <div className="flex items-center gap-2">
                                                                    <Building2 className="h-4 w-4 text-gray-500" />
                                                                    {
                                                                        providerName
                                                                    }
                                                                </div>
                                                            </TableCell>
                                                            <TableCell className="text-right">
                                                                <Badge variant="outline">
                                                                    {
                                                                        providerData.count
                                                                    }
                                                                </Badge>
                                                            </TableCell>
                                                            <TableCell className="text-right font-semibold text-red-600">
                                                                {formatCurrency(
                                                                    providerData.amount,
                                                                )}
                                                            </TableCell>
                                                            <TableCell className="text-right">
                                                                {
                                                                    providerData.oldest_claim_days
                                                                }{' '}
                                                                days
                                                            </TableCell>
                                                            <TableCell className="text-right">
                                                                <Badge
                                                                    className={
                                                                        riskColor
                                                                    }
                                                                >
                                                                    {riskLevel}
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
                            <div className="py-8 text-center text-gray-500">
                                No outstanding claims found
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
