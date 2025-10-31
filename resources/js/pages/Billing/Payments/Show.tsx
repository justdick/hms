import { InsuranceCoverageBadge } from '@/components/Insurance/InsuranceCoverageBadge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
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
import { Head, router, useForm } from '@inertiajs/react';
import {
    Calendar,
    CheckCircle,
    Clock,
    CreditCard,
    DollarSign,
    MapPin,
    Phone,
    Receipt,
    ShieldCheck,
    User,
    XCircle,
} from 'lucide-react';
import React, { useState } from 'react';

interface Patient {
    id: number;
    first_name: string;
    last_name: string;
    phone_number: string;
    date_of_birth: string;
}

interface Department {
    id: number;
    name: string;
}

interface PatientCheckin {
    id: number;
    patient: Patient;
    department: Department;
    checked_in_at: string;
    status: string;
}

interface Charge {
    id: number;
    description: string;
    amount: string; // Laravel casts decimal as string
    is_insurance_claim: boolean;
    insurance_covered_amount: string;
    patient_copay_amount: string;
    service_type: string;
    service_code?: string;
    status: 'pending' | 'paid' | 'partial' | 'waived' | 'cancelled';
    charged_at: string;
    paid_amount: string; // Laravel casts decimal as string
}

interface PatientInsurance {
    plan_name: string;
    provider_name: string;
    policy_number: string;
}

interface ServiceStatus {
    consultation: boolean;
    laboratory: boolean;
    pharmacy: boolean;
    ward: boolean;
}

interface Props {
    checkin: PatientCheckin;
    charges: Charge[];
    paidCharges: Charge[];
    totalAmount: number;
    totalPatientOwes: number;
    totalInsuranceCovered: number;
    patientInsurance: PatientInsurance | null;
    canProceedWithServices: ServiceStatus;
}

