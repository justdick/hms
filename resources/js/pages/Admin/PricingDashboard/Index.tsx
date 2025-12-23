import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { FileSpreadsheet } from 'lucide-react';
import { useMemo, useState } from 'react';
import { BulkEditModal } from './components/BulkEditModal';
import { ItemHistoryModal } from './components/ItemHistoryModal';
import { PlanSelector } from './components/PlanSelector';
import { PricingImportModal } from './components/PricingImportModal';
import {
    PricingSummaryCards,
    type PricingSummary,
} from './components/PricingSummaryCards';
import { createPricingColumns } from './pricing-columns';
import { PricingDataTable } from './pricing-data-table';

export interface PricingItem {
    id: number;
    type: string;
    code: string | null;
    name: string;
    category: string;
    cash_price: number | null;
    insurance_tariff: number | null;
    copay_amount: number | null;
    coverage_value: number | null;
    coverage_type: string | null;
    is_mapped: boolean;
    is_unmapped: boolean;
    nhis_code: string | null;
    coverage_rule_id: number | null;
    pricing_status:
        | 'priced'
        | 'unpriced'
        | 'nhis_mapped'
        | 'flexible_copay'
        | 'not_mapped';
}

export interface InsurancePlan {
    id: number;
    name: string;
    provider_name: string | null;
    is_nhis: boolean;
}

interface PaginatedData<T> {
    data: T[];
    links: any[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

interface Filters {
    plan_id?: string | null;
    category?: string | null;
    search?: string | null;
    unmapped_only?: boolean;
    pricing_status?: string | null;
}

interface Props {
    items: PaginatedData<PricingItem>;
    categories: string[];
    selectedPlan: InsurancePlan | null;
    isNhis: boolean;
    insurancePlans: InsurancePlan[];
    filters: Filters;
    summary: PricingSummary;
}

export default function PricingDashboardIndex({
    items,
    categories,
    selectedPlan,
    isNhis,
    insurancePlans,
    filters,
    summary,
}: Props) {
    const [selectedItems, setSelectedItems] = useState<PricingItem[]>([]);
    const [bulkEditOpen, setBulkEditOpen] = useState(false);
    const [importModalOpen, setImportModalOpen] = useState(false);
    const [historyModalOpen, setHistoryModalOpen] = useState(false);
    const [historyItem, setHistoryItem] = useState<PricingItem | null>(null);

    const handlePlanChange = (planId: string | null) => {
        const params = new URLSearchParams(window.location.search);
        if (planId) {
            params.set('plan_id', planId);
        } else {
            params.delete('plan_id');
        }
        // Clear selection when plan changes
        setSelectedItems([]);
        window.location.href = `/admin/pricing-dashboard?${params.toString()}`;
    };

    const handleSummaryFilterClick = (filter: string) => {
        const params = new URLSearchParams(window.location.search);
        if (filter === 'all' || filter === filters.pricing_status) {
            params.delete('pricing_status');
        } else {
            params.set('pricing_status', filter);
        }
        window.location.href = `/admin/pricing-dashboard?${params.toString()}`;
    };

    const handleViewHistory = (item: PricingItem) => {
        setHistoryItem(item);
        setHistoryModalOpen(true);
    };

    const handleBulkEdit = () => {
        if (selectedItems.length > 0 && selectedPlan) {
            setBulkEditOpen(true);
        }
    };

    const handleExport = () => {
        const params = new URLSearchParams();
        if (filters.plan_id) params.set('plan_id', filters.plan_id);
        if (filters.category) params.set('category', filters.category);
        if (filters.search) params.set('search', filters.search);
        window.location.href = `/admin/pricing-dashboard/export?${params.toString()}`;
    };

    const handleDownloadTemplate = () => {
        const params = new URLSearchParams();
        if (filters.plan_id) params.set('plan_id', filters.plan_id);
        if (filters.category) params.set('category', filters.category);
        window.location.href = `/admin/pricing-dashboard/import-template?${params.toString()}`;
    };

    const columns = useMemo(
        () =>
            createPricingColumns(
                selectedPlan,
                isNhis,
                handleViewHistory,
                setSelectedItems,
                selectedItems,
            ),
        [selectedPlan, isNhis, selectedItems],
    );

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                { title: 'Pricing Dashboard', href: '' },
            ]}
        >
            <Head title="Pricing Dashboard" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="flex items-center gap-2 text-3xl font-bold text-gray-900 dark:text-gray-100">
                            <FileSpreadsheet className="h-8 w-8" />
                            Unified Pricing Dashboard
                        </h1>
                        <p className="mt-2 text-gray-600 dark:text-gray-400">
                            Manage cash prices and insurance coverage for all
                            services
                        </p>
                    </div>
                </div>

                {/* Plan Selector */}
                <Card>
                    <CardContent className="p-4">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-center gap-4">
                                <Label
                                    htmlFor="plan-selector"
                                    className="whitespace-nowrap"
                                >
                                    Insurance Plan:
                                </Label>
                                <PlanSelector
                                    plans={insurancePlans}
                                    selectedPlanId={selectedPlan?.id ?? null}
                                    onPlanChange={handlePlanChange}
                                />
                            </div>
                            {selectedPlan && (
                                <div className="flex items-center gap-2">
                                    {isNhis ? (
                                        <Badge
                                            variant="default"
                                            className="bg-green-600"
                                        >
                                            NHIS Plan
                                        </Badge>
                                    ) : (
                                        <Badge variant="secondary">
                                            Private Insurance
                                        </Badge>
                                    )}
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Pricing Summary Cards */}
                <PricingSummaryCards
                    summary={summary}
                    isNhis={isNhis}
                    onFilterClick={handleSummaryFilterClick}
                    activeFilter={filters.pricing_status}
                />

                {/* Data Table */}
                <Card>
                    <CardContent className="p-6">
                        <PricingDataTable
                            columns={columns}
                            data={items.data}
                            pagination={{
                                current_page: items.current_page,
                                from: items.from,
                                last_page: items.last_page,
                                per_page: items.per_page,
                                to: items.to,
                                total: items.total,
                                links: items.links,
                            }}
                            filters={filters}
                            categories={categories}
                            isNhis={isNhis}
                            selectedPlan={selectedPlan}
                            selectedItems={selectedItems}
                            onBulkEdit={handleBulkEdit}
                            onExport={handleExport}
                            onImport={() => setImportModalOpen(true)}
                            onDownloadTemplate={handleDownloadTemplate}
                        />
                    </CardContent>
                </Card>
            </div>

            {/* Bulk Edit Modal */}
            {selectedPlan && (
                <BulkEditModal
                    open={bulkEditOpen}
                    onClose={() => {
                        setBulkEditOpen(false);
                        setSelectedItems([]);
                    }}
                    selectedItems={selectedItems}
                    planId={selectedPlan.id}
                    isNhis={isNhis}
                />
            )}

            {/* Import Modal */}
            <PricingImportModal
                open={importModalOpen}
                onClose={() => setImportModalOpen(false)}
                planId={selectedPlan?.id ?? null}
            />

            {/* History Modal */}
            {historyItem && (
                <ItemHistoryModal
                    open={historyModalOpen}
                    onClose={() => {
                        setHistoryModalOpen(false);
                        setHistoryItem(null);
                    }}
                    item={historyItem}
                />
            )}
        </AppLayout>
    );
}
