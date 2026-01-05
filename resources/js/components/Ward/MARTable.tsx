'use client';

import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { router } from '@inertiajs/react';
import {
    ColumnDef,
    ColumnFiltersState,
    SortingState,
    flexRender,
    getCoreRowModel,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    useReactTable,
} from '@tanstack/react-table';
import { differenceInHours, format, isToday } from 'date-fns';
import {
    ArrowUpDown,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Pill,
    Search,
    Trash2,
} from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

interface Drug {
    id: number;
    name: string;
    strength?: string;
}

interface Prescription {
    id: number;
    drug?: Drug;
    medication_name: string;
    dose_quantity?: string;
    frequency?: string;
}

interface User {
    id: number;
    name: string;
}

export interface MedicationAdministration {
    id: number;
    prescription_id: number;
    administered_at: string;
    status: 'given' | 'held' | 'refused' | 'omitted';
    administered_by?: User;
    notes?: string;
    dosage_given?: string;
    route?: string;
}

interface MARTableProps {
    administrations: MedicationAdministration[];
    prescriptions: Prescription[];
    admissionId: number;
    canDelete?: boolean;
}

const statusConfig: Record<
    string,
    {
        label: string;
        variant: 'default' | 'secondary' | 'destructive' | 'outline';
        className?: string;
    }
> = {
    given: {
        label: 'Given',
        variant: 'default',
        className: 'bg-green-600 hover:bg-green-700',
    },
    held: { label: 'Held', variant: 'secondary' },
    refused: { label: 'Refused', variant: 'destructive' },
    omitted: { label: 'Omitted', variant: 'outline' },
};

