import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    Edit,
    ExternalLink,
    FileText,
    XCircle,
} from 'lucide-react';

interface InsuranceProvider {
    id: number;
    name: string;
    code: string;
}

interface InsurancePlan {
    id: number;
    plan_name: string;
    plan_code: string;
    plan_type: string;
    coverage_type: string;
    annual_limit?: string;
    visit_limit?: number;
    default_copay_percentage?: string;
    consultation_default?: string;
    drugs_default?: string;
    labs_default?: string;
    procedures_default?: string;
    requires_referral: boolean;
    is_active: boolean;
    effective_from?: string;
    effective_to?: string;
    description?: string;
    provider?: InsuranceProvider;
}

interface Props {
    plan: {
        data: InsurancePlan;
    };
}

export default function InsurancePlanShow({ plan: planWrapper }: Props) {
    const plan = planWrapper.data;
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                { title: 'Insurance Plans', href: '/admin/insurance/plans' },
                { title: plan.plan_name, href: '' },
            ]}
        >
            <Head title={plan.plan_name} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Link href="/admin/insurance/plans">
                            <Button variant="ghost" size="sm">
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Back to Plans
                            </Button>
                        </Link>
                        <div>
                            <div className="flex items-center gap-3">
                                <h1 className="flex items-center gap-2 text-3xl font-bold text-gray-900 dark:text-gray-100">
                                    <FileText className="h-8 w-8" />
                                    {plan.plan_name}
                                </h1>
                                <Badge
                                    variant={
                                        plan.is_active ? 'default' : 'secondary'
                                    }
                                >
                                    {plan.is_active ? 'Active' : 'Inactive'}
                                </Badge>
                            </div>
                            <p className="mt-1 text-gray-600 dark:text-gray-400">
                                {plan.provider?.name} - {plan.plan_code}
                            </p>
                        </div>
                    </div>
                    <Link href={`/admin/insurance/plans/${plan.id}/edit`}>
                        <Button>
                            <Edit className="mr-2 h-4 w-4" />
                            Edit Plan
                        </Button>
                    </Link>
                </div>

                {/* Plan Details */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Plan Details</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Plan Type
                                    </p>
                                    <p className="mt-1 text-base font-semibold text-gray-900 capitalize dark:text-gray-100">
                                        {plan.plan_type}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Coverage Type
                                    </p>
                                    <p className="mt-1 text-base font-semibold text-gray-900 capitalize dark:text-gray-100">
                                        {plan.coverage_type}
                                    </p>
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Annual Limit
                                    </p>
                                    <p className="mt-1 text-base font-semibold text-gray-900 dark:text-gray-100">
                                        {plan.annual_limit
                                            ? `$${parseFloat(plan.annual_limit).toLocaleString()}`
                                            : 'Unlimited'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Visit Limit
                                    </p>
                                    <p className="mt-1 text-base font-semibold text-gray-900 dark:text-gray-100">
                                        {plan.visit_limit || 'Unlimited'}
                                    </p>
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Default Co-pay
                                    </p>
                                    <p className="mt-1 text-base font-semibold text-gray-900 dark:text-gray-100">
                                        {plan.default_copay_percentage
                                            ? `${plan.default_copay_percentage}%`
                                            : 'N/A'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Requires Referral
                                    </p>
                                    <p className="mt-1 text-base font-semibold text-gray-900 dark:text-gray-100">
                                        {plan.requires_referral ? (
                                            <CheckCircle2 className="inline h-5 w-5 text-green-600" />
                                        ) : (
                                            <XCircle className="inline h-5 w-5 text-gray-400" />
                                        )}
                                    </p>
                                </div>
                            </div>

                            {(plan.effective_from || plan.effective_to) && (
                                <div className="grid grid-cols-2 gap-4 border-t pt-4 dark:border-gray-700">
                                    {plan.effective_from && (
                                        <div>
                                            <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                                Effective From
                                            </p>
                                            <p className="mt-1 text-base text-gray-900 dark:text-gray-100">
                                                {plan.effective_from}
                                            </p>
                                        </div>
                                    )}
                                    {plan.effective_to && (
                                        <div>
                                            <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                                Effective To
                                            </p>
                                            <p className="mt-1 text-base text-gray-900 dark:text-gray-100">
                                                {plan.effective_to}
                                            </p>
                                        </div>
                                    )}
                                </div>
                            )}

                            {plan.description && (
                                <div className="border-t pt-4 dark:border-gray-700">
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Description
                                    </p>
                                    <p className="mt-1 text-base text-gray-900 dark:text-gray-100">
                                        {plan.description}
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Provider Info</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                    Provider Name
                                </p>
                                <p className="mt-1 text-base font-semibold text-gray-900 dark:text-gray-100">
                                    {plan.provider?.name}
                                </p>
                            </div>
                            <div>
                                <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                    Provider Code
                                </p>
                                <p className="mt-1 text-base font-semibold text-gray-900 dark:text-gray-100">
                                    {plan.provider?.code}
                                </p>
                            </div>
                            <div className="border-t pt-4 dark:border-gray-700">
                                <Link
                                    href={`/admin/insurance/providers/${plan.provider?.id}`}
                                >
                                    <Button
                                        variant="outline"
                                        className="w-full"
                                    >
                                        View Provider
                                    </Button>
                                </Link>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Category Defaults */}
                {(plan.consultation_default ||
                    plan.drugs_default ||
                    plan.labs_default ||
                    plan.procedures_default) && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Category Default Coverage</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                <div className="rounded-lg border p-4 text-center dark:border-gray-700">
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Consultations
                                    </p>
                                    <p className="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">
                                        {plan.consultation_default
                                            ? `${plan.consultation_default}%`
                                            : 'N/A'}
                                    </p>
                                </div>
                                <div className="rounded-lg border p-4 text-center dark:border-gray-700">
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Drugs
                                    </p>
                                    <p className="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">
                                        {plan.drugs_default
                                            ? `${plan.drugs_default}%`
                                            : 'N/A'}
                                    </p>
                                </div>
                                <div className="rounded-lg border p-4 text-center dark:border-gray-700">
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Labs
                                    </p>
                                    <p className="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">
                                        {plan.labs_default
                                            ? `${plan.labs_default}%`
                                            : 'N/A'}
                                    </p>
                                </div>
                                <div className="rounded-lg border p-4 text-center dark:border-gray-700">
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Procedures
                                    </p>
                                    <p className="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">
                                        {plan.procedures_default
                                            ? `${plan.procedures_default}%`
                                            : 'N/A'}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Manage Pricing Link */}
                <Card>
                    <CardHeader>
                        <CardTitle>Pricing & Coverage Management</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="mb-4 text-gray-600 dark:text-gray-400">
                            All pricing, coverage rules, and tariffs for this
                            plan are managed through the centralized Pricing
                            Dashboard. This provides a unified view of all
                            service pricing and insurance coverage
                            configuration.
                        </p>
                        <Link
                            href={`/admin/pricing-dashboard?plan=${plan.id}`}
                        >
                            <Button>
                                <ExternalLink className="mr-2 h-4 w-4" />
                                Manage Pricing
                            </Button>
                        </Link>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
