import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { StatCard } from '@/components/ui/stat-card';
import AppLayout from '@/layouts/app-layout';
import { formatCurrency } from '@/lib/utils';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    BarChart3,
    Calendar,
    ChevronDown,
    Download,
    Package,
    Plus,
    TrendingDown,
    Upload,
} from 'lucide-react';
import { useState } from 'react';
import { inventoryColumns, type InventoryDrug } from './inventory-columns';
import { DataTable } from './inventory-data-table';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedDrugs {
    data: InventoryDrug[];
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
    links: PaginationLink[];
}

interface Stats {
    total: number;
    low_stock: number;
    out_of_stock: number;
    total_value: number;
}

interface Filters {
    search?: string;
    category?: string;
    stock_status?: string;
}

interface Props {
    drugs: PaginatedDrugs;
    categories: string[];
    stats: Stats;
    filters: Filters;
}

export default function InventoryIndex({
    drugs,
    categories,
    stats,
    filters,
}: Props) {
    const [importModalOpen, setImportModalOpen] = useState(false);
    const [inventoryImportModalOpen, setInventoryImportModalOpen] = useState(false);
    const { data, setData, post, processing, reset } = useForm<{
        file: File | null;
    }>({
        file: null,
    });

    const handleImport = (e: React.FormEvent) => {
        e.preventDefault();
        if (!data.file) return;

        post('/pharmacy/drugs-import', {
            forceFormData: true,
            onSuccess: () => {
                setImportModalOpen(false);
                reset();
            },
        });
    };

    const handleInventoryImport = (e: React.FormEvent) => {
        e.preventDefault();
        if (!data.file) return;

        post('/pharmacy/inventory/import', {
            forceFormData: true,
            onSuccess: () => {
                setInventoryImportModalOpen(false);
                reset();
            },
        });
    };

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
                                Low Stock ({stats.low_stock})
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/pharmacy/inventory/expiring">
                                <Calendar className="mr-1 h-4 w-4" />
                                Expiring Soon
                            </Link>
                        </Button>
                        <Dialog
                            open={importModalOpen}
                            onOpenChange={setImportModalOpen}
                        >
                            <DialogTrigger asChild>
                                <Button variant="outline">
                                    <Upload className="mr-1 h-4 w-4" />
                                    Import Drugs
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>Import Drugs</DialogTitle>
                                    <DialogDescription>
                                        Upload a CSV or Excel file to bulk
                                        import drugs. Download the template for
                                        the correct format.
                                    </DialogDescription>
                                </DialogHeader>
                                <form onSubmit={handleImport}>
                                    <div className="space-y-4 py-4">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="w-full"
                                            asChild
                                        >
                                            <a href="/pharmacy/drugs-import/template">
                                                <Download className="mr-2 h-4 w-4" />
                                                Download Template
                                            </a>
                                        </Button>
                                        <div className="space-y-2">
                                            <Label htmlFor="file">
                                                Select File
                                            </Label>
                                            <Input
                                                id="file"
                                                type="file"
                                                accept=".csv,.xlsx,.xls"
                                                onChange={(e) =>
                                                    setData(
                                                        'file',
                                                        e.target.files?.[0] ||
                                                            null,
                                                    )
                                                }
                                            />
                                            <p className="text-xs text-muted-foreground">
                                                Supported formats: CSV, Excel
                                                (.xlsx, .xls)
                                            </p>
                                        </div>
                                    </div>
                                    <DialogFooter>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                setImportModalOpen(false)
                                            }
                                        >
                                            Cancel
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={!data.file || processing}
                                        >
                                            {processing
                                                ? 'Importing...'
                                                : 'Import'}
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>
                        {/* Inventory Export/Import Dropdown */}
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline">
                                    <Package className="mr-1 h-4 w-4" />
                                    Stock Batches
                                    <ChevronDown className="ml-1 h-4 w-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem asChild>
                                    <a href="/pharmacy/inventory/export">
                                        <Download className="mr-2 h-4 w-4" />
                                        Export Stock (Backup)
                                    </a>
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    onClick={() => setInventoryImportModalOpen(true)}
                                >
                                    <Upload className="mr-2 h-4 w-4" />
                                    Import Stock (Restore)
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        {/* Inventory Import Dialog */}
                        <Dialog
                            open={inventoryImportModalOpen}
                            onOpenChange={setInventoryImportModalOpen}
                        >
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>Import Stock Batches</DialogTitle>
                                    <DialogDescription>
                                        Restore inventory from a previously exported file.
                                        Drugs must exist in the system before importing batches.
                                    </DialogDescription>
                                </DialogHeader>
                                <form onSubmit={handleInventoryImport}>
                                    <div className="space-y-4 py-4">
                                        <div className="rounded-md bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
                                            <strong>Note:</strong> Export your current stock before migration,
                                            then import after running migrate:fresh to restore stock levels.
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="inventory-file">
                                                Select Exported File
                                            </Label>
                                            <Input
                                                id="inventory-file"
                                                type="file"
                                                accept=".csv,.xlsx,.xls"
                                                onChange={(e) =>
                                                    setData(
                                                        'file',
                                                        e.target.files?.[0] || null,
                                                    )
                                                }
                                            />
                                            <p className="text-xs text-muted-foreground">
                                                Use the file from "Export Stock (Backup)"
                                            </p>
                                        </div>
                                    </div>
                                    <DialogFooter>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => setInventoryImportModalOpen(false)}
                                        >
                                            Cancel
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={!data.file || processing}
                                        >
                                            {processing ? 'Importing...' : 'Import Stock'}
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>
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
                    <StatCard
                        label="Total Drugs"
                        value={stats.total}
                        icon={<Package className="h-4 w-4" />}
                        variant="info"
                    />
                    <StatCard
                        label="Low Stock"
                        value={stats.low_stock}
                        icon={<AlertTriangle className="h-4 w-4" />}
                        variant="warning"
                    />
                    <StatCard
                        label="Out of Stock"
                        value={stats.out_of_stock}
                        icon={<TrendingDown className="h-4 w-4" />}
                        variant="error"
                    />
                    <StatCard
                        label="Total Value"
                        value={formatCurrency(stats.total_value)}
                        icon={<BarChart3 className="h-4 w-4" />}
                        variant="success"
                    />
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
                            Inventory Overview ({stats.total} drugs)
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {drugs.data.length > 0 ||
                        filters.search ||
                        filters.category ||
                        filters.stock_status ? (
                            <DataTable
                                columns={inventoryColumns}
                                data={drugs.data}
                                pagination={drugs}
                                categories={categories}
                                filters={filters}
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
