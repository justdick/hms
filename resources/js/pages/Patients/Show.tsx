import CheckinModal from '@/components/Checkin/CheckinModal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import patients from '@/routes/patients';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Calendar,
    ClipboardPlus,
    Edit,
    MapPin,
    Phone,
    Shield,
    User,
} from 'lucide-react';
import { useState } from 'react';

interface InsurancePlan {
    id: number;
    name: string;
}

interface PatientInsurance {
    id: number;
    insurance_plan: InsurancePlan;
    membership_id: string;
    policy_number: string | null;
    card_number: string | null;
    is_dependent: boolean;
    principal_member_name: string | null;
    relationship_to_principal: string | null;
    coverage_start_date: string;
    coverage_end_date: string | null;
    status: string;
}

interface Department {
    id: number;
    name: string;
}

interface CheckinHistory {
    id: number;
    checked_in_at: string;
    department: Department;
    status: string;
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
    insurance_plans: PatientInsurance[];
    checkin_history: CheckinHistory[];
}

interface DepartmentOption {
    id: number;
    name: string;
    code: string;
    description: string;
}

interface Props {
    patient: Patient;
    can_edit: boolean;
    can_checkin: boolean;
    departments?: DepartmentOption[];
}

export default function PatientsShow({
    patient,
    can_edit,
    can_checkin,
    departments = [],
}: Props) {
    const [checkinModalOpen, setCheckinModalOpen] = useState(false);

    const formatGender = (gender: string) => {
        return gender.charAt(0).toUpperCase() + gender.slice(1);
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    };

    const formatDateTime = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const getStatusBadgeVariant = (status: string) => {
        const statusMap: Record<string, 'default' | 'secondary' | 'outline'> =
            {
                checked_in: 'default',
                vitals_taken: 'default',
                awaiting_consultation: 'default',
                in_consultation: 'default',
                completed: 'secondary',
                cancelled: 'outline',
            };
        return statusMap[status] || 'outline';
    };

    const formatStatus = (status: string) => {
        return status
            .split('_')
            .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
            .join(' ');
    };

    const handleCheckinSuccess = () => {
        setCheckinModalOpen(false);
        router.reload({ only: ['patient'] });
    };

    // Convert patient to format expected by CheckinModal
    const checkinPatient = {
        id: patient.id,
        patient_number: patient.patient_number,
        full_name: patient.full_name,
        age: patient.age,
        gender: patient.gender,
        phone_number: patient.phone_number,
    };

    return (
        <AppLayout>
            <Head title={`${patient.full_name} - Patient Profile`} />

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
                                <Link href={patients.index.url()}>
                                    <ArrowLeft className="h-4 w-4" />
                                </Link>
                            </Button>
                            <div>
                                <h1 className="text-3xl font-bold tracking-tight">
                                    {patient.full_name}
                                </h1>
                                <p className="text-muted-foreground">
                                    Patient #{patient.patient_number}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        {can_edit && (
                            <Button variant="outline" asChild className="gap-2">
                                <Link href={patients.edit.url(patient.id)}>
                                    <Edit className="h-4 w-4" />
                                    Edit
                                </Link>
                            </Button>
                        )}
                        {can_checkin && (
                            <Button
                                className="gap-2"
                                onClick={() => setCheckinModalOpen(true)}
                            >
                                <ClipboardPlus className="h-4 w-4" />
                                Check-in
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Demographics */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <User className="h-5 w-5" />
                                Demographics
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        Full Name
                                    </p>
                                    <p className="font-medium">
                                        {patient.full_name}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        Gender
                                    </p>
                                    <p className="font-medium">
                                        {formatGender(patient.gender)}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        Date of Birth
                                    </p>
                                    <p className="font-medium">
                                        {formatDate(patient.date_of_birth)}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        Age
                                    </p>
                                    <p className="font-medium">
                                        {patient.age} years
                                    </p>
                                </div>
                            </div>

                            <div className="space-y-3 border-t pt-4">
                                {patient.phone_number && (
                                    <div className="flex items-start gap-2">
                                        <Phone className="mt-0.5 h-4 w-4 text-muted-foreground" />
                                        <div className="flex-1">
                                            <p className="text-sm text-muted-foreground">
                                                Phone Number
                                            </p>
                                            <p className="font-medium">
                                                {patient.phone_number}
                                            </p>
                                        </div>
                                    </div>
                                )}
                                {patient.address && (
                                    <div className="flex items-start gap-2">
                                        <MapPin className="mt-0.5 h-4 w-4 text-muted-foreground" />
                                        <div className="flex-1">
                                            <p className="text-sm text-muted-foreground">
                                                Address
                                            </p>
                                            <p className="font-medium">
                                                {patient.address}
                                            </p>
                                        </div>
                                    </div>
                                )}
                                {patient.national_id && (
                                    <div>
                                        <p className="text-sm text-muted-foreground">
                                            National ID
                                        </p>
                                        <p className="font-medium">
                                            {patient.national_id}
                                        </p>
                                    </div>
                                )}
                            </div>

                            {(patient.emergency_contact_name ||
                                patient.emergency_contact_phone) && (
                                <div className="space-y-3 border-t pt-4">
                                    <h4 className="text-sm font-semibold">
                                        Emergency Contact
                                    </h4>
                                    {patient.emergency_contact_name && (
                                        <div>
                                            <p className="text-sm text-muted-foreground">
                                                Name
                                            </p>
                                            <p className="font-medium">
                                                {patient.emergency_contact_name}
                                            </p>
                                        </div>
                                    )}
                                    {patient.emergency_contact_phone && (
                                        <div>
                                            <p className="text-sm text-muted-foreground">
                                                Phone
                                            </p>
                                            <p className="font-medium">
                                                {patient.emergency_contact_phone}
                                            </p>
                                        </div>
                                    )}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Insurance Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Shield className="h-5 w-5" />
                                Insurance Information
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {patient.active_insurance ? (
                                <div className="space-y-4">
                                    <div className="flex items-center gap-2">
                                        <Badge
                                            variant="default"
                                            className="gap-1"
                                        >
                                            <Shield className="h-3 w-3" />
                                            Active Coverage
                                        </Badge>
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <p className="text-sm text-muted-foreground">
                                                Provider
                                            </p>
                                            <p className="font-medium">
                                                {
                                                    patient.active_insurance
                                                        .insurance_plan.name
                                                }
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-sm text-muted-foreground">
                                                Membership ID
                                            </p>
                                            <p className="font-medium">
                                                {
                                                    patient.active_insurance
                                                        .membership_id
                                                }
                                            </p>
                                        </div>
                                        {patient.active_insurance
                                            .policy_number && (
                                            <div>
                                                <p className="text-sm text-muted-foreground">
                                                    Policy Number
                                                </p>
                                                <p className="font-medium">
                                                    {
                                                        patient.active_insurance
                                                            .policy_number
                                                    }
                                                </p>
                                            </div>
                                        )}
                                        {patient.active_insurance
                                            .card_number && (
                                            <div>
                                                <p className="text-sm text-muted-foreground">
                                                    Card Number
                                                </p>
                                                <p className="font-medium">
                                                    {
                                                        patient.active_insurance
                                                            .card_number
                                                    }
                                                </p>
                                            </div>
                                        )}
                                        <div>
                                            <p className="text-sm text-muted-foreground">
                                                Coverage Start
                                            </p>
                                            <p className="font-medium">
                                                {formatDate(
                                                    patient.active_insurance
                                                        .coverage_start_date,
                                                )}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-sm text-muted-foreground">
                                                Coverage End
                                            </p>
                                            <p className="font-medium">
                                                {patient.active_insurance
                                                    .coverage_end_date
                                                    ? formatDate(
                                                          patient
                                                              .active_insurance
                                                              .coverage_end_date,
                                                      )
                                                    : 'No expiry'}
                                            </p>
                                        </div>
                                    </div>

                                    {patient.active_insurance.is_dependent && (
                                        <div className="space-y-3 border-t pt-4">
                                            <h4 className="text-sm font-semibold">
                                                Dependent Information
                                            </h4>
                                            {patient.active_insurance
                                                .principal_member_name && (
                                                <div>
                                                    <p className="text-sm text-muted-foreground">
                                                        Principal Member
                                                    </p>
                                                    <p className="font-medium">
                                                        {
                                                            patient
                                                                .active_insurance
                                                                .principal_member_name
                                                        }
                                                    </p>
                                                </div>
                                            )}
                                            {patient.active_insurance
                                                .relationship_to_principal && (
                                                <div>
                                                    <p className="text-sm text-muted-foreground">
                                                        Relationship
                                                    </p>
                                                    <p className="font-medium">
                                                        {
                                                            patient
                                                                .active_insurance
                                                                .relationship_to_principal
                                                        }
                                                    </p>
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <div className="py-8 text-center">
                                    <Shield className="mx-auto h-12 w-12 text-muted-foreground/50" />
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        No active insurance coverage
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Check-in History */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Calendar className="h-5 w-5" />
                            Check-in History
                        </CardTitle>
                        <CardDescription>
                            Recent check-ins and consultations
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {patient.checkin_history.length > 0 ? (
                            <div className="space-y-3">
                                {patient.checkin_history.map((checkin) => (
                                    <div
                                        key={checkin.id}
                                        className="flex items-center justify-between rounded-lg border p-4"
                                    >
                                        <div className="space-y-1">
                                            <p className="font-medium">
                                                {checkin.department.name}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {formatDateTime(
                                                    checkin.checked_in_at,
                                                )}
                                            </p>
                                        </div>
                                        <Badge
                                            variant={getStatusBadgeVariant(
                                                checkin.status,
                                            )}
                                        >
                                            {formatStatus(checkin.status)}
                                        </Badge>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="py-8 text-center">
                                <Calendar className="mx-auto h-12 w-12 text-muted-foreground/50" />
                                <p className="mt-2 text-sm text-muted-foreground">
                                    No check-in history
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Medical History (if available) */}
                {(patient.past_medical_surgical_history ||
                    patient.drug_history ||
                    patient.family_history ||
                    patient.social_history) && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Medical History</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {patient.past_medical_surgical_history && (
                                <div>
                                    <h4 className="mb-2 text-sm font-semibold">
                                        Past Medical/Surgical History
                                    </h4>
                                    <p className="text-sm text-muted-foreground">
                                        {patient.past_medical_surgical_history}
                                    </p>
                                </div>
                            )}
                            {patient.drug_history && (
                                <div>
                                    <h4 className="mb-2 text-sm font-semibold">
                                        Drug History
                                    </h4>
                                    <p className="text-sm text-muted-foreground">
                                        {patient.drug_history}
                                    </p>
                                </div>
                            )}
                            {patient.family_history && (
                                <div>
                                    <h4 className="mb-2 text-sm font-semibold">
                                        Family History
                                    </h4>
                                    <p className="text-sm text-muted-foreground">
                                        {patient.family_history}
                                    </p>
                                </div>
                            )}
                            {patient.social_history && (
                                <div>
                                    <h4 className="mb-2 text-sm font-semibold">
                                        Social History
                                    </h4>
                                    <p className="text-sm text-muted-foreground">
                                        {patient.social_history}
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>

            {/* Check-in Modal */}
            {departments.length > 0 && (
                <CheckinModal
                    open={checkinModalOpen}
                    onClose={() => setCheckinModalOpen(false)}
                    patient={checkinPatient}
                    departments={departments}
                    onSuccess={handleCheckinSuccess}
                />
            )}
        </AppLayout>
    );
}
