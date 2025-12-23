import { GdrgTariffForm } from '@/components/GdrgTariff/GdrgTariffForm';
import { ImportGdrgModal } from '@/components/GdrgTariff/ImportGdrgModal';
import { StatCard } from '@/components/ui/stat-card';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { Activity, CheckCircle, Layers } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    createGdrgTariffsColumns,
    GdrgTariffData,
} from './gdrg-tariffs-columns';
import { GdrgTariffsDataTable } from './gdrg-tariffs-data-table';

interface Filters {
    search?: string;
    mdc_category?: string;
    age_category?: string;
    active_only?: boolean;
}

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

interface Props {
    tariffs: {
        data: GdrgTariffData[];
        links: {
            first: string | null;
            last: string | null;
            prev: string | null;
            next: string | null;
        };
        meta: PaginationMeta;
    };
    filters: Filters;
    mdcCategories: string[];
    ageCategories: string[];
}

export default function GdrgTariffsIndex({
    tariffs,
    filters,
    mdcCategories,
    ageCategories,
}: Props) {
    const [createModalOpen, setCreateModalOpen] = useState(false);
    const [editModalOpen, setEditModalOpen] = useState(false);
    const [importModalOpen, setImportModalOpen] = useState(false);
    const [selectedTariff, setSelectedTariff] = useState<GdrgTariffData | null>(
        null,
    );

    const handleEdit = (tariff: GdrgTariffData) => {
        setSelectedTariff(tariff);
        setEditModalOpen(true);
    };

    const handleDelete = (tariff: GdrgTariffData) => {
        if (
            confirm(
                `Are you sure you want to delete "${tariff.name}"? This action cannot be undone.`,
            )
        ) {
            router.delete(`/admin/gdrg-tariffs/${tariff.id}`);
        }
    };

    const columns = useMemo(
        () => createGdrgTariffsColumns(handleEdit, handleDelete),
        [],
    );

    const stats = useMemo(() => {
        const data = tariffs.data || [];
        return {
            total: tariffs.meta?.total || data.length,
            active: data.filter((t) => t.is_active).length,
            mdcCategories: mdcCategories.length,
        };
    }, [tariffs, mdcCategories]);

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                { title: 'G-DRG Tariffs', href: '' },
            ]}
        >
            <Head title="G-DRG Tariffs" />

            <div className="space-y-6">
                {/* Header */}
                <div>
                    <h1 className="flex items-center gap-2 text-3xl font-bold tracking-tight">
                        <Activity className="h-8 w-8" />
                        G-DRG Tariff Management
                    </h1>
                    <p className="text-muted-foreground">
                        Manage Ghana Diagnosis Related Groups tariff codes and
                        prices
                    </p>
                </div>

                {/* Stats Overview */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <StatCard
                        label="Total Tariffs"
                        value={stats.total}
                        icon={<Activity className="h-5 w-5" />}
                        variant="info"
                    />
                    <StatCard
                        label="Active Tariffs"
                        value={stats.active}
                        icon={<CheckCircle className="h-5 w-5" />}
                        variant="success"
                    />
                    <StatCard
                        label="MDC Categories"
                        value={stats.mdcCategories}
                        icon={<Layers className="h-5 w-5" />}
                    />
                </div>

                {/* DataTable */}
                <GdrgTariffsDataTable
                    columns={columns}
                    data={tariffs.data || []}
                    pagination={tariffs}
                    onAddClick={() => setCreateModalOpen(true)}
                    onImportClick={() => setImportModalOpen(true)}
                    filters={filters}
                    mdcCategories={mdcCategories}
                    ageCategories={ageCategories}
                />
            </div>

            {/* Create Tariff Modal */}
            <GdrgTariffForm
                isOpen={createModalOpen}
                onClose={() => setCreateModalOpen(false)}
                mdcCategories={mdcCategories}
                ageCategories={ageCategories}
            />

            {/* Edit Tariff Modal */}
            <GdrgTariffForm
                isOpen={editModalOpen}
                onClose={() => {
                    setEditModalOpen(false);
                    setSelectedTariff(null);
                }}
                tariff={selectedTariff}
                mdcCategories={mdcCategories}
                ageCategories={ageCategories}
            />

            {/* Import Modal */}
            <ImportGdrgModal
                isOpen={importModalOpen}
                onClose={() => setImportModalOpen(false)}
            />
        </AppLayout>
    );
}
