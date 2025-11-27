import { InsuranceCoverageBadge } from '@/components/Insurance/InsuranceCoverageBadge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    AlertTriangle,
    CheckCircle,
    ChevronDown,
    ChevronUp,
    Clock,
    ShieldCheck,
    User,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';

interface Patient {
    id: number;
    first_name: string;
    last_name: string;
    patient_number: string;
    phone_number: string;
}

interface Department {
    id: number;
    name: string;
}

interface ChargeItem {
    id: number;
    description: string;
    amount: number;
    is_insurance_claim: boolean;
    insurance_covered_amount: number;
    patient_copay_amount: number;
    service_type: string;
    charged_at: string;
}

interface Visit {
    checkin_id: number;
    department: Department;
    checked_in_at: string;
    total_pending: number;
    patient_copay: number;
    insurance_covered: number;
    charges_count: number;
    charges: ChargeItem[];
}

interface ServiceAccessStatus {
    service_type: string;
    is_blocked: boolean;
    pending_amount: number;
    has_active_override: boolean;
}

interface OverrideHistoryItem {
    id: number;
    type: 'override' | 'waiver' | 'adjustment';
    service_type?: string;
    charge_description?: string;
    reason: string;
    authorized_by: {
        id: number;
        name: string;
    };
    authorized_at: string;
    expires_at?: string;
    is_active?: boolean;
    remaining_duration?: string;
    original_amount?: number;
    adjustment_amount?: number;
    final_amount?: number;
    adjustment_type?: string;
}

interface PatientSearchResult {
    patient_id: number;
    patient: Patient;
    total_pending: number;
    total_patient_owes: number;
    total_insurance_covered: number;
    total_charges: number;
    visits_with_charges: number;
    visits: Visit[];
    service_access_status?: ServiceAccessStatus[];
    override_history?: OverrideHistoryItem[];
}

interface BillingPermissions {
    canProcessPayment: boolean;
    canWaiveCharges: boolean;
    canAdjustCharges: boolean;
    canOverrideServices: boolean;
    canCancelCharges: boolean;
}

interface PatientBillingDetailsProps {
    patient: PatientSearchResult;
    isExpanded: boolean;
    onToggle: () => void;
    permissions: BillingPermissions;
    formatCurrency: (amount: number) => string;
    onWaiveCharge?: (chargeId: number) => void;
    onAdjustCharge?: (chargeId: number) => void;
    onOverrideService?: (serviceType: string) => void;
}

