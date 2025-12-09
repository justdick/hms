import DateRangeFilter from '@/components/Insurance/DateRangeFilter';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
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
import { cache, TTL } from '@/lib/cache';
import { Head, Link } from '@inertiajs/react';
import axios from 'axios';
import {
    AlertTriangle,
    ArrowRight,
    BarChart3,
    Banknote,
    Building2,
    CheckCircle,
    FileBarChart,
    FileText,
    Hash,
    Package,
    TrendingDown,
    TrendingUp,
    Users,
} from 'lucide-react';
import { lazy, Suspense, useEffect, useState } from 'react';

// Lazy load AnalyticsWidget for better performance
const AnalyticsWidget = lazy(
    () => import('@/components/Insurance/AnalyticsWidget'),
);

interface ClaimsSummaryData {
    total_claims: number;
    total_claimed_amount: number;
    total_approved_amount: number;
    total_paid_amount: number;
    outstanding_amount: number;
    status_breakdown: Record<
        string,
        { status: string; count: number; total_amount: number }
    >;
    claims_by_provider: Array<{
        id: number;
        name: string;
        claim_count: number;
        total_claimed: number;
        total_approved: number;
        total_paid: number;
    }>;
}

interface RevenueAnalysisData {
    insurance_revenue: number;
    cash_revenue: number;
    total_revenue: number;
    insurance_percentage: number;
    cash_percentage: number;
    monthly_trend: Array<{
        month: string;
        insurance: number;
        cash: number;
        total: number;
    }>;
}

interface OutstandingClaimsData {
    total_outstanding: number;
    total_claims: number;
    aging_analysis: Record<string, { count: number; amount: number }>;
    by_provider: Record<
        string,
        { count: number; amount: number; oldest_claim_days: number }
    >;
}

