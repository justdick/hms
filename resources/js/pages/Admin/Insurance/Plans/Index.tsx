import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
    ClipboardList,
    FileText,
    MoreVertical,
    Plus,
    Settings,
    Shield,
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
    default_copay_percentage?: string;
    is_active: boolean;
    provider?: InsuranceProvider;
    coverage_rules_count?: number;
    tariffs_count?: number;
}

interface Props {
    plans: {
        data: InsurancePlan[];
        links: any;
        meta: any;
    };
}

export default function InsurancePlansIndex({ plans }: Props) {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                { title: 'Insurance Plans', href: '' },
            ]}
        >
            <Head title="Insurance Plans" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="flex items-center gap-2 text-3xl font-bold text-gray-900 dark:text-gray-100">
                            <FileText className="h-8 w-8" />
                            Insurance Plans
                        </h1>
                        <p className="mt-2 text-gray-600 dark:text-gray-400">
                            Manage insurance plans and coverage details
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Link href="/admin/insurance/plans/create">
                            <Button>
                                <Plus className="mr-2 h-4 w-4" />
                                Add Plan
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* Plans Table */}
                <Card>
                    <CardContent className="p-0">
                        {plans.data.length > 0 ? (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Plan Name</TableHead>
                                        <TableHead>Code</TableHead>
                                        <TableHead>Provider</TableHead>
                                        <TableHead>Type</TableHead>
                                        <TableHead>Coverage</TableHead>
                                        <TableHead>Annual Limit</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Rules</TableHead>
                                        <TableHead>Tariffs</TableHead>
                                        <TableHead>Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {plans.data.map((plan) => (
                                        <TableRow key={plan.id}>
                                            <TableCell className="font-medium">
                                                {plan.plan_name}
                                            </TableCell>
                                            <TableCell>
                                                {plan.plan_code}
                                            </TableCell>
                                            <TableCell>
                                                {plan.provider?.name || 'N/A'}
                                            </TableCell>
                                            <TableCell className="capitalize">
                                                {plan.plan_type}
                                            </TableCell>
                                            <TableCell className="capitalize">
                                                {plan.coverage_type}
                                            </TableCell>
                                            <TableCell>
                                                {plan.annual_limit
                                                    ? `$${parseFloat(plan.annual_limit).toLocaleString()}`
                                                    : 'Unlimited'}
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
                                                {/* Desktop: Show all buttons */}
                                                <div className="hidden items-center gap-2 lg:flex">
                                                    <Link
                                                        href={`/admin/insurance/plans/${plan.id}/coverage`}
                                                    >
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                        >
                                                            <Settings className="mr-2 h-4 w-4" />
                                                            Manage Coverage
                                                        </Button>
                                                    </Link>
                                                    <Link
                                                        href={`/admin/insurance/claims?plan_id=${plan.id}`}
                                                    >
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                        >
                                                            <ClipboardList className="mr-2 h-4 w-4" />
                                                            View Claims
                                                        </Button>
                                                    </Link>
                                                    <Link
                                                        href={`/admin/insurance/plans/${plan.id}`}
                                                    >
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                        >
                                                            <FileText className="h-4 w-4" />
                                                        </Button>
                                                    </Link>
                                                </div>

                                                {/* Mobile: Show dropdown menu */}
                                                <div className="lg:hidden">
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger
                                                            asChild
                                                        >
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                            >
                                                                <MoreVertical className="h-4 w-4" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuItem
                                                                asChild
                                                            >
                                                                <Link
                                                                    href={`/admin/insurance/plans/${plan.id}/coverage`}
                                                                >
                                                                    <Settings className="mr-2 h-4 w-4" />
                                                                    Manage
                                                                    Coverage
                                                                </Link>
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem
                                                                asChild
                                                            >
                                                                <Link
                                                                    href={`/admin/insurance/claims?plan_id=${plan.id}`}
                                                                >
                                                                    <ClipboardList className="mr-2 h-4 w-4" />
                                                                    View Claims
                                                                </Link>
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem
                                                                asChild
                                                            >
                                                                <Link
                                                                    href={`/admin/insurance/plans/${plan.id}`}
                                                                >
                                                                    <FileText className="mr-2 h-4 w-4" />
                                                                    View Details
                                                                </Link>
                                                            </DropdownMenuItem>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        ) : (
                            <div className="py-12 text-center">
                                <Shield className="mx-auto mb-4 h-16 w-16 text-gray-300 dark:text-gray-600" />
                                <h3 className="mb-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    No insurance plans found
                                </h3>
                                <p className="mb-4 text-gray-600 dark:text-gray-400">
                                    Get started by adding your first insurance
                                    plan.
                                </p>
                                <Link href="/admin/insurance/plans/create">
                                    <Button>
                                        <Plus className="mr-2 h-4 w-4" />
                                        Add Plan
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
