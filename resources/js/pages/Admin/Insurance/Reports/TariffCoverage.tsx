import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { StatCard } from '@/components/ui/stat-card';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle,
    Download,
    Filter,
    FlaskConical,
    Hash,
    Package,
    Pill,
    Stethoscope,
    X,
} from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';

interface CoverageCategory {
    total: number;
    mapped: number;
    unmapped: number;
    percentage: number;
}

interface TariffCoverageData {
    coverage_by_type: {
        drugs?: CoverageCategory;
        lab_services?: CoverageCategory;
        procedures?: CoverageCategory;
    };
    overall: CoverageCategory;
}

interface Filters {
    item_type: string | null;
}

interface Props {
    data: TariffCoverageData;
    filters: Filters;
}

const categoryConfig: Record<
    string,
    { label: string; icon: React.ReactNode; color: string }
> = {
    drugs: {
        label: 'Drugs',
        icon: <Pill className="h-6 w-6" />,
        color: 'text-blue-600',
    },
    lab_services: {
        label: 'Lab Services',
        icon: <FlaskConical className="h-6 w-6" />,
        color: 'text-purple-600',
    },
    procedures: {
        label: 'Procedures',
        icon: <Stethoscope className="h-6 w-6" />,
        color: 'text-green-600',
    },
};

