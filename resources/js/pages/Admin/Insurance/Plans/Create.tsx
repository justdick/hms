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
import { ArrowLeft, FileText } from 'lucide-react';

interface InsuranceProvider {
    id: number;
    name: string;
    code: string;
}

interface Props {
    providers: InsuranceProvider[];
}

export default function InsurancePlanCreate({ providers }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        insurance_provider_id: '',
        plan_name: '',
        plan_code: '',
        plan_type: 'individual',
        coverage_type: 'comprehensive',
        annual_limit: '',
        visit_limit: '',
        default_copay_percentage: '',
        requires_referral: false,
        is_active: true,
        effective_from: '',
        effective_to: '',
        description: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/insurance/plans');
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                { title: 'Insurance Plans', href: '/admin/insurance/plans' },
                { title: 'Create Plan', href: '' },
            ]}
        >
            <Head title="Create Insurance Plan" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Link href="/admin/insurance/plans">
                        <Button variant="ghost" size="sm">
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to Plans
                        </Button>
                    </Link>
                    <div>
                        <h1 className="flex items-center gap-2 text-3xl font-bold text-gray-900 dark:text-gray-100">
                            <FileText className="h-8 w-8" />
                            Create Insurance Plan
                        </h1>
                        <p className="mt-1 text-gray-600 dark:text-gray-400">
                            Add a new insurance plan
                        </p>
                    </div>
                </div>

                <Card className="max-w-3xl">
                    <CardHeader>
                        <CardTitle>Plan Information</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div>
                                <Label htmlFor="insurance_provider_id">
                                    Insurance Provider *
                                </Label>
                                <Select
                                    value={data.insurance_provider_id}
                                    onValueChange={(value) =>
                                        setData('insurance_provider_id', value)
                                    }
                                >
                                    <SelectTrigger className="mt-1">
                                        <SelectValue placeholder="Select provider" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {providers.map((provider) => (
                                            <SelectItem
                                                key={provider.id}
                                                value={provider.id.toString()}
                                            >
                                                {provider.name} ({provider.code}
                                                )
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.insurance_provider_id && (
                                    <p className="mt-1 text-sm text-red-600">
                                        {errors.insurance_provider_id}
                                    </p>
                                )}
                            </div>

                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <Label htmlFor="plan_name">
                                        Plan Name *
                                    </Label>
                                    <Input
                                        id="plan_name"
                                        type="text"
                                        value={data.plan_name}
                                        onChange={(e) =>
                                            setData('plan_name', e.target.value)
                                        }
                                        placeholder="e.g., Gold Coverage Plan"
                                        required
                                        className="mt-1"
                                    />
                                    {errors.plan_name && (
                                        <p className="mt-1 text-sm text-red-600">
                                            {errors.plan_name}
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="plan_code">
                                        Plan Code *
                                    </Label>
                                    <Input
                                        id="plan_code"
                                        type="text"
                                        value={data.plan_code}
                                        onChange={(e) =>
                                            setData(
                                                'plan_code',
                                                e.target.value.toUpperCase(),
                                            )
                                        }
                                        placeholder="e.g., GOLD-01"
                                        maxLength={50}
                                        required
                                        className="mt-1"
                                    />
                                    {errors.plan_code && (
                                        <p className="mt-1 text-sm text-red-600">
                                            {errors.plan_code}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <Label htmlFor="plan_type">
                                        Plan Type *
                                    </Label>
                                    <Select
                                        value={data.plan_type}
                                        onValueChange={(value) =>
                                            setData('plan_type', value)
                                        }
                                    >
                                        <SelectTrigger className="mt-1">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="individual">
                                                Individual
                                            </SelectItem>
                                            <SelectItem value="family">
                                                Family
                                            </SelectItem>
                                            <SelectItem value="corporate">
                                                Corporate
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.plan_type && (
                                        <p className="mt-1 text-sm text-red-600">
                                            {errors.plan_type}
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
                                            <SelectItem value="inpatient">
                                                Inpatient Only
                                            </SelectItem>
                                            <SelectItem value="outpatient">
                                                Outpatient Only
                                            </SelectItem>
                                            <SelectItem value="comprehensive">
                                                Comprehensive
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

                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div>
                                    <Label htmlFor="annual_limit">
                                        Annual Limit
                                    </Label>
                                    <Input
                                        id="annual_limit"
                                        type="number"
                                        step="0.01"
                                        value={data.annual_limit}
                                        onChange={(e) =>
                                            setData(
                                                'annual_limit',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Leave empty for unlimited"
                                        className="mt-1"
                                    />
                                    {errors.annual_limit && (
                                        <p className="mt-1 text-sm text-red-600">
                                            {errors.annual_limit}
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="visit_limit">
                                        Visit Limit
                                    </Label>
                                    <Input
                                        id="visit_limit"
                                        type="number"
                                        value={data.visit_limit}
                                        onChange={(e) =>
                                            setData(
                                                'visit_limit',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Annual visits"
                                        className="mt-1"
                                    />
                                    {errors.visit_limit && (
                                        <p className="mt-1 text-sm text-red-600">
                                            {errors.visit_limit}
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="default_copay_percentage">
                                        Default Co-pay %
                                    </Label>
                                    <Input
                                        id="default_copay_percentage"
                                        type="number"
                                        step="0.01"
                                        value={data.default_copay_percentage}
                                        onChange={(e) =>
                                            setData(
                                                'default_copay_percentage',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="0-100"
                                        className="mt-1"
                                    />
                                    {errors.default_copay_percentage && (
                                        <p className="mt-1 text-sm text-red-600">
                                            {errors.default_copay_percentage}
                                        </p>
                                    )}
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

                            <div className="space-y-4">
                                <div className="flex items-center space-x-2">
                                    <Checkbox
                                        id="requires_referral"
                                        checked={data.requires_referral}
                                        onCheckedChange={(checked) =>
                                            setData(
                                                'requires_referral',
                                                checked as boolean,
                                            )
                                        }
                                    />
                                    <Label htmlFor="requires_referral">
                                        Requires Referral
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
                                    <Label htmlFor="is_active">
                                        Active Plan
                                    </Label>
                                </div>
                            </div>

                            <div>
                                <Label htmlFor="description">
                                    Description (Optional)
                                </Label>
                                <Textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) =>
                                        setData('description', e.target.value)
                                    }
                                    placeholder="Plan details and coverage information..."
                                    rows={4}
                                    className="mt-1"
                                />
                                {errors.description && (
                                    <p className="mt-1 text-sm text-red-600">
                                        {errors.description}
                                    </p>
                                )}
                            </div>

                            <div className="flex gap-4 border-t pt-6 dark:border-gray-700">
                                <Link href="/admin/insurance/plans">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="flex-1 md:flex-none"
                                    >
                                        Cancel
                                    </Button>
                                </Link>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="flex-1 md:flex-none"
                                >
                                    {processing ? 'Creating...' : 'Create Plan'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
