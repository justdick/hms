'use client';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ColumnDef } from '@tanstack/react-table';
import { ArrowUpDown, Calendar, Eye } from 'lucide-react';
import { Link } from '@inertiajs/react';

export interface ClaimBatch {
    id: number;
    batch_number: string;
    name: string;
    submission_period: string;
    submission_period_formatted: string;
    status: 'draft' | 'finalized' | 'submitted' | 'processing' | 'completed';
    status_label: string;
    total_claims: number;
    total_amount: string;
    approved_amount: string | null;
    paid_amount: string | null;
    submitted_at: string | null;
    exported_at: string | null;
    paid_at: string | null;
    created_at: string;
    creator?: {
        id: number;
        name: string;
    };
}

const statusConfig: Record<string, { label: string; className: string }> = {
    draft: { label: 'Draft', className: 'bg-gray-500 hover:bg-gray-500' },
    finalized: { label: 'Finalized', className: 'bg-blue-500 hover:bg-blue-500' },
    submitted: { label: 'Submitted', className: 'bg-purple-500 hover:bg-purple-500' },
    processing: { label: 'Processing', className: 'bg-yellow-500 hover:bg-yellow-500' },
    completed: { label: 'Completed', className: 'bg-green-500 hover:bg-green-500' },
};

const formatCurrency = (amount: string | null) => {
    if (!amount) return 'GHS 0.00';
    return new Intl.NumberFormat('en-GH', {
        style: 'currency',
        currency: 'GHS',
    }).format(parseFloat(amount));
};

const formatDate = (dateString: string | null) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

export const batchesColumns: ColumnDef<ClaimBatch>[] = [
    {
        accessorKey: 'batch_number',
        header: ({ column }) => (
            <Button
                variant="ghost"
                onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
            >
                Batch Number
                <ArrowUpDown className="ml-2 h-4 w-4" />
            </Button>
        ),
        cell: ({ row }) => (
            <div className="font-mono text-sm font-medium">
                {row.original.batch_number}
            </div>
        ),
    },
    {
        accessorKey: 'name',
        header: ({ column }) => (
            <Button
                variant="ghost"
                onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
            >
                Name
                <ArrowUpDown className="ml-2 h-4 w-4" />
            </Button>
        ),
        cell: ({ row }) => (
            <div className="space-y-1">
                <div className="font-medium">{row.original.name}</div>
                {row.original.creator && (
                    <div className="text-sm text-muted-foreground">
                        by {row.original.creator.name}
                    </div>
                )}
            </div>
        ),
    },
    {
        accessorKey: 'submission_period_formatted',
        header: 'Period',
        cell: ({ row }) => (
            <div className="flex items-center gap-1">
                <Calendar className="h-4 w-4 text-muted-foreground" />
                {row.original.submission_period_formatted}
            </div>
        ),
    },
    {
        accessorKey: 'total_claims',
        header: ({ column }) => (
            <Button
                variant="ghost"
                className="w-full justify-center"
                onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
            >
                Claims
                <ArrowUpDown className="ml-2 h-4 w-4" />
            </Button>
        ),
        cell: ({ row }) => (
            <div className="text-center">
                <Badge variant="outline">{row.original.total_claims} claims</Badge>
            </div>
        ),
    },
    {
        accessorKey: 'total_amount',
        header: ({ column }) => (
            <Button
                variant="ghost"
                className="w-full justify-end"
                onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
            >
                Total Amount
                <ArrowUpDown className="ml-2 h-4 w-4" />
            </Button>
        ),
        cell: ({ row }) => (
            <div className="text-right font-medium">
                {formatCurrency(row.original.total_amount)}
            </div>
        ),
    },
    {
        accessorKey: 'approved_amount',
        header: () => <div className="text-right">Approved</div>,
        cell: ({ row }) => (
            <div className="text-right">
                {row.original.approved_amount ? (
                    <span className="font-medium text-green-600">
                        {formatCurrency(row.original.approved_amount)}
                    </span>
                ) : (
                    <span className="text-muted-foreground">-</span>
                )}
            </div>
        ),
    },
    {
        accessorKey: 'status',
        header: 'Status',
        cell: ({ row }) => {
            const config = statusConfig[row.original.status] || {
                label: row.original.status,
                className: 'bg-gray-400',
            };
            return <Badge className={config.className}>{config.label}</Badge>;
        },
        filterFn: (row, id, value) => value.includes(row.getValue(id)),
    },
    {
        accessorKey: 'created_at',
        header: ({ column }) => (
            <Button
                variant="ghost"
                onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
            >
                Created
                <ArrowUpDown className="ml-2 h-4 w-4" />
            </Button>
        ),
        cell: ({ row }) => formatDate(row.original.created_at),
    },
    {
        id: 'actions',
        enableHiding: false,
        cell: ({ row }) => (
            <div className="text-right">
                <Link href={`/admin/insurance/batches/${row.original.id}`}>
                    <Button variant="ghost" size="sm">
                        <Eye className="h-4 w-4" />
                    </Button>
                </Link>
            </div>
        ),
    },
];
