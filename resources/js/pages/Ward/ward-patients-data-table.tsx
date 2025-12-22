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
import { Bed, ChevronDown, Filter, Search } from 'lucide-react';
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
import { TooltipProvider } from '@/components/ui/tooltip';
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

interface WardPatientsDataTableProps<TData, TValue> {
    columns: ColumnDef<TData, TValue>[];
    data: TData[];
    pagination: PaginationData;
    searchValue?: string;
    wardId: number;
    onBedAction?: (admission: TData, action: 'assign' | 'change') => void;
}

export function WardPatientsDataTable<TData, TValue>({
    columns,
    data,
    pagination,
    searchValue = '',
    wardId,
    onBedAction,
}: WardPatientsDataTableProps<TData, TValue>) {
    const [sorting, setSorting] = React.useState<SortingState>([]);
    const [columnFilters, setColumnFilters] =
        React.useState<ColumnFiltersState>([]);
    const [columnVisibility, setColumnVisibility] =
        React.useState<VisibilityState>({});
    const [rowSelection, setRowSelection] = React.useState({});
    const [search, setSearch] = React.useState(searchValue ?? '');

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
        meta: {
            onBedAction,
        },
    });

    // Debounced server-side search
    const debouncedSearch = useDebouncedCallback((value: string) => {
        router.get(
            window.location.pathname,
            { search: value || undefined },
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
            { per_page: perPage, search: search || undefined },
            { preserveState: true, preserveScroll: true },
        );
    };

    // Get unique statuses for filtering
    const statuses = React.useMemo(() => {
        const statusSet = new Set<string>();
        data.forEach((item: any) => {
            if (item.status) {
                statusSet.add(item.status);
            }
        });
        return Array.from(statusSet);
    }, [data]);

    // Find prev/next links from pagination
    const prevLink = pagination.links.find((link) =>
        link.label.includes('Previous'),
    );
    const nextLink = pagination.links.find((link) =>
        link.label.includes('Next'),
    );

    return (
        <TooltipProvider>
            <div className="w-full space-y-4">
                <div className="flex items-center gap-4">
                    <div className="relative max-w-sm flex-1">
                        <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 transform text-muted-foreground" />
                        <Input
                            placeholder="Search patients by name, admission #..."
                            value={search}
                            onChange={(event) =>
                                handleSearchChange(event.target.value)
                            }
                            className="pl-10"
                        />
                    </div>

                    {/* Per Page Selector */}
                    <div className="flex items-center gap-2">
                        <span className="text-sm text-muted-foreground">
                            Show
                        </span>
                        <select
                            value={pagination.per_page}
                            onChange={(e) =>
                                handlePerPageChange(e.target.value)
                            }
                            className="h-8 rounded-md border border-input bg-background px-2 text-sm"
                        >
                            <option value="5">5</option>
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>

                    {/* Status Filter */}
                    {statuses.length > 0 && (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="outline"
                                    className="border-dashed"
                                >
                                    <Filter className="mr-2 h-4 w-4" />
                                    Status
                                    <ChevronDown className="ml-2 h-4 w-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start">
                                <DropdownMenuLabel>
                                    Filter by Status
                                </DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                {statuses.map((status) => (
                                    <DropdownMenuCheckboxItem
                                        key={status}
                                        checked={
                                            (
                                                table
                                                    .getColumn('status')
                                                    ?.getFilterValue() as string[]
                                            )?.includes(status) ?? false
                                        }
                                        onCheckedChange={(checked) => {
                                            const currentFilter =
                                                (table
                                                    .getColumn('status')
                                                    ?.getFilterValue() as string[]) ||
                                                [];
                                            if (checked) {
                                                table
                                                    .getColumn('status')
                                                    ?.setFilterValue([
                                                        ...currentFilter,
                                                        status,
                                                    ]);
                                            } else {
                                                table
                                                    .getColumn('status')
                                                    ?.setFilterValue(
                                                        currentFilter.filter(
                                                            (s) => s !== status,
                                                        ),
                                                    );
                                            }
                                        }}
                                    >
                                        {status
                                            .replace('_', ' ')
                                            .charAt(0)
                                            .toUpperCase() +
                                            status.replace('_', ' ').slice(1)}
                                    </DropdownMenuCheckboxItem>
                                ))}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}

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
                                            {column.id.replace('_', ' ')}
                                        </DropdownMenuCheckboxItem>
                                    );
                                })}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <div className="rounded-md border dark:border-gray-700">
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
                                                          header.column
                                                              .columnDef.header,
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
                                table.getRowModel().rows.map((row) => {
                                    const admission = row.original as any;
                                    const hasOverdueVitals =
                                        admission.vitals_schedule &&
                                        calculateVitalsStatus(
                                            admission.vitals_schedule,
                                        ) === 'overdue';

                                    return (
                                        <TableRow
                                            key={row.id}
                                            data-state={
                                                row.getIsSelected() &&
                                                'selected'
                                            }
                                            className={`cursor-pointer hover:bg-muted/50 ${hasOverdueVitals ? 'bg-red-50 dark:bg-red-950/20' : ''}`}
                                        >
                                            {row
                                                .getVisibleCells()
                                                .map((cell) => (
                                                    <TableCell key={cell.id}>
                                                        {flexRender(
                                                            cell.column
                                                                .columnDef.cell,
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
                                            <Bed className="h-8 w-8 text-muted-foreground" />
                                            <div>
                                                No patients currently admitted
                                                to this ward
                                            </div>
                                            <div className="text-sm text-muted-foreground">
                                                Patients will appear here once
                                                admitted.
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
                                {pagination.total} patient(s)
                            </>
                        ) : (
                            <>No results</>
                        )}
                    </div>
                    <div className="flex items-center gap-1">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                handlePageChange(prevLink?.url ?? null)
                            }
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
                            onClick={() =>
                                handlePageChange(nextLink?.url ?? null)
                            }
                            disabled={!nextLink?.url}
                        >
                            Next
                        </Button>
                    </div>
                </div>
            </div>
        </TooltipProvider>
    );
}

/**
 * Calculate vitals status from schedule
 */
function calculateVitalsStatus(schedule: {
    next_due_at: string;
}): 'upcoming' | 'due' | 'overdue' {
    const now = new Date();
    const nextDue = new Date(schedule.next_due_at);
    const diffMinutes = Math.floor(
        (nextDue.getTime() - now.getTime()) / (1000 * 60),
    );

    const GRACE_PERIOD_MINUTES = 15;

    if (diffMinutes > GRACE_PERIOD_MINUTES) {
        return 'upcoming';
    } else if (diffMinutes >= -GRACE_PERIOD_MINUTES) {
        return 'due';
    } else {
        return 'overdue';
    }
}
