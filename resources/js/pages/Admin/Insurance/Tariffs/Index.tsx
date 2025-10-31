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
import { DollarSign, Filter, Plus } from 'lucide-react';
import { useState } from 'react';

interface InsurancePlan {
    id: number;
    plan_name: string;
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
    plan?: { plan_name: string; provider?: { name: string } };
}

interface Props {
    tariffs: { data: Tariff[]; links: any; meta: any };
    plans: InsurancePlan[];
    filters: { plan_id?: string; item_type?: string; search?: string };
}

export default function TariffsIndex({ tariffs, plans, filters }: Props) {
    const [planId, setPlanId] = useState(filters.plan_id || 'all');
    const [itemType, setItemType] = useState(filters.item_type || 'all');
    const [search, setSearch] = useState(filters.search || '');

    const handleFilter = () => {
        router.get('/admin/insurance/tariffs', {
            plan_id: planId === 'all' ? undefined : planId,
            item_type: itemType === 'all' ? undefined : itemType,
            search: search || undefined,
        });
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                { title: 'Tariffs', href: '' },
            ]}
        >
            <Head title="Insurance Tariffs" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="flex items-center gap-2 text-3xl font-bold text-gray-900 dark:text-gray-100">
                            <DollarSign className="h-8 w-8" />
                            Insurance Tariffs
                        </h1>
                        <p className="mt-2 text-gray-600 dark:text-gray-400">
                            Manage insurance pricing tariffs
                        </p>
                    </div>
                    <Link href="/admin/insurance/tariffs/create">
                        <Button>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Tariff
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
                                <Label>Item Type</Label>
                                <Select
                                    value={itemType}
                                    onValueChange={setItemType}
                                >
                                    <SelectTrigger className="mt-1">
                                        <SelectValue placeholder="All types" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All types
                                        </SelectItem>
                                        <SelectItem value="drug">
                                            Drug
                                        </SelectItem>
                                        <SelectItem value="service">
                                            Service
                                        </SelectItem>
                                        <SelectItem value="lab">Lab</SelectItem>
                                        <SelectItem value="procedure">
                                            Procedure
                                        </SelectItem>
                                        <SelectItem value="ward">
                                            Ward
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
                        {tariffs.data.length > 0 ? (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Plan</TableHead>
                                        <TableHead>Item Type</TableHead>
                                        <TableHead>Code</TableHead>
                                        <TableHead>Description</TableHead>
                                        <TableHead>Standard Price</TableHead>
                                        <TableHead>Insurance Tariff</TableHead>
                                        <TableHead>Effective From</TableHead>
                                        <TableHead></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {tariffs.data.map((tariff) => (
                                        <TableRow key={tariff.id}>
                                            <TableCell>
                                                {tariff.plan?.plan_name}
                                            </TableCell>
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
                            <div className="py-12 text-center">
                                <h3 className="mb-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    No tariffs found
                                </h3>
                                <Link href="/admin/insurance/tariffs/create">
                                    <Button>
                                        <Plus className="mr-2 h-4 w-4" />
                                        Add Tariff
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
