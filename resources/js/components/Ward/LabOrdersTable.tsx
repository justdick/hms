import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    AlertCircle,
    Calendar,
    ChevronDown,
    ChevronUp,
    FlaskConical,
    User,
} from 'lucide-react';
import { useMemo, useState } from 'react';

interface LabService {
    id: number;
    name: string;
    code: string;
    price: number;
}

interface LabOrder {
    id: number;
    lab_service?: LabService;
    status: string;
    ordered_at: string;
    priority: string;
    special_instructions?: string;
    result_values?: any;
    result_notes?: string;
    ordered_by?: {
        id: number;
        name: string;
    };
}

interface Props {
    labOrders: LabOrder[];
    onViewDetails: (order: LabOrder) => void;
}

type StatusFilter =
    | 'all'
    | 'pending'
    | 'in_progress'
    | 'completed'
    | 'cancelled';
type SortColumn =
    | 'test_name'
    | 'status'
    | 'priority'
    | 'ordered_at'
    | 'ordered_by';
type SortDirection = 'asc' | 'desc';

export function LabOrdersTable({ labOrders, onViewDetails }: Props) {
    const [statusFilter, setStatusFilter] = useState<StatusFilter>('all');
    const [sortColumn, setSortColumn] = useState<SortColumn>('ordered_at');
    const [sortDirection, setSortDirection] = useState<SortDirection>('desc');
    const [expandedRows, setExpandedRows] = useState<Set<number>>(new Set());

    // Filter lab orders by status
    const filteredOrders = useMemo(() => {
        if (statusFilter === 'all') return labOrders;
        return labOrders.filter((order) => order.status === statusFilter);
    }, [labOrders, statusFilter]);

    // Sort lab orders
    const sortedOrders = useMemo(() => {
        const sorted = [...filteredOrders];
        sorted.sort((a, b) => {
            let aValue: any;
            let bValue: any;

            switch (sortColumn) {
                case 'test_name':
                    aValue = a.lab_service?.name || '';
                    bValue = b.lab_service?.name || '';
                    break;
                case 'status':
                    aValue = a.status;
                    bValue = b.status;
                    break;
                case 'priority':
                    aValue = a.priority;
                    bValue = b.priority;
                    break;
                case 'ordered_at':
                    aValue = new Date(a.ordered_at).getTime();
                    bValue = new Date(b.ordered_at).getTime();
                    break;
                case 'ordered_by':
                    aValue = a.ordered_by?.name || '';
                    bValue = b.ordered_by?.name || '';
                    break;
                default:
                    return 0;
            }

            if (aValue < bValue) return sortDirection === 'asc' ? -1 : 1;
            if (aValue > bValue) return sortDirection === 'asc' ? 1 : -1;
            return 0;
        });
        return sorted;
    }, [filteredOrders, sortColumn, sortDirection]);

    // Count orders by status
    const statusCounts = useMemo(() => {
        return {
            all: labOrders.length,
            pending: labOrders.filter((o) => o.status === 'pending').length,
            in_progress: labOrders.filter((o) => o.status === 'in_progress')
                .length,
            completed: labOrders.filter((o) => o.status === 'completed').length,
            cancelled: labOrders.filter((o) => o.status === 'cancelled').length,
        };
    }, [labOrders]);

    const handleSort = (column: SortColumn) => {
        if (sortColumn === column) {
            setSortDirection(sortDirection === 'asc' ? 'desc' : 'asc');
        } else {
            setSortColumn(column);
            setSortDirection('asc');
        }
    };

    const toggleRowExpansion = (orderId: number) => {
        const newExpanded = new Set(expandedRows);
        if (newExpanded.has(orderId)) {
            newExpanded.delete(orderId);
        } else {
            newExpanded.add(orderId);
        }
        setExpandedRows(newExpanded);
    };

    const getStatusBadgeClasses = (status: string) => {
        switch (status) {
            case 'completed':
                return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
            case 'in_progress':
                return 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200';
            case 'pending':
                return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
            case 'cancelled':
                return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
            default:
                return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
        }
    };

    const getPriorityBadgeClasses = (priority: string) => {
        switch (priority) {
            case 'stat':
            case 'urgent':
                return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
            case 'routine':
                return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
            default:
                return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
        }
    };

    const formatDateTime = (dateString: string) => {
        return new Date(dateString).toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const SortIcon = ({ column }: { column: SortColumn }) => {
        if (sortColumn !== column) return null;
        return sortDirection === 'asc' ? (
            <ChevronUp className="ml-1 inline h-4 w-4" />
        ) : (
            <ChevronDown className="ml-1 inline h-4 w-4" />
        );
    };

    return (
        <div className="space-y-4">
            {/* Status Filter Tabs */}
            <Tabs
                value={statusFilter}
                onValueChange={(v) => setStatusFilter(v as StatusFilter)}
            >
                <TabsList>
                    <TabsTrigger value="all">
                        All ({statusCounts.all})
                    </TabsTrigger>
                    <TabsTrigger value="pending">
                        Pending ({statusCounts.pending})
                    </TabsTrigger>
                    <TabsTrigger value="in_progress">
                        In Progress ({statusCounts.in_progress})
                    </TabsTrigger>
                    <TabsTrigger value="completed">
                        Completed ({statusCounts.completed})
                    </TabsTrigger>
                </TabsList>
            </Tabs>

            {/* Lab Orders Table */}
            <div className="rounded-lg border dark:border-gray-700">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="w-12"></TableHead>
                            <TableHead
                                className="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800"
                                onClick={() => handleSort('test_name')}
                            >
                                Test Name
                                <SortIcon column="test_name" />
                            </TableHead>
                            <TableHead
                                className="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800"
                                onClick={() => handleSort('status')}
                            >
                                Status
                                <SortIcon column="status" />
                            </TableHead>
                            <TableHead
                                className="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800"
                                onClick={() => handleSort('priority')}
                            >
                                Priority
                                <SortIcon column="priority" />
                            </TableHead>
                            <TableHead
                                className="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800"
                                onClick={() => handleSort('ordered_at')}
                            >
                                Ordered Date
                                <SortIcon column="ordered_at" />
                            </TableHead>
                            <TableHead
                                className="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800"
                                onClick={() => handleSort('ordered_by')}
                            >
                                Ordered By
                                <SortIcon column="ordered_by" />
                            </TableHead>
                            <TableHead className="text-right">
                                Actions
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {sortedOrders.length === 0 ? (
                            <TableRow>
                                <TableCell
                                    colSpan={7}
                                    className="h-24 text-center"
                                >
                                    <div className="flex flex-col items-center justify-center text-gray-500 dark:text-gray-400">
                                        <FlaskConical className="mb-2 h-8 w-8" />
                                        <p>No lab orders found</p>
                                    </div>
                                </TableCell>
                            </TableRow>
                        ) : (
                            sortedOrders.map((order) => (
                                <>
                                    <TableRow
                                        key={order.id}
                                        className="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800"
                                    >
                                        <TableCell>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    toggleRowExpansion(order.id)
                                                }
                                                className="h-6 w-6 p-0"
                                            >
                                                {expandedRows.has(order.id) ? (
                                                    <ChevronUp className="h-4 w-4" />
                                                ) : (
                                                    <ChevronDown className="h-4 w-4" />
                                                )}
                                            </Button>
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            <div>
                                                <div className="text-gray-900 dark:text-gray-100">
                                                    {order.lab_service?.name ||
                                                        'Unknown Test'}
                                                </div>
                                                {order.lab_service?.code && (
                                                    <div className="text-sm text-gray-500 dark:text-gray-400">
                                                        {order.lab_service.code}
                                                    </div>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                className={getStatusBadgeClasses(
                                                    order.status,
                                                )}
                                            >
                                                {order.status
                                                    .replace('_', ' ')
                                                    .toUpperCase()}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                className={getPriorityBadgeClasses(
                                                    order.priority,
                                                )}
                                            >
                                                {order.priority.toUpperCase()}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                                <Calendar className="h-4 w-4 text-gray-400" />
                                                {formatDateTime(
                                                    order.ordered_at,
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {order.ordered_by ? (
                                                <div className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                                    <User className="h-4 w-4 text-gray-400" />
                                                    {order.ordered_by.name}
                                                </div>
                                            ) : (
                                                <span className="text-sm text-gray-400">
                                                    -
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    onViewDetails(order)
                                                }
                                            >
                                                View Details
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                    {expandedRows.has(order.id) && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={7}
                                                className="bg-gray-50 dark:bg-gray-900"
                                            >
                                                <div className="space-y-3 p-4">
                                                    {order.special_instructions && (
                                                        <div className="flex items-start gap-2 rounded-lg border border-yellow-200 bg-yellow-50 p-3 dark:border-yellow-800 dark:bg-yellow-950">
                                                            <AlertCircle className="mt-0.5 h-4 w-4 text-yellow-600 dark:text-yellow-400" />
                                                            <div>
                                                                <p className="text-sm font-medium text-yellow-900 dark:text-yellow-200">
                                                                    Special
                                                                    Instructions
                                                                </p>
                                                                <p className="text-sm text-yellow-800 dark:text-yellow-300">
                                                                    {
                                                                        order.special_instructions
                                                                    }
                                                                </p>
                                                            </div>
                                                        </div>
                                                    )}
                                                    {order.result_notes && (
                                                        <div className="rounded-lg border p-3 dark:border-gray-700">
                                                            <p className="mb-1 text-sm font-medium text-gray-900 dark:text-gray-100">
                                                                Result Notes
                                                            </p>
                                                            <p className="text-sm text-gray-700 dark:text-gray-300">
                                                                {
                                                                    order.result_notes
                                                                }
                                                            </p>
                                                        </div>
                                                    )}
                                                    {!order.special_instructions &&
                                                        !order.result_notes && (
                                                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                                                No additional
                                                                information
                                                                available
                                                            </p>
                                                        )}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </>
                            ))
                        )}
                    </TableBody>
                </Table>
            </div>
        </div>
    );
}
