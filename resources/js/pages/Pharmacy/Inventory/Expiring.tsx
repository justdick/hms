import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    Calendar,
    Clock,
    DollarSign,
    Package,
    Plus,
} from 'lucide-react';
import { expiringColumns, type ExpiringBatch } from './expiring-columns';
import { DataTable } from './expiring-data-table';

interface PaginatedBatches {
    data: ExpiringBatch[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}

interface Props {
    batches: PaginatedBatches;
}

export default function ExpiringInventory({ batches }: Props) {
    const totalExpiring = batches.total;
    const expiredBatches = batches.data.filter((batch) => {
        const expiryDate = new Date(batch.expiry_date);
        const now = new Date();
        return expiryDate < now;
    }).length;

    const expiringSoon = batches.data.filter((batch) => {
        const expiryDate = new Date(batch.expiry_date);
        const now = new Date();
        const daysUntilExpiry = Math.ceil(
            (expiryDate.getTime() - now.getTime()) / (1000 * 60 * 60 * 24),
        );
        return daysUntilExpiry <= 7 && daysUntilExpiry >= 0;
    }).length;

    const totalValue = batches.data.reduce((sum, batch) => {
        return sum + batch.quantity_remaining * batch.selling_price_per_unit;
    }, 0);

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Pharmacy', href: '/pharmacy' },
                { title: 'Inventory', href: '/pharmacy/inventory' },
                {
                    title: 'Expiring Soon',
                    href: '/pharmacy/inventory/expiring',
                },
            ]}
        >
            <Head title="Expiring Drug Batches" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="ghost" size="sm" asChild>
                            <Link href="/pharmacy/inventory">
                                <ArrowLeft className="mr-1 h-4 w-4" />
                                Back to Inventory
                            </Link>
                        </Button>
                        <div>
                            <h1 className="flex items-center gap-2 text-2xl font-bold">
                                <Calendar className="h-6 w-6 text-orange-600" />
                                Expiring Drug Batches
                            </h1>
                            <p className="text-muted-foreground">
                                Monitor batches approaching expiry dates
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/pharmacy/inventory">
                                <Package className="mr-1 h-4 w-4" />
                                Full Inventory
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/pharmacy/inventory/low-stock">
                                <AlertTriangle className="mr-1 h-4 w-4" />
                                Low Stock
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link href="/pharmacy/drugs/create">
                                <Plus className="mr-1 h-4 w-4" />
                                Add Drug
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Alert Banner */}
                {totalExpiring > 0 && (
                    <div className="rounded-lg border border-orange-200 bg-orange-50 p-4 dark:border-orange-800 dark:bg-orange-950">
                        <div className="flex items-start gap-3">
                            <Calendar className="mt-0.5 h-5 w-5 text-orange-600" />
                            <div>
                                <h3 className="font-medium text-orange-900 dark:text-orange-100">
                                    Expiry Monitoring Alert
                                </h3>
                                <p className="mt-1 text-sm text-orange-700 dark:text-orange-300">
                                    {totalExpiring} batch
                                    {totalExpiring !== 1 ? 'es' : ''}{' '}
                                    {totalExpiring === 1 ? 'is' : 'are'}{' '}
                                    approaching expiry.
                                    {expiredBatches > 0 &&
                                        ` ${expiredBatches} ${expiredBatches === 1 ? 'has' : 'have'} already expired.`}
                                    {expiringSoon > 0 &&
                                        ` ${expiringSoon} ${expiringSoon === 1 ? 'expires' : 'expire'} within 7 days.`}
                                </p>
                            </div>
                        </div>
                    </div>
                )}

                {/* Expiry Statistics */}
                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Total Expiring
                            </CardTitle>
                            <Calendar className="h-4 w-4 text-orange-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-orange-600">
                                {totalExpiring}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Batches to monitor
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Already Expired
                            </CardTitle>
                            <AlertTriangle className="h-4 w-4 text-red-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-red-600">
                                {expiredBatches}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Immediate removal
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Expiring Soon
                            </CardTitle>
                            <Clock className="h-4 w-4 text-orange-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-orange-500">
                                {expiringSoon}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Within 7 days
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Value at Risk
                            </CardTitle>
                            <DollarSign className="h-4 w-4 text-blue-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-blue-600">
                                ${totalValue.toLocaleString()}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Potential loss value
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Priority Actions */}
                <Card className="border-red-200 bg-gradient-to-r from-red-50 to-orange-50 dark:border-red-800 dark:from-red-950 dark:to-orange-950">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-red-900 dark:text-red-100">
                            <AlertTriangle className="h-5 w-5" />
                            Recommended Actions
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-3 md:grid-cols-3">
                            <div className="flex items-center gap-2 text-sm">
                                <Badge className="bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300">
                                    Urgent
                                </Badge>
                                <span>
                                    Remove {expiredBatches} expired batches
                                    immediately
                                </span>
                            </div>
                            <div className="flex items-center gap-2 text-sm">
                                <Badge className="bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300">
                                    This Week
                                </Badge>
                                <span>
                                    Prioritize {expiringSoon} batches expiring
                                    soon
                                </span>
                            </div>
                            <div className="flex items-center gap-2 text-sm">
                                <Badge className="bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300">
                                    Monitor
                                </Badge>
                                <span>
                                    Track{' '}
                                    {totalExpiring -
                                        expiredBatches -
                                        expiringSoon}{' '}
                                    other expiring batches
                                </span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Expiring Batches DataTable */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Calendar className="h-5 w-5 text-orange-600" />
                            Expiring Drug Batches ({totalExpiring})
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {batches.data.length > 0 ? (
                            <DataTable
                                columns={expiringColumns}
                                data={batches.data}
                                pagination={{
                                    current_page: batches.current_page,
                                    last_page: batches.last_page,
                                    per_page: batches.per_page,
                                    total: batches.total,
                                    links: batches.links,
                                }}
                            />
                        ) : (
                            <div className="py-12 text-center">
                                <Package className="mx-auto mb-4 h-16 w-16 text-green-600 opacity-50" />
                                <h3 className="mb-2 text-lg font-medium text-green-800">
                                    No Expiring Batches!
                                </h3>
                                <p className="mb-4 text-muted-foreground">
                                    No drug batches are approaching expiry
                                    dates. Your inventory is well-managed!
                                </p>
                                <Button variant="outline" asChild>
                                    <Link href="/pharmacy/inventory">
                                        <Package className="mr-1 h-4 w-4" />
                                        View Full Inventory
                                    </Link>
                                </Button>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
