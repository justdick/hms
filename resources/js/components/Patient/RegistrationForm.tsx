import { Button } from '@/components/ui/button';
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
import { useNhisExtension } from '@/hooks/useNhisExtension';
import { copyToClipboard } from '@/lib/utils';
import { useForm, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    ExternalLink,
    Loader2,
    Shield,
    Sparkles,
} from 'lucide-react';
import React, { useEffect, useState } from 'react';
import { toast } from 'sonner';

interface Patient {
    id: number;
    patient_number: string;
    full_name: string;
    age: number;
    gender: string;
    phone_number: string | null;
    has_checkin_today: boolean;
}

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

interface PatientRegistrationFormProps {
    onPatientRegistered: (patient: Patient) => void;
    onCancel?: () => void;
    registrationEndpoint?: string;
    showCancelButton?: boolean;
    insurancePlans?: InsurancePlan[];
    nhisSettings?: NhisSettings;
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

/**
 * Parse NHIS name into first and last name
 * NHIS returns names like "DICKSON KWAME OWUSU" or "OWUSU, DICKSON KWAME"
 * Middle names go with first name: "DICKSON KWAME" + "OWUSU"
 */
function parseNhisName(fullName: string | null | undefined): {
    firstName: string;
    lastName: string;
} {
    if (!fullName) return { firstName: '', lastName: '' };

    const name = fullName.trim();

    // Check for "LASTNAME, FIRSTNAME MIDDLENAME" format
    if (name.includes(',')) {
        const parts = name.split(',').map((p) => p.trim());
        return {
            firstName: parts[1] || '', // Everything after comma is first + middle names
            lastName: parts[0] || '', // Before comma is last name
        };
    }

    // Assume "FIRSTNAME MIDDLENAME LASTNAME" format
    const parts = name.split(/\s+/);
    if (parts.length === 1) {
        return { firstName: parts[0], lastName: '' };
    }

    if (parts.length === 2) {
        // Just first and last name
        return {
            firstName: parts[0],
            lastName: parts[1],
        };
    }

    // 3+ parts: last word is surname, everything else is first name (including middle names)
    return {
        firstName: parts.slice(0, -1).join(' '), // All but last = first + middle names
        lastName: parts[parts.length - 1], // Last word = surname
    };
}

/**
 * Parse NHIS gender to our format
 */
function parseNhisGender(gender: string | null | undefined): string {
    if (!gender) return '';
    const g = gender.toLowerCase().trim();
    if (g === 'm' || g === 'male') return 'male';
    if (g === 'f' || g === 'female') return 'female';
    return '';
}

export default function PatientRegistrationForm({
    onPatientRegistered,
    onCancel,
    registrationEndpoint = '/checkin/patients',
    showCancelButton = false,
    insurancePlans = [],
    nhisSettings,
}: PatientRegistrationFormProps) {
    const [hasInsurance, setHasInsurance] = useState(false);
    const page = usePage<{ patient?: Patient }>();

    // NHIS Extension hook
    const { isVerifying, cccData, startVerification, clearCccData } =
        useNhisExtension();

    const { data, setData, post, processing, errors, reset } = useForm({
        first_name: '',
        last_name: '',
        gender: '',
        date_of_birth: '',
        phone_number: '',
        address: '',
        emergency_contact_name: '',
        emergency_contact_phone: '',
        national_id: '',
        // Insurance fields
        has_insurance: false,
        insurance_plan_id: '',
        membership_id: '',
        policy_number: '',
        card_number: '',
        is_dependent: false,
        principal_member_name: '',
        relationship_to_principal: '',
        coverage_start_date: '',
        coverage_end_date: '',
    });

    // Get selected plan details
    const selectedPlan = insurancePlans.find(
        (p) => p.id.toString() === data.insurance_plan_id,
    );
    const isNhisPlan = selectedPlan?.provider?.is_nhis ?? false;
    const verificationMode = nhisSettings?.verification_mode ?? 'manual';

    // Check if NHIS lookup shows INACTIVE or expired coverage
    const isNhisInactive =
        cccData?.status === 'INACTIVE' || cccData?.error === 'INACTIVE';
    const nhisEndDate = parseNhisDate(cccData?.coverageEnd);
    const isNhisExpired = nhisEndDate ? isDateExpired(nhisEndDate) : false;
    const isNhisUnusable = isNhisInactive || isNhisExpired;

    // Auto-fill ALL fields when NHIS data is received
    useEffect(() => {
        if (cccData) {
            const startDate = parseNhisDate(cccData.coverageStart);
            const endDate = parseNhisDate(cccData.coverageEnd);
            const dob = parseNhisDate(cccData.dob);
            const { firstName, lastName } = parseNhisName(cccData.memberName);
            const gender = parseNhisGender(cccData.gender);

            // Track what was auto-filled
            const autoFilled: string[] = [];

            // Auto-fill patient details (only if empty)
            if (firstName && !data.first_name) {
                setData('first_name', firstName);
                autoFilled.push('first name');
            }
            if (lastName && !data.last_name) {
                setData('last_name', lastName);
                autoFilled.push('last name');
            }
            if (gender && !data.gender) {
                setData('gender', gender);
                autoFilled.push('gender');
            }
            if (dob && !data.date_of_birth) {
                setData('date_of_birth', dob);
                autoFilled.push('date of birth');
            }

            // Always update insurance dates
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
            } else if (autoFilled.length > 0) {
                toast.success('Patient details auto-filled from NHIS', {
                    description: `Filled: ${autoFilled.join(', ')}`,
                    icon: <Sparkles className="h-4 w-4" />,
                });
            } else if (startDate || endDate) {
                toast.success('NHIS dates auto-filled', {
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
        if (!data.membership_id.trim()) {
            toast.error('Enter membership ID first');
            return;
        }

        // Copy membership number to clipboard
        copyToClipboard(data.membership_id);

        // Start verification
        startVerification(
            data.membership_id,
            nhisSettings?.credentials || undefined,
            nhisSettings?.nhia_portal_url,
        );
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        post(registrationEndpoint, {
            preserveScroll: true,
            preserveState: (page) => Object.keys(page.props.errors).length > 0,
            onSuccess: (page) => {
                toast.success('Patient registered successfully');
                reset();
                setHasInsurance(false);
                clearCccData();
                if (page.props.patient) {
                    onPatientRegistered(page.props.patient as Patient);
                }
            },
            onError: () => {
                toast.error('Failed to register patient');
            },
        });
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            {/* Insurance Section - NOW AT THE TOP */}
            {insurancePlans.length > 0 && (
                <div className="space-y-4 rounded-lg border border-primary/20 bg-primary/5 p-4 dark:bg-primary/10">
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="has_insurance"
                            checked={hasInsurance}
                            onCheckedChange={(checked) => {
                                setHasInsurance(checked === true);
                                setData('has_insurance', checked === true);
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
                        <div className="space-y-4 pl-6">
                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="insurance_plan_id">
                                        Insurance Plan *
                                    </Label>
                                    <Select
                                        value={data.insurance_plan_id}
                                        onValueChange={(value) =>
                                            setData('insurance_plan_id', value)
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
                                            {insurancePlans.map((plan) => (
                                                <SelectItem
                                                    key={plan.id}
                                                    value={plan.id.toString()}
                                                >
                                                    {plan.provider.name} -{' '}
                                                    {plan.plan_name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.insurance_plan_id && (
                                        <p className="text-sm text-destructive">
                                            {errors.insurance_plan_id}
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
                                                    onClick={handleLookupNhis}
                                                    disabled={
                                                        isVerifying ||
                                                        !data.membership_id.trim()
                                                    }
                                                    title="Lookup from NHIS - auto-fills patient details"
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
                                        verificationMode === 'extension' &&
                                        !cccData && (
                                            <p className="text-xs text-muted-foreground">
                                                💡 Click lookup to auto-fill
                                                patient name, DOB, and coverage
                                                dates
                                            </p>
                                        )}
                                </div>
                            </div>

                            {/* NHIS Lookup Result */}
                            {cccData && isNhisPlan && (
                                <div
                                    className={`rounded-md p-2 text-xs ${
                                        isNhisUnusable
                                            ? isNhisInactive
                                                ? 'border border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950/20'
                                                : 'border border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/20'
                                            : 'bg-green-50 dark:bg-green-950/20'
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
                                            <AlertTriangle className="h-3 w-3" />
                                        ) : (
                                            <CheckCircle2 className="h-3 w-3" />
                                        )}
                                        <span className="font-medium">
                                            NHIS:{' '}
                                            {cccData.memberName || 'Member'}
                                        </span>
                                    </div>
                                    <p
                                        className={`${
                                            isNhisUnusable
                                                ? isNhisInactive
                                                    ? 'text-red-600 dark:text-red-500'
                                                    : 'text-amber-600 dark:text-amber-500'
                                                : 'text-green-600 dark:text-green-500'
                                        }`}
                                    >
                                        {cccData.status}
                                        {cccData.dob &&
                                            ` • DOB: ${cccData.dob}`}
                                        {cccData.gender &&
                                            ` • ${cccData.gender}`}
                                        {cccData.coverageStart &&
                                            cccData.coverageEnd &&
                                            ` • ${cccData.coverageStart} to ${cccData.coverageEnd}`}
                                        {isNhisInactive && ' (INACTIVE)'}
                                        {!isNhisInactive &&
                                            isNhisExpired &&
                                            ' (EXPIRED)'}
                                    </p>
                                </div>
                            )}

                            <div className="grid grid-cols-2 gap-4">
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

                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="coverage_start_date">
                                        Coverage Start Date *
                                    </Label>
                                    <Input
                                        id="coverage_start_date"
                                        type="date"
                                        value={data.coverage_start_date}
                                        onChange={(e) =>
                                            setData(
                                                'coverage_start_date',
                                                e.target.value,
                                            )
                                        }
                                        className={
                                            errors.coverage_start_date
                                                ? 'border-destructive'
                                                : cccData &&
                                                    data.coverage_start_date
                                                  ? 'border-green-500 bg-green-50 dark:bg-green-950/20'
                                                  : ''
                                        }
                                    />
                                    {errors.coverage_start_date && (
                                        <p className="text-sm text-destructive">
                                            {errors.coverage_start_date}
                                        </p>
                                    )}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="coverage_end_date">
                                        Coverage End Date
                                    </Label>
                                    <Input
                                        id="coverage_end_date"
                                        type="date"
                                        value={data.coverage_end_date}
                                        onChange={(e) =>
                                            setData(
                                                'coverage_end_date',
                                                e.target.value,
                                            )
                                        }
                                        className={
                                            cccData && data.coverage_end_date
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
                                        onCheckedChange={(checked) =>
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
                                    <div className="grid grid-cols-2 gap-4 pl-6">
                                        <div className="space-y-2">
                                            <Label htmlFor="principal_member_name">
                                                Principal Member Name *
                                            </Label>
                                            <Input
                                                id="principal_member_name"
                                                value={
                                                    data.principal_member_name
                                                }
                                                onChange={(e) =>
                                                    setData(
                                                        'principal_member_name',
                                                        e.target.value,
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
                                                onValueChange={(value) =>
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
                </div>
            )}

            {/* Patient Details Section */}
            <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                    <Label htmlFor="first_name">First Name *</Label>
                    <Input
                        id="first_name"
                        value={data.first_name}
                        onChange={(e) => setData('first_name', e.target.value)}
                        required
                        className={
                            cccData && data.first_name
                                ? 'border-green-500 bg-green-50 dark:bg-green-950/20'
                                : ''
                        }
                    />
                    {errors.first_name && (
                        <p className="text-sm text-destructive">
                            {errors.first_name}
                        </p>
                    )}
                </div>
                <div className="space-y-2">
                    <Label htmlFor="last_name">Last Name *</Label>
                    <Input
                        id="last_name"
                        value={data.last_name}
                        onChange={(e) => setData('last_name', e.target.value)}
                        required
                        className={
                            cccData && data.last_name
                                ? 'border-green-500 bg-green-50 dark:bg-green-950/20'
                                : ''
                        }
                    />
                    {errors.last_name && (
                        <p className="text-sm text-destructive">
                            {errors.last_name}
                        </p>
                    )}
                </div>
            </div>

            <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                    <Label htmlFor="gender">Gender *</Label>
                    <select
                        id="gender"
                        value={data.gender}
                        onChange={(e) => setData('gender', e.target.value)}
                        className={`flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background ${
                            cccData && data.gender
                                ? 'border-green-500 bg-green-50 dark:bg-green-950/20'
                                : ''
                        }`}
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
                    <Label htmlFor="date_of_birth">Date of Birth *</Label>
                    <Input
                        id="date_of_birth"
                        type="date"
                        value={data.date_of_birth}
                        onChange={(e) =>
                            setData('date_of_birth', e.target.value)
                        }
                        required
                        className={
                            cccData && data.date_of_birth
                                ? 'border-green-500 bg-green-50 dark:bg-green-950/20'
                                : ''
                        }
                    />
                    {errors.date_of_birth && (
                        <p className="text-sm text-destructive">
                            {errors.date_of_birth}
                        </p>
                    )}
                </div>
            </div>

            <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                    <Label htmlFor="phone_number">Phone Number</Label>
                    <Input
                        id="phone_number"
                        value={data.phone_number}
                        onChange={(e) =>
                            setData('phone_number', e.target.value)
                        }
                        placeholder="+255..."
                    />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="national_id">National ID</Label>
                    <Input
                        id="national_id"
                        value={data.national_id}
                        onChange={(e) => setData('national_id', e.target.value)}
                    />
                </div>
            </div>

            <div className="space-y-2">
                <Label htmlFor="address">Address</Label>
                <Input
                    id="address"
                    value={data.address}
                    onChange={(e) => setData('address', e.target.value)}
                />
            </div>

            <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                    <Label htmlFor="emergency_contact_name">
                        Emergency Contact Name
                    </Label>
                    <Input
                        id="emergency_contact_name"
                        value={data.emergency_contact_name}
                        onChange={(e) =>
                            setData('emergency_contact_name', e.target.value)
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
                            setData('emergency_contact_phone', e.target.value)
                        }
                    />
                </div>
            </div>

            <div className="flex justify-end gap-2">
                {showCancelButton && onCancel && (
                    <Button type="button" variant="outline" onClick={onCancel}>
                        Cancel
                    </Button>
                )}
                <Button type="submit" disabled={processing}>
                    {processing && (
                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                    )}
                    Register
                </Button>
            </div>
        </form>
    );
}
