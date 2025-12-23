import { ImportMappingModal } from '@/components/NhisMapping/ImportMappingModal';
import { MappingForm } from '@/components/NhisMapping/MappingForm';
import { StatCard } from '@/components/ui/stat-card';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { FlaskConical, Link2, Pill, Stethoscope } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    createNhisMappingsColumns,
    NhisMappingData,
} from './nhis-mappings-columns';
import { NhisMappingsDataTable } from './nhis-mappings-data-table';

interface Filters {
    search?: string;
    item_type?: string;
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
}

interface Props {
    mappings: {
        data: NhisMappingData[];
        links: PaginationLink[];
        meta: PaginationMeta;
    };
    filters: Filters;
    itemTypes: string[];
}

export default function NhisMappingsIndex({
    mappings,
    filters,
    itemTypes,
}: Props) {
    const [createModalOpen, setCreateModalOpen] = useState(false);
    const [importModalOpen, setImportModalOpen] = useState(false);

    const handleDelete = (mapping: NhisMappingData) => {
        if (
            confirm(
                `Are you sure you want to delete this mapping? This action cannot be undone.`,
            )
        ) {
            router.delete(`/admin/nhis-mappings/${mapping.id}`);
        }
    };

    const columns = useMemo(() => createNhisMappingsColumns(handleDelete), []);

    const stats = useMemo(() => {
        const data = mappings.data || [];
        return {
            total: mappings.meta?.total || data.length,
            drugs: data.filter((m) => m.item_type === 'drug').length,
            labs: data.filter((m) => m.item_type === 'lab_service').length,
            procedures: data.filter((m) => m.item_type === 'procedure').length,
        };
    }, [mappings]);

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                { title: 'NHIS Mappings', href: '' },
            ]}
        >
            <Head title="NHIS Item Mappings" />

            <div className="space-y-6">
                {/* Header */}
                <div>
                    <h1 className="flex items-center gap-2 text-3xl font-bold tracking-tight">
                        <Link2 className="h-8 w-8" />
                        NHIS Item Mappings
                    </h1>
                    <p className="text-muted-foreground">
                        Map hospital items to NHIS tariff codes
                    </p>
                </div>

                {/* Stats Overview */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <StatCard
                        label="Total Mappings"
                        value={stats.total}
                        icon={<Link2 className="h-5 w-5" />}
                        variant="info"
                    />
                    <StatCard
                        label="Drugs"
                        value={stats.drugs}
                        icon={<Pill className="h-5 w-5" />}
                        variant="success"
                    />
                    <StatCard
                        label="Lab Services"
                        value={stats.labs}
                        icon={<FlaskConical className="h-5 w-5" />}
                    />
                    <StatCard
                        label="Procedures"
                        value={stats.procedures}
                        icon={<Stethoscope className="h-5 w-5" />}
                        variant="warning"
                    />
                </div>

                {/* DataTable */}
                <NhisMappingsDataTable
                    columns={columns}
                    data={mappings.data || []}
                    pagination={mappings}
                    onAddClick={() => setCreateModalOpen(true)}
                    onImportClick={() => setImportModalOpen(true)}
                    searchValue={filters.search}
                    itemTypeFilter={filters.item_type}
                    itemTypes={itemTypes}
                />
            </div>

            {/* Create Mapping Modal */}
            <MappingForm
                isOpen={createModalOpen}
                onClose={() => setCreateModalOpen(false)}
                itemTypes={itemTypes}
            />

            {/* Import Modal */}
            <ImportMappingModal
                isOpen={importModalOpen}
                onClose={() => setImportModalOpen(false)}
            />
        </AppLayout>
    );
}
