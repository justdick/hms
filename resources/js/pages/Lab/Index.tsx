import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { StatCard } from '@/components/ui/stat-card';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
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

interface PaginatedOrders {
    data: GroupedConsultation[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
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
}

interface Props {
    groupedOrders: PaginatedOrders;
    stats: Stats;
    filters: Filters;
}

export default function LabIndex({ groupedOrders, stats, filters }: Props) {
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
                    <Button asChild>
                        <Link href="/lab/services/configuration">
                            <Settings className="mr-2 h-4 w-4" />
                            Configure Test Parameters
                        </Link>
                    </Button>
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
                        label="Completed Today"
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
                            filters={filters}
                        />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
