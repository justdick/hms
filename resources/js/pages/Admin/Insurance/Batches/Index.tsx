import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { StatCard } from '@/components/ui/stat-card';
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import {
    CheckCircle,
    FileCheck,
    FileText,
    Package,
    Plus,
    Send,
} from 'lucide-react';
import { lazy, Suspense, useState } from 'react';
import { batchesColumns, ClaimBatch } from './batches-columns';
import { BatchesDataTable } from './batches-data-table';

const CreateBatchModal = lazy(
    () => import('@/components/Insurance/Batches/CreateBatchModal'),
);

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedBatches {
    data: ClaimBatch[];
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
    links: PaginationLink[];
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
    batches: PaginatedBatches;
    filters: Filters;
    stats: Stats;
}

export default function BatchesIndex({ batches, filters, stats }: Props) {
    const [createModalOpen, setCreateModalOpen] = useState(false);

    const hasActiveFilters = Object.keys(filters).some(
        (key) => filters[key as keyof Filters] !== undefined,
    );

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

                {/* Batches DataTable */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Package className="h-5 w-5" />
                            Batches ({batches.total || 0})
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <BatchesDataTable
                            columns={batchesColumns}
                            data={batches.data}
                            pagination={batches}
                            filters={filters}
                        />
                    </CardContent>
                </Card>
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
