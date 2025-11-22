import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/react';
import { AlertTriangle, CheckCircle, Clock } from 'lucide-react';

interface Charge {
    id: number;
    description: string;
    amount: number;
    service_type: string;
}

interface ServiceAccessOverride {
    id: number;
    service_type: string;
    reason: string;
    authorized_by: {
        id: number;
        name: string;
    };
    expires_at: string;
    remaining_duration: string;
}

interface ServiceBlockAlertProps {
    isBlocked: boolean;
    blockReason?: string;
    pendingCharges?: Charge[];
    activeOverride?: ServiceAccessOverride | null;
    checkinId?: number;
}

export function ServiceBlockAlert({
    isBlocked,
    blockReason,
    pendingCharges = [],
    activeOverride,
    checkinId,
}: ServiceBlockAlertProps) {
    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-GH', {
            style: 'currency',
            currency: 'GHS',
        }).format(amount);
    };

    const totalPending = pendingCharges.reduce(
        (sum, charge) => sum + charge.amount,
        0,
    );

    // If there's an active override, show success message
    if (activeOverride) {
        return (
            <Alert className="border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950">
                <CheckCircle className="h-4 w-4 text-green-600 dark:text-green-400" />
                <AlertTitle className="text-green-900 dark:text-green-100">
                    Service Access Authorized
                </AlertTitle>
                <AlertDescription className="text-green-800 dark:text-green-200">
                    <p>Emergency override is active for this service.</p>
                    <div className="mt-2 flex items-center gap-2 text-sm">
                        <Clock className="h-3 w-3" />
                        <span>
                            Expires in: {activeOverride.remaining_duration}
                        </span>
                    </div>
                    <p className="mt-1 text-sm text-green-700 dark:text-green-300">
                        Authorized by: {activeOverride.authorized_by.name}
                    </p>
                </AlertDescription>
            </Alert>
        );
    }

    // If service is blocked, show error message
    if (isBlocked) {
        return (
            <Alert
                variant="destructive"
                className="border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950"
            >
                <AlertTriangle className="h-4 w-4" />
                <AlertTitle>Service Blocked - Payment Required</AlertTitle>
                <AlertDescription>
                    <p className="font-medium">{blockReason}</p>

                    {pendingCharges.length > 0 && (
                        <div className="mt-3 space-y-2">
                            <p className="text-sm font-medium">
                                Outstanding Charges:
                            </p>
                            <div className="space-y-1">
                                {pendingCharges.map((charge) => (
                                    <div
                                        key={charge.id}
                                        className="flex items-center justify-between rounded border border-red-200 bg-white px-3 py-2 text-sm dark:border-red-800 dark:bg-red-900"
                                    >
                                        <span className="text-gray-900 dark:text-gray-100">
                                            {charge.description}
                                        </span>
                                        <Badge
                                            variant="outline"
                                            className="border-red-300 text-red-700 dark:border-red-700 dark:text-red-300"
                                        >
                                            {formatCurrency(charge.amount)}
                                        </Badge>
                                    </div>
                                ))}
                            </div>
                            <div className="flex items-center justify-between border-t border-red-200 pt-2 font-semibold dark:border-red-800">
                                <span>Total Outstanding:</span>
                                <span className="text-lg">
                                    {formatCurrency(totalPending)}
                                </span>
                            </div>
                        </div>
                    )}

                    <div className="mt-4 rounded-md border border-red-300 bg-red-100 p-3 dark:border-red-700 dark:bg-red-900">
                        <p className="font-semibold text-red-900 dark:text-red-100">
                            Please direct the patient to the billing desk to
                            resolve payment before proceeding with this service.
                        </p>
                    </div>

                    {checkinId && (
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.visit(`/billing?checkin=${checkinId}`)
                            }
                            className="mt-3"
                        >
                            View Billing Details
                        </Button>
                    )}
                </AlertDescription>
            </Alert>
        );
    }

    return null;
}
