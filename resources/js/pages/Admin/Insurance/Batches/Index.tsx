import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
import {
    Calendar,
    CheckCircle,
    Clock,
    Eye,
    FileCheck,
    FileText,
    Filter,
    FolderOpen,
    Package,
    Plus,
    Search,
    Send,
    X,
} from 'lucide-react';
import { FormEvent, lazy, Suspense, useEffect, useState } from 'react';

// Lazy load the CreateBatchModal
const CreateBatchModal = lazy(
    () => import('@/components/Insurance/Batches/CreateBatchModal'),
);

interface ClaimBatch {
    id: number;
    batch_number: string;
    name: string;
    submission_period: string;
    submission_period_formatted: string;
    status: 'draft' | 'finalized' | 'submitted' | 'processing' | 'completed';
    status_label: string;
    total_claims: number;
    total_amount: string;
    approved_amount: string | null;
    paid_amount: string | null;
    submitted_at: string | null;
    exported_at: string | null;
    paid_at: string | null;
    created_at: string;
    is_draft: boolean;
    is_finalized: boolean;
    is_submitted: boolean;
    is_completed: boolean;
    can_be_modified: boolean;
    creator?: {
        id: number;
        name: string;
    };
    batch_items_count?: number;
}

interface Filters {
    status?: string;
    period?: string;
    search?: string;
}

interface Stats {
    total: number;
    draft: number;
    finalized: number;
    submitted: number;
    completed: number;
    vetted_claims_available: number;
}

interface Props {
    batches: {
        data: ClaimBatch[];
        links: any;
        meta: any;
    };
    filters: Filters;
    stats: Stats;
}

const statusConfig: Record<
    string,
    { label: string; color: string; icon: React.ReactNode }
> = {
    draft: {
        label: 'Draft',
        color: 'bg-gray-500',
        icon: <FileText className="h-4 w-4" />,
    },
    finalized: {
        label: 'Finalized',
        color: 'bg-blue-500',
        icon: <FileCheck className="h-4 w-4" />,
    },
    submitted: {
        label: 'Submitted',
        color: 'bg-purple-500',
        icon: <Send className="h-4 w-4" />,
    },
    processing: {
        label: 'Processing',
        color: 'bg-yellow-500',
        icon: <Clock className="h-4 w-4" />,
    },
    completed: {
        label: 'Completed',
        color: 'bg-green-500',
        icon: <CheckCircle className="h-4 w-4" />,
    },
};

