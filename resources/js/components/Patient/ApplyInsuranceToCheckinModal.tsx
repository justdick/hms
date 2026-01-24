import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useNhisExtension } from '@/hooks/useNhisExtension';
import { copyToClipboard } from '@/lib/utils';
import { router } from '@inertiajs/react';
import {
    AlertTriangle,
    Building2,
    CheckCircle2,
    ExternalLink,
    Loader2,
    Shield,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

interface ActiveCheckin {
    id: number;
    checked_in_at: string;
    department: {
        id: number;
        name: string;
    };
    status: string;
    is_admitted: boolean;
}

interface PatientInsurance {
    id: number;
    membership_id: string;
    insurance_plan?: {
        id: number;
        name: string;
    };
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

interface Props {
    open: boolean;
    onClose: () => void;
    checkin: ActiveCheckin;
    insurance: PatientInsurance;
    nhisSettings?: NhisSettings;
}

export default function ApplyInsuranceToCheckinModal({
    open,
    onClose,
    checkin,
    insurance,
    nhisSettings,
}: Props) {
    const [claimCheckCode, setClaimCheckCode] = useState('');
    const [error, setError] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);

    // NHIS Extension hook
    const { isVerifying, cccData, startVerification, clearCccData } =
        useNhisExtension();

    const verificationMode = nhisSettings?.verification_mode ?? 'manual';
    const isExtensionMode = verificationMode === 'extension';

    // Check if NHIS says coverage is INACTIVE or expired
    const isInactiveFromNhis =
        cccData?.status === 'INACTIVE' || cccData?.error === 'INACTIVE';

    // Auto-fill CCC when received from extension
    useEffect(() => {
        if (cccData?.ccc && !isInactiveFromNhis) {
            setClaimCheckCode(cccData.ccc);
            setError('');
        }
    }, [cccData, isInactiveFromNhis]);

    // Clear state when dialog closes
    useEffect(() => {
        if (!open) {
            setClaimCheckCode('');
            setError('');
            setIsSubmitting(false);
            clearCccData();
        }
    }, [open, clearCccData]);

    const handleVerifyNhis = () => {
        if (!insurance.membership_id) {
            setError('No NHIS membership number found');
            return;
        }

        // Copy membership number to clipboard
        copyToClipboard(insurance.membership_id);

        // Start verification
        startVerification(
            insurance.membership_id,
            nhisSettings?.credentials || undefined,
            nhisSettings?.nhia_portal_url,
        );
    };

    const handleApplyInsurance = () => {
        if (!claimCheckCode.trim()) {
            setError('Claim Check Code (CCC) is required');
            return;
        }

        if (claimCheckCode.length > 50) {
            setError('Claim Check Code cannot exceed 50 characters');
            return;
        }

        if (isSubmitting) return;

        setError('');
        setIsSubmitting(true);

        router.post(
            `/checkin/checkins/${checkin.id}/apply-insurance`,
            { claim_check_code: claimCheckCode.trim() },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Insurance applied successfully', {
                        description: 'Coverage has been applied to all pending charges.',
                    });
                    onClose();
                },
                onError: (errors) => {
                    setError(
                        errors.claim_check_code ||
                            'Failed to apply insurance. Please try again.',
                    );
                    setIsSubmitting(false);
                },
                onFinish: () => {
                    setIsSubmitting(false);
                },
            },
        );
    };

    const handleSkip = () => {
        onClose();
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Shield className="h-5 w-5 text-primary" />
                        Apply Insurance to Active Check-in
                    </DialogTitle>
                    <DialogDescription>
                        This patient has an active check-in that can have
                        insurance coverage applied.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    {/* Check-in Info */}
                    <div className="rounded-lg border bg-muted/50 p-3">
                        <h4 className="mb-2 flex items-center gap-2 text-sm font-medium">
                            <Building2 className="h-4 w-4" />
                            Active Check-in
                        </h4>
                        <div className="grid grid-cols-2 gap-2 text-xs">
                            <div>
                                <span className="text-muted-foreground">
                                    Department:{' '}
                                </span>
                                <span className="font-medium">
                                    {checkin.department.name}
                                </span>
                            </div>
                            <div>
                                <span className="text-muted-foreground">
                                    Status:{' '}
                                </span>
                                <span className="font-medium capitalize">
                                    {checkin.status.replace(/_/g, ' ')}
                                </span>
                            </div>
                            <div>
                                <span className="text-muted-foreground">
                                    Checked in:{' '}
                                </span>
                                <span className="font-medium">
                                    {checkin.checked_in_at}
                                </span>
                            </div>
                            <div>
                                <span className="text-muted-foreground">
                                    Membership ID:{' '}
                                </span>
                                <span className="font-mono font-medium">
                                    {insurance.membership_id}
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* CCC Verification Section */}
                    <div className="space-y-3 rounded-lg border bg-primary/5 p-3">
                        <p className="text-xs text-muted-foreground">
                            {isExtensionMode
                                ? 'Click "Verify NHIS" to get the CCC from the NHIA portal.'
                                : 'Enter the Claim Check Code (CCC) from the NHIS portal.'}
                        </p>

                        {/* Extension Mode: Verify Button */}
                        {isExtensionMode && (
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
                                            isInactiveFromNhis
                                                ? 'border border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950/20'
                                                : 'bg-green-50 dark:bg-green-950/20'
                                        }`}
                                    >
                                        <div
                                            className={`flex items-center gap-2 ${
                                                isInactiveFromNhis
                                                    ? 'text-red-700 dark:text-red-400'
                                                    : 'text-green-700 dark:text-green-400'
                                            }`}
                                        >
                                            {isInactiveFromNhis ? (
                                                <AlertTriangle className="h-3 w-3" />
                                            ) : (
                                                <CheckCircle2 className="h-3 w-3" />
                                            )}
                                            <span className="text-xs font-medium">
                                                {isInactiveFromNhis
                                                    ? 'INACTIVE'
                                                    : 'Verified'}
                                                :{' '}
                                                {cccData.memberName || 'Member'}
                                            </span>
                                        </div>
                                        {isInactiveFromNhis && (
                                            <p className="mt-1 text-xs text-red-600">
                                                Membership is INACTIVE. Patient
                                                must renew to use insurance.
                                            </p>
                                        )}
                                    </div>
                                )}
                            </>
                        )}

                        {/* CCC Input */}
                        <div className="space-y-1">
                            <Label htmlFor="ccc" className="text-xs">
                                Claim Check Code (CCC) *
                            </Label>
                            <Input
                                id="ccc"
                                type="text"
                                placeholder={
                                    isExtensionMode
                                        ? 'Auto-fills after verification...'
                                        : 'Enter CCC from NHIS portal...'
                                }
                                value={claimCheckCode}
                                onChange={(e) => {
                                    setClaimCheckCode(e.target.value);
                                    setError('');
                                }}
                                maxLength={50}
                                className={`h-9 ${
                                    error
                                        ? 'border-destructive'
                                        : cccData?.ccc && !isInactiveFromNhis
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
                    </div>

                    {/* Actions */}
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            onClick={handleSkip}
                            disabled={isSubmitting}
                            className="flex-1"
                        >
                            Skip for Now
                        </Button>
                        <Button
                            onClick={handleApplyInsurance}
                            disabled={
                                !claimCheckCode.trim() ||
                                isSubmitting ||
                                isInactiveFromNhis
                            }
                            className="flex-1"
                        >
                            {isSubmitting ? (
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            ) : (
                                <Shield className="mr-2 h-4 w-4" />
                            )}
                            Apply Insurance
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
