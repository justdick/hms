'use client';

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
import { ChevronDown, Search } from 'lucide-react';
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
import { FlaskConical } from 'lucide-react';

interface DataTableProps<TData, TValue> {
    columns: ColumnDef<TData, TValue>[];
    data: TData[];
}

export function LabOrdersDataTable<TData, TValue>({
    columns,
    data,
}: DataTableProps<TData, TValue>) {
    const [sorting, setSorting] = React.useState<SortingState>([]);
    const [columnFilters, setColumnFilters] =
        React.useState<ColumnFiltersState>([]);
    const [columnVisibility, setColumnVisibility] =
        React.useState<VisibilityState>({});
    const [rowSelection, setRowSelection] = React.useState({});
    const [globalFilter, setGlobalFilter] = React.useState('');
    const [statusFilter, setStatusFilter] = React.useState<string>('all');
    const [categoryFilter, setCategoryFilter] = React.useState<string>('all');
    const [priorityFilter, setPriorityFilter] = React.useState<string>('all');

    // Client-side filtering (works with grouped consultations)
    const filteredData = React.useMemo(() => {
        return data.filter((item: any) => {
            // Status filter - check if ANY test in the consultation matches the status
            if (statusFilter !== 'all') {
                if (item.tests && Array.isArray(item.tests)) {
                    const hasMatchingStatus = item.tests.some(
                        (test: any) => test.status === statusFilter,
                    );
                    if (!hasMatchingStatus) return false;
                }
            }

            // Priority filter - check consultation-level priority
            if (priorityFilter !== 'all' && item.priority !== priorityFilter) {
                return false;
            }

            // Category filter - check if ANY test matches the category
            if (categoryFilter !== 'all') {
                if (item.tests && Array.isArray(item.tests)) {
                    const hasMatchingCategory = item.tests.some(
                        (test: any) => test.category === categoryFilter,
                    );
                    if (!hasMatchingCategory) return false;
                }
            }

            return true;
        });
    }, [data, statusFilter, priorityFilter, categoryFilter]);

    const table = useReactTable({
        data: filteredData,
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
        globalFilterFn: (row, columnId, filterValue) => {
            const searchStr = String(filterValue).toLowerCase();
            const rowData = row.original as any;

            // Search in test name, code, and category
            if (rowData.lab_service) {
                return (
                    rowData.lab_service.name
                        ?.toLowerCase()
                        .includes(searchStr) ||
                    rowData.lab_service.code
                        ?.toLowerCase()
                        .includes(searchStr) ||
                    rowData.lab_service.category
                        ?.toLowerCase()
                        .includes(searchStr)
                );
            }

            // For grouped consultations, also search patient name
            if (rowData.patient) {
                const patientName =
                    `${rowData.patient.first_name} ${rowData.patient.last_name}`.toLowerCase();
                return patientName.includes(searchStr);
            }

            return false;
        },
        state: {
            sorting,
            columnFilters,
            columnVisibility,
            rowSelection,
            globalFilter,
        },
    });

    // Get unique values for filters from grouped consultation data
    const statuses = React.useMemo(() => {
        const statusSet = new Set<string>();
        data.forEach((item: any) => {
            // For grouped consultations, extract statuses from tests array
            if (item.tests && Array.isArray(item.tests)) {
                item.tests.forEach((test: any) => {
                    if (test.status) {
                        statusSet.add(test.status);
                    }
                });
            }
            // For individual lab orders (backward compatibility)
            else if (item.status) {
                statusSet.add(item.status);
            }
        });
        return Array.from(statusSet);
    }, [data]);

    const priorities = React.useMemo(() => {
        const prioritySet = new Set<string>();
        data.forEach((item: any) => {
            if (item.priority) {
                prioritySet.add(item.priority);
            }
        });
        return Array.from(prioritySet);
    }, [data]);

    const categories = React.useMemo(() => {
        const categorySet = new Set<string>();
        data.forEach((item: any) => {
            // For grouped consultations, extract categories from tests array
            if (item.tests && Array.isArray(item.tests)) {
                item.tests.forEach((test: any) => {
                    if (test.category) {
                        categorySet.add(test.category);
                    }
                });
            }
            // For individual lab orders (backward compatibility)
            else if (item.lab_service?.category) {
                categorySet.add(item.lab_service.category);
            }
        });
        return Array.from(categorySet);
    }, [data]);

    return (
        <div className="w-full space-y-4">
            <div className="flex items-center gap-4">
                <div className="relative max-w-sm flex-1">
                    <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 transform text-muted-foreground" />
                    <Input
                        placeholder="Search patients, tests..."
                        value={globalFilter}
                        onChange={(event) => {
                            setGlobalFilter(event.target.value);
                        }}
                        className="pl-10"
                    />
                </div>

                {/* Status Filter */}
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" className="border-dashed">
                            Status
                            <ChevronDown className="ml-2 h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        align="start"
                        onCloseAutoFocus={(e) => e.preventDefault()}
                    >
                        <DropdownMenuLabel>Filter by Status</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        {statuses.map((status) => (
                            <DropdownMenuCheckboxItem
                                key={status}
                                checked={statusFilter === status}
                                onCheckedChange={(checked) => {
                                    setStatusFilter(checked ? status : 'all');
                                }}
                            >
                                {status
                                    .replace('_', ' ')
                                    .replace(/\b\w/g, (l) => l.toUpperCase())}
                            </DropdownMenuCheckboxItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>

                {/* Priority Filter */}
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" className="border-dashed">
                            Priority
                            <ChevronDown className="ml-2 h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        align="start"
                        onCloseAutoFocus={(e) => e.preventDefault()}
                    >
                        <DropdownMenuLabel>
                            Filter by Priority
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        {priorities.map((priority) => (
                            <DropdownMenuCheckboxItem
                                key={priority}
                                checked={priorityFilter === priority}
                                onCheckedChange={(checked) => {
                                    setPriorityFilter(
                                        checked ? priority : 'all',
                                    );
                                }}
                            >
                                {priority.toUpperCase()}
                            </DropdownMenuCheckboxItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>

                {/* Category Filter */}
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" className="border-dashed">
                            Category
                            <ChevronDown className="ml-2 h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        align="start"
                        onCloseAutoFocus={(e) => e.preventDefault()}
                    >
                        <DropdownMenuLabel>
                            Filter by Category
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        {categories.map((category) => (
                            <DropdownMenuCheckboxItem
                                key={category}
                                checked={categoryFilter === category}
                                onCheckedChange={(checked) => {
                                    setCategoryFilter(
                                        checked ? category : 'all',
                                    );
                                }}
                            >
                                {category}
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
                                        <FlaskConical className="h-8 w-8 text-muted-foreground" />
                                        <div>No lab orders found.</div>
                                        <div className="text-sm text-muted-foreground">
                                            No lab orders match your current
                                            filters.
                                        </div>
                                    </div>
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>

            <div className="flex items-center justify-between">
                <div className="text-sm text-muted-foreground">
                    Showing {table.getRowModel().rows.length} of{' '}
                    {table.getFilteredRowModel().rows.length} lab order(s).
                </div>
                <div className="flex items-center space-x-2">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => table.previousPage()}
                        disabled={!table.getCanPreviousPage()}
                    >
                        Previous
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => table.nextPage()}
                        disabled={!table.getCanNextPage()}
                    >
                        Next
                    </Button>
                </div>
            </div>
        </div>
    );
}
