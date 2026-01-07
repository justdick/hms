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
    AlertCircle,
    ArrowUpDown,
    Calendar,
    Check,
    ChevronDown,
    Eye,
    FlaskConical,
    Search,
    Trash2,
} from 'lucide-react';
import * as React from 'react';

import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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

interface LabService {
    id: number;
    name: string;
    code: string;
    category: string;
    price: number | null;
    sample_type: string;
    test_parameters?: {
        parameters: Array<{
            name: string;
            label: string;
            type: string;
            unit?: string;
            normal_range?: {
                min?: number;
                max?: number;
            };
        }>;
    };
}

interface LabOrder {
    id: number;
    lab_service: LabService;
    status:
        | 'ordered'
        | 'sample_collected'
        | 'in_progress'
        | 'completed'
        | 'cancelled'
        | 'external_referral';
    priority: 'routine' | 'urgent' | 'stat';
    special_instructions?: string;
    ordered_at: string;
    sample_collected_at?: string;
    result_entered_at?: string;
    result_values?: any;
    result_notes?: string;
    ordered_by?: {
        id: number;
        name: string;
    };
}

interface ConsultationLabOrdersTableProps {
    labOrders: LabOrder[];
    consultationId: number;
    canDelete?: boolean;
}

const formatDateTime = (dateString: string) => {
    return new Date(dateString).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getStatusBadgeClasses = (status: string) => {
    switch (status) {
        case 'completed':
            return 'bg-green-100 text-green-800 hover:bg-green-100';
        case 'in_progress':
            return 'bg-orange-100 text-orange-800 hover:bg-orange-100';
        case 'sample_collected':
            return 'bg-yellow-100 text-yellow-800 hover:bg-yellow-100';
        case 'cancelled':
            return 'bg-red-100 text-red-800 hover:bg-red-100';
        default:
            return 'bg-blue-100 text-blue-800 hover:bg-blue-100';
    }
};

const LabResultsModal = ({
    order,
    open,
    onOpenChange,
}: {
    order: LabOrder;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) => {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[80vh] max-w-3xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="text-2xl">
                        {order.lab_service.name}
                    </DialogTitle>
                    <DialogDescription>
                        {order.lab_service.code} • {order.lab_service.category}
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-6">
                    {/* Test Information */}
                    <div className="grid grid-cols-2 gap-4 rounded-lg bg-muted/50 p-4">
                        <div className="flex items-start gap-2">
                            <Calendar className="mt-0.5 h-4 w-4 text-muted-foreground" />
                            <div className="space-y-1">
                                <p className="text-xs text-muted-foreground">
                                    Ordered
                                </p>
                                <p className="text-sm font-medium">
                                    {formatDateTime(order.ordered_at)}
                                </p>
                                {order.ordered_by && (
                                    <p className="text-xs text-muted-foreground">
                                        by {order.ordered_by.name}
                                    </p>
                                )}
                            </div>
                        </div>

                        {order.sample_collected_at && (
                            <div className="flex items-start gap-2">
                                <FlaskConical className="mt-0.5 h-4 w-4 text-muted-foreground" />
                                <div className="space-y-1">
                                    <p className="text-xs text-muted-foreground">
                                        Sample Collected
                                    </p>
                                    <p className="text-sm font-medium">
                                        {formatDateTime(
                                            order.sample_collected_at,
                                        )}
                                    </p>
                                </div>
                            </div>
                        )}

                        {order.result_entered_at && (
                            <div className="flex items-start gap-2">
                                <Check className="mt-0.5 h-4 w-4 text-green-600" />
                                <div className="space-y-1">
                                    <p className="text-xs text-muted-foreground">
                                        Results Entered
                                    </p>
                                    <p className="text-sm font-medium">
                                        {formatDateTime(
                                            order.result_entered_at,
                                        )}
                                    </p>
                                </div>
                            </div>
                        )}

                        <div className="flex items-start gap-2">
                            <div className="space-y-1">
                                <p className="text-xs text-muted-foreground">
                                    Priority
                                </p>
                                <Badge
                                    variant={
                                        order.priority !== 'routine'
                                            ? 'destructive'
                                            : 'outline'
                                    }
                                >
                                    {order.priority.toUpperCase()}
                                </Badge>
                            </div>
                        </div>
                    </div>

                    {/* Special Instructions */}
                    {order.special_instructions && (
                        <div className="border-l-4 border-yellow-400 bg-yellow-50 p-3 dark:border-yellow-600 dark:bg-yellow-950">
                            <p className="mb-1 text-sm font-semibold text-yellow-900 dark:text-yellow-200">
                                Special Instructions
                            </p>
                            <p className="text-sm text-yellow-800 dark:text-yellow-300">
                                {order.special_instructions}
                            </p>
                        </div>
                    )}

                    {/* Results Section */}
                    {order.status === 'completed' && order.result_values && (
                        <div>
                            <h3 className="mb-4 flex items-center gap-2 text-lg font-semibold">
                                <Check className="h-5 w-5 text-green-600 dark:text-green-500" />
                                Test Results
                            </h3>

                            <div className="overflow-hidden rounded-lg border dark:border-gray-700">
                                <Table>
                                    <TableHeader>
                                        <TableRow className="bg-muted/50 dark:bg-gray-800/50">
                                            <TableHead className="font-semibold text-gray-900 dark:text-gray-100">
                                                Parameter
                                            </TableHead>
                                            <TableHead className="font-semibold text-gray-900 dark:text-gray-100">
                                                Value
                                            </TableHead>
                                            <TableHead className="font-semibold text-gray-900 dark:text-gray-100">
                                                Reference Range
                                            </TableHead>
                                            <TableHead className="font-semibold text-gray-900 dark:text-gray-100">
                                                Status
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {Object.entries(
                                            order.result_values,
                                        ).map(
                                            ([key, result]: [string, any]) => {
                                                const isObject =
                                                    typeof result ===
                                                        'object' &&
                                                    result !== null;
                                                const value = isObject
                                                    ? result.value
                                                    : result;
                                                const unit = isObject
                                                    ? result.unit
                                                    : '';
                                                
                                                // Try to get range from result, or fall back to test parameters
                                                let range = isObject ? result.range : '';
                                                let flag = isObject ? result.flag : 'normal';
                                                
                                                // If no range in result, try to get from test parameters
                                                if (!range && order.lab_service.test_parameters?.parameters) {
                                                    const param = order.lab_service.test_parameters.parameters.find(
                                                        p => p.name === key || p.name.toLowerCase() === key.toLowerCase()
                                                    );
                                                    if (param?.normal_range) {
                                                        const { min, max } = param.normal_range;
                                                        if (min !== undefined && max !== undefined) {
                                                            range = `${min}-${max}`;
                                                        } else if (min !== undefined) {
                                                            range = `>${min}`;
                                                        } else if (max !== undefined) {
                                                            range = `<${max}`;
                                                        }
                                                        
                                                        // Also calculate flag if not set
                                                        if (flag === 'normal' && param.type === 'numeric') {
                                                            const numValue = parseFloat(String(value));
                                                            if (!isNaN(numValue)) {
                                                                if (min !== undefined && numValue < min) {
                                                                    flag = 'low';
                                                                } else if (max !== undefined && numValue > max) {
                                                                    flag = 'high';
                                                                }
                                                            }
                                                        }
                                                    }
                                                }

                                                const getValueColor = (
                                                    flag?: string,
                                                ) => {
                                                    switch (flag) {
                                                        case 'high':
                                                        case 'critical':
                                                            return 'text-red-600 dark:text-red-400 font-semibold';
                                                        case 'low':
                                                            return 'text-orange-600 dark:text-orange-400 font-semibold';
                                                        default:
                                                            return 'text-gray-900 dark:text-gray-100';
                                                    }
                                                };

                                                return (
                                                    <TableRow
                                                        key={key}
                                                        className="hover:bg-muted/30 dark:hover:bg-gray-800/30"
                                                    >
                                                        <TableCell className="font-medium text-gray-900 dark:text-gray-100">
                                                            {key.replace(
                                                                /_/g,
                                                                ' ',
                                                            )}
                                                        </TableCell>
                                                        <TableCell
                                                            className={getValueColor(
                                                                flag,
                                                            )}
                                                        >
                                                            {value}{' '}
                                                            {unit && (
                                                                <span className="font-normal text-gray-500 dark:text-gray-400">
                                                                    {unit}
                                                                </span>
                                                            )}
                                                        </TableCell>
                                                        <TableCell className="text-gray-600 dark:text-gray-400">
                                                            {range || '-'}
                                                        </TableCell>
                                                        <TableCell>
                                                            {flag &&
                                                            flag !==
                                                                'normal' ? (
                                                                <div className="flex items-center gap-1">
                                                                    <AlertCircle className="h-3 w-3 text-red-500 dark:text-red-400" />
                                                                    <span
                                                                        className={getValueColor(
                                                                            flag,
                                                                        )}
                                                                    >
                                                                        {flag.toUpperCase()}
                                                                    </span>
                                                                </div>
                                                            ) : (
                                                                <span className="text-green-600 dark:text-green-400">
                                                                    Normal
                                                                </span>
                                                            )}
                                                        </TableCell>
                                                    </TableRow>
                                                );
                                            },
                                        )}
                                    </TableBody>
                                </Table>
                            </div>
                        </div>
                    )}

                    {/* Clinical Notes */}
                    {order.result_notes && (
                        <div>
                            <h3 className="mb-2 text-sm font-semibold">
                                {order.status === 'cancelled'
                                    ? 'Cancellation Reason'
                                    : 'Clinical Notes'}
                            </h3>
                            <div
                                className={`rounded-lg border p-4 ${
                                    order.status === 'cancelled'
                                        ? 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950'
                                        : 'bg-muted/50'
                                }`}
                            >
                                <p
                                    className={`text-sm ${
                                        order.status === 'cancelled'
                                            ? 'text-red-900 dark:text-red-200'
                                            : 'text-foreground'
                                    }`}
                                >
                                    {order.result_notes}
                                </p>
                            </div>
                        </div>
                    )}

                    {/* No Results Yet */}
                    {order.status !== 'completed' &&
                        order.status !== 'cancelled' && (
                            <div className="py-8 text-center text-muted-foreground">
                                <FlaskConical className="mx-auto mb-3 h-12 w-12 opacity-50" />
                                <p className="font-medium">Results pending</p>
                                <p className="text-sm">
                                    {order.status === 'ordered' &&
                                        'Sample collection pending'}
                                    {order.status === 'sample_collected' &&
                                        'Sample collected, analysis in progress'}
                                    {order.status === 'in_progress' &&
                                        'Analysis in progress'}
                                </p>
                            </div>
                        )}
                </div>

                <div className="flex justify-end gap-2 border-t pt-4">
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Close
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
};

export function ConsultationLabOrdersTable({
    labOrders,
    consultationId,
    canDelete = true,
}: ConsultationLabOrdersTableProps) {
    const [sorting, setSorting] = React.useState<SortingState>([]);
    const [columnFilters, setColumnFilters] =
        React.useState<ColumnFiltersState>([]);
    const [columnVisibility, setColumnVisibility] =
        React.useState<VisibilityState>({});
    const [globalFilter, setGlobalFilter] = React.useState('');
    const [selectedOrder, setSelectedOrder] = React.useState<LabOrder | null>(
        null,
    );
    const [modalOpen, setModalOpen] = React.useState(false);
    const [deleteOrderId, setDeleteOrderId] = React.useState<number | null>(
        null,
    );
    const [deleting, setDeleting] = React.useState(false);

    const handleViewResults = (order: LabOrder) => {
        setSelectedOrder(order);
        setModalOpen(true);
    };

    const handleDelete = async () => {
        if (!deleteOrderId) return;

        setDeleting(true);
        try {
            const { router } = await import('@inertiajs/react');
            router.delete(
                `/consultation/${consultationId}/lab-orders/${deleteOrderId}`,
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setDeleteOrderId(null);
                    },
                    onFinish: () => {
                        setDeleting(false);
                    },
                },
            );
        } catch (error) {
            setDeleting(false);
        }
    };

    const columns: ColumnDef<LabOrder>[] = [
        {
            accessorKey: 'lab_service.name',
            header: ({ column }) => {
                return (
                    <Button
                        variant="ghost"
                        onClick={() =>
                            column.toggleSorting(column.getIsSorted() === 'asc')
                        }
                        className="p-0 hover:bg-transparent"
                    >
                        Test Name
                        <ArrowUpDown className="ml-2 h-4 w-4" />
                    </Button>
                );
            },
            cell: ({ row }) => {
                const labService = row.original.lab_service;
                return (
                    <div>
                        <div className="font-medium">{labService.name}</div>
                        <div className="text-sm text-muted-foreground">
                            {labService.code} • {labService.category}
                        </div>
                    </div>
                );
            },
        },
        {
            accessorKey: 'status',
            header: ({ column }) => {
                return (
                    <Button
                        variant="ghost"
                        onClick={() =>
                            column.toggleSorting(column.getIsSorted() === 'asc')
                        }
                        className="p-0 hover:bg-transparent"
                    >
                        Status
                        <ArrowUpDown className="ml-2 h-4 w-4" />
                    </Button>
                );
            },
            cell: ({ row }) => {
                const status = row.getValue('status') as string;
                return (
                    <Badge
                        variant="outline"
                        className={getStatusBadgeClasses(status)}
                    >
                        {status.replace('_', ' ').toUpperCase()}
                    </Badge>
                );
            },
            filterFn: (row, id, value) => {
                return value.length === 0 || value.includes(row.getValue(id));
            },
        },
        {
            accessorKey: 'priority',
            header: 'Priority',
            cell: ({ row }) => {
                const priority = row.getValue('priority') as string;
                return priority !== 'routine' ? (
                    <Badge variant="destructive">
                        {priority.toUpperCase()}
                    </Badge>
                ) : (
                    <span className="text-sm text-muted-foreground">
                        Routine
                    </span>
                );
            },
            filterFn: (row, id, value) => {
                return value.length === 0 || value.includes(row.getValue(id));
            },
        },
        {
            accessorKey: 'ordered_at',
            header: ({ column }) => {
                return (
                    <Button
                        variant="ghost"
                        onClick={() =>
                            column.toggleSorting(column.getIsSorted() === 'asc')
                        }
                        className="p-0 hover:bg-transparent"
                    >
                        Ordered At
                        <ArrowUpDown className="ml-2 h-4 w-4" />
                    </Button>
                );
            },
            cell: ({ row }) => {
                return (
                    <div className="text-sm">
                        {formatDateTime(row.getValue('ordered_at'))}
                    </div>
                );
            },
        },
        {
            id: 'ordered_by',
            header: 'Ordered By',
            cell: ({ row }) => {
                return row.original.ordered_by ? (
                    <div className="text-sm">
                        {row.original.ordered_by.name}
                    </div>
                ) : (
                    <span className="text-sm text-muted-foreground">-</span>
                );
            },
        },
        {
            id: 'actions',
            header: () => null,
            cell: ({ row }) => {
                const order = row.original;
                const canDeleteOrder =
                    canDelete &&
                    ['ordered', 'cancelled'].includes(order.status);
                return (
                    <div className="flex justify-end gap-1">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => handleViewResults(order)}
                            className="h-8"
                        >
                            <Eye className="mr-2 h-4 w-4" />
                            View
                        </Button>
                        {canDeleteOrder && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setDeleteOrderId(order.id)}
                                className="h-8 text-red-600 hover:bg-red-50 hover:text-red-700"
                            >
                                <Trash2 className="h-4 w-4" />
                            </Button>
                        )}
                    </div>
                );
            },
        },
    ];

    const table = useReactTable({
        data: labOrders,
        columns,
        onSortingChange: setSorting,
        onColumnFiltersChange: setColumnFilters,
        getCoreRowModel: getCoreRowModel(),
        getSortedRowModel: getSortedRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        onColumnVisibilityChange: setColumnVisibility,
        onGlobalFilterChange: setGlobalFilter,
        globalFilterFn: (row, columnId, filterValue) => {
            const searchStr = String(filterValue).toLowerCase();
            const rowData = row.original;

            return (
                rowData.lab_service.name?.toLowerCase().includes(searchStr) ||
                rowData.lab_service.code?.toLowerCase().includes(searchStr) ||
                rowData.lab_service.category
                    ?.toLowerCase()
                    .includes(searchStr) ||
                rowData.status?.toLowerCase().includes(searchStr) ||
                rowData.priority?.toLowerCase().includes(searchStr)
            );
        },
        state: {
            sorting,
            columnFilters,
            columnVisibility,
            globalFilter,
        },
    });

    const statuses = React.useMemo(() => {
        const statusSet = new Set<string>();
        labOrders.forEach((order) => {
            if (order.status) {
                statusSet.add(order.status);
            }
        });
        return Array.from(statusSet);
    }, [labOrders]);

    const priorities = React.useMemo(() => {
        const prioritySet = new Set<string>();
        labOrders.forEach((order) => {
            if (order.priority) {
                prioritySet.add(order.priority);
            }
        });
        return Array.from(prioritySet);
    }, [labOrders]);

    return (
        <div className="w-full space-y-4">
            <div className="flex items-center gap-4">
                <div className="relative max-w-sm flex-1">
                    <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 transform text-muted-foreground" />
                    <Input
                        placeholder="Search tests..."
                        value={globalFilter}
                        onChange={(event) => {
                            setGlobalFilter(event.target.value);
                        }}
                        className="pl-10"
                    />
                </div>

                {statuses.length > 0 && (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="outline" className="border-dashed">
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
                                        .replace(/\b\w/g, (l) =>
                                            l.toUpperCase(),
                                        )}
                                </DropdownMenuCheckboxItem>
                            ))}
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}

                {priorities.length > 0 && (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="outline" className="border-dashed">
                                Priority
                                <ChevronDown className="ml-2 h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start">
                            <DropdownMenuLabel>
                                Filter by Priority
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            {priorities.map((priority) => (
                                <DropdownMenuCheckboxItem
                                    key={priority}
                                    checked={
                                        (
                                            table
                                                .getColumn('priority')
                                                ?.getFilterValue() as string[]
                                        )?.includes(priority) ?? false
                                    }
                                    onCheckedChange={(checked) => {
                                        const currentFilter =
                                            (table
                                                .getColumn('priority')
                                                ?.getFilterValue() as string[]) ||
                                            [];
                                        if (checked) {
                                            table
                                                .getColumn('priority')
                                                ?.setFilterValue([
                                                    ...currentFilter,
                                                    priority,
                                                ]);
                                        } else {
                                            table
                                                .getColumn('priority')
                                                ?.setFilterValue(
                                                    currentFilter.filter(
                                                        (p) => p !== priority,
                                                    ),
                                                );
                                        }
                                    }}
                                >
                                    {priority.toUpperCase()}
                                </DropdownMenuCheckboxItem>
                            ))}
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}

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
                                        {column.id
                                            .replace('_', ' ')
                                            .replace('.', ' ')}
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
                                    <div className="text-muted-foreground">
                                        No lab orders found.
                                    </div>
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>

            {table.getFilteredRowModel().rows.length > 0 && (
                <div className="text-sm text-muted-foreground">
                    Showing {table.getRowModel().rows.length} lab order(s).
                </div>
            )}

            {/* Lab Results Modal */}
            {selectedOrder && (
                <LabResultsModal
                    order={selectedOrder}
                    open={modalOpen}
                    onOpenChange={setModalOpen}
                />
            )}

            {/* Delete Confirmation Dialog */}
            <AlertDialog
                open={deleteOrderId !== null}
                onOpenChange={(open) => !open && setDeleteOrderId(null)}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete Lab Order</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to delete this lab order? This
                            will also remove the associated charge and any claim
                            items. This action cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={deleting}>
                            Cancel
                        </AlertDialogCancel>
                        <AlertDialogAction
                            onClick={handleDelete}
                            disabled={deleting}
                            className="bg-red-600 hover:bg-red-700"
                        >
                            {deleting ? 'Deleting...' : 'Delete'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}
