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
import { ArrowLeft, CheckCircle2, Clock, Users, XCircle } from 'lucide-react';
import { useState } from 'react';

interface OfficerPerformance {
    officer_name: string;
    claims_vetted: number;
    avg_turnaround_hours: number;
    approved_for_submission: number;
    rejected_at_vetting: number;
}

interface VettingPerformanceData {
    total_claims_vetted: number;
    avg_turnaround_hours: number;
    officers_performance: OfficerPerformance[];
}

interface Props {
    data: VettingPerformanceData;
    filters: {
        date_from: string;
        date_to: string;
    };
}

export default function VettingPerformance({ data, filters }: Props) {
    const [dateFrom, setDateFrom] = useState(filters.date_from);
    const [dateTo, setDateTo] = useState(filters.date_to);

    const handleFilter = () => {
        router.get(
            '/admin/insurance/reports/vetting-performance',
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
            '/admin/insurance/reports/vetting-performance',
            {},
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const calculateApprovalRate = (approved: number, total: number) => {
        if (total === 0) return 0;
        return ((approved / total) * 100).toFixed(2);
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                {
                    title: 'Insurance Reports',
                    href: '/admin/insurance/reports',
                },
                { title: 'Vetting Performance', href: '' },
            ]}
        >
            <Head title="Vetting Performance Report" />

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
                            <Users className="h-8 w-8" />
                            Vetting Performance Report
                        </h1>
                        <p className="mt-2 text-gray-600 dark:text-gray-400">
                            Monitor vetting officer productivity and turnaround
                            times
                        </p>
                    </div>
                </div>

                {/* Filters */}
                <Card>
                    <CardHeader>
                        <CardTitle>Filters</CardTitle>
                        <CardDescription>
                            Filter performance data by date range
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
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Total Claims Vetted
                                    </p>
                                    <p className="text-3xl font-bold text-gray-900 dark:text-gray-100">
                                        {data.total_claims_vetted}
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
                                        Average Turnaround Time
                                    </p>
                                    <p className="text-3xl font-bold text-blue-600 dark:text-blue-400">
                                        {data.avg_turnaround_hours.toFixed(2)}h
                                    </p>
                                </div>
                                <Clock className="h-8 w-8 text-blue-600 dark:text-blue-400" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Officer Performance Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>Vetting Officer Performance</CardTitle>
                        <CardDescription>
                            Individual performance metrics for each vetting
                            officer
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {data.officers_performance.length > 0 ? (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Officer Name</TableHead>
                                            <TableHead className="text-right">
                                                Claims Vetted
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Avg Turnaround (Hours)
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Approved for Submission
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Rejected at Vetting
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Approval Rate
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {data.officers_performance.map(
                                            (officer, index) => {
                                                const approvalRate =
                                                    calculateApprovalRate(
                                                        officer.approved_for_submission,
                                                        officer.claims_vetted,
                                                    );

                                                return (
                                                    <TableRow key={index}>
                                                        <TableCell className="font-medium">
                                                            <div className="flex items-center gap-2">
                                                                <Users className="h-4 w-4 text-gray-500" />
                                                                {
                                                                    officer.officer_name
                                                                }
                                                            </div>
                                                        </TableCell>
                                                        <TableCell className="text-right">
                                                            {
                                                                officer.claims_vetted
                                                            }
                                                        </TableCell>
                                                        <TableCell className="text-right">
                                                            <Badge variant="outline">
                                                                {officer.avg_turnaround_hours.toFixed(
                                                                    2,
                                                                )}
                                                                h
                                                            </Badge>
                                                        </TableCell>
                                                        <TableCell className="text-right">
                                                            <div className="flex items-center justify-end gap-1">
                                                                <CheckCircle2 className="h-4 w-4 text-green-600" />
                                                                <span className="text-green-600 dark:text-green-400">
                                                                    {
                                                                        officer.approved_for_submission
                                                                    }
                                                                </span>
                                                            </div>
                                                        </TableCell>
                                                        <TableCell className="text-right">
                                                            <div className="flex items-center justify-end gap-1">
                                                                <XCircle className="h-4 w-4 text-red-600" />
                                                                <span className="text-red-600 dark:text-red-400">
                                                                    {
                                                                        officer.rejected_at_vetting
                                                                    }
                                                                </span>
                                                            </div>
                                                        </TableCell>
                                                        <TableCell className="text-right">
                                                            <Badge
                                                                variant={
                                                                    parseFloat(
                                                                        approvalRate,
                                                                    ) >= 90
                                                                        ? 'default'
                                                                        : parseFloat(
                                                                                approvalRate,
                                                                            ) >=
                                                                            75
                                                                          ? 'secondary'
                                                                          : 'destructive'
                                                                }
                                                            >
                                                                {approvalRate}%
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
                                <Users className="mx-auto mb-4 h-16 w-16 text-gray-300 dark:text-gray-600" />
                                <h3 className="mb-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    No vetting data available
                                </h3>
                                <p className="text-gray-600 dark:text-gray-400">
                                    No claims have been vetted in the selected
                                    date range.
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Performance Insights */}
                {data.officers_performance.length > 0 && (
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                        <Card>
                            <CardHeader>
                                <CardTitle>Top Performer</CardTitle>
                                <CardDescription>
                                    Highest number of claims vetted
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {(() => {
                                    const topPerformer =
                                        data.officers_performance.reduce(
                                            (prev, current) =>
                                                current.claims_vetted >
                                                prev.claims_vetted
                                                    ? current
                                                    : prev,
                                        );

                                    return (
                                        <div className="space-y-2">
                                            <p className="text-xl font-bold text-gray-900 dark:text-gray-100">
                                                {topPerformer.officer_name}
                                            </p>
                                            <p className="text-3xl font-bold text-green-600 dark:text-green-400">
                                                {topPerformer.claims_vetted}{' '}
                                                claims
                                            </p>
                                        </div>
                                    );
                                })()}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Fastest Turnaround</CardTitle>
                                <CardDescription>
                                    Lowest average processing time
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {(() => {
                                    const fastest =
                                        data.officers_performance.reduce(
                                            (prev, current) =>
                                                current.avg_turnaround_hours <
                                                prev.avg_turnaround_hours
                                                    ? current
                                                    : prev,
                                        );

                                    return (
                                        <div className="space-y-2">
                                            <p className="text-xl font-bold text-gray-900 dark:text-gray-100">
                                                {fastest.officer_name}
                                            </p>
                                            <p className="text-3xl font-bold text-blue-600 dark:text-blue-400">
                                                {fastest.avg_turnaround_hours.toFixed(
                                                    2,
                                                )}
                                                h
                                            </p>
                                        </div>
                                    );
                                })()}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Highest Approval Rate</CardTitle>
                                <CardDescription>
                                    Best approval percentage
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {(() => {
                                    const bestApproval =
                                        data.officers_performance.reduce(
                                            (prev, current) => {
                                                const prevRate =
                                                    (prev.approved_for_submission /
                                                        prev.claims_vetted) *
                                                    100;
                                                const currentRate =
                                                    (current.approved_for_submission /
                                                        current.claims_vetted) *
                                                    100;
                                                return currentRate > prevRate
                                                    ? current
                                                    : prev;
                                            },
                                        );

                                    return (
                                        <div className="space-y-2">
                                            <p className="text-xl font-bold text-gray-900 dark:text-gray-100">
                                                {bestApproval.officer_name}
                                            </p>
                                            <p className="text-3xl font-bold text-purple-600 dark:text-purple-400">
                                                {calculateApprovalRate(
                                                    bestApproval.approved_for_submission,
                                                    bestApproval.claims_vetted,
                                                )}
                                                %
                                            </p>
                                        </div>
                                    );
                                })()}
                            </CardContent>
                        </Card>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
