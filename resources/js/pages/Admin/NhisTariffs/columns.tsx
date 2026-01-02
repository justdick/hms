'use client';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ColumnDef } from '@tanstack/react-table';
import { ArrowUpDown, Edit, Trash2 } from 'lucide-react';

export interface NhisTariff {
    id: number;
    nhis_code: string;
    name: string;
    category: string;
    price: number;
    unit: string | null;
    is_active: boolean;
    formatted_price: string;
    display_name: string;
    created_at: string;
    updated_at: string;
}

const categoryLabels: Record<string, string> = {
    medicine: 'Medicine',
    lab: 'Laboratory',
    procedure: 'Procedure',
    consultation: 'Consultation',
    consumable: 'Consumable',
};

export const columns = (
    onEdit: (tariff: NhisTariff) => void,
    onDelete: (tariff: NhisTariff) => void,
): ColumnDef<NhisTariff>[] => [
    {
        accessorKey: 'nhis_code',
        header: ({ column }) => (
            <Button
                variant="ghost"
                onClick={() =>
                    column.toggleSorting(column.getIsSorted() === 'asc')
                }
            >
                NHIS Code
                <ArrowUpDown className="ml-2 h-4 w-4" />
            </Button>
        ),
        cell: ({ row }) => (
            <span className="font-mono font-medium">
                {row.getValue('nhis_code')}
            </span>
        ),
    },
    {
        accessorKey: 'name',
        header: ({ column }) => (
            <Button
                variant="ghost"
                onClick={() =>
                    column.toggleSorting(column.getIsSorted() === 'asc')
                }
            >
                Name
                <ArrowUpDown className="ml-2 h-4 w-4" />
            </Button>
        ),
        cell: ({ row }) => (
            <span className="max-w-[300px] truncate">
                {row.getValue('name')}
            </span>
        ),
    },
    {
        accessorKey: 'category',
        header: 'Category',
        cell: ({ row }) => {
            const category = row.getValue('category') as string;
            return (
                <Badge variant="outline">
                    {categoryLabels[category] || category}
                </Badge>
            );
        },
        filterFn: (row, id, value) => {
            return value === 'all' || row.getValue(id) === value;
        },
    },
    {
        accessorKey: 'price',
        header: ({ column }) => (
            <div className="text-right">
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                >
                    Price
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            </div>
        ),
        cell: ({ row }) => (
            <div className="text-right font-medium">
                {row.original.formatted_price}
            </div>
        ),
    },
    {
        accessorKey: 'unit',
        header: 'Unit',
        cell: ({ row }) => row.getValue('unit') || '-',
    },
    {
        accessorKey: 'is_active',
        header: 'Status',
        cell: ({ row }) => {
            const isActive = row.getValue('is_active') as boolean;
            return (
                <Badge variant={isActive ? 'default' : 'secondary'}>
                    {isActive ? 'Active' : 'Inactive'}
                </Badge>
            );
        },
        filterFn: (row, id, value) => {
            if (value === 'all') return true;
            if (value === 'active') return row.getValue(id) === true;
            if (value === 'inactive') return row.getValue(id) === false;
            return true;
        },
    },
    {
        id: 'actions',
        header: () => <div className="text-right">Actions</div>,
        cell: ({ row }) => {
            const tariff = row.original;
            return (
                <div className="flex items-center justify-end gap-2">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => onEdit(tariff)}
                    >
                        <Edit className="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => onDelete(tariff)}
                        className="text-red-600 hover:text-red-700"
                    >
                        <Trash2 className="h-4 w-4" />
                    </Button>
                </div>
            );
        },
    },
];
