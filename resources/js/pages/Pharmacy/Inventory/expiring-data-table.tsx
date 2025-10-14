'use client';

import { Link } from '@inertiajs/react';
import {
    ColumnDef,
    ColumnFiltersState,
    SortingState,
    VisibilityState,
    flexRender,
    getCoreRowModel,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    useReactTable,
} from '@tanstack/react-table';
import {
    Calendar,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Filter,
    Search,
} from 'lucide-react';
import * as React from 'react';

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
import { Package } from 'lucide-react';

interface DataTableProps<TData, TValue> {
    columns: ColumnDef<TData, TValue>[];
    data: TData[];
    pagination?: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
    };
}

export function DataTable<TData, TValue>({
    columns,
    data,
    pagination,
}: DataTableProps<TData, TValue>) {
    const [sorting, setSorting] = React.useState<SortingState>([]);
    const [columnFilters, setColumnFilters] =
        React.useState<ColumnFiltersState>([]);
    const [columnVisibility, setColumnVisibility] =
        React.useState<VisibilityState>({});
    const [rowSelection, setRowSelection] = React.useState({});
    const [globalFilter, setGlobalFilter] = React.useState('');

    const table = useReactTable({
        data,
        columns,
        onSortingChange: setSorting,
        onColumnFiltersChange: setColumnFilters,
        getCoreRowModel: getCoreRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        onColumnVisibilityChange: setColumnVisibility,
        onRowSelectionChange: setRowSelection,
        onGlobalFilterChange: setGlobalFilter,
        state: {
            sorting,
            columnFilters,
            columnVisibility,
            rowSelection,
            globalFilter,
        },
    });

    // Get unique suppliers for filtering
    const suppliers = React.useMemo(() => {
        const supplierSet = new Set<string>();
        data.forEach((item: any) => {
            if (item.supplier?.name) {
                supplierSet.add(item.supplier.name);
            }
        });
        return Array.from(supplierSet);
    }, [data]);

    // Get expiry status options
    const expiryStatuses = React.useMemo(() => {
        return [
            { value: 'expired', label: 'Expired' },
            { value: 'expires_soon', label: 'Expires Soon (≤7 days)' },
            { value: 'expiring', label: 'Expiring (≤30 days)' },
            { value: 'stable', label: 'Stable (>30 days)' },
        ];
    }, []);

    return (
        <div className="w-full space-y-4">
            <div className="flex items-center gap-4">
                <div className="relative max-w-sm flex-1">
                    <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 transform text-muted-foreground" />
                    <Input
                        placeholder="Search drugs, batches..."
                        value={globalFilter}
                        onChange={(event) =>
                            setGlobalFilter(event.target.value)
                        }
                        className="pl-10"
                    />
                </div>

                {/* Supplier Filter */}
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" className="border-dashed">
                            <Filter className="mr-2 h-4 w-4" />
                            Supplier
                            <ChevronDown className="ml-2 h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start">
                        <DropdownMenuLabel>
                            Filter by Supplier
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        {suppliers.map((supplier) => (
                            <DropdownMenuCheckboxItem
                                key={supplier}
                                checked={
                                    (
                                        table
                                            .getColumn('supplier')
                                            ?.getFilterValue() as string[]
                                    )?.includes(supplier) ?? false
                                }
                                onCheckedChange={(checked) => {
                                    const currentFilter =
                                        (table
                                            .getColumn('supplier')
                                            ?.getFilterValue() as string[]) ||
                                        [];
                                    if (checked) {
                                        table
                                            .getColumn('supplier')
                                            ?.setFilterValue([
                                                ...currentFilter,
                                                supplier,
                                            ]);
                                    } else {
                                        table
                                            .getColumn('supplier')
                                            ?.setFilterValue(
                                                currentFilter.filter(
                                                    (s) => s !== supplier,
                                                ),
                                            );
                                    }
                                }}
                            >
                                {supplier}
                            </DropdownMenuCheckboxItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>

                {/* Expiry Status Filter */}
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" className="border-dashed">
                            <Calendar className="mr-2 h-4 w-4" />
                            Expiry Status
                            <ChevronDown className="ml-2 h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start">
                        <DropdownMenuLabel>
                            Filter by Expiry Status
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        {expiryStatuses.map((status) => (
                            <DropdownMenuCheckboxItem
                                key={status.value}
                                checked={
                                    (
                                        table
                                            .getColumn('expiry_status')
                                            ?.getFilterValue() as string[]
                                    )?.includes(status.value) ?? false
                                }
                                onCheckedChange={(checked) => {
                                    const currentFilter =
                                        (table
                                            .getColumn('expiry_status')
                                            ?.getFilterValue() as string[]) ||
                                        [];
                                    if (checked) {
                                        table
                                            .getColumn('expiry_status')
                                            ?.setFilterValue([
                                                ...currentFilter,
                                                status.value,
                                            ]);
                                    } else {
                                        table
                                            .getColumn('expiry_status')
                                            ?.setFilterValue(
                                                currentFilter.filter(
                                                    (s) => s !== status.value,
                                                ),
                                            );
                                    }
                                }}
                            >
                                {status.label}
                            </DropdownMenuCheckboxItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>

                {/* Column Visibility */}
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" className="ml-auto">
                            Columns <ChevronDown className="ml-2 h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        {table
                            .getAllColumns()
                            .filter((column) => column.getCanHide())
                            .map((column) => {
                                return (
                                    <DropdownMenuCheckboxItem
                                        key={column.id}
                                        className="capitalize"
                                        checked={column.getIsVisible()}
                                        onCheckedChange={(value) =>
                                            column.toggleVisibility(!!value)
                                        }
                                    >
                                        {column.id.replace('_', ' ')}
                                    </DropdownMenuCheckboxItem>
                                );
                            })}
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            <div className="rounded-md border">
                <Table>
                    <TableHeader>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id}>
                                {headerGroup.headers.map((header) => {
                                    return (
                                        <TableHead key={header.id}>
                                            {header.isPlaceholder
                                                ? null
                                                : flexRender(
                                                      header.column.columnDef
                                                          .header,
                                                      header.getContext(),
                                                  )}
                                        </TableHead>
                                    );
                                })}
                            </TableRow>
                        ))}
                    </TableHeader>
                    <TableBody>
                        {table.getRowModel().rows?.length ? (
                            table.getRowModel().rows.map((row) => (
                                <TableRow
                                    key={row.id}
                                    data-state={
                                        row.getIsSelected() && 'selected'
                                    }
                                    className="hover:bg-muted/50"
                                >
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
                                        <Package className="h-8 w-8 text-muted-foreground" />
                                        <div>No expiring batches found.</div>
                                        <div className="text-sm text-muted-foreground">
                                            No batches match your current
                                            filters.
                                        </div>
                                    </div>
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>

            {/* Laravel Pagination */}
            {pagination && (
                <div className="flex items-center justify-between">
                    <div className="text-sm text-muted-foreground">
                        Showing{' '}
                        {Math.min(
                            (pagination.current_page - 1) *
                                pagination.per_page +
                                1,
                            pagination.total,
                        )}{' '}
                        to{' '}
                        {Math.min(
                            pagination.current_page * pagination.per_page,
                            pagination.total,
                        )}{' '}
                        of {pagination.total} batch(es).
                    </div>
                    <div className="flex items-center space-x-2">
                        {pagination.links.map((link, index) => {
                            if (!link.url) {
                                return (
                                    <Button
                                        key={index}
                                        variant="outline"
                                        size="sm"
                                        disabled
                                        className="opacity-50"
                                    >
                                        {link.label === '&laquo; Previous' ? (
                                            <ChevronLeft className="h-4 w-4" />
                                        ) : link.label === 'Next &raquo;' ? (
                                            <ChevronRight className="h-4 w-4" />
                                        ) : (
                                            <span
                                                dangerouslySetInnerHTML={{
                                                    __html: link.label,
                                                }}
                                            />
                                        )}
                                    </Button>
                                );
                            }

                            return (
                                <Button
                                    key={index}
                                    variant={
                                        link.active ? 'default' : 'outline'
                                    }
                                    size="sm"
                                    asChild
                                >
                                    <Link href={link.url}>
                                        {link.label === '&laquo; Previous' ? (
                                            <ChevronLeft className="h-4 w-4" />
                                        ) : link.label === 'Next &raquo;' ? (
                                            <ChevronRight className="h-4 w-4" />
                                        ) : (
                                            <span
                                                dangerouslySetInnerHTML={{
                                                    __html: link.label,
                                                }}
                                            />
                                        )}
                                    </Link>
                                </Button>
                            );
                        })}
                    </div>
                </div>
            )}
        </div>
    );
}
