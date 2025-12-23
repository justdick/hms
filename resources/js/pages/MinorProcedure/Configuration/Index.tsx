import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { StatCard } from '@/components/ui/stat-card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import {
    ColumnDef,
    ColumnFiltersState,
    SortingState,
    flexRender,
    getCoreRowModel,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    useReactTable,
} from '@tanstack/react-table';
import {
    ArrowLeft,
    ArrowUpDown,
    Bandage,
    CheckCircle,
    Download,
    Edit,
    Plus,
    Search,
    Stethoscope,
    Trash2,
    Upload,
} from 'lucide-react';
import * as React from 'react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import ProcedureTypeModal from './ProcedureTypeModal';

interface ProcedureType {
    id: number;
    name: string;
    code: string;
    category: string;
    type: 'minor' | 'major';
    description: string | null;
    price: number;
    is_active: boolean;
}

interface Props {
    procedureTypes: ProcedureType[];
    categories: string[];
}

// Category DataTable Component
function CategoryDataTable({
    data,
    onEdit,
    onDelete,
}: {
    data: ProcedureType[];
    onEdit: (type: ProcedureType) => void;
    onDelete: (type: ProcedureType) => void;
}) {
    const [sorting, setSorting] = useState<SortingState>([]);
    const [columnFilters, setColumnFilters] = useState<ColumnFiltersState>([]);
    const [globalFilter, setGlobalFilter] = useState('');

    const columns: ColumnDef<ProcedureType>[] = [
        {
            accessorKey: 'name',
            header: ({ column }) => (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                    className="-ml-4"
                >
                    Name
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            ),
            cell: ({ row }) => (
                <span className="font-medium">{row.getValue('name')}</span>
            ),
        },
        {
            accessorKey: 'code',
            header: 'Code',
            cell: ({ row }) => (
                <code className="rounded bg-muted px-2 py-1 text-xs">
                    {row.getValue('code')}
                </code>
            ),
        },
        {
            accessorKey: 'type',
            header: 'Type',
            cell: ({ row }) => {
                const type = row.getValue('type') as string;
                return (
                    <Badge
                        variant="outline"
                        className={
                            type === 'major'
                                ? 'border-purple-200 bg-purple-100 text-purple-700 dark:bg-purple-900/20 dark:text-purple-400'
                                : 'border-blue-200 bg-blue-100 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400'
                        }
                    >
                        {type === 'major' ? 'Major' : 'Minor'}
                    </Badge>
                );
            },
        },
        {
            accessorKey: 'description',
            header: 'Description',
            cell: ({ row }) => (
                <span className="max-w-md truncate text-sm text-muted-foreground">
                    {row.getValue('description') || 'No description'}
                </span>
            ),
        },
        {
            accessorKey: 'price',
            header: ({ column }) => (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                    className="-mr-4 ml-auto"
                >
                    Price (GH₵)
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            ),
            cell: ({ row }) => (
                <span className="block text-right font-mono">
                    {Number(row.getValue('price')).toFixed(2)}
                </span>
            ),
        },
        {
            accessorKey: 'is_active',
            header: 'Status',
            cell: ({ row }) => {
                const isActive = row.getValue('is_active') as boolean;
                return isActive ? (
                    <span className="inline-flex items-center rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-700 dark:bg-green-900/20 dark:text-green-400">
                        Active
                    </span>
                ) : (
                    <span className="inline-flex items-center rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-400">
                        Inactive
                    </span>
                );
            },
        },
        {
            id: 'actions',
            header: () => <span className="sr-only">Actions</span>,
            cell: ({ row }) => (
                <div className="flex justify-end gap-2">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => onEdit(row.original)}
                    >
                        <Edit className="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => onDelete(row.original)}
                        className="text-destructive hover:text-destructive"
                    >
                        <Trash2 className="h-4 w-4" />
                    </Button>
                </div>
            ),
        },
    ];

    const table = useReactTable({
        data,
        columns,
        onSortingChange: setSorting,
        onColumnFiltersChange: setColumnFilters,
        onGlobalFilterChange: setGlobalFilter,
        getCoreRowModel: getCoreRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        state: {
            sorting,
            columnFilters,
            globalFilter,
        },
        initialState: {
            pagination: {
                pageSize: 10,
            },
        },
    });

    return (
        <div className="space-y-4">
            <div className="flex items-center gap-4">
                <div className="relative max-w-sm flex-1">
                    <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        placeholder="Search procedures..."
                        value={globalFilter ?? ''}
                        onChange={(e) => setGlobalFilter(e.target.value)}
                        className="pl-10"
                    />
                </div>
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                    {table.getFilteredRowModel().rows.length} of {data.length}{' '}
                    procedures
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
                                    data-state={
                                        row.getIsSelected() && 'selected'
                                    }
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
                                    No procedures found.
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>

            {/* Pagination */}
            {table.getPageCount() > 1 && (
                <div className="flex items-center justify-between">
                    <div className="text-sm text-muted-foreground">
                        Page {table.getState().pagination.pageIndex + 1} of{' '}
                        {table.getPageCount()}
                    </div>
                    <div className="flex items-center gap-2">
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
            )}
        </div>
    );
}

