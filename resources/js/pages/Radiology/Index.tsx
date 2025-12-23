import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { format, formatDistanceToNow } from 'date-fns';
import {
    AlertCircle,
    Calendar,
    ChevronDown,
    Clock,
    Eye,
    FileText,
    Image,
    Play,
    Scan,
    Search,
} from 'lucide-react';
import * as React from 'react';
import { useDebouncedCallback } from 'use-debounce';

interface Patient {
    id: number;
    patient_number: string;
    first_name: string;
    last_name: string;
    date_of_birth: string;
    gender: string;
}

interface LabService {
    id: number;
    name: string;
    code: string;
    modality: string | null;
    category: string;
}

interface ImagingOrder {
    id: number;
    status: string;
    priority: string;
    clinical_notes: string | null;
    ordered_at: string;
    result_entered_at: string | null;
    lab_service: LabService;
    ordered_by: { id: number; name: string };
    patient: Patient | null;
    context: string | null;
    has_images: boolean;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedOrders {
    data: ImagingOrder[];
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
    links: PaginationLink[];
}

interface Stats {
    ordered: number;
    in_progress: number;
    completed_today: number;
}

interface Filters {
    status: string;
    priority?: string;
    modality?: string;
    search?: string;
    date_from?: string;
    date_to?: string;
}

interface Props {
    orders: PaginatedOrders;
    stats: Stats;
    modalities: string[];
    filters: Filters;
}

const statusConfig: Record<string, { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }> = {
    ordered: { label: 'Ordered', variant: 'secondary' },
    in_progress: { label: 'In Progress', variant: 'default' },
    completed: { label: 'Completed', variant: 'outline' },
};

const priorityConfig: Record<string, { label: string; className: string }> = {
    stat: { label: 'STAT', className: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 font-bold animate-pulse' },
    urgent: { label: 'URGENT', className: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200 font-semibold' },
    routine: { label: 'Routine', className: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200' },
};

export default function RadiologyIndex({ orders, stats, modalities, filters }: Props) {
    const [search, setSearch] = React.useState(filters.search || '');
    const [dateFrom, setDateFrom] = React.useState(filters.date_from || '');
    const [dateTo, setDateTo] = React.useState(filters.date_to || '');

    const currentStatus = filters.status || 'pending';
    const currentPriority = filters.priority || '';
    const currentModality = filters.modality || '';

    const allStatuses = [
        { value: 'pending', label: 'Pending (All Active)' },
        { value: 'ordered', label: 'Ordered' },
        { value: 'in_progress', label: 'In Progress' },
        { value: 'completed', label: 'Completed' },
        { value: 'all', label: 'All Statuses' },
    ];

    const priorities = ['stat', 'urgent', 'routine'];

    // Debounced server-side search
    const debouncedSearch = useDebouncedCallback((value: string) => {
        router.get(
            '/radiology',
            {
                ...filters,
                search: value || undefined,
                page: 1,
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
            '/radiology',
            {
                ...filters,
                [key]: value || undefined,
                page: 1,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const handleDateFilterChange = () => {
        router.get(
            '/radiology',
            {
                ...filters,
                date_from: dateFrom || undefined,
                date_to: dateTo || undefined,
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
            '/radiology',
            { ...filters, per_page: perPage, page: 1 },
            { preserveState: true, preserveScroll: true },
        );
    };

    const handleMarkInProgress = (orderId: number) => {
        router.patch(`/radiology/orders/${orderId}/in-progress`, {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const prevLink = orders.links.find((link) => link.label.includes('Previous'));
    const nextLink = orders.links.find((link) => link.label.includes('Next'));

    return (
        <AppLayout breadcrumbs={[
            { title: 'Investigations', href: '#' },
            { title: 'Radiology', href: '/radiology' },
        ]}>
            <Head title="Radiology Worklist" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Radiology Worklist</h1>
                        <p className="text-muted-foreground">
                            Process imaging orders and upload results
                        </p>
                    </div>
                </div>

                {/* Stats Cards */}
                <div className="grid gap-4 md:grid-cols-3">
                    <StatCard
                        label="Pending Orders"
                        value={stats.ordered}
                        icon={<FileText className="h-4 w-4" />}
                        variant="warning"
                    />
                    <StatCard
                        label="In Progress"
                        value={stats.in_progress}
                        icon={<Scan className="h-4 w-4" />}
                        variant="info"
                    />
                    <StatCard
                        label="Completed Today"
                        value={stats.completed_today}
                        icon={<Image className="h-4 w-4" />}
                        variant="success"
                    />
                </div>

                {/* Worklist Card */}
                <Card>
                    <CardHeader>
                        <CardTitle>
                            Imaging Orders ({orders.total} total)
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {/* Filters */}
                            <div className="flex flex-wrap items-center gap-4">
                                <div className="relative max-w-sm flex-1">
                                    <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 transform text-muted-foreground" />
                                    <Input
                                        placeholder="Search patients, studies..."
                                        value={search}
                                        onChange={(e) => handleSearchChange(e.target.value)}
                                        className="pl-10"
                                    />
                                </div>

                                {/* Per Page Selector */}
                                <div className="flex items-center gap-2">
                                    <span className="text-sm text-muted-foreground">Show</span>
                                    <select
                                        value={orders.per_page}
                                        onChange={(e) => handlePerPageChange(e.target.value)}
                                        className="h-8 rounded-md border border-input bg-background px-2 text-sm"
                                    >
                                        <option value="10">10</option>
                                        <option value="20">20</option>
                                        <option value="50">50</option>
                                        <option value="100">100</option>
                                    </select>
                                </div>

                                {/* Status Filter */}
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button variant="outline" className="border-dashed">
                                            Status: {allStatuses.find((s) => s.value === currentStatus)?.label}
                                            <ChevronDown className="ml-2 h-4 w-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="start" onCloseAutoFocus={(e) => e.preventDefault()}>
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
                                    <DropdownMenuContent align="start" onCloseAutoFocus={(e) => e.preventDefault()}>
                                        <DropdownMenuLabel>Filter by Priority</DropdownMenuLabel>
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
                                                    handleFilterChange('priority', checked ? priority : undefined);
                                                }}
                                            >
                                                {priority.toUpperCase()}
                                            </DropdownMenuCheckboxItem>
                                        ))}
                                    </DropdownMenuContent>
                                </DropdownMenu>

                                {/* Modality Filter */}
                                {modalities.length > 0 && (
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button variant="outline" className="border-dashed">
                                                Modality{currentModality ? `: ${currentModality}` : ''}
                                                <ChevronDown className="ml-2 h-4 w-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="start" onCloseAutoFocus={(e) => e.preventDefault()}>
                                            <DropdownMenuLabel>Filter by Modality</DropdownMenuLabel>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuCheckboxItem
                                                checked={!currentModality}
                                                onCheckedChange={(checked) => {
                                                    if (checked) {
                                                        handleFilterChange('modality', undefined);
                                                    }
                                                }}
                                            >
                                                All Modalities
                                            </DropdownMenuCheckboxItem>
                                            {modalities.map((modality) => (
                                                <DropdownMenuCheckboxItem
                                                    key={modality}
                                                    checked={currentModality === modality}
                                                    onCheckedChange={(checked) => {
                                                        handleFilterChange('modality', checked ? modality : undefined);
                                                    }}
                                                >
                                                    {modality}
                                                </DropdownMenuCheckboxItem>
                                            ))}
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                )}

                                {/* Date Range Filter */}
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button variant="outline" className="border-dashed">
                                            <Calendar className="mr-2 h-4 w-4" />
                                            Date Range
                                            {(dateFrom || dateTo) && (
                                                <Badge variant="secondary" className="ml-2">
                                                    {dateFrom && dateTo ? `${dateFrom} - ${dateTo}` : dateFrom || dateTo}
                                                </Badge>
                                            )}
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="start" className="w-72 p-4" onCloseAutoFocus={(e) => e.preventDefault()}>
                                        <div className="space-y-4">
                                            <div>
                                                <label className="text-sm font-medium">From</label>
                                                <Input
                                                    type="date"
                                                    value={dateFrom}
                                                    onChange={(e) => setDateFrom(e.target.value)}
                                                    className="mt-1"
                                                />
                                            </div>
                                            <div>
                                                <label className="text-sm font-medium">To</label>
                                                <Input
                                                    type="date"
                                                    value={dateTo}
                                                    onChange={(e) => setDateTo(e.target.value)}
                                                    className="mt-1"
                                                />
                                            </div>
                                            <div className="flex gap-2">
                                                <Button
                                                    size="sm"
                                                    onClick={handleDateFilterChange}
                                                >
                                                    Apply
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => {
                                                        setDateFrom('');
                                                        setDateTo('');
                                                        handleFilterChange('date_from', undefined);
                                                        handleFilterChange('date_to', undefined);
                                                    }}
                                                >
                                                    Clear
                                                </Button>
                                            </div>
                                        </div>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>

                            {/* Table */}
                            <div className="rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Priority</TableHead>
                                            <TableHead>Patient</TableHead>
                                            <TableHead>Study</TableHead>
                                            <TableHead>Modality</TableHead>
                                            <TableHead>Ordered By</TableHead>
                                            <TableHead>Ordered</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead className="text-right">Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {orders.data.length > 0 ? (
                                            orders.data.map((order) => (
                                                <TableRow
                                                    key={order.id}
                                                    className={order.priority === 'stat' ? 'bg-red-50 dark:bg-red-950/20' : ''}
                                                >
                                                    <TableCell>
                                                        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs ${priorityConfig[order.priority]?.className || ''}`}>
                                                            {order.priority === 'stat' && (
                                                                <AlertCircle className="mr-1 h-3 w-3" />
                                                            )}
                                                            {priorityConfig[order.priority]?.label || order.priority}
                                                        </span>
                                                    </TableCell>
                                                    <TableCell>
                                                        {order.patient ? (
                                                            <div>
                                                                <div className="font-medium">
                                                                    {order.patient.first_name} {order.patient.last_name}
                                                                </div>
                                                                <div className="text-sm text-muted-foreground">
                                                                    {order.patient.patient_number}
                                                                </div>
                                                                {order.context && (
                                                                    <div className="text-xs text-muted-foreground">
                                                                        {order.context}
                                                                    </div>
                                                                )}
                                                            </div>
                                                        ) : (
                                                            <span className="text-muted-foreground">Unknown</span>
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        <div className="flex items-center gap-2">
                                                            <span className="font-medium">{order.lab_service.name}</span>
                                                            {order.has_images && (
                                                                <Image className="h-4 w-4 text-green-600" />
                                                            )}
                                                        </div>
                                                        {order.clinical_notes && (
                                                            <div className="text-xs text-muted-foreground truncate max-w-[200px]" title={order.clinical_notes}>
                                                                {order.clinical_notes}
                                                            </div>
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        {order.lab_service.modality ? (
                                                            <Badge variant="outline">{order.lab_service.modality}</Badge>
                                                        ) : (
                                                            <span className="text-muted-foreground">-</span>
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        <div className="text-sm">{order.ordered_by.name}</div>
                                                    </TableCell>
                                                    <TableCell>
                                                        <div className="flex items-center gap-1 text-sm">
                                                            <Clock className="h-3 w-3 text-muted-foreground" />
                                                            <span title={format(new Date(order.ordered_at), 'PPpp')}>
                                                                {formatDistanceToNow(new Date(order.ordered_at), { addSuffix: true })}
                                                            </span>
                                                        </div>
                                                    </TableCell>
                                                    <TableCell>
                                                        <Badge variant={statusConfig[order.status]?.variant || 'secondary'}>
                                                            {statusConfig[order.status]?.label || order.status}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        <div className="flex items-center justify-end gap-2">
                                                            {order.status === 'ordered' && (
                                                                <Button
                                                                    size="sm"
                                                                    variant="outline"
                                                                    onClick={() => handleMarkInProgress(order.id)}
                                                                    title="Start Processing"
                                                                >
                                                                    <Play className="h-4 w-4" />
                                                                </Button>
                                                            )}
                                                            <Button
                                                                size="sm"
                                                                variant="default"
                                                                asChild
                                                            >
                                                                <Link href={`/radiology/orders/${order.id}`}>
                                                                    <Eye className="mr-1 h-4 w-4" />
                                                                    View
                                                                </Link>
                                                            </Button>
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            ))
                                        ) : (
                                            <TableRow>
                                                <TableCell colSpan={8} className="h-24 text-center">
                                                    <div className="flex flex-col items-center gap-2">
                                                        <Scan className="h-8 w-8 text-muted-foreground" />
                                                        <div>No imaging orders found.</div>
                                                        <div className="text-sm text-muted-foreground">
                                                            No imaging orders match your current filters.
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
                                    {orders.from && orders.to ? (
                                        <>
                                            Showing {orders.from} to {orders.to} of {orders.total} order(s)
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
                                    {orders.links
                                        .filter(
                                            (link) =>
                                                !link.label.includes('Previous') &&
                                                !link.label.includes('Next'),
                                        )
                                        .slice(0, 5) // Limit visible page numbers
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
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
