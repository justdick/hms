import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import { router } from '@inertiajs/react';
import {
    AlertCircle,
    CheckCircle,
    FileText,
    Loader2,
    XCircle,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { AttendanceDetailsSection } from './AttendanceDetailsSection';
import { ClaimItemsTabs } from './ClaimItemsTabs';
import { ClaimTotalDisplay } from './ClaimTotalDisplay';
import { DiagnosesManager } from './DiagnosesManager';
import { GdrgSelector } from './GdrgSelector';
import { PatientInfoSection } from './PatientInfoSection';
import type {
    Diagnosis,
    GdrgTariff,
    VettingData,
    VettingModalProps,
} from './types';

/**
 * VettingModal - Modal overlay for reviewing and vetting NHIS insurance claims
 *
 * Features:
 * - Modal interface for claim vetting without page navigation
 * - Displays patient info, attendance details, diagnoses, and claim items
 * - G-DRG selection for NHIS claims (required for approval)
 * - Diagnosis management (add/remove)
 * - Approve/reject actions with validation
 * - Keyboard shortcuts (Escape to close, Ctrl+Enter to approve)
 *
 * @example
 * ```tsx
 * <VettingModal
 *   claimId={selectedClaimId}
 *   isOpen={isModalOpen}
 *   onClose={() => setIsModalOpen(false)}
 *   onVetSuccess={refreshClaimsList}
 * />
 * ```
 */
export function VettingModal({
    claimId,
    isOpen,
    onClose,
    onVetSuccess,
}: VettingModalProps) {
    const [vettingData, setVettingData] = useState<VettingData | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);
    const [showRejectForm, setShowRejectForm] = useState(false);
    const [rejectionReason, setRejectionReason] = useState('');

    // Local state for editable fields
    const [selectedGdrg, setSelectedGdrg] = useState<GdrgTariff | null>(null);
    const [diagnoses, setDiagnoses] = useState<Diagnosis[]>([]);

    // Focus management
    const approveButtonRef = useRef<HTMLButtonElement>(null);
    const previousFocusRef = useRef<HTMLElement | null>(null);

    // Fetch vetting data when modal opens
    useEffect(() => {
        if (claimId && isOpen) {
            setLoading(true);
            setError(null);

            fetch(`/admin/insurance/claims/${claimId}/vetting-data`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Failed to load claim data');
                    }
                    return response.json();
                })
                .then((data: VettingData) => {
                    setVettingData(data);
                    setDiagnoses(data.diagnoses);
                    // Set initial G-DRG if already selected
                    if (data.claim.gdrg_tariff_id) {
                        const existingGdrg = data.gdrg_tariffs.find(
                            (t) => t.id === data.claim.gdrg_tariff_id,
                        );
                        setSelectedGdrg(existingGdrg || null);
                    }
                    setLoading(false);
                })
                .catch((err) => {
                    console.error('Failed to load vetting data:', err);
                    setError('Failed to load claim details. Please try again.');
                    setLoading(false);
                });
        }
    }, [claimId, isOpen]);

    // Reset state when modal closes
    useEffect(() => {
        if (!isOpen) {
            setVettingData(null);
            setSelectedGdrg(null);
            setDiagnoses([]);
            setShowRejectForm(false);
            setRejectionReason('');
            setError(null);
        }
    }, [isOpen]);

    // Focus management
    useEffect(() => {
        if (isOpen) {
            previousFocusRef.current = document.activeElement as HTMLElement;
            setTimeout(() => {
                approveButtonRef.current?.focus();
            }, 100);
        } else if (previousFocusRef.current) {
            previousFocusRef.current.focus();
        }
    }, [isOpen, vettingData]);

    // Keyboard shortcuts
    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if (!isOpen || !vettingData) return;

            if (e.key === 'Escape') {
                onClose();
            }

            if (e.ctrlKey && e.key === 'Enter') {
                e.preventDefault();
                handleApprove();
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [isOpen, vettingData, selectedGdrg, diagnoses]);

    // Calculate updated totals when G-DRG changes
    const calculateTotals = useCallback(() => {
        if (!vettingData) return vettingData?.totals;

        const gdrgAmount = selectedGdrg?.tariff_price || 0;
        const grandTotal =
            gdrgAmount +
            vettingData.totals.investigations +
            vettingData.totals.prescriptions +
            vettingData.totals.procedures;

        return {
            ...vettingData.totals,
            gdrg: gdrgAmount,
            grand_total: grandTotal,
        };
    }, [vettingData, selectedGdrg]);

    const handleApprove = () => {
        if (!vettingData) return;

        // Validate G-DRG required for NHIS claims
        if (vettingData.is_nhis && !selectedGdrg) {
            setError('G-DRG selection is required for NHIS claims.');
            return;
        }

        setProcessing(true);
        setError(null);

        router.post(
            `/admin/insurance/claims/${vettingData.claim.id}/vet`,
            {
                action: 'approve',
                gdrg_tariff_id: selectedGdrg?.id || null,
                diagnoses: diagnoses.map((d) => ({
                    diagnosis_id: d.diagnosis_id,
                    is_primary: d.is_primary,
                })),
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
                    const errorMessage =
                        Object.values(errors).flat().join(', ') ||
                        'Failed to approve claim.';
                    setError(errorMessage);
                },
            },
        );
    };

    const handleReject = () => {
        if (!vettingData) return;

        if (!rejectionReason.trim()) {
            setError('Please provide a rejection reason.');
            return;
        }

        setProcessing(true);
        setError(null);

        router.post(
            `/admin/insurance/claims/${vettingData.claim.id}/vet`,
            {
                action: 'reject',
                rejection_reason: rejectionReason,
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
                    const errorMessage =
                        Object.values(errors).flat().join(', ') ||
                        'Failed to reject claim.';
                    setError(errorMessage);
                },
            },
        );
    };

    const handleDiagnosesChange = (newDiagnoses: Diagnosis[]) => {
        setDiagnoses(newDiagnoses);
    };

    const updatedTotals = calculateTotals();

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent
                className="max-h-[90vh] max-w-4xl overflow-hidden p-0"
                aria-labelledby="vetting-modal-title"
                aria-describedby="vetting-modal-description"
            >
                {loading ? (
                    <div
                        className="flex h-96 items-center justify-center"
                        role="status"
                        aria-live="polite"
                        aria-busy="true"
                    >
                        <Loader2 className="h-8 w-8 animate-spin text-gray-400" />
                        <span className="sr-only">
                            Loading claim details...
                        </span>
                    </div>
                ) : error && !vettingData ? (
                    <div
                        className="flex h-96 flex-col items-center justify-center gap-4"
                        role="alert"
                    >
                        <AlertCircle className="h-12 w-12 text-red-500" />
                        <p className="text-gray-600 dark:text-gray-400">
                            {error}
                        </p>
                        <Button variant="outline" onClick={onClose}>
                            Close
                        </Button>
                    </div>
                ) : vettingData ? (
                    <>
                        <DialogHeader className="border-b px-6 py-4">
                            <DialogTitle
                                id="vetting-modal-title"
                                className="flex items-center gap-2"
                            >
                                <FileText
                                    className="h-5 w-5"
                                    aria-hidden="true"
                                />
                                Claim Vetting -{' '}
                                {vettingData.attendance.claim_check_code}
                            </DialogTitle>
                            <DialogDescription id="vetting-modal-description">
                                Review claim details and approve or reject for
                                submission
                            </DialogDescription>
                        </DialogHeader>

                        <ScrollArea className="max-h-[calc(90vh-200px)]">
                            <div className="space-y-6 p-6">
                                {/* Error Alert */}
                                {error && (
                                    <div
                                        className="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200"
                                        role="alert"
                                    >
                                        <AlertCircle className="h-4 w-4 shrink-0" />
                                        {error}
                                    </div>
                                )}

                                {/* Patient Information */}
                                <PatientInfoSection
                                    patient={vettingData.patient}
                                />

                                <Separator />

                                {/* Attendance Details */}
                                <AttendanceDetailsSection
                                    attendance={vettingData.attendance}
                                />

                                <Separator />

                                {/* G-DRG Selection (NHIS only) */}
                                {vettingData.is_nhis && (
                                    <>
                                        <GdrgSelector
                                            value={selectedGdrg}
                                            onChange={setSelectedGdrg}
                                            tariffs={vettingData.gdrg_tariffs}
                                            disabled={processing}
                                        />
                                        <Separator />
                                    </>
                                )}

                                {/* Diagnoses */}
                                <DiagnosesManager
                                    diagnoses={diagnoses}
                                    onChange={handleDiagnosesChange}
                                    disabled={processing}
                                />

                                <Separator />

                                {/* Claim Items */}
                                <ClaimItemsTabs
                                    items={vettingData.items}
                                    isNhis={vettingData.is_nhis}
                                />

                                <Separator />

                                {/* Claim Total */}
                                {updatedTotals && (
                                    <ClaimTotalDisplay
                                        totals={updatedTotals}
                                        isNhis={vettingData.is_nhis}
                                    />
                                )}

                                {/* Rejection Form */}
                                {showRejectForm && (
                                    <div className="space-y-2">
                                        <label
                                            htmlFor="rejection_reason"
                                            className="text-sm font-medium"
                                        >
                                            Rejection Reason *
                                        </label>
                                        <textarea
                                            id="rejection_reason"
                                            className="w-full rounded-md border border-gray-300 p-3 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-900"
                                            placeholder="Provide a reason for rejecting this claim..."
                                            value={rejectionReason}
                                            onChange={(e) =>
                                                setRejectionReason(
                                                    e.target.value,
                                                )
                                            }
                                            rows={3}
                                        />
                                    </div>
                                )}
                            </div>
                        </ScrollArea>

                        <DialogFooter className="border-t px-6 py-4">
                            <div className="flex w-full flex-col gap-3 sm:flex-row sm:justify-between">
                                <div className="text-xs text-gray-500 dark:text-gray-400">
                                    <kbd className="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800">
                                        Esc
                                    </kbd>{' '}
                                    to close,{' '}
                                    <kbd className="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800">
                                        Ctrl+Enter
                                    </kbd>{' '}
                                    to approve
                                </div>
                                <div className="flex gap-2">
                                    {showRejectForm ? (
                                        <>
                                            <Button
                                                variant="outline"
                                                onClick={() => {
                                                    setShowRejectForm(false);
                                                    setRejectionReason('');
                                                }}
                                                disabled={processing}
                                            >
                                                Cancel
                                            </Button>
                                            <Button
                                                variant="destructive"
                                                onClick={handleReject}
                                                disabled={processing}
                                            >
                                                {processing ? (
                                                    <>
                                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                        Rejecting...
                                                    </>
                                                ) : (
                                                    <>
                                                        <XCircle className="mr-2 h-4 w-4" />
                                                        Confirm Reject
                                                    </>
                                                )}
                                            </Button>
                                        </>
                                    ) : (
                                        <>
                                            <Button
                                                variant="outline"
                                                onClick={() =>
                                                    setShowRejectForm(true)
                                                }
                                                disabled={processing}
                                            >
                                                <XCircle className="mr-2 h-4 w-4" />
                                                Reject
                                            </Button>
                                            <Button
                                                ref={approveButtonRef}
                                                onClick={handleApprove}
                                                disabled={processing}
                                                className="bg-green-600 hover:bg-green-700"
                                            >
                                                {processing ? (
                                                    <>
                                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                        Approving...
                                                    </>
                                                ) : (
                                                    <>
                                                        <CheckCircle className="mr-2 h-4 w-4" />
                                                        Approve Claim
                                                    </>
                                                )}
                                            </Button>
                                        </>
                                    )}
                                </div>
                            </div>
                        </DialogFooter>
                    </>
                ) : null}
            </DialogContent>
        </Dialog>
    );
}