export function PatientBillingDetails({
    patient,
    isExpanded,
    onToggle,
    permissions,
    formatCurrency,
    onWaiveCharge,
    onAdjustCharge,
    onOverrideService,
}: PatientBillingDetailsProps) {
    const [expandedVisits, setExpandedVisits] = useState<Set<number>>(
        new Set(),
    );

    const toggleVisit = (checkinId: number) => {
        setExpandedVisits((prev) => {
            const newSet = new Set(prev);
            if (newSet.has(checkinId)) {
                newSet.delete(checkinId);
            } else {
                newSet.add(checkinId);
            }
            return newSet;
        });
    };

    const getServiceStatusIcon = (status: ServiceAccessStatus) => {
        if (status.has_active_override) {
            return <Clock className="h-4 w-4 text-yellow-600" />;
        }
        if (status.is_blocked) {
            return <XCircle className="h-4 w-4 text-red-600" />;
        }
        return <CheckCircle className="h-4 w-4 text-green-600" />;
    };

    const getServiceStatusText = (status: ServiceAccessStatus) => {
        if (status.has_active_override) {
            return 'Override Active';
        }
        if (status.is_blocked) {
            return 'Blocked';
        }
        return 'Allowed';
    };

    const getServiceStatusColor = (status: ServiceAccessStatus) => {
        if (status.has_active_override) {
            return 'text-yellow-600 bg-yellow-50 border-yellow-200';
        }
        if (status.is_blocked) {
            return 'text-red-600 bg-red-50 border-red-200';
        }
        return 'text-green-600 bg-green-50 border-green-200';
    };

    const formatServiceType = (serviceType: string) => {
        return serviceType
            .split('_')
            .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
            .join(' ');
    };

    const formatDateTime = (dateString: string) => {
        const date = new Date(dateString);
        return date.toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
        });
    };

    const getOverrideTypeColor = (type: string) => {
        switch (type) {
            case 'override':
                return 'bg-yellow-100 text-yellow-800 border-yellow-300';
            case 'waiver':
                return 'bg-gray-100 text-gray-800 border-gray-300';
            case 'adjustment':
                return 'bg-blue-100 text-blue-800 border-blue-300';
            default:
                return 'bg-gray-100 text-gray-800 border-gray-300';
        }
    };

    return (
        <Card className="overflow-hidden">
            <CardHeader
                className="cursor-pointer hover:bg-muted/50"
                onClick={onToggle}
            >
                <CardTitle className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <User className="h-5 w-5" />
                        <span>Patient Billing Details</span>
                    </div>
                    {isExpanded ? (
                        <ChevronUp className="h-5 w-5" />
                    ) : (
                        <ChevronDown className="h-5 w-5" />
                    )}
                </CardTitle>
            </CardHeader>

            {isExpanded && (
                <CardContent className="space-y-6">
                    {/* Patient Information Card */}
                    <div className="rounded-lg bg-muted/30 p-4">
                        <h3 className="text-lg font-semibold">
                            {patient.patient.first_name}{' '}
                            {patient.patient.last_name}
                        </h3>
                        <p className="text-sm text-muted-foreground">
                            {patient.patient.patient_number}
                        </p>
                        {patient.patient.phone_number && (
                            <p className="text-sm text-muted-foreground">
                                {patient.patient.phone_number}
                            </p>
                        )}
                        <p className="mt-1 text-xs text-muted-foreground">
                            {patient.visits_with_charges} visit
                            {patient.visits_with_charges !== 1 ? 's' : ''} with
                            outstanding charges
                        </p>
                    </div>

                    {/* Service Access Status */}
                    {patient.service_access_status &&
                        patient.service_access_status.length > 0 && (
                            <div className="space-y-2">
                                <h4 className="flex items-center gap-2 text-sm font-medium">
                                    <ShieldCheck className="h-4 w-4" />
                                    Service Access Status
                                </h4>
                                <div className="space-y-2">
                                    {patient.service_access_status.map(
                                        (status) => (
                                            <div
                                                key={status.service_type}
                                                className={`flex items-center justify-between rounded-lg border p-3 ${getServiceStatusColor(status)}`}
                                            >
                                                <div className="flex items-center gap-2">
                                                    {getServiceStatusIcon(
                                                        status,
                                                    )}
                                                    <div>
                                                        <p className="text-sm font-medium">
                                                            {formatServiceType(
                                                                status.service_type,
                                                            )}
                                                        </p>
                                                        <p className="text-xs opacity-80">
                                                            {getServiceStatusText(
                                                                status,
                                                            )}
                                                            {status.is_blocked &&
                                                                ` - ${formatCurrency(status.pending_amount)} pending`}
                                                        </p>
                                                    </div>
                                                </div>
                                                {status.is_blocked &&
                                                    permissions.canOverrideServices &&
                                                    onOverrideService && (
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                onOverrideService(
                                                                    status.service_type,
                                                                )
                                                            }
                                                        >
                                                            Override
                                                        </Button>
                                                    )}
                                            </div>
                                        ),
                                    )}
                                </div>
                            </div>
                        )}

                    {/* Payment Summary */}
                    <div className="space-y-3">
                        <div className="rounded-lg border border-orange-200 bg-orange-50 p-4 dark:border-orange-800 dark:bg-orange-950/20">
                            <div className="flex items-center justify-between">
                                <span className="font-medium">
                                    Patient Owes (Copay):
                                </span>
                                <span className="text-xl font-bold text-orange-600">
                                    {formatCurrency(patient.total_patient_owes)}
                                </span>
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Amount to collect from patient
                            </p>
                        </div>

                        {patient.total_insurance_covered > 0 && (
                            <div className="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-950/20">
                                <div className="flex items-center justify-between">
                                    <span className="flex items-center gap-2 font-medium">
                                        <ShieldCheck className="h-4 w-4 text-green-600" />
                                        Insurance Covers:
                                    </span>
                                    <span className="text-xl font-bold text-green-600">
                                        {formatCurrency(
                                            patient.total_insurance_covered,
                                        )}
                                    </span>
                                </div>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Will be claimed from insurance
                                </p>
                            </div>
                        )}

                        <div className="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/20">
                            <div className="flex items-center justify-between">
                                <span className="font-medium">
                                    Total Charges:
                                </span>
                                <span className="text-xl font-bold text-gray-700 dark:text-gray-300">
                                    {formatCurrency(patient.total_pending)}
                                </span>
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {patient.total_charges} charge
                                {patient.total_charges !== 1
                                    ? 's'
                                    : ''} across {patient.visits_with_charges}{' '}
                                visit
                                {patient.visits_with_charges !== 1 ? 's' : ''}
                            </p>
                        </div>
                    </div>

                    {/* Visits Grouped by Date */}
                    <div className="space-y-3">
                        <h4 className="font-medium">
                            Visits with Outstanding Charges:
                        </h4>
                        {patient.visits.map((visit) => (
                            <div
                                key={visit.checkin_id}
                                className="rounded-lg border bg-card"
                            >
                                <div
                                    className="flex cursor-pointer items-center justify-between p-3 hover:bg-muted/50"
                                    onClick={() =>
                                        toggleVisit(visit.checkin_id)
                                    }
                                >
                                    <div className="flex-1">
                                        <p className="text-sm font-medium">
                                            {visit.department.name} •{' '}
                                            {visit.checked_in_at}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {visit.charges_count} charge
                                            {visit.charges_count !== 1
                                                ? 's'
                                                : ''}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <div className="space-y-1 text-right">
                                            <div className="font-medium text-orange-600">
                                                Patient:{' '}
                                                {formatCurrency(
                                                    visit.patient_copay,
                                                )}
                                            </div>
                                            {visit.insurance_covered > 0 && (
                                                <div className="text-xs text-green-600">
                                                    Insurance:{' '}
                                                    {formatCurrency(
                                                        visit.insurance_covered,
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                        {expandedVisits.has(
                                            visit.checkin_id,
                                        ) ? (
                                            <ChevronUp className="h-4 w-4" />
                                        ) : (
                                            <ChevronDown className="h-4 w-4" />
                                        )}
                                    </div>
                                </div>

                                {expandedVisits.has(visit.checkin_id) && (
                                    <div className="space-y-2 border-t p-3">
                                        {visit.charges.map((charge) => (
                                            <div
                                                key={charge.id}
                                                className="flex items-center justify-between gap-2 rounded-lg border bg-muted/30 p-3"
                                            >
                                                <div className="flex-1">
                                                    <div className="flex items-center gap-2">
                                                        <span className="text-sm font-medium">
                                                            {charge.description}
                                                        </span>
                                                        {charge.is_insurance_claim && (
                                                            <InsuranceCoverageBadge
                                                                isInsuranceClaim={
                                                                    charge.is_insurance_claim
                                                                }
                                                                insuranceCoveredAmount={
                                                                    charge.insurance_covered_amount
                                                                }
                                                                patientCopayAmount={
                                                                    charge.patient_copay_amount
                                                                }
                                                                amount={
                                                                    charge.amount
                                                                }
                                                                className="text-xs"
                                                            />
                                                        )}
                                                    </div>
                                                    <p className="text-xs text-muted-foreground">
                                                        {formatServiceType(
                                                            charge.service_type,
                                                        )}{' '}
                                                        •{' '}
                                                        {formatDateTime(
                                                            charge.charged_at,
                                                        )}
                                                    </p>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <div className="text-right">
                                                        {charge.is_insurance_claim ? (
                                                            <div className="space-y-0.5">
                                                                <div className="font-medium text-orange-600">
                                                                    {formatCurrency(
                                                                        charge.patient_copay_amount,
                                                                    )}
                                                                </div>
                                                                <div className="text-[10px] text-muted-foreground">
                                                                    of{' '}
                                                                    {formatCurrency(
                                                                        charge.amount,
                                                                    )}
                                                                </div>
                                                            </div>
                                                        ) : (
                                                            <span className="font-medium">
                                                                {formatCurrency(
                                                                    charge.amount,
                                                                )}
                                                            </span>
                                                        )}
                                                    </div>
                                                    {(permissions.canWaiveCharges ||
                                                        permissions.canAdjustCharges) && (
                                                        <div className="flex gap-1">
                                                            {permissions.canWaiveCharges &&
                                                                onWaiveCharge && (
                                                                    <Button
                                                                        size="sm"
                                                                        variant="outline"
                                                                        onClick={() =>
                                                                            onWaiveCharge(
                                                                                charge.id,
                                                                            )
                                                                        }
                                                                    >
                                                                        Waive
                                                                    </Button>
                                                                )}
                                                            {permissions.canAdjustCharges &&
                                                                onAdjustCharge && (
                                                                    <Button
                                                                        size="sm"
                                                                        variant="outline"
                                                                        onClick={() =>
                                                                            onAdjustCharge(
                                                                                charge.id,
                                                                            )
                                                                        }
                                                                    >
                                                                        Adjust
                                                                    </Button>
                                                                )}
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>

                    {/* Override History Section */}
                    {patient.override_history &&
                        patient.override_history.length > 0 && (
                            <div className="space-y-3">
                                <h4 className="flex items-center gap-2 text-sm font-medium">
                                    <AlertTriangle className="h-4 w-4" />
                                    Override & Adjustment History
                                </h4>
                                <div className="space-y-2">
                                    {patient.override_history.map((item) => (
                                        <div
                                            key={item.id}
                                            className={`rounded-lg border p-3 ${
                                                item.is_active
                                                    ? 'border-yellow-300 bg-yellow-50 dark:border-yellow-800 dark:bg-yellow-950/20'
                                                    : 'bg-muted/30'
                                            }`}
                                        >
                                            <div className="flex items-start justify-between gap-2">
                                                <div className="flex-1">
                                                    <div className="flex items-center gap-2">
                                                        <Badge
                                                            variant="outline"
                                                            className={`text-xs ${getOverrideTypeColor(item.type)}`}
                                                        >
                                                            {item.type.toUpperCase()}
                                                        </Badge>
                                                        {item.is_active && (
                                                            <Badge
                                                                variant="outline"
                                                                className="bg-green-100 text-xs text-green-800"
                                                            >
                                                                ACTIVE
                                                            </Badge>
                                                        )}
                                                    </div>
                                                    <p className="mt-1 text-sm font-medium">
                                                        {item.type ===
                                                            'override' &&
                                                            item.service_type &&
                                                            `${formatServiceType(item.service_type)} Override`}
                                                        {item.type ===
                                                            'waiver' &&
                                                            item.charge_description &&
                                                            `Waived: ${item.charge_description}`}
                                                        {item.type ===
                                                            'adjustment' &&
                                                            item.charge_description &&
                                                            `Adjusted: ${item.charge_description}`}
                                                    </p>
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        {item.reason}
                                                    </p>
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        By{' '}
                                                        {
                                                            item.authorized_by
                                                                .name
                                                        }{' '}
                                                        •{' '}
                                                        {formatDateTime(
                                                            item.authorized_at,
                                                        )}
                                                    </p>
                                                    {item.expires_at &&
                                                        item.is_active && (
                                                            <p className="mt-1 flex items-center gap-1 text-xs font-medium text-yellow-700">
                                                                <Clock className="h-3 w-3" />
                                                                Expires in:{' '}
                                                                {
                                                                    item.remaining_duration
                                                                }
                                                            </p>
                                                        )}
                                                </div>
                                                {(item.type === 'waiver' ||
                                                    item.type ===
                                                        'adjustment') && (
                                                    <div className="text-right text-xs">
                                                        {item.original_amount && (
                                                            <p className="text-muted-foreground line-through">
                                                                {formatCurrency(
                                                                    item.original_amount,
                                                                )}
                                                            </p>
                                                        )}
                                                        {item.final_amount !==
                                                            undefined && (
                                                            <p className="font-medium text-green-600">
                                                                {formatCurrency(
                                                                    item.final_amount,
                                                                )}
                                                            </p>
                                                        )}
                                                        {item.adjustment_amount && (
                                                            <p className="text-muted-foreground">
                                                                -
                                                                {formatCurrency(
                                                                    item.adjustment_amount,
                                                                )}
                                                            </p>
                                                        )}
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                </CardContent>
            )}
        </Card>
    );
}
