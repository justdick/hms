import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Bandage,
    Download,
    Edit,
    Plus,
    Stethoscope,
    Trash2,
    Upload,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import ProcedureTypeModal from './ProcedureTypeModal';

interface ProcedureType {
    id: number;
    name: string;
    code: string;
    category: string;
    type: 'minor' | 'major';
    description: string | null;
    price: number;
    is_active: boolean;
}

interface Props {
    procedureTypes: ProcedureType[];
    categories: string[];
}

export default function MinorProcedureConfigurationIndex({
    procedureTypes,
    categories,
}: Props) {
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [editingType, setEditingType] = useState<ProcedureType | null>(null);
    const [activeTab, setActiveTab] = useState<'all' | 'minor' | 'major'>(
        'all',
    );
    const [isImporting, setIsImporting] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const handleImport = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;

        setIsImporting(true);
        router.post(
            '/admin/procedure-types/import',
            { file },
            {
                forceFormData: true,
                onSuccess: () => {
                    toast.success('Import completed');
                    if (fileInputRef.current) {
                        fileInputRef.current.value = '';
                    }
                },
                onError: (errors) => {
                    toast.error(errors.file || errors.error || 'Import failed');
                },
                onFinish: () => {
                    setIsImporting(false);
                },
            },
        );
    };

    const handleDelete = (procedureType: ProcedureType) => {
        if (
            !confirm(
                `Are you sure you want to delete "${procedureType.name}"? This action cannot be undone.`,
            )
        ) {
            return;
        }

        router.delete(`/minor-procedures/types/${procedureType.id}`, {
            onSuccess: () => {
                toast.success('Procedure type deleted successfully');
            },
            onError: (errors) => {
                toast.error(errors.error || 'Failed to delete procedure type');
            },
        });
    };

    const filteredProcedures = procedureTypes.filter((type) => {
        if (activeTab === 'all') return true;
        return type.type === activeTab;
    });

    const groupedByCategory = filteredProcedures.reduce(
        (acc, type) => {
            if (!acc[type.category]) {
                acc[type.category] = [];
            }
            acc[type.category].push(type);
            return acc;
        },
        {} as Record<string, ProcedureType[]>,
    );

    const minorCount = procedureTypes.filter((t) => t.type === 'minor').length;
    const majorCount = procedureTypes.filter((t) => t.type === 'major').length;

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Minor Procedures', href: '/minor-procedures' },
                {
                    title: 'Configuration',
                    href: '/minor-procedures/types',
                },
            ]}
        >
            <Head title="Procedure Types Configuration" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="ghost" size="sm" asChild>
                            <Link href="/minor-procedures">
                                <ArrowLeft className="mr-1 h-4 w-4" />
                                Back to Minor Procedures
                            </Link>
                        </Button>
                        <div>
                            <h1 className="flex items-center gap-2 text-2xl font-bold">
                                <Bandage className="h-6 w-6" />
                                Procedure Types Configuration
                            </h1>
                            <p className="text-muted-foreground">
                                Manage procedure types and pricing
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            asChild
                        >
                            <a href="/admin/procedure-types/template">
                                <Download className="mr-2 h-4 w-4" />
                                Template
                            </a>
                        </Button>
                        <input
                            ref={fileInputRef}
                            type="file"
                            accept=".csv,.xlsx,.xls"
                            onChange={handleImport}
                            className="hidden"
                            id="procedure-import"
                        />
                        <Button
                            variant="outline"
                            onClick={() => fileInputRef.current?.click()}
                            disabled={isImporting}
                        >
                            <Upload className="mr-2 h-4 w-4" />
                            {isImporting ? 'Importing...' : 'Import'}
                        </Button>
                        <Button onClick={() => setShowCreateModal(true)}>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Procedure Type
                        </Button>
                    </div>
                </div>

                {/* Stats Summary */}
                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Total Types
                            </CardTitle>
                            <Bandage className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {procedureTypes.length}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Available procedure types
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Minor Procedures
                            </CardTitle>
                            <Bandage className="h-4 w-4 text-blue-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-blue-600">
                                {minorCount}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                OPD procedures
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Major Procedures
                            </CardTitle>
                            <Stethoscope className="h-4 w-4 text-purple-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-purple-600">
                                {majorCount}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Theatre procedures
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Active
                            </CardTitle>
                            <Bandage className="h-4 w-4 text-green-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600">
                                {
                                    procedureTypes.filter((t) => t.is_active)
                                        .length
                                }
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Currently active
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Procedure Types Tabs */}
                <Tabs
                    value={activeTab}
                    onValueChange={(v) => setActiveTab(v as any)}
                >
                    <TabsList>
                        <TabsTrigger value="all">
                            All Procedures ({procedureTypes.length})
                        </TabsTrigger>
                        <TabsTrigger value="minor">
                            Minor ({minorCount})
                        </TabsTrigger>
                        <TabsTrigger value="major">
                            Major/Theatre ({majorCount})
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value={activeTab} className="mt-6 space-y-6">
                        {/* Procedure Types by Category */}
                        {Object.entries(groupedByCategory).map(
                            ([category, types]) => (
                                <Card key={category}>
                                    <CardHeader>
                                        <CardTitle className="capitalize">
                                            {category.replace(/_/g, ' ')}
                                        </CardTitle>
                                        <CardDescription>
                                            {types.length} procedure
                                            {types.length !== 1 ? 's' : ''}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>Name</TableHead>
                                                    <TableHead>Code</TableHead>
                                                    <TableHead>Type</TableHead>
                                                    <TableHead>
                                                        Description
                                                    </TableHead>
                                                    <TableHead className="text-right">
                                                        Price (KES)
                                                    </TableHead>
                                                    <TableHead>
                                                        Status
                                                    </TableHead>
                                                    <TableHead className="text-right">
                                                        Actions
                                                    </TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {types.map((type) => (
                                                    <TableRow key={type.id}>
                                                        <TableCell className="font-medium">
                                                            {type.name}
                                                        </TableCell>
                                                        <TableCell>
                                                            <code className="rounded bg-muted px-2 py-1 text-xs">
                                                                {type.code}
                                                            </code>
                                                        </TableCell>
                                                        <TableCell>
                                                            <Badge
                                                                variant="outline"
                                                                className={
                                                                    type.type ===
                                                                    'major'
                                                                        ? 'border-purple-200 bg-purple-100 text-purple-700 dark:bg-purple-900/20 dark:text-purple-400'
                                                                        : 'border-blue-200 bg-blue-100 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400'
                                                                }
                                                            >
                                                                {type.type ===
                                                                'major'
                                                                    ? 'Major'
                                                                    : 'Minor'}
                                                            </Badge>
                                                        </TableCell>
                                                        <TableCell className="max-w-md truncate text-sm text-muted-foreground">
                                                            {type.description ||
                                                                'No description'}
                                                        </TableCell>
                                                        <TableCell className="text-right font-mono">
                                                            {Number(
                                                                type.price,
                                                            ).toFixed(2)}
                                                        </TableCell>
                                                        <TableCell>
                                                            {type.is_active ? (
                                                                <span className="inline-flex items-center rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-700 dark:bg-green-900/20 dark:text-green-400">
                                                                    Active
                                                                </span>
                                                            ) : (
                                                                <span className="inline-flex items-center rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-400">
                                                                    Inactive
                                                                </span>
                                                            )}
                                                        </TableCell>
                                                        <TableCell className="text-right">
                                                            <div className="flex justify-end gap-2">
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        setEditingType(
                                                                            type,
                                                                        )
                                                                    }
                                                                >
                                                                    <Edit className="h-4 w-4" />
                                                                </Button>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        handleDelete(
                                                                            type,
                                                                        )
                                                                    }
                                                                    className="text-destructive hover:text-destructive"
                                                                >
                                                                    <Trash2 className="h-4 w-4" />
                                                                </Button>
                                                            </div>
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    </CardContent>
                                </Card>
                            ),
                        )}

                        {filteredProcedures.length === 0 && (
                            <Card>
                                <CardContent className="flex flex-col items-center justify-center py-12">
                                    <Bandage className="mb-4 h-12 w-12 text-muted-foreground" />
                                    <h3 className="mb-2 text-lg font-semibold">
                                        No Procedure Types
                                    </h3>
                                    <p className="mb-4 text-center text-sm text-muted-foreground">
                                        {activeTab === 'all'
                                            ? 'Get started by adding your first procedure type'
                                            : `No ${activeTab} procedures found`}
                                    </p>
                                    <Button
                                        onClick={() => setShowCreateModal(true)}
                                    >
                                        <Plus className="mr-2 h-4 w-4" />
                                        Add Procedure Type
                                    </Button>
                                </CardContent>
                            </Card>
                        )}
                    </TabsContent>
                </Tabs>
            </div>

            <ProcedureTypeModal
                open={showCreateModal}
                onClose={() => setShowCreateModal(false)}
                categories={categories}
            />

            {editingType && (
                <ProcedureTypeModal
                    open={!!editingType}
                    onClose={() => setEditingType(null)}
                    categories={categories}
                    editingType={editingType}
                />
            )}
        </AppLayout>
    );
}
