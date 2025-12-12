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
import { ChevronDown, FlaskConical, Search } from 'lucide-react';
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
import { useDebouncedCallback } from 'use-debounce';

interface DataTableProps<TData, TValue> {
    columns: ColumnDef<TData, TValue>[];
    data: TData[];
}

export function DataTable<TData, TValue>({
    columns,
    data,
}: DataTableProps<TData, TValue>) {
    const [sorting, setSorting] = React.useState<SortingState>([]);
    const [columnFilters, setColumnFilters] =
        React.useState<ColumnFiltersState>([]);
    const [columnVisibility, setColumnVisibility] =
        React.useState<VisibilityState>({});
    const [rowSelection, setRowSelection] = React.useState({});

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
        state: {
            sorting,
            columnFilters,
            columnVisibility,
            rowSelection,
        },
    });

    const [search, setSearch] = React.useState('');

    // Get unique categories for category filter
    const categories = React.useMemo(() => {
        const categorySet = new Set<string>();
        data.forEach((item: any) => {
            if (item.category) {
                categorySet.add(item.category);
            }
        });
        return Array.from(categorySet);
    }, [data]);

    // Debounced client-side search
    const debouncedSearch = useDebouncedCallback((value: string) => {
        table.getColumn('name')?.setFilterValue(value);
    }, 300);

    const handleSearchChange = (value: string) => {
        setSearch(value);
        debouncedSearch(value);
    };

    return (
        <div className="w-full space-y-4">
            <div className="flex items-center gap-4">
                <div className="relative max-w-sm flex-1">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        placeholder="Search by name or code..."
                        value={search}
                        onChange={(e) => handleSearchChange(e.target.value)}
                        className="pl-10"
                    />
                </div>

                {/* Category Filter */}
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" className="border-dashed">
                            Category
                            <ChevronDown className="ml-2 h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start">
                        <DropdownMenuLabel>
                            Filter by Category
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        {categories.map((category) => (
                            <DropdownMenuCheckboxItem
                                key={category}
                                checked={
                                    (
                                        table
                                            .getColumn('category')
                                            ?.getFilterValue() as string[]
                                    )?.includes(category) ?? false
                                }
                                onCheckedChange={(checked) => {
                                    const currentFilter =
                                        (table
                                            .getColumn('category')
                                            ?.getFilterValue() as string[]) ||
                                        [];
                                    if (checked) {
                                        table
                                            .getColumn('category')
                                            ?.setFilterValue([
                                                ...currentFilter,
                                                category,
                                            ]);
                                    } else {
                                        table
                                            .getColumn('category')
                                            ?.setFilterValue(
                                                currentFilter.filter(
                                                    (c) => c !== category,
                                                ),
                                            );
                                    }
                                }}
                            >
                                {category}
                            </DropdownMenuCheckboxItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>

                {/* Status Filter */}
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" className="border-dashed">
                            Status
                            <ChevronDown className="ml-2 h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start">
                        <DropdownMenuLabel>Filter by Status</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuCheckboxItem
                            checked={
                                (
                                    table
                                        .getColumn('status')
                                        ?.getFilterValue() as string[]
                                )?.includes('configured') ?? false
                            }
                            onCheckedChange={(checked) => {
                                const currentFilter =
                                    (table
                                        .getColumn('status')
                                        ?.getFilterValue() as string[]) || [];
                                if (checked) {
                                    table
                                        .getColumn('status')
                                        ?.setFilterValue([
                                            ...currentFilter,
                                            'configured',
                                        ]);
                                } else {
                                    table
                                        .getColumn('status')
                                        ?.setFilterValue(
                                            currentFilter.filter(
                                                (s) => s !== 'configured',
                                            ),
                                        );
                                }
                            }}
                        >
                            Configured
                        </DropdownMenuCheckboxItem>
                        <DropdownMenuCheckboxItem
                            checked={
                                (
                                    table
                                        .getColumn('status')
                                        ?.getFilterValue() as string[]
                                )?.includes('pending') ?? false
                            }
                            onCheckedChange={(checked) => {
                                const currentFilter =
                                    (table
                                        .getColumn('status')
                                        ?.getFilterValue() as string[]) || [];
                                if (checked) {
                                    table
                                        .getColumn('status')
                                        ?.setFilterValue([
                                            ...currentFilter,
                                            'pending',
                                        ]);
                                } else {
                                    table
                                        .getColumn('status')
                                        ?.setFilterValue(
                                            currentFilter.filter(
                                                (s) => s !== 'pending',
                                            ),
                                        );
                                }
                            }}
                        >
                            Setup Needed
                        </DropdownMenuCheckboxItem>
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
                                        {column.id}
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
                                        <div>No lab services found.</div>
                                        <div className="text-sm text-muted-foreground">
                                            Try adjusting your search or filters.
                                        </div>
                                    </div>
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>
            <div className="flex items-center justify-end space-x-2 py-4">
                <div className="flex-1 text-sm text-muted-foreground">
                    Showing {table.getRowModel().rows.length} of{' '}
                    {table.getFilteredRowModel().rows.length} lab service(s).
                </div>
                <div className="space-x-2">
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
