import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { StatCard } from '@/components/ui/stat-card';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowRight,
    Bed,
    Building2,
    Edit,
    Eye,
    Hospital,
    Plus,
    Search,
    Sparkles,
    ToggleLeft,
    ToggleRight,
    Trash2,
    Users,
    Wrench,
} from 'lucide-react';
import { useState } from 'react';

interface Bed {
    id: number;
    ward_id: number;
    status: 'available' | 'occupied' | 'maintenance' | 'cleaning';
}

interface Ward {
    id: number;
    name: string;
    code: string;
    description?: string;
    total_beds: number;
    available_beds: number;
    is_active: boolean;
    beds: Bed[];
    created_at: string;
}

interface Props {
    wards: Ward[];
}

export default function WardIndex({ wards }: Props) {
    const [searchQuery, setSearchQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState<
        'all' | 'active' | 'inactive'
    >('all');

    const handleDelete = (ward: Ward) => {
        if (
            confirm(
                `Are you sure you want to delete "${ward.name}"? This action cannot be undone.`,
            )
        ) {
            router.delete(`/wards/${ward.id}`);
        }
    };

    const handleToggleStatus = (ward: Ward) => {
        const action = ward.is_active ? 'deactivate' : 'activate';
        if (confirm(`Are you sure you want to ${action} "${ward.name}"?`)) {
            router.post(`/wards/${ward.id}/toggle-status`);
        }
    };

    const getOccupancyRate = (ward: Ward) => {
        if (ward.total_beds === 0) return 0;
        const occupied = ward.total_beds - ward.available_beds;
        return Math.round((occupied / ward.total_beds) * 100);
    };

    const getOccupancyColor = (rate: number) => {
        if (rate >= 90) return 'text-red-600';
        if (rate >= 70) return 'text-orange-600';
        if (rate >= 50) return 'text-yellow-600';
        return 'text-green-600';
    };

    const getOccupancyBgColor = (rate: number) => {
        if (rate >= 90) return 'bg-red-500 dark:bg-red-600';
        if (rate >= 70) return 'bg-orange-500 dark:bg-orange-600';
        if (rate >= 50) return 'bg-yellow-500 dark:bg-yellow-600';
        return 'bg-green-500 dark:bg-green-600';
    };

    const getBedStatusCounts = (ward: Ward) => {
        const occupied = ward.total_beds - ward.available_beds;
        const available = ward.available_beds;
        const maintenance = ward.beds.filter(
            (b) => b.status === 'maintenance',
        ).length;
        const cleaning = ward.beds.filter(
            (b) => b.status === 'cleaning',
        ).length;

        return { occupied, available, maintenance, cleaning };
    };

    const filteredWards = wards.filter((ward) => {
        const matchesSearch =
            ward.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            ward.code.toLowerCase().includes(searchQuery.toLowerCase());

        const matchesStatus =
            statusFilter === 'all' ||
            (statusFilter === 'active' && ward.is_active) ||
            (statusFilter === 'inactive' && !ward.is_active);

        return matchesSearch && matchesStatus;
    });

    return (
        <AppLayout breadcrumbs={[{ title: 'Wards', href: '/wards' }]}>
            <Head title="Ward Management" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="flex items-center gap-2 text-3xl font-bold text-gray-900 dark:text-gray-100">
                            <Hospital className="h-8 w-8 text-blue-600" />
                            Ward Management
                        </h1>
                        <p className="mt-2 text-gray-600 dark:text-gray-400">
                            Manage hospital wards and bed allocation
                        </p>
                    </div>
                    <Link href="/wards/create">
                        <Button size="lg" className="gap-2">
                            <Plus className="h-4 w-4" />
                            Create Ward
                        </Button>
                    </Link>
                </div>

                {/* Stats Overview */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        label="Total Wards"
                        value={wards.length}
                        icon={<Building2 className="h-4 w-4" />}
                        variant="info"
                    />
                    <StatCard
                        label="Active Wards"
                        value={wards.filter((w) => w.is_active).length}
                        icon={<Sparkles className="h-4 w-4" />}
                        variant="success"
                    />
                    <StatCard
                        label="Total Beds"
                        value={wards.reduce((sum, w) => sum + w.total_beds, 0)}
                        icon={<Bed className="h-4 w-4" />}
                    />
                    <StatCard
                        label="Available Beds"
                        value={wards.reduce((sum, w) => sum + w.available_beds, 0)}
                        icon={<Users className="h-4 w-4" />}
                        variant="success"
                    />
                </div>

                {/* Search and Filter */}
                {wards.length > 0 && (
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div className="relative flex-1 sm:max-w-sm">
                                    <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
                                    <Input
                                        type="text"
                                        placeholder="Search wards by name or code..."
                                        value={searchQuery}
                                        onChange={(e) =>
                                            setSearchQuery(e.target.value)
                                        }
                                        className="pl-10"
                                    />
                                </div>
                                <div className="flex gap-2">
                                    <Button
                                        variant={
                                            statusFilter === 'all'
                                                ? 'default'
                                                : 'outline'
                                        }
                                        size="sm"
                                        onClick={() => setStatusFilter('all')}
                                    >
                                        All
                                    </Button>
                                    <Button
                                        variant={
                                            statusFilter === 'active'
                                                ? 'default'
                                                : 'outline'
                                        }
                                        size="sm"
                                        onClick={() =>
                                            setStatusFilter('active')
                                        }
                                    >
                                        Active
                                    </Button>
                                    <Button
                                        variant={
                                            statusFilter === 'inactive'
                                                ? 'default'
                                                : 'outline'
                                        }
                                        size="sm"
                                        onClick={() =>
                                            setStatusFilter('inactive')
                                        }
                                    >
                                        Inactive
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Wards List */}
                {wards.length > 0 ? (
                    filteredWards.length > 0 ? (
                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
                            {filteredWards.map((ward) => {
                                const bedStatus = getBedStatusCounts(ward);
                                const occupancyRate = getOccupancyRate(ward);

                                return (
                                    <Card
                                        key={ward.id}
                                        className={`group transition-all hover:shadow-lg ${!ward.is_active ? 'border-gray-300 opacity-75' : 'hover:border-blue-300'}`}
                                    >
                                        <CardHeader className="pb-3">
                                            <div className="flex items-start justify-between">
                                                <div className="flex-1">
                                                    <CardTitle className="text-xl font-bold text-gray-900 dark:text-gray-100">
                                                        {ward.name}
                                                    </CardTitle>
                                                    <p className="mt-1 text-sm font-medium text-gray-500 dark:text-gray-400">
                                                        Code: {ward.code}
                                                    </p>
                                                </div>
                                                <Badge
                                                    variant={
                                                        ward.is_active
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                    className="shrink-0"
                                                >
                                                    {ward.is_active
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </Badge>
                                            </div>
                                        </CardHeader>
                                        <CardContent className="space-y-4">
                                            {ward.description && (
                                                <p className="text-sm text-gray-600 dark:text-gray-400">
                                                    {ward.description}
                                                </p>
                                            )}

                                            {/* Bed Status Grid */}
                                            <div className="grid grid-cols-2 gap-3">
                                                <div className="rounded-lg bg-blue-50 p-3 dark:bg-blue-900/10">
                                                    <div className="flex items-center gap-2">
                                                        <Bed className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                                        <span className="text-xs font-medium text-gray-600 dark:text-gray-400">
                                                            Total
                                                        </span>
                                                    </div>
                                                    <p className="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">
                                                        {ward.total_beds}
                                                    </p>
                                                </div>
                                                <div className="rounded-lg bg-green-50 p-3 dark:bg-green-900/10">
                                                    <div className="flex items-center gap-2">
                                                        <Bed className="h-4 w-4 text-green-600 dark:text-green-400" />
                                                        <span className="text-xs font-medium text-gray-600 dark:text-gray-400">
                                                            Available
                                                        </span>
                                                    </div>
                                                    <p className="mt-1 text-2xl font-bold text-green-600 dark:text-green-400">
                                                        {bedStatus.available}
                                                    </p>
                                                </div>
                                                <div className="rounded-lg bg-orange-50 p-3 dark:bg-orange-900/10">
                                                    <div className="flex items-center gap-2">
                                                        <Users className="h-4 w-4 text-orange-600 dark:text-orange-400" />
                                                        <span className="text-xs font-medium text-gray-600 dark:text-gray-400">
                                                            Occupied
                                                        </span>
                                                    </div>
                                                    <p className="mt-1 text-2xl font-bold text-orange-600 dark:text-orange-400">
                                                        {bedStatus.occupied}
                                                    </p>
                                                </div>
                                                <div className="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                                                    <div className="flex items-center gap-2">
                                                        <Wrench className="h-4 w-4 text-gray-600 dark:text-gray-400" />
                                                        <span className="text-xs font-medium text-gray-600 dark:text-gray-400">
                                                            Maintenance
                                                        </span>
                                                    </div>
                                                    <p className="mt-1 text-2xl font-bold text-gray-600 dark:text-gray-400">
                                                        {bedStatus.maintenance}
                                                    </p>
                                                </div>
                                            </div>

                                            {/* Occupancy Bar */}
                                            <div className="space-y-2">
                                                <div className="flex items-center justify-between">
                                                    <span className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        Occupancy Rate
                                                    </span>
                                                    <span
                                                        className={`text-sm font-bold ${getOccupancyColor(occupancyRate)}`}
                                                    >
                                                        {occupancyRate}%
                                                    </span>
                                                </div>
                                                <div className="h-3 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                                    <div
                                                        className={`h-full transition-all ${getOccupancyBgColor(occupancyRate)}`}
                                                        style={{
                                                            width: `${occupancyRate}%`,
                                                        }}
                                                    />
                                                </div>
                                            </div>

                                            {/* Actions */}
                                            <div className="flex items-center justify-between gap-2 border-t pt-4 dark:border-gray-700">
                                                <Link
                                                    href={`/wards/${ward.id}`}
                                                    className="flex-1"
                                                >
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        className="w-full gap-2"
                                                    >
                                                        <Eye className="h-4 w-4" />
                                                        View Details
                                                        <ArrowRight className="ml-auto h-4 w-4" />
                                                    </Button>
                                                </Link>

                                                <div className="flex gap-1">
                                                    <Link
                                                        href={`/wards/${ward.id}/edit`}
                                                    >
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            className="h-9 w-9 p-0"
                                                        >
                                                            <Edit className="h-4 w-4" />
                                                        </Button>
                                                    </Link>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="h-9 w-9 p-0"
                                                        onClick={() =>
                                                            handleToggleStatus(
                                                                ward,
                                                            )
                                                        }
                                                    >
                                                        {ward.is_active ? (
                                                            <ToggleLeft className="h-4 w-4 text-orange-600" />
                                                        ) : (
                                                            <ToggleRight className="h-4 w-4 text-green-600" />
                                                        )}
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="h-9 w-9 p-0 text-red-600 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-900/20"
                                                        onClick={() =>
                                                            handleDelete(ward)
                                                        }
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                );
                            })}
                        </div>
                    ) : (
                        <Card>
                            <CardContent className="p-12 text-center">
                                <Search className="mx-auto mb-4 h-16 w-16 text-gray-300" />
                                <h3 className="mb-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    No matching wards found
                                </h3>
                                <p className="text-gray-600 dark:text-gray-400">
                                    Try adjusting your search or filter criteria
                                </p>
                            </CardContent>
                        </Card>
                    )
                ) : (
                    <Card>
                        <CardContent className="p-12 text-center">
                            <Hospital className="mx-auto mb-4 h-16 w-16 text-gray-300" />
                            <h3 className="mb-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                                No wards found
                            </h3>
                            <p className="mb-4 text-gray-600 dark:text-gray-400">
                                Get started by creating your first ward.
                            </p>
                            <Link href="/wards/create">
                                <Button>
                                    <Plus className="mr-2 h-4 w-4" />
                                    Create Ward
                                </Button>
                            </Link>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
