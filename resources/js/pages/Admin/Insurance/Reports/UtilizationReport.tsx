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
    BarChart3,
    Building2,
    FileText,
    Shield,
} from 'lucide-react';
import { useState } from 'react';

interface InsuranceProvider {
    id: number;
    name: string;
}

interface TopService {
    service: string;
    count: number;
    total_claimed: number;
    total_approved: number;
}

interface ProviderUtilization {
    provider: string;
    plan: string;
    claim_count: number;
    total_claimed: number;
    avg_claim_amount: number;
}

interface UtilizationData {
    top_services: TopService[];
    provider_utilization: ProviderUtilization[];
}

interface Props {
    data: UtilizationData;
    providers: InsuranceProvider[];
    filters: {
        date_from: string;
        date_to: string;
        provider_id?: number;
    };
}

export default function UtilizationReport({ data, providers, filters }: Props) {
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

        router.get('/admin/insurance/reports/utilization', params, {
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
            '/admin/insurance/reports/utilization',
            {},
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const calculateApprovalRate = (approved: number, claimed: number) => {
        if (claimed === 0) return 0;
        return ((approved / claimed) * 100).toFixed(2);
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                {
                    title: 'Insurance Reports',
                    href: '/admin/insurance/reports',
                },
                { title: 'Utilization Report', href: '' },
            ]}
        >
            <Head title="Utilization Report" />

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
                            <BarChart3 className="h-8 w-8" />
                            Utilization Report
                        </h1>
                        <p className="mt-2 text-gray-600 dark:text-gray-400">
                            Analyze most used services and coverage patterns by
                            provider
                        </p>
                    </div>
                </div>

                {/* Filters */}
                <Card>
                    <CardHeader>
                        <CardTitle>Filters</CardTitle>
                        <CardDescription>
                            Filter utilization data by date range and provider
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

                {/* Top Services */}
                <Card>
                    <CardHeader>
                        <CardTitle>Top 10 Most Utilized Services</CardTitle>
                        <CardDescription>
                            Services most frequently claimed through insurance
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {data.top_services.length > 0 ? (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Rank</TableHead>
                                            <TableHead>
                                                Service Description
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Count
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Total Claimed
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Total Approved
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Approval Rate
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {data.top_services.map(
                                            (service, index) => (
                                                <TableRow key={index}>
                                                    <TableCell className="font-bold">
                                                        <Badge
                                                            variant={
                                                                index === 0
                                                                    ? 'default'
                                                                    : index < 3
                                                                      ? 'secondary'
                                                                      : 'outline'
                                                            }
                                                        >
                                                            #{index + 1}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell className="font-medium">
                                                        <div className="flex items-center gap-2">
                                                            <FileText className="h-4 w-4 text-gray-500" />
                                                            {service.service}
                                                        </div>
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        <Badge variant="outline">
                                                            {service.count}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        {formatCurrency(
                                                            service.total_claimed,
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-right text-green-600 dark:text-green-400">
                                                        {formatCurrency(
                                                            service.total_approved,
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        <Badge
                                                            variant={
                                                                parseFloat(
                                                                    calculateApprovalRate(
                                                                        service.total_approved,
                                                                        service.total_claimed,
                                                                    ),
                                                                ) >= 90
                                                                    ? 'default'
                                                                    : 'secondary'
                                                            }
                                                        >
                                                            {calculateApprovalRate(
                                                                service.total_approved,
                                                                service.total_claimed,
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
                                <FileText className="mx-auto mb-4 h-16 w-16 text-gray-300 dark:text-gray-600" />
                                <h3 className="mb-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    No service data available
                                </h3>
                                <p className="text-gray-600 dark:text-gray-400">
                                    No services have been claimed for the
                                    selected filters.
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Provider Utilization */}
                <Card>
                    <CardHeader>
                        <CardTitle>
                            Coverage Utilization by Provider & Plan
                        </CardTitle>
                        <CardDescription>
                            Insurance plan usage breakdown with average claim
                            amounts
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {data.provider_utilization.length > 0 ? (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Provider</TableHead>
                                            <TableHead>Plan</TableHead>
                                            <TableHead className="text-right">
                                                Claims
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Total Claimed
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Avg Claim Amount
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {data.provider_utilization.map(
                                            (item, index) => (
                                                <TableRow key={index}>
                                                    <TableCell className="font-medium">
                                                        <div className="flex items-center gap-2">
                                                            <Building2 className="h-4 w-4 text-gray-500" />
                                                            {item.provider}
                                                        </div>
                                                    </TableCell>
                                                    <TableCell>
                                                        <div className="flex items-center gap-2">
                                                            <Shield className="h-4 w-4 text-blue-500" />
                                                            {item.plan}
                                                        </div>
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        <Badge variant="outline">
                                                            {item.claim_count}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        {formatCurrency(
                                                            item.total_claimed,
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-right font-semibold text-blue-600 dark:text-blue-400">
                                                        {formatCurrency(
                                                            item.avg_claim_amount,
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
                                    No provider utilization data
                                </h3>
                                <p className="text-gray-600 dark:text-gray-400">
                                    No claims found for the selected filters.
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Summary Stats */}
                {data.top_services.length > 0 && (
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                        <Card>
                            <CardHeader>
                                <CardTitle>Most Popular Service</CardTitle>
                                <CardDescription>
                                    Highest claim count
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-2">
                                    <p className="font-medium text-gray-900 dark:text-gray-100">
                                        {data.top_services[0].service}
                                    </p>
                                    <p className="text-3xl font-bold text-blue-600 dark:text-blue-400">
                                        {data.top_services[0].count} claims
                                    </p>
                                    <p className="text-sm text-gray-600 dark:text-gray-400">
                                        {formatCurrency(
                                            data.top_services[0].total_claimed,
                                        )}{' '}
                                        claimed
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Total Unique Services</CardTitle>
                                <CardDescription>
                                    Different services claimed
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-2">
                                    <p className="text-3xl font-bold text-purple-600 dark:text-purple-400">
                                        {data.top_services.length}
                                    </p>
                                    <p className="text-sm text-gray-600 dark:text-gray-400">
                                        services utilized
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Total Active Plans</CardTitle>
                                <CardDescription>
                                    Plans with claims
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-2">
                                    <p className="text-3xl font-bold text-green-600 dark:text-green-400">
                                        {data.provider_utilization.length}
                                    </p>
                                    <p className="text-sm text-gray-600 dark:text-gray-400">
                                        provider plans
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
