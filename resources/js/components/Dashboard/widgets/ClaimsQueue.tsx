import { Link } from '@inertiajs/react';
import {
    ArrowRight,
    CheckCircle2,
    Clock,
    DollarSign,
    FileCheck,
    FileSearch,
    FolderOpen,
    Send,
} from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { MetricCard } from '@/components/Dashboard/MetricCard';
import { DashboardMetricsGrid } from '@/components/Dashboard/DashboardLayout';
import { cn } from '@/lib/utils';

export interface InsuranceMetricsData {
    pendingVetting: number;
    vettedReady: number;
    submittedThisMonth: number;
    totalClaimValue: number;
}

export interface InsuranceMetricsProps {
    metrics: InsuranceMetricsData;
    vettingHref?: string;
    submissionHref?: string;
}

function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('en-GH', {
        style: 'currency',
        currency: 'GHS',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(amount);
}

export function InsuranceMetrics({
    metrics,
    vettingHref,
    submissionHref,
}: InsuranceMetricsProps) {
    return (
        <DashboardMetricsGrid columns={4}>
            <MetricCard
                title="Pending Vetting"
                value={metrics.pendingVetting}
                icon={FileSearch}
                variant={metrics.pendingVetting > 20 ? 'warning' : 'primary'}
                href={vettingHref}
            />
            <MetricCard
                title="Ready to Submit"
                value={metrics.vettedReady}
                icon={FileCheck}
                variant={metrics.vettedReady > 0 ? 'success' : 'accent'}
                href={submissionHref}
            />
            <MetricCard
                title="Submitted This Month"
                value={metrics.submittedThisMonth}
                icon={Send}
                variant="info"
            />
            <MetricCard
                title="Total Claim Value"
                value={formatCurrency(metrics.totalClaimValue)}
                icon={DollarSign}
                variant="success"
            />
        </DashboardMetricsGrid>
    );
}

export interface ClaimSummary {
    id: number;
    claim_check_code: string;
    patient_name: string;
    insurance_provider: string;
    total_amount: number;
    days_pending: number;
}

export interface PendingClaimsProps {
    claims: ClaimSummary[];
    viewAllHref?: string;
    className?: string;
}

export function PendingClaims({
    claims,
    viewAllHref,
    className,
}: PendingClaimsProps) {
    // Show only top 5 oldest pending
    const oldest = claims.slice(0, 5);

    return (
        <Card className={cn('', className)}>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
                <div className="flex items-center gap-2">
                    <FileSearch className="h-5 w-5 text-primary" />
                    <div>
                        <CardTitle className="text-base font-semibold">
                            Oldest Pending Claims
                        </CardTitle>
                        <CardDescription>
                            Claims awaiting vetting
                        </CardDescription>
                    </div>
                </div>
                {viewAllHref && (
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={viewAllHref}>
                            View All
                            <ArrowRight className="ml-1 h-4 w-4" />
                        </Link>
                    </Button>
                )}
            </CardHeader>
            <CardContent>
                {oldest.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-6 text-muted-foreground">
                        <CheckCircle2 className="mb-2 h-8 w-8 opacity-50" />
                        <span>No claims pending vetting</span>
                    </div>
                ) : (
                    <div className="space-y-2">
                        {oldest.map((claim) => (
                            <div
                                key={claim.id}
                                className={cn(
                                    'flex items-center justify-between rounded-lg border p-3',
                                    claim.days_pending > 7 && 'border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950'
                                )}
                            >
                                <div className="flex flex-col">
                                    <div className="flex items-center gap-2">
                                        <span className="font-mono text-xs font-medium">
                                            {claim.claim_check_code}
                                        </span>
                                        {claim.days_pending > 7 && (
                                            <Badge variant="outline" className="text-xs text-amber-600">
                                                {claim.days_pending}d old
                                            </Badge>
                                        )}
                                    </div>
                                    <span className="text-sm">{claim.patient_name}</span>
                                    <span className="text-xs text-muted-foreground">
                                        {claim.insurance_provider}
                                    </span>
                                </div>
                                <div className="text-right">
                                    <span className="font-medium">
                                        {formatCurrency(claim.total_amount)}
                                    </span>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

export interface BatchSummary {
    id: number;
    batch_number: string;
    name: string;
    status: string;
    total_claims: number;
    total_amount: number;
}

export interface RecentBatchesProps {
    batches: BatchSummary[];
    viewAllHref?: string;
    className?: string;
}

function getBatchStatusVariant(
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'completed':
            return 'default';
        case 'submitted':
        case 'processing':
            return 'secondary';
        default:
            return 'outline';
    }
}

export function RecentBatches({
    batches,
    viewAllHref,
    className,
}: RecentBatchesProps) {
    const recent = batches.slice(0, 3);

    return (
        <Card className={cn('', className)}>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
                <div className="flex items-center gap-2">
                    <FolderOpen className="h-5 w-5 text-primary" />
                    <div>
                        <CardTitle className="text-base font-semibold">
                            Recent Batches
                        </CardTitle>
                        <CardDescription>
                            Latest batch submissions
                        </CardDescription>
                    </div>
                </div>
                {viewAllHref && (
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={viewAllHref}>
                            View All
                            <ArrowRight className="ml-1 h-4 w-4" />
                        </Link>
                    </Button>
                )}
            </CardHeader>
            <CardContent>
                {recent.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-6 text-muted-foreground">
                        <FolderOpen className="mb-2 h-8 w-8 opacity-50" />
                        <span>No recent batches</span>
                    </div>
                ) : (
                    <div className="space-y-2">
                        {recent.map((batch) => (
                            <div
                                key={batch.id}
                                className="flex items-center justify-between rounded-lg border p-3"
                            >
                                <div className="flex flex-col">
                                    <div className="flex items-center gap-2">
                                        <span className="font-mono text-xs font-medium">
                                            {batch.batch_number}
                                        </span>
                                        <Badge variant={getBatchStatusVariant(batch.status)} className="text-xs">
                                            {batch.status}
                                        </Badge>
                                    </div>
                                    <span className="text-xs text-muted-foreground">
                                        {batch.total_claims} claims
                                    </span>
                                </div>
                                <span className="font-medium">
                                    {formatCurrency(batch.total_amount)}
                                </span>
                            </div>
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
