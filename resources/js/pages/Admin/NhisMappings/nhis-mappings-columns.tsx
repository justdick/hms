'use client';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ColumnDef } from '@tanstack/react-table';
import {
    ArrowUpDown,
    FlaskConical,
    Pill,
    Stethoscope,
    Trash2,
} from 'lucide-react';

export interface NhisTariff {
    id: number;
    nhis_code: string;
    name: string;
    category: string;
    price: number;
    formatted_price: string;
    display_name: string;
}

export interface UnifiedTariff {
    id: number;
    code: string;
    name: string;
    category: string;
    price: number;
    formatted_price: string;
    source: 'nhis' | 'gdrg';
}

export interface NhisMappingData {
    id: number;
    item_type: string;
    item_id: number;
    item_code: string;
    nhis_tariff_id: number | null;
    gdrg_tariff_id: number | null;
    item_type_label: string;
    nhis_tariff?: NhisTariff;
    tariff?: UnifiedTariff;
    created_at: string;
}

const itemTypeConfig: Record<
    string,
    { label: string; icon: React.ReactNode; className: string }
> = {
    drug: {
        label: 'Drug',
        icon: <Pill className="h-3 w-3" />,
        className:
            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    },
    lab_service: {
        label: 'Lab Service',
        icon: <FlaskConical className="h-3 w-3" />,
        className:
            'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
    },
    procedure: {
        label: 'Procedure',
        icon: <Stethoscope className="h-3 w-3" />,
        className:
            'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300',
    },
    consumable: {
        label: 'Consumable',
        icon: <Pill className="h-3 w-3" />,
        className:
            'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
    },
};

export const createNhisMappingsColumns = (
    onDelete: (mapping: NhisMappingData) => void,
): ColumnDef<NhisMappingData>[] => [
    {
        accessorKey: 'item_type',
        id: 'item_type',
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                >
                    Item Type
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            const itemType = row.original.item_type;
            const config = itemTypeConfig[itemType] || {
                label: itemType,
                icon: null,
                className:
                    'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300',
            };

            return (
                <Badge className={`gap-1 ${config.className}`}>
                    {config.icon}
                    {config.label}
                </Badge>
            );
        },
        filterFn: (row, id, value) => {
            return value.includes(row.getValue(id));
        },
    },
    {
        accessorKey: 'item_code',
        id: 'item_code',
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                >
                    Item Code
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            return (
                <div className="font-mono text-sm font-medium">
                    {row.original.item_code}
                </div>
            );
        },
    },
    {
        accessorKey: 'tariff.code',
        id: 'nhis_code',
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                >
                    NHIS Code
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            return (
                <div className="font-mono text-sm">
                    {row.original.tariff?.code ||
                        row.original.nhis_tariff?.nhis_code ||
                        '-'}
                </div>
            );
        },
    },
    {
        accessorKey: 'tariff.name',
        id: 'nhis_name',
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                >
                    NHIS Tariff Name
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            return (
                <div className="max-w-[300px] truncate">
                    {row.original.tariff?.name ||
                        row.original.nhis_tariff?.name ||
                        '-'}
                </div>
            );
        },
    },
    {
        accessorKey: 'tariff.formatted_price',
        id: 'nhis_price',
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                    className="w-full justify-end"
                >
                    NHIS Price
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            return (
                <div className="text-right font-medium">
                    {row.original.tariff?.formatted_price ||
                        row.original.nhis_tariff?.formatted_price ||
                        '-'}
                </div>
            );
        },
    },
    {
        id: 'actions',
        enableHiding: false,
        cell: ({ row }) => {
            const mapping = row.original;

            return (
                <div className="flex items-center justify-end gap-1">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => onDelete(mapping)}
                        className="text-red-600 hover:bg-red-50 hover:text-red-700"
                    >
                        <Trash2 className="h-4 w-4" />
                    </Button>
                </div>
            );
        },
    },
];
