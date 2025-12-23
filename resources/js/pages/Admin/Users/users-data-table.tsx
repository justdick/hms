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
    Filter,
    Plus,
    Search,
    Shield,
    UserCog,
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
import { Link, router } from '@inertiajs/react';
import { useDebouncedCallback } from 'use-debounce';
import { Department, Role } from './users-columns';

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

interface DataTableProps<TData, TValue> {
    columns: ColumnDef<TData, TValue>[];
    data: TData[];
    pagination: PaginationData;
    roles: Role[];
    departments: Department[];
    filters: {
        search?: string;
        role?: string;
        department?: string;
    };
}

export function UsersDataTable<TData, TValue>({
    columns,
    data,
    pagination,
    roles,
    departments,
    filters,
}: DataTableProps<TData, TValue>) {
    const [sorting, setSorting] = React.useState<SortingState>([]);
    const [columnFilters, setColumnFilters] =
        React.useState<ColumnFiltersState>([]);
    const [columnVisibility, setColumnVisibility] =
        React.useState<VisibilityState>({});
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

    // Debounced server-side search
    const debouncedSearch = useDebouncedCallback((value: string) => {
        router.get(
            window.location.pathname,
            {
                search: value || undefined,
                role: filters.role || undefined,
                department: filters.department || undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    }, 300);

    const handleSearchChange = (value: string) => {
        setSearch(value);
        debouncedSearch(value);
    };

    const handleRoleFilterChange = (role: string) => {
        router.get(
            window.location.pathname,
            {
                search: search || undefined,
                role: role || undefined,
                department: filters.department || undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const handleDepartmentFilterChange = (department: string) => {
        router.get(
            window.location.pathname,
            {
                search: search || undefined,
                role: filters.role || undefined,
                department: department || undefined,
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
            window.location.pathname,
            {
                per_page: perPage,
                search: search || undefined,
                role: filters.role || undefined,
                department: filters.department || undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const prevLink = pagination.links.find((link) =>
        link.label.includes('Previous'),
    );
    const nextLink = pagination.links.find((link) =>
        link.label.includes('Next'),
    );

    return (
        <div className="w-full space-y-4">
            <div className="flex flex-wrap items-center gap-4">
                {/* Search */}
                <div className="relative max-w-sm flex-1">
                    <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        placeholder="Search by name or username..."
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
                        <option value="5">5</option>
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </div>

                {/* Role Filter */}
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" className="border-dashed">
                            <Filter className="mr-2 h-4 w-4" />
                            Role
                            {filters.role && (
                                <span className="ml-2 rounded bg-primary/20 px-1.5 py-0.5 text-xs">
                                    {filters.role}
                                </span>
                            )}
                            <ChevronDown className="ml-2 h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start">
                        <DropdownMenuLabel>Filter by Role</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuCheckboxItem
                            checked={!filters.role}
                            onCheckedChange={() => handleRoleFilterChange('')}
                        >
                            All Roles
                        </DropdownMenuCheckboxItem>
                        {roles.map((role) => (
                            <DropdownMenuCheckboxItem
                                key={role.id}
                                checked={filters.role === role.name}
                                onCheckedChange={() =>
                                    handleRoleFilterChange(role.name)
                                }
                            >
                                {role.name}
                            </DropdownMenuCheckboxItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>

                {/* Department Filter */}
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" className="border-dashed">
                            <Filter className="mr-2 h-4 w-4" />
                            Department
                            {filters.department && (
                                <span className="ml-2 rounded bg-primary/20 px-1.5 py-0.5 text-xs">
                                    {
                                        departments.find(
                                            (d) =>
                                                d.id.toString() ===
                                                filters.department,
                                        )?.name
                                    }
                                </span>
                            )}
                            <ChevronDown className="ml-2 h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start">
                        <DropdownMenuLabel>
                            Filter by Department
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuCheckboxItem
                            checked={!filters.department}
                            onCheckedChange={() =>
                                handleDepartmentFilterChange('')
                            }
                        >
                            All Departments
                        </DropdownMenuCheckboxItem>
                        {departments.map((dept) => (
                            <DropdownMenuCheckboxItem
                                key={dept.id}
                                checked={
                                    filters.department === dept.id.toString()
                                }
                                onCheckedChange={() =>
                                    handleDepartmentFilterChange(
                                        dept.id.toString(),
                                    )
                                }
                            >
                                {dept.name}
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
                            .map((column) => (
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
                            ))}
                    </DropdownMenuContent>
                </DropdownMenu>

                {/* Action Buttons */}
                <div className="ml-auto flex gap-2">
                    <Link href="/admin/roles">
                        <Button variant="outline">
                            <Shield className="mr-2 h-4 w-4" />
                            Manage Roles
                        </Button>
                    </Link>
                    <Link href="/admin/users/create">
                        <Button>
                            <Plus className="mr-2 h-4 w-4" />
                            Add User
                        </Button>
                    </Link>
                </div>
            </div>

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
                                <TableRow
                                    key={row.id}
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
                                        <UserCog className="h-8 w-8 text-muted-foreground" />
                                        <div>No users found.</div>
                                        <div className="text-sm text-muted-foreground">
                                            {filters.search ||
                                            filters.role ||
                                            filters.department
                                                ? 'Try adjusting your search or filters.'
                                                : 'Get started by adding your first user.'}
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
                            {pagination.total} user(s)
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
