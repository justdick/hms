import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    CheckCircle2,
    FlaskConical,
    Plus,
    Settings,
} from 'lucide-react';
import { useState } from 'react';
import { columns, LabService } from './columns';
import CreateTestModal from './CreateTestModal';
import { DataTable } from './data-table';

interface Props {
    labServices: LabService[];
    categories: string[];
}

export default function LabConfigurationIndex({
    labServices,
    categories,
}: Props) {
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [editingService, setEditingService] = useState<LabService | null>(
        null,
    );

    const hasParameters = (service: LabService) => {
        return (
            service.test_parameters &&
            service.test_parameters.parameters &&
            service.test_parameters.parameters.length > 0
        );
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Laboratory', href: '/lab' },
                { title: 'Configuration', href: '/lab/services/configuration' },
            ]}
        >
            <Head title="Lab Test Configuration" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="ghost" size="sm" asChild>
                            <Link href="/lab">
                                <ArrowLeft className="mr-1 h-4 w-4" />
                                Back to Lab Dashboard
                            </Link>
                        </Button>
                        <div>
                            <h1 className="flex items-center gap-2 text-2xl font-bold">
                                <Settings className="h-6 w-6" />
                                Lab Test Configuration
                            </h1>
                            <p className="text-muted-foreground">
                                Configure test parameters for dynamic result
                                entry forms
                            </p>
                        </div>
                    </div>
                    <Button onClick={() => setShowCreateModal(true)}>
                        <Plus className="mr-2 h-4 w-4" />
                        Add New Test
                    </Button>
                </div>

                {/* Stats Summary */}
                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Total Services
                            </CardTitle>
                            <FlaskConical className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {labServices.length}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Available lab services
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Configured
                            </CardTitle>
                            <CheckCircle2 className="h-4 w-4 text-green-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600">
                                {labServices.filter(hasParameters).length}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                With test parameters
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Pending
                            </CardTitle>
                            <AlertCircle className="h-4 w-4 text-orange-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-orange-600">
                                {
                                    labServices.filter(
                                        (service) => !hasParameters(service),
                                    ).length
                                }
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Need configuration
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Lab Services DataTable */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <FlaskConical className="h-5 w-5" />
                            Laboratory Test Services
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <DataTable
                            columns={columns(setEditingService)}
                            data={labServices}
                        />
                    </CardContent>
                </Card>
            </div>

            <CreateTestModal
                open={showCreateModal}
                onClose={() => setShowCreateModal(false)}
                categories={categories}
            />

            {editingService && (
                <CreateTestModal
                    open={!!editingService}
                    onClose={() => setEditingService(null)}
                    categories={categories}
                    editingService={editingService}
                />
            )}
        </AppLayout>
    );
}
