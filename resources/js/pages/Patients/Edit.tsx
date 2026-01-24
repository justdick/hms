import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { useNhisExtension, NhisIdType } from '@/hooks/useNhisExtension';
import AppLayout from '@/layouts/app-layout';
import { copyToClipboard } from '@/lib/utils';
import patients from '@/routes/patients';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    ExternalLink,
    Loader2,
    Shield,
} from 'lucide-react';
import React, { useEffect, useState } from 'react';
import { toast } from 'sonner';

interface InsurancePlan {
    id: number;
    plan_name: string;
    plan_code: string;
    provider: {
        id: number;
        name: string;
        code: string;
        is_nhis?: boolean;
    };
}

interface NhisSettings {
    verification_mode: 'manual' | 'extension';
    nhia_portal_url: string;
    auto_open_portal: boolean;
    credentials?: {
        username: string;
        password: string;
    } | null;
}

/**
 * Parse NHIS date format (could be DD-MM-YYYY or YYYY-MM-DD)
 */
function parseNhisDate(dateStr: string | null | undefined): string {
    if (!dateStr) return '';

    // Try DD-MM-YYYY format first (common NHIS format)
    const ddmmyyyy = dateStr.match(/^(\d{2})-(\d{2})-(\d{4})$/);
    if (ddmmyyyy) {
        return `${ddmmyyyy[3]}-${ddmmyyyy[2]}-${ddmmyyyy[1]}`;
    }

    // Try YYYY-MM-DD format - return as is
    const yyyymmdd = dateStr.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (yyyymmdd) {
        return dateStr;
    }

    // Fallback to Date parsing
    const parsed = new Date(dateStr);
    if (!isNaN(parsed.getTime())) {
        return parsed.toISOString().split('T')[0];
    }

    return '';
}

/**
 * Check if a date string represents an expired date
 */
function isDateExpired(dateStr: string): boolean {
    if (!dateStr) return false;
    const date = new Date(dateStr);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return date < today;
}

interface PatientInsurance {
    id: number;
    insurance_plan_id: number;
    membership_id: string;
    policy_number: string | null;
    card_number: string | null;
    is_dependent: boolean;
    principal_member_name: string | null;
    relationship_to_principal: string | null;
    coverage_start_date: string;
    coverage_end_date: string | null;
}

interface Patient {
    id: number;
    patient_number: string;
    first_name: string;
    last_name: string;
    full_name: string;
    gender: 'male' | 'female';
    date_of_birth: string;
    age: number;
    phone_number: string | null;
    address: string | null;
    emergency_contact_name: string | null;
    emergency_contact_phone: string | null;
    national_id: string | null;
    status: string;
    past_medical_surgical_history: string | null;
    drug_history: string | null;
    family_history: string | null;
    social_history: string | null;
    active_insurance: PatientInsurance | null;
}

interface Props {
    patient: Patient;
    insurance_plans: InsurancePlan[];
    nhis_settings?: NhisSettings;
}

