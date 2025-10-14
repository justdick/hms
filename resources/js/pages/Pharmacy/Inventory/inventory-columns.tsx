'use client';

import { Link } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import {
    AlertTriangle,
    ArrowUpDown,
    Calendar,
    Edit,
    Eye,
    Package,
} from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

export interface InventoryDrug {
    id: number;
    name: string;
    category: string;
    form: string;
    unit_type: string;
    total_stock: number;
    minimum_stock_level: number;
    is_low_stock: boolean;
    batches_count: number;
    next_expiry?: string;
    unit_price?: number;
}

const getStockStatusConfig = (drug: InventoryDrug) => {
    if (drug.total_stock === 0) {
        return {
            label: 'Out of Stock',
            className:
                'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
        };
    }
    if (drug.is_low_stock) {
        return {
            label: 'Low Stock',
            className:
                'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300',
        };
    }
    return {
        label: 'In Stock',
        className:
            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    };
};

const formatDate = (dateString?: string) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString();
};

const isExpiringSoon = (dateString?: string) => {
    if (!dateString) return false;
    const expiryDate = new Date(dateString);
    const now = new Date();
    const daysUntilExpiry = Math.ceil(
        (expiryDate.getTime() - now.getTime()) / (1000 * 60 * 60 * 24),
    );
    return daysUntilExpiry <= 30 && daysUntilExpiry >= 0;
};

export const inventoryColumns: ColumnDef<InventoryDrug>[] = [
    {
        accessorKey: 'name',
        id: 'name',
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                >
                    Drug Name
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            const drug = row.original;
            return (
                <div className="space-y-1">
                    <div className="flex items-center gap-2 font-medium">
                        <Package className="h-4 w-4 text-blue-600" />
                        {drug.name}
                    </div>
                    <div className="text-sm text-muted-foreground">
                        {drug.category} • {drug.form}
                    </div>
                </div>
            );
        },
        filterFn: (row, id, value) => {
            const drugName = row.original.name.toLowerCase();
            const category = row.original.category.toLowerCase();
            const form = row.original.form.toLowerCase();
            const searchTerm = value.toLowerCase();
            return (
                drugName.includes(searchTerm) ||
                category.includes(searchTerm) ||
                form.includes(searchTerm)
            );
        },
    },
    {
        accessorKey: 'category',
        id: 'category',
        header: 'Category',
        cell: ({ row }) => {
            return <span className="text-sm">{row.original.category}</span>;
        },
        filterFn: (row, id, value) => {
            return value.includes(row.getValue(id));
        },
    },
    {
        id: 'stock_level',
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                >
                    Stock Level
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            const drug = row.original;
            const stockConfig = getStockStatusConfig(drug);

            return (
                <div className="space-y-2">
                    <div className="flex items-center gap-2">
                        <span className="text-lg font-medium">
                            {drug.total_stock}
                        </span>
                        <span className="text-sm text-muted-foreground">
                            {drug.unit_type}
                        </span>
                    </div>
                    <div className="flex items-center gap-2">
                        <Badge className={stockConfig.className}>
                            {stockConfig.label}
                        </Badge>
                        {drug.is_low_stock && (
                            <AlertTriangle className="h-3 w-3 text-orange-600" />
                        )}
                    </div>
                    <div className="text-xs text-muted-foreground">
                        Min: {drug.minimum_stock_level} {drug.unit_type}
                    </div>
                </div>
            );
        },
        sortingFn: (rowA, rowB) => {
            return rowA.original.total_stock - rowB.original.total_stock;
        },
    },
    {
        id: 'stock_status',
        accessorFn: (row) => {
            if (row.total_stock === 0) return 'out_of_stock';
            if (row.is_low_stock) return 'low_stock';
            return 'in_stock';
        },
        header: 'Stock Status',
        cell: ({ row }) => {
            const stockConfig = getStockStatusConfig(row.original);
            return (
                <Badge className={stockConfig.className}>
                    {stockConfig.label}
                </Badge>
            );
        },
        filterFn: (row, id, value) => {
            return value.includes(row.getValue(id));
        },
    },
    {
        id: 'batches_info',
        header: 'Batch Information',
        cell: ({ row }) => {
            const drug = row.original;
            const isExpiring = isExpiringSoon(drug.next_expiry);

            return (
                <div className="space-y-1">
                    <div className="flex items-center gap-2">
                        <span className="text-sm font-medium">
                            {drug.batches_count} batch
                            {drug.batches_count !== 1 ? 'es' : ''}
                        </span>
                    </div>
                    {drug.next_expiry && (
                        <div
                            className={`flex items-center gap-1 text-sm ${
                                isExpiring
                                    ? 'text-orange-600'
                                    : 'text-muted-foreground'
                            }`}
                        >
                            <Calendar className="h-3 w-3" />
                            Next expiry: {formatDate(drug.next_expiry)}
                            {isExpiring && (
                                <AlertTriangle className="h-3 w-3 text-orange-600" />
                            )}
                        </div>
                    )}
                </div>
            );
        },
    },
    {
        accessorKey: 'unit_price',
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                >
                    Unit Price
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            const price = row.original.unit_price;
            if (!price)
                return <span className="text-muted-foreground">N/A</span>;

            return (
                <div className="space-y-1">
                    <div className="font-medium">
                        ${Number(price).toFixed(2)}
                    </div>
                    <div className="text-xs text-muted-foreground">
                        per {row.original.unit_type}
                    </div>
                </div>
            );
        },
    },
    {
        id: 'value',
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                >
                    Total Value
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            const drug = row.original;
            const totalValue =
                drug.total_stock * (Number(drug.unit_price) || 0);

            return (
                <div className="font-medium">
                    ${totalValue.toLocaleString()}
                </div>
            );
        },
        sortingFn: (rowA, rowB) => {
            const valueA =
                rowA.original.total_stock *
                (Number(rowA.original.unit_price) || 0);
            const valueB =
                rowB.original.total_stock *
                (Number(rowB.original.unit_price) || 0);
            return valueA - valueB;
        },
    },
    {
        id: 'actions',
        enableHiding: false,
        cell: ({ row }) => {
            const drug = row.original;

            return (
                <div className="flex items-center gap-1">
                    <Button size="sm" variant="ghost" asChild>
                        <Link href={`/pharmacy/drugs/${drug.id}`}>
                            <Eye className="mr-1 h-3 w-3" />
                            View
                        </Link>
                    </Button>
                    <Button size="sm" variant="ghost" asChild>
                        <Link href={`/pharmacy/drugs/${drug.id}/edit`}>
                            <Edit className="mr-1 h-3 w-3" />
                            Edit
                        </Link>
                    </Button>
                    <Button size="sm" variant="ghost" asChild>
                        <Link href={`/pharmacy/drugs/${drug.id}/batches`}>
                            <Package className="mr-1 h-3 w-3" />
                            Batches
                        </Link>
                    </Button>
                </div>
            );
        },
    },
];