export function MARTable({ administrations, prescriptions, admissionId, canDelete = false }: MARTableProps) {
    const [sorting, setSorting] = React.useState<SortingState>([
        { id: 'administered_at', desc: true },
    ]);
    const [columnFilters, setColumnFilters] =
        React.useState<ColumnFiltersState>([]);
    const [globalFilter, setGlobalFilter] = React.useState('');
    const [statusFilter, setStatusFilter] = React.useState<string>('all');
    const [deleteConfirmOpen, setDeleteConfirmOpen] = React.useState(false);
    const [deletingRecord, setDeletingRecord] = React.useState<MedicationAdministration | null>(null);
    const [isDeleting, setIsDeleting] = React.useState(false);

    // Helper to get prescription details
    const getPrescription = (prescriptionId: number) => {
        return prescriptions.find((p) => p.id === prescriptionId);
    };

    // Check if a record can be deleted (within 2 hours)
    const canDeleteRecord = (record: MedicationAdministration) => {
        if (!canDelete) return false;
        const administeredAt = new Date(record.administered_at);
        const hoursSince = differenceInHours(new Date(), administeredAt);
        return hoursSince < 2;
    };

    // Handle delete confirmation
    const handleDeleteClick = (record: MedicationAdministration) => {
        setDeletingRecord(record);
        setDeleteConfirmOpen(true);
    };

    // Handle actual delete
    const handleConfirmDelete = () => {
        if (!deletingRecord) return;

        setIsDeleting(true);
        router.delete(`/admissions/${admissionId}/medications/${deletingRecord.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Medication administration record deleted');
                setDeleteConfirmOpen(false);
                setDeletingRecord(null);
            },
            onError: (errors: any) => {
                const errorMessage = errors.medication || 'Failed to delete record';
                toast.error(errorMessage);
            },
            onFinish: () => {
                setIsDeleting(false);
            },
        });
    };

    // Filter data by status
    const filteredData = React.useMemo(() => {
        if (statusFilter === 'all') return administrations;
        return administrations.filter((a) => a.status === statusFilter);
    }, [administrations, statusFilter]);

    const columns: ColumnDef<MedicationAdministration>[] = [
        {
            accessorKey: 'administered_at',
            header: ({ column }) => (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                    className="-ml-4"
                >
                    Date & Time
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            ),
            cell: ({ row }) => {
                const date = new Date(row.getValue('administered_at'));
                const dateIsToday = isToday(date);
                return (
                    <div className="font-medium">
                        {dateIsToday ? (
                            <span>{format(date, 'HH:mm')}</span>
                        ) : (
                            <div>
                                <div>{format(date, 'MMM d, yyyy')}</div>
                                <div className="text-xs text-muted-foreground">
                                    {format(date, 'HH:mm')}
                                </div>
                            </div>
                        )}
                    </div>
                );
            },
        },
        {
            id: 'medication',
            header: 'Medication',
            accessorFn: (row) => {
                const prescription = getPrescription(row.prescription_id);
                return (
                    prescription?.drug?.name ||
                    prescription?.medication_name ||
                    'Unknown'
                );
            },
            cell: ({ row }) => {
                const prescription = getPrescription(
                    row.original.prescription_id,
                );
                return (
                    <div>
                        <div className="font-medium">
                            {prescription?.drug?.name ||
                                prescription?.medication_name ||
                                'Unknown'}
                        </div>
                        {prescription?.drug?.strength && (
                            <div className="text-xs text-muted-foreground">
                                {prescription.drug.strength}
                            </div>
                        )}
                    </div>
                );
            },
        },
        {
            accessorKey: 'dosage_given',
            header: 'Dosage',
            cell: ({ row }) => {
                const dosage = row.getValue('dosage_given') as string;
                return (
                    dosage || <span className="text-muted-foreground">-</span>
                );
            },
        },
        {
            accessorKey: 'route',
            header: 'Route',
            cell: ({ row }) => {
                const route = row.getValue('route') as string;
                return route ? (
                    <span className="capitalize">{route}</span>
                ) : (
                    <span className="text-muted-foreground">-</span>
                );
            },
        },
        {
            accessorKey: 'status',
            header: 'Status',
            cell: ({ row }) => {
                const status = row.getValue('status') as string;
                const config = statusConfig[status] || statusConfig.given;
                return (
                    <Badge
                        variant={config.variant}
                        className={config.className}
                    >
                        {config.label}
                    </Badge>
                );
            },
        },
        {
            id: 'administered_by',
            header: 'Recorded By',
            accessorFn: (row) => row.administered_by?.name || 'Unknown',
            cell: ({ row }) => {
                return (
                    <span className="text-sm text-muted-foreground">
                        {row.original.administered_by?.name || 'Unknown'}
                    </span>
                );
            },
        },
        {
            accessorKey: 'notes',
            header: 'Notes',
            cell: ({ row }) => {
                const notes = row.getValue('notes') as string;
                if (!notes)
                    return <span className="text-muted-foreground">-</span>;
                return (
                    <span
                        className="max-w-[200px] truncate text-sm"
                        title={notes}
                    >
                        {notes}
                    </span>
                );
            },
        },
        ...(canDelete ? [{
            id: 'actions',
            header: '',
            cell: ({ row }: { row: { original: MedicationAdministration } }) => {
                const record = row.original;
                const deletable = canDeleteRecord(record);
                
                if (!deletable) return null;
                
                return (
                    <Button
                        variant="ghost"
                        size="sm"
                        className="h-8 w-8 p-0 text-red-600 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-950"
                        onClick={() => handleDeleteClick(record)}
                        title="Delete (within 2 hours only)"
                    >
                        <Trash2 className="h-4 w-4" />
                    </Button>
                );
            },
        }] : []),
    ];

    const table = useReactTable({
        data: filteredData,
        columns,
        onSortingChange: setSorting,
        onColumnFiltersChange: setColumnFilters,
        onGlobalFilterChange: setGlobalFilter,
        getCoreRowModel: getCoreRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        state: {
            sorting,
            columnFilters,
            globalFilter,
        },
        initialState: {
            pagination: {
                pageSize: 10,
            },
        },
    });

    return (
        <div className="space-y-4">
            {/* Filters */}
            <div className="flex items-center gap-4">
                <div className="relative max-w-sm flex-1">
                    <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        placeholder="Search medications..."
                        value={globalFilter}
                        onChange={(e) => setGlobalFilter(e.target.value)}
                        className="pl-10"
                    />
                </div>

                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" className="border-dashed">
                            Status:{' '}
                            {statusFilter === 'all'
                                ? 'All'
                                : statusConfig[statusFilter]?.label}
                            <ChevronDown className="ml-2 h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start">
                        <DropdownMenuLabel>Filter by Status</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuCheckboxItem
                            checked={statusFilter === 'all'}
                            onCheckedChange={() => setStatusFilter('all')}
                        >
                            All
                        </DropdownMenuCheckboxItem>
                        {Object.entries(statusConfig).map(([key, config]) => (
                            <DropdownMenuCheckboxItem
                                key={key}
                                checked={statusFilter === key}
                                onCheckedChange={() => setStatusFilter(key)}
                            >
                                {config.label}
                            </DropdownMenuCheckboxItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            {/* Table */}
            <div className="rounded-md border">
                <Table>
                    <TableHeader>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id}>
                                {headerGroup.headers.map((header) => (
                                    <TableHead key={header.id}>
                                        {header.isPlaceholder
                                            ? null
                                            : flexRender(
                                                  header.column.columnDef
                                                      .header,
                                                  header.getContext(),
                                              )}
                                    </TableHead>
                                ))}
                            </TableRow>
                        ))}
                    </TableHeader>
                    <TableBody>
                        {table.getRowModel().rows?.length ? (
                            table.getRowModel().rows.map((row) => (
                                <TableRow key={row.id}>
                                    {row.getVisibleCells().map((cell) => (
                                        <TableCell key={cell.id}>
                                            {flexRender(
                                                cell.column.columnDef.cell,
                                                cell.getContext(),
                                            )}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell
                                    colSpan={columns.length}
                                    className="h-24 text-center"
                                >
                                    <div className="flex flex-col items-center gap-2">
                                        <Pill className="h-8 w-8 text-muted-foreground" />
                                        <div>
                                            No medication administrations found
                                        </div>
                                        <div className="text-sm text-muted-foreground">
                                            Click "Record Medication" to log
                                            administrations
                                        </div>
                                    </div>
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>

            {/* Pagination */}
            {filteredData.length > 0 && (
                <div className="flex items-center justify-between">
                    <div className="text-sm text-muted-foreground">
                        Showing{' '}
                        {table.getState().pagination.pageIndex *
                            table.getState().pagination.pageSize +
                            1}{' '}
                        to{' '}
                        {Math.min(
                            (table.getState().pagination.pageIndex + 1) *
                                table.getState().pagination.pageSize,
                            filteredData.length,
                        )}{' '}
                        of {filteredData.length} record(s)
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => table.previousPage()}
                            disabled={!table.getCanPreviousPage()}
                        >
                            <ChevronLeft className="h-4 w-4" />
                            Previous
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => table.nextPage()}
                            disabled={!table.getCanNextPage()}
                        >
                            Next
                            <ChevronRight className="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            )}

            {/* Delete Confirmation Dialog */}
            <AlertDialog open={deleteConfirmOpen} onOpenChange={setDeleteConfirmOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete Medication Record</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to delete this medication administration record?
                            {deletingRecord && (
                                <span className="mt-2 block font-medium text-foreground">
                                    {getPrescription(deletingRecord.prescription_id)?.drug?.name || 
                                     getPrescription(deletingRecord.prescription_id)?.medication_name || 
                                     'Unknown medication'} - {statusConfig[deletingRecord.status]?.label}
                                </span>
                            )}
                            This action cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={isDeleting}>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={handleConfirmDelete}
                            disabled={isDeleting}
                            className="bg-red-600 hover:bg-red-700"
                        >
                            {isDeleting ? 'Deleting...' : 'Delete'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}
