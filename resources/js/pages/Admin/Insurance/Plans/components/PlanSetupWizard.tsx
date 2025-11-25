import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';
import { Check, ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { ValidationWarning } from '@/components/Insurance/ValidationWarning';
import { HelpTooltip } from '@/components/Insurance/HelpTooltip';

interface InsuranceProvider {
    id: number;
    name: string;
    code: string;
}

interface Props {
    providers: InsuranceProvider[];
}

interface PlanData {
    insurance_provider_id: string;
    plan_name: string;
    plan_code: string;
    plan_type: string;
    coverage_type: string;
    annual_limit: string;
    visit_limit: string;
    default_copay_percentage: string;
    requires_referral: boolean;
    require_explicit_approval_for_new_items: boolean;
    is_active: boolean;
    effective_from: string;
    effective_to: string;
    description: string;
}

interface CoverageData {
    consultation: string;
    drug: string;
    lab: string;
    procedure: string;
    ward: string;
    nursing: string;
}

const categoryLabels: Record<keyof CoverageData, string> = {
    consultation: 'Consultation',
    drug: 'Drugs',
    lab: 'Lab Services',
    procedure: 'Procedures',
    ward: 'Ward Services',
    nursing: 'Nursing Services',
};

export default function PlanSetupWizard({ providers }: Props) {
    const [currentStep, setCurrentStep] = useState(1);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const [planData, setPlanData] = useState<PlanData>({
        insurance_provider_id: '',
        plan_name: '',
        plan_code: '',
        plan_type: 'individual',
        coverage_type: 'comprehensive',
        annual_limit: '',
        visit_limit: '',
        default_copay_percentage: '',
        requires_referral: false,
        require_explicit_approval_for_new_items: false,
        is_active: true,
        effective_from: '',
        effective_to: '',
        description: '',
    });

    const [coverageData, setCoverageData] = useState<CoverageData>({
        consultation: '0',
        drug: '0',
        lab: '0',
        procedure: '0',
        ward: '0',
        nursing: '0',
    });

    const steps = [
        { number: 1, title: 'Plan Details', description: 'Basic plan information' },
        { number: 2, title: 'Coverage', description: 'Set default coverage percentages' },
        { number: 3, title: 'Review', description: 'Review and create plan' },
    ];

    const validateStep1 = (): boolean => {
        const newErrors: Record<string, string> = {};

        if (!planData.insurance_provider_id) {
            newErrors.insurance_provider_id = 'Please select an insurance provider';
        }
        if (!planData.plan_name.trim()) {
            newErrors.plan_name = 'Plan name is required';
        }
        if (!planData.plan_code.trim()) {
            newErrors.plan_code = 'Plan code is required';
        }

        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    const validateStep2 = (): boolean => {
        const newErrors: Record<string, string> = {};

        Object.entries(coverageData).forEach(([category, value]) => {
            if (value !== '') {
                const numValue = parseFloat(value);
                if (isNaN(numValue) || numValue < 0 || numValue > 100) {
                    newErrors[category] = 'Coverage must be between 0 and 100';
                }
            }
        });

        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    const getCoverageWarnings = (): string[] => {
        const warnings: string[] = [];
        
        // Check for 0% coverage on essential categories
        if (coverageData.consultation && parseFloat(coverageData.consultation) === 0) {
            warnings.push('Consultations are set to 0% coverage. Patients will pay full price for all consultations.');
        }
        
        if (coverageData.drug && parseFloat(coverageData.drug) === 0) {
            warnings.push('Drugs are set to 0% coverage. Patients will pay full price for all medications.');
        }

        // Check for very low coverage
        Object.entries(coverageData).forEach(([category, value]) => {
            if (value !== '') {
                const numValue = parseFloat(value);
                if (numValue > 0 && numValue < 30) {
                    warnings.push(`${categoryLabels[category as keyof CoverageData]} has very low coverage (${numValue}%). Consider increasing for better patient care.`);
                }
            }
        });

        // Check if no coverage is set
        const hasAnyCoverage = Object.values(coverageData).some(v => v !== '');
        if (!hasAnyCoverage) {
            warnings.push('No coverage percentages have been set. You can set them now or configure them later.');
        }

        return warnings;
    };

    const handleNext = () => {
        if (currentStep === 1 && !validateStep1()) {
            return;
        }
        if (currentStep === 2 && !validateStep2()) {
            return;
        }
        setCurrentStep((prev) => Math.min(prev + 1, 3));
    };

    const handleBack = () => {
        setCurrentStep((prev) => Math.max(prev - 1, 1));
    };

    const handleCopyToAll = () => {
        const firstValue = Object.values(coverageData).find((v) => v !== '');
        if (firstValue) {
            const newCoverageData = Object.keys(coverageData).reduce(
                (acc, key) => ({
                    ...acc,
                    [key]: firstValue,
                }),
                {} as CoverageData,
            );
            setCoverageData(newCoverageData);
        }
    };

    const handleSubmit = () => {
        setProcessing(true);
        setErrors({});

        // Prepare coverage rules data
        const coverageRules = Object.entries(coverageData)
            .filter(([_, value]) => value !== '')
            .map(([category, value]) => ({
                coverage_category: category,
                coverage_value: parseFloat(value),
            }));

        router.post(
            '/admin/insurance/plans',
            {
                ...planData,
                coverage_rules: coverageRules,
            },
            {
                onError: (errors) => {
                    setErrors(errors);
                    setProcessing(false);
                },
                onSuccess: () => {
                    // Redirect handled by controller
                },
            },
        );
    };

    return (
        <div className="space-y-6">
            {/* Step Indicator */}
            <div className="flex items-center justify-center">
                <div className="flex items-center gap-4">
                    {steps.map((step, index) => (
                        <div key={step.number} className="flex items-center">
                            <div className="flex flex-col items-center">
                                <div
                                    className={cn(
                                        'flex h-10 w-10 items-center justify-center rounded-full border-2 transition-colors',
                                        currentStep > step.number &&
                                            'border-primary bg-primary text-white',
                                        currentStep === step.number &&
                                            'border-primary bg-white text-primary dark:bg-gray-900',
                                        currentStep < step.number &&
                                            'border-gray-300 bg-white text-gray-400 dark:border-gray-600 dark:bg-gray-900',
                                    )}
                                >
                                    {currentStep > step.number ? (
                                        <Check className="h-5 w-5" />
                                    ) : (
                                        <span className="font-semibold">{step.number}</span>
                                    )}
                                </div>
                                <div className="mt-2 text-center">
                                    <p
                                        className={cn(
                                            'text-sm font-medium',
                                            currentStep >= step.number
                                                ? 'text-gray-900 dark:text-gray-100'
                                                : 'text-gray-400 dark:text-gray-600',
                                        )}
                                    >
                                        {step.title}
                                    </p>
                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                        {step.description}
                                    </p>
                                </div>
                            </div>
                            {index < steps.length - 1 && (
                                <div
                                    className={cn(
                                        'mx-4 h-0.5 w-16 transition-colors',
                                        currentStep > step.number
                                            ? 'bg-primary'
                                            : 'bg-gray-300 dark:bg-gray-600',
                                    )}
                                />
                            )}
                        </div>
                    ))}
                </div>
            </div>

            {/* Step Content */}
            <Card className="mx-auto max-w-4xl">
                <CardHeader>
                    <CardTitle>{steps[currentStep - 1].title}</CardTitle>
                    <CardDescription>{steps[currentStep - 1].description}</CardDescription>
                </CardHeader>
                <CardContent>
                    {/* Step 1: Plan Details */}
                    {currentStep === 1 && (
                        <div className="space-y-6">
                            <div>
                                <Label htmlFor="insurance_provider_id">Insurance Provider *</Label>
                                <Select
                                    value={planData.insurance_provider_id}
                                    onValueChange={(value) =>
                                        setPlanData({ ...planData, insurance_provider_id: value })
                                    }
                                >
                                    <SelectTrigger className="mt-1">
                                        <SelectValue placeholder="Select provider" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {providers.map((provider) => (
                                            <SelectItem key={provider.id} value={provider.id.toString()}>
                                                {provider.name} ({provider.code})
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
                                    <Label htmlFor="plan_name">Plan Name *</Label>
                                    <Input
                                        id="plan_name"
                                        type="text"
                                        value={planData.plan_name}
                                        onChange={(e) =>
                                            setPlanData({ ...planData, plan_name: e.target.value })
                                        }
                                        placeholder="e.g., Gold Coverage Plan"
                                        className="mt-1"
                                    />
                                    {errors.plan_name && (
                                        <p className="mt-1 text-sm text-red-600">{errors.plan_name}</p>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="plan_code">Plan Code *</Label>
                                    <Input
                                        id="plan_code"
                                        type="text"
                                        value={planData.plan_code}
                                        onChange={(e) =>
                                            setPlanData({
                                                ...planData,
                                                plan_code: e.target.value.toUpperCase(),
                                            })
                                        }
                                        placeholder="e.g., GOLD-01"
                                        maxLength={50}
                                        className="mt-1"
                                    />
                                    {errors.plan_code && (
                                        <p className="mt-1 text-sm text-red-600">{errors.plan_code}</p>
                                    )}
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <Label htmlFor="plan_type">Plan Type *</Label>
                                    <Select
                                        value={planData.plan_type}
                                        onValueChange={(value) =>
                                            setPlanData({ ...planData, plan_type: value })
                                        }
                                    >
                                        <SelectTrigger className="mt-1">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="individual">Individual</SelectItem>
                                            <SelectItem value="family">Family</SelectItem>
                                            <SelectItem value="corporate">Corporate</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div>
                                    <Label htmlFor="coverage_type">Coverage Type *</Label>
                                    <Select
                                        value={planData.coverage_type}
                                        onValueChange={(value) =>
                                            setPlanData({ ...planData, coverage_type: value })
                                        }
                                    >
                                        <SelectTrigger className="mt-1">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="inpatient">Inpatient Only</SelectItem>
                                            <SelectItem value="outpatient">Outpatient Only</SelectItem>
                                            <SelectItem value="comprehensive">Comprehensive</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div>
                                    <Label htmlFor="annual_limit">Annual Limit</Label>
                                    <Input
                                        id="annual_limit"
                                        type="number"
                                        step="0.01"
                                        value={planData.annual_limit}
                                        onChange={(e) =>
                                            setPlanData({ ...planData, annual_limit: e.target.value })
                                        }
                                        placeholder="Leave empty for unlimited"
                                        className="mt-1"
                                    />
                                </div>

                                <div>
                                    <Label htmlFor="visit_limit">Visit Limit</Label>
                                    <Input
                                        id="visit_limit"
                                        type="number"
                                        value={planData.visit_limit}
                                        onChange={(e) =>
                                            setPlanData({ ...planData, visit_limit: e.target.value })
                                        }
                                        placeholder="Annual visits"
                                        className="mt-1"
                                    />
                                </div>

                                <div>
                                    <Label htmlFor="default_copay_percentage">Default Co-pay %</Label>
                                    <Input
                                        id="default_copay_percentage"
                                        type="number"
                                        step="0.01"
                                        value={planData.default_copay_percentage}
                                        onChange={(e) =>
                                            setPlanData({
                                                ...planData,
                                                default_copay_percentage: e.target.value,
                                            })
                                        }
                                        placeholder="0-100"
                                        className="mt-1"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <Label htmlFor="effective_from">Effective From</Label>
                                    <Input
                                        id="effective_from"
                                        type="date"
                                        value={planData.effective_from}
                                        onChange={(e) =>
                                            setPlanData({ ...planData, effective_from: e.target.value })
                                        }
                                        className="mt-1"
                                    />
                                </div>

                                <div>
                                    <Label htmlFor="effective_to">Effective To</Label>
                                    <Input
                                        id="effective_to"
                                        type="date"
                                        value={planData.effective_to}
                                        onChange={(e) =>
                                            setPlanData({ ...planData, effective_to: e.target.value })
                                        }
                                        className="mt-1"
                                    />
                                </div>
                            </div>

                            <div className="space-y-4">
                                <div className="flex flex-col space-y-2">
                                    <div className="flex items-center space-x-2">
                                        <Checkbox
                                            id="require_explicit_approval_for_new_items"
                                            checked={planData.require_explicit_approval_for_new_items}
                                            onCheckedChange={(checked) =>
                                                setPlanData({
                                                    ...planData,
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
                                        When enabled, new drugs, lab services, and other items added to the
                                        system will not be automatically covered by default rules. Insurance
                                        administrators will need to explicitly review and approve coverage
                                        for each new item.
                                    </p>
                                </div>

                                <div className="flex items-center space-x-2">
                                    <Checkbox
                                        id="is_active"
                                        checked={planData.is_active}
                                        onCheckedChange={(checked) =>
                                            setPlanData({ ...planData, is_active: checked as boolean })
                                        }
                                    />
                                    <Label htmlFor="is_active">Active Plan</Label>
                                </div>
                            </div>

                            <div>
                                <Label htmlFor="description">Description (Optional)</Label>
                                <Textarea
                                    id="description"
                                    value={planData.description}
                                    onChange={(e) =>
                                        setPlanData({ ...planData, description: e.target.value })
                                    }
                                    placeholder="Plan details and coverage information..."
                                    rows={4}
                                    className="mt-1"
                                />
                            </div>
                        </div>
                    )}

                    {/* Step 2: Coverage */}
                    {currentStep === 2 && (
                        <div className="space-y-6">
                            {/* Coverage Warnings */}
                            {getCoverageWarnings().map((warning, index) => (
                                <ValidationWarning
                                    key={index}
                                    message={warning}
                                    severity="warning"
                                />
                            ))}

                            <div>
                                <div className="mb-4 flex items-center justify-between">
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <Label className="text-base">Coverage Percentages</Label>
                                            <HelpTooltip
                                                content="Set the default coverage percentage for each service category. This applies to all items in that category unless you add specific exceptions later."
                                                example="80% means insurance pays 80%, patient pays 20%"
                                            />
                                        </div>
                                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                            Set the default coverage percentage for each category. You can
                                            modify the preset values or leave categories empty.
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={handleCopyToAll}
                                    >
                                        Copy to All
                                    </Button>
                                </div>

                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    {(Object.keys(coverageData) as Array<keyof CoverageData>).map(
                                        (category) => (
                                            <div key={category}>
                                                <Label htmlFor={category}>
                                                    {categoryLabels[category]}
                                                </Label>
                                                <div className="mt-1 flex items-center gap-2">
                                                    <Input
                                                        id={category}
                                                        type="number"
                                                        min="0"
                                                        max="100"
                                                        step="0.01"
                                                        value={coverageData[category]}
                                                        onChange={(e) =>
                                                            setCoverageData({
                                                                ...coverageData,
                                                                [category]: e.target.value,
                                                            })
                                                        }
                                                        placeholder="0-100"
                                                        className="flex-1"
                                                    />
                                                    <span className="text-sm text-gray-500">%</span>
                                                    {coverageData[category] && (
                                                        <span className="text-sm text-gray-600 dark:text-gray-400">
                                                            (Copay:{' '}
                                                            {(
                                                                100 - parseFloat(coverageData[category])
                                                            ).toFixed(1)}
                                                            %)
                                                        </span>
                                                    )}
                                                </div>
                                                {errors[category] && (
                                                    <p className="mt-1 text-sm text-red-600">
                                                        {errors[category]}
                                                    </p>
                                                )}
                                            </div>
                                        ),
                                    )}
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Step 3: Review */}
                    {currentStep === 3 && (
                        <div className="space-y-6">
                            <div>
                                <h3 className="mb-4 text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    Plan Details
                                </h3>
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <p className="text-sm text-gray-600 dark:text-gray-400">
                                            Provider
                                        </p>
                                        <p className="font-medium text-gray-900 dark:text-gray-100">
                                            {providers.find(
                                                (p) => p.id.toString() === planData.insurance_provider_id,
                                            )?.name || 'N/A'}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-600 dark:text-gray-400">
                                            Plan Name
                                        </p>
                                        <p className="font-medium text-gray-900 dark:text-gray-100">
                                            {planData.plan_name}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-600 dark:text-gray-400">
                                            Plan Code
                                        </p>
                                        <p className="font-medium text-gray-900 dark:text-gray-100">
                                            {planData.plan_code}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-600 dark:text-gray-400">
                                            Plan Type
                                        </p>
                                        <p className="font-medium capitalize text-gray-900 dark:text-gray-100">
                                            {planData.plan_type}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-600 dark:text-gray-400">
                                            Coverage Type
                                        </p>
                                        <p className="font-medium capitalize text-gray-900 dark:text-gray-100">
                                            {planData.coverage_type}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-gray-600 dark:text-gray-400">Status</p>
                                        <p className="font-medium text-gray-900 dark:text-gray-100">
                                            {planData.is_active ? 'Active' : 'Inactive'}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div className="border-t pt-6 dark:border-gray-700">
                                <h3 className="mb-4 text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    Default Coverage
                                </h3>
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    {(Object.keys(coverageData) as Array<keyof CoverageData>).map(
                                        (category) =>
                                            coverageData[category] && (
                                                <div
                                                    key={category}
                                                    className="flex items-center justify-between rounded-lg border p-3 dark:border-gray-700"
                                                >
                                                    <span className="font-medium text-gray-900 dark:text-gray-100">
                                                        {categoryLabels[category]}
                                                    </span>
                                                    <div className="text-right">
                                                        <p className="text-lg font-bold text-primary">
                                                            {coverageData[category]}%
                                                        </p>
                                                        <p className="text-xs text-gray-600 dark:text-gray-400">
                                                            Copay:{' '}
                                                            {(
                                                                100 - parseFloat(coverageData[category])
                                                            ).toFixed(1)}
                                                            %
                                                        </p>
                                                    </div>
                                                </div>
                                            ),
                                    )}
                                </div>
                                {Object.values(coverageData).every((v) => v === '') && (
                                    <p className="text-sm text-gray-600 dark:text-gray-400">
                                        No default coverage rules will be created. You can add them later.
                                    </p>
                                )}
                            </div>

                            {Object.keys(errors).length > 0 && (
                                <div className="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
                                    <p className="text-sm font-medium text-red-800 dark:text-red-200">
                                        There were errors creating the plan. Please try again.
                                    </p>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Navigation Buttons */}
                    <div className="mt-8 flex justify-between border-t pt-6 dark:border-gray-700">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={handleBack}
                            disabled={currentStep === 1 || processing}
                        >
                            <ChevronLeft className="mr-2 h-4 w-4" />
                            Back
                        </Button>

                        {currentStep < 3 ? (
                            <Button type="button" onClick={handleNext}>
                                Next
                                <ChevronRight className="ml-2 h-4 w-4" />
                            </Button>
                        ) : (
                            <Button type="button" onClick={handleSubmit} disabled={processing}>
                                {processing ? 'Creating Plan...' : 'Create Plan'}
                            </Button>
                        )}
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
