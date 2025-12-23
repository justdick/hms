'use client';

import {
    ColumnDef,
    ColumnFiltersState,
    SortingState,
    VisibilityState,
    flexRender,
    getCoreRowModel,
    getFilteredRowModel,
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
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
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
import { useDebouncedCallback } from 'use-debounce';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginationData {
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
    links: PaginationLink[];
}

interface Filters {
    search?: string;
    category?: string;
    status?: string;
    per_page?: number;
}

interface DataTableProps<TData, TValue> {
    columns: ColumnDef<TData, TValue>[];
    data: TData[];
    pagination: PaginationData;
    filters: Filters;
    categories: string[];
}

export function DataTable<TData, TValue>({
    columns,
    data,
    pagination,
    filters,
    categories,
}: DataTableProps<TData, TValue>) {
    const [sorting, setSorting] = React.useState<SortingState>([]);
    const [columnFilters, setColumnFilters] =
        React.useState<ColumnFiltersState>([]);
    const [columnVisibility, setColumnVisibility] =
        React.useState<VisibilityState>({});
    const [rowSelection, setRowSelection] = React.useState({});
    const [search, setSearch] = React.useState(filters.search || '');

    const table = useReactTable({
        data,
        columns,
        onSortingChange: setSorting,
        onColumnFiltersChange: setColumnFilters,
        getCoreRowModel: getCoreRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        onColumnVisibilityChange: setColumnVisibility,
        onRowSelectionChange: setRowSelection,
        manualPagination: true,
        pageCount: pagination.last_page,
        state: {
            sorting,
            columnFilters,
            columnVisibility,
            rowSelection,
            pagination: {
                pageIndex: pagination.current_page - 1,
                pageSize: pagination.per_page,
            },
        },
    });

    // Debounced server-side search
    const debouncedSearch = useDebouncedCallback((value: string) => {
        router.get(
            window.location.pathname,
            {
                search: value || undefined,
                category: filters.category || undefined,
                status: filters.status || undefined,
                per_page: pagination.per_page,
            },
            { preserveState: true, preserveScroll: true },
        );
    }, 300);

    const handleSearchChange = (value: string) => {
        setSearch(value);
        debouncedSearch(value);
    };

    const handlePageChange = (url: string | null) => {
        if (url) {
            router.get(url, {}, { preserveState: true, preserveScroll: true });
        }
    };

    const handlePerPageChange = (perPage: string) => {
        router.get(
            window.location.pathname,
            {
                search: filters.search || undefined,
                category: filters.category || undefined,
                status: filters.status || undefined,
                per_page: perPage,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const handleCategoryChange = (category: string | undefined) => {
        router.get(
            window.location.pathname,
            {
                search: filters.search || undefined,
                category: category || undefined,
                status: filters.status || undefined,
                per_page: pagination.per_page,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const handleStatusChange = (status: string | undefined) => {
        router.get(
            window.location.pathname,
            {
                search: filters.search || undefined,
                category: filters.category || undefined,
                status: status || undefined,
                per_page: pagination.per_page,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    // Find prev/next links from pagination
    const prevLink = pagination.links.find((link) =>
        link.label.includes('Previous'),
    );
    const nextLink = pagination.links.find((link) =>
        link.label.includes('Next'),
    );

    return (
        <div className="w-full space-y-4">
            <div className="flex items-center gap-4">
                <div className="relative max-w-sm flex-1">
                    <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        placeholder="Search by name or code..."
                        value={search}
                        onChange={(e) => handleSearchChange(e.target.value)}
                        className="pl-10"
                    />
                </div>

                {/* Per Page Selector */}
                <div className="flex items-center gap-2">
                    <span className="text-sm text-muted-foreground">Show</span>
                    <select
                        value={pagination.per_page}
                        onChange={(e) => handlePerPageChange(e.target.value)}
                        className="h-8 rounded-md border border-input bg-background px-2 text-sm"
                    >
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>

                {/* Category Filter */}
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" className="border-dashed">
                            {filters.category || 'Category'}
                            <ChevronDown className="ml-2 h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start">
                        <DropdownMenuLabel>
                            Filter by Category
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuRadioGroup
                            value={filters.category || ''}
                            onValueChange={(value) =>
                                handleCategoryChange(value || undefined)
                            }
                        >
                            <DropdownMenuRadioItem value="">
                                All Categories
                            </DropdownMenuRadioItem>
                            {categories.map((category) => (
                                <DropdownMenuRadioItem
                                    key={category}
                                    value={category}
                                >
                                    {category}
                                </DropdownMenuRadioItem>
                            ))}
                        </DropdownMenuRadioGroup>
                    </DropdownMenuContent>
                </DropdownMenu>

                {/* Status Filter */}
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" className="border-dashed">
                            {filters.status === 'configured'
                                ? 'Configured'
                                : filters.status === 'pending'
                                  ? 'Setup Needed'
                                  : 'Status'}
                            <ChevronDown className="ml-2 h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start">
                        <DropdownMenuLabel>Filter by Status</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuRadioGroup
                            value={filters.status || ''}
                            onValueChange={(value) =>
                                handleStatusChange(value || undefined)
                            }
                        >
                            <DropdownMenuRadioItem value="">
                                All Statuses
                            </DropdownMenuRadioItem>
                            <DropdownMenuRadioItem value="configured">
                                Configured
                            </DropdownMenuRadioItem>
                            <DropdownMenuRadioItem value="pending">
                                Setup Needed
                            </DropdownMenuRadioItem>
                        </DropdownMenuRadioGroup>
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
                                            Try adjusting your search or
                                            filters.
                                        </div>
                                    </div>
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>

            {/* Pagination */}
            <div className="flex items-center justify-between space-x-2 py-4">
                <div className="text-sm text-muted-foreground">
                    {pagination.from && pagination.to ? (
                        <>
                            Showing {pagination.from} to {pagination.to} of{' '}
                            {pagination.total} lab service(s)
                        </>
                    ) : (
                        <>No results</>
                    )}
                </div>
                <div className="flex items-center gap-1">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => handlePageChange(prevLink?.url ?? null)}
                        disabled={!prevLink?.url}
                    >
                        Previous
                    </Button>
                    {pagination.links
                        .filter(
                            (link) =>
                                !link.label.includes('Previous') &&
                                !link.label.includes('Next'),
                        )
                        .map((link, index) => (
                            <Button
                                key={index}
                                variant={link.active ? 'default' : 'outline'}
                                size="sm"
                                className="min-w-[40px]"
                                onClick={() => handlePageChange(link.url)}
                                disabled={!link.url}
                            >
                                {link.label}
                            </Button>
                        ))}
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => handlePageChange(nextLink?.url ?? null)}
                        disabled={!nextLink?.url}
                    >
                        Next
                    </Button>
                </div>
            </div>
        </div>
    );
}