export default function TariffCoverage({ data, filters }: Props) {
    const [showFilters, setShowFilters] = useState(false);
    const [localFilters, setLocalFilters] = useState<Filters>(filters);

    useEffect(() => {
        setLocalFilters(filters);
    }, [filters]);

    const handleFilterChange = (key: keyof Filters, value: string) => {
        setLocalFilters((prev) => ({
            ...prev,
            [key]: value === 'all' || !value ? null : value,
        }));
    };

    const handleApplyFilters = (e: FormEvent) => {
        e.preventDefault();
        router.get(
            '/admin/insurance/reports/tariff-coverage',
            localFilters as any,
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const handleClearFilters = () => {
        const defaultFilters = { item_type: null };
        setLocalFilters(defaultFilters);
        router.get(
            '/admin/insurance/reports/tariff-coverage',
            defaultFilters as any,
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const handleExport = () => {
        const params = new URLSearchParams();
        if (localFilters.item_type)
            params.append('item_type', localFilters.item_type);
        window.location.href = `/admin/insurance/reports/tariff-coverage/export?${params.toString()}`;
    };

    const hasActiveFilters = filters.item_type !== null;

    const getCoverageColor = (percentage: number) => {
        if (percentage >= 80) return 'text-green-600';
        if (percentage >= 50) return 'text-yellow-600';
        return 'text-red-600';
    };

    const getCoverageStatus = (percentage: number) => {
        if (percentage >= 80) return { label: 'Good', color: 'bg-green-500' };
        if (percentage >= 50)
            return { label: 'Moderate', color: 'bg-yellow-500' };
        return { label: 'Low', color: 'bg-red-500' };
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                { title: 'Insurance', href: '/admin/insurance' },
                { title: 'Reports', href: '/admin/insurance/reports' },
                { title: 'Tariff Coverage', href: '' },
            ]}
        >
            <Head title="Tariff Coverage Report" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() =>
                                router.visit('/admin/insurance/reports')
                            }
                        >
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back
                        </Button>
                        <div>
                            <h1 className="flex items-center gap-2 text-3xl font-bold text-gray-900 dark:text-gray-100">
                                <Package className="h-8 w-8" />
                                Tariff Coverage Report
                            </h1>
                            <p className="mt-1 text-gray-600 dark:text-gray-400">
                                Percentage of hospital items mapped to NHIS
                                tariffs
                            </p>
                        </div>
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
                        <Button onClick={handleExport}>
                            <Download className="mr-2 h-4 w-4" />
                            Export Excel
                        </Button>
                    </div>
                </div>

                {/* Filters Panel */}
                {showFilters && (
                    <Card>
                        <CardContent className="p-6">
                            <form
                                onSubmit={handleApplyFilters}
                                className="space-y-4"
                            >
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
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
                                                <SelectItem value="drug">
                                                    Drugs
                                                </SelectItem>
                                                <SelectItem value="lab_service">
                                                    Lab Services
                                                </SelectItem>
                                                <SelectItem value="procedure">
                                                    Procedures
                                                </SelectItem>
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
                                            Clear Filters
                                        </Button>
                                    )}
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {/* Overall Coverage */}
                <Card>
                    <CardHeader>
                        <CardTitle>Overall NHIS Coverage</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-col items-center justify-center space-y-4 py-6">
                            <div className="relative h-40 w-40">
                                <svg className="h-40 w-40 -rotate-90 transform">
                                    <circle
                                        cx="80"
                                        cy="80"
                                        r="70"
                                        stroke="currentColor"
                                        strokeWidth="12"
                                        fill="none"
                                        className="text-gray-200 dark:text-gray-700"
                                    />
                                    <circle
                                        cx="80"
                                        cy="80"
                                        r="70"
                                        stroke="currentColor"
                                        strokeWidth="12"
                                        fill="none"
                                        strokeDasharray={`${(data.overall.percentage / 100) * 440} 440`}
                                        className={getCoverageColor(
                                            data.overall.percentage,
                                        )}
                                    />
                                </svg>
                                <div className="absolute inset-0 flex flex-col items-center justify-center">
                                    <span
                                        className={`text-4xl font-bold ${getCoverageColor(data.overall.percentage)}`}
                                    >
                                        {data.overall.percentage.toFixed(1)}%
                                    </span>
                                    <span className="text-sm text-gray-500">
                                        Coverage
                                    </span>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                <Badge
                                    className={
                                        getCoverageStatus(
                                            data.overall.percentage,
                                        ).color
                                    }
                                >
                                    {
                                        getCoverageStatus(
                                            data.overall.percentage,
                                        ).label
                                    }
                                </Badge>
                            </div>
                            <div className="grid grid-cols-3 gap-4">
                                <StatCard
                                    label="Total Items"
                                    value={data.overall.total}
                                    icon={<Hash className="h-5 w-5" />}
                                />
                                <StatCard
                                    label="Mapped"
                                    value={data.overall.mapped}
                                    icon={<CheckCircle className="h-5 w-5" />}
                                    variant="success"
                                />
                                <StatCard
                                    label="Unmapped"
                                    value={data.overall.unmapped}
                                    icon={<AlertTriangle className="h-5 w-5" />}
                                    variant="error"
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Coverage by Category */}
                <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                    {Object.entries(data.coverage_by_type).map(
                        ([category, categoryData]) => {
                            const config = categoryConfig[category];
                            if (!config || !categoryData) return null;

                            return (
                                <Card key={category}>
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2">
                                            <span className={config.color}>
                                                {config.icon}
                                            </span>
                                            {config.label}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="space-y-4">
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm text-gray-600 dark:text-gray-400">
                                                    Coverage
                                                </span>
                                                <span
                                                    className={`text-2xl font-bold ${getCoverageColor(categoryData.percentage)}`}
                                                >
                                                    {categoryData.percentage.toFixed(
                                                        1,
                                                    )}
                                                    %
                                                </span>
                                            </div>
                                            <Progress
                                                value={categoryData.percentage}
                                                className="h-3"
                                            />
                                            <div className="grid grid-cols-3 gap-4 text-center">
                                                <div className="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                                                    <p className="text-lg font-bold">
                                                        {categoryData.total}
                                                    </p>
                                                    <p className="text-xs text-gray-500">
                                                        Total
                                                    </p>
                                                </div>
                                                <div className="rounded-lg bg-green-50 p-3 dark:bg-green-900/20">
                                                    <p className="text-lg font-bold text-green-600">
                                                        {categoryData.mapped}
                                                    </p>
                                                    <p className="text-xs text-gray-500">
                                                        Mapped
                                                    </p>
                                                </div>
                                                <div className="rounded-lg bg-red-50 p-3 dark:bg-red-900/20">
                                                    <p className="text-lg font-bold text-red-600">
                                                        {categoryData.unmapped}
                                                    </p>
                                                    <p className="text-xs text-gray-500">
                                                        Unmapped
                                                    </p>
                                                </div>
                                            </div>
                                            {categoryData.unmapped > 0 && (
                                                <div className="flex items-center gap-2 rounded-lg border border-yellow-200 bg-yellow-50 p-3 dark:border-yellow-800 dark:bg-yellow-900/20">
                                                    <AlertTriangle className="h-4 w-4 text-yellow-600" />
                                                    <span className="text-sm text-yellow-700 dark:text-yellow-400">
                                                        {categoryData.unmapped}{' '}
                                                        items need NHIS mapping
                                                    </span>
                                                </div>
                                            )}
                                            {categoryData.percentage ===
                                                100 && (
                                                <div className="flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 p-3 dark:border-green-800 dark:bg-green-900/20">
                                                    <CheckCircle className="h-4 w-4 text-green-600" />
                                                    <span className="text-sm text-green-700 dark:text-green-400">
                                                        All items are mapped
                                                    </span>
                                                </div>
                                            )}
                                        </div>
                                    </CardContent>
                                </Card>
                            );
                        },
                    )}
                </div>

                {/* Action Items */}
                {data.overall.unmapped > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <AlertTriangle className="h-5 w-5 text-yellow-600" />
                                Action Required
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                <p className="text-gray-600 dark:text-gray-400">
                                    There are {data.overall.unmapped} items that
                                    are not mapped to NHIS tariffs. These items
                                    will show as "Not Covered" during claim
                                    vetting.
                                </p>
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        router.visit(
                                            '/admin/nhis-mappings/unmapped',
                                        )
                                    }
                                >
                                    View Unmapped Items
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
