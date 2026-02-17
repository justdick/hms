import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { formatCurrency } from '@/lib/utils';
import { router } from '@inertiajs/react';
import {
    AlertCircle,
    Calendar,
    CheckCircle,
    FileText,
    Loader2,
    User,
    XCircle,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

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
    type_of_service: 'OPD' | 'IPD';
    type_of_attendance: 'EAE' | 'ANC' | 'PNC' | 'FP' | 'CWC' | 'REV';
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

/**
 * Props for the ClaimsVettingPanel component
 */
interface ClaimsVettingPanelProps {
    /** ID of the claim to display, null when no claim is selected */
    claimId: number | null;
    /** Whether the panel is currently open */
    isOpen: boolean;
    /** Callback function to close the panel */
    onClose: () => void;
    /** Callback function triggered after successful vetting action */
    onVetSuccess: () => void;
}

/**
 * ClaimsVettingPanel - Slide-over panel for reviewing and vetting insurance claims
 *
 * Features:
 * - Slide-over interface without page navigation
 * - Displays complete claim details including items, diagnosis, and financial summary
 * - Approve/reject actions with validation
 * - Keyboard shortcuts (Escape to close, Ctrl+Enter to approve)
 * - Focus management and focus trap for accessibility
 * - Loading states and error handling
 *
 * Accessibility:
 * - WCAG 2.1 Level AA compliant
 * - Proper ARIA labels and roles
 * - Focus trap within panel
 * - Returns focus to trigger element on close
 * - Screen reader announcements for status changes
 *
 * @example
 * ```tsx
 * <ClaimsVettingPanel
 *   claimId={selectedClaimId}
 *   isOpen={isPanelOpen}
 *   onClose={() => setIsPanelOpen(false)}
 *   onVetSuccess={refreshClaimsList}
 * />
 * ```
 */
export default function ClaimsVettingPanel({
    claimId,
    isOpen,
    onClose,
    onVetSuccess,
}: ClaimsVettingPanelProps) {
    const [claim, setClaim] = useState<InsuranceClaim | null>(null);
    const [loading, setLoading] = useState(false);
    const [vettingAction, setVettingAction] = useState<
        'approve' | 'reject' | null
    >(null);
    const [rejectionReason, setRejectionReason] = useState('');
    const [processing, setProcessing] = useState(false);

    // Focus management refs
    const panelRef = useRef<HTMLDivElement>(null);
    const previousFocusRef = useRef<HTMLElement | null>(null);
    const approveButtonRef = useRef<HTMLButtonElement>(null);

    useEffect(() => {
        if (claimId && isOpen) {
            setLoading(true);
            fetch(`/admin/insurance/claims/${claimId}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then((response) => response.json())
                .then((data) => {
                    setClaim(data.claim);
                    setLoading(false);
                })
                .catch((error) => {
                    console.error('Failed to load claim:', error);
                    setLoading(false);
                });
        }
    }, [claimId, isOpen]);

    useEffect(() => {
        if (!isOpen) {
            setClaim(null);
            setVettingAction(null);
            setRejectionReason('');
        }
    }, [isOpen]);

    // Focus management: Save previous focus and set focus to panel when opened
    useEffect(() => {
        if (isOpen) {
            // Save the currently focused element
            previousFocusRef.current = document.activeElement as HTMLElement;

            // Set focus to the first focusable element in the panel after a short delay
            setTimeout(() => {
                if (claim && approveButtonRef.current) {
                    approveButtonRef.current.focus();
                } else if (panelRef.current) {
                    const firstFocusable =
                        panelRef.current.querySelector<HTMLElement>(
                            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])',
                        );
                    firstFocusable?.focus();
                }
            }, 100);
        } else {
            // Return focus to the element that opened the panel
            if (previousFocusRef.current) {
                previousFocusRef.current.focus();
            }
        }
    }, [isOpen, claim]);

    // Focus trap: Keep focus within the panel
    useEffect(() => {
        if (!isOpen || !panelRef.current) return;

        const handleFocusTrap = (e: KeyboardEvent) => {
            if (e.key !== 'Tab') return;

            const focusableElements =
                panelRef.current?.querySelectorAll<HTMLElement>(
                    'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
                );

            if (!focusableElements || focusableElements.length === 0) return;

            const firstElement = focusableElements[0];
            const lastElement = focusableElements[focusableElements.length - 1];

            if (e.shiftKey) {
                // Shift + Tab
                if (document.activeElement === firstElement) {
                    e.preventDefault();
                    lastElement.focus();
                }
            } else {
                // Tab
                if (document.activeElement === lastElement) {
                    e.preventDefault();
                    firstElement.focus();
                }
            }
        };

        document.addEventListener('keydown', handleFocusTrap);
        return () => document.removeEventListener('keydown', handleFocusTrap);
    }, [isOpen]);

    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if (!isOpen) return;

            if (e.key === 'Escape') {
                onClose();
            }

            if (e.ctrlKey && e.key === 'Enter' && claim) {
                e.preventDefault();
                handleVet('approve');
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [isOpen, claim, onClose]);

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        });
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

    const handleVet = (action: 'approve' | 'reject') => {
        if (action === 'reject' && !rejectionReason.trim()) {
            alert('Please provide a rejection reason');
            return;
        }

        setProcessing(true);

        router.post(
            `/admin/insurance/claims/${claim?.id}/vet`,
            {
                action,
                rejection_reason: action === 'reject' ? rejectionReason : null,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setProcessing(false);
                    onVetSuccess();
                    onClose();
                },
                onError: (errors) => {
                    setProcessing(false);
                    console.error('Vetting failed:', errors);
                    alert('Failed to vet claim. Please try again.');
                },
            },
        );
    };

    return (
        <Sheet open={isOpen} onOpenChange={onClose}>
            <SheetContent
                ref={panelRef}
                className="w-full overflow-y-auto sm:max-w-2xl"
                role="dialog"
                aria-labelledby="vetting-panel-title"
                aria-describedby="vetting-panel-description"
                aria-modal="true"
            >
                {loading ? (
                    <div
                        className="flex h-full items-center justify-center"
                        role="status"
                        aria-live="polite"
                        aria-busy="true"
                    >
                        <Loader2 className="h-8 w-8 animate-spin text-gray-400" />
                        <span className="sr-only">
                            Loading claim details...
                        </span>
                    </div>
                ) : claim ? (
                    <>
                        <SheetHeader>
                            <SheetTitle
                                id="vetting-panel-title"
                                className="flex items-center gap-2"
                            >
                                <FileText
                                    className="h-5 w-5"
                                    aria-hidden="true"
                                />
                                Claim Vetting - {claim.claim_check_code}
                            </SheetTitle>
                            <SheetDescription id="vetting-panel-description">
                                Review and approve or reject this insurance
                                claim
                            </SheetDescription>
                        </SheetHeader>

                        <div className="mt-6 space-y-6" role="main">
                            {/* Claim Header */}
                            <section
                                className="space-y-4"
                                aria-labelledby="patient-info-heading"
                            >
                                <h2
                                    id="patient-info-heading"
                                    className="sr-only"
                                >
                                    Patient Information
                                </h2>
                                <div className="flex items-center justify-between">
                                    <div>
                                        <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                            {claim.patient_full_name}
                                        </h3>
                                        <p className="text-sm text-gray-600 dark:text-gray-400">
                                            Membership ID: {claim.membership_id}
                                        </p>
                                    </div>
                                    <Badge
                                        className={getStatusColor(claim.status)}
                                        role="status"
                                        aria-label={`Claim status: ${claim.status.replace('_', ' ')}`}
                                    >
                                        {claim.status
                                            .replace('_', ' ')
                                            .toUpperCase()}
                                    </Badge>
                                </div>

                                <div className="grid grid-cols-2 gap-4 text-sm">
                                    <div className="flex items-center gap-2">
                                        <User
                                            className="h-4 w-4 text-gray-500"
                                            aria-hidden="true"
                                        />
                                        <span className="text-gray-600 dark:text-gray-400">
                                            Gender:
                                        </span>
                                        <span className="font-medium">
                                            {claim.patient_gender}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Calendar
                                            className="h-4 w-4 text-gray-500"
                                            aria-hidden="true"
                                        />
                                        <span className="text-gray-600 dark:text-gray-400">
                                            DOB:
                                        </span>
                                        <span className="font-medium">
                                            {formatDate(claim.patient_dob)}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Calendar
                                            className="h-4 w-4 text-gray-500"
                                            aria-hidden="true"
                                        />
                                        <span className="text-gray-600 dark:text-gray-400">
                                            Attendance:
                                        </span>
                                        <span className="font-medium">
                                            {formatDate(
                                                claim.date_of_attendance,
                                            )}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <FileText
                                            className="h-4 w-4 text-gray-500"
                                            aria-hidden="true"
                                        />
                                        <span className="text-gray-600 dark:text-gray-400">
                                            Service:
                                        </span>
                                        <span className="font-medium">
                                            {claim.type_of_service}
                                        </span>
                                    </div>
                                </div>

                                <div
                                    className="rounded-lg bg-blue-50 p-4 dark:bg-blue-950"
                                    role="region"
                                    aria-label="Insurance provider information"
                                >
                                    <div className="text-sm font-medium text-blue-900 dark:text-blue-100">
                                        Insurance Provider
                                    </div>
                                    <div className="mt-1 text-lg font-semibold text-blue-900 dark:text-blue-100">
                                        {claim.patient_insurance?.plan.provider
                                            .name || 'N/A'}
                                    </div>
                                    <div className="text-sm text-blue-700 dark:text-blue-300">
                                        {claim.patient_insurance?.plan
                                            .plan_name || ''}
                                    </div>
                                </div>
                            </section>

                            <Separator />

                            {/* Diagnosis */}
                            {claim.primary_diagnosis_description && (
                                <>
                                    <section
                                        className="space-y-2"
                                        aria-labelledby="diagnosis-heading"
                                    >
                                        <h4
                                            id="diagnosis-heading"
                                            className="font-semibold text-gray-900 dark:text-gray-100"
                                        >
                                            Diagnosis
                                        </h4>
                                        <div className="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                                            <div className="text-sm">
                                                <span className="font-medium">
                                                    {
                                                        claim.primary_diagnosis_code
                                                    }
                                                </span>
                                                {' - '}
                                                <span>
                                                    {
                                                        claim.primary_diagnosis_description
                                                    }
                                                </span>
                                            </div>
                                        </div>
                                    </section>
                                    <Separator />
                                </>
                            )}

                            {/* Claim Items */}
                            <section
                                className="space-y-2"
                                aria-labelledby="claim-items-heading"
                            >
                                <h4
                                    id="claim-items-heading"
                                    className="font-semibold text-gray-900 dark:text-gray-100"
                                >
                                    Claim Items
                                </h4>
                                <div className="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                    <Table
                                        role="table"
                                        aria-label="Claim items table"
                                    >
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>
                                                    Description
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    Qty
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    Tariff
                                                </TableHead>
                                                <TableHead className="text-right">
                                                    Insurance Pays
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {claim.items.map((item) => (
                                                <TableRow key={item.id}>
                                                    <TableCell>
                                                        <div>
                                                            <div className="font-medium">
                                                                {
                                                                    item.description
                                                                }
                                                            </div>
                                                            <div className="text-xs text-gray-600 dark:text-gray-400">
                                                                {item.item_type}
                                                                {item.code &&
                                                                    ` - ${item.code}`}
                                                            </div>
                                                        </div>
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        {item.quantity}
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        {formatCurrency(
                                                            item.unit_tariff,
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-right font-medium">
                                                        {formatCurrency(
                                                            item.insurance_pays,
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>
                            </section>

                            <Separator />

                            {/* Financial Summary */}
                            <section
                                className="space-y-2"
                                aria-labelledby="financial-summary-heading"
                            >
                                <h4
                                    id="financial-summary-heading"
                                    className="font-semibold text-gray-900 dark:text-gray-100"
                                >
                                    Financial Summary
                                </h4>
                                <div className="space-y-2 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                                    <div className="flex justify-between text-sm">
                                        <span className="text-gray-600 dark:text-gray-400">
                                            Total Claim Amount
                                        </span>
                                        <span className="font-medium">
                                            {formatCurrency(
                                                claim.total_claim_amount,
                                            )}
                                        </span>
                                    </div>
                                    <div className="flex justify-between text-sm">
                                        <span className="text-gray-600 dark:text-gray-400">
                                            Insurance Covered
                                        </span>
                                        <span className="font-medium text-blue-600">
                                            {formatCurrency(
                                                claim.insurance_covered_amount,
                                            )}
                                        </span>
                                    </div>
                                    <div className="flex justify-between text-sm">
                                        <span className="text-gray-600 dark:text-gray-400">
                                            Patient Copay
                                        </span>
                                        <span className="font-medium text-orange-600">
                                            {formatCurrency(
                                                claim.patient_copay_amount,
                                            )}
                                        </span>
                                    </div>
                                </div>
                            </section>

                            <Separator />

                            {/* Vetting Actions */}
                            {claim.status === 'pending_vetting' && (
                                <section
                                    className="space-y-4"
                                    aria-labelledby="vetting-actions-heading"
                                >
                                    <h4
                                        id="vetting-actions-heading"
                                        className="font-semibold text-gray-900 dark:text-gray-100"
                                    >
                                        Vetting Decision
                                    </h4>

                                    {vettingAction === 'reject' && (
                                        <div className="space-y-2">
                                            <Label htmlFor="rejection_reason">
                                                Rejection Reason *
                                            </Label>
                                            <Textarea
                                                id="rejection_reason"
                                                placeholder="Provide a reason for rejecting this claim..."
                                                value={rejectionReason}
                                                onChange={(e) =>
                                                    setRejectionReason(
                                                        e.target.value,
                                                    )
                                                }
                                                rows={4}
                                                className="resize-none"
                                            />
                                        </div>
                                    )}

                                    <div
                                        className="flex gap-2"
                                        role="group"
                                        aria-label="Vetting action buttons"
                                    >
                                        <Button
                                            ref={approveButtonRef}
                                            onClick={() => handleVet('approve')}
                                            disabled={processing}
                                            className="flex-1 bg-green-600 hover:bg-green-700"
                                            aria-label="Approve claim"
                                        >
                                            {processing ? (
                                                <>
                                                    <Loader2
                                                        className="mr-2 h-4 w-4 animate-spin"
                                                        aria-hidden="true"
                                                    />
                                                    Processing...
                                                </>
                                            ) : (
                                                <>
                                                    <CheckCircle
                                                        className="mr-2 h-4 w-4"
                                                        aria-hidden="true"
                                                    />
                                                    Approve
                                                </>
                                            )}
                                        </Button>
                                        <Button
                                            onClick={() => {
                                                if (
                                                    vettingAction === 'reject'
                                                ) {
                                                    handleVet('reject');
                                                } else {
                                                    setVettingAction('reject');
                                                }
                                            }}
                                            disabled={processing}
                                            variant="destructive"
                                            className="flex-1"
                                            aria-label={
                                                vettingAction === 'reject'
                                                    ? 'Confirm rejection'
                                                    : 'Reject claim'
                                            }
                                        >
                                            {processing ? (
                                                <>
                                                    <Loader2
                                                        className="mr-2 h-4 w-4 animate-spin"
                                                        aria-hidden="true"
                                                    />
                                                    Processing...
                                                </>
                                            ) : (
                                                <>
                                                    <XCircle
                                                        className="mr-2 h-4 w-4"
                                                        aria-hidden="true"
                                                    />
                                                    {vettingAction === 'reject'
                                                        ? 'Confirm Reject'
                                                        : 'Reject'}
                                                </>
                                            )}
                                        </Button>
                                    </div>

                                    {vettingAction === 'reject' && (
                                        <Button
                                            onClick={() => {
                                                setVettingAction(null);
                                                setRejectionReason('');
                                            }}
                                            variant="outline"
                                            className="w-full"
                                        >
                                            Cancel Rejection
                                        </Button>
                                    )}

                                    <div
                                        className="rounded-lg bg-blue-50 p-3 dark:bg-blue-950"
                                        role="note"
                                        aria-label="Keyboard shortcuts information"
                                    >
                                        <div className="flex items-start gap-2">
                                            <AlertCircle
                                                className="mt-0.5 h-4 w-4 text-blue-600 dark:text-blue-400"
                                                aria-hidden="true"
                                            />
                                            <div className="text-sm text-blue-900 dark:text-blue-100">
                                                <strong>
                                                    Keyboard shortcuts:
                                                </strong>{' '}
                                                Press{' '}
                                                <kbd className="rounded bg-blue-100 px-1.5 py-0.5 font-mono text-xs dark:bg-blue-900">
                                                    Esc
                                                </kbd>{' '}
                                                to close,{' '}
                                                <kbd className="rounded bg-blue-100 px-1.5 py-0.5 font-mono text-xs dark:bg-blue-900">
                                                    Ctrl+Enter
                                                </kbd>{' '}
                                                to approve
                                            </div>
                                        </div>
                                    </div>
                                </section>
                            )}

                            {claim.status !== 'pending_vetting' && (
                                <div
                                    className="rounded-lg bg-gray-50 p-4 dark:bg-gray-900"
                                    role="alert"
                                    aria-live="polite"
                                >
                                    <div className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                        <AlertCircle
                                            className="h-4 w-4"
                                            aria-hidden="true"
                                        />
                                        This claim has already been vetted and
                                        cannot be modified.
                                    </div>
                                </div>
                            )}
                        </div>
                    </>
                ) : (
                    <div
                        className="flex h-full items-center justify-center"
                        role="alert"
                        aria-live="assertive"
                    >
                        <div className="text-center">
                            <AlertCircle
                                className="mx-auto mb-4 h-12 w-12 text-gray-400"
                                aria-hidden="true"
                            />
                            <p className="text-gray-600 dark:text-gray-400">
                                Failed to load claim details
                            </p>
                        </div>
                    </div>
                )}
            </SheetContent>
        </Sheet>
    );
}
