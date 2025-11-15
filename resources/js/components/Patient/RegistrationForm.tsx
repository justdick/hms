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
import { useForm, usePage } from '@inertiajs/react';
import { Loader2, Shield } from 'lucide-react';
import React, { useState } from 'react';
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
    };
}

interface PatientRegistrationFormProps {
    onPatientRegistered: (patient: Patient) => void;
    onCancel?: () => void;
    registrationEndpoint?: string;
    showCancelButton?: boolean;
    insurancePlans?: InsurancePlan[];
}

export default function PatientRegistrationForm({
    onPatientRegistered,
    onCancel,
    registrationEndpoint = '/checkin/patients',
    showCancelButton = false,
    insurancePlans = [],
}: PatientRegistrationFormProps) {
    const [hasInsurance, setHasInsurance] = useState(false);
    const page = usePage<{ patient?: Patient }>();

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

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        post(registrationEndpoint, {
            preserveScroll: true,
            preserveState: (page) => Object.keys(page.props.errors).length > 0,
            onSuccess: (page) => {
                toast.success('Patient registered successfully');
                reset();
                setHasInsurance(false);
                // Patient data comes from the page props via flash data
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
            <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                    <Label htmlFor="first_name">First Name *</Label>
                    <Input
                        id="first_name"
                        value={data.first_name}
                        onChange={(e) => setData('first_name', e.target.value)}
                        required
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
                    <Label htmlFor="date_of_birth">Date of Birth *</Label>
                    <Input
                        id="date_of_birth"
                        type="date"
                        value={data.date_of_birth}
                        onChange={(e) =>
                            setData('date_of_birth', e.target.value)
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

            {/* Insurance Information Section */}
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
                                        placeholder="Optional"
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
