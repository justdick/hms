import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    BarChart3,
    Building,
    Calendar,
    DollarSign,
    Edit,
    Eye,
    Package,
    Plus,
} from 'lucide-react';

interface Drug {
    id: number;
    name: string;
    generic_name?: string;
    brand_name?: string;
    drug_code: string;
    category: string;
    form: string;
    description?: string;
    unit_price: number;
    unit_type: string;
    minimum_stock_level: number;
    maximum_stock_level: number;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    batches: DrugBatch[];
    total_stock?: number;
    is_low_stock?: boolean;
}

interface DrugBatch {
    id: number;
    batch_number: string;
    supplier: {
        id: number;
        name: string;
    };
    expiry_date: string;
    manufacture_date?: string;
    quantity_received: number;
    quantity_remaining: number;
    cost_per_unit: number;
    selling_price_per_unit: number;
    received_date: string;
    notes?: string;
}

interface Props {
    drug: Drug;
}

const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleDateString();
};

const getDaysUntilExpiry = (expiryDate: string) => {
    const expiry = new Date(expiryDate);
    const now = new Date();
    const daysUntilExpiry = Math.ceil(
        (expiry.getTime() - now.getTime()) / (1000 * 60 * 60 * 24),
    );
    return daysUntilExpiry;
};

const getBatchStatus = (batch: DrugBatch) => {
    const daysUntilExpiry = getDaysUntilExpiry(batch.expiry_date);

    if (daysUntilExpiry < 0) {
        return {
            label: 'Expired',
            className:
                'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
        };
    }
    if (daysUntilExpiry <= 7) {
        return {
            label: 'Expires Soon',
            className:
                'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300',
        };
    }
    if (daysUntilExpiry <= 30) {
        return {
            label: 'Expiring',
            className:
                'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
        };
    }
    return {
        label: 'Good',
        className:
            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    };
};

