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
import {
    ChevronDown,
    Download,
    Filter,
    Link2Off,
    Plus,
    Search,
    Upload,
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
import { router } from '@inertiajs/react';
import { useDebouncedCallback } from 'use-debounce';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginationMeta {
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
    links: PaginationLink[];
}

interface PaginationData {
    data: any[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: PaginationMeta;
}

interface DataTableProps<TData, TValue> {
    columns: ColumnDef<TData, TValue>[];
    data: TData[];
    pagination: PaginationData;
    onAddClick: () => void;
    onImportClick: () => void;
    searchValue?: string;
    itemTypeFilter?: string;
    itemTypes: string[];
}

const itemTypeLabels: Record<string, string> = {
    drug: 'Drug',
    lab_service: 'Lab Service',
    procedure: 'Procedure',
    consumable: 'Consumable',
};

export function NhisMappingsDataTable<TData, TValue>({
    columns,
    data,
    pagination,
    onAddClick,
    onImportClick,
    searchValue = '',
    itemTypeFilter = '',
    itemTypes,
}: DataTableProps<TData, TValue>) {
    const [sorting, setSorting] = React.useState<SortingState>([]);
    const [columnFilters, setColumnFilters] =
        React.useState<ColumnFiltersState>([]);
    const [columnVisibility, setColumnVisibility] =
        React.useState<VisibilityState>({});
    const [rowSelection, setRowSelection] = React.useState({});
    const [search, setSearch] = React.useState(searchValue);

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
        pageCount: pagination.meta?.last_page || 1,
        state: {
            sorting,
            columnFilters,
            columnVisibility,
            rowSelection,
            pagination: {
                pageIndex: (pagination.meta?.current_page || 1) - 1,
                pageSize: pagination.meta?.per_page || 20,
            },
        },
    });

    // Debounced server-side search
    const debouncedSearch = useDebouncedCallback((value: string) => {
        router.get(
            window.location.pathname,
            {
                search: value || undefined,
                item_type: itemTypeFilter || undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    }, 300);

    const handleSearchChange = (value: string) => {
        setSearch(value);
        debouncedSearch(value);
    };

    const handleItemTypeFilter = (type: string) => {
        router.get(
            window.location.pathname,
            { search: search || undefined, item_type: type || undefined },
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
            window.location.pathname,
            {
                per_page: perPage,
                search: search || undefined,
                item_type: itemTypeFilter || undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    // Get prev/next links from pagination (Laravel resource collection format)
    const prevUrl = pagination.links?.prev;
    const nextUrl = pagination.links?.next;
    const pageLinks = pagination.meta?.links || [];

    return (
        <div className="w-full space-y-4">
            <div className="flex flex-wrap items-center gap-4">
                <div className="relative max-w-sm flex-1">
                    <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 transform text-muted-foreground" />
                    <Input
                        placeholder="Search by item code, NHIS code, name..."
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
                        value={pagination.meta?.per_page || 20}
                        onChange={(e) => handlePerPageChange(e.target.value)}
                        className="h-8 rounded-md border border-input bg-background px-2 text-sm"
                    >
                        <option value="10">10</option>
                        <option value="20">20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>

                {/* Item Type Filter */}
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" className="border-dashed">
                            <Filter className="mr-2 h-4 w-4" />
                            {itemTypeFilter
                                ? itemTypeLabels[itemTypeFilter] ||
                                  itemTypeFilter
                                : 'Item Type'}
                            <ChevronDown className="ml-2 h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start">
                        <DropdownMenuLabel>
                            Filter by Item Type
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuCheckboxItem
                            checked={!itemTypeFilter}
                            onCheckedChange={() => handleItemTypeFilter('')}
                        >
                            All Types
                        </DropdownMenuCheckboxItem>
                        {itemTypes.map((type) => (
                            <DropdownMenuCheckboxItem
                                key={type}
                                checked={itemTypeFilter === type}
                                onCheckedChange={() =>
                                    handleItemTypeFilter(type)
                                }
                            >
                                {itemTypeLabels[type] || type}
                            </DropdownMenuCheckboxItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>

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

                {/* Action Buttons */}
                <div className="ml-auto flex items-center gap-2">
                    <Button variant="outline" asChild>
                        <a href="/admin/nhis-mappings/unmapped/export">
                            <Download className="mr-2 h-4 w-4" />
                            Export Unmapped
                        </a>
                    </Button>
                    <Button variant="outline" asChild>
                        <a href="/admin/nhis-mappings/mapped/export">
                            <Download className="mr-2 h-4 w-4" />
                            Export Mapped
                        </a>
                    </Button>
                    <Button variant="outline" onClick={onImportClick}>
                        <Upload className="mr-2 h-4 w-4" />
                        Import
                    </Button>
                    <Button onClick={onAddClick}>
                        <Plus className="mr-2 h-4 w-4" />
                        Add Mapping
                    </Button>
                </div>
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
                                        <Link2Off className="h-8 w-8 text-muted-foreground" />
                                        <div>No mappings found.</div>
                                        <div className="text-sm text-muted-foreground">
                                            {search || itemTypeFilter
                                                ? 'Try adjusting your filters.'
                                                : 'Get started by adding your first NHIS mapping.'}
                                        </div>
                                        {!search && !itemTypeFilter && (
                                            <div className="mt-2 flex gap-2">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={onImportClick}
                                                >
                                                    <Upload className="mr-2 h-4 w-4" />
                                                    Import
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    onClick={onAddClick}
                                                >
                                                    <Plus className="mr-2 h-4 w-4" />
                                                    Add Mapping
                                                </Button>
                                            </div>
                                        )}
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
                    {pagination.meta?.from && pagination.meta?.to ? (
                        <>
                            Showing {pagination.meta.from} to{' '}
                            {pagination.meta.to} of {pagination.meta.total}{' '}
                            mapping(s)
                        </>
                    ) : (
                        <>No results</>
                    )}
                </div>
                <div className="flex items-center gap-1">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => handlePageChange(prevUrl)}
                        disabled={!prevUrl}
                    >
                        Previous
                    </Button>
                    {pageLinks
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
                        onClick={() => handlePageChange(nextUrl)}
                        disabled={!nextUrl}
                    >
                        Next
                    </Button>
                </div>
            </div>
        </div>
    );
}
