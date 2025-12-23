import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    ArrowLeft,
    Building2,
    CheckCircle,
    Clock,
    DollarSign,
    Download,
    FileText,
    Filter,
    RefreshCw,
    X,
    XCircle,
} from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';

interface StatusBreakdown {
    status: string;
    count: number;
    total_amount: number;
}

interface ProviderClaim {
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
    claims_by_provider: ProviderClaim[];
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
    data: ClaimsSummaryData;
    providers: InsuranceProvider[];
    filters: Filters;
}

const statusConfig: Record<
    string,
    { label: string; color: string; icon: React.ReactNode }
> = {
    pending_vetting: {
        label: 'Pending Vetting',
        color: 'bg-yellow-500',
        icon: <Clock className="h-4 w-4" />,
    },
    vetted: {
        label: 'Vetted',
        color: 'bg-blue-500',
        icon: <CheckCircle className="h-4 w-4" />,
    },
    submitted: {
        label: 'Submitted',
        color: 'bg-purple-500',
        icon: <FileText className="h-4 w-4" />,
    },
    approved: {
        label: 'Approved',
        color: 'bg-green-500',
        icon: <CheckCircle className="h-4 w-4" />,
    },
    rejected: {
        label: 'Rejected',
        color: 'bg-red-500',
        icon: <XCircle className="h-4 w-4" />,
    },
    paid: {
        label: 'Paid',
        color: 'bg-emerald-600',
        icon: <DollarSign className="h-4 w-4" />,
    },
    partial: {
        label: 'Partial',
        color: 'bg-orange-500',
        icon: <RefreshCw className="h-4 w-4" />,
    },
};

export default function ClaimsSummary({ data, providers, filters }: Props) {
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
            '/admin/insurance/reports/claims-summary',
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
            '/admin/insurance/reports/claims-summary',
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
        window.location.href = `/admin/insurance/reports/claims-summary/export?${params.toString()}`;
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

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                { title: 'Insurance', href: '/admin/insurance' },
                { title: 'Reports', href: '/admin/insurance/reports' },
                { title: 'Claims Summary', href: '' },
            ]}
        >
            <Head title="Claims Summary Report" />

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
                                <FileText className="h-8 w-8" />
                                Claims Summary Report
                            </h1>
                            <p className="mt-1 text-gray-600 dark:text-gray-400">
                                Overview of all claims with status breakdown for
                                the selected period
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
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-5">
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
                                <FileText className="h-8 w-8 text-blue-600" />
                            </div>
                        </CardContent>
                    </Card>
                    <StatCard
                        label="Total Claimed"
                        value={formatCurrency(data.total_claimed_amount)}
                        icon={<DollarSign className="h-4 w-4" />}
                    />
                    <StatCard
                        label="Total Approved"
                        value={formatCurrency(data.total_approved_amount)}
                        icon={<CheckCircle className="h-4 w-4" />}
                        variant="success"
                    />
                    <StatCard
                        label="Total Paid"
                        value={formatCurrency(data.total_paid_amount)}
                        icon={<DollarSign className="h-4 w-4" />}
                        variant="success"
                    />
                    <StatCard
                        label="Outstanding"
                        value={formatCurrency(data.outstanding_amount)}
                        icon={<Clock className="h-4 w-4" />}
                        variant="error"
                    />
                </div>

                {/* Status Breakdown */}
                <Card>
                    <CardHeader>
                        <CardTitle>Status Breakdown</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-2 gap-4 md:grid-cols-4 lg:grid-cols-8">
                            {Object.entries(data.status_breakdown).map(
                                ([status, breakdown]) => (
                                    <div
                                        key={status}
                                        className="rounded-lg border p-4 dark:border-gray-700"
                                    >
                                        <div className="mb-2 flex items-center gap-2">
                                            {statusConfig[status]?.icon}
                                            <Badge
                                                className={
                                                    statusConfig[status]
                                                        ?.color || 'bg-gray-500'
                                                }
                                            >
                                                {statusConfig[status]?.label ||
                                                    status}
                                            </Badge>
                                        </div>
                                        <p className="text-2xl font-bold">
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
                    </CardContent>
                </Card>

                {/* Claims by Provider */}
                <Card>
                    <CardHeader>
                        <CardTitle>Claims by Provider</CardTitle>
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
                                                        <Badge variant="outline">
                                                            {
                                                                provider.claim_count
                                                            }
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        {formatCurrency(
                                                            provider.total_claimed,
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-right text-green-600">
                                                        {formatCurrency(
                                                            provider.total_approved,
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-right text-emerald-600">
                                                        {formatCurrency(
                                                            provider.total_paid,
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-right text-red-600">
                                                        {formatCurrency(
                                                            (provider.total_approved ||
                                                                0) -
                                                                (provider.total_paid ||
                                                                    0),
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            ),
                                        )}
                                    </TableBody>
                                </Table>
                            </div>
                        ) : (
                            <div className="py-8 text-center text-gray-500">
                                No claims found for the selected period
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
