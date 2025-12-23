import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    AlertTriangle,
    CheckCircle,
    Clock,
    CreditCard,
    FileText,
    Printer,
    Receipt,
    RefreshCw,
    User,
    XCircle,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import RefundModal from './RefundModal';
import VoidConfirmationModal from './VoidConfirmationModal';

interface Patient {
    id: number;
    patient_number: string;
    name: string;
}

interface Department {
    id: number;
    name: string;
}

interface ProcessedBy {
    id: number;
    name: string;
}

interface Payment {
    id: number;
    service_type: string;
    service_code: string | null;
    description: string;
    amount: number;
    paid_amount: number;
    status: string;
    receipt_number: string | null;
    paid_at: string;
    metadata: {
        payment_method?: string;
        reference_number?: string;
    } | null;
    patient_checkin: {
        patient: Patient;
        department: Department;
    } | null;
    processed_by_user: ProcessedBy | null;
}

interface AuditEntry {
    id: number;
    action: string;
    user_name: string;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    reason: string | null;
    ip_address: string | null;
    created_at: string;
}

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    payment: Payment | null;
    canVoid?: boolean;
    canRefund?: boolean;
}

export default function DetailSlideOver({
    open,
    onOpenChange,
    payment,
    canVoid = false,
    canRefund = false,
}: Props) {
    const [auditTrail, setAuditTrail] = useState<AuditEntry[]>([]);
    const [loading, setLoading] = useState(false);
    const [showVoidModal, setShowVoidModal] = useState(false);
    const [showRefundModal, setShowRefundModal] = useState(false);

    useEffect(() => {
        if (open && payment) {
            fetchAuditTrail();
        }
    }, [open, payment]);

    const fetchAuditTrail = async () => {
        if (!payment) return;

        setLoading(true);
        try {
            const response = await fetch(
                `/billing/accounts/history/${payment.id}`,
            );
            const data = await response.json();
            if (data.auditTrail) {
                setAuditTrail(data.auditTrail);
            }
        } catch {
            // Silently fail - audit trail is supplementary
            setAuditTrail([]);
        } finally {
            setLoading(false);
        }
    };

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-GH', {
            style: 'currency',
            currency: 'GHS',
        }).format(amount);
    };

    const formatDateTime = (dateString: string) => {
        return new Date(dateString).toLocaleString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'paid':
                return (
                    <Badge className="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                        <CheckCircle className="mr-1 h-3 w-3" />
                        Paid
                    </Badge>
                );
            case 'partial':
                return (
                    <Badge className="bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                        Partial
                    </Badge>
                );
            case 'voided':
                return (
                    <Badge className="bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                        <XCircle className="mr-1 h-3 w-3" />
                        Voided
                    </Badge>
                );
            case 'refunded':
                return (
                    <Badge className="bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                        <RefreshCw className="mr-1 h-3 w-3" />
                        Refunded
                    </Badge>
                );
            default:
                return <Badge>{status}</Badge>;
        }
    };

    const getActionIcon = (action: string) => {
        switch (action) {
            case 'payment':
                return <CreditCard className="h-4 w-4 text-green-600" />;
            case 'void':
                return <XCircle className="h-4 w-4 text-red-600" />;
            case 'refund':
                return <RefreshCw className="h-4 w-4 text-purple-600" />;
            case 'receipt_printed':
                return <Printer className="h-4 w-4 text-blue-600" />;
            case 'override':
                return <AlertTriangle className="h-4 w-4 text-orange-600" />;
            default:
                return <FileText className="h-4 w-4 text-gray-600" />;
        }
    };

    const getActionLabel = (action: string) => {
        switch (action) {
            case 'payment':
                return 'Payment Processed';
            case 'void':
                return 'Payment Voided';
            case 'refund':
                return 'Payment Refunded';
            case 'receipt_printed':
                return 'Receipt Printed';
            case 'override':
                return 'Override Applied';
            case 'statement_generated':
                return 'Statement Generated';
            case 'credit_tag_added':
                return 'Credit Tag Added';
            case 'credit_tag_removed':
                return 'Credit Tag Removed';
            default:
                return action
                    .replace(/_/g, ' ')
                    .replace(/\b\w/g, (l) => l.toUpperCase());
        }
    };

    if (!payment) return null;

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="w-full overflow-y-auto sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle className="flex items-center gap-2">
                        <Receipt className="h-5 w-5" />
                        Payment Details
                    </SheetTitle>
                    <SheetDescription>
                        View complete payment information and audit trail
                    </SheetDescription>
                </SheetHeader>

                <div className="mt-6 space-y-6">
                    {/* Status and Receipt */}
                    <div className="flex items-center justify-between">
                        {getStatusBadge(payment.status)}
                        {payment.receipt_number && (
                            <span className="font-mono text-sm text-muted-foreground">
                                {payment.receipt_number}
                            </span>
                        )}
                    </div>

                    {/* Amount */}
                    <div className="rounded-lg bg-green-50 p-4 dark:bg-green-900/20">
                        <div className="text-sm text-green-600 dark:text-green-400">
                            Amount Paid
                        </div>
                        <div className="text-3xl font-bold text-green-700 dark:text-green-300">
                            {formatCurrency(payment.paid_amount)}
                        </div>
                        {payment.amount !== payment.paid_amount && (
                            <div className="mt-1 text-sm text-muted-foreground">
                                Original: {formatCurrency(payment.amount)}
                            </div>
                        )}
                    </div>

                    {/* Patient Info */}
                    {payment.patient_checkin?.patient && (
                        <div className="space-y-2">
                            <h4 className="flex items-center gap-2 font-medium">
                                <User className="h-4 w-4" />
                                Patient
                            </h4>
                            <div className="rounded-lg border p-3">
                                <div className="font-medium">
                                    {payment.patient_checkin.patient.name}
                                </div>
                                <div className="text-sm text-muted-foreground">
                                    {
                                        payment.patient_checkin.patient
                                            .patient_number
                                    }
                                </div>
                                {payment.patient_checkin.department && (
                                    <div className="mt-1 text-sm text-muted-foreground">
                                        Department:{' '}
                                        {
                                            payment.patient_checkin.department
                                                .name
                                        }
                                    </div>
                                )}
                            </div>
                        </div>
                    )}

                    {/* Payment Details */}
                    <div className="space-y-2">
                        <h4 className="flex items-center gap-2 font-medium">
                            <CreditCard className="h-4 w-4" />
                            Payment Information
                        </h4>
                        <div className="space-y-2 rounded-lg border p-3">
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">
                                    Service Type
                                </span>
                                <span className="font-medium capitalize">
                                    {payment.service_type.replace(/_/g, ' ')}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">
                                    Description
                                </span>
                                <span className="max-w-[200px] text-right font-medium">
                                    {payment.description}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">
                                    Payment Method
                                </span>
                                <span className="font-medium capitalize">
                                    {payment.metadata?.payment_method?.replace(
                                        /_/g,
                                        ' ',
                                    ) || 'Cash'}
                                </span>
                            </div>
                            {payment.metadata?.reference_number && (
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Reference
                                    </span>
                                    <span className="font-mono text-sm">
                                        {payment.metadata.reference_number}
                                    </span>
                                </div>
                            )}
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">
                                    Date/Time
                                </span>
                                <span className="font-medium">
                                    {formatDateTime(payment.paid_at)}
                                </span>
                            </div>
                            {payment.processed_by_user && (
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Processed By
                                    </span>
                                    <span className="font-medium">
                                        {payment.processed_by_user.name}
                                    </span>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Audit Trail */}
                    <div className="space-y-2">
                        <h4 className="flex items-center gap-2 font-medium">
                            <Clock className="h-4 w-4" />
                            Audit Trail
                        </h4>
                        <div className="rounded-lg border">
                            {loading ? (
                                <div className="p-4 text-center text-muted-foreground">
                                    Loading audit trail...
                                </div>
                            ) : auditTrail.length > 0 ? (
                                <div className="divide-y">
                                    {auditTrail.map((entry) => (
                                        <div key={entry.id} className="p-3">
                                            <div className="flex items-start gap-3">
                                                <div className="mt-0.5">
                                                    {getActionIcon(
                                                        entry.action,
                                                    )}
                                                </div>
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex items-center justify-between">
                                                        <span className="font-medium">
                                                            {getActionLabel(
                                                                entry.action,
                                                            )}
                                                        </span>
                                                        <span className="text-xs text-muted-foreground">
                                                            {formatDateTime(
                                                                entry.created_at,
                                                            )}
                                                        </span>
                                                    </div>
                                                    <div className="text-sm text-muted-foreground">
                                                        By {entry.user_name}
                                                    </div>
                                                    {entry.reason && (
                                                        <div className="mt-1 text-sm text-muted-foreground italic">
                                                            "{entry.reason}"
                                                        </div>
                                                    )}
                                                    {entry.new_values &&
                                                        Object.keys(
                                                            entry.new_values,
                                                        ).length > 0 && (
                                                            <div className="mt-2 rounded bg-gray-50 p-2 text-xs dark:bg-gray-800">
                                                                {Object.entries(
                                                                    entry.new_values,
                                                                ).map(
                                                                    ([
                                                                        key,
                                                                        value,
                                                                    ]) => (
                                                                        <div
                                                                            key={
                                                                                key
                                                                            }
                                                                            className="flex justify-between"
                                                                        >
                                                                            <span className="text-muted-foreground">
                                                                                {key.replace(
                                                                                    /_/g,
                                                                                    ' ',
                                                                                )}
                                                                                :
                                                                            </span>
                                                                            <span className="font-mono">
                                                                                {typeof value ===
                                                                                'object'
                                                                                    ? JSON.stringify(
                                                                                          value,
                                                                                      )
                                                                                    : String(
                                                                                          value,
                                                                                      )}
                                                                            </span>
                                                                        </div>
                                                                    ),
                                                                )}
                                                            </div>
                                                        )}
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="p-4 text-center text-muted-foreground">
                                    No audit trail available
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Actions */}
                    <div className="flex flex-col gap-2 border-t pt-4">
                        {/* Void and Refund buttons - only show for paid/partial payments */}
                        {(payment.status === 'paid' ||
                            payment.status === 'partial') &&
                            (canVoid || canRefund) && (
                                <div className="flex gap-2">
                                    {canVoid && (
                                        <Button
                                            variant="destructive"
                                            className="flex-1"
                                            onClick={() =>
                                                setShowVoidModal(true)
                                            }
                                        >
                                            <XCircle className="mr-2 h-4 w-4" />
                                            Void
                                        </Button>
                                    )}
                                    {canRefund && (
                                        <Button
                                            variant="outline"
                                            className="flex-1 border-purple-600 text-purple-600 hover:bg-purple-50 dark:hover:bg-purple-900/20"
                                            onClick={() =>
                                                setShowRefundModal(true)
                                            }
                                        >
                                            <RefreshCw className="mr-2 h-4 w-4" />
                                            Refund
                                        </Button>
                                    )}
                                </div>
                            )}
                        <Button
                            variant="outline"
                            className="w-full"
                            onClick={() => onOpenChange(false)}
                        >
                            Close
                        </Button>
                    </div>
                </div>
            </SheetContent>

            {/* Void Confirmation Modal */}
            <VoidConfirmationModal
                open={showVoidModal}
                onOpenChange={setShowVoidModal}
                payment={payment}
            />

            {/* Refund Modal */}
            <RefundModal
                open={showRefundModal}
                onOpenChange={setShowRefundModal}
                payment={payment}
            />
        </Sheet>
    );
}
