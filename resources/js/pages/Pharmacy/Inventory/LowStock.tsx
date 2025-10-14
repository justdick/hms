import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    Package,
    Plus,
    RefreshCw,
    ShoppingCart,
    TrendingDown,
} from 'lucide-react';
import { inventoryColumns, type InventoryDrug } from './inventory-columns';
import { DataTable } from './inventory-data-table';

interface Props {
    drugs: InventoryDrug[];
}

export default function LowStockInventory({ drugs }: Props) {
    const totalLowStock = drugs.length;
    const outOfStockDrugs = drugs.filter(
        (drug) => drug.total_stock === 0,
    ).length;
    const criticalStock = drugs.filter(
        (drug) =>
            drug.total_stock > 0 &&
            drug.total_stock <= drug.minimum_stock_level * 0.5,
    ).length;
    const totalValue = drugs.reduce(
        (sum, drug) => sum + drug.total_stock * (drug.unit_price || 0),
        0,
    );

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Pharmacy', href: '/pharmacy' },
                { title: 'Inventory', href: '/pharmacy/inventory' },
                { title: 'Low Stock', href: '/pharmacy/inventory/low-stock' },
            ]}
        >
            <Head title="Low Stock Drugs" />

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
                                <AlertTriangle className="h-6 w-6 text-orange-600" />
                                Low Stock Alert
                            </h1>
                            <p className="text-muted-foreground">
                                Drugs requiring immediate restocking attention
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
                            <Link href="/pharmacy/inventory/expiring">
                                <RefreshCw className="mr-1 h-4 w-4" />
                                Expiring Soon
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
                {totalLowStock > 0 && (
                    <div className="rounded-lg border border-orange-200 bg-orange-50 p-4 dark:border-orange-800 dark:bg-orange-950">
                        <div className="flex items-start gap-3">
                            <AlertTriangle className="mt-0.5 h-5 w-5 text-orange-600" />
                            <div>
                                <h3 className="font-medium text-orange-900 dark:text-orange-100">
                                    Immediate Action Required
                                </h3>
                                <p className="mt-1 text-sm text-orange-700 dark:text-orange-300">
                                    {totalLowStock} drug
                                    {totalLowStock !== 1 ? 's' : ''}{' '}
                                    {totalLowStock === 1 ? 'is' : 'are'} running
                                    low on stock.
                                    {outOfStockDrugs > 0 &&
                                        ` ${outOfStockDrugs} ${outOfStockDrugs === 1 ? 'is' : 'are'} completely out of stock.`}
                                    {criticalStock > 0 &&
                                        ` ${criticalStock} ${criticalStock === 1 ? 'is' : 'are'} at critical levels.`}
                                </p>
                            </div>
                        </div>
                    </div>
                )}

                {/* Low Stock Statistics */}
                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Total Low Stock
                            </CardTitle>
                            <AlertTriangle className="h-4 w-4 text-orange-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-orange-600">
                                {totalLowStock}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Drugs need restocking
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Out of Stock
                            </CardTitle>
                            <TrendingDown className="h-4 w-4 text-red-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-red-600">
                                {outOfStockDrugs}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Immediate priority
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Critical Level
                            </CardTitle>
                            <Package className="h-4 w-4 text-orange-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-orange-500">
                                {criticalStock}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Below 50% minimum
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Restock Value
                            </CardTitle>
                            <ShoppingCart className="h-4 w-4 text-blue-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-blue-600">
                                ${totalValue.toLocaleString()}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Current low stock value
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Priority Actions */}
                <Card className="border-orange-200 bg-gradient-to-r from-orange-50 to-red-50 dark:border-orange-800 dark:from-orange-950 dark:to-red-950">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-orange-900 dark:text-orange-100">
                            <ShoppingCart className="h-5 w-5" />
                            Recommended Actions
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-3 md:grid-cols-3">
                            <div className="flex items-center gap-2 text-sm">
                                <Badge className="bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300">
                                    Priority 1
                                </Badge>
                                <span>
                                    Restock {outOfStockDrugs} out-of-stock items
                                </span>
                            </div>
                            <div className="flex items-center gap-2 text-sm">
                                <Badge className="bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300">
                                    Priority 2
                                </Badge>
                                <span>
                                    Order {criticalStock} critical-level drugs
                                </span>
                            </div>
                            <div className="flex items-center gap-2 text-sm">
                                <Badge className="bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300">
                                    Priority 3
                                </Badge>
                                <span>
                                    Review{' '}
                                    {totalLowStock -
                                        outOfStockDrugs -
                                        criticalStock}{' '}
                                    low-stock items
                                </span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Low Stock DataTable */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <AlertTriangle className="h-5 w-5 text-orange-600" />
                            Low Stock Drugs ({totalLowStock})
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {drugs.length > 0 ? (
                            <DataTable
                                columns={inventoryColumns}
                                data={drugs}
                            />
                        ) : (
                            <div className="py-12 text-center">
                                <Package className="mx-auto mb-4 h-16 w-16 text-green-600 opacity-50" />
                                <h3 className="mb-2 text-lg font-medium text-green-800">
                                    All Drugs Well Stocked!
                                </h3>
                                <p className="mb-4 text-muted-foreground">
                                    No drugs are currently running low on stock.
                                    Great job maintaining inventory levels!
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
