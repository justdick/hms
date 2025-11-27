import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    CheckCircle2,
    Download,
    FlaskConical,
    Plus,
    Settings,
    Upload,
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
    const [importModalOpen, setImportModalOpen] = useState(false);
    const [editingService, setEditingService] = useState<LabService | null>(
        null,
    );
    const { data, setData, post, processing, reset } = useForm<{
        file: File | null;
    }>({
        file: null,
    });

    const handleImport = (e: React.FormEvent) => {
        e.preventDefault();
        if (!data.file) return;

        post('/lab/services/import', {
            forceFormData: true,
            onSuccess: () => {
                setImportModalOpen(false);
                reset();
            },
        });
    };

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
                    <div className="flex gap-2">
                        <Dialog
                            open={importModalOpen}
                            onOpenChange={setImportModalOpen}
                        >
                            <DialogTrigger asChild>
                                <Button variant="outline">
                                    <Upload className="mr-2 h-4 w-4" />
                                    Import
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>Import Lab Services</DialogTitle>
                                    <DialogDescription>
                                        Upload a CSV or Excel file to bulk import
                                        lab services.
                                    </DialogDescription>
                                </DialogHeader>
                                <form onSubmit={handleImport}>
                                    <div className="space-y-4 py-4">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="w-full"
                                            asChild
                                        >
                                            <a href="/lab/services/import/template">
                                                <Download className="mr-2 h-4 w-4" />
                                                Download Template
                                            </a>
                                        </Button>
                                        <div className="space-y-2">
                                            <Label htmlFor="file">Select File</Label>
                                            <Input
                                                id="file"
                                                type="file"
                                                accept=".csv,.xlsx,.xls"
                                                onChange={(e) =>
                                                    setData(
                                                        'file',
                                                        e.target.files?.[0] || null,
                                                    )
                                                }
                                            />
                                        </div>
                                    </div>
                                    <DialogFooter>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => setImportModalOpen(false)}
                                        >
                                            Cancel
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={!data.file || processing}
                                        >
                                            {processing ? 'Importing...' : 'Import'}
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>
                        <Button onClick={() => setShowCreateModal(true)}>
                            <Plus className="mr-2 h-4 w-4" />
                            Add New Test
                        </Button>
                    </div>
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