export default function ShowDrug({ drug }: Props) {
    const totalStock = drug.batches.reduce(
        (sum, batch) => sum + batch.quantity_remaining,
        0,
    );
    const totalValue = drug.batches.reduce(
        (sum, batch) =>
            sum +
            batch.quantity_remaining * Number(batch.selling_price_per_unit),
        0,
    );
    const averageCost =
        drug.batches.length > 0
            ? drug.batches.reduce(
                  (sum, batch) => sum + Number(batch.cost_per_unit),
                  0,
              ) / drug.batches.length
            : 0;
    const activeBatches = drug.batches.filter(
        (batch) => batch.quantity_remaining > 0,
    );
    const expiredBatches = drug.batches.filter(
        (batch) => getDaysUntilExpiry(batch.expiry_date) < 0,
    );

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Pharmacy', href: '/pharmacy' },
                { title: 'Drugs', href: '/pharmacy/drugs' },
                { title: drug.name, href: `/pharmacy/drugs/${drug.id}` },
            ]}
        >
            <Head title={`${drug.name} - Drug Details`} />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="ghost" size="sm" asChild>
                            <Link href="/pharmacy/drugs">
                                <ArrowLeft className="mr-1 h-4 w-4" />
                                Back to Drugs
                            </Link>
                        </Button>
                        <div>
                            <h1 className="flex items-center gap-2 text-2xl font-bold">
                                <Package className="h-6 w-6" />
                                {drug.name}
                                {!drug.is_active && (
                                    <Badge variant="secondary">Inactive</Badge>
                                )}
                            </h1>
                            <p className="text-muted-foreground">
                                {drug.generic_name && `${drug.generic_name} • `}
                                {drug.category} • {drug.form}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button variant="outline" asChild>
                            <Link href={`/pharmacy/drugs/${drug.id}/batches`}>
                                <Package className="mr-1 h-4 w-4" />
                                Manage Batches
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={`/pharmacy/drugs/${drug.id}/edit`}>
                                <Edit className="mr-1 h-4 w-4" />
                                Edit Drug
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Stock Alert */}
                {totalStock <= drug.minimum_stock_level && (
                    <div className="rounded-lg border border-orange-200 bg-orange-50 p-4 dark:border-orange-800 dark:bg-orange-950">
                        <div className="flex items-start gap-3">
                            <AlertTriangle className="mt-0.5 h-5 w-5 text-orange-600" />
                            <div>
                                <h3 className="font-medium text-orange-900 dark:text-orange-100">
                                    {totalStock === 0
                                        ? 'Out of Stock'
                                        : 'Low Stock Alert'}
                                </h3>
                                <p className="mt-1 text-sm text-orange-700 dark:text-orange-300">
                                    Current stock ({totalStock} {drug.unit_type}
                                    ) is{' '}
                                    {totalStock === 0
                                        ? 'empty'
                                        : `below minimum level (${drug.minimum_stock_level} ${drug.unit_type})`}
                                    . Consider restocking immediately.
                                </p>
                            </div>
                        </div>
                    </div>
                )}

                <div className="grid gap-6 md:grid-cols-3">
                    {/* Drug Information */}
                    <div className="space-y-6 md:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Package className="h-5 w-5" />
                                    Drug Information
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label className="text-sm font-medium text-muted-foreground">
                                            Drug Code
                                        </label>
                                        <p className="font-mono text-lg">
                                            {drug.drug_code}
                                        </p>
                                    </div>
                                    <div>
                                        <label className="text-sm font-medium text-muted-foreground">
                                            Category
                                        </label>
                                        <p>{drug.category}</p>
                                    </div>
                                    <div>
                                        <label className="text-sm font-medium text-muted-foreground">
                                            Form
                                        </label>
                                        <p>{drug.form}</p>
                                    </div>
                                    <div>
                                        <label className="text-sm font-medium text-muted-foreground">
                                            Unit Type
                                        </label>
                                        <p>{drug.unit_type}</p>
                                    </div>
                                    {drug.generic_name && (
                                        <div>
                                            <label className="text-sm font-medium text-muted-foreground">
                                                Generic Name
                                            </label>
                                            <p>{drug.generic_name}</p>
                                        </div>
                                    )}
                                    {drug.brand_name && (
                                        <div>
                                            <label className="text-sm font-medium text-muted-foreground">
                                                Brand Name
                                            </label>
                                            <p>{drug.brand_name}</p>
                                        </div>
                                    )}
                                </div>
                                {drug.description && (
                                    <div>
                                        <label className="text-sm font-medium text-muted-foreground">
                                            Description
                                        </label>
                                        <p className="mt-1">
                                            {drug.description}
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Batch Information */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <Calendar className="h-5 w-5" />
                                        Batch Information ({drug.batches.length}
                                        )
                                    </div>
                                    <Button size="sm" asChild>
                                        <Link
                                            href={`/pharmacy/drugs/${drug.id}/batches`}
                                        >
                                            <Plus className="mr-1 h-3 w-3" />
                                            Add Batch
                                        </Link>
                                    </Button>
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {drug.batches.length > 0 ? (
                                    <div className="space-y-3">
                                        {drug.batches
                                            .slice(0, 5)
                                            .map((batch) => {
                                                const status =
                                                    getBatchStatus(batch);
                                                return (
                                                    <div
                                                        key={batch.id}
                                                        className="flex items-center justify-between rounded-lg border p-3"
                                                    >
                                                        <div className="space-y-1">
                                                            <div className="flex items-center gap-2">
                                                                <span className="font-medium">
                                                                    {
                                                                        batch.batch_number
                                                                    }
                                                                </span>
                                                                <Badge
                                                                    className={
                                                                        status.className
                                                                    }
                                                                >
                                                                    {
                                                                        status.label
                                                                    }
                                                                </Badge>
                                                            </div>
                                                            <div className="flex items-center gap-1 text-sm text-muted-foreground">
                                                                <Building className="h-3 w-3" />
                                                                {
                                                                    batch
                                                                        .supplier
                                                                        .name
                                                                }
                                                            </div>
                                                            <div className="text-sm">
                                                                Stock:{' '}
                                                                {
                                                                    batch.quantity_remaining
                                                                }{' '}
                                                                {drug.unit_type}{' '}
                                                                • Expires:{' '}
                                                                {formatDate(
                                                                    batch.expiry_date,
                                                                )}
                                                            </div>
                                                        </div>
                                                        <div className="text-right">
                                                            <div className="font-medium">
                                                                $
                                                                {(
                                                                    batch.quantity_remaining *
                                                                    Number(
                                                                        batch.selling_price_per_unit,
                                                                    )
                                                                ).toLocaleString()}
                                                            </div>
                                                            <div className="text-sm text-muted-foreground">
                                                                $
                                                                {Number(
                                                                    batch.selling_price_per_unit,
                                                                ).toFixed(
                                                                    2,
                                                                )}{' '}
                                                                per{' '}
                                                                {drug.unit_type}
                                                            </div>
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        {drug.batches.length > 5 && (
                                            <div className="pt-2 text-center">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={`/pharmacy/drugs/${drug.id}/batches`}
                                                    >
                                                        <Eye className="mr-1 h-3 w-3" />
                                                        View All{' '}
                                                        {drug.batches.length}{' '}
                                                        Batches
                                                    </Link>
                                                </Button>
                                            </div>
                                        )}
                                    </div>
                                ) : (
                                    <div className="py-8 text-center">
                                        <Package className="mx-auto mb-3 h-12 w-12 text-muted-foreground opacity-50" />
                                        <h3 className="mb-1 font-medium">
                                            No Batches Available
                                        </h3>
                                        <p className="mb-3 text-sm text-muted-foreground">
                                            Add drug batches to start tracking
                                            inventory.
                                        </p>
                                        <Button size="sm" asChild>
                                            <Link
                                                href={`/pharmacy/drugs/${drug.id}/batches`}
                                            >
                                                <Plus className="mr-1 h-3 w-3" />
                                                Add First Batch
                                            </Link>
                                        </Button>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Statistics Sidebar */}
                    <div className="space-y-6">
                        {/* Stock Statistics */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <BarChart3 className="h-5 w-5" />
                                    Stock Statistics
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="text-center">
                                    <div className="text-3xl font-bold text-blue-600">
                                        {totalStock}
                                    </div>
                                    <div className="text-sm text-muted-foreground">
                                        Total Stock ({drug.unit_type})
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <div className="flex justify-between">
                                        <span className="text-sm">
                                            Minimum Level:
                                        </span>
                                        <span className="text-sm font-medium">
                                            {drug.minimum_stock_level}
                                        </span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-sm">
                                            Maximum Level:
                                        </span>
                                        <span className="text-sm font-medium">
                                            {drug.maximum_stock_level}
                                        </span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-sm">
                                            Active Batches:
                                        </span>
                                        <span className="text-sm font-medium">
                                            {activeBatches.length}
                                        </span>
                                    </div>
                                    {expiredBatches.length > 0 && (
                                        <div className="flex justify-between text-red-600">
                                            <span className="text-sm">
                                                Expired Batches:
                                            </span>
                                            <span className="text-sm font-medium">
                                                {expiredBatches.length}
                                            </span>
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Financial Information */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <DollarSign className="h-5 w-5" />
                                    Financial Information
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="text-center">
                                    <div className="text-2xl font-bold text-green-600">
                                        ${totalValue.toLocaleString()}
                                    </div>
                                    <div className="text-sm text-muted-foreground">
                                        Total Inventory Value
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <div className="flex justify-between">
                                        <span className="text-sm">
                                            Unit Price:
                                        </span>
                                        <span className="text-sm font-medium">
                                            $
                                            {drug.unit_price
                                                ? Number(
                                                      drug.unit_price,
                                                  ).toFixed(2)
                                                : '0.00'}
                                        </span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-sm">
                                            Avg. Cost:
                                        </span>
                                        <span className="text-sm font-medium">
                                            ${averageCost.toFixed(2)}
                                        </span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-sm">
                                            Profit Margin:
                                        </span>
                                        <span className="text-sm font-medium">
                                            {averageCost > 0 && drug.unit_price
                                                ? (
                                                      ((Number(
                                                          drug.unit_price,
                                                      ) -
                                                          averageCost) /
                                                          averageCost) *
                                                      100
                                                  ).toFixed(1)
                                                : '0'}
                                            %
                                        </span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Quick Actions */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Quick Actions</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                <Button className="w-full" size="sm" asChild>
                                    <Link
                                        href={`/pharmacy/drugs/${drug.id}/batches`}
                                    >
                                        <Plus className="mr-1 h-3 w-3" />
                                        Add New Batch
                                    </Link>
                                </Button>
                                <Button
                                    variant="outline"
                                    className="w-full"
                                    size="sm"
                                    asChild
                                >
                                    <Link
                                        href={`/pharmacy/drugs/${drug.id}/edit`}
                                    >
                                        <Edit className="mr-1 h-3 w-3" />
                                        Edit Drug Info
                                    </Link>
                                </Button>
                                <Button
                                    variant="outline"
                                    className="w-full"
                                    size="sm"
                                    asChild
                                >
                                    <Link href="/pharmacy/inventory">
                                        <BarChart3 className="mr-1 h-3 w-3" />
                                        View Inventory
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
