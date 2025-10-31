import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, DollarSign } from 'lucide-react';

interface InsurancePlan {
    id: number;
    plan_name: string;
    provider?: { name: string };
}

interface Props {
    plans: InsurancePlan[];
}

export default function TariffCreate({ plans }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        insurance_plan_id: '',
        item_type: 'drug',
        item_code: '',
        item_description: '',
        standard_price: '',
        insurance_tariff: '',
        effective_from: '',
        effective_to: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/insurance/tariffs');
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                { title: 'Tariffs', href: '/admin/insurance/tariffs' },
                { title: 'Create Tariff', href: '' },
            ]}
        >
            <Head title="Create Insurance Tariff" />

            <div className="space-y-6">
                <div className="flex items-center gap-4">
                    <Link href="/admin/insurance/tariffs">
                        <Button variant="ghost" size="sm">
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back
                        </Button>
                    </Link>
                    <div>
                        <h1 className="flex items-center gap-2 text-3xl font-bold text-gray-900 dark:text-gray-100">
                            <DollarSign className="h-8 w-8" />
                            Create Tariff
                        </h1>
                    </div>
                </div>

                <Card className="max-w-3xl">
                    <CardHeader>
                        <CardTitle>Tariff Information</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div>
                                <Label htmlFor="insurance_plan_id">
                                    Insurance Plan *
                                </Label>
                                <Select
                                    value={data.insurance_plan_id}
                                    onValueChange={(value) =>
                                        setData('insurance_plan_id', value)
                                    }
                                >
                                    <SelectTrigger className="mt-1">
                                        <SelectValue placeholder="Select plan" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {plans.map((plan) => (
                                            <SelectItem
                                                key={plan.id}
                                                value={plan.id.toString()}
                                            >
                                                {plan.plan_name} (
                                                {plan.provider?.name})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.insurance_plan_id && (
                                    <p className="mt-1 text-sm text-red-600">
                                        {errors.insurance_plan_id}
                                    </p>
                                )}
                            </div>

                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <Label htmlFor="item_type">
                                        Item Type *
                                    </Label>
                                    <Select
                                        value={data.item_type}
                                        onValueChange={(value) =>
                                            setData('item_type', value)
                                        }
                                    >
                                        <SelectTrigger className="mt-1">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="drug">
                                                Drug
                                            </SelectItem>
                                            <SelectItem value="service">
                                                Service
                                            </SelectItem>
                                            <SelectItem value="lab">
                                                Lab
                                            </SelectItem>
                                            <SelectItem value="procedure">
                                                Procedure
                                            </SelectItem>
                                            <SelectItem value="ward">
                                                Ward
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.item_type && (
                                        <p className="mt-1 text-sm text-red-600">
                                            {errors.item_type}
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="item_code">
                                        Item Code *
                                    </Label>
                                    <Input
                                        id="item_code"
                                        value={data.item_code}
                                        onChange={(e) =>
                                            setData('item_code', e.target.value)
                                        }
                                        required
                                        className="mt-1"
                                    />
                                    {errors.item_code && (
                                        <p className="mt-1 text-sm text-red-600">
                                            {errors.item_code}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div>
                                <Label htmlFor="item_description">
                                    Item Description
                                </Label>
                                <Input
                                    id="item_description"
                                    value={data.item_description}
                                    onChange={(e) =>
                                        setData(
                                            'item_description',
                                            e.target.value,
                                        )
                                    }
                                    className="mt-1"
                                />
                                {errors.item_description && (
                                    <p className="mt-1 text-sm text-red-600">
                                        {errors.item_description}
                                    </p>
                                )}
                            </div>

                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <Label htmlFor="standard_price">
                                        Standard Price *
                                    </Label>
                                    <Input
                                        id="standard_price"
                                        type="number"
                                        step="0.01"
                                        value={data.standard_price}
                                        onChange={(e) =>
                                            setData(
                                                'standard_price',
                                                e.target.value,
                                            )
                                        }
                                        required
                                        className="mt-1"
                                    />
                                    {errors.standard_price && (
                                        <p className="mt-1 text-sm text-red-600">
                                            {errors.standard_price}
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="insurance_tariff">
                                        Insurance Tariff *
                                    </Label>
                                    <Input
                                        id="insurance_tariff"
                                        type="number"
                                        step="0.01"
                                        value={data.insurance_tariff}
                                        onChange={(e) =>
                                            setData(
                                                'insurance_tariff',
                                                e.target.value,
                                            )
                                        }
                                        required
                                        className="mt-1"
                                    />
                                    {errors.insurance_tariff && (
                                        <p className="mt-1 text-sm text-red-600">
                                            {errors.insurance_tariff}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <Label htmlFor="effective_from">
                                        Effective From *
                                    </Label>
                                    <Input
                                        id="effective_from"
                                        type="date"
                                        value={data.effective_from}
                                        onChange={(e) =>
                                            setData(
                                                'effective_from',
                                                e.target.value,
                                            )
                                        }
                                        required
                                        className="mt-1"
                                    />
                                    {errors.effective_from && (
                                        <p className="mt-1 text-sm text-red-600">
                                            {errors.effective_from}
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="effective_to">
                                        Effective To
                                    </Label>
                                    <Input
                                        id="effective_to"
                                        type="date"
                                        value={data.effective_to}
                                        onChange={(e) =>
                                            setData(
                                                'effective_to',
                                                e.target.value,
                                            )
                                        }
                                        className="mt-1"
                                    />
                                    {errors.effective_to && (
                                        <p className="mt-1 text-sm text-red-600">
                                            {errors.effective_to}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="flex gap-4 border-t pt-6 dark:border-gray-700">
                                <Link href="/admin/insurance/tariffs">
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </Link>
                                <Button type="submit" disabled={processing}>
                                    {processing
                                        ? 'Creating...'
                                        : 'Create Tariff'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
