import { Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRight,
    CheckCircle2,
    Clock,
    FileCheck,
    Package,
    Pill,
} from 'lucide-react';

import { DashboardMetricsGrid } from '@/components/Dashboard/DashboardLayout';
import { MetricCard } from '@/components/Dashboard/MetricCard';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';

export interface PharmacistMetricsData {
    pendingPrescriptions: number;
    dispensedToday: number;
    lowStockCount: number;
    expiringCount: number;
}

export interface PharmacistMetricsProps {
    metrics: PharmacistMetricsData;
    dispensingHref?: string;
    inventoryHref?: string;
}

export function PharmacistMetrics({
    metrics,
    dispensingHref,
    inventoryHref,
}: PharmacistMetricsProps) {
    return (
        <DashboardMetricsGrid columns={4}>
            <MetricCard
                title="Pending Prescriptions"
                value={metrics.pendingPrescriptions}
                icon={FileCheck}
                variant={
                    metrics.pendingPrescriptions > 10 ? 'warning' : 'primary'
                }
                href={dispensingHref}
            />
            <MetricCard
                title="Dispensed Today"
                value={metrics.dispensedToday}
                icon={Pill}
                variant="success"
            />
            <MetricCard
                title="Low Stock Items"
                value={metrics.lowStockCount}
                icon={Package}
                variant={metrics.lowStockCount > 0 ? 'danger' : 'accent'}
                href={inventoryHref}
            />
            <MetricCard
                title="Expiring Soon"
                value={metrics.expiringCount}
                icon={AlertTriangle}
                variant={metrics.expiringCount > 0 ? 'warning' : 'info'}
            />
        </DashboardMetricsGrid>
    );
}

export interface UrgentPrescription {
    id: number;
    patient_name: string;
    drug_name: string;
    quantity: number;
    wait_time: string;
    is_urgent: boolean;
}

export interface UrgentPrescriptionsProps {
    prescriptions: UrgentPrescription[];
    viewAllHref?: string;
    className?: string;
}

export function UrgentPrescriptions({
    prescriptions,
    viewAllHref,
    className,
}: UrgentPrescriptionsProps) {
    // Show only top 5 urgent/oldest
    const urgent = prescriptions.slice(0, 5);

    return (
        <Card className={cn('', className)}>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
                <div>
                    <CardTitle className="text-base font-semibold">
                        Priority Prescriptions
                    </CardTitle>
                    <CardDescription>
                        Oldest and urgent prescriptions
                    </CardDescription>
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
                {urgent.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-6 text-muted-foreground">
                        <CheckCircle2 className="mb-2 h-8 w-8 opacity-50" />
                        <span>No pending prescriptions</span>
                    </div>
                ) : (
                    <div className="space-y-2">
                        {urgent.map((rx) => (
                            <div
                                key={rx.id}
                                className={cn(
                                    'flex items-center justify-between rounded-lg border p-3',
                                    rx.is_urgent &&
                                        'border-red-300 bg-red-50 dark:border-red-800 dark:bg-red-950',
                                )}
                            >
                                <div className="flex flex-col">
                                    <div className="flex items-center gap-2">
                                        {rx.is_urgent && (
                                            <Badge
                                                variant="destructive"
                                                className="text-xs"
                                            >
                                                Urgent
                                            </Badge>
                                        )}
                                        <span className="text-sm font-medium">
                                            {rx.patient_name}
                                        </span>
                                    </div>
                                    <span className="text-xs text-muted-foreground">
                                        {rx.drug_name} × {rx.quantity}
                                    </span>
                                </div>
                                <div className="flex items-center gap-1 text-muted-foreground">
                                    <Clock className="h-3 w-3" />
                                    <span className="font-mono text-xs">
                                        {rx.wait_time}
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
