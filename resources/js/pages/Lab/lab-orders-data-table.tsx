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
import { router } from '@inertiajs/react';
import { FlaskConical } from 'lucide-react';
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
    status: string;
    priority?: string;
    category?: string;
    search?: string;
}

interface DataTableProps<TData, TValue> {
    columns: ColumnDef<TData, TValue>[];
    data: TData[];
    pagination?: PaginationData;
    filters?: Filters;
}

export function LabOrdersDataTable<TData, TValue>({
    columns,
    data,
    pagination,
    filters,
}: DataTableProps<TData, TValue>) {
    // If no pagination provided, use client-side pagination defaults
    const hasPagination = !!pagination;
    const [sorting, setSorting] = React.useState<SortingState>([]);
    const [columnFilters, setColumnFilters] =
        React.useState<ColumnFiltersState>([]);
    const [columnVisibility, setColumnVisibility] =
        React.useState<VisibilityState>({});
    const [rowSelection, setRowSelection] = React.useState({});
    const [search, setSearch] = React.useState(filters?.search || '');

    const currentStatus = filters?.status || 'pending';
    const currentPriority = filters?.priority || '';
    const currentCategory = filters?.category || '';

    const allStatuses = [
        { value: 'pending', label: 'Pending (All Active)' },
        { value: 'ordered', label: 'Ordered' },
        { value: 'sample_collected', label: 'Sample Collected' },
        { value: 'in_progress', label: 'In Progress' },
        { value: 'completed', label: 'Completed' },
        { value: 'cancelled', label: 'Cancelled' },
        { value: 'all', label: 'All Statuses' },
    ];

    const priorities = ['stat', 'urgent', 'routine'];

    // Debounced server-side search
    const debouncedSearch = useDebouncedCallback((value: string) => {
        router.get(
            '/lab',
            {
                ...filters,
                search: value || undefined,
                page: 1, // Reset to first page on search
            },
            { preserveState: true, preserveScroll: true },
        );
    }, 300);

    const handleSearchChange = (value: string) => {
        setSearch(value);
        debouncedSearch(value);
    };

    const handleFilterChange = (key: string, value: string | undefined) => {
        router.get(
            '/lab',
            {
                ...filters,
                [key]: value || undefined,
                page: 1,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const handlePageChange = (url: string | null) => {
        if (url) {
            router.get(url, {}, { preserveState: true, preserveScroll: true });
        }
    };

    const handlePerPageChange = (perPage: string) => {
        router.get(
            '/lab',
            { ...filters, per_page: perPage, page: 1 },
            { preserveState: true, preserveScroll: true },
        );
    };

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
        manualPagination: hasPagination,
        pageCount: pagination?.last_page ?? 1,
        state: {
            sorting,
            columnFilters,
            columnVisibility,
            rowSelection,
            ...(hasPagination && {
                pagination: {
                    pageIndex: pagination.current_page - 1,
                    pageSize: pagination.per_page,
                },
            }),
        },
    });

    // Get unique categories from current page data for display
    const categories = React.useMemo(() => {
        const categorySet = new Set<string>();
        data.forEach((item: any) => {
            if (item.tests && Array.isArray(item.tests)) {
                item.tests.forEach((test: any) => {
                    if (test.category) {
                        categorySet.add(test.category);
                    }
                });
            }
        });
        return Array.from(categorySet);
    }, [data]);

    const prevLink = pagination?.links.find((link) =>
        link.label.includes('Previous'),
    );
    const nextLink = pagination?.links.find((link) =>
        link.label.includes('Next'),
    );

    return (
        <div className="w-full space-y-4">
            <div className="flex items-center gap-4">
                <div className="relative max-w-sm flex-1">
                    <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 transform text-muted-foreground" />
                    <Input
                        placeholder="Search patients, tests..."
                        value={search}
                        onChange={(event) =>
                            handleSearchChange(event.target.value)
                        }
                        className="pl-10"
                    />
                </div>

                {/* Per Page Selector */}
                {hasPagination && (
                    <div className="flex items-center gap-2">
                        <span className="text-sm text-muted-foreground">Show</span>
                        <select
                            value={pagination.per_page}
                            onChange={(e) => handlePerPageChange(e.target.value)}
                            className="h-8 rounded-md border border-input bg-background px-2 text-sm"
                        >
                            <option value="5">5</option>
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                )}

                {/* Status Filter */}
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" className="border-dashed">
                            Status:{' '}
                            {
                                allStatuses.find(
                                    (s) => s.value === currentStatus,
                                )?.label
                            }
                            <ChevronDown className="ml-2 h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        align="start"
                        onCloseAutoFocus={(e) => e.preventDefault()}
                    >
                        <DropdownMenuLabel>Filter by Status</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        {allStatuses.map((status) => (
                            <DropdownMenuCheckboxItem
                                key={status.value}
                                checked={currentStatus === status.value}
                                onCheckedChange={(checked) => {
                                    if (checked) {
                                        handleFilterChange('status', status.value);
                                    }
                                }}
                            >
                                {status.label}
                            </DropdownMenuCheckboxItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>

                {/* Priority Filter */}
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" className="border-dashed">
                            Priority{currentPriority ? `: ${currentPriority.toUpperCase()}` : ''}
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
                        <DropdownMenuCheckboxItem
                            checked={!currentPriority}
                            onCheckedChange={(checked) => {
                                if (checked) {
                                    handleFilterChange('priority', undefined);
                                }
                            }}
                        >
                            All Priorities
                        </DropdownMenuCheckboxItem>
                        {priorities.map((priority) => (
                            <DropdownMenuCheckboxItem
                                key={priority}
                                checked={currentPriority === priority}
                                onCheckedChange={(checked) => {
                                    handleFilterChange(
                                        'priority',
                                        checked ? priority : undefined,
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
                            Category{currentCategory ? `: ${currentCategory}` : ''}
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
                        <DropdownMenuCheckboxItem
                            checked={!currentCategory}
                            onCheckedChange={(checked) => {
                                if (checked) {
                                    handleFilterChange('category', undefined);
                                }
                            }}
                        >
                            All Categories
                        </DropdownMenuCheckboxItem>
                        {categories.map((category) => (
                            <DropdownMenuCheckboxItem
                                key={category}
                                checked={currentCategory === category}
                                onCheckedChange={(checked) => {
                                    handleFilterChange(
                                        'category',
                                        checked ? category : undefined,
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

            {/* Pagination */}
            {hasPagination && (
                <div className="flex items-center justify-between space-x-2 py-4">
                    <div className="text-sm text-muted-foreground">
                        {pagination.from && pagination.to ? (
                            <>
                                Showing {pagination.from} to {pagination.to} of{' '}
                                {pagination.total} patient order(s)
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
            )}

            {/* Simple count for non-paginated data */}
            {!hasPagination && data.length > 0 && (
                <div className="py-4 text-sm text-muted-foreground">
                    Showing {data.length} item(s)
                </div>
            )}
        </div>
    );
}
