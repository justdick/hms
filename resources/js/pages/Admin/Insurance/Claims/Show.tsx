import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    Calendar,
    CheckCircle,
    FileCheck,
    FileText,
    ShieldCheck,
    User,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';

interface InsuranceProvider {
    id: number;
    name: string;
    code: string;
}

interface InsurancePlan {
    id: number;
    plan_name: string;
    provider: InsuranceProvider;
}

interface PatientInsurance {
    id: number;
    membership_id: string;
    plan: InsurancePlan;
}

interface InsuranceClaimItem {
    id: number;
    item_date: string;
    item_type: 'prescription' | 'investigation' | 'procedure' | 'consultation';
    code?: string;
    description: string;
    quantity: number;
    unit_tariff: string;
    subtotal: string;
    is_covered: boolean;
    coverage_percentage: string;
    insurance_pays: string;
    patient_pays: string;
    is_approved: boolean | null;
    rejection_reason?: string;
}

interface InsuranceClaim {
    id: number;
    claim_check_code: string;
    folder_id?: string;
    patient_full_name: string;
    patient_dob: string;
    patient_gender: string;
    membership_id: string;
    date_of_attendance: string;
    date_of_discharge?: string;
    type_of_service: 'inpatient' | 'outpatient';
    type_of_attendance: 'emergency' | 'acute' | 'routine';
    specialty_attended?: string;
    attending_prescriber?: string;
    primary_diagnosis_code?: string;
    primary_diagnosis_description?: string;
    secondary_diagnoses?: Array<{ code: string; description: string }>;
    total_claim_amount: string;
    approved_amount: string;
    patient_copay_amount: string;
    insurance_covered_amount: string;
    status:
        | 'draft'
        | 'pending_vetting'
        | 'vetted'
        | 'submitted'
        | 'approved'
        | 'rejected'
        | 'paid';
    vetted_by_user?: {
        id: number;
        name: string;
    };
    vetted_at?: string;
    submitted_by_user?: {
        id: number;
        name: string;
    };
    submitted_at?: string;
    rejection_reason?: string;
    notes?: string;
    patient_insurance?: PatientInsurance;
    items: InsuranceClaimItem[];
}

interface Props {
    claim: InsuranceClaim;
    can: {
        vet: boolean;
        submit: boolean;
        approve: boolean;
        reject: boolean;
    };
}

