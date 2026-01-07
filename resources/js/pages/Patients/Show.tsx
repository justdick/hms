import CheckinModal from '@/components/Checkin/CheckinModal';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { CreditLimitModal } from '@/pages/Billing/PatientAccounts/components/CreditLimitModal';
import { DepositModal } from '@/pages/Billing/PatientAccounts/components/DepositModal';
import patients from '@/routes/patients';
import { Head, Link, router } from '@inertiajs/react';
import {
    Activity,
    AlertCircle,
    ArrowLeft,
    Calendar,
    ClipboardPlus,
    Clock,
    DollarSign,
    Edit,
    FileText,
    Heart,
    IdCard,
    MapPin,
    Phone,
    Plus,
    Shield,
    User,
    Users,
    Wallet,
} from 'lucide-react';
import { useState } from 'react';
import BillingSummary from './components/BillingSummary';
import MedicalHistoryTab from './components/MedicalHistoryTab';

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

interface Payment {
    date: string;
    amount: number;
    method: string;
    description: string;
}

interface BillingSummaryData {
    total_outstanding: number;
    insurance_covered: number;
    patient_owes: number;
    recent_payments: Payment[];
    has_active_overrides: boolean;
}

interface PaymentMethod {
    id: number;
    name: string;
    code: string;
}

interface AccountSummary {
    balance: number;
    credit_limit: number;
}

interface MedicalHistoryData {
    consultations: Array<{
        id: number;
        date: string | null;
        doctor: string | null;
        department: string | null;
        presenting_complaint: string | null;
        history_presenting_complaint: string | null;
        examination_findings: string | null;
        assessment_notes: string | null;
        plan_notes: string | null;
        diagnoses: Array<{
            type: string;
            code: string | null;
            description: string | null;
            notes?: string | null;
        }>;
        prescriptions: Array<{
            drug_name: string | null;
            generic_name: string | null;
            form: string | null;
            strength: string | null;
            dose_quantity: string | null;
            frequency: string | null;
            duration: string | null;
            quantity: number | null;
            instructions: string | null;
            status: string;
        }>;
        lab_orders: Array<{
            service_name: string | null;
            code: string | null;
            is_imaging: boolean;
            status: string;
            result_values: Record<string, unknown> | null;
            result_notes: string | null;
            ordered_at: string | null;
            result_entered_at: string | null;
        }>;
        procedures: Array<{
            name: string | null;
            code: string | null;
            notes: string | null;
        }>;
    }>;
    vitals: Array<{
        id: number;
        recorded_at: string | null;
        recorded_by: string | null;
        blood_pressure: string;
        temperature: number | null;
        pulse_rate: number | null;
        respiratory_rate: number | null;
        oxygen_saturation: number | null;
        weight: number | null;
        height: number | null;
        bmi: number | null;
        notes: string | null;
    }>;
    admissions: Array<{
        id: number;
        admission_number: string;
        admitted_at: string | null;
        discharged_at: string | null;
        status: string;
        ward: string | null;
        bed: string | null;
        admission_reason: string | null;
        discharge_notes: string | null;
        admitting_doctor: string | null;
        diagnoses: Array<{
            type: string;
            code: string | null;
            description: string | null;
            is_active: boolean;
        }>;
    }>;
    prescriptions: Array<{
        id: number;
        date: string | null;
        drug_name: string | null;
        generic_name: string | null;
        form: string | null;
        strength: string | null;
        dose_quantity: string | null;
        frequency: string | null;
        duration: string | null;
        quantity: number | null;
        instructions: string | null;
        status: string;
    }>;
    lab_results: Array<{
        id: number;
        service_name: string | null;
        code: string | null;
        is_imaging: boolean;
        ordered_by: string | null;
        ordered_at: string | null;
        result_entered_at: string | null;
        result_values: Record<string, unknown> | null;
        result_notes: string | null;
    }>;
    theatre_procedures: Array<{
        id: number;
        performed_at: string | null;
        procedure_name: string | null;
        procedure_code: string | null;
        category: string | null;
        doctor: string | null;
        department: string | null;
        indication: string | null;
        assistant: string | null;
        anaesthetist: string | null;
        anaesthesia_type: string | null;
        procedure_subtype: string | null;
        procedure_steps: string | null;
        findings: string | null;
        plan: string | null;
        comments: string | null;
        estimated_gestational_age: string | null;
        parity: string | null;
    }>;
}

