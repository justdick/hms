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
    Activity,
    AlertCircle,
    ChevronDown,
    Download,
    FileSpreadsheet,
    Filter,
    Pill,
    Search,
    Stethoscope,
    TestTube,
    Upload,
    X,
} from 'lucide-react';
import * as React from 'react';
import {
    PricingStatusFilter,
    type PricingStatusValue,
} from './components/PricingStatusFilter';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import type { InsurancePlan, PricingItem } from './Index';

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
    plan_id?: string | null;
    category?: string | null;
    search?: string | null;
    unmapped_only?: boolean;
    pricing_status?: string | null;
}

interface DataTableProps<TData, TValue> {
    columns: ColumnDef<TData, TValue>[];
    data: TData[];
    pagination: PaginationData;
    filters: Filters;
    categories: string[];
    isNhis: boolean;
    selectedPlan: InsurancePlan | null;
    selectedItems: PricingItem[];
    onBulkEdit: () => void;
    onExport: () => void;
    onImport: () => void;
    onDownloadTemplate: () => void;
}

const categoryLabels: Record<string, string> = {
    drugs: 'Drugs',
    lab: 'Lab Tests',
    consultation: 'Consultations',
    procedure: 'Procedures',
};

const categoryIcons: Record<string, React.ElementType> = {
    drugs: Pill,
    lab: TestTube,
    consultation: Stethoscope,
    procedure: Activity,
};

