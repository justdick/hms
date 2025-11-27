import { ImportMappingModal } from '@/components/NhisMapping/ImportMappingModal';
import { MappingForm } from '@/components/NhisMapping/MappingForm';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import {
    Download,
    Filter,
    Link2,
    Link2Off,
    Plus,
    Search,
    Trash2,
    Upload,
    X,
} from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';

interface NhisTariff {
    id: number;
    nhis_code: string;
    name: string;
    category: string;
    price: number;
    formatted_price: string;
    display_name: string;
}

interface NhisMapping {
    id: number;
    item_type: string;
    item_id: number;
    item_code: string;
    nhis_tariff_id: number;
    item_type_label: string;
    nhis_tariff: NhisTariff;
    created_at: string;
}

interface Filters {
    search?: string;
    item_type?: string;
}

interface Props {
    mappings: {
        data: NhisMapping[];
        links: any;
        meta: any;
    };
    filters: Filters;
    itemTypes: string[];
}

const itemTypeLabels: Record<string, string> = {
    drug: 'Drug',
    lab_service: 'Lab Service',
    procedure: 'Procedure',
    consumable: 'Consumable',
};

export default function NhisMappingsIndex({
    mappings,
    filters,
    itemTypes,
}: Props) {
    const [showFilters, setShowFilters] = useState(false);
    const [localFilters, setLocalFilters] = useState<Filters>(filters);
    const [createModalOpen, setCreateModalOpen] = useState(false);
    const [importModalOpen, setImportModalOpen] = useState(false);

    useEffect(() => {
        setLocalFilters(filters);
    }, [filters]);

    const handleFilterChange = (key: keyof Filters, value: string) => {
        setLocalFilters((prev) => ({
            ...prev,
            [key]: value === 'all' || value === '' ? undefined : value,
        }));
    };

    const handleApplyFilters = (e: FormEvent) => {
        e.preventDefault();
        router.get('/admin/nhis-mappings', localFilters as any, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleClearFilters = () => {
        setLocalFilters({});
        router.get(
            '/admin/nhis-mappings',
            {},
            { preserveState: true, preserveScroll: true },
        );
    };

    const hasActiveFilters = Object.keys(filters).some(
        (key) => filters[key as keyof Filters] !== undefined,
    );

    const handleDelete = (mapping: NhisMapping) => {
        if (
            confirm(
                `Are you sure you want to delete this mapping? This action cannot be undone.`,
            )
        ) {
            router.delete(`/admin/nhis-mappings/${mapping.id}`);
        }
    };

    const stats = {
        total: mappings.meta?.total || mappings.data.length,
        drugs: mappings.data.filter((m) => m.item_type === 'drug').length,
        labs: mappings.data.filter((m) => m.item_type === 'lab_service').length,
        procedures: mappings.data.filter((m) => m.item_type === 'procedure')
            .length,
    };

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
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="flex items-center gap-2 text-3xl font-bold text-gray-900 dark:text-gray-100">
                            <Link2 className="h-8 w-8" />
                            NHIS Item Mappings
                        </h1>
                        <p className="mt-2 text-gray-600 dark:text-gray-400">
                            Map hospital items to NHIS tariff codes
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        {hasActiveFilters && (
                            <Badge variant="secondary">Filters active</Badge>
                        )}
                        <Button
                            variant="outline"
                            onClick={() => setShowFilters(!showFilters)}
                        >
                            <Filter className="mr-2 h-4 w-4" />
                            {showFilters ? 'Hide Filters' : 'Show Filters'}
                        </Button>
                        <Button variant="outline" asChild>
                            <a href="/admin/nhis-mappings/unmapped/export">
                                <Download className="mr-2 h-4 w-4" />
                                Export Unmapped
                            </a>
                        </Button>
                        <Button variant="outline" asChild>
                            <a href="/admin/nhis-mappings/mapped/export">
                                <Download className="mr-2 h-4 w-4" />
                                Export Mapped
                            </a>
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => setImportModalOpen(true)}
                        >
                            <Upload className="mr-2 h-4 w-4" />
                            Import
                        </Button>
                        <Button onClick={() => setCreateModalOpen(true)}>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Mapping
                        </Button>
                    </div>
                </div>

                {/* Stats Overview */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Total Mappings
                                    </p>
                                    <p className="text-3xl font-bold text-gray-900 dark:text-gray-100">
                                        {stats.total}
                                    </p>
                                </div>
                                <Link2 className="h-8 w-8 text-blue-600" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Drugs
                                    </p>
                                    <p className="text-3xl font-bold text-green-600">
                                        {stats.drugs}
                                    </p>
                                </div>
                                <Badge variant="outline">Drug</Badge>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Lab Services
                                    </p>
                                    <p className="text-3xl font-bold text-purple-600">
                                        {stats.labs}
                                    </p>
                                </div>
                                <Badge variant="outline">Lab</Badge>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Procedures
                                    </p>
                                    <p className="text-3xl font-bold text-orange-600">
                                        {stats.procedures}
                                    </p>
                                </div>
                                <Badge variant="outline">Procedure</Badge>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters Panel */}
                {showFilters && (
                    <Card>
                        <CardContent className="p-6">
                            <form
                                onSubmit={handleApplyFilters}
                                className="space-y-4"
                            >
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="search">Search</Label>
                                        <div className="relative">
                                            <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-500" />
                                            <Input
                                                id="search"
                                                placeholder="Item code, NHIS code, name..."
                                                value={
                                                    localFilters.search || ''
                                                }
                                                onChange={(e) =>
                                                    handleFilterChange(
                                                        'search',
                                                        e.target.value,
                                                    )
                                                }
                                                className="pl-9"
                                            />
                                        </div>
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="item_type">
                                            Item Type
                                        </Label>
                                        <Select
                                            value={
                                                localFilters.item_type || 'all'
                                            }
                                            onValueChange={(value) =>
                                                handleFilterChange(
                                                    'item_type',
                                                    value,
                                                )
                                            }
                                        >
                                            <SelectTrigger id="item_type">
                                                <SelectValue placeholder="All types" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">
                                                    All types
                                                </SelectItem>
                                                {itemTypes.map((type) => (
                                                    <SelectItem
                                                        key={type}
                                                        value={type}
                                                    >
                                                        {itemTypeLabels[type] ||
                                                            type}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Button type="submit">Apply Filters</Button>
                                    {hasActiveFilters && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={handleClearFilters}
                                        >
                                            <X className="mr-2 h-4 w-4" />
                                            Clear All Filters
                                        </Button>
                                    )}
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {/* Mappings Table */}
                <Card>
                    <CardContent className="p-0">
                        {mappings.data.length > 0 ? (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Item Type</TableHead>
                                            <TableHead>Item Code</TableHead>
                                            <TableHead>NHIS Code</TableHead>
                                            <TableHead>
                                                NHIS Tariff Name
                                            </TableHead>
                                            <TableHead className="text-right">
                                                NHIS Price
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Actions
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {mappings.data.map((mapping) => (
                                            <TableRow key={mapping.id}>
                                                <TableCell>
                                                    <Badge variant="outline">
                                                        {
                                                            mapping.item_type_label
                                                        }
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="font-mono font-medium">
                                                    {mapping.item_code}
                                                </TableCell>
                                                <TableCell className="font-mono">
                                                    {mapping.nhis_tariff
                                                        ?.nhis_code || '-'}
                                                </TableCell>
                                                <TableCell>
                                                    {mapping.nhis_tariff
                                                        ?.name || '-'}
                                                </TableCell>
                                                <TableCell className="text-right font-medium">
                                                    {mapping.nhis_tariff
                                                        ?.formatted_price ||
                                                        '-'}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            handleDelete(
                                                                mapping,
                                                            )
                                                        }
                                                        className="text-red-600 hover:text-red-700"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        ) : (
                            <div className="p-12 text-center">
                                <Link2Off className="mx-auto mb-4 h-16 w-16 text-gray-300 dark:text-gray-600" />
                                <h3 className="mb-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    No mappings found
                                </h3>
                                <p className="mb-4 text-gray-600 dark:text-gray-400">
                                    {hasActiveFilters
                                        ? 'Try adjusting your filters'
                                        : 'Get started by adding your first NHIS mapping or importing from a file.'}
                                </p>
                                {!hasActiveFilters && (
                                    <div className="flex justify-center gap-2">
                                        <Button
                                            variant="outline"
                                            onClick={() =>
                                                setImportModalOpen(true)
                                            }
                                        >
                                            <Upload className="mr-2 h-4 w-4" />
                                            Import
                                        </Button>
                                        <Button
                                            onClick={() =>
                                                setCreateModalOpen(true)
                                            }
                                        >
                                            <Plus className="mr-2 h-4 w-4" />
                                            Add Mapping
                                        </Button>
                                    </div>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Pagination */}
                {mappings.data.length > 0 &&
                    mappings.links &&
                    Array.isArray(mappings.links) && (
                        <div className="flex items-center justify-between">
                            <div className="text-sm text-gray-600 dark:text-gray-400">
                                Showing {mappings.meta?.from} to{' '}
                                {mappings.meta?.to} of {mappings.meta?.total}{' '}
                                mappings
                            </div>
                            <div className="flex gap-2">
                                {mappings.links.map(
                                    (link: any, index: number) => {
                                        if (link.url === null) return null;
                                        return (
                                            <Button
                                                key={index}
                                                variant={
                                                    link.active
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                size="sm"
                                                onClick={() =>
                                                    router.get(
                                                        link.url,
                                                        localFilters as any,
                                                        {
                                                            preserveState: true,
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                                dangerouslySetInnerHTML={{
                                                    __html: link.label,
                                                }}
                                            />
                                        );
                                    },
                                )}
                            </div>
                        </div>
                    )}
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
