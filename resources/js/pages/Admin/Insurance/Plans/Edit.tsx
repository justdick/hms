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
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { useState } from 'react';

interface InsuranceProvider {
    id: number;
    name: string;
    code: string;
}

interface InsurancePlan {
    id: number;
    insurance_provider_id: number;
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
    require_explicit_approval_for_new_items: boolean;
    is_active: boolean;
    effective_from?: string;
    effective_to?: string;
    description?: string;
}

interface Props {
    plan: { data: InsurancePlan };
    providers: { data: InsuranceProvider[] };
}

export default function Edit({ plan: planWrapper, providers }: Props) {
    const plan = planWrapper.data;
    const providersList = providers.data;

    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const [formData, setFormData] = useState({
        insurance_provider_id: plan.insurance_provider_id.toString(),
        plan_name: plan.plan_name,
        plan_code: plan.plan_code,
        plan_type: plan.plan_type,
        coverage_type: plan.coverage_type,
        annual_limit: plan.annual_limit || '',
        visit_limit: plan.visit_limit?.toString() || '',
        default_copay_percentage: plan.default_copay_percentage || '',
        consultation_default: plan.consultation_default || '',
        drugs_default: plan.drugs_default || '',
        labs_default: plan.labs_default || '',
        procedures_default: plan.procedures_default || '',
        requires_referral: plan.requires_referral,
        require_explicit_approval_for_new_items:
            plan.require_explicit_approval_for_new_items,
        is_active: plan.is_active,
        effective_from: plan.effective_from || '',
        effective_to: plan.effective_to || '',
        description: plan.description || '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        router.put(`/admin/insurance/plans/${plan.id}`, formData, {
            onError: (errors) => {
                setErrors(errors);
                setProcessing(false);
            },
            onSuccess: () => {
                setProcessing(false);
            },
        });
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                { title: 'Insurance Plans', href: '/admin/insurance/plans' },
                {
                    title: plan.plan_name,
                    href: `/admin/insurance/plans/${plan.id}`,
                },
                { title: 'Edit', href: '' },
            ]}
        >
            <Head title={`Edit ${plan.plan_name}`} />

            <div className="mx-auto max-w-4xl space-y-6">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Link href={`/admin/insurance/plans/${plan.id}`}>
                        <Button variant="ghost" size="sm">
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to Plan
                        </Button>
                    </Link>
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                            Edit Insurance Plan
                        </h1>
                        <p className="text-sm text-gray-600 dark:text-gray-400">
                            Update plan details and category defaults
                        </p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Basic Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Basic Information</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <Label htmlFor="insurance_provider_id">
                                    Insurance Provider *
                                </Label>
                                <Select
                                    value={formData.insurance_provider_id}
                                    onValueChange={(value) =>
                                        setFormData({
                                            ...formData,
                                            insurance_provider_id: value,
                                        })
                                    }
                                >
                                    <SelectTrigger className="mt-1">
                                        <SelectValue placeholder="Select provider" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {providersList.map((provider) => (
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
                                        value={formData.plan_name}
                                        onChange={(e) =>
                                            setFormData({
                                                ...formData,
                                                plan_name: e.target.value,
                                            })
                                        }
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
                                        value={formData.plan_code}
                                        onChange={(e) =>
                                            setFormData({
                                                ...formData,
                                                plan_code:
                                                    e.target.value.toUpperCase(),
                                            })
                                        }
                                        maxLength={50}
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
                                        value={formData.plan_type}
                                        onValueChange={(value) =>
                                            setFormData({
                                                ...formData,
                                                plan_type: value,
                                            })
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
                                </div>

                                <div>
                                    <Label htmlFor="coverage_type">
                                        Coverage Type *
                                    </Label>
                                    <Select
                                        value={formData.coverage_type}
                                        onValueChange={(value) =>
                                            setFormData({
                                                ...formData,
                                                coverage_type: value,
                                            })
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
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Limits & Co-pay */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Limits & Co-pay</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div>
                                    <Label htmlFor="annual_limit">
                                        Annual Limit
                                    </Label>
                                    <Input
                                        id="annual_limit"
                                        type="number"
                                        step="0.01"
                                        value={formData.annual_limit}
                                        onChange={(e) =>
                                            setFormData({
                                                ...formData,
                                                annual_limit: e.target.value,
                                            })
                                        }
                                        placeholder="Leave empty for unlimited"
                                        className="mt-1"
                                    />
                                </div>

                                <div>
                                    <Label htmlFor="visit_limit">
                                        Visit Limit
                                    </Label>
                                    <Input
                                        id="visit_limit"
                                        type="number"
                                        value={formData.visit_limit}
                                        onChange={(e) =>
                                            setFormData({
                                                ...formData,
                                                visit_limit: e.target.value,
                                            })
                                        }
                                        placeholder="Annual visits"
                                        className="mt-1"
                                    />
                                </div>

                                <div>
                                    <Label htmlFor="default_copay_percentage">
                                        Default Co-pay %
                                    </Label>
                                    <Input
                                        id="default_copay_percentage"
                                        type="number"
                                        step="0.01"
                                        value={
                                            formData.default_copay_percentage
                                        }
                                        onChange={(e) =>
                                            setFormData({
                                                ...formData,
                                                default_copay_percentage:
                                                    e.target.value,
                                            })
                                        }
                                        placeholder="0-100"
                                        className="mt-1"
                                    />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Category Defaults */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Category Default Coverage</CardTitle>
                            <p className="text-sm text-gray-600 dark:text-gray-400">
                                Set default coverage percentages for each
                                service category. These are used when no
                                item-specific coverage rule exists.
                            </p>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <div>
                                    <Label htmlFor="consultation_default">
                                        Consultations %
                                    </Label>
                                    <Input
                                        id="consultation_default"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="100"
                                        value={formData.consultation_default}
                                        onChange={(e) =>
                                            setFormData({
                                                ...formData,
                                                consultation_default:
                                                    e.target.value,
                                            })
                                        }
                                        placeholder="0-100"
                                        className="mt-1"
                                    />
                                    {errors.consultation_default && (
                                        <p className="mt-1 text-sm text-red-600">
                                            {errors.consultation_default}
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="drugs_default">
                                        Drugs %
                                    </Label>
                                    <Input
                                        id="drugs_default"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="100"
                                        value={formData.drugs_default}
                                        onChange={(e) =>
                                            setFormData({
                                                ...formData,
                                                drugs_default: e.target.value,
                                            })
                                        }
                                        placeholder="0-100"
                                        className="mt-1"
                                    />
                                    {errors.drugs_default && (
                                        <p className="mt-1 text-sm text-red-600">
                                            {errors.drugs_default}
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="labs_default">Labs %</Label>
                                    <Input
                                        id="labs_default"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="100"
                                        value={formData.labs_default}
                                        onChange={(e) =>
                                            setFormData({
                                                ...formData,
                                                labs_default: e.target.value,
                                            })
                                        }
                                        placeholder="0-100"
                                        className="mt-1"
                                    />
                                    {errors.labs_default && (
                                        <p className="mt-1 text-sm text-red-600">
                                            {errors.labs_default}
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="procedures_default">
                                        Procedures %
                                    </Label>
                                    <Input
                                        id="procedures_default"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="100"
                                        value={formData.procedures_default}
                                        onChange={(e) =>
                                            setFormData({
                                                ...formData,
                                                procedures_default:
                                                    e.target.value,
                                            })
                                        }
                                        placeholder="0-100"
                                        className="mt-1"
                                    />
                                    {errors.procedures_default && (
                                        <p className="mt-1 text-sm text-red-600">
                                            {errors.procedures_default}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Effective Dates */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Effective Dates</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <Label htmlFor="effective_from">
                                        Effective From
                                    </Label>
                                    <Input
                                        id="effective_from"
                                        type="date"
                                        value={formData.effective_from}
                                        onChange={(e) =>
                                            setFormData({
                                                ...formData,
                                                effective_from: e.target.value,
                                            })
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
                                        value={formData.effective_to}
                                        onChange={(e) =>
                                            setFormData({
                                                ...formData,
                                                effective_to: e.target.value,
                                            })
                                        }
                                        className="mt-1"
                                    />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Options */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Options</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="requires_referral"
                                    checked={formData.requires_referral}
                                    onCheckedChange={(checked) =>
                                        setFormData({
                                            ...formData,
                                            requires_referral:
                                                checked as boolean,
                                        })
                                    }
                                />
                                <Label htmlFor="requires_referral">
                                    Requires Referral
                                </Label>
                            </div>

                            <div className="flex flex-col space-y-2">
                                <div className="flex items-center space-x-2">
                                    <Checkbox
                                        id="require_explicit_approval_for_new_items"
                                        checked={
                                            formData.require_explicit_approval_for_new_items
                                        }
                                        onCheckedChange={(checked) =>
                                            setFormData({
                                                ...formData,
                                                require_explicit_approval_for_new_items:
                                                    checked as boolean,
                                            })
                                        }
                                    />
                                    <Label htmlFor="require_explicit_approval_for_new_items">
                                        Require explicit approval for new items
                                    </Label>
                                </div>
                                <p className="ml-6 text-sm text-gray-600 dark:text-gray-400">
                                    When enabled, new drugs, lab services, and
                                    other items added to the system will not be
                                    automatically covered by default rules.
                                </p>
                            </div>

                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="is_active"
                                    checked={formData.is_active}
                                    onCheckedChange={(checked) =>
                                        setFormData({
                                            ...formData,
                                            is_active: checked as boolean,
                                        })
                                    }
                                />
                                <Label htmlFor="is_active">Active Plan</Label>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Description */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Description</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Textarea
                                id="description"
                                value={formData.description}
                                onChange={(e) =>
                                    setFormData({
                                        ...formData,
                                        description: e.target.value,
                                    })
                                }
                                placeholder="Plan details and coverage information..."
                                rows={4}
                            />
                        </CardContent>
                    </Card>

                    {/* Submit */}
                    <div className="flex justify-end gap-4">
                        <Link href={`/admin/insurance/plans/${plan.id}`}>
                            <Button type="button" variant="outline">
                                Cancel
                            </Button>
                        </Link>
                        <Button type="submit" disabled={processing}>
                            <Save className="mr-2 h-4 w-4" />
                            {processing ? 'Saving...' : 'Save Changes'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