interface Props {
    patient: Patient;
    can_edit: boolean;
    can_checkin: boolean;
    can_view_medical_history: boolean;
    departments?: DepartmentOption[];
    billing_summary?: BillingSummaryData | null;
    medical_history?: MedicalHistoryData | null;
    can_process_payment?: boolean;
    can_manage_credit?: boolean;
    payment_methods?: PaymentMethod[];
    account_summary?: AccountSummary | null;
}

export default function PatientsShow({
    patient,
    can_edit,
    can_checkin,
    can_view_medical_history,
    departments = [],
    billing_summary = null,
    medical_history = null,
    can_process_payment = false,
    can_manage_credit = false,
    payment_methods = [],
    account_summary = null,
}: Props) {
    const [checkinModalOpen, setCheckinModalOpen] = useState(false);
    const [depositModalOpen, setDepositModalOpen] = useState(false);
    const [creditLimitModalOpen, setCreditLimitModalOpen] = useState(false);

    const formatCurrency = (amount: number | string) => {
        return new Intl.NumberFormat('en-GH', {
            style: 'currency',
            currency: 'GHS',
        }).format(Number(amount));
    };

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
        const statusMap: Record<string, 'default' | 'secondary' | 'outline'> = {
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

    // Get initials for avatar
    const getInitials = (name: string) => {
        return name
            .split(' ')
            .map((n) => n[0])
            .join('')
            .toUpperCase()
            .slice(0, 2);
    };

    // Get avatar color based on gender
    const getAvatarColor = (gender: string) => {
        return gender === 'male' ? 'bg-blue-500' : 'bg-pink-500';
    };

    return (
        <AppLayout>
            <Head title={`${patient.full_name} - Patient Profile`} />

            <div className="space-y-6">
                {/* Back Button */}
                <Button
                    variant="ghost"
                    size="sm"
                    asChild
                    className="-ml-2 gap-2"
                >
                    <Link href={patients.index.url()}>
                        <ArrowLeft className="h-4 w-4" />
                        Back to Patients
                    </Link>
                </Button>

                {/* Profile Header Card */}
                <Card>
                    <CardContent className="pt-6">
                        <div className="flex flex-col gap-6 md:flex-row md:items-start md:justify-between">
                            <div className="flex gap-4">
                                <Avatar
                                    className={`h-20 w-20 ${getAvatarColor(patient.gender)}`}
                                >
                                    <AvatarFallback className="text-2xl font-semibold text-white">
                                        {getInitials(patient.full_name)}
                                    </AvatarFallback>
                                </Avatar>
                                <div className="space-y-2">
                                    <div>
                                        <h1 className="text-2xl font-bold">
                                            {patient.full_name}
                                        </h1>
                                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                            <IdCard className="h-4 w-4" />
                                            <span>
                                                {patient.patient_number}
                                            </span>
                                        </div>
                                    </div>
                                    <div className="flex flex-wrap gap-3 text-sm">
                                        <div className="flex items-center gap-1.5">
                                            <User className="h-4 w-4 text-muted-foreground" />
                                            <span>
                                                {formatGender(patient.gender)}
                                            </span>
                                        </div>
                                        <Separator
                                            orientation="vertical"
                                            className="h-4"
                                        />
                                        <div className="flex items-center gap-1.5">
                                            <Calendar className="h-4 w-4 text-muted-foreground" />
                                            <span>{patient.age} years old</span>
                                        </div>
                                        {patient.phone_number && (
                                            <>
                                                <Separator
                                                    orientation="vertical"
                                                    className="h-4"
                                                />
                                                <div className="flex items-center gap-1.5">
                                                    <Phone className="h-4 w-4 text-muted-foreground" />
                                                    <span>
                                                        {patient.phone_number}
                                                    </span>
                                                </div>
                                            </>
                                        )}
                                    </div>
                                    {patient.active_insurance && (
                                        <Badge
                                            variant="default"
                                            className="w-fit gap-1.5"
                                        >
                                            <Shield className="h-3 w-3" />
                                            {
                                                patient.active_insurance
                                                    .insurance_plan.name
                                            }
                                        </Badge>
                                    )}
                                </div>
                            </div>
                            <div className="flex gap-2">
                                {can_edit && (
                                    <Button
                                        variant="outline"
                                        asChild
                                        className="gap-2"
                                    >
                                        <Link
                                            href={patients.edit.url(patient.id)}
                                        >
                                            <Edit className="h-4 w-4" />
                                            Edit Profile
                                        </Link>
                                    </Button>
                                )}
                                {can_checkin && (
                                    <Button
                                        className="gap-2"
                                        onClick={() =>
                                            setCheckinModalOpen(true)
                                        }
                                    >
                                        <ClipboardPlus className="h-4 w-4" />
                                        Check-in Patient
                                    </Button>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Tabbed Content */}
                <Tabs defaultValue="overview" className="space-y-6">
                    <TabsList>
                        <TabsTrigger value="overview" className="gap-2">
                            <User className="h-4 w-4" />
                            Overview
                        </TabsTrigger>
                        <TabsTrigger value="insurance" className="gap-2">
                            <Shield className="h-4 w-4" />
                            Insurance
                        </TabsTrigger>
                        {billing_summary && (
                            <TabsTrigger value="billing" className="gap-2">
                                <DollarSign className="h-4 w-4" />
                                Billing
                            </TabsTrigger>
                        )}
                        <TabsTrigger value="history" className="gap-2">
                            <Clock className="h-4 w-4" />
                            Visit History
                        </TabsTrigger>
                        {can_view_medical_history && (
                            <TabsTrigger value="medical" className="gap-2">
                                <FileText className="h-4 w-4" />
                                Medical History
                            </TabsTrigger>
                        )}
                    </TabsList>

                    {/* Overview Tab */}
                    <TabsContent value="overview" className="space-y-6">
                        <div className="grid gap-6 lg:grid-cols-2">
                            {/* Personal Information */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-lg">
                                        <User className="h-5 w-5" />
                                        Personal Information
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid gap-6">
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div className="space-y-1">
                                                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                                    Date of Birth
                                                </p>
                                                <p className="text-sm font-medium">
                                                    {formatDate(
                                                        patient.date_of_birth,
                                                    )}
                                                </p>
                                            </div>
                                            <div className="space-y-1">
                                                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                                    Gender
                                                </p>
                                                <p className="text-sm font-medium">
                                                    {formatGender(
                                                        patient.gender,
                                                    )}
                                                </p>
                                            </div>
                                        </div>

                                        {patient.national_id && (
                                            <div className="space-y-1">
                                                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                                    National ID
                                                </p>
                                                <p className="text-sm font-medium">
                                                    {patient.national_id}
                                                </p>
                                            </div>
                                        )}
                                    </div>

                                    <Separator />

                                    <div className="space-y-4">
                                        <div className="space-y-1">
                                            <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                                Contact Information
                                            </p>
                                        </div>
                                        {patient.phone_number && (
                                            <div className="flex items-center gap-3 rounded-lg bg-muted/50 p-3">
                                                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-background">
                                                    <Phone className="h-4 w-4 text-muted-foreground" />
                                                </div>
                                                <div className="flex-1">
                                                    <p className="text-xs text-muted-foreground">
                                                        Phone Number
                                                    </p>
                                                    <p className="text-sm font-medium">
                                                        {patient.phone_number}
                                                    </p>
                                                </div>
                                            </div>
                                        )}
                                        {patient.address && (
                                            <div className="flex items-start gap-3 rounded-lg bg-muted/50 p-3">
                                                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-background">
                                                    <MapPin className="h-4 w-4 text-muted-foreground" />
                                                </div>
                                                <div className="flex-1">
                                                    <p className="text-xs text-muted-foreground">
                                                        Address
                                                    </p>
                                                    <p className="text-sm font-medium">
                                                        {patient.address}
                                                    </p>
                                                </div>
                                            </div>
                                        )}
                                    </div>

                                    {(patient.emergency_contact_name ||
                                        patient.emergency_contact_phone) && (
                                        <>
                                            <Separator />
                                            <div className="space-y-4">
                                                <div className="flex items-center gap-2">
                                                    <AlertCircle className="h-4 w-4 text-destructive" />
                                                    <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                                        Emergency Contact
                                                    </p>
                                                </div>
                                                {patient.emergency_contact_name && (
                                                    <div className="flex items-center gap-3 rounded-lg bg-destructive/5 p-3">
                                                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-background">
                                                            <Users className="h-4 w-4 text-destructive" />
                                                        </div>
                                                        <div className="flex-1">
                                                            <p className="text-xs text-muted-foreground">
                                                                Name
                                                            </p>
                                                            <p className="text-sm font-medium">
                                                                {
                                                                    patient.emergency_contact_name
                                                                }
                                                            </p>
                                                        </div>
                                                    </div>
                                                )}
                                                {patient.emergency_contact_phone && (
                                                    <div className="flex items-center gap-3 rounded-lg bg-destructive/5 p-3">
                                                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-background">
                                                            <Phone className="h-4 w-4 text-destructive" />
                                                        </div>
                                                        <div className="flex-1">
                                                            <p className="text-xs text-muted-foreground">
                                                                Phone
                                                            </p>
                                                            <p className="text-sm font-medium">
                                                                {
                                                                    patient.emergency_contact_phone
                                                                }
                                                            </p>
                                                        </div>
                                                    </div>
                                                )}
                                            </div>
                                        </>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Quick Stats */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-lg">
                                        <Activity className="h-5 w-5" />
                                        Quick Stats
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="flex items-center gap-3 rounded-lg bg-primary/5 p-4">
                                            <div className="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10">
                                                <Calendar className="h-5 w-5 text-primary" />
                                            </div>
                                            <div>
                                                <p className="text-2xl font-bold">
                                                    {
                                                        patient.checkin_history
                                                            .length
                                                    }
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    Total Visits
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-3 rounded-lg bg-green-500/5 p-4">
                                            <div className="flex h-12 w-12 items-center justify-center rounded-full bg-green-500/10">
                                                <Shield className="h-5 w-5 text-green-600" />
                                            </div>
                                            <div>
                                                <p className="text-2xl font-bold">
                                                    {patient.active_insurance
                                                        ? 'Yes'
                                                        : 'No'}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    Insurance Coverage
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    {patient.active_insurance && (
                                        <div className="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-900 dark:bg-green-950/20">
                                            <div className="flex items-start gap-3">
                                                <Shield className="h-5 w-5 text-green-600 dark:text-green-500" />
                                                <div className="flex-1 space-y-1">
                                                    <p className="text-sm font-medium text-green-900 dark:text-green-100">
                                                        Active Insurance
                                                    </p>
                                                    <p className="text-sm text-green-700 dark:text-green-300">
                                                        {
                                                            patient
                                                                .active_insurance
                                                                .insurance_plan
                                                                .name
                                                        }
                                                    </p>
                                                    <p className="text-xs text-green-600 dark:text-green-400">
                                                        ID:{' '}
                                                        {
                                                            patient
                                                                .active_insurance
                                                                .membership_id
                                                        }
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </div>
                    </TabsContent>

                    {/* Billing Tab */}
                    {billing_summary && (
                        <TabsContent value="billing" className="space-y-6">
                            {/* Account Actions */}
                            {(can_process_payment || can_manage_credit) && (
                                <Card>
                                    <CardHeader>
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <CardTitle className="flex items-center gap-2">
                                                    <Wallet className="h-5 w-5" />
                                                    Patient Account
                                                </CardTitle>
                                                <CardDescription>
                                                    Manage deposits and credit
                                                    limits
                                                </CardDescription>
                                            </div>
                                            <div className="flex gap-2">
                                                {can_manage_credit && (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            setCreditLimitModalOpen(
                                                                true,
                                                            )
                                                        }
                                                    >
                                                        <Shield className="mr-2 h-4 w-4" />
                                                        Set Credit Limit
                                                    </Button>
                                                )}
                                                {can_process_payment && (
                                                    <Button
                                                        size="sm"
                                                        onClick={() =>
                                                            setDepositModalOpen(
                                                                true,
                                                            )
                                                        }
                                                    >
                                                        <Plus className="mr-2 h-4 w-4" />
                                                        Add Deposit
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                    </CardHeader>
                                    {account_summary &&
                                        (account_summary.balance !== 0 ||
                                            account_summary.credit_limit >
                                                0) && (
                                            <CardContent>
                                                <div className="grid gap-4 sm:grid-cols-2">
                                                    <div className="rounded-lg border p-4">
                                                        <p className="text-sm text-muted-foreground">
                                                            Account Balance
                                                        </p>
                                                        <p
                                                            className={`text-2xl font-bold ${account_summary.balance >= 0 ? 'text-green-600' : 'text-red-600'}`}
                                                        >
                                                            {formatCurrency(
                                                                account_summary.balance,
                                                            )}
                                                        </p>
                                                    </div>
                                                    <div className="rounded-lg border p-4">
                                                        <p className="text-sm text-muted-foreground">
                                                            Credit Limit
                                                        </p>
                                                        <p className="text-2xl font-bold text-blue-600">
                                                            {account_summary.credit_limit >=
                                                            999999999
                                                                ? 'Unlimited'
                                                                : formatCurrency(
                                                                      account_summary.credit_limit,
                                                                  )}
                                                        </p>
                                                    </div>
                                                </div>
                                            </CardContent>
                                        )}
                                </Card>
                            )}

                            <BillingSummary
                                billingSummary={billing_summary}
                                canProcessPayment={can_process_payment}
                            />
                        </TabsContent>
                    )}

                    {/* Insurance Tab */}
                    <TabsContent value="insurance" className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Shield className="h-5 w-5" />
                                    Insurance Coverage
                                </CardTitle>
                                <CardDescription>
                                    Active and historical insurance information
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {patient.active_insurance ? (
                                    <div className="space-y-6">
                                        <div className="rounded-lg border-2 border-green-200 bg-green-50 p-6 dark:border-green-900 dark:bg-green-950/20">
                                            <div className="mb-4 flex items-center justify-between">
                                                <Badge
                                                    variant="default"
                                                    className="gap-1.5 bg-green-600"
                                                >
                                                    <Shield className="h-3 w-3" />
                                                    Active Coverage
                                                </Badge>
                                                <p className="text-sm font-medium text-green-700 dark:text-green-300">
                                                    {
                                                        patient.active_insurance
                                                            .insurance_plan.name
                                                    }
                                                </p>
                                            </div>

                                            <div className="grid gap-6 sm:grid-cols-2">
                                                <div className="space-y-1">
                                                    <p className="text-xs font-medium text-muted-foreground">
                                                        Membership ID
                                                    </p>
                                                    <p className="text-sm font-semibold">
                                                        {
                                                            patient
                                                                .active_insurance
                                                                .membership_id
                                                        }
                                                    </p>
                                                </div>
                                                {patient.active_insurance
                                                    .policy_number && (
                                                    <div className="space-y-1">
                                                        <p className="text-xs font-medium text-muted-foreground">
                                                            Policy Number
                                                        </p>
                                                        <p className="text-sm font-semibold">
                                                            {
                                                                patient
                                                                    .active_insurance
                                                                    .policy_number
                                                            }
                                                        </p>
                                                    </div>
                                                )}
                                                {patient.active_insurance
                                                    .card_number && (
                                                    <div className="space-y-1">
                                                        <p className="text-xs font-medium text-muted-foreground">
                                                            Card Number
                                                        </p>
                                                        <p className="text-sm font-semibold">
                                                            {
                                                                patient
                                                                    .active_insurance
                                                                    .card_number
                                                            }
                                                        </p>
                                                    </div>
                                                )}
                                                <div className="space-y-1">
                                                    <p className="text-xs font-medium text-muted-foreground">
                                                        Coverage Period
                                                    </p>
                                                    <p className="text-sm font-semibold">
                                                        {formatDate(
                                                            patient
                                                                .active_insurance
                                                                .coverage_start_date,
                                                        )}{' '}
                                                        -{' '}
                                                        {patient
                                                            .active_insurance
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
                                        </div>

                                        {patient.active_insurance
                                            .is_dependent && (
                                            <div className="rounded-lg border bg-background p-4">
                                                <div className="mb-3 flex items-center gap-2">
                                                    <Users className="h-4 w-4 text-muted-foreground" />
                                                    <h4 className="text-sm font-semibold">
                                                        Dependent Information
                                                    </h4>
                                                </div>
                                                <div className="grid gap-3 sm:grid-cols-2">
                                                    {patient.active_insurance
                                                        .principal_member_name && (
                                                        <div className="space-y-1">
                                                            <p className="text-xs text-muted-foreground">
                                                                Principal Member
                                                            </p>
                                                            <p className="text-sm font-medium">
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
                                                        <div className="space-y-1">
                                                            <p className="text-xs text-muted-foreground">
                                                                Relationship
                                                            </p>
                                                            <p className="text-sm font-medium">
                                                                {
                                                                    patient
                                                                        .active_insurance
                                                                        .relationship_to_principal
                                                                }
                                                            </p>
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                ) : (
                                    <div className="flex flex-col items-center justify-center py-12">
                                        <div className="flex h-20 w-20 items-center justify-center rounded-full bg-muted">
                                            <Shield className="h-10 w-10 text-muted-foreground/50" />
                                        </div>
                                        <p className="mt-4 text-sm font-medium">
                                            No Active Insurance
                                        </p>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            This patient doesn't have active
                                            insurance coverage
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Visit History Tab */}
                    <TabsContent value="history" className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Clock className="h-5 w-5" />
                                    Visit History
                                </CardTitle>
                                <CardDescription>
                                    Timeline of patient check-ins and
                                    consultations
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {patient.checkin_history.length > 0 ? (
                                    <div className="relative space-y-4">
                                        {/* Timeline line */}
                                        <div className="absolute top-2 left-[19px] h-[calc(100%-2rem)] w-0.5 bg-border" />

                                        {patient.checkin_history.map(
                                            (checkin, index) => (
                                                <div
                                                    key={checkin.id}
                                                    className="relative flex gap-4"
                                                >
                                                    {/* Timeline dot */}
                                                    <div className="relative z-10 flex h-10 w-10 shrink-0 items-center justify-center rounded-full border-2 border-background bg-primary">
                                                        <Calendar className="h-4 w-4 text-primary-foreground" />
                                                    </div>

                                                    {/* Content */}
                                                    <div className="flex-1 rounded-lg border bg-card p-4">
                                                        <div className="flex items-start justify-between gap-4">
                                                            <div className="space-y-1">
                                                                <p className="font-semibold">
                                                                    {
                                                                        checkin
                                                                            .department
                                                                            .name
                                                                    }
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
                                                                {formatStatus(
                                                                    checkin.status,
                                                                )}
                                                            </Badge>
                                                        </div>
                                                    </div>
                                                </div>
                                            ),
                                        )}
                                    </div>
                                ) : (
                                    <div className="flex flex-col items-center justify-center py-12">
                                        <div className="flex h-20 w-20 items-center justify-center rounded-full bg-muted">
                                            <Calendar className="h-10 w-10 text-muted-foreground/50" />
                                        </div>
                                        <p className="mt-4 text-sm font-medium">
                                            No Visit History
                                        </p>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            This patient hasn't checked in yet
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Medical History Tab */}
                    {can_view_medical_history && (
                        <TabsContent value="medical" className="space-y-6">
                            <MedicalHistoryTab
                                backgroundHistory={{
                                    past_medical_surgical_history:
                                        patient.past_medical_surgical_history,
                                    drug_history: patient.drug_history,
                                    family_history: patient.family_history,
                                    social_history: patient.social_history,
                                }}
                                medicalHistory={medical_history}
                            />
                        </TabsContent>
                    )}
                </Tabs>
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

            {/* Deposit Modal */}
            {payment_methods.length > 0 && (
                <DepositModal
                    isOpen={depositModalOpen}
                    onClose={() => setDepositModalOpen(false)}
                    paymentMethods={payment_methods}
                    formatCurrency={formatCurrency}
                    preselectedPatient={{
                        id: patient.id,
                        full_name: patient.full_name,
                        patient_number: patient.patient_number,
                        phone_number: patient.phone_number || '',
                        account_balance: account_summary?.balance || 0,
                        credit_limit: account_summary?.credit_limit || 0,
                        available_balance:
                            (account_summary?.balance || 0) +
                            (account_summary?.credit_limit || 0),
                    }}
                />
            )}

            {/* Credit Limit Modal */}
            <CreditLimitModal
                isOpen={creditLimitModalOpen}
                onClose={() => setCreditLimitModalOpen(false)}
                patient={{
                    id: patient.id,
                    first_name: patient.first_name,
                    last_name: patient.last_name,
                    patient_number: patient.patient_number,
                }}
                currentLimit={account_summary?.credit_limit || 0}
                formatCurrency={formatCurrency}
            />
        </AppLayout>
    );
}
