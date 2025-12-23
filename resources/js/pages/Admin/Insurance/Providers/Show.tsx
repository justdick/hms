import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { StatCard } from '@/components/ui/stat-card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    Building2,
    Calendar,
    Edit,
    FileText,
    Mail,
    Phone,
    Plus,
    Send,
    Shield,
} from 'lucide-react';

interface InsurancePlan {
    id: number;
    plan_name: string;
    plan_code: string;
    plan_type: string;
    coverage_type: string;
    is_active: boolean;
    coverage_rules_count?: number;
    tariffs_count?: number;
}

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
    plans?: InsurancePlan[];
    created_at: string;
    updated_at: string;
}

interface Props {
    provider: InsuranceProvider;
}

export default function InsuranceProviderShow({ provider }: Props) {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                {
                    title: 'Insurance Providers',
                    href: '/admin/insurance/providers',
                },
                { title: provider.name, href: '' },
            ]}
        >
            <Head title={provider.name} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Link href="/admin/insurance/providers">
                            <Button variant="ghost" size="sm">
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Back to Providers
                            </Button>
                        </Link>
                        <div>
                            <div className="flex items-center gap-3">
                                <h1 className="flex items-center gap-2 text-3xl font-bold text-gray-900 dark:text-gray-100">
                                    <Shield className="h-8 w-8" />
                                    {provider.name}
                                </h1>
                                <Badge
                                    variant={
                                        provider.is_active
                                            ? 'default'
                                            : 'secondary'
                                    }
                                >
                                    {provider.is_active ? 'Active' : 'Inactive'}
                                </Badge>
                            </div>
                            <p className="mt-1 text-gray-600 dark:text-gray-400">
                                Code: {provider.code}
                            </p>
                        </div>
                    </div>
                    <Link
                        href={`/admin/insurance/providers/${provider.id}/edit`}
                    >
                        <Button>
                            <Edit className="mr-2 h-4 w-4" />
                            Edit Provider
                        </Button>
                    </Link>
                </div>

                {/* Provider Information - Stat Cards */}
                <div className="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-5">
                    <StatCard
                        icon={<Shield className="h-4 w-4" />}
                        label="Provider Name"
                        value={provider.name}
                        variant="default"
                    />
                    <StatCard
                        icon={<FileText className="h-4 w-4" />}
                        label="Provider Code"
                        value={provider.code}
                        variant="default"
                    />
                    <StatCard
                        icon={<Building2 className="h-4 w-4" />}
                        label="Total Plans"
                        value={provider.plans?.length || 0}
                        variant="info"
                    />
                    <StatCard
                        icon={<Send className="h-4 w-4" />}
                        label="Claim Submission"
                        value={provider.claim_submission_method || 'N/A'}
                        variant="default"
                    />
                    <StatCard
                        icon={<Calendar className="h-4 w-4" />}
                        label="Payment Terms"
                        value={`${provider.payment_terms_days || 0} days`}
                        variant="default"
                    />
                </div>

                {/* Contact & Additional Information */}
                {(provider.contact_person ||
                    provider.phone ||
                    provider.email ||
                    provider.address ||
                    provider.notes) && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Contact Information</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {provider.contact_person && (
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Contact Person
                                    </p>
                                    <p className="mt-1 text-base text-gray-900 dark:text-gray-100">
                                        {provider.contact_person}
                                    </p>
                                </div>
                            )}

                            {(provider.phone || provider.email) && (
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    {provider.phone && (
                                        <div>
                                            <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                                <Phone className="mr-1 inline h-4 w-4" />
                                                Phone
                                            </p>
                                            <p className="mt-1 text-base text-gray-900 dark:text-gray-100">
                                                {provider.phone}
                                            </p>
                                        </div>
                                    )}
                                    {provider.email && (
                                        <div>
                                            <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                                <Mail className="mr-1 inline h-4 w-4" />
                                                Email
                                            </p>
                                            <p className="mt-1 text-base text-gray-900 dark:text-gray-100">
                                                {provider.email}
                                            </p>
                                        </div>
                                    )}
                                </div>
                            )}

                            {provider.address && (
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Address
                                    </p>
                                    <p className="mt-1 text-base text-gray-900 dark:text-gray-100">
                                        {provider.address}
                                    </p>
                                </div>
                            )}

                            {provider.notes && (
                                <div className="border-t pt-4 dark:border-gray-700">
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        <FileText className="mr-1 inline h-4 w-4" />
                                        Notes
                                    </p>
                                    <p className="mt-1 text-base text-gray-900 dark:text-gray-100">
                                        {provider.notes}
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Insurance Plans */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle className="flex items-center gap-2">
                            <Building2 className="h-5 w-5" />
                            Insurance Plans
                        </CardTitle>
                        <Link
                            href={`/admin/insurance/plans/create?provider=${provider.id}`}
                        >
                            <Button size="sm">
                                <Plus className="mr-2 h-4 w-4" />
                                Add Plan
                            </Button>
                        </Link>
                    </CardHeader>
                    <CardContent>
                        {provider.plans && provider.plans.length > 0 ? (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Plan Name</TableHead>
                                        <TableHead>Code</TableHead>
                                        <TableHead>Type</TableHead>
                                        <TableHead>Coverage</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Rules</TableHead>
                                        <TableHead>Tariffs</TableHead>
                                        <TableHead></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {provider.plans.map((plan) => (
                                        <TableRow key={plan.id}>
                                            <TableCell className="font-medium">
                                                {plan.plan_name}
                                            </TableCell>
                                            <TableCell>
                                                {plan.plan_code}
                                            </TableCell>
                                            <TableCell className="capitalize">
                                                {plan.plan_type}
                                            </TableCell>
                                            <TableCell className="capitalize">
                                                {plan.coverage_type}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={
                                                        plan.is_active
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {plan.is_active
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                {plan.coverage_rules_count || 0}
                                            </TableCell>
                                            <TableCell>
                                                {plan.tariffs_count || 0}
                                            </TableCell>
                                            <TableCell>
                                                <Link
                                                    href={`/admin/insurance/plans/${plan.id}`}
                                                >
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                    >
                                                        View
                                                    </Button>
                                                </Link>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        ) : (
                            <div className="py-12 text-center">
                                <Building2 className="mx-auto mb-4 h-12 w-12 text-gray-300 dark:text-gray-600" />
                                <h3 className="mb-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    No insurance plans
                                </h3>
                                <p className="mb-4 text-gray-600 dark:text-gray-400">
                                    This provider doesn't have any plans yet.
                                </p>
                                <Link
                                    href={`/admin/insurance/plans/create?provider=${provider.id}`}
                                >
                                    <Button size="sm">
                                        <Plus className="mr-2 h-4 w-4" />
                                        Add First Plan
                                    </Button>
                                </Link>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
