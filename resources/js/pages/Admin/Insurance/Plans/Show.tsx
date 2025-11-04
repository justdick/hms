import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
    CheckCircle2,
    Edit,
    FileText,
    Plus,
    XCircle,
} from 'lucide-react';

interface InsuranceProvider {
    id: number;
    name: string;
    code: string;
}

interface CoverageRule {
    id: number;
    coverage_category: string;
    item_code?: string;
    item_description?: string;
    coverage_type: string;
    coverage_percentage?: number;
    max_allowed_amount?: string;
}

interface Tariff {
    id: number;
    item_type: string;
    item_code: string;
    item_description: string;
    standard_price: string;
    insurance_tariff: string;
    effective_from: string;
    effective_to?: string;
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
    requires_referral: boolean;
    is_active: boolean;
    effective_from?: string;
    effective_to?: string;
    description?: string;
    provider?: InsuranceProvider;
    coverage_rules?: CoverageRule[];
    tariffs?: Tariff[];
}

interface Props {
    plan: {
        data: InsurancePlan;
    };
    simplifiedUiEnabled?: boolean;
}

export default function InsurancePlanShow({
    plan: planWrapper,
    simplifiedUiEnabled = false,
}: Props) {
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

                {/* Coverage Rules */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>Coverage Rules</CardTitle>
                        <div className="flex gap-2">
                            <Link
                                href={
                                    simplifiedUiEnabled
                                        ? `/admin/insurance/plans/${plan.id}/coverage`
                                        : `/admin/insurance/plans/${plan.id}/coverage-rules`
                                }
                            >
                                <Button size="sm" variant="outline">
                                    Manage Coverage
                                </Button>
                            </Link>
                            <Link
                                href={`/admin/insurance/coverage-rules/create?plan=${plan.id}`}
                            >
                                <Button size="sm">
                                    <Plus className="mr-2 h-4 w-4" />
                                    Add Rule
                                </Button>
                            </Link>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {plan.coverage_rules &&
                        plan.coverage_rules.length > 0 ? (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Category</TableHead>
                                        <TableHead>Item Code</TableHead>
                                        <TableHead>Description</TableHead>
                                        <TableHead>Coverage Type</TableHead>
                                        <TableHead>Coverage %</TableHead>
                                        <TableHead></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {plan.coverage_rules.map((rule) => (
                                        <TableRow key={rule.id}>
                                            <TableCell className="capitalize">
                                                {rule.coverage_category}
                                            </TableCell>
                                            <TableCell>
                                                {rule.item_code}
                                            </TableCell>
                                            <TableCell>
                                                {rule.item_description}
                                            </TableCell>
                                            <TableCell className="capitalize">
                                                {rule.coverage_type}
                                            </TableCell>
                                            <TableCell>
                                                {rule.coverage_percentage}%
                                            </TableCell>
                                            <TableCell>
                                                <Link
                                                    href={`/admin/insurance/coverage-rules/${rule.id}/edit`}
                                                >
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                    >
                                                        Edit
                                                    </Button>
                                                </Link>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        ) : (
                            <p className="py-8 text-center text-gray-600 dark:text-gray-400">
                                No coverage rules defined
                            </p>
                        )}
                    </CardContent>
                </Card>

                {/* Tariffs */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>Tariffs</CardTitle>
                        <Link
                            href={`/admin/insurance/tariffs/create?plan=${plan.id}`}
                        >
                            <Button size="sm">
                                <Plus className="mr-2 h-4 w-4" />
                                Add Tariff
                            </Button>
                        </Link>
                    </CardHeader>
                    <CardContent>
                        {plan.tariffs && plan.tariffs.length > 0 ? (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Item Type</TableHead>
                                        <TableHead>Code</TableHead>
                                        <TableHead>Description</TableHead>
                                        <TableHead>Standard Price</TableHead>
                                        <TableHead>Insurance Tariff</TableHead>
                                        <TableHead>Effective</TableHead>
                                        <TableHead></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {plan.tariffs.map((tariff) => (
                                        <TableRow key={tariff.id}>
                                            <TableCell className="capitalize">
                                                {tariff.item_type}
                                            </TableCell>
                                            <TableCell>
                                                {tariff.item_code}
                                            </TableCell>
                                            <TableCell>
                                                {tariff.item_description}
                                            </TableCell>
                                            <TableCell>
                                                $
                                                {parseFloat(
                                                    tariff.standard_price,
                                                ).toFixed(2)}
                                            </TableCell>
                                            <TableCell>
                                                $
                                                {parseFloat(
                                                    tariff.insurance_tariff,
                                                ).toFixed(2)}
                                            </TableCell>
                                            <TableCell>
                                                {tariff.effective_from}
                                            </TableCell>
                                            <TableCell>
                                                <Link
                                                    href={`/admin/insurance/tariffs/${tariff.id}/edit`}
                                                >
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                    >
                                                        Edit
                                                    </Button>
                                                </Link>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        ) : (
                            <p className="py-8 text-center text-gray-600 dark:text-gray-400">
                                No tariffs defined
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
