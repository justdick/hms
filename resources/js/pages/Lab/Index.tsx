import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Pending Orders
                            </CardTitle>
                            <FileText className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {stats.ordered}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Waiting for sample collection
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Samples Collected
                            </CardTitle>
                            <TestTube className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {stats.sample_collected}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Ready for processing
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                In Progress
                            </CardTitle>
                            <FlaskConical className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {stats.in_progress}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Currently being processed
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Completed Today
                            </CardTitle>
                            <Activity className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {stats.completed_today}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Tests completed today
                            </p>
                        </CardContent>
                    </Card>
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