export default function MinorProcedureConfigurationIndex({
    procedureTypes,
    categories,
}: Props) {
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [editingType, setEditingType] = useState<ProcedureType | null>(null);
    const [activeTab, setActiveTab] = useState<'all' | 'minor' | 'major'>(
        'all',
    );
    const [isImporting, setIsImporting] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const handleImport = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;

        setIsImporting(true);
        router.post(
            '/admin/procedure-types/import',
            { file },
            {
                forceFormData: true,
                onSuccess: () => {
                    toast.success('Import completed');
                    if (fileInputRef.current) {
                        fileInputRef.current.value = '';
                    }
                },
                onError: (errors) => {
                    toast.error(errors.file || errors.error || 'Import failed');
                },
                onFinish: () => {
                    setIsImporting(false);
                },
            },
        );
    };

    const handleDelete = (procedureType: ProcedureType) => {
        if (
            !confirm(
                `Are you sure you want to delete "${procedureType.name}"? This action cannot be undone.`,
            )
        ) {
            return;
        }

        router.delete(`/minor-procedures/types/${procedureType.id}`, {
            onSuccess: () => {
                toast.success('Procedure type deleted successfully');
            },
            onError: (errors) => {
                toast.error(errors.error || 'Failed to delete procedure type');
            },
        });
    };

    const filteredProcedures = procedureTypes.filter((type) => {
        if (activeTab === 'all') return true;
        return type.type === activeTab;
    });

    const groupedByCategory = filteredProcedures.reduce(
        (acc, type) => {
            if (!acc[type.category]) {
                acc[type.category] = [];
            }
            acc[type.category].push(type);
            return acc;
        },
        {} as Record<string, ProcedureType[]>,
    );

    const minorCount = procedureTypes.filter((t) => t.type === 'minor').length;
    const majorCount = procedureTypes.filter((t) => t.type === 'major').length;

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Minor Procedures', href: '/minor-procedures' },
                { title: 'Configuration', href: '/minor-procedures/types' },
            ]}
        >
            <Head title="Procedure Types Configuration" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="ghost" size="sm" asChild>
                            <Link href="/minor-procedures">
                                <ArrowLeft className="mr-1 h-4 w-4" />
                                Back to Minor Procedures
                            </Link>
                        </Button>
                        <div>
                            <h1 className="flex items-center gap-2 text-2xl font-bold">
                                <Bandage className="h-6 w-6" />
                                Procedure Types Configuration
                            </h1>
                            <p className="text-muted-foreground">
                                Manage procedure types and pricing
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button variant="outline" asChild>
                            <a href="/admin/procedure-types/template">
                                <Download className="mr-2 h-4 w-4" />
                                Template
                            </a>
                        </Button>
                        <input
                            ref={fileInputRef}
                            type="file"
                            accept=".csv,.xlsx,.xls"
                            onChange={handleImport}
                            className="hidden"
                            id="procedure-import"
                        />
                        <Button
                            variant="outline"
                            onClick={() => fileInputRef.current?.click()}
                            disabled={isImporting}
                        >
                            <Upload className="mr-2 h-4 w-4" />
                            {isImporting ? 'Importing...' : 'Import'}
                        </Button>
                        <Button onClick={() => setShowCreateModal(true)}>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Procedure Type
                        </Button>
                    </div>
                </div>

                {/* Stats Summary */}
                <div className="grid gap-4 md:grid-cols-4">
                    <StatCard
                        icon={<Bandage className="h-4 w-4" />}
                        label="Total Types"
                        value={procedureTypes.length}
                        variant="default"
                    />
                    <StatCard
                        icon={<Bandage className="h-4 w-4" />}
                        label="Minor Procedures"
                        value={minorCount}
                        variant="info"
                    />
                    <StatCard
                        icon={<Stethoscope className="h-4 w-4" />}
                        label="Major Procedures"
                        value={majorCount}
                        variant="warning"
                    />
                    <StatCard
                        icon={<CheckCircle className="h-4 w-4" />}
                        label="Active"
                        value={procedureTypes.filter((t) => t.is_active).length}
                        variant="success"
                    />
                </div>

                {/* Procedure Types Tabs */}
                <Tabs
                    value={activeTab}
                    onValueChange={(v) =>
                        setActiveTab(v as 'all' | 'minor' | 'major')
                    }
                >
                    <TabsList>
                        <TabsTrigger value="all">
                            All Procedures ({procedureTypes.length})
                        </TabsTrigger>
                        <TabsTrigger value="minor">
                            Minor ({minorCount})
                        </TabsTrigger>
                        <TabsTrigger value="major">
                            Major/Theatre ({majorCount})
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value={activeTab} className="mt-6 space-y-6">
                        {/* Procedure Types by Category */}
                        {Object.entries(groupedByCategory)
                            .sort(([a], [b]) => a.localeCompare(b))
                            .map(([category, types]) => (
                                <Card key={category}>
                                    <CardHeader>
                                        <CardTitle className="capitalize">
                                            {category.replace(/_/g, ' ')}
                                        </CardTitle>
                                        <CardDescription>
                                            {types.length} procedure
                                            {types.length !== 1 ? 's' : ''}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <CategoryDataTable
                                            data={types}
                                            onEdit={setEditingType}
                                            onDelete={handleDelete}
                                        />
                                    </CardContent>
                                </Card>
                            ))}

                        {filteredProcedures.length === 0 && (
                            <Card>
                                <CardContent className="flex flex-col items-center justify-center py-12">
                                    <Bandage className="mb-4 h-12 w-12 text-muted-foreground" />
                                    <h3 className="mb-2 text-lg font-semibold">
                                        No Procedure Types
                                    </h3>
                                    <p className="mb-4 text-center text-sm text-muted-foreground">
                                        {activeTab === 'all'
                                            ? 'Get started by adding your first procedure type'
                                            : `No ${activeTab} procedures found`}
                                    </p>
                                    <Button
                                        onClick={() => setShowCreateModal(true)}
                                    >
                                        <Plus className="mr-2 h-4 w-4" />
                                        Add Procedure Type
                                    </Button>
                                </CardContent>
                            </Card>
                        )}
                    </TabsContent>
                </Tabs>
            </div>

            <ProcedureTypeModal
                open={showCreateModal}
                onClose={() => setShowCreateModal(false)}
                categories={categories}
            />

            {editingType && (
                <ProcedureTypeModal
                    open={!!editingType}
                    onClose={() => setEditingType(null)}
                    categories={categories}
                    editingType={editingType}
                />
            )}
        </AppLayout>
    );
}
