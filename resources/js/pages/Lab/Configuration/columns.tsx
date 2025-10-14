'use client';

import { Link } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { ArrowUpDown, Edit, Wrench } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { AlertCircle, CheckCircle2 } from 'lucide-react';

export type LabService = {
    id: number;
    name: string;
    code: string;
    category: string;
    price?: number | string;
    description?: string;
    preparation_instructions?: string;
    sample_type?: string;
    turnaround_time?: string;
    normal_range?: string;
    clinical_significance?: string;
    is_active?: boolean;
    test_parameters?: any;
};

const hasParameters = (service: LabService): boolean => {
    return (
        service.test_parameters &&
        service.test_parameters.parameters &&
        service.test_parameters.parameters.length > 0
    );
};

export const columns = (
    onEdit: (service: LabService) => void,
): ColumnDef<LabService>[] => [
    {
        accessorKey: 'name',
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
        cell: ({ row }) => (
            <div className="font-medium">{row.getValue('name')}</div>
        ),
    },
    {
        accessorKey: 'code',
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
        cell: ({ row }) => (
            <code className="rounded bg-muted px-2 py-1 font-mono text-xs">
                {row.getValue('code')}
            </code>
        ),
    },
    {
        accessorKey: 'category',
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                >
                    Category
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => (
            <Badge variant="outline" className="text-xs">
                {row.getValue('category')}
            </Badge>
        ),
        filterFn: (row, id, value) => {
            return value.includes(row.getValue(id));
        },
    },
    {
        accessorKey: 'price',
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                >
                    Price
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            const price = row.getValue('price') as number | string;
            const formatted =
                typeof price === 'number'
                    ? price.toFixed(2)
                    : parseFloat((price as string) || '0').toFixed(2);

            return <div className="font-medium">${formatted}</div>;
        },
    },
    {
        id: 'status',
        header: 'Status',
        cell: ({ row }) => {
            const service = row.original;
            const configured = hasParameters(service);

            return configured ? (
                <Badge className="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                    <CheckCircle2 className="mr-1 h-3 w-3" />
                    Configured
                </Badge>
            ) : (
                <Badge
                    variant="outline"
                    className="bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300"
                >
                    <AlertCircle className="mr-1 h-3 w-3" />
                    Setup Needed
                </Badge>
            );
        },
        filterFn: (row, id, value) => {
            const service = row.original;
            const configured = hasParameters(service);
            const status = configured ? 'configured' : 'pending';
            return value.includes(status);
        },
    },
    {
        id: 'parameters',
        header: 'Parameters',
        cell: ({ row }) => {
            const service = row.original;
            return (
                <div className="text-sm text-muted-foreground">
                    {hasParameters(service)
                        ? `${service.test_parameters.parameters.length} configured`
                        : 'None'}
                </div>
            );
        },
    },
    {
        id: 'actions',
        enableHiding: false,
        cell: ({ row }) => {
            const service = row.original;

            return (
                <div className="flex items-center gap-2">
                    <Button
                        size="sm"
                        variant="ghost"
                        onClick={() => onEdit(service)}
                    >
                        <Edit className="mr-1 h-3 w-3" />
                        Edit
                    </Button>
                    <Button size="sm" variant="outline" asChild>
                        <Link
                            href={`/lab/services/configuration/${service.id}`}
                        >
                            <Wrench className="mr-1 h-3 w-3" />
                            Parameters
                        </Link>
                    </Button>
                </div>
            );
        },
    },
];
