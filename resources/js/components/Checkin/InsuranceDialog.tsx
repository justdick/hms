import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useNhisExtension } from '@/hooks/useNhisExtension';
import { router } from '@inertiajs/react';
import {
    AlertTriangle,
    Building2,
    CheckCircle2,
    CreditCard,
    ExternalLink,
    Loader2,
    RefreshCw,
    Shield,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

interface InsuranceInfo {
    id: number;
    membership_id: string;
    policy_number: string | null;
    plan: {
        id: number;
        plan_name: string;
        plan_code: string;
        provider: {
            id: number;
            name: string;
            code: string;
            is_nhis?: boolean;
        };
    };
    coverage_start_date: string;
    coverage_end_date: string | null;
    is_expired?: boolean;
}

interface NhisSettings {
    verification_mode: 'manual' | 'extension';
    nhia_portal_url: string;
    auto_open_portal: boolean;
    credentials?: {
        username: string;
        password: string;
    } | null;
}

interface InsuranceDialogProps {
    open: boolean;
    onClose: () => void;
    insurance: InsuranceInfo;
    onUseCash: () => void;
    onUseInsurance: (claimCheckCode: string) => void;
    nhisSettings?: NhisSettings;
}

/**
 * Parse NHIS date format (could be DD-MM-YYYY or YYYY-MM-DD)
 */
function parseNhisDate(dateStr: string | null | undefined): Date | null {
    if (!dateStr) return null;

    // Try DD-MM-YYYY format first (common NHIS format)
    const ddmmyyyy = dateStr.match(/^(\d{2})-(\d{2})-(\d{4})$/);
    if (ddmmyyyy) {
        return new Date(`${ddmmyyyy[3]}-${ddmmyyyy[2]}-${ddmmyyyy[1]}`);
    }

    // Try YYYY-MM-DD format
    const yyyymmdd = dateStr.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (yyyymmdd) {
        return new Date(dateStr);
    }

    // Fallback to Date parsing
    const parsed = new Date(dateStr);
    return isNaN(parsed.getTime()) ? null : parsed;
}

/**
 * Format date to YYYY-MM-DD for backend
 */
function formatDateForBackend(date: Date): string {
    return date.toISOString().split('T')[0];
}

/**
 * Check if a date is in the past (expired)
 */
function isDateExpired(date: Date | null): boolean {
    if (!date) return false;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return date < today;
}

/**
 * Check if two dates are different (ignoring time)
 */
function datesAreDifferent(date1: Date | null, date2: string | null): boolean {
    if (!date1 && !date2) return false;
    if (!date1 || !date2) return true;

    const d1 = formatDateForBackend(date1);
    const d2 = date2.split('T')[0]; // Handle ISO format
    return d1 !== d2;
}

export default function InsuranceDialog({
    open,
    onClose,
    insurance,
    onUseCash,
    onUseInsurance,
    nhisSettings,
}: InsuranceDialogProps) {
    const [claimCheckCode, setClaimCheckCode] = useState('');
    const [error, setError] = useState('');
    const [isSyncingDates, setIsSyncingDates] = useState(false);

    // NHIS Extension hook
    const { isVerifying, cccData, startVerification, clearCccData } =
        useNhisExtension();

    // Check if this is an NHIS provider
    const isNhisProvider = insurance.plan.provider.is_nhis ?? false;

    // Parse dates from NHIS verification
    const nhisStartDate = parseNhisDate(cccData?.coverageStart);
    const nhisEndDate = parseNhisDate(cccData?.coverageEnd);

    // Check if NHIS says coverage is INACTIVE or expired
    const isInactiveFromNhis =
        cccData?.status === 'INACTIVE' || cccData?.error === 'INACTIVE';
    const isExpiredFromNhis = nhisEndDate ? isDateExpired(nhisEndDate) : false;

    // Membership is unusable if INACTIVE or expired
    const isNhisUnusable = isInactiveFromNhis || isExpiredFromNhis;

    // Use stored expiry status if no NHIS data, otherwise use NHIS data
    const isExpired = cccData
        ? isNhisUnusable
        : (insurance.is_expired ?? false);
    const canUseInsurance = !isExpired;

    // Check if dates need syncing (even for INACTIVE, we want to update dates)
    const startDateChanged =
        nhisStartDate &&
        datesAreDifferent(nhisStartDate, insurance.coverage_start_date);
    const endDateChanged =
        nhisEndDate &&
        datesAreDifferent(nhisEndDate, insurance.coverage_end_date);
    const needsDateSync = startDateChanged || endDateChanged;

    // Determine verification mode
    const verificationMode = nhisSettings?.verification_mode ?? 'manual';
    const isExtensionMode = verificationMode === 'extension' && isNhisProvider;

    // Auto-fill CCC when received from extension (only if not INACTIVE)
    useEffect(() => {
        if (cccData?.ccc && !isInactiveFromNhis) {
            setClaimCheckCode(cccData.ccc);
            setError('');
        }
    }, [cccData, isInactiveFromNhis]);

    // Auto-sync dates when they differ (even for INACTIVE memberships)
    useEffect(() => {
        if (
            cccData &&
            needsDateSync &&
            nhisStartDate &&
            nhisEndDate &&
            !isSyncingDates
        ) {
            syncInsuranceDates();
        }
    }, [cccData, needsDateSync]);

    // Clear state when dialog closes
    useEffect(() => {
        if (!open) {
            setClaimCheckCode('');
            setError('');
            setIsSyncingDates(false);
            clearCccData();
        }
    }, [open, clearCccData]);

    const syncInsuranceDates = () => {
        if (!nhisStartDate || !nhisEndDate) return;

        setIsSyncingDates(true);

        router.patch(
            `/patient-insurance/${insurance.id}/sync-dates`,
            {
                coverage_start_date: formatDateForBackend(nhisStartDate),
                coverage_end_date: formatDateForBackend(nhisEndDate),
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    toast.success('Insurance dates updated from NHIS', {
                        description: `Coverage: ${formatDateForBackend(nhisStartDate)} to ${formatDateForBackend(nhisEndDate)}`,
                    });
                    setIsSyncingDates(false);
                },
                onError: () => {
                    toast.error('Failed to update insurance dates');
                    setIsSyncingDates(false);
                },
            },
        );
    };

    const handleVerifyNhis = () => {
        if (!insurance.membership_id) {
            setError('No NHIS membership number found');
            return;
        }

        // Copy membership number to clipboard for manual paste
        navigator.clipboard.writeText(insurance.membership_id).catch(() => {
            // Clipboard API might fail, continue anyway
        });

        // Start verification (opens portal, extension will auto-fill and login)
        startVerification(
            insurance.membership_id,
            nhisSettings?.credentials || undefined,
            nhisSettings?.nhia_portal_url,
        );
    };

    const handleUseInsurance = () => {
        if (!claimCheckCode.trim()) {
            setError('Claim Check Code (CCC) is required');
            return;
        }

        if (claimCheckCode.length > 50) {
            setError('Claim Check Code cannot exceed 50 characters');
            return;
        }

        setError('');
        onUseInsurance(claimCheckCode.trim());
    };

    const handleUseCash = () => {
        setClaimCheckCode('');
        setError('');
        onUseCash();
    };

    const handleModalClose = () => {
        setClaimCheckCode('');
        setError('');
        clearCccData();
        onClose();
    };

    return (
        <Dialog open={open} onOpenChange={handleModalClose}>
            <DialogContent className="max-h-[90vh] max-w-lg overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-base">
                        <Shield className="h-4 w-4 text-primary" />
                        Insurance Check-in
                    </DialogTitle>
                </DialogHeader>

                <div className="space-y-4">
                    {/* Insurance Information Display */}
                    <div className="rounded-lg border bg-muted/50 p-3">
                        <h3 className="mb-2 flex items-center gap-2 text-sm font-medium">
                            <Building2 className="h-4 w-4" />
                            Insurance Details
                        </h3>
                        <div className="grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                            <div>
                                <span className="text-muted-foreground">
                                    Provider:{' '}
                                </span>
                                <span className="font-medium">
                                    {insurance.plan.provider.name}
                                </span>
                            </div>
                            <div>
                                <span className="text-muted-foreground">
                                    Plan:{' '}
                                </span>
                                <span className="font-medium">
                                    {insurance.plan.plan_name}
                                </span>
                            </div>
                            <div>
                                <span className="text-muted-foreground">
                                    ID:{' '}
                                </span>
                                <span className="font-mono font-medium">
                                    {insurance.membership_id}
                                </span>
                            </div>
                            <div>
                                <span className="text-muted-foreground">
                                    Coverage End:{' '}
                                </span>
                                <span className="font-medium">
                                    {insurance.coverage_end_date
                                        ? new Date(
                                              insurance.coverage_end_date,
                                          ).toLocaleDateString()
                                        : 'N/A'}
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* Expired Insurance Warning - from stored data */}
                    {!cccData && isExpired && (
                        <div className="rounded-lg border border-amber-500/50 bg-amber-50 p-2 dark:bg-amber-950/20">
                            <p className="text-xs font-medium text-amber-700 dark:text-amber-400">
                                ⚠️ Coverage expired - please renew to use
                                insurance
                            </p>
                        </div>
                    )}

                    {/* CCC Verification Section */}
                    <div
                        className={`space-y-3 rounded-lg border p-3 ${!canUseInsurance ? 'bg-muted opacity-60' : 'bg-primary/5'}`}
                    >
                        <div className="flex items-center gap-2">
                            <CreditCard className="h-4 w-4 text-primary" />
                            <h4 className="text-sm font-medium">
                                Use {isNhisProvider ? 'NHIS' : 'Insurance'} for
                                this Visit
                            </h4>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            {isExtensionMode
                                ? 'Click "Verify NHIS" to automatically get the CCC from the NHIA portal.'
                                : 'Enter the Claim Check Code (CCC) to process this visit under insurance coverage.'}
                        </p>

                        {/* Extension Mode: Verify Button */}
                        {isExtensionMode && isNhisProvider && (
                            <>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={handleVerifyNhis}
                                    disabled={isVerifying}
                                    className="w-full"
                                >
                                    {isVerifying ? (
                                        <>
                                            <Loader2 className="mr-2 h-3 w-3 animate-spin" />
                                            Verifying...
                                        </>
                                    ) : (
                                        <>
                                            <ExternalLink className="mr-2 h-3 w-3" />
                                            Verify NHIS Membership
                                        </>
                                    )}
                                </Button>

                                {/* Verification Result */}
                                {cccData && (
                                    <div
                                        className={`rounded-md p-2 ${
                                            isNhisUnusable
                                                ? 'border border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950/20'
                                                : 'bg-green-50 dark:bg-green-950/20'
                                        }`}
                                    >
                                        <div
                                            className={`flex items-center gap-2 ${
                                                isNhisUnusable
                                                    ? 'text-red-700 dark:text-red-400'
                                                    : 'text-green-700 dark:text-green-400'
                                            }`}
                                        >
                                            {isNhisUnusable ? (
                                                <AlertTriangle className="h-3 w-3" />
                                            ) : (
                                                <CheckCircle2 className="h-3 w-3" />
                                            )}
                                            <span className="text-xs font-medium">
                                                {isInactiveFromNhis
                                                    ? 'INACTIVE'
                                                    : isExpiredFromNhis
                                                      ? 'EXPIRED'
                                                      : 'Verified'}
                                                :{' '}
                                                {cccData.memberName || 'Member'}
                                            </span>
                                        </div>
                                        <p
                                            className={`text-xs ${
                                                isNhisUnusable
                                                    ? 'text-red-600 dark:text-red-500'
                                                    : 'text-green-600 dark:text-green-500'
                                            }`}
                                        >
                                            {cccData.status}
                                            {cccData.coverageStart &&
                                            cccData.coverageEnd
                                                ? ` • ${cccData.coverageStart} to ${cccData.coverageEnd}`
                                                : ''}
                                        </p>

                                        {/* Date sync indicator */}
                                        {needsDateSync && (
                                            <div className="mt-1 flex items-center gap-1 text-xs text-blue-600 dark:text-blue-400">
                                                {isSyncingDates ? (
                                                    <>
                                                        <Loader2 className="h-3 w-3 animate-spin" />
                                                        <span>
                                                            Updating dates...
                                                        </span>
                                                    </>
                                                ) : (
                                                    <>
                                                        <RefreshCw className="h-3 w-3" />
                                                        <span>
                                                            Dates updated from
                                                            NHIS
                                                        </span>
                                                    </>
                                                )}
                                            </div>
                                        )}

                                        {/* INACTIVE or Expired warning message */}
                                        {isNhisUnusable && (
                                            <p className="mt-2 text-xs font-medium text-red-700 dark:text-red-400">
                                                ⚠️{' '}
                                                {isInactiveFromNhis
                                                    ? 'Membership is INACTIVE. Patient must renew NHIS membership to use insurance.'
                                                    : 'Coverage has expired. Patient must renew NHIS membership to use insurance.'}
                                            </p>
                                        )}
                                    </div>
                                )}
                            </>
                        )}

                        {/* CCC Input Field */}
                        <div className="space-y-1">
                            <Label
                                htmlFor="claim_check_code"
                                className="text-xs"
                            >
                                Claim Check Code (CCC) *
                            </Label>
                            <Input
                                id="claim_check_code"
                                type="text"
                                placeholder={
                                    isExtensionMode
                                        ? 'Auto-fills after verification...'
                                        : 'Enter CCC...'
                                }
                                value={claimCheckCode}
                                onChange={(e) => {
                                    setClaimCheckCode(e.target.value);
                                    setError('');
                                }}
                                maxLength={50}
                                disabled={!canUseInsurance}
                                className={`h-9 ${
                                    error
                                        ? 'border-destructive'
                                        : cccData?.ccc && !isExpiredFromNhis
                                          ? 'border-green-500 bg-green-50 dark:bg-green-950/20'
                                          : ''
                                }`}
                            />
                            {error && (
                                <p className="text-xs text-destructive">
                                    {error}
                                </p>
                            )}
                        </div>

                        <Button
                            onClick={handleUseInsurance}
                            size="sm"
                            className="w-full"
                            disabled={
                                !canUseInsurance || !claimCheckCode.trim()
                            }
                        >
                            <Shield className="mr-2 h-3 w-3" />
                            Check-in with{' '}
                            {isNhisProvider ? 'NHIS' : 'Insurance'}
                        </Button>
                    </div>

                    {/* Cash Payment Option */}
                    <div className="rounded-lg border p-3">
                        <h4 className="text-sm font-medium">Or Pay Cash</h4>
                        <p className="mb-2 text-xs text-muted-foreground">
                            Patient pays out-of-pocket instead.
                        </p>
                        <Button
                            onClick={handleUseCash}
                            variant="outline"
                            size="sm"
                            className="w-full"
                        >
                            Proceed without Insurance
                        </Button>
                    </div>

                    {/* Cancel Option */}
                    <div className="flex justify-end">
                        <Button
                            onClick={handleModalClose}
                            variant="ghost"
                            size="sm"
                        >
                            Cancel
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