export default function BatchesIndex({ batches, filters, stats }: Props) {
    const [showFilters, setShowFilters] = useState(false);
    const [localFilters, setLocalFilters] = useState<Filters>(filters);
    const [createModalOpen, setCreateModalOpen] = useState(false);

    useEffect(() => {
        setLocalFilters(filters);
    }, [filters]);

    const handleFilterChange = (key: keyof Filters, value: string) => {
        setLocalFilters((prev) => ({
            ...prev,
            [key]: value === 'all' || !value ? undefined : value,
        }));
    };

    const handleApplyFilters = (e: FormEvent) => {
        e.preventDefault();
        router.get('/admin/insurance/batches', localFilters as any, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleClearFilters = () => {
        setLocalFilters({});
        router.get(
            '/admin/insurance/batches',
            {},
            { preserveState: true, preserveScroll: true },
        );
    };

    const hasActiveFilters = Object.keys(filters).some(
        (key) => filters[key as keyof Filters] !== undefined,
    );

    const formatCurrency = (amount: string | null) => {
        if (!amount) return 'GHS 0.00';
        return new Intl.NumberFormat('en-GH', {
            style: 'currency',
            currency: 'GHS',
        }).format(parseFloat(amount));
    };

    const formatDate = (dateString: string | null) => {
        if (!dateString) return '-';
        return new Date(dateString).toLocaleDateString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        });
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                { title: 'Insurance', href: '/admin/insurance' },
                { title: 'Claim Batches', href: '' },
            ]}
        >
            <Head title="Claim Batches" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="flex items-center gap-2 text-3xl font-bold text-gray-900 dark:text-gray-100">
                            <Package className="h-8 w-8" />
                            Claim Batches
                        </h1>
                        <p className="mt-2 text-gray-600 dark:text-gray-400">
                            Manage claim batches for NHIA submission
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        {hasActiveFilters && (
                            <Badge variant="secondary">Filters active</Badge>
                        )}
                        <Button
                            variant="outline"
                            onClick={() => setShowFilters(!showFilters)}
                        >
                            <Filter className="mr-2 h-4 w-4" />
                            {showFilters ? 'Hide Filters' : 'Show Filters'}
                        </Button>
                        <Button onClick={() => setCreateModalOpen(true)}>
                            <Plus className="mr-2 h-4 w-4" />
                            Create Batch
                        </Button>
                    </div>
                </div>

                {/* Stats Overview */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-5">
                    <StatCard
                        label="Total Batches"
                        value={stats.total}
                        icon={<Package className="h-5 w-5" />}
                        variant="info"
                    />
                    <StatCard
                        label="Draft"
                        value={stats.draft}
                        icon={<FileText className="h-5 w-5" />}
                    />
                    <StatCard
                        label="Finalized"
                        value={stats.finalized}
                        icon={<FileCheck className="h-5 w-5" />}
                    />
                    <StatCard
                        label="Submitted"
                        value={stats.submitted}
                        icon={<Send className="h-5 w-5" />}
                    />
                    <StatCard
                        label="Vetted Claims"
                        value={stats.vetted_claims_available}
                        icon={<CheckCircle className="h-5 w-5" />}
                        variant="success"
                    />
                </div>

                {/* Filters Panel */}
                {showFilters && (
                    <Card>
                        <CardContent className="p-6">
                            <form
                                onSubmit={handleApplyFilters}
                                className="space-y-4"
                            >
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                    <div className="space-y-2">
                                        <Label htmlFor="search">Search</Label>
                                        <div className="relative">
                                            <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-500" />
                                            <Input
                                                id="search"
                                                placeholder="Batch number, name..."
                                                value={
                                                    localFilters.search || ''
                                                }
                                                onChange={(e) =>
                                                    handleFilterChange(
                                                        'search',
                                                        e.target.value,
                                                    )
                                                }
                                                className="pl-9"
                                            />
                                        </div>
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="status">Status</Label>
                                        <Select
                                            value={localFilters.status || 'all'}
                                            onValueChange={(value) =>
                                                handleFilterChange(
                                                    'status',
                                                    value,
                                                )
                                            }
                                        >
                                            <SelectTrigger id="status">
                                                <SelectValue placeholder="All statuses" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">
                                                    All statuses
                                                </SelectItem>
                                                <SelectItem value="draft">
                                                    Draft
                                                </SelectItem>
                                                <SelectItem value="finalized">
                                                    Finalized
                                                </SelectItem>
                                                <SelectItem value="submitted">
                                                    Submitted
                                                </SelectItem>
                                                <SelectItem value="processing">
                                                    Processing
                                                </SelectItem>
                                                <SelectItem value="completed">
                                                    Completed
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="period">
                                            Submission Period
                                        </Label>
                                        <Input
                                            id="period"
                                            type="month"
                                            value={localFilters.period || ''}
                                            onChange={(e) =>
                                                handleFilterChange(
                                                    'period',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Button type="submit">Apply Filters</Button>
                                    {hasActiveFilters && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={handleClearFilters}
                                        >
                                            <X className="mr-2 h-4 w-4" />
                                            Clear All Filters
                                        </Button>
                                    )}
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {/* Batches Table */}
                <Card>
                    <CardContent className="p-0">
                        {batches.data.length > 0 ? (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Batch Number</TableHead>
                                            <TableHead>Name</TableHead>
                                            <TableHead>Period</TableHead>
                                            <TableHead className="text-center">
                                                Claims
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Total Amount
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Approved
                                            </TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Created</TableHead>
                                            <TableHead className="text-right">
                                                Actions
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {batches.data.map((batch) => (
                                            <TableRow key={batch.id}>
                                                <TableCell className="font-mono font-medium">
                                                    {batch.batch_number}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="font-medium">
                                                        {batch.name}
                                                    </div>
                                                    {batch.creator && (
                                                        <div className="text-sm text-gray-500">
                                                            by{' '}
                                                            {batch.creator.name}
                                                        </div>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-1">
                                                        <Calendar className="h-4 w-4 text-gray-400" />
                                                        {
                                                            batch.submission_period_formatted
                                                        }
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-center">
                                                    <Badge variant="outline">
                                                        {batch.total_claims}{' '}
                                                        claims
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-right font-medium">
                                                    {formatCurrency(
                                                        batch.total_amount,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    {batch.approved_amount ? (
                                                        <span className="font-medium text-green-600">
                                                            {formatCurrency(
                                                                batch.approved_amount,
                                                            )}
                                                        </span>
                                                    ) : (
                                                        <span className="text-gray-400">
                                                            -
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge
                                                        className={
                                                            statusConfig[
                                                                batch.status
                                                            ]?.color ||
                                                            'bg-gray-500'
                                                        }
                                                    >
                                                        {statusConfig[
                                                            batch.status
                                                        ]?.label ||
                                                            batch.status}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    {formatDate(
                                                        batch.created_at,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <Link
                                                        href={`/admin/insurance/batches/${batch.id}`}
                                                    >
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                        </Button>
                                                    </Link>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        ) : (
                            <div className="p-12 text-center">
                                <FolderOpen className="mx-auto mb-4 h-16 w-16 text-gray-300 dark:text-gray-600" />
                                <h3 className="mb-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    No batches found
                                </h3>
                                <p className="mb-4 text-gray-600 dark:text-gray-400">
                                    {hasActiveFilters
                                        ? 'Try adjusting your filters'
                                        : 'Get started by creating your first claim batch.'}
                                </p>
                                {!hasActiveFilters && (
                                    <Button
                                        onClick={() => setCreateModalOpen(true)}
                                    >
                                        <Plus className="mr-2 h-4 w-4" />
                                        Create Batch
                                    </Button>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Pagination */}
                {batches.data.length > 0 &&
                    batches.links &&
                    Array.isArray(batches.links) && (
                        <div className="flex items-center justify-between">
                            <div className="text-sm text-gray-600 dark:text-gray-400">
                                Showing {batches.meta?.from} to{' '}
                                {batches.meta?.to} of {batches.meta?.total}{' '}
                                batches
                            </div>
                            <div className="flex gap-2">
                                {batches.links.map(
                                    (link: any, index: number) => {
                                        if (link.url === null) return null;
                                        return (
                                            <Button
                                                key={index}
                                                variant={
                                                    link.active
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                size="sm"
                                                onClick={() =>
                                                    router.get(
                                                        link.url,
                                                        localFilters as any,
                                                        {
                                                            preserveState: true,
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                                dangerouslySetInnerHTML={{
                                                    __html: link.label,
                                                }}
                                            />
                                        );
                                    },
                                )}
                            </div>
                        </div>
                    )}
            </div>

            {/* Create Batch Modal */}
            <Suspense fallback={null}>
                <CreateBatchModal
                    isOpen={createModalOpen}
                    onClose={() => setCreateModalOpen(false)}
                />
            </Suspense>
        </AppLayout>
    );
}
