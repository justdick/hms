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
import AppLayout from '@/layouts/app-layout';
import patients from '@/routes/patients';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Loader2, Shield } from 'lucide-react';
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
    };
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
}

export default function PatientsEdit({ patient, insurance_plans }: Props) {
    const [hasInsurance, setHasInsurance] = useState(
        !!patient.active_insurance,
    );

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
                const flash = page.props.flash as { success?: string } | undefined;
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
                                                {errors.membership_id && (
                                                    <p className="text-sm text-destructive">
                                                        {errors.membership_id}
                                                    </p>
                                                )}
                                            </div>
                                        </div>

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
                                                    Coverage Start Date *
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
                                                    className={
                                                        errors.coverage_start_date
                                                            ? 'border-destructive'
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
                                                    Coverage End Date
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
                                                    placeholder="Optional"
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
