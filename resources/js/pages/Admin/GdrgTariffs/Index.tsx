import { GdrgTariffForm } from '@/components/GdrgTariff/GdrgTariffForm';
import { ImportGdrgModal } from '@/components/GdrgTariff/ImportGdrgModal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
    Activity,
    Edit,
    FileSpreadsheet,
    Filter,
    Plus,
    Search,
    Trash2,
    Upload,
    X,
} from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';

interface GdrgTariff {
    id: number;
    code: string;
    name: string;
    mdc_category: string;
    tariff_price: number;
    age_category: string;
    is_active: boolean;
    formatted_price: string;
    display_name: string;
    created_at: string;
    updated_at: string;
}

interface Filters {
    search?: string;
    mdc_category?: string;
    age_category?: string;
    active_only?: boolean;
}

interface Props {
    tariffs: {
        data: GdrgTariff[];
        links: any;
        meta: any;
    };
    filters: Filters;
    mdcCategories: string[];
    ageCategories: string[];
}

const ageCategoryLabels: Record<string, string> = {
    adult: 'Adult',
    child: 'Child',
    all: 'All Ages',
};

export default function GdrgTariffsIndex({
    tariffs,
    filters,
    mdcCategories,
    ageCategories,
}: Props) {
    const [showFilters, setShowFilters] = useState(false);
    const [localFilters, setLocalFilters] = useState<Filters>(filters);
    const [createModalOpen, setCreateModalOpen] = useState(false);
    const [editModalOpen, setEditModalOpen] = useState(false);
    const [importModalOpen, setImportModalOpen] = useState(false);
    const [selectedTariff, setSelectedTariff] = useState<GdrgTariff | null>(
        null,
    );

    useEffect(() => {
        setLocalFilters(filters);
    }, [filters]);

    const handleFilterChange = (
        key: keyof Filters,
        value: string | boolean,
    ) => {
        setLocalFilters((prev) => ({
            ...prev,
            [key]: value === 'all' || value === '' ? undefined : value,
        }));
    };

    const handleApplyFilters = (e: FormEvent) => {
        e.preventDefault();
        router.get('/admin/gdrg-tariffs', localFilters as any, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleClearFilters = () => {
        setLocalFilters({});
        router.get(
            '/admin/gdrg-tariffs',
            {},
            { preserveState: true, preserveScroll: true },
        );
    };

    const hasActiveFilters = Object.keys(filters).some(
        (key) => filters[key as keyof Filters] !== undefined,
    );

    const handleDelete = (tariff: GdrgTariff) => {
        if (
            confirm(
                `Are you sure you want to delete "${tariff.name}"? This action cannot be undone.`,
            )
        ) {
            router.delete(`/admin/gdrg-tariffs/${tariff.id}`);
        }
    };

    const handleEdit = (tariff: GdrgTariff) => {
        setSelectedTariff(tariff);
        setEditModalOpen(true);
    };

    const stats = {
        total: tariffs.meta?.total || tariffs.data.length,
        active: tariffs.data.filter((t) => t.is_active).length,
        mdcCategories: [...new Set(tariffs.data.map((t) => t.mdc_category))]
            .length,
    };

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
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="flex items-center gap-2 text-3xl font-bold text-gray-900 dark:text-gray-100">
                            <Activity className="h-8 w-8" />
                            G-DRG Tariff Management
                        </h1>
                        <p className="mt-2 text-gray-600 dark:text-gray-400">
                            Manage Ghana Diagnosis Related Groups tariff codes
                            and prices
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
                        <Button
                            variant="outline"
                            onClick={() => setImportModalOpen(true)}
                        >
                            <Upload className="mr-2 h-4 w-4" />
                            Import
                        </Button>
                        <Button onClick={() => setCreateModalOpen(true)}>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Tariff
                        </Button>
                    </div>
                </div>

                {/* Stats Overview */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Total Tariffs
                                    </p>
                                    <p className="text-3xl font-bold text-gray-900 dark:text-gray-100">
                                        {stats.total}
                                    </p>
                                </div>
                                <FileSpreadsheet className="h-8 w-8 text-blue-600" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Active Tariffs
                                    </p>
                                    <p className="text-3xl font-bold text-green-600">
                                        {stats.active}
                                    </p>
                                </div>
                                <Activity className="h-8 w-8 text-green-600" />
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        MDC Categories
                                    </p>
                                    <p className="text-3xl font-bold text-purple-600">
                                        {mdcCategories.length}
                                    </p>
                                </div>
                                <Filter className="h-8 w-8 text-purple-600" />
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
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="search">Search</Label>
                                        <div className="relative">
                                            <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-500" />
                                            <Input
                                                id="search"
                                                placeholder="Code, name, MDC..."
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
                                        <Label htmlFor="mdc_category">
                                            MDC Category
                                        </Label>
                                        <Select
                                            value={
                                                localFilters.mdc_category ||
                                                'all'
                                            }
                                            onValueChange={(value) =>
                                                handleFilterChange(
                                                    'mdc_category',
                                                    value,
                                                )
                                            }
                                        >
                                            <SelectTrigger id="mdc_category">
                                                <SelectValue placeholder="All MDC categories" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">
                                                    All MDC categories
                                                </SelectItem>
                                                {mdcCategories.map((cat) => (
                                                    <SelectItem
                                                        key={cat}
                                                        value={cat}
                                                    >
                                                        {cat}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="age_category">
                                            Age Category
                                        </Label>
                                        <Select
                                            value={
                                                localFilters.age_category ||
                                                'all'
                                            }
                                            onValueChange={(value) =>
                                                handleFilterChange(
                                                    'age_category',
                                                    value,
                                                )
                                            }
                                        >
                                            <SelectTrigger id="age_category">
                                                <SelectValue placeholder="All age categories" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">
                                                    All age categories
                                                </SelectItem>
                                                {ageCategories.map((cat) => (
                                                    <SelectItem
                                                        key={cat}
                                                        value={cat}
                                                    >
                                                        {ageCategoryLabels[
                                                            cat
                                                        ] || cat}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="flex items-end space-x-2 pb-2">
                                        <Checkbox
                                            id="active_only"
                                            checked={
                                                localFilters.active_only ||
                                                false
                                            }
                                            onCheckedChange={(checked) =>
                                                handleFilterChange(
                                                    'active_only',
                                                    checked === true,
                                                )
                                            }
                                        />
                                        <Label
                                            htmlFor="active_only"
                                            className="cursor-pointer"
                                        >
                                            Show active only
                                        </Label>
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

                {/* Tariffs Table */}
                <Card>
                    <CardContent className="p-0">
                        {tariffs.data.length > 0 ? (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Code</TableHead>
                                            <TableHead>Name</TableHead>
                                            <TableHead>MDC Category</TableHead>
                                            <TableHead className="text-right">
                                                Tariff Price
                                            </TableHead>
                                            <TableHead>Age Category</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead className="text-right">
                                                Actions
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {tariffs.data.map((tariff) => (
                                            <TableRow key={tariff.id}>
                                                <TableCell className="font-mono font-medium">
                                                    {tariff.code}
                                                </TableCell>
                                                <TableCell>
                                                    {tariff.name}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant="outline">
                                                        {tariff.mdc_category}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-right font-medium">
                                                    {tariff.formatted_price}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant="secondary">
                                                        {ageCategoryLabels[
                                                            tariff.age_category
                                                        ] ||
                                                            tariff.age_category}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge
                                                        variant={
                                                            tariff.is_active
                                                                ? 'default'
                                                                : 'secondary'
                                                        }
                                                    >
                                                        {tariff.is_active
                                                            ? 'Active'
                                                            : 'Inactive'}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <div className="flex items-center justify-end gap-2">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                handleEdit(
                                                                    tariff,
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
                                                                    tariff,
                                                                )
                                                            }
                                                            className="text-red-600 hover:text-red-700"
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        ) : (
                            <div className="p-12 text-center">
                                <Activity className="mx-auto mb-4 h-16 w-16 text-gray-300 dark:text-gray-600" />
                                <h3 className="mb-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    No G-DRG tariffs found
                                </h3>
                                <p className="mb-4 text-gray-600 dark:text-gray-400">
                                    {hasActiveFilters
                                        ? 'Try adjusting your filters'
                                        : 'Get started by adding your first G-DRG tariff or importing from a file.'}
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
                                            Add Tariff
                                        </Button>
                                    </div>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Pagination */}
                {tariffs.data.length > 0 &&
                    tariffs.links &&
                    Array.isArray(tariffs.links) && (
                        <div className="flex items-center justify-between">
                            <div className="text-sm text-gray-600 dark:text-gray-400">
                                Showing {tariffs.meta?.from} to{' '}
                                {tariffs.meta?.to} of {tariffs.meta?.total}{' '}
                                tariffs
                            </div>
                            <div className="flex gap-2">
                                {tariffs.links.map(
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