export default function PaymentShow({
    checkin,
    charges,
    paidCharges,
    totalAmount,
    totalPatientOwes,
    totalInsuranceCovered,
    patientInsurance,
    canProceedWithServices,
}: Props) {
    const [selectedCharges, setSelectedCharges] = useState<number[]>(
        charges.map((c) => c.id),
    );

    const { data, setData, post, processing, errors, reset } = useForm({
        charges: charges.map((c) => c.id),
        payment_method: 'cash',
        amount_paid: 0,
        notes: '',
    });

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-GH', {
            style: 'currency',
            currency: 'GHS',
        }).format(amount);
    };

    const getServiceIcon = (service: string, canProceed: boolean) => {
        const IconComponent = canProceed ? CheckCircle : XCircle;
        return (
            <IconComponent
                className={`h-4 w-4 ${canProceed ? 'text-green-600' : 'text-red-600'}`}
            />
        );
    };

    const handleChargeSelection = (chargeId: number, checked: boolean) => {
        let newSelectedCharges;
        if (checked) {
            newSelectedCharges = [...selectedCharges, chargeId];
            setSelectedCharges(newSelectedCharges);
        } else {
            newSelectedCharges = selectedCharges.filter(
                (id) => id !== chargeId,
            );
            setSelectedCharges(newSelectedCharges);
        }

        // Update form data
        setData('charges', newSelectedCharges);
    };

    const selectedChargesTotal = charges
        .filter((charge) => selectedCharges.includes(charge.id))
        .reduce((sum, charge) => sum + parseFloat(charge.amount), 0);

    // Calculate patient copay for selected charges (what patient needs to pay)
    const selectedChargesCopay = charges
        .filter((charge) => selectedCharges.includes(charge.id))
        .reduce(
            (sum, charge) =>
                sum +
                (charge.is_insurance_claim
                    ? parseFloat(charge.patient_copay_amount)
                    : parseFloat(charge.amount)),
            0,
        );

    // Update amount paid when selected charges change
    React.useEffect(() => {
        setData('amount_paid', selectedChargesCopay);
    }, [selectedChargesCopay, setData]);

    const handlePayment = () => {
        if (selectedCharges.length === 0) {
            return;
        }

        post(`/billing/checkin/${checkin.id}/payment`, {
            onSuccess: () => {
                // Form will automatically redirect/reload on success
            },
            onError: () => {
                // Errors will be available in the errors object
            },
        });
    };

    const handleEmergencyOverride = async (serviceType: string) => {
        const reason = prompt(
            `Enter reason for emergency override for ${serviceType}:`,
        );
        if (!reason) return;

        try {
            const response = await fetch(
                `/billing/checkin/${checkin.id}/emergency-override`,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN':
                            document
                                .querySelector('meta[name="csrf-token"]')
                                ?.getAttribute('content') || '',
                    },
                    body: JSON.stringify({
                        service_type: serviceType,
                        reason: reason,
                    }),
                },
            );

            if (response.ok) {
                setMessage({
                    type: 'success',
                    text: `Emergency override activated for ${serviceType}`,
                });
                router.reload();
            }
        } catch (error) {
            setMessage({ type: 'error', text: 'Emergency override failed' });
        }
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Billing', href: '/billing' },
                {
                    title: `${checkin.patient.first_name} ${checkin.patient.last_name}`,
                    href: '',
                },
            ]}
        >
            <Head
                title={`Billing - ${checkin.patient.first_name} ${checkin.patient.last_name}`}
            />

            <div className="mx-auto max-w-6xl space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">
                            Patient Billing
                        </h1>
                        <p className="text-gray-600">
                            {checkin.patient.first_name}{' '}
                            {checkin.patient.last_name} -{' '}
                            {checkin.department.name}
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        onClick={() => router.visit('/billing/charges')}
                        className="flex items-center gap-2"
                    >
                        <Receipt className="h-4 w-4" />
                        All Charges
                    </Button>
                </div>

                {/* Errors */}
                {Object.keys(errors).length > 0 && (
                    <Alert className="border-red-200 bg-red-50">
                        <AlertDescription className="text-red-800">
                            {Object.values(errors)[0]}
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Patient Info */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <User className="h-5 w-5" />
                                Patient Information
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="flex items-center gap-2">
                                <User className="h-4 w-4 text-gray-500" />
                                <span>
                                    {checkin.patient.first_name}{' '}
                                    {checkin.patient.last_name}
                                </span>
                            </div>
                            <div className="flex items-center gap-2">
                                <Phone className="h-4 w-4 text-gray-500" />
                                <span>{checkin.patient.phone_number}</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <Calendar className="h-4 w-4 text-gray-500" />
                                <span>
                                    {new Date(
                                        checkin.patient.date_of_birth,
                                    ).toLocaleDateString()}
                                </span>
                            </div>
                            <div className="flex items-center gap-2">
                                <MapPin className="h-4 w-4 text-gray-500" />
                                <span>{checkin.department.name}</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <Clock className="h-4 w-4 text-gray-500" />
                                <span>
                                    Checked in:{' '}
                                    {new Date(
                                        checkin.checked_in_at,
                                    ).toLocaleString()}
                                </span>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Service Status */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Service Access Status</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {Object.entries(canProceedWithServices).map(
                                ([service, canProceed]) => (
                                    <div
                                        key={service}
                                        className="flex items-center justify-between"
                                    >
                                        <div className="flex items-center gap-2">
                                            {getServiceIcon(
                                                service,
                                                canProceed,
                                            )}
                                            <span
                                                className={
                                                    canProceed
                                                        ? 'text-green-700'
                                                        : 'text-red-700'
                                                }
                                            >
                                                {service
                                                    .charAt(0)
                                                    .toUpperCase() +
                                                    service.slice(1)}
                                            </span>
                                        </div>
                                        {!canProceed && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    handleEmergencyOverride(
                                                        service,
                                                    )
                                                }
                                                className="text-orange-600 hover:text-orange-700"
                                            >
                                                Override
                                            </Button>
                                        )}
                                    </div>
                                ),
                            )}
                        </CardContent>
                    </Card>

                    {/* Payment Summary */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <DollarSign className="h-5 w-5" />
                                Payment Summary
                            </CardTitle>
                            {patientInsurance && (
                                <div className="mt-2 rounded-md bg-green-50 p-2 text-xs text-green-800 dark:bg-green-950/20 dark:text-green-200">
                                    <div className="flex items-center gap-1">
                                        <ShieldCheck className="h-3 w-3" />
                                        <span className="font-medium">
                                            {patientInsurance.provider_name} -{' '}
                                            {patientInsurance.plan_name}
                                        </span>
                                    </div>
                                    <div className="text-[10px]">
                                        Policy: {patientInsurance.policy_number}
                                    </div>
                                </div>
                            )}
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="flex justify-between">
                                <span className="text-gray-600">
                                    Total Charges:
                                </span>
                                <span className="font-semibold">
                                    {formatCurrency(totalAmount)}
                                </span>
                            </div>
                            {totalInsuranceCovered > 0 && (
                                <>
                                    <div className="flex justify-between">
                                        <span className="flex items-center gap-1 text-gray-600">
                                            <ShieldCheck className="h-3 w-3 text-green-600" />
                                            Insurance Covers:
                                        </span>
                                        <span className="font-semibold text-green-600">
                                            {formatCurrency(
                                                totalInsuranceCovered,
                                            )}
                                        </span>
                                    </div>
                                    <div className="border-t" />
                                </>
                            )}
                            <div className="flex justify-between">
                                <span className="font-medium text-gray-900">
                                    Patient Owes:
                                </span>
                                <span className="font-bold text-orange-600">
                                    {formatCurrency(totalPatientOwes)}
                                </span>
                            </div>
                            <div className="border-t" />
                            <div className="flex justify-between">
                                <span className="text-gray-600">Selected:</span>
                                <span className="font-semibold">
                                    {formatCurrency(selectedChargesCopay)}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-gray-600">
                                    Amount to Collect:
                                </span>
                                <span className="font-semibold text-green-600">
                                    {formatCurrency(data.amount_paid || 0)}
                                </span>
                            </div>
                            {paidCharges.length > 0 && (
                                <div className="flex justify-between border-t pt-2">
                                    <span className="text-gray-600">
                                        Already Paid:
                                    </span>
                                    <span className="font-semibold text-green-600">
                                        {formatCurrency(
                                            paidCharges.reduce(
                                                (sum, charge) =>
                                                    sum +
                                                    parseFloat(
                                                        charge.paid_amount,
                                                    ),
                                                0,
                                            ),
                                        )}
                                    </span>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* Pending Charges */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Pending Charges</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {charges.length === 0 ? (
                                <div className="py-8 text-center text-gray-500">
                                    <CheckCircle className="mx-auto mb-3 h-12 w-12 text-green-500" />
                                    <p>No pending charges</p>
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    {charges.map((charge) => (
                                        <div
                                            key={charge.id}
                                            className="flex items-center gap-3 rounded border p-3"
                                        >
                                            <Checkbox
                                                checked={selectedCharges.includes(
                                                    charge.id,
                                                )}
                                                onCheckedChange={(checked) =>
                                                    handleChargeSelection(
                                                        charge.id,
                                                        checked as boolean,
                                                    )
                                                }
                                            />
                                            <div className="flex-1">
                                                <div className="flex items-start justify-between gap-2">
                                                    <div className="flex-1">
                                                        <div className="font-medium">
                                                            {charge.description}
                                                        </div>
                                                        {charge.is_insurance_claim && (
                                                            <div className="mt-1 space-y-0.5 text-xs">
                                                                <div className="flex items-center gap-1">
                                                                    <ShieldCheck className="h-3 w-3 text-green-600" />
                                                                    <span className="text-green-600">
                                                                        Insurance:{' '}
                                                                        {formatCurrency(
                                                                            parseFloat(
                                                                                charge.insurance_covered_amount,
                                                                            ),
                                                                        )}
                                                                    </span>
                                                                </div>
                                                                <div className="text-orange-600">
                                                                    Patient
                                                                    Copay:{' '}
                                                                    {formatCurrency(
                                                                        parseFloat(
                                                                            charge.patient_copay_amount,
                                                                        ),
                                                                    )}
                                                                </div>
                                                            </div>
                                                        )}
                                                    </div>
                                                    <div className="text-right">
                                                        <div className="font-semibold">
                                                            {formatCurrency(
                                                                parseFloat(
                                                                    charge.amount,
                                                                ),
                                                            )}
                                                        </div>
                                                        {charge.is_insurance_claim && (
                                                            <div className="mt-1 text-xs font-medium text-orange-600">
                                                                Pay:{' '}
                                                                {formatCurrency(
                                                                    parseFloat(
                                                                        charge.patient_copay_amount,
                                                                    ),
                                                                )}
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>
                                                <div className="mt-1 flex items-center gap-2">
                                                    <Badge
                                                        variant="outline"
                                                        className="text-xs"
                                                    >
                                                        {charge.service_type}
                                                    </Badge>
                                                    {charge.is_insurance_claim && (
                                                        <InsuranceCoverageBadge
                                                            isInsuranceClaim={
                                                                charge.is_insurance_claim
                                                            }
                                                            insuranceCoveredAmount={parseFloat(
                                                                charge.insurance_covered_amount,
                                                            )}
                                                            patientCopayAmount={parseFloat(
                                                                charge.patient_copay_amount,
                                                            )}
                                                            amount={parseFloat(
                                                                charge.amount,
                                                            )}
                                                            className="text-xs"
                                                        />
                                                    )}
                                                    <span className="text-xs text-gray-500">
                                                        {new Date(
                                                            charge.charged_at,
                                                        ).toLocaleDateString()}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Payment Form */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <CreditCard className="h-5 w-5" />
                                Process Payment
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="payment-method">
                                    Payment Method
                                </Label>
                                <Select
                                    value={data.payment_method}
                                    onValueChange={(value) =>
                                        setData('payment_method', value)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="cash">
                                            Cash
                                        </SelectItem>
                                        <SelectItem value="card">
                                            Card
                                        </SelectItem>
                                        <SelectItem value="mobile_money">
                                            Mobile Money
                                        </SelectItem>
                                        <SelectItem value="insurance">
                                            Insurance
                                        </SelectItem>
                                        <SelectItem value="bank_transfer">
                                            Bank Transfer
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="amount">
                                    Amount to Collect from Patient
                                </Label>
                                <Input
                                    id="amount"
                                    type="number"
                                    value={data.amount_paid}
                                    onChange={(e) =>
                                        setData(
                                            'amount_paid',
                                            parseFloat(e.target.value) || 0,
                                        )
                                    }
                                    placeholder="0.00"
                                    step="0.01"
                                    min="0"
                                    max={selectedChargesCopay}
                                />
                                <p className="text-xs text-muted-foreground">
                                    Patient owes:{' '}
                                    {formatCurrency(selectedChargesCopay)}{' '}
                                    (copay amount)
                                </p>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            setData(
                                                'amount_paid',
                                                selectedChargesCopay,
                                            )
                                        }
                                    >
                                        Full Copay
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            setData(
                                                'amount_paid',
                                                parseFloat(
                                                    (
                                                        selectedChargesCopay / 2
                                                    ).toFixed(2),
                                                ),
                                            )
                                        }
                                    >
                                        Half Amount
                                    </Button>
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="notes">Notes (Optional)</Label>
                                <Textarea
                                    id="notes"
                                    value={data.notes}
                                    onChange={(e) =>
                                        setData('notes', e.target.value)
                                    }
                                    placeholder="Payment notes or reference..."
                                    rows={3}
                                />
                            </div>

                            <Button
                                onClick={handlePayment}
                                disabled={
                                    processing ||
                                    selectedCharges.length === 0 ||
                                    !data.amount_paid ||
                                    data.amount_paid <= 0
                                }
                                className="w-full"
                            >
                                {processing ? (
                                    <>
                                        <div className="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                                        Processing...
                                    </>
                                ) : (
                                    <>
                                        <CreditCard className="mr-2 h-4 w-4" />
                                        Process Payment (
                                        {formatCurrency(data.amount_paid || 0)})
                                    </>
                                )}
                            </Button>
                        </CardContent>
                    </Card>
                </div>

                {/* Paid Charges History */}
                {paidCharges.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Payment History</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                {paidCharges.map((charge) => (
                                    <div
                                        key={charge.id}
                                        className="flex items-center justify-between rounded border border-green-200 bg-green-50 p-3"
                                    >
                                        <div>
                                            <span className="font-medium">
                                                {charge.description}
                                            </span>
                                            <div className="mt-1 flex items-center gap-2">
                                                <Badge
                                                    variant="secondary"
                                                    className="bg-green-100 text-xs text-green-700"
                                                >
                                                    {charge.status}
                                                </Badge>
                                                <span className="text-xs text-gray-500">
                                                    Paid:{' '}
                                                    {charge.paid_at
                                                        ? new Date(
                                                              charge.paid_at,
                                                          ).toLocaleDateString()
                                                        : 'N/A'}
                                                </span>
                                            </div>
                                        </div>
                                        <span className="font-semibold text-green-600">
                                            {formatCurrency(
                                                parseFloat(charge.paid_amount),
                                            )}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
