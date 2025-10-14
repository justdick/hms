'use client';

import { Link } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import {
    AlertTriangle,
    ArrowUpDown,
    Building,
    Calendar,
    Eye,
    Package,
} from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

interface Drug {
    id: number;
    name: string;
    form: string;
    unit_type: string;
}

interface Supplier {
    id: number;
    name: string;
}

export interface ExpiringBatch {
    id: number;
    drug: Drug;
    supplier: Supplier;
    batch_number: string;
    expiry_date: string;
    manufacture_date?: string;
    quantity_remaining: number;
    selling_price_per_unit: number;
    received_date: string;
}

const getExpiryStatusConfig = (expiryDate: string) => {
    const expiry = new Date(expiryDate);
    const now = new Date();
    const daysUntilExpiry = Math.ceil(
        (expiry.getTime() - now.getTime()) / (1000 * 60 * 60 * 24),
    );

    if (daysUntilExpiry < 0) {
        return {
            label: 'Expired',
            className:
                'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
            priority: 'urgent',
        };
    }
    if (daysUntilExpiry <= 7) {
        return {
            label: 'Expires Soon',
            className:
                'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300',
            priority: 'high',
        };
    }
    if (daysUntilExpiry <= 30) {
        return {
            label: 'Expiring',
            className:
                'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
            priority: 'medium',
        };
    }
    return {
        label: 'Stable',
        className:
            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        priority: 'low',
    };
};

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

    if (daysUntilExpiry < 0) {
        return `${Math.abs(daysUntilExpiry)} days ago`;
    }
    if (daysUntilExpiry === 0) {
        return 'Today';
    }
    if (daysUntilExpiry === 1) {
        return 'Tomorrow';
    }
    return `${daysUntilExpiry} days`;
};

export const expiringColumns: ColumnDef<ExpiringBatch>[] = [
    {
        accessorKey: 'drug.name',
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                >
                    Drug
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            const batch = row.original;
            return (
                <div className="space-y-1">
                    <div className="flex items-center gap-2 font-medium">
                        <Package className="h-4 w-4 text-blue-600" />
                        {batch.drug.name}
                    </div>
                    <div className="text-sm text-muted-foreground">
                        {batch.drug.form}
                    </div>
                </div>
            );
        },
    },
    {
        accessorKey: 'batch_number',
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                >
                    Batch Details
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            const batch = row.original;
            return (
                <div className="space-y-1">
                    <div className="font-medium">{batch.batch_number}</div>
                    <div className="flex items-center gap-1 text-sm text-muted-foreground">
                        <Building className="h-3 w-3" />
                        {batch.supplier.name}
                    </div>
                </div>
            );
        },
    },
    {
        id: 'quantity_remaining',
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                >
                    Remaining Stock
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            const batch = row.original;
            return (
                <div className="space-y-1">
                    <div className="text-lg font-medium">
                        {batch.quantity_remaining}
                    </div>
                    <div className="text-sm text-muted-foreground">
                        {batch.drug.unit_type}
                    </div>
                </div>
            );
        },
        sortingFn: (rowA, rowB) => {
            return (
                rowA.original.quantity_remaining -
                rowB.original.quantity_remaining
            );
        },
    },
    {
        accessorKey: 'expiry_date',
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                >
                    Expiry Date
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            const expiryDate = row.getValue('expiry_date') as string;
            const config = getExpiryStatusConfig(expiryDate);
            const daysUntilExpiry = getDaysUntilExpiry(expiryDate);

            return (
                <div className="space-y-2">
                    <div className="flex items-center gap-1 text-sm">
                        <Calendar className="h-3 w-3" />
                        {formatDate(expiryDate)}
                    </div>
                    <div className="flex items-center gap-2">
                        <Badge className={config.className}>
                            {config.label}
                        </Badge>
                        {config.priority === 'urgent' && (
                            <AlertTriangle className="h-3 w-3 text-red-600" />
                        )}
                    </div>
                    <div className="text-xs text-muted-foreground">
                        {daysUntilExpiry}
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
                    Value at Risk
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            const batch = row.original;
            const totalValue =
                batch.quantity_remaining * batch.selling_price_per_unit;

            return (
                <div className="space-y-1">
                    <div className="font-medium">
                        ${totalValue.toLocaleString()}
                    </div>
                    <div className="text-xs text-muted-foreground">
                        ${Number(batch.selling_price_per_unit).toFixed(2)} per{' '}
                        {batch.drug.unit_type}
                    </div>
                </div>
            );
        },
        sortingFn: (rowA, rowB) => {
            const valueA =
                rowA.original.quantity_remaining *
                rowA.original.selling_price_per_unit;
            const valueB =
                rowB.original.quantity_remaining *
                rowB.original.selling_price_per_unit;
            return valueA - valueB;
        },
    },
    {
        accessorKey: 'received_date',
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                >
                    Received
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            const receivedDate = row.getValue('received_date') as string;
            return (
                <div className="text-sm text-muted-foreground">
                    {formatDate(receivedDate)}
                </div>
            );
        },
    },
    {
        id: 'actions',
        enableHiding: false,
        cell: ({ row }) => {
            const batch = row.original;

            return (
                <Button size="sm" variant="ghost" asChild>
                    <Link href={`/pharmacy/drugs/${batch.drug.id}`}>
                        <Eye className="mr-1 h-3 w-3" />
                        View Drug
                    </Link>
                </Button>
            );
        },
    },
];
