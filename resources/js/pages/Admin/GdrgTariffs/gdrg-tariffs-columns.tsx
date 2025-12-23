'use client';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ColumnDef } from '@tanstack/react-table';
import { ArrowUpDown, Edit, Trash2 } from 'lucide-react';

export interface GdrgTariffData {
    id: number;
    code: string;
    name: string;
    mdc_category: string;
    tariff_price: number;
    age_category: string;
    is_active: boolean;
    formatted_price: string;
    display_name: string;
    created_at: string;
    updated_at: string;
}

const ageCategoryLabels: Record<string, string> = {
    adult: 'Adult',
    child: 'Child',
    all: 'All Ages',
};

const ageCategoryStyles: Record<string, string> = {
    adult: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
    child: 'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-300',
    all: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300',
};

export const createGdrgTariffsColumns = (
    onEdit: (tariff: GdrgTariffData) => void,
    onDelete: (tariff: GdrgTariffData) => void,
): ColumnDef<GdrgTariffData>[] => [
    {
        accessorKey: 'code',
        id: 'code',
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                >
                    Code
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            return (
                <div className="font-mono text-sm font-medium">
                    {row.original.code}
                </div>
            );
        },
    },
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
                    Name
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            return (
                <div className="max-w-[300px] truncate">
                    {row.original.name}
                </div>
            );
        },
    },
    {
        accessorKey: 'mdc_category',
        id: 'mdc_category',
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                >
                    MDC Category
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            return (
                <Badge variant="outline">
                    {row.original.mdc_category || '-'}
                </Badge>
            );
        },
        filterFn: (row, id, value) => {
            return value.includes(row.getValue(id));
        },
    },
    {
        accessorKey: 'formatted_price',
        id: 'tariff_price',
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                    className="w-full justify-end"
                >
                    Tariff Price
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            return (
                <div className="text-right font-medium">
                    {row.original.formatted_price}
                </div>
            );
        },
    },
    {
        accessorKey: 'age_category',
        id: 'age_category',
        header: 'Age Category',
        cell: ({ row }) => {
            const ageCategory = row.original.age_category;
            return (
                <Badge
                    className={
                        ageCategoryStyles[ageCategory] || ageCategoryStyles.all
                    }
                >
                    {ageCategoryLabels[ageCategory] || ageCategory}
                </Badge>
            );
        },
        filterFn: (row, id, value) => {
            return value.includes(row.getValue(id));
        },
    },
    {
        accessorKey: 'is_active',
        id: 'status',
        header: 'Status',
        cell: ({ row }) => {
            const isActive = row.original.is_active;
            return (
                <Badge variant={isActive ? 'default' : 'secondary'}>
                    {isActive ? 'Active' : 'Inactive'}
                </Badge>
            );
        },
        filterFn: (row, id, value) => {
            return value === row.original.is_active;
        },
    },
    {
        id: 'actions',
        enableHiding: false,
        cell: ({ row }) => {
            const tariff = row.original;

            return (
                <div className="flex items-center justify-end gap-1">
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
                        className="text-red-600 hover:bg-red-50 hover:text-red-700"
                    >
                        <Trash2 className="h-4 w-4" />
                    </Button>
                </div>
            );
        },
    },
];
