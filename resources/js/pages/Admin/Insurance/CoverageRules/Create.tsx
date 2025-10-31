import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Shield } from 'lucide-react';

interface InsurancePlan {
    id: number;
    plan_name: string;
    provider?: { name: string };
}

interface Props {
    plans: InsurancePlan[];
}

export default function CoverageRuleCreate({ plans }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        insurance_plan_id: '',
        coverage_category: 'consultation',
        item_code: '',
        item_description: '',
        is_covered: true,
        coverage_type: 'percentage',
        coverage_value: '',
        patient_copay_percentage: '',
        max_quantity_per_visit: '',
        max_amount_per_visit: '',
        requires_preauthorization: false,
        is_active: true,
        effective_from: '',
        effective_to: '',
        notes: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/insurance/coverage-rules');
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                {
                    title: 'Coverage Rules',
                    href: '/admin/insurance/coverage-rules',
                },
                { title: 'Create Rule', href: '' },
            ]}
        >
            <Head title="Create Coverage Rule" />

            <div className="space-y-6">
                <div className="flex items-center gap-4">
                    <Link href="/admin/insurance/coverage-rules">
                        <Button variant="ghost" size="sm">
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back
                        </Button>
                    </Link>
                    <div>
                        <h1 className="flex items-center gap-2 text-3xl font-bold text-gray-900 dark:text-gray-100">
                            <Shield className="h-8 w-8" />
                            Create Coverage Rule
                        </h1>
                    </div>
                </div>

                <Card className="max-w-3xl">
                    <CardHeader>
                        <CardTitle>Coverage Rule Information</CardTitle>
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
                                    <Label htmlFor="coverage_category">
                                        Category *
                                    </Label>
                                    <Select
                                        value={data.coverage_category}
                                        onValueChange={(value) =>
                                            setData('coverage_category', value)
                                        }
                                    >
                                        <SelectTrigger className="mt-1">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="consultation">
                                                Consultation
                                            </SelectItem>
                                            <SelectItem value="drug">
                                                Drug
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
                                            <SelectItem value="nursing">
                                                Nursing
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.coverage_category && (
                                        <p className="mt-1 text-sm text-red-600">
                                            {errors.coverage_category}
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="coverage_type">
                                        Coverage Type *
                                    </Label>
                                    <Select
                                        value={data.coverage_type}
                                        onValueChange={(value) =>
                                            setData('coverage_type', value)
                                        }
                                    >
                                        <SelectTrigger className="mt-1">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="percentage">
                                                Percentage
                                            </SelectItem>
                                            <SelectItem value="fixed">
                                                Fixed Amount
                                            </SelectItem>
                                            <SelectItem value="full">
                                                Full Coverage
                                            </SelectItem>
                                            <SelectItem value="excluded">
                                                Excluded
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.coverage_type && (
                                        <p className="mt-1 text-sm text-red-600">
                                            {errors.coverage_type}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <Label htmlFor="item_code">
                                        Item Code (Optional)
                                    </Label>
                                    <Input
                                        id="item_code"
                                        value={data.item_code}
                                        onChange={(e) =>
                                            setData('item_code', e.target.value)
                                        }
                                        placeholder="Leave empty for category-wide rule"
                                        className="mt-1"
                                    />
                                    {errors.item_code && (
                                        <p className="mt-1 text-sm text-red-600">
                                            {errors.item_code}
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="coverage_value">
                                        Coverage Value{' '}
                                        {data.coverage_type !== 'excluded' &&
                                            '*'}
                                    </Label>
                                    <Input
                                        id="coverage_value"
                                        type="number"
                                        step="0.01"
                                        value={data.coverage_value}
                                        onChange={(e) =>
                                            setData(
                                                'coverage_value',
                                                e.target.value,
                                            )
                                        }
                                        placeholder={
                                            data.coverage_type === 'percentage'
                                                ? '0-100'
                                                : 'Amount'
                                        }
                                        className="mt-1"
                                        disabled={
                                            data.coverage_type === 'excluded'
                                        }
                                    />
                                    {errors.coverage_value && (
                                        <p className="mt-1 text-sm text-red-600">
                                            {errors.coverage_value}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div>
                                <Label htmlFor="item_description">
                                    Description (Optional)
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

                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div>
                                    <Label htmlFor="patient_copay_percentage">
                                        Patient Co-pay %
                                    </Label>
                                    <Input
                                        id="patient_copay_percentage"
                                        type="number"
                                        step="0.01"
                                        value={data.patient_copay_percentage}
                                        onChange={(e) =>
                                            setData(
                                                'patient_copay_percentage',
                                                e.target.value,
                                            )
                                        }
                                        className="mt-1"
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="max_quantity_per_visit">
                                        Max Qty/Visit
                                    </Label>
                                    <Input
                                        id="max_quantity_per_visit"
                                        type="number"
                                        value={data.max_quantity_per_visit}
                                        onChange={(e) =>
                                            setData(
                                                'max_quantity_per_visit',
                                                e.target.value,
                                            )
                                        }
                                        className="mt-1"
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="max_amount_per_visit">
                                        Max Amount/Visit
                                    </Label>
                                    <Input
                                        id="max_amount_per_visit"
                                        type="number"
                                        step="0.01"
                                        value={data.max_amount_per_visit}
                                        onChange={(e) =>
                                            setData(
                                                'max_amount_per_visit',
                                                e.target.value,
                                            )
                                        }
                                        className="mt-1"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <Label htmlFor="effective_from">
                                        Effective From
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
                                        className="mt-1"
                                    />
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
                                </div>
                            </div>

                            <div className="space-y-4">
                                <div className="flex items-center space-x-2">
                                    <Checkbox
                                        id="requires_preauthorization"
                                        checked={data.requires_preauthorization}
                                        onCheckedChange={(checked) =>
                                            setData(
                                                'requires_preauthorization',
                                                checked as boolean,
                                            )
                                        }
                                    />
                                    <Label htmlFor="requires_preauthorization">
                                        Requires Pre-authorization
                                    </Label>
                                </div>
                                <div className="flex items-center space-x-2">
                                    <Checkbox
                                        id="is_active"
                                        checked={data.is_active}
                                        onCheckedChange={(checked) =>
                                            setData(
                                                'is_active',
                                                checked as boolean,
                                            )
                                        }
                                    />
                                    <Label htmlFor="is_active">Active</Label>
                                </div>
                            </div>

                            <div>
                                <Label htmlFor="notes">Notes</Label>
                                <Textarea
                                    id="notes"
                                    value={data.notes}
                                    onChange={(e) =>
                                        setData('notes', e.target.value)
                                    }
                                    rows={3}
                                    className="mt-1"
                                />
                            </div>

                            <div className="flex gap-4 border-t pt-6 dark:border-gray-700">
                                <Link href="/admin/insurance/coverage-rules">
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </Link>
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Creating...' : 'Create Rule'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