export function PricingDataTable<TData extends PricingItem, TValue>({
    columns,
    data,
    pagination,
    filters,
    categories,
    isNhis,
    selectedPlan,
    selectedItems,
    onBulkEdit,
    onExport,
    onImport,
    onDownloadTemplate,
}: DataTableProps<TData, TValue>) {
    const [sorting, setSorting] = React.useState<SortingState>([]);
    const [columnFilters, setColumnFilters] =
        React.useState<ColumnFiltersState>([]);
    const [columnVisibility, setColumnVisibility] =
        React.useState<VisibilityState>({});
    const [search, setSearch] = React.useState(filters.search || '');
    const [unmappedOnly, setUnmappedOnly] = React.useState(
        filters.unmapped_only || false,
    );
    const [pricingStatus, setPricingStatus] =
        React.useState<PricingStatusValue>(
            (filters.pricing_status as PricingStatusValue) || 'all',
        );

    const table = useReactTable({
        data,
        columns,
        onSortingChange: setSorting,
        onColumnFiltersChange: setColumnFilters,
        getCoreRowModel: getCoreRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        onColumnVisibilityChange: setColumnVisibility,
        manualPagination: true,
        pageCount: pagination.last_page,
        state: {
            sorting,
            columnFilters,
            columnVisibility,
            pagination: {
                pageIndex: pagination.current_page - 1,
                pageSize: pagination.per_page,
            },
        },
    });

    // Debounced server-side search (500ms for better UX)
    const debouncedSearch = useDebouncedCallback((value: string) => {
        router.get(
            '/admin/pricing-dashboard',
            { ...filters, search: value || undefined },
            { preserveState: true, preserveScroll: true },
        );
    }, 500);

    const handleSearchChange = (value: string) => {
        setSearch(value);
        debouncedSearch(value);
    };

    const handleCategoryChange = (category: string | null) => {
        router.get(
            '/admin/pricing-dashboard',
            { ...filters, category: category || undefined },
            { preserveState: true, preserveScroll: true },
        );
    };

    const handleUnmappedOnlyChange = (checked: boolean) => {
        setUnmappedOnly(checked);
        router.get(
            '/admin/pricing-dashboard',
            { ...filters, unmapped_only: checked || undefined },
            { preserveState: true, preserveScroll: true },
        );
    };

    const handlePricingStatusChange = (status: PricingStatusValue) => {
        setPricingStatus(status);
        router.get(
            '/admin/pricing-dashboard',
            {
                ...filters,
                pricing_status: status === 'all' ? undefined : status,
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
            '/admin/pricing-dashboard',
            { ...filters, per_page: perPage },
            { preserveState: true, preserveScroll: true },
        );
    };

    const handleClearFilters = () => {
        setSearch('');
        setUnmappedOnly(false);
        setPricingStatus('all');
        router.get(
            '/admin/pricing-dashboard',
            { plan_id: filters.plan_id },
            { preserveState: true, preserveScroll: true },
        );
    };

    const hasActiveFilters =
        filters.category ||
        filters.search ||
        filters.unmapped_only ||
        filters.pricing_status;

    // Find prev/next links from pagination
    const prevLink = pagination.links.find((link) =>
        link.label.includes('Previous'),
    );
    const nextLink = pagination.links.find((link) =>
        link.label.includes('Next'),
    );

    return (
        <div className="w-full space-y-4">
            {/* Top Action Buttons */}
            <div className="flex flex-wrap items-center justify-end gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    onClick={onDownloadTemplate}
                >
                    <Download className="mr-2 h-4 w-4" />
                    Template
                </Button>
                <Button variant="outline" size="sm" onClick={onImport}>
                    <Upload className="mr-2 h-4 w-4" />
                    Import
                </Button>
                <Button variant="outline" size="sm" onClick={onExport}>
                    <Download className="mr-2 h-4 w-4" />
                    Export
                </Button>
            </div>

            {/* Toolbar */}
            <div className="flex flex-wrap items-center gap-4">
                {/* Search */}
                <div className="relative max-w-sm flex-1">
                    <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 transform text-muted-foreground" />
                    <Input
                        placeholder="Search by name or code..."
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
                        <option value="100">100</option>
                    </select>
                </div>

                {/* Category Filter */}
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" className="border-dashed">
                            <Filter className="mr-2 h-4 w-4" />
                            Category
                            {filters.category && (
                                <Badge variant="secondary" className="ml-2">
                                    {categoryLabels[filters.category] ||
                                        filters.category}
                                </Badge>
                            )}
                            <ChevronDown className="ml-2 h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start">
                        <DropdownMenuLabel>
                            Filter by Category
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuCheckboxItem
                            checked={!filters.category}
                            onCheckedChange={() => handleCategoryChange(null)}
                        >
                            All Categories
                        </DropdownMenuCheckboxItem>
                        {categories.map((cat) => {
                            const Icon = categoryIcons[cat] || FileSpreadsheet;
                            return (
                                <DropdownMenuCheckboxItem
                                    key={cat}
                                    checked={filters.category === cat}
                                    onCheckedChange={() =>
                                        handleCategoryChange(cat)
                                    }
                                >
                                    <Icon className="mr-2 h-4 w-4" />
                                    {categoryLabels[cat] || cat}
                                </DropdownMenuCheckboxItem>
                            );
                        })}
                    </DropdownMenuContent>
                </DropdownMenu>

                {/* Pricing Status Filter */}
                <PricingStatusFilter
                    value={pricingStatus}
                    onChange={handlePricingStatusChange}
                />

                {/* Unmapped Only Filter (NHIS only) */}
                {isNhis && (
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="unmapped_only"
                            checked={unmappedOnly}
                            onCheckedChange={(checked) =>
                                handleUnmappedOnlyChange(checked === true)
                            }
                        />
                        <Label
                            htmlFor="unmapped_only"
                            className="cursor-pointer text-sm"
                        >
                            Unmapped only
                        </Label>
                    </div>
                )}

                {/* Clear Filters */}
                {hasActiveFilters && (
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={handleClearFilters}
                    >
                        <X className="mr-2 h-4 w-4" />
                        Clear
                    </Button>
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
                                        {column.id.replace(/_/g, ' ')}
                                    </DropdownMenuCheckboxItem>
                                );
                            })}
                    </DropdownMenuContent>
                </DropdownMenu>

                {/* Bulk Edit Button */}
                {selectedPlan && selectedItems.length > 0 && (
                    <Button size="sm" onClick={onBulkEdit} className="ml-auto">
                        Bulk Edit ({selectedItems.length})
                    </Button>
                )}
            </div>

            {/* Table */}
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
                                    className={`hover:bg-muted/50 ${
                                        isNhis && !row.original.is_mapped
                                            ? 'bg-yellow-50 dark:bg-yellow-950/20'
                                            : ''
                                    }`}
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
                                        <AlertCircle className="h-8 w-8 text-muted-foreground" />
                                        <div>No items found.</div>
                                        <div className="text-sm text-muted-foreground">
                                            Try adjusting your filters or search
                                            criteria.
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
                            {pagination.total} item(s)
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
                        .slice(0, 7) // Limit visible page numbers
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
