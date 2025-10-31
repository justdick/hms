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
import { Head, Link, router } from '@inertiajs/react';
import { Filter, Plus, Shield } from 'lucide-react';
import { useState } from 'react';

interface InsurancePlan {
    id: number;
    plan_name: string;
}

interface CoverageRule {
    id: number;
    coverage_category: string;
    item_code?: string;
    item_description?: string;
    coverage_type: string;
    coverage_value?: string;
    is_active: boolean;
    plan?: { id: number; plan_name: string; provider?: { name: string } };
}

interface Props {
    rules: { data: CoverageRule[]; links: any; meta: any };
    plans: InsurancePlan[];
    filters: { plan_id?: string; coverage_category?: string; search?: string };
}

export default function CoverageRulesIndex({ rules, plans, filters }: Props) {
    const [planId, setPlanId] = useState(filters.plan_id || 'all');
    const [category, setCategory] = useState(
        filters.coverage_category || 'all',
    );
    const [search, setSearch] = useState(filters.search || '');

    const handleFilter = () => {
        router.get('/admin/insurance/coverage-rules', {
            plan_id: planId === 'all' ? undefined : planId,
            coverage_category: category === 'all' ? undefined : category,
            search: search || undefined,
        });
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                { title: 'Coverage Rules', href: '' },
            ]}
        >
            <Head title="Coverage Rules" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="flex items-center gap-2 text-3xl font-bold text-gray-900 dark:text-gray-100">
                            <Shield className="h-8 w-8" />
                            Coverage Rules
                        </h1>
                        <p className="mt-2 text-gray-600 dark:text-gray-400">
                            Manage insurance coverage rules
                        </p>
                    </div>
                    <Link href="/admin/insurance/coverage-rules/create">
                        <Button>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Rule
                        </Button>
                    </Link>
                </div>

                <Card>
                    <CardContent className="p-4">
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                            <div>
                                <Label>Plan</Label>
                                <Select
                                    value={planId}
                                    onValueChange={setPlanId}
                                >
                                    <SelectTrigger className="mt-1">
                                        <SelectValue placeholder="All plans" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All plans
                                        </SelectItem>
                                        {plans.map((plan) => (
                                            <SelectItem
                                                key={plan.id}
                                                value={plan.id.toString()}
                                            >
                                                {plan.plan_name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label>Category</Label>
                                <Select
                                    value={category}
                                    onValueChange={setCategory}
                                >
                                    <SelectTrigger className="mt-1">
                                        <SelectValue placeholder="All categories" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All categories
                                        </SelectItem>
                                        <SelectItem value="consultation">
                                            Consultation
                                        </SelectItem>
                                        <SelectItem value="drug">
                                            Drug
                                        </SelectItem>
                                        <SelectItem value="lab">Lab</SelectItem>
                                        <SelectItem value="procedure">
                                            Procedure
                                        </SelectItem>
                                        <SelectItem value="ward">
                                            Ward
                                        </SelectItem>
                                        <SelectItem value="nursing">
                                            Nursing
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label>Search</Label>
                                <Input
                                    className="mt-1"
                                    placeholder="Item code or description"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                />
                            </div>
                            <div className="flex items-end">
                                <Button
                                    onClick={handleFilter}
                                    className="w-full"
                                >
                                    <Filter className="mr-2 h-4 w-4" />
                                    Filter
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-0">
                        {rules.data.length > 0 ? (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Plan</TableHead>
                                        <TableHead>Category</TableHead>
                                        <TableHead>Item Code</TableHead>
                                        <TableHead>Description</TableHead>
                                        <TableHead>Coverage Type</TableHead>
                                        <TableHead>Value</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {rules.data.map((rule) => (
                                        <TableRow key={rule.id}>
                                            <TableCell>
                                                {rule.plan?.plan_name}
                                            </TableCell>
                                            <TableCell className="capitalize">
                                                {rule.coverage_category}
                                            </TableCell>
                                            <TableCell>
                                                {rule.item_code || 'All'}
                                            </TableCell>
                                            <TableCell>
                                                {rule.item_description ||
                                                    'All items'}
                                            </TableCell>
                                            <TableCell className="capitalize">
                                                {rule.coverage_type}
                                            </TableCell>
                                            <TableCell>
                                                {rule.coverage_value || 'N/A'}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={
                                                        rule.is_active
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {rule.is_active
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </Badge>
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
                            <div className="py-12 text-center">
                                <h3 className="mb-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    No coverage rules found
                                </h3>
                                <Link href="/admin/insurance/coverage-rules/create">
                                    <Button>
                                        <Plus className="mr-2 h-4 w-4" />
                                        Add Rule
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
