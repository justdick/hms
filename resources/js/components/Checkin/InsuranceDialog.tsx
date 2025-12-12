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
import {
    Building2,
    CheckCircle2,
    CreditCard,
    ExternalLink,
    Loader2,
    Shield,
} from 'lucide-react';
import { useEffect, useState } from 'react';

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
    
    // NHIS Extension hook
    const { isVerifying, cccData, startVerification, clearCccData } = useNhisExtension();

    // Check if this is an NHIS provider and if coverage is valid (not expired)
    const isNhisProvider = insurance.plan.provider.is_nhis ?? false;
    const isExpired = insurance.is_expired ?? false;
    const canUseInsurance = !isExpired;
    
    // Determine verification mode
    const verificationMode = nhisSettings?.verification_mode ?? 'manual';
    const isExtensionMode = verificationMode === 'extension' && isNhisProvider;

    // Auto-fill CCC when received from extension
    useEffect(() => {
        if (cccData?.ccc) {
            setClaimCheckCode(cccData.ccc);
            setError('');
        }
    }, [cccData]);

    // Clear state when dialog closes
    useEffect(() => {
        if (!open) {
            setClaimCheckCode('');
            setError('');
            clearCccData();
        }
    }, [open, clearCccData]);

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
        startVerification(insurance.membership_id, nhisSettings?.credentials || undefined, nhisSettings?.nhia_portal_url);
    };

    const handleOpenPortalManual = () => {
        // Copy membership number to clipboard
        navigator.clipboard.writeText(insurance.membership_id).catch(() => {});
        
        // Open portal
        window.open(nhisSettings?.nhia_portal_url || 'https://ccc.nhia.gov.gh/', '_blank');
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
            <DialogContent className="max-w-lg max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-base">
                        <Shield className="h-4 w-4 text-primary" />
                        Insurance Check-in
                    </DialogTitle>
                </DialogHeader>

                <div className="space-y-4">
                    {/* Insurance Information Display */}
                    <div className="rounded-lg border bg-muted/50 p-3">
                        <h3 className="flex items-center gap-2 text-sm font-medium mb-2">
                            <Building2 className="h-4 w-4" />
                            Insurance Details
                        </h3>
                        <div className="grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                            <div>
                                <span className="text-muted-foreground">Provider: </span>
                                <span className="font-medium">{insurance.plan.provider.name}</span>
                            </div>
                            <div>
                                <span className="text-muted-foreground">Plan: </span>
                                <span className="font-medium">{insurance.plan.plan_name}</span>
                            </div>
                            <div>
                                <span className="text-muted-foreground">ID: </span>
                                <span className="font-medium font-mono">{insurance.membership_id}</span>
                            </div>
                            <div>
                                <span className="text-muted-foreground">Coverage End: </span>
                                <span className="font-medium">
                                    {insurance.coverage_end_date 
                                        ? new Date(insurance.coverage_end_date).toLocaleDateString()
                                        : 'N/A'}
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* Expired Insurance Warning */}
                    {isExpired && (
                        <div className="rounded-lg border border-amber-500/50 bg-amber-50 p-2 dark:bg-amber-950/20">
                            <p className="text-xs font-medium text-amber-700 dark:text-amber-400">
                                ⚠️ Coverage expired - please renew to use insurance
                            </p>
                        </div>
                    )}

                    {/* CCC Verification Section */}
                    <div className={`rounded-lg border p-3 space-y-3 ${!canUseInsurance ? 'bg-muted opacity-50' : 'bg-primary/5'}`}>
                        <div className="flex items-center gap-2">
                            <CreditCard className="h-4 w-4 text-primary" />
                            <h4 className="text-sm font-medium">
                                Use {isNhisProvider ? 'NHIS' : 'Insurance'} for this Visit
                            </h4>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            {isExtensionMode
                                ? 'Click "Verify NHIS" to automatically get the CCC from the NHIA portal.'
                                : 'Enter the Claim Check Code (CCC) to process this visit under insurance coverage.'}
                        </p>

                        {/* Extension Mode: Verify Button */}
                        {isExtensionMode && isNhisProvider && canUseInsurance && (
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
                                
                                {cccData && (
                                    <div className="rounded-md bg-green-50 p-2 dark:bg-green-950/20">
                                        <div className="flex items-center gap-2 text-green-700 dark:text-green-400">
                                            <CheckCircle2 className="h-3 w-3" />
                                            <span className="text-xs font-medium">
                                                Verified: {cccData.memberName}
                                            </span>
                                        </div>
                                        <p className="text-xs text-green-600 dark:text-green-500">
                                            {cccData.status} • {cccData.coverageStart} to {cccData.coverageEnd}
                                        </p>
                                    </div>
                                )}
                            </>
                        )}

                        {/* Manual Mode: Open Portal Button */}
                        {!isExtensionMode && isNhisProvider && canUseInsurance && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={handleOpenPortalManual}
                                className="w-full"
                            >
                                <ExternalLink className="mr-2 h-3 w-3" />
                                Open NHIA Portal (Membership # copied)
                            </Button>
                        )}

                        {/* CCC Input Field */}
                        <div className="space-y-1">
                            <Label htmlFor="claim_check_code" className="text-xs">
                                Claim Check Code (CCC) *
                            </Label>
                            <Input
                                id="claim_check_code"
                                type="text"
                                placeholder={isExtensionMode ? 'Auto-fills after verification...' : 'Enter CCC...'}
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
                                        : cccData?.ccc
                                          ? 'border-green-500 bg-green-50 dark:bg-green-950/20'
                                          : ''
                                }`}
                            />
                            {error && <p className="text-xs text-destructive">{error}</p>}
                        </div>

                        <Button
                            onClick={handleUseInsurance}
                            size="sm"
                            className="w-full"
                            disabled={!canUseInsurance || !claimCheckCode.trim()}
                        >
                            <Shield className="mr-2 h-3 w-3" />
                            Check-in with {isNhisProvider ? 'NHIS' : 'Insurance'}
                        </Button>
                    </div>

                    {/* Cash Payment Option */}
                    <div className="rounded-lg border p-3">
                        <h4 className="text-sm font-medium">Or Pay Cash</h4>
                        <p className="text-xs text-muted-foreground mb-2">
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
                        <Button onClick={handleModalClose} variant="ghost" size="sm">
                            Cancel
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