export default function PatientsEdit({
    patient,
    insurance_plans,
    nhis_settings,
}: Props) {
    const [hasInsurance, setHasInsurance] = useState(
        !!patient.active_insurance,
    );

    // NHIS Extension hook
    const { isVerifying, cccData, startVerification, clearCccData } =
        useNhisExtension();

    const { data, setData, processing, errors } = useForm({
        first_name: patient.first_name || '',
        last_name: patient.last_name || '',
        gender: (patient.gender || '') as 'male' | 'female' | '',
        date_of_birth: patient.date_of_birth || '',
        phone_number: patient.phone_number || '',
        address: patient.address || '',
        emergency_contact_name: patient.emergency_contact_name || '',
        emergency_contact_phone: patient.emergency_contact_phone || '',
        national_id: patient.national_id || '',
        // Insurance fields
        has_insurance: !!patient.active_insurance,
        insurance_plan_id:
            patient.active_insurance?.insurance_plan_id?.toString() || '',
        membership_id: patient.active_insurance?.membership_id || '',
        policy_number: patient.active_insurance?.policy_number || '',
        card_number: patient.active_insurance?.card_number || '',
        is_dependent: patient.active_insurance?.is_dependent || false,
        principal_member_name:
            patient.active_insurance?.principal_member_name || '',
        relationship_to_principal:
            patient.active_insurance?.relationship_to_principal || '',
        coverage_start_date:
            patient.active_insurance?.coverage_start_date || '',
        coverage_end_date: patient.active_insurance?.coverage_end_date || '',
    });

    // Update has_insurance in form data when checkbox changes
    useEffect(() => {
        setData('has_insurance', hasInsurance);
    }, [hasInsurance]);

    // Get selected plan details
    const selectedPlan = insurance_plans.find(
        (p) => p.id.toString() === data.insurance_plan_id,
    );
    const isNhisPlan = selectedPlan?.provider?.is_nhis ?? false;
    const verificationMode = nhis_settings?.verification_mode ?? 'manual';

    // Check if NHIS lookup shows INACTIVE or expired coverage
    const isNhisInactive =
        cccData?.status === 'INACTIVE' || cccData?.error === 'INACTIVE';
    const nhisEndDate = parseNhisDate(cccData?.coverageEnd);
    const isNhisExpired = nhisEndDate ? isDateExpired(nhisEndDate) : false;
    const isNhisUnusable = isNhisInactive || isNhisExpired;

    // Auto-fill coverage dates when NHIS data is received
    useEffect(() => {
        if (cccData) {
            // Check for Ghana Card not linked error first
            if (cccData.errorType === 'GHANACARD_NOT_LINKED') {
                toast.error('Ghana Card not linked to NHIS', {
                    description:
                        'This Ghana Card is not linked to an NHIS membership. The patient needs to link their Ghana Card at an NHIS office, or use their NHIS membership number instead.',
                    duration: 8000,
                });
                return;
            }

            const startDate = parseNhisDate(cccData.coverageStart);
            const endDate = parseNhisDate(cccData.coverageEnd);

            // Auto-fill membership ID from NHIS response (useful when using Ghana Card)
            if (cccData.membershipNumber && !data.membership_id.trim()) {
                setData('membership_id', cccData.membershipNumber);
                toast.info('NHIS membership number auto-filled', {
                    description: `Membership ID: ${cccData.membershipNumber}`,
                });
            }

            // Always update insurance dates if available
            if (startDate) {
                setData('coverage_start_date', startDate);
            }
            if (endDate) {
                setData('coverage_end_date', endDate);
            }

            // Show appropriate toast
            if (isNhisInactive) {
                toast.error('NHIS membership is INACTIVE', {
                    description: `Coverage: ${startDate || '?'} to ${endDate || '?'}. Patient needs to renew.`,
                });
            } else if (isNhisExpired) {
                toast.warning('NHIS coverage has expired', {
                    description: `Coverage ended on ${endDate}. Patient needs to renew.`,
                });
            } else if (startDate || endDate) {
                toast.success('NHIS coverage dates verified', {
                    description: `Coverage: ${startDate} to ${endDate}`,
                });
            }
        }
    }, [cccData]);

    // Clear NHIS data when plan changes or insurance is toggled off
    useEffect(() => {
        if (!hasInsurance || !isNhisPlan) {
            clearCccData();
        }
    }, [hasInsurance, data.insurance_plan_id]);

    const handleLookupNhis = () => {
        // Can use either membership ID or national ID (Ghana Card)
        const hasMembershipId = data.membership_id.trim();
        const hasNationalId = data.national_id?.trim();
        const lookupId = hasMembershipId || hasNationalId;
        
        if (!lookupId) {
            toast.error('Enter membership ID or national ID (Ghana Card) first');
            return;
        }

        // Determine which ID type to use
        const idType = hasMembershipId ? 'nhis' : 'ghanacard';

        // Copy the ID to clipboard
        copyToClipboard(lookupId);

        // Show hint about which ID type will be used
        if (idType === 'ghanacard') {
            toast.info('Ghana Card number copied - extension will select Ghana Card on portal');
        }

        // Start verification with the appropriate ID type
        startVerification(
            lookupId,
            nhis_settings?.credentials || undefined,
            nhis_settings?.nhia_portal_url,
            idType,
        );
    };
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        // Ensure has_insurance is synced before submission
        const submitData = {
            ...data,
            has_insurance: hasInsurance,
        };

        router.patch(patients.update.url(patient.id), submitData, {
            preserveScroll: true,
            onSuccess: (page) => {
                // Check for flash success message
                const flash = page.props.flash as
                    | { success?: string }
                    | undefined;
                if (flash?.success) {
                    toast.success(flash.success);
                } else {
                    toast.success('Patient information updated successfully');
                }
            },
            onError: (errors) => {
                // Show specific validation errors if available
                const errorMessages = Object.values(errors);
                if (errorMessages.length > 0) {
                    toast.error(errorMessages[0] as string);
                } else {
                    toast.error('Failed to update patient information');
                }
            },
        });
    };

    return (
        <AppLayout>
            <Head title={`Edit ${patient.full_name}`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div className="space-y-1">
                        <div className="flex items-center gap-3">
                            <Button
                                variant="ghost"
                                size="icon"
                                asChild
                                className="shrink-0"
                            >
                                <Link href={patients.show.url(patient.id)}>
                                    <ArrowLeft className="h-4 w-4" />
                                </Link>
                            </Button>
                            <div>
                                <h1 className="text-3xl font-bold tracking-tight">
                                    Edit Patient
                                </h1>
                                <p className="text-muted-foreground">
                                    {patient.full_name} (#
                                    {patient.patient_number})
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Demographics */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Demographics</CardTitle>
                            <CardDescription>
                                Basic patient information
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="first_name">
                                        First Name *
                                    </Label>
                                    <Input
                                        id="first_name"
                                        value={data.first_name}
                                        onChange={(e) =>
                                            setData(
                                                'first_name',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                    {errors.first_name && (
                                        <p className="text-sm text-destructive">
                                            {errors.first_name}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="last_name">
                                        Last Name *
                                    </Label>
                                    <Input
                                        id="last_name"
                                        value={data.last_name}
                                        onChange={(e) =>
                                            setData('last_name', e.target.value)
                                        }
                                        required
                                    />
                                    {errors.last_name && (
                                        <p className="text-sm text-destructive">
                                            {errors.last_name}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="gender">Gender *</Label>
                                    <select
                                        id="gender"
                                        value={data.gender}
                                        onChange={(e) =>
                                            setData(
                                                'gender',
                                                e.target.value as
                                                    | 'male'
                                                    | 'female'
                                                    | '',
                                            )
                                        }
                                        className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background"
                                        required
                                    >
                                        <option value="">Select gender</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                    </select>
                                    {errors.gender && (
                                        <p className="text-sm text-destructive">
                                            {errors.gender}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="date_of_birth">
                                        Date of Birth *
                                    </Label>
                                    <Input
                                        id="date_of_birth"
                                        type="date"
                                        value={data.date_of_birth}
                                        onChange={(e) =>
                                            setData(
                                                'date_of_birth',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                    {errors.date_of_birth && (
                                        <p className="text-sm text-destructive">
                                            {errors.date_of_birth}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="phone_number">
                                        Phone Number
                                    </Label>
                                    <Input
                                        id="phone_number"
                                        value={data.phone_number}
                                        onChange={(e) =>
                                            setData(
                                                'phone_number',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="+255..."
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="national_id">
                                        National ID
                                    </Label>
                                    <Input
                                        id="national_id"
                                        value={data.national_id}
                                        onChange={(e) =>
                                            setData(
                                                'national_id',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="address">Address</Label>
                                <Input
                                    id="address"
                                    value={data.address}
                                    onChange={(e) =>
                                        setData('address', e.target.value)
                                    }
                                />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="emergency_contact_name">
                                        Emergency Contact Name
                                    </Label>
                                    <Input
                                        id="emergency_contact_name"
                                        value={data.emergency_contact_name}
                                        onChange={(e) =>
                                            setData(
                                                'emergency_contact_name',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="emergency_contact_phone">
                                        Emergency Contact Phone
                                    </Label>
                                    <Input
                                        id="emergency_contact_phone"
                                        value={data.emergency_contact_phone}
                                        onChange={(e) =>
                                            setData(
                                                'emergency_contact_phone',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Insurance Information */}
                    {insurance_plans.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Insurance Information</CardTitle>
                                <CardDescription>
                                    Patient insurance coverage details
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="has_insurance"
                                        checked={hasInsurance}
                                        onCheckedChange={(checked) => {
                                            setHasInsurance(checked === true);
                                        }}
                                    />
                                    <Label
                                        htmlFor="has_insurance"
                                        className="flex items-center gap-2 text-base font-medium"
                                    >
                                        <Shield className="h-4 w-4 text-primary" />
                                        Patient has insurance coverage
                                    </Label>
                                </div>

                                {hasInsurance && (
                                    <div className="space-y-4 rounded-lg border bg-muted/50 p-4">
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label htmlFor="insurance_plan_id">
                                                    Insurance Plan *
                                                </Label>
                                                <Select
                                                    value={
                                                        data.insurance_plan_id
                                                    }
                                                    onValueChange={(value) =>
                                                        setData(
                                                            'insurance_plan_id',
                                                            value,
                                                        )
                                                    }
                                                >
                                                    <SelectTrigger
                                                        id="insurance_plan_id"
                                                        className={
                                                            errors.insurance_plan_id
                                                                ? 'border-destructive'
                                                                : ''
                                                        }
                                                    >
                                                        <SelectValue placeholder="Select insurance plan" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {insurance_plans.map(
                                                            (plan) => (
                                                                <SelectItem
                                                                    key={
                                                                        plan.id
                                                                    }
                                                                    value={plan.id.toString()}
                                                                >
                                                                    {
                                                                        plan
                                                                            .provider
                                                                            .name
                                                                    }{' '}
                                                                    -{' '}
                                                                    {
                                                                        plan.plan_name
                                                                    }
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                                {errors.insurance_plan_id && (
                                                    <p className="text-sm text-destructive">
                                                        {
                                                            errors.insurance_plan_id
                                                        }
                                                    </p>
                                                )}
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="membership_id">
                                                    Membership ID *
                                                </Label>
                                                <div className="flex gap-2">
                                                    <Input
                                                        id="membership_id"
                                                        value={data.membership_id}
                                                        onChange={(e) =>
                                                            setData(
                                                                'membership_id',
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="Enter membership ID"
                                                        className={
                                                            errors.membership_id
                                                                ? 'border-destructive'
                                                                : ''
                                                        }
                                                    />
                                                    {isNhisPlan &&
                                                        verificationMode ===
                                                            'extension' && (
                                                            <Button
                                                                type="button"
                                                                variant="default"
                                                                size="icon"
                                                                onClick={
                                                                    handleLookupNhis
                                                                }
                                                                disabled={
                                                                    isVerifying ||
                                                                    (!data.membership_id.trim() &&
                                                                        !data.national_id?.trim())
                                                                }
                                                                title="Verify NHIS using membership ID or Ghana Card"
                                                                className="shrink-0 bg-blue-600 hover:bg-blue-700"
                                                            >
                                                                {isVerifying ? (
                                                                    <Loader2 className="h-4 w-4 animate-spin" />
                                                                ) : (
                                                                    <ExternalLink className="h-4 w-4" />
                                                                )}
                                                            </Button>
                                                        )}
                                                </div>
                                                {errors.membership_id && (
                                                    <p className="text-sm text-destructive">
                                                        {errors.membership_id}
                                                    </p>
                                                )}
                                                {isNhisPlan &&
                                                    verificationMode ===
                                                        'extension' &&
                                                    !cccData && (
                                                        <p className="text-xs text-muted-foreground">
                                                            💡 Click to verify
                                                            using membership ID
                                                            or Ghana Card
                                                            (national ID)
                                                        </p>
                                                    )}
                                            </div>
                                        </div>

                                        {/* NHIS Lookup Result */}
                                        {cccData && isNhisPlan && (
                                            <div
                                                className={`rounded-md p-3 text-sm ${
                                                    isNhisUnusable
                                                        ? isNhisInactive
                                                            ? 'border border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950/20'
                                                            : 'border border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/20'
                                                        : 'border border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950/20'
                                                }`}
                                            >
                                                <div
                                                    className={`flex items-center gap-2 ${
                                                        isNhisUnusable
                                                            ? isNhisInactive
                                                                ? 'text-red-700 dark:text-red-400'
                                                                : 'text-amber-700 dark:text-amber-400'
                                                            : 'text-green-700 dark:text-green-400'
                                                    }`}
                                                >
                                                    {isNhisUnusable ? (
                                                        <AlertTriangle className="h-4 w-4" />
                                                    ) : (
                                                        <CheckCircle2 className="h-4 w-4" />
                                                    )}
                                                    <span className="font-medium">
                                                        NHIS Verified:{' '}
                                                        {cccData.memberName ||
                                                            'Member'}
                                                    </span>
                                                </div>
                                                <p
                                                    className={`mt-1 ${
                                                        isNhisUnusable
                                                            ? isNhisInactive
                                                                ? 'text-red-600 dark:text-red-500'
                                                                : 'text-amber-600 dark:text-amber-500'
                                                            : 'text-green-600 dark:text-green-500'
                                                    }`}
                                                >
                                                    Status: {cccData.status}
                                                    {cccData.coverageStart &&
                                                        cccData.coverageEnd &&
                                                        ` • Coverage: ${cccData.coverageStart} to ${cccData.coverageEnd}`}
                                                    {isNhisInactive &&
                                                        ' (INACTIVE)'}
                                                    {!isNhisInactive &&
                                                        isNhisExpired &&
                                                        ' (EXPIRED)'}
                                                </p>
                                            </div>
                                        )}

                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label htmlFor="policy_number">
                                                    Policy Number
                                                </Label>
                                                <Input
                                                    id="policy_number"
                                                    value={data.policy_number}
                                                    onChange={(e) =>
                                                        setData(
                                                            'policy_number',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="Optional"
                                                />
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="card_number">
                                                    Card Number
                                                </Label>
                                                <Input
                                                    id="card_number"
                                                    value={data.card_number}
                                                    onChange={(e) =>
                                                        setData(
                                                            'card_number',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="Optional"
                                                />
                                            </div>
                                        </div>

                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label htmlFor="coverage_start_date">
                                                    Coverage Start Date{' '}
                                                    {!isNhisPlan && '*'}
                                                    {isNhisPlan && (
                                                        <span className="text-xs font-normal text-muted-foreground">
                                                            (from NHIS)
                                                        </span>
                                                    )}
                                                </Label>
                                                <Input
                                                    id="coverage_start_date"
                                                    type="date"
                                                    value={
                                                        data.coverage_start_date
                                                    }
                                                    onChange={(e) =>
                                                        setData(
                                                            'coverage_start_date',
                                                            e.target.value,
                                                        )
                                                    }
                                                    readOnly={
                                                        isNhisPlan && !!cccData
                                                    }
                                                    className={
                                                        errors.coverage_start_date
                                                            ? 'border-destructive'
                                                            : isNhisPlan &&
                                                                cccData
                                                              ? 'border-green-500 bg-green-50 dark:bg-green-950/20'
                                                              : ''
                                                    }
                                                />
                                                {errors.coverage_start_date && (
                                                    <p className="text-sm text-destructive">
                                                        {
                                                            errors.coverage_start_date
                                                        }
                                                    </p>
                                                )}
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="coverage_end_date">
                                                    Coverage End Date{' '}
                                                    {isNhisPlan && (
                                                        <span className="text-xs font-normal text-muted-foreground">
                                                            (from NHIS)
                                                        </span>
                                                    )}
                                                </Label>
                                                <Input
                                                    id="coverage_end_date"
                                                    type="date"
                                                    value={
                                                        data.coverage_end_date
                                                    }
                                                    onChange={(e) =>
                                                        setData(
                                                            'coverage_end_date',
                                                            e.target.value,
                                                        )
                                                    }
                                                    readOnly={
                                                        isNhisPlan && !!cccData
                                                    }
                                                    placeholder="Optional"
                                                    className={
                                                        isNhisPlan && cccData
                                                            ? isNhisExpired
                                                                ? 'border-amber-500 bg-amber-50 dark:bg-amber-950/20'
                                                                : 'border-green-500 bg-green-50 dark:bg-green-950/20'
                                                            : ''
                                                    }
                                                />
                                            </div>
                                        </div>

                                        <div className="space-y-3 rounded-md border bg-background/50 p-3 dark:bg-background/30">
                                            <div className="flex items-center gap-2">
                                                <Checkbox
                                                    id="is_dependent"
                                                    checked={data.is_dependent}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        setData(
                                                            'is_dependent',
                                                            checked === true,
                                                        )
                                                    }
                                                />
                                                <Label
                                                    htmlFor="is_dependent"
                                                    className="font-medium"
                                                >
                                                    Patient is a dependent
                                                </Label>
                                            </div>

                                            {data.is_dependent && (
                                                <div className="grid gap-4 pl-6 sm:grid-cols-2">
                                                    <div className="space-y-2">
                                                        <Label htmlFor="principal_member_name">
                                                            Principal Member
                                                            Name *
                                                        </Label>
                                                        <Input
                                                            id="principal_member_name"
                                                            value={
                                                                data.principal_member_name
                                                            }
                                                            onChange={(e) =>
                                                                setData(
                                                                    'principal_member_name',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            placeholder="Full name of principal member"
                                                            className={
                                                                errors.principal_member_name
                                                                    ? 'border-destructive'
                                                                    : ''
                                                            }
                                                        />
                                                        {errors.principal_member_name && (
                                                            <p className="text-sm text-destructive">
                                                                {
                                                                    errors.principal_member_name
                                                                }
                                                            </p>
                                                        )}
                                                    </div>

                                                    <div className="space-y-2">
                                                        <Label htmlFor="relationship_to_principal">
                                                            Relationship *
                                                        </Label>
                                                        <Select
                                                            value={
                                                                data.relationship_to_principal
                                                            }
                                                            onValueChange={(
                                                                value,
                                                            ) =>
                                                                setData(
                                                                    'relationship_to_principal',
                                                                    value,
                                                                )
                                                            }
                                                        >
                                                            <SelectTrigger
                                                                id="relationship_to_principal"
                                                                className={
                                                                    errors.relationship_to_principal
                                                                        ? 'border-destructive'
                                                                        : ''
                                                                }
                                                            >
                                                                <SelectValue placeholder="Select relationship" />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem value="spouse">
                                                                    Spouse
                                                                </SelectItem>
                                                                <SelectItem value="child">
                                                                    Child
                                                                </SelectItem>
                                                                <SelectItem value="parent">
                                                                    Parent
                                                                </SelectItem>
                                                                <SelectItem value="other">
                                                                    Other
                                                                </SelectItem>
                                                            </SelectContent>
                                                        </Select>
                                                        {errors.relationship_to_principal && (
                                                            <p className="text-sm text-destructive">
                                                                {
                                                                    errors.relationship_to_principal
                                                                }
                                                            </p>
                                                        )}
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    )}

                    {/* Actions */}
                    <div className="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                router.visit(patients.show.url(patient.id))
                            }
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && (
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            )}
                            Save Changes
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
