import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ColumnDef } from '@tanstack/react-table';
import { ArrowUpDown, Edit, Trash2 } from 'lucide-react';
import { Department } from './DepartmentModal';

const typeLabels: Record<string, string> = {
    opd: 'Outpatient',
    ipd: 'Inpatient',
    diagnostic: 'Diagnostic',
    support: 'Support',
};

const typeColors: Record<string, string> = {
    opd: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
    ipd: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
    diagnostic: 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-300',
    support: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
};

export const columns = (
    onEdit: (department: Department) => void,
    onDelete: (department: Department) => void,
): ColumnDef<Department>[] => [
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
            <div>
                <p className="font-medium">{row.original.name}</p>
                {row.original.description && (
                    <p className="text-sm text-muted-foreground">{row.original.description}</p>
                )}
            </div>
        ),
    },
    {
        accessorKey: 'code',
        header: 'Code',
        cell: ({ row }) => (
            <code className="rounded bg-muted px-2 py-1 text-sm">{row.original.code}</code>
        ),
    },
    {
        accessorKey: 'type',
        header: 'Type',
        cell: ({ row }) => (
            <span
                className={`inline-flex rounded-full px-2 py-1 text-xs font-medium ${typeColors[row.original.type]}`}
            >
                {typeLabels[row.original.type] || row.original.type}
            </span>
        ),
        filterFn: (row, id, value: string[]) => {
            if (!value || value.length === 0) return true;
            return value.includes(row.getValue(id));
        },
    },
    {
        accessorKey: 'is_active',
        header: 'Status',
        cell: ({ row }) => (
            <Badge variant={row.original.is_active ? 'default' : 'secondary'}>
                {row.original.is_active ? 'Active' : 'Inactive'}
            </Badge>
        ),
    },
    {
        accessorKey: 'users_count',
        header: ({ column }) => (
            <Button
                variant="ghost"
                onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
                className="w-full justify-end"
            >
                Staff
                <ArrowUpDown className="ml-2 h-4 w-4" />
            </Button>
        ),
        cell: ({ row }) => (
            <div className="text-right">{row.original.users_count || 0}</div>
        ),
    },
    {
        accessorKey: 'checkins_count',
        header: ({ column }) => (
            <Button
                variant="ghost"
                onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
                className="w-full justify-end"
            >
                Check-ins
                <ArrowUpDown className="ml-2 h-4 w-4" />
            </Button>
        ),
        cell: ({ row }) => (
            <div className="text-right">{row.original.checkins_count || 0}</div>
        ),
    },
    {
        id: 'actions',
        header: '',
        cell: ({ row }) => (
            <div className="flex justify-end gap-1">
                <Button variant="ghost" size="sm" onClick={() => onEdit(row.original)}>
                    <Edit className="h-4 w-4" />
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => onDelete(row.original)}
                    className="text-red-600 hover:text-red-700"
                >
                    <Trash2 className="h-4 w-4" />
                </Button>
            </div>
        ),
    },
];
