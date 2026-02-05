import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    DateFilterPresets,
    DateFilterValue,
} from '@/components/ui/date-filter-presets';
import { StatCard } from '@/components/ui/stat-card';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import {
    Activity,
    FileText,
    FlaskConical,
    Settings,
    TestTube,
} from 'lucide-react';
import {
    GroupedConsultation,
    groupedConsultationColumns,
} from './grouped-consultations-columns';
import { LabOrdersDataTable } from './lab-orders-data-table';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedOrders {
    data: GroupedConsultation[];
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
    sample_collected: number;
    in_progress: number;
    completed_today: number;
}

interface Filters {
    status: string;
    priority?: string;
    category?: string;
    search?: string;
    date_from?: string;
    date_to?: string;
    date_preset?: string;
}

interface Props {
    groupedOrders: PaginatedOrders;
    stats: Stats;
    filters: Filters;
}

export default function LabIndex({ groupedOrders, stats, filters }: Props) {
    const handleDateFilterChange = (value: DateFilterValue) => {
        router.get(
            '/lab',
            {
                ...filters,
                date_from: value.from || undefined,
                date_to: value.to || undefined,
                date_preset: value.preset || undefined,
                page: 1,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const getCompletedLabel = () => {
        const presetLabels: Record<string, string> = {
            today: 'Completed Today',
            yesterday: 'Completed Yesterday',
            this_week: 'Completed This Week',
            last_week: 'Completed Last Week',
            this_month: 'Completed This Month',
            last_month: 'Completed Last Month',
            custom: 'Completed (Custom Range)',
        };
        return presetLabels[filters.date_preset || ''] || 'Completed Today';
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'Laboratory', href: '/lab' }]}>
            <Head title="Laboratory Dashboard" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">
                            Laboratory Dashboard
                        </h1>
                        <p className="text-muted-foreground">
                            Manage lab orders and process test results
                        </p>
                    </div>
                    <div className="flex items-center gap-4">
                        <DateFilterPresets
                            value={{
                                from: filters.date_from,
                                to: filters.date_to,
                                preset: filters.date_preset || 'today',
                            }}
                            onChange={handleDateFilterChange}
                            variant="primary"
                        />
                        <Button asChild>
                            <Link href="/lab/services/configuration">
                                <Settings className="mr-2 h-4 w-4" />
                                Configure Test Parameters
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Stats Cards */}
                <div className="grid gap-4 md:grid-cols-4">
                    <StatCard
                        label="Pending Orders"
                        value={stats.ordered}
                        icon={<FileText className="h-4 w-4" />}
                        variant="warning"
                    />
                    <StatCard
                        label="Samples Collected"
                        value={stats.sample_collected}
                        icon={<TestTube className="h-4 w-4" />}
                        variant="info"
                    />
                    <StatCard
                        label="In Progress"
                        value={stats.in_progress}
                        icon={<FlaskConical className="h-4 w-4" />}
                        variant="info"
                    />
                    <StatCard
                        label={getCompletedLabel()}
                        value={stats.completed_today}
                        icon={<Activity className="h-4 w-4" />}
                        variant="success"
                    />
                </div>

                {/* Grouped Orders DataTable */}
                <Card>
                    <CardHeader>
                        <CardTitle>
                            Patient Lab Orders ({groupedOrders.total} orders)
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <LabOrdersDataTable
                            columns={groupedConsultationColumns}
                            data={groupedOrders.data}
                            pagination={groupedOrders}
                            filters={filters}
                        />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
