import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    BarChart3,
    Calendar,
    Package,
    Plus,
    TrendingDown,
} from 'lucide-react';
import { inventoryColumns, type InventoryDrug } from './inventory-columns';
import { DataTable } from './inventory-data-table';

interface Props {
    drugs: InventoryDrug[];
}

export default function InventoryIndex({ drugs }: Props) {
    const totalDrugs = drugs.length;
    const lowStockDrugs = drugs.filter((drug) => drug.is_low_stock).length;
    const outOfStockDrugs = drugs.filter(
        (drug) => drug.total_stock === 0,
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
            ]}
        >
            <Head title="Inventory Management" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="ghost" size="sm" asChild>
                            <Link href="/pharmacy">
                                <ArrowLeft className="mr-1 h-4 w-4" />
                                Back to Dashboard
                            </Link>
                        </Button>
                        <div>
                            <h1 className="flex items-center gap-2 text-2xl font-bold">
                                <Package className="h-6 w-6" />
                                Inventory Management
                            </h1>
                            <p className="text-muted-foreground">
                                Monitor drug stock levels and manage inventory
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/pharmacy/inventory/low-stock">
                                <AlertTriangle className="mr-1 h-4 w-4" />
                                Low Stock ({lowStockDrugs})
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/pharmacy/inventory/expiring">
                                <Calendar className="mr-1 h-4 w-4" />
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

                {/* Inventory Statistics */}
                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Total Drugs
                            </CardTitle>
                            <Package className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-blue-600">
                                {totalDrugs}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Active medications
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Low Stock
                            </CardTitle>
                            <AlertTriangle className="h-4 w-4 text-orange-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-orange-600">
                                {lowStockDrugs}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Require restocking
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
                                Immediate attention needed
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Total Value
                            </CardTitle>
                            <BarChart3 className="h-4 w-4 text-green-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600">
                                ${totalValue.toLocaleString()}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Current inventory value
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Quick Actions */}
                <div className="flex flex-wrap gap-2">
                    <Button variant="outline" size="sm" asChild>
                        <Link href="/pharmacy/inventory?filter=low_stock">
                            <AlertTriangle className="mr-1 h-3 w-3" />
                            Filter Low Stock
                        </Link>
                    </Button>
                    <Button variant="outline" size="sm" asChild>
                        <Link href="/pharmacy/inventory?filter=out_of_stock">
                            <TrendingDown className="mr-1 h-3 w-3" />
                            Filter Out of Stock
                        </Link>
                    </Button>
                    <Button variant="outline" size="sm" asChild>
                        <Link href="/pharmacy/inventory?sort=expiry">
                            <Calendar className="mr-1 h-3 w-3" />
                            Sort by Expiry
                        </Link>
                    </Button>
                </div>

                {/* Inventory DataTable */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Package className="h-5 w-5" />
                            Inventory Overview ({totalDrugs} drugs)
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
                                <Package className="mx-auto mb-4 h-16 w-16 text-muted-foreground opacity-50" />
                                <h3 className="mb-2 text-lg font-medium">
                                    No Drugs in Inventory
                                </h3>
                                <p className="mb-4 text-muted-foreground">
                                    Start by adding drugs to your pharmacy
                                    inventory.
                                </p>
                                <Button asChild>
                                    <Link href="/pharmacy/drugs/create">
                                        <Plus className="mr-1 h-4 w-4" />
                                        Add First Drug
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
