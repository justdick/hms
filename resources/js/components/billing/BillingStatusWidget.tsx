import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatCurrency } from '@/lib/utils';
import { router } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle,
    Clock,
    CreditCard,
    DollarSign,
    XCircle,
} from 'lucide-react';
import { useEffect, useState } from 'react';

interface Charge {
    id: number;
    description: string;
    amount: number;
    service_type: string;
    service_code?: string;
    status: 'pending' | 'paid' | 'partial' | 'waived' | 'cancelled';
    charged_at: string;
}

interface ServiceStatus {
    consultation: boolean;
    laboratory: boolean;
    pharmacy: boolean;
    ward: boolean;
}

interface EmergencyOverride {
    service_type: string;
    service_code?: string;
    reason: string;
    authorized_by: number;
    authorized_at: string;
    expires_at: string;
}

interface BillingStatus {
    has_pending_charges: boolean;
    total_pending: number;
    pending_charges: Charge[];
    service_status: ServiceStatus;
    emergency_overrides: EmergencyOverride[];
}

interface BillingStatusWidgetProps {
    checkinId: number;
    compact?: boolean;
    showActions?: boolean;
    onPaymentSuccess?: () => void;
}

export default function BillingStatusWidget({
    checkinId,
    compact = false,
    showActions = true,
    onPaymentSuccess,
}: BillingStatusWidgetProps) {
    const [billingStatus, setBillingStatus] = useState<BillingStatus | null>(
        null,
    );
    const [loading, setLoading] = useState(true);
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        fetchBillingStatus();
    }, [checkinId]);

    const fetchBillingStatus = async () => {
        try {
            const response = await fetch(
                `/billing/checkin/${checkinId}/billing-status`,
            );
            const data = await response.json();
            setBillingStatus(data);
        } catch (error) {
            console.error('Failed to fetch billing status:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleEmergencyOverride = async (
        serviceType: string,
        serviceCode?: string,
    ) => {
        const reason = prompt(
            `Enter reason for emergency override for ${serviceType}:`,
        );
        if (!reason) return;

        setProcessing(true);
        try {
            const response = await fetch(
                `/billing/checkin/${checkinId}/emergency-override`,
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
                        service_code: serviceCode,
                        reason: reason,
                    }),
                },
            );

            if (response.ok) {
                await fetchBillingStatus();
                if (onPaymentSuccess) onPaymentSuccess();
            }
        } catch (error) {
            console.error('Emergency override failed:', error);
        } finally {
            setProcessing(false);
        }
    };

    const openPaymentPage = () => {
        router.visit(`/billing/checkin/${checkinId}/billing`);
    };

    if (loading) {
        return (
            <Card className={compact ? 'p-2' : ''}>
                <CardContent className="p-4">
                    <div className="animate-pulse">
                        <div className="mb-2 h-4 w-3/4 rounded bg-gray-200"></div>
                        <div className="h-4 w-1/2 rounded bg-gray-200"></div>
                    </div>
                </CardContent>
            </Card>
        );
    }

    if (!billingStatus) {
        return null;
    }



    const getServiceIcon = (service: string, canProceed: boolean) => {
        const IconComponent = canProceed ? CheckCircle : XCircle;
        return (
            <IconComponent
                className={`h-4 w-4 ${canProceed ? 'text-green-600' : 'text-red-600'}`}
            />
        );
    };

    const hasActiveOverrides = billingStatus.emergency_overrides.length > 0;

    if (compact) {
        return (
            <div className="flex items-center gap-2 rounded bg-gray-50 p-2">
                {billingStatus.has_pending_charges ? (
                    <>
                        <AlertTriangle className="h-4 w-4 text-amber-600" />
                        <span className="text-sm font-medium text-amber-700">
                            {formatCurrency(billingStatus.total_pending)}{' '}
                            pending
                        </span>
                        {showActions && (
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={openPaymentPage}
                                className="ml-auto"
                            >
                                Pay Now
                            </Button>
                        )}
                    </>
                ) : (
                    <>
                        <CheckCircle className="h-4 w-4 text-green-600" />
                        <span className="text-sm text-green-700">
                            All cleared
                        </span>
                    </>
                )}
            </div>
        );
    }

    return (
        <Card>
            <CardHeader className="pb-3">
                <CardTitle className="flex items-center gap-2 text-base">
                    <CreditCard className="h-5 w-5" />
                    Billing Status
                    {hasActiveOverrides && (
                        <Badge
                            variant="outline"
                            className="border-orange-600 text-orange-600"
                        >
                            Emergency Override Active
                        </Badge>
                    )}
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                {/* Payment Status */}
                {billingStatus.has_pending_charges ? (
                    <Alert className="border-amber-200 bg-amber-50">
                        <AlertTriangle className="h-4 w-4 text-amber-600" />
                        <AlertDescription className="text-amber-800">
                            <div className="flex items-center justify-between">
                                <span>
                                    <strong>
                                        {formatCurrency(
                                            billingStatus.total_pending,
                                        )}
                                    </strong>{' '}
                                    in pending charges
                                </span>
                                {showActions && (
                                    <Button
                                        size="sm"
                                        onClick={openPaymentPage}
                                        className="bg-amber-600 hover:bg-amber-700"
                                    >
                                        <DollarSign className="mr-1 h-4 w-4" />
                                        Pay Now
                                    </Button>
                                )}
                            </div>
                        </AlertDescription>
                    </Alert>
                ) : (
                    <Alert className="border-green-200 bg-green-50">
                        <CheckCircle className="h-4 w-4 text-green-600" />
                        <AlertDescription className="text-green-800">
                            All charges have been cleared
                        </AlertDescription>
                    </Alert>
                )}

                {/* Service Status */}
                <div className="space-y-2">
                    <h4 className="text-sm font-medium text-gray-700">
                        Service Access
                    </h4>
                    <div className="grid grid-cols-2 gap-2 text-sm">
                        {Object.entries(billingStatus.service_status).map(
                            ([service, canProceed]) => (
                                <div
                                    key={service}
                                    className="flex items-center gap-2"
                                >
                                    {getServiceIcon(service, canProceed)}
                                    <span
                                        className={
                                            canProceed
                                                ? 'text-green-700'
                                                : 'text-red-700'
                                        }
                                    >
                                        {service.charAt(0).toUpperCase() +
                                            service.slice(1)}
                                    </span>
                                    {!canProceed && showActions && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                handleEmergencyOverride(service)
                                            }
                                            disabled={processing}
                                            className="ml-auto h-6 px-2 text-xs text-orange-600 hover:text-orange-700"
                                        >
                                            Override
                                        </Button>
                                    )}
                                </div>
                            ),
                        )}
                    </div>
                </div>

                {/* Pending Charges List */}
                {billingStatus.pending_charges.length > 0 && (
                    <div className="space-y-2">
                        <h4 className="text-sm font-medium text-gray-700">
                            Pending Charges
                        </h4>
                        <div className="space-y-1">
                            {billingStatus.pending_charges.map((charge) => (
                                <div
                                    key={charge.id}
                                    className="flex items-center justify-between py-1 text-sm"
                                >
                                    <span className="text-gray-600">
                                        {charge.description}
                                    </span>
                                    <span className="font-medium">
                                        {formatCurrency(charge.amount)}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Active Overrides */}
                {hasActiveOverrides && (
                    <div className="space-y-2">
                        <h4 className="text-sm font-medium text-gray-700">
                            Active Overrides
                        </h4>
                        {billingStatus.emergency_overrides.map(
                            (override, index) => (
                                <div
                                    key={index}
                                    className="rounded border border-orange-200 bg-orange-50 p-2"
                                >
                                    <div className="flex items-center gap-2 text-sm">
                                        <Clock className="h-4 w-4 text-orange-600" />
                                        <span className="font-medium text-orange-700">
                                            {override.service_type
                                                .charAt(0)
                                                .toUpperCase() +
                                                override.service_type.slice(1)}
                                        </span>
                                        <span className="ml-auto text-xs text-orange-600">
                                            Expires:{' '}
                                            {new Date(
                                                override.expires_at,
                                            ).toLocaleTimeString()}
                                        </span>
                                    </div>
                                    <p className="mt-1 text-xs text-orange-600">
                                        {override.reason}
                                    </p>
                                </div>
                            ),
                        )}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