export default function Index() {
    const [dateFrom, setDateFrom] = useState(() => {
        const now = new Date();
        const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
        return firstDay.toISOString().split('T')[0];
    });

    const [dateTo, setDateTo] = useState(() => {
        const now = new Date();
        const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);
        return lastDay.toISOString().split('T')[0];
    });

    // Arrow key navigation for widgets
    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
                const widgets = document.querySelectorAll(
                    '[data-widget-index]',
                );
                const activeElement = document.activeElement;

                if (!activeElement) return;

                const currentWidget = activeElement.closest(
                    '[data-widget-index]',
                );
                if (!currentWidget) return;

                const currentIndex = parseInt(
                    currentWidget.getAttribute('data-widget-index') || '0',
                );
                let nextIndex = currentIndex;

                if (e.key === 'ArrowRight') {
                    nextIndex = (currentIndex + 1) % widgets.length;
                } else if (e.key === 'ArrowLeft') {
                    nextIndex =
                        (currentIndex - 1 + widgets.length) % widgets.length;
                }

                const nextWidget = widgets[nextIndex] as HTMLElement;
                const focusableElement = nextWidget.querySelector(
                    'button',
                ) as HTMLElement;
                if (focusableElement) {
                    focusableElement.focus();
                }
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, []);

    // Widget data states
    const [claimsSummaryData, setClaimsSummaryData] =
        useState<ClaimsSummaryData | null>(null);
    const [revenueAnalysisData, setRevenueAnalysisData] =
        useState<RevenueAnalysisData | null>(null);
    const [outstandingClaimsData, setOutstandingClaimsData] =
        useState<OutstandingClaimsData | null>(null);

    // Loading states
    const [claimsSummaryLoading, setClaimsSummaryLoading] = useState(false);
    const [revenueAnalysisLoading, setRevenueAnalysisLoading] = useState(false);
    const [outstandingClaimsLoading, setOutstandingClaimsLoading] =
        useState(false);

    const formatCurrency = (amount: number | string) => {
        const numAmount =
            typeof amount === 'string' ? parseFloat(amount) : amount;
        return `GHS ${numAmount.toFixed(2)}`;
    };

    const loadClaimsSummary = async () => {
        // Check cache first
        const cacheKey = `claims-summary-${dateFrom}-${dateTo}`;
        const cachedData = cache.get<ClaimsSummaryData>(cacheKey);

        if (cachedData) {
            setClaimsSummaryData(cachedData);
            return;
        }

        setClaimsSummaryLoading(true);
        try {
            const response = await axios.get(
                '/admin/insurance/reports/claims-summary',
                {
                    params: { date_from: dateFrom, date_to: dateTo },
                    headers: { Accept: 'application/json' },
                },
            );
            const data = response.data.data;

            // Cache for 5 minutes
            cache.set(cacheKey, data, TTL.FIVE_MINUTES);

            setClaimsSummaryData(data);
        } catch (error) {
            console.error('Failed to load claims summary:', error);
        } finally {
            setClaimsSummaryLoading(false);
        }
    };

    const loadRevenueAnalysis = async () => {
        // Check cache first
        const cacheKey = `revenue-analysis-${dateFrom}-${dateTo}`;
        const cachedData = cache.get<RevenueAnalysisData>(cacheKey);

        if (cachedData) {
            setRevenueAnalysisData(cachedData);
            return;
        }

        setRevenueAnalysisLoading(true);
        try {
            const response = await axios.get(
                '/admin/insurance/reports/revenue-analysis',
                {
                    params: { date_from: dateFrom, date_to: dateTo },
                    headers: { Accept: 'application/json' },
                },
            );
            const data = response.data.data;

            // Cache for 5 minutes
            cache.set(cacheKey, data, TTL.FIVE_MINUTES);

            setRevenueAnalysisData(data);
        } catch (error) {
            console.error('Failed to load revenue analysis:', error);
        } finally {
            setRevenueAnalysisLoading(false);
        }
    };

    const loadOutstandingClaims = async () => {
        // Check cache first
        const cacheKey = 'outstanding-claims';
        const cachedData = cache.get<OutstandingClaimsData>(cacheKey);

        if (cachedData) {
            setOutstandingClaimsData(cachedData);
            return;
        }

        setOutstandingClaimsLoading(true);
        try {
            const response = await axios.get(
                '/admin/insurance/reports/outstanding-claims',
                {
                    params: {},
                    headers: { Accept: 'application/json' },
                },
            );
            const data = response.data.data;

            // Cache for 5 minutes
            cache.set(cacheKey, data, TTL.FIVE_MINUTES);

            setOutstandingClaimsData(data);
        } catch (error) {
            console.error('Failed to load outstanding claims:', error);
        } finally {
            setOutstandingClaimsLoading(false);
        }
    };

    const handleApplyFilter = () => {
        // Reload all widgets with new date range
        if (claimsSummaryData) loadClaimsSummary();
        if (revenueAnalysisData) loadRevenueAnalysis();
        // Outstanding claims doesn't use date filter
    };

    const handleResetFilter = () => {
        const now = new Date();
        const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
        const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);

        setDateFrom(firstDay.toISOString().split('T')[0]);
        setDateTo(lastDay.toISOString().split('T')[0]);
    };

    return (
        <AppLayout>
            <Head title="Insurance Analytics Dashboard" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-bold">
                        Insurance Analytics Dashboard
                    </h1>
                    <p className="mt-2 text-muted-foreground">
                        Comprehensive analytics and reporting for insurance
                        claims management
                    </p>
                </div>

                {/* Quick Links to Detailed Reports */}
                <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                    <Link href="/admin/insurance/reports/claims-summary">
                        <div className="flex items-center gap-3 rounded-lg border p-4 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                            <FileText className="h-6 w-6 text-blue-600" />
                            <div>
                                <p className="font-medium">Claims Summary</p>
                                <p className="text-sm text-gray-500">View full report</p>
                            </div>
                            <ArrowRight className="ml-auto h-4 w-4 text-gray-400" />
                        </div>
                    </Link>
                    <Link href="/admin/insurance/reports/outstanding-claims">
                        <div className="flex items-center gap-3 rounded-lg border p-4 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                            <FileBarChart className="h-6 w-6 text-orange-600" />
                            <div>
                                <p className="font-medium">Outstanding Claims</p>
                                <p className="text-sm text-gray-500">View aging report</p>
                            </div>
                            <ArrowRight className="ml-auto h-4 w-4 text-gray-400" />
                        </div>
                    </Link>
                    <Link href="/admin/insurance/reports/rejection-analysis">
                        <div className="flex items-center gap-3 rounded-lg border p-4 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                            <TrendingDown className="h-6 w-6 text-red-600" />
                            <div>
                                <p className="font-medium">Rejection Analysis</p>
                                <p className="text-sm text-gray-500">View rejections</p>
                            </div>
                            <ArrowRight className="ml-auto h-4 w-4 text-gray-400" />
                        </div>
                    </Link>
                    <Link href="/admin/insurance/reports/tariff-coverage">
                        <div className="flex items-center gap-3 rounded-lg border p-4 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                            <Package className="h-6 w-6 text-green-600" />
                            <div>
                                <p className="font-medium">Tariff Coverage</p>
                                <p className="text-sm text-gray-500">NHIS mapping status</p>
                            </div>
                            <ArrowRight className="ml-auto h-4 w-4 text-gray-400" />
                        </div>
                    </Link>
                </div>

                {/* Date Range Filter */}
                <DateRangeFilter
                    dateFrom={dateFrom}
                    dateTo={dateTo}
                    onDateFromChange={setDateFrom}
                    onDateToChange={setDateTo}
                    onApply={handleApplyFilter}
                    onReset={handleResetFilter}
                />

                {/* Analytics Widgets */}
                <Suspense
                    fallback={
                        <div className="grid gap-6 lg:grid-cols-2">
                            {[...Array(6)].map((_, i) => (
                                <div
                                    key={i}
                                    className="rounded-lg border p-6 dark:border-gray-700"
                                >
                                    <div className="flex items-center gap-3">
                                        <Skeleton className="h-10 w-10 rounded-lg" />
                                        <div className="flex-1 space-y-2">
                                            <Skeleton className="h-5 w-32" />
                                            <Skeleton className="h-4 w-48" />
                                        </div>
                                    </div>
                                    <div className="mt-4 space-y-3">
                                        <Skeleton className="h-20 w-full" />
                                        <Skeleton className="h-20 w-full" />
                                    </div>
                                </div>
                            ))}
                        </div>
                    }
                >
                    <div className="grid gap-6 lg:grid-cols-2">
                        {/* Claims Summary Widget */}
                        <div data-widget-index="0">
                            <AnalyticsWidget
                                title="Claims Summary"
                                description="Overview of all claims with status breakdown"
                                icon={FileText}
                                color="text-blue-600 dark:text-blue-400"
                                isLoading={claimsSummaryLoading}
                                onExpand={loadClaimsSummary}
                                summary={
                                    claimsSummaryData ? (
                                        <div className="grid grid-cols-2 gap-3">
                                            <StatCard
                                                label="Total Claims"
                                                value={claimsSummaryData.total_claims}
                                                icon={<Hash className="h-5 w-5" />}
                                            />
                                            <StatCard
                                                label="Total Claimed"
                                                value={formatCurrency(claimsSummaryData.total_claimed_amount)}
                                                icon={<Banknote className="h-5 w-5" />}
                                                variant="info"
                                            />
                                            <StatCard
                                                label="Total Approved"
                                                value={formatCurrency(claimsSummaryData.total_approved_amount)}
                                                icon={<CheckCircle className="h-5 w-5" />}
                                                variant="success"
                                            />
                                            <StatCard
                                                label="Outstanding"
                                                value={formatCurrency(claimsSummaryData.outstanding_amount)}
                                                icon={<AlertTriangle className="h-5 w-5" />}
                                                variant="error"
                                            />
                                        </div>
                                    ) : (
                                        <p className="text-sm text-gray-500">
                                            Click to load data
                                        </p>
                                    )
                                }
                                details={
                                    claimsSummaryData && (
                                        <div className="space-y-6">
                                            {/* Status Breakdown */}
                                            <div>
                                                <h4 className="mb-3 font-semibold">
                                                    Status Breakdown
                                                </h4>
                                                <div className="grid grid-cols-2 gap-3 md:grid-cols-3">
                                                    {Object.entries(
                                                        claimsSummaryData.status_breakdown,
                                                    ).map(([status, data]) => (
                                                        <div
                                                            key={status}
                                                            className="rounded-lg border p-3 dark:border-gray-700"
                                                        >
                                                            <Badge className="mb-1">
                                                                {status}
                                                            </Badge>
                                                            <p className="text-xl font-bold">
                                                                {data.count}
                                                            </p>
                                                            <p className="text-xs text-gray-600">
                                                                {formatCurrency(
                                                                    data.total_amount,
                                                                )}
                                                            </p>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>

                                            {/* Claims by Provider */}
                                            <div>
                                                <h4 className="mb-3 font-semibold">
                                                    Claims by Provider
                                                </h4>
                                                <div className="overflow-x-auto">
                                                    <Table>
                                                        <TableHeader>
                                                            <TableRow>
                                                                <TableHead>
                                                                    Provider
                                                                </TableHead>
                                                                <TableHead className="text-right">
                                                                    Claims
                                                                </TableHead>
                                                                <TableHead className="text-right">
                                                                    Claimed
                                                                </TableHead>
                                                                <TableHead className="text-right">
                                                                    Approved
                                                                </TableHead>
                                                            </TableRow>
                                                        </TableHeader>
                                                        <TableBody>
                                                            {claimsSummaryData.claims_by_provider.map(
                                                                (provider) => (
                                                                    <TableRow
                                                                        key={
                                                                            provider.id
                                                                        }
                                                                    >
                                                                        <TableCell className="font-medium">
                                                                            <div className="flex items-center gap-2">
                                                                                <Building2 className="h-4 w-4 text-gray-500" />
                                                                                {
                                                                                    provider.name
                                                                                }
                                                                            </div>
                                                                        </TableCell>
                                                                        <TableCell className="text-right">
                                                                            {
                                                                                provider.claim_count
                                                                            }
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
                                                                    </TableRow>
                                                                ),
                                                            )}
                                                        </TableBody>
                                                    </Table>
                                                </div>
                                            </div>
                                        </div>
                                    )
                                }
                            />
                        </div>

                        {/* Revenue Analysis Widget */}
                        <div data-widget-index="1">
                            <AnalyticsWidget
                                title="Revenue Analysis"
                                description="Compare insurance vs cash revenue"
                                icon={TrendingUp}
                                color="text-green-600 dark:text-green-400"
                                isLoading={revenueAnalysisLoading}
                                onExpand={loadRevenueAnalysis}
                                summary={
                                    revenueAnalysisData ? (
                                        <div className="space-y-4">
                                            <div className="rounded-lg border p-4 dark:border-gray-700">
                                                <p className="text-sm text-gray-600 dark:text-gray-400">
                                                    Total Revenue
                                                </p>
                                                <p className="text-2xl font-bold">
                                                    {formatCurrency(
                                                        revenueAnalysisData.total_revenue,
                                                    )}
                                                </p>
                                            </div>
                                            <div className="grid grid-cols-2 gap-4">
                                                <div className="rounded-lg bg-green-50 p-3 dark:bg-green-950/20">
                                                    <p className="text-xs text-gray-600 dark:text-gray-400">
                                                        Insurance
                                                    </p>
                                                    <p className="text-lg font-bold text-green-600">
                                                        {formatCurrency(
                                                            revenueAnalysisData.insurance_revenue,
                                                        )}
                                                    </p>
                                                    <p className="text-xs text-gray-500">
                                                        {revenueAnalysisData.insurance_percentage.toFixed(
                                                            1,
                                                        )}
                                                        %
                                                    </p>
                                                </div>
                                                <div className="rounded-lg bg-blue-50 p-3 dark:bg-blue-950/20">
                                                    <p className="text-xs text-gray-600 dark:text-gray-400">
                                                        Cash
                                                    </p>
                                                    <p className="text-lg font-bold text-blue-600">
                                                        {formatCurrency(
                                                            revenueAnalysisData.cash_revenue,
                                                        )}
                                                    </p>
                                                    <p className="text-xs text-gray-500">
                                                        {revenueAnalysisData.cash_percentage.toFixed(
                                                            1,
                                                        )}
                                                        %
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    ) : (
                                        <p className="text-sm text-gray-500">
                                            Click to load data
                                        </p>
                                    )
                                }
                                details={
                                    revenueAnalysisData && (
                                        <div>
                                            <h4 className="mb-3 font-semibold">
                                                Monthly Trend (Last 6 Months)
                                            </h4>
                                            <div className="overflow-x-auto">
                                                <Table>
                                                    <TableHeader>
                                                        <TableRow>
                                                            <TableHead>
                                                                Month
                                                            </TableHead>
                                                            <TableHead className="text-right">
                                                                Insurance
                                                            </TableHead>
                                                            <TableHead className="text-right">
                                                                Cash
                                                            </TableHead>
                                                            <TableHead className="text-right">
                                                                Total
                                                            </TableHead>
                                                        </TableRow>
                                                    </TableHeader>
                                                    <TableBody>
                                                        {revenueAnalysisData.monthly_trend.map(
                                                            (trend) => (
                                                                <TableRow
                                                                    key={
                                                                        trend.month
                                                                    }
                                                                >
                                                                    <TableCell className="font-medium">
                                                                        {
                                                                            trend.month
                                                                        }
                                                                    </TableCell>
                                                                    <TableCell className="text-right text-green-600">
                                                                        {formatCurrency(
                                                                            trend.insurance,
                                                                        )}
                                                                    </TableCell>
                                                                    <TableCell className="text-right text-blue-600">
                                                                        {formatCurrency(
                                                                            trend.cash,
                                                                        )}
                                                                    </TableCell>
                                                                    <TableCell className="text-right font-semibold">
                                                                        {formatCurrency(
                                                                            trend.total,
                                                                        )}
                                                                    </TableCell>
                                                                </TableRow>
                                                            ),
                                                        )}
                                                    </TableBody>
                                                </Table>
                                            </div>
                                        </div>
                                    )
                                }
                            />
                        </div>

                        {/* Outstanding Claims Widget */}
                        <div data-widget-index="2">
                            <AnalyticsWidget
                                title="Outstanding Claims"
                                description="Track unpaid claims with aging analysis"
                                icon={FileBarChart}
                                color="text-orange-600 dark:text-orange-400"
                                isLoading={outstandingClaimsLoading}
                                onExpand={loadOutstandingClaims}
                                summary={
                                    outstandingClaimsData ? (
                                        <div className="grid grid-cols-2 gap-3">
                                            <StatCard
                                                label="Outstanding Claims"
                                                value={outstandingClaimsData.total_claims}
                                                icon={<Hash className="h-5 w-5" />}
                                                variant="warning"
                                            />
                                            <StatCard
                                                label="Outstanding Amount"
                                                value={formatCurrency(outstandingClaimsData.total_outstanding)}
                                                icon={<AlertTriangle className="h-5 w-5" />}
                                                variant="error"
                                            />
                                        </div>
                                    ) : (
                                        <p className="text-sm text-gray-500">
                                            Click to load data
                                        </p>
                                    )
                                }
                                details={
                                    outstandingClaimsData && (
                                        <div className="space-y-6">
                                            {/* Aging Analysis */}
                                            <div>
                                                <h4 className="mb-3 font-semibold">
                                                    Aging Analysis
                                                </h4>
                                                <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                                                    {Object.entries(
                                                        outstandingClaimsData.aging_analysis,
                                                    ).map(([bucket, data]) => (
                                                        <div
                                                            key={bucket}
                                                            className="rounded-lg border p-3 dark:border-gray-700"
                                                        >
                                                            <Badge
                                                                variant={
                                                                    bucket ===
                                                                    '90+'
                                                                        ? 'destructive'
                                                                        : 'outline'
                                                                }
                                                            >
                                                                {bucket} Days
                                                            </Badge>
                                                            <p className="mt-2 text-xl font-bold">
                                                                {data.count}
                                                            </p>
                                                            <p className="text-xs text-gray-600">
                                                                {formatCurrency(
                                                                    data.amount,
                                                                )}
                                                            </p>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>

                                            {/* By Provider */}
                                            <div>
                                                <h4 className="mb-3 font-semibold">
                                                    By Provider
                                                </h4>
                                                <div className="overflow-x-auto">
                                                    <Table>
                                                        <TableHeader>
                                                            <TableRow>
                                                                <TableHead>
                                                                    Provider
                                                                </TableHead>
                                                                <TableHead className="text-right">
                                                                    Claims
                                                                </TableHead>
                                                                <TableHead className="text-right">
                                                                    Amount
                                                                </TableHead>
                                                                <TableHead className="text-right">
                                                                    Oldest
                                                                </TableHead>
                                                            </TableRow>
                                                        </TableHeader>
                                                        <TableBody>
                                                            {Object.entries(
                                                                outstandingClaimsData.by_provider,
                                                            ).map(
                                                                ([
                                                                    provider,
                                                                    data,
                                                                ]) => (
                                                                    <TableRow
                                                                        key={
                                                                            provider
                                                                        }
                                                                    >
                                                                        <TableCell className="font-medium">
                                                                            {
                                                                                provider
                                                                            }
                                                                        </TableCell>
                                                                        <TableCell className="text-right">
                                                                            {
                                                                                data.count
                                                                            }
                                                                        </TableCell>
                                                                        <TableCell className="text-right text-red-600">
                                                                            {formatCurrency(
                                                                                data.amount,
                                                                            )}
                                                                        </TableCell>
                                                                        <TableCell className="text-right">
                                                                            <Badge
                                                                                variant={
                                                                                    data.oldest_claim_days >
                                                                                    90
                                                                                        ? 'destructive'
                                                                                        : 'outline'
                                                                                }
                                                                            >
                                                                                {
                                                                                    data.oldest_claim_days
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
                                            </div>
                                        </div>
                                    )
                                }
                            />
                        </div>

                        {/* Vetting Performance Widget - Placeholder */}
                        <div data-widget-index="3">
                            <AnalyticsWidget
                                title="Vetting Performance"
                                description="Monitor vetting officer productivity"
                                icon={Users}
                                color="text-purple-600 dark:text-purple-400"
                                summary={
                                    <p className="text-sm text-gray-500">
                                        Click to load data
                                    </p>
                                }
                                details={
                                    <p className="text-sm text-gray-500">
                                        Vetting performance data will be loaded
                                        here
                                    </p>
                                }
                            />
                        </div>

                        {/* Utilization Report Widget - Placeholder */}
                        <div data-widget-index="4">
                            <AnalyticsWidget
                                title="Utilization Report"
                                description="Analyze most used services"
                                icon={BarChart3}
                                color="text-cyan-600 dark:text-cyan-400"
                                summary={
                                    <p className="text-sm text-gray-500">
                                        Click to load data
                                    </p>
                                }
                                details={
                                    <p className="text-sm text-gray-500">
                                        Utilization data will be loaded here
                                    </p>
                                }
                            />
                        </div>

                        {/* Rejection Analysis Widget - Placeholder */}
                        <div data-widget-index="5">
                            <AnalyticsWidget
                                title="Rejection Analysis"
                                description="Review rejection reasons and trends"
                                icon={TrendingDown}
                                color="text-red-600 dark:text-red-400"
                                summary={
                                    <p className="text-sm text-gray-500">
                                        Click to load data
                                    </p>
                                }
                                details={
                                    <p className="text-sm text-gray-500">
                                        Rejection analysis data will be loaded
                                        here
                                    </p>
                                }
                            />
                        </div>
                    </div>
                </Suspense>
            </div>
        </AppLayout>
    );
}