export default function ClaimShow({ claim, can }: Props) {
    const [itemApprovals, setItemApprovals] = useState<
        Record<number, { approved: boolean; reason?: string }>
    >(
        claim.items.reduce(
            (acc, item) => ({
                ...acc,
                [item.id]: {
                    approved: item.is_approved ?? true,
                    reason: item.rejection_reason,
                },
            }),
            {},
        ),
    );

    const { data, setData, post, processing, errors } = useForm<{
        action: 'approve' | 'reject';
        rejection_reason?: string;
        items: Array<{
            id: number;
            is_approved: boolean;
            rejection_reason?: string;
        }>;
    }>({
        action: 'approve',
        rejection_reason: '',
        items: [],
    });

    const formatCurrency = (amount: string | number) => {
        return new Intl.NumberFormat('en-GH', {
            style: 'currency',
            currency: 'GHS',
        }).format(typeof amount === 'string' ? parseFloat(amount) : amount);
    };

    const getStatusColor = (status: InsuranceClaim['status']) => {
        const colors = {
            draft: 'bg-gray-500',
            pending_vetting: 'bg-yellow-500',
            vetted: 'bg-blue-500',
            submitted: 'bg-purple-500',
            approved: 'bg-green-500',
            rejected: 'bg-red-500',
            paid: 'bg-emerald-600',
        };
        return colors[status] || 'bg-gray-500';
    };

    const handleItemApprovalChange = (
        itemId: number,
        approved: boolean,
        reason?: string,
    ) => {
        setItemApprovals((prev) => ({
            ...prev,
            [itemId]: { approved, reason },
        }));
    };

    const handleVetClaim = (action: 'approve' | 'reject') => {
        const items = Object.entries(itemApprovals).map(
            ([id, { approved, reason }]) => ({
                id: parseInt(id),
                is_approved: approved,
                rejection_reason: reason,
            }),
        );

        setData({
            action,
            rejection_reason: data.rejection_reason,
            items,
        });

        post(`/admin/insurance/claims/${claim.id}/vet`);
    };

    const calculateApprovedTotal = () => {
        return claim.items
            .filter((item) => itemApprovals[item.id]?.approved)
            .reduce((sum, item) => sum + parseFloat(item.insurance_pays), 0);
    };

    const groupedItems = claim.items.reduce(
        (acc, item) => {
            if (!acc[item.item_type]) {
                acc[item.item_type] = [];
            }
            acc[item.item_type].push(item);
            return acc;
        },
        {} as Record<string, InsuranceClaimItem[]>,
    );

    return (
        <AppLayout>
            <Head title={`Claim ${claim.claim_check_code}`} />

            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Link href="/admin/insurance/claims">
                            <Button variant="ghost" size="sm">
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Back to Claims
                            </Button>
                        </Link>
                        <div>
                            <h1 className="text-3xl font-bold">
                                Claim {claim.claim_check_code}
                            </h1>
                            <p className="text-muted-foreground">
                                Review and vet insurance claim details
                            </p>
                        </div>
                    </div>
                    <Badge className={getStatusColor(claim.status)}>
                        {claim.status.replace('_', ' ').toUpperCase()}
                    </Badge>
                </div>

                {/* Rejection Alert */}
                {claim.status === 'rejected' && claim.rejection_reason && (
                    <Alert variant="destructive">
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>
                            <strong>Rejection Reason:</strong>{' '}
                            {claim.rejection_reason}
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Left Column - Patient & Visit Details */}
                    <div className="space-y-6 lg:col-span-2">
                        {/* Patient Information */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <User className="h-5 w-5" />
                                    Patient Information
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4 md:grid-cols-2">
                                <div>
                                    <Label className="text-muted-foreground">
                                        Full Name
                                    </Label>
                                    <p className="font-medium">
                                        {claim.patient_full_name}
                                    </p>
                                </div>
                                <div>
                                    <Label className="text-muted-foreground">
                                        Date of Birth
                                    </Label>
                                    <p className="font-medium">
                                        {claim.patient_dob}
                                    </p>
                                </div>
                                <div>
                                    <Label className="text-muted-foreground">
                                        Gender
                                    </Label>
                                    <p className="font-medium">
                                        {claim.patient_gender}
                                    </p>
                                </div>
                                <div>
                                    <Label className="text-muted-foreground">
                                        Membership ID
                                    </Label>
                                    <p className="font-medium">
                                        {claim.membership_id}
                                    </p>
                                </div>
                                {claim.folder_id && (
                                    <div>
                                        <Label className="text-muted-foreground">
                                            Folder ID
                                        </Label>
                                        <p className="font-medium">
                                            {claim.folder_id}
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Visit Information */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Calendar className="h-5 w-5" />
                                    Visit Information
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4 md:grid-cols-2">
                                <div>
                                    <Label className="text-muted-foreground">
                                        Date of Attendance
                                    </Label>
                                    <p className="font-medium">
                                        {claim.date_of_attendance}
                                    </p>
                                </div>
                                {claim.date_of_discharge && (
                                    <div>
                                        <Label className="text-muted-foreground">
                                            Date of Discharge
                                        </Label>
                                        <p className="font-medium">
                                            {claim.date_of_discharge}
                                        </p>
                                    </div>
                                )}
                                <div>
                                    <Label className="text-muted-foreground">
                                        Type of Service
                                    </Label>
                                    <p className="font-medium capitalize">
                                        {claim.type_of_service}
                                    </p>
                                </div>
                                <div>
                                    <Label className="text-muted-foreground">
                                        Type of Attendance
                                    </Label>
                                    <p className="font-medium capitalize">
                                        {claim.type_of_attendance}
                                    </p>
                                </div>
                                {claim.specialty_attended && (
                                    <div>
                                        <Label className="text-muted-foreground">
                                            Specialty Attended
                                        </Label>
                                        <p className="font-medium">
                                            {claim.specialty_attended}
                                        </p>
                                    </div>
                                )}
                                {claim.attending_prescriber && (
                                    <div>
                                        <Label className="text-muted-foreground">
                                            Attending Prescriber
                                        </Label>
                                        <p className="font-medium">
                                            {claim.attending_prescriber}
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Diagnosis Information */}
                        {claim.primary_diagnosis_code && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <FileText className="h-5 w-5" />
                                        Diagnosis
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <div>
                                        <Label className="text-muted-foreground">
                                            Primary Diagnosis
                                        </Label>
                                        <p className="font-medium">
                                            {claim.primary_diagnosis_code} -{' '}
                                            {
                                                claim.primary_diagnosis_description
                                            }
                                        </p>
                                    </div>
                                    {claim.secondary_diagnoses &&
                                        claim.secondary_diagnoses.length >
                                            0 && (
                                            <div>
                                                <Label className="text-muted-foreground">
                                                    Secondary Diagnoses
                                                </Label>
                                                <ul className="mt-1 space-y-1">
                                                    {claim.secondary_diagnoses.map(
                                                        (diag, idx) => (
                                                            <li
                                                                key={idx}
                                                                className="text-sm"
                                                            >
                                                                {diag.code} -{' '}
                                                                {
                                                                    diag.description
                                                                }
                                                            </li>
                                                        ),
                                                    )}
                                                </ul>
                                            </div>
                                        )}
                                </CardContent>
                            </Card>
                        )}

                        {/* Claim Items - Tabbed by Type */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <FileCheck className="h-5 w-5" />
                                    Claim Line Items
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Tabs
                                    defaultValue={
                                        Object.keys(groupedItems)[0] || 'all'
                                    }
                                >
                                    <TabsList>
                                        <TabsTrigger value="all">
                                            All Items ({claim.items.length})
                                        </TabsTrigger>
                                        {Object.entries(groupedItems).map(
                                            ([type, items]) => (
                                                <TabsTrigger
                                                    key={type}
                                                    value={type}
                                                >
                                                    {type
                                                        .charAt(0)
                                                        .toUpperCase() +
                                                        type.slice(1)}
                                                    s ({items.length})
                                                </TabsTrigger>
                                            ),
                                        )}
                                    </TabsList>

                                    <TabsContent value="all" className="mt-4">
                                        <ClaimItemsTable
                                            items={claim.items}
                                            itemApprovals={itemApprovals}
                                            onApprovalChange={
                                                handleItemApprovalChange
                                            }
                                            canVet={can.vet}
                                            formatCurrency={formatCurrency}
                                        />
                                    </TabsContent>

                                    {Object.entries(groupedItems).map(
                                        ([type, items]) => (
                                            <TabsContent
                                                key={type}
                                                value={type}
                                                className="mt-4"
                                            >
                                                <ClaimItemsTable
                                                    items={items}
                                                    itemApprovals={
                                                        itemApprovals
                                                    }
                                                    onApprovalChange={
                                                        handleItemApprovalChange
                                                    }
                                                    canVet={can.vet}
                                                    formatCurrency={
                                                        formatCurrency
                                                    }
                                                />
                                            </TabsContent>
                                        ),
                                    )}
                                </Tabs>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Right Column - Insurance & Actions */}
                    <div className="space-y-6">
                        {/* Insurance Details */}
                        {claim.patient_insurance && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <ShieldCheck className="h-5 w-5" />
                                        Insurance
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <div>
                                        <Label className="text-muted-foreground">
                                            Provider
                                        </Label>
                                        <p className="font-medium">
                                            {claim.patient_insurance?.plan
                                                ?.provider?.name || 'N/A'}
                                        </p>
                                    </div>
                                    <div>
                                        <Label className="text-muted-foreground">
                                            Plan
                                        </Label>
                                        <p className="font-medium">
                                            {
                                                claim.patient_insurance.plan
                                                    .plan_name
                                            }
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Financial Summary */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Financial Summary</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Total Claim Amount
                                    </span>
                                    <span className="font-medium">
                                        {formatCurrency(
                                            claim.total_claim_amount,
                                        )}
                                    </span>
                                </div>
                                <Separator />
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Approved Amount
                                    </span>
                                    <span className="font-medium text-green-600">
                                        {formatCurrency(
                                            calculateApprovedTotal(),
                                        )}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Patient Copay
                                    </span>
                                    <span className="font-medium text-orange-600">
                                        {formatCurrency(
                                            parseFloat(
                                                claim.total_claim_amount,
                                            ) - calculateApprovedTotal(),
                                        )}
                                    </span>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Workflow Information */}
                        {(claim.vetted_by_user || claim.submitted_by_user) && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Workflow History</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    {claim.vetted_by_user && (
                                        <div>
                                            <Label className="text-muted-foreground">
                                                Vetted By
                                            </Label>
                                            <p className="font-medium">
                                                {claim.vetted_by_user.name}
                                            </p>
                                            {claim.vetted_at && (
                                                <p className="text-sm text-muted-foreground">
                                                    {new Date(
                                                        claim.vetted_at,
                                                    ).toLocaleString()}
                                                </p>
                                            )}
                                        </div>
                                    )}
                                    {claim.submitted_by_user && (
                                        <div>
                                            <Label className="text-muted-foreground">
                                                Submitted By
                                            </Label>
                                            <p className="font-medium">
                                                {claim.submitted_by_user.name}
                                            </p>
                                            {claim.submitted_at && (
                                                <p className="text-sm text-muted-foreground">
                                                    {new Date(
                                                        claim.submitted_at,
                                                    ).toLocaleString()}
                                                </p>
                                            )}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        )}

                        {/* Vetting Actions */}
                        {can.vet && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Vet Claim</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="rejection_reason">
                                            Rejection Reason (if rejecting)
                                        </Label>
                                        <Textarea
                                            id="rejection_reason"
                                            placeholder="Enter reason for rejecting this claim..."
                                            value={data.rejection_reason}
                                            onChange={(e) =>
                                                setData(
                                                    'rejection_reason',
                                                    e.target.value,
                                                )
                                            }
                                            rows={4}
                                        />
                                        {errors.rejection_reason && (
                                            <p className="text-sm text-red-600">
                                                {errors.rejection_reason}
                                            </p>
                                        )}
                                    </div>

                                    <div className="flex gap-2">
                                        <Button
                                            className="flex-1 bg-green-600 hover:bg-green-700"
                                            onClick={() =>
                                                handleVetClaim('approve')
                                            }
                                            disabled={processing}
                                        >
                                            <CheckCircle className="mr-2 h-4 w-4" />
                                            Approve Claim
                                        </Button>
                                        <Button
                                            variant="destructive"
                                            className="flex-1"
                                            onClick={() =>
                                                handleVetClaim('reject')
                                            }
                                            disabled={processing}
                                        >
                                            <XCircle className="mr-2 h-4 w-4" />
                                            Reject Claim
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Notes */}
                        {claim.notes && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Notes</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm">{claim.notes}</p>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

interface ClaimItemsTableProps {
    items: InsuranceClaimItem[];
    itemApprovals: Record<number, { approved: boolean; reason?: string }>;
    onApprovalChange: (
        itemId: number,
        approved: boolean,
        reason?: string,
    ) => void;
    canVet: boolean;
    formatCurrency: (amount: string | number) => string;
}

function ClaimItemsTable({
    items,
    itemApprovals,
    onApprovalChange,
    canVet,
    formatCurrency,
}: ClaimItemsTableProps) {
    return (
        <div className="space-y-4">
            {items.map((item) => (
                <Card
                    key={item.id}
                    className={
                        itemApprovals[item.id]?.approved
                            ? 'border-green-200 dark:border-green-900'
                            : 'border-red-200 dark:border-red-900'
                    }
                >
                    <CardContent className="pt-6">
                        <div className="space-y-3">
                            <div className="flex items-start justify-between">
                                <div className="flex-1">
                                    <div className="flex items-center gap-2">
                                        <h4 className="font-medium">
                                            {item.description}
                                        </h4>
                                        {item.code && (
                                            <Badge variant="outline">
                                                {item.code}
                                            </Badge>
                                        )}
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        {item.item_date} • Qty: {item.quantity}
                                    </p>
                                </div>
                                {canVet && (
                                    <div className="flex items-center gap-2">
                                        <Checkbox
                                            checked={
                                                itemApprovals[item.id]
                                                    ?.approved ?? true
                                            }
                                            onCheckedChange={(checked) =>
                                                onApprovalChange(
                                                    item.id,
                                                    checked as boolean,
                                                )
                                            }
                                        />
                                        <Label className="text-sm">
                                            Approve
                                        </Label>
                                    </div>
                                )}
                            </div>

                            <div className="grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <span className="text-muted-foreground">
                                        Unit Tariff:
                                    </span>{' '}
                                    {formatCurrency(item.unit_tariff)}
                                </div>
                                <div>
                                    <span className="text-muted-foreground">
                                        Subtotal:
                                    </span>{' '}
                                    {formatCurrency(item.subtotal)}
                                </div>
                                <div>
                                    <span className="text-muted-foreground">
                                        Insurance Pays:
                                    </span>{' '}
                                    <span className="font-medium text-green-600">
                                        {formatCurrency(item.insurance_pays)}
                                    </span>
                                </div>
                                <div>
                                    <span className="text-muted-foreground">
                                        Patient Pays:
                                    </span>{' '}
                                    <span className="font-medium text-orange-600">
                                        {formatCurrency(item.patient_pays)}
                                    </span>
                                </div>
                                <div className="col-span-2">
                                    <span className="text-muted-foreground">
                                        Coverage:
                                    </span>{' '}
                                    {item.is_covered ? (
                                        <span className="text-green-600">
                                            {item.coverage_percentage}% covered
                                        </span>
                                    ) : (
                                        <span className="text-red-600">
                                            Not covered
                                        </span>
                                    )}
                                </div>
                            </div>

                            {!itemApprovals[item.id]?.approved && canVet && (
                                <div className="mt-3">
                                    <Label
                                        htmlFor={`rejection-${item.id}`}
                                        className="text-sm"
                                    >
                                        Rejection Reason
                                    </Label>
                                    <Input
                                        id={`rejection-${item.id}`}
                                        placeholder="Why is this item rejected?"
                                        value={
                                            itemApprovals[item.id]?.reason || ''
                                        }
                                        onChange={(e) =>
                                            onApprovalChange(
                                                item.id,
                                                false,
                                                e.target.value,
                                            )
                                        }
                                        className="mt-1"
                                    />
                                </div>
                            )}

                            {item.rejection_reason && !canVet && (
                                <Alert variant="destructive">
                                    <AlertCircle className="h-4 w-4" />
                                    <AlertDescription>
                                        {item.rejection_reason}
                                    </AlertDescription>
                                </Alert>
                            )}
                        </div>
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}
