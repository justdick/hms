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
import { ChevronDown, ClipboardList, Search } from 'lucide-react';
import * as React from 'react';

import { Button } from '@/components/ui/button';
import {
    DateFilterPresets,
    DateFilterValue,
} from '@/components/ui/date-filter-presets';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';
import { useDebouncedCallback } from 'use-debounce';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginationData {
    data: unknown[];
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
    status?: string;
    provider_id?: string;
    date_from?: string;
    date_to?: string;
    date_preset?: string;
    service_type?: string;
}

// Type for claim data with claim_check_code
interface ClaimData {
    claim_check_code?: string | null;
    [key: string]: unknown;
}

interface DataTableProps<TData, TValue> {
    columns: ColumnDef<TData, TValue>[];
    data: TData[];
    pagination: PaginationData;
    filters: Filters;
}

/**
 * Determines if a row should be highlighted as part of a CCC group.
 * A row is highlighted if it shares the same CCC with its previous or next neighbor.
 * _Requirements: 4.3, 4.4_
 */
function shouldHighlightCccGroup<TData extends ClaimData>(
    data: TData[],
    index: number,
): boolean {
    const currentCcc = data[index]?.claim_check_code;
    if (!currentCcc) return false;

    const prevCcc = index > 0 ? data[index - 1]?.claim_check_code : null;
    const nextCcc =
        index < data.length - 1 ? data[index + 1]?.claim_check_code : null;

    return currentCcc === prevCcc || currentCcc === nextCcc;
}

const statusOptions = [
    { value: 'all', label: 'All Statuses' },
    { value: 'pending_vetting', label: 'Pending Vetting' },
    { value: 'vetted', label: 'Vetted' },
    { value: 'submitted', label: 'Submitted' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'paid', label: 'Paid' },
    { value: 'partial', label: 'Partial Payment' },
];

const serviceTypeOptions = [
    { value: 'all', label: 'All Types' },
    { value: 'OPD', label: 'OPD' },
    { value: 'IPD', label: 'IPD' },
];

export function ClaimsDataTable<TData, TValue>({
    columns,
    data,
    pagination,
    filters,
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
            { ...filters, search: value || undefined },
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
            { ...filters, per_page: perPage },
            { preserveState: true, preserveScroll: true },
        );
    };

    const handleStatusFilter = (status: string) => {
        router.get(
            window.location.pathname,
            {
                ...filters,
                status: status,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const handleServiceTypeFilter = (serviceType: string) => {
        router.get(
            window.location.pathname,
            {
                ...filters,
                service_type: serviceType === 'all' ? undefined : serviceType,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    /**
     * Handle date filter changes from DateFilterPresets component.
     * Sends date_from and date_to to the server for filtering.
     * _Requirements: 5.1, 5.4, 5.5, 5.7_
     */
    const handleDateFilterChange = (dateFilter: DateFilterValue) => {
        router.get(
            window.location.pathname,
            {
                ...filters,
                date_from: dateFilter.from || undefined,
                date_to: dateFilter.to || undefined,
                date_preset: dateFilter.preset || undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    // Derive date filter value from filters prop
    const dateFilterValue: DateFilterValue = React.useMemo(() => {
        if (filters.date_from || filters.date_to) {
            return {
                from: filters.date_from,
                to: filters.date_to,
                preset: filters.date_preset || 'custom', // Use preset from URL or fallback to custom
            };
        }
        return {};
    }, [filters.date_from, filters.date_to, filters.date_preset]);

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
                    <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 transform text-muted-foreground" />
                    <Input
                        placeholder="Search by claim code, patient name..."
                        value={search}
                        onChange={(event) =>
                            handleSearchChange(event.target.value)
                        }
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
                        <option value="5">5</option>
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </div>

                {/* Status Filter */}
                <Select
                    value={filters.status || 'all'}
                    onValueChange={handleStatusFilter}
                >
                    <SelectTrigger className="w-[180px]">
                        <SelectValue placeholder="Filter by status" />
                    </SelectTrigger>
                    <SelectContent>
                        {statusOptions.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                {/* Service Type Filter (OPD/IPD) */}
                <Select
                    value={filters.service_type || 'all'}
                    onValueChange={handleServiceTypeFilter}
                >
                    <SelectTrigger className="w-[120px]">
                        <SelectValue placeholder="Type" />
                    </SelectTrigger>
                    <SelectContent>
                        {serviceTypeOptions.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                {/* Date Filter - Requirements: 5.1, 5.4, 5.5, 5.7 */}
                <DateFilterPresets
                    value={dateFilterValue}
                    onChange={handleDateFilterChange}
                />

                {/* Column Visibility */}
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline">
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
                                        {column.id.replace(/_/g, ' ')}
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
                            table.getRowModel().rows.map((row, index) => {
                                const isGrouped = shouldHighlightCccGroup(
                                    data as ClaimData[],
                                    index,
                                );
                                return (
                                    <TableRow
                                        key={row.id}
                                        data-state={
                                            row.getIsSelected() && 'selected'
                                        }
                                        className={cn(
                                            'hover:bg-muted/50',
                                            isGrouped &&
                                            'border-l-2 border-l-blue-400 bg-blue-50/50 dark:bg-blue-950/20',
                                        )}
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
                                );
                            })
                        ) : (
                            <TableRow>
                                <TableCell
                                    colSpan={columns.length}
                                    className="h-24 text-center"
                                >
                                    <div className="flex flex-col items-center gap-2">
                                        <ClipboardList className="h-8 w-8 text-muted-foreground" />
                                        <div>No claims found.</div>
                                        <div className="text-sm text-muted-foreground">
                                            {filters.search ||
                                                filters.status ||
                                                filters.date_from ||
                                                filters.date_to
                                                ? 'Try adjusting your filters.'
                                                : 'Insurance claims will appear here.'}
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
                            {pagination.total} claim(s)
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
