import { ImportNhisTariffModal } from '@/components/NhisTariff/ImportNhisTariffModal';
import { NhisTariffForm } from '@/components/NhisTariff/NhisTariffForm';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { StatCard } from '@/components/ui/stat-card';
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { CheckCircle, FileSpreadsheet, Layers, Plus } from 'lucide-react';
import { useState } from 'react';
import { columns, NhisTariff } from './columns';
import { NhisTariffsDataTable } from './data-table';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Filters {
    search?: string | null;
    category?: string | null;
    active_only?: boolean;
}

interface Props {
    tariffs: {
        data: NhisTariff[];
        current_page: number;
        from: number | null;
        last_page: number;
        per_page: number;
        to: number | null;
        total: number;
        links: PaginationLink[];
    };
    filters: Filters;
    categories: string[];
}

export default function NhisTariffsIndex({
    tariffs,
    filters,
    categories,
}: Props) {
    const [createModalOpen, setCreateModalOpen] = useState(false);
    const [editModalOpen, setEditModalOpen] = useState(false);
    const [importModalOpen, setImportModalOpen] = useState(false);
    const [selectedTariff, setSelectedTariff] = useState<NhisTariff | null>(
        null,
    );

    const hasActiveFilters = Object.keys(filters).some(
        (key) => filters[key as keyof Filters] !== undefined,
    );

    const handleDelete = (tariff: NhisTariff) => {
        if (
            confirm(
                `Are you sure you want to delete "${tariff.name}"? This action cannot be undone.`,
            )
        ) {
            // Use router.delete when needed
        }
    };

    const handleEdit = (tariff: NhisTariff) => {
        setSelectedTariff(tariff);
        setEditModalOpen(true);
    };

    const handleExport = () => {
        const params = new URLSearchParams();
        if (filters.search) params.set('search', filters.search);
        if (filters.category) params.set('category', filters.category);
        if (filters.active_only) params.set('active_only', 'true');
        window.location.href = `/admin/nhis-tariffs/export?${params.toString()}`;
    };

    const handleDownloadTemplate = () => {
        window.location.href = '/admin/nhis-tariffs/template';
    };

    const stats = {
        total: tariffs.total,
        active: tariffs.data.filter((t) => t.is_active).length,
        categories: categories.length,
    };

    const pagination = {
        current_page: tariffs.current_page,
        from: tariffs.from,
        last_page: tariffs.last_page,
        per_page: tariffs.per_page,
        to: tariffs.to,
        total: tariffs.total,
        links: tariffs.links,
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                { title: 'NHIS Tariffs', href: '' },
            ]}
        >
            <Head title="NHIS Tariffs" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="flex items-center gap-2 text-3xl font-bold text-gray-900 dark:text-gray-100">
                            <FileSpreadsheet className="h-8 w-8" />
                            NHIS Tariff Master
                        </h1>
                        <p className="mt-2 text-gray-600 dark:text-gray-400">
                            Manage NHIS tariff codes and prices
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        {hasActiveFilters && (
                            <Badge variant="secondary">Filters active</Badge>
                        )}
                        <Button onClick={() => setCreateModalOpen(true)}>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Tariff
                        </Button>
                    </div>
                </div>

                {/* Stats Overview */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <StatCard
                        label="Total Tariffs"
                        value={stats.total}
                        icon={<FileSpreadsheet className="h-5 w-5" />}
                        variant="info"
                    />
                    <StatCard
                        label="Active Tariffs"
                        value={stats.active}
                        icon={<CheckCircle className="h-5 w-5" />}
                        variant="success"
                    />
                    <StatCard
                        label="Categories"
                        value={stats.categories}
                        icon={<Layers className="h-5 w-5" />}
                    />
                </div>

                {/* Data Table */}
                <Card>
                    <CardContent className="p-6">
                        <NhisTariffsDataTable
                            columns={columns(handleEdit, handleDelete)}
                            data={tariffs.data}
                            pagination={pagination}
                            filters={filters}
                            categories={categories}
                            onImport={() => setImportModalOpen(true)}
                            onExport={handleExport}
                            onDownloadTemplate={handleDownloadTemplate}
                        />
                    </CardContent>
                </Card>
            </div>

            {/* Create Tariff Modal */}
            <NhisTariffForm
                isOpen={createModalOpen}
                onClose={() => setCreateModalOpen(false)}
                categories={categories}
            />

            {/* Edit Tariff Modal */}
            <NhisTariffForm
                isOpen={editModalOpen}
                onClose={() => {
                    setEditModalOpen(false);
                    setSelectedTariff(null);
                }}
                tariff={selectedTariff}
                categories={categories}
            />

            {/* Import Modal */}
            <ImportNhisTariffModal
                isOpen={importModalOpen}
                onClose={() => setImportModalOpen(false)}
            />
        </AppLayout>
    );
}
