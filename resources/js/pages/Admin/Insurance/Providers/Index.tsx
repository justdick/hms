import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import {
    Building2,
    Edit,
    Eye,
    Mail,
    Phone,
    Plus,
    Shield,
    ToggleRight,
    Trash2,
} from 'lucide-react';

interface InsuranceProvider {
    id: number;
    name: string;
    code: string;
    contact_person?: string;
    phone?: string;
    email?: string;
    address?: string;
    claim_submission_method?: string;
    payment_terms_days?: number;
    is_active: boolean;
    notes?: string;
    plans_count?: number;
    created_at: string;
}

interface Props {
    providers: {
        data: InsuranceProvider[];
        links: any;
        meta: any;
    };
}

export default function InsuranceProvidersIndex({ providers }: Props) {
    const handleDelete = (provider: InsuranceProvider) => {
        if (
            confirm(
                `Are you sure you want to delete "${provider.name}"? This action cannot be undone.`,
            )
        ) {
            router.delete(`/admin/insurance/providers/${provider.id}`);
        }
    };

    const handleToggleStatus = (provider: InsuranceProvider) => {
        const action = provider.is_active ? 'deactivate' : 'activate';
        if (confirm(`Are you sure you want to ${action} "${provider.name}"?`)) {
            router.post(
                `/admin/insurance/providers/${provider.id}/toggle-status`,
            );
        }
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                { title: 'Insurance Providers', href: '' },
            ]}
        >
            <Head title="Insurance Providers" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="flex items-center gap-2 text-3xl font-bold text-gray-900 dark:text-gray-100">
                            <Shield className="h-8 w-8" />
                            Insurance Providers
                        </h1>
                        <p className="mt-2 text-gray-600 dark:text-gray-400">
                            Manage insurance providers and their details
                        </p>
                    </div>
                    <Link href="/admin/insurance/providers/create">
                        <Button>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Provider
                        </Button>
                    </Link>
                </div>

                {/* Stats Overview */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Total Providers
                                    </p>
                                    <p className="text-3xl font-bold text-gray-900 dark:text-gray-100">
                                        {providers.data.length}
                                    </p>
                                </div>
                                <Building2 className="h-8 w-8 text-blue-600" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Active Providers
                                    </p>
                                    <p className="text-3xl font-bold text-green-600">
                                        {
                                            providers.data.filter(
                                                (p) => p.is_active,
                                            ).length
                                        }
                                    </p>
                                </div>
                                <ToggleRight className="h-8 w-8 text-green-600" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Total Plans
                                    </p>
                                    <p className="text-3xl font-bold text-gray-900 dark:text-gray-100">
                                        {providers.data.reduce(
                                            (sum, p) =>
                                                sum + (p.plans_count || 0),
                                            0,
                                        )}
                                    </p>
                                </div>
                                <Shield className="h-8 w-8 text-purple-600" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Providers List */}
                {providers.data.length > 0 ? (
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                        {providers.data.map((provider) => (
                            <Card
                                key={provider.id}
                                className={`${!provider.is_active ? 'border-gray-300 opacity-75 dark:border-gray-700' : ''}`}
                            >
                                <CardHeader className="pb-3">
                                    <div className="flex items-start justify-between">
                                        <div>
                                            <CardTitle className="text-lg">
                                                {provider.name}
                                            </CardTitle>
                                            <p className="text-sm text-gray-600 dark:text-gray-400">
                                                Code: {provider.code}
                                            </p>
                                        </div>
                                        <div>
                                            <Badge
                                                variant={
                                                    provider.is_active
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {provider.is_active
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </Badge>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {provider.contact_person && (
                                        <div>
                                            <p className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                                Contact Person
                                            </p>
                                            <p className="text-sm text-gray-600 dark:text-gray-400">
                                                {provider.contact_person}
                                            </p>
                                        </div>
                                    )}

                                    {(provider.phone || provider.email) && (
                                        <div className="space-y-2 text-sm">
                                            {provider.phone && (
                                                <div className="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                                                    <Phone className="h-4 w-4" />
                                                    {provider.phone}
                                                </div>
                                            )}
                                            {provider.email && (
                                                <div className="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                                                    <Mail className="h-4 w-4" />
                                                    {provider.email}
                                                </div>
                                            )}
                                        </div>
                                    )}

                                    <div className="grid grid-cols-2 gap-4 border-t pt-4 text-sm dark:border-gray-700">
                                        <div>
                                            <p className="text-gray-600 dark:text-gray-400">
                                                Plans
                                            </p>
                                            <p className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                                {provider.plans_count || 0}
                                            </p>
                                        </div>
                                        {provider.payment_terms_days && (
                                            <div>
                                                <p className="text-gray-600 dark:text-gray-400">
                                                    Payment Terms
                                                </p>
                                                <p className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                                    {
                                                        provider.payment_terms_days
                                                    }{' '}
                                                    days
                                                </p>
                                            </div>
                                        )}
                                    </div>

                                    <div className="flex items-center justify-between border-t pt-4 dark:border-gray-700">
                                        <div className="flex gap-2">
                                            <Link
                                                href={`/admin/insurance/providers/${provider.id}`}
                                            >
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                >
                                                    <Eye className="h-4 w-4" />
                                                </Button>
                                            </Link>
                                            <Link
                                                href={`/admin/insurance/providers/${provider.id}/edit`}
                                            >
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                >
                                                    <Edit className="h-4 w-4" />
                                                </Button>
                                            </Link>
                                        </div>

                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                handleDelete(provider)
                                            }
                                            className="text-red-600 hover:text-red-700"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                ) : (
                    <Card>
                        <CardContent className="p-12 text-center">
                            <Shield className="mx-auto mb-4 h-16 w-16 text-gray-300 dark:text-gray-600" />
                            <h3 className="mb-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                                No insurance providers found
                            </h3>
                            <p className="mb-4 text-gray-600 dark:text-gray-400">
                                Get started by adding your first insurance
                                provider.
                            </p>
                            <Link href="/admin/insurance/providers/create">
                                <Button>
                                    <Plus className="mr-2 h-4 w-4" />
                                    Add Provider
                                </Button>
                            </Link>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
