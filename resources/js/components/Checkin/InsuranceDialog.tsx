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
    CheckCircle2,
    ExternalLink,
    Loader2,
    Shield,
    Wallet,
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
    
    const { isVerifying, cccData, startVerification, clearCccData } = useNhisExtension();

    const isNhisProvider = insurance.plan.provider.is_nhis ?? false;
    const isExpired = insurance.is_expired ?? false;
    const canUseInsurance = !isExpired;
    const verificationMode = nhisSettings?.verification_mode ?? 'manual';
    const isExtensionMode = verificationMode === 'extension' && isNhisProvider;

    useEffect(() => {
        if (cccData?.ccc) {
            setClaimCheckCode(cccData.ccc);
            setError('');
        }
    }, [cccData]);

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
        navigator.clipboard.writeText(insurance.membership_id).catch(() => {});
        startVerification(insurance.membership_id, nhisSettings?.credentials || undefined, nhisSettings?.nhia_portal_url);
    };

    const handleOpenPortalManual = () => {
        navigator.clipboard.writeText(insurance.membership_id).catch(() => {});
        window.open(nhisSettings?.nhia_portal_url || 'https://ccc.nhia.gov.gh/', '_blank');
    };

    const handleUseInsurance = () => {
        if (!claimCheckCode.trim()) {
            setError('Claim Check Code (CCC) is required');
            return;
        }
        setError('');
        onUseInsurance(claimCheckCode.trim());
    };

    const handleModalClose = () => {
        setClaimCheckCode('');
        setError('');
        clearCccData();
        onClose();
    };

    return (
        <Dialog open={open} onOpenChange={handleModalClose}>
            <DialogContent className="max-w-lg">
                <DialogHeader className="pb-2">
                    <DialogTitle className="flex items-center gap-2">
                        <Shield className="h-5 w-5 text-primary" />
                        Insurance Check-in
                    </DialogTitle>
                    <DialogDescription>
                        {insurance.plan.provider.name} - {insurance.plan.plan_name}
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    {/* Compact Insurance Info */}
                    <div className="grid grid-cols-2 gap-x-4 gap-y-1 rounded-lg border bg-muted/50 p-3 text-sm">
                        <div>
                            <span className="text-muted-foreground">Member ID: </span>
                            <span className="font-mono font-medium">{insurance.membership_id}</span>
                        </div>
                        <div>
                            <span className="text-muted-foreground">Coverage: </span>
                            <span className="font-medium">
                                {new Date(insurance.coverage_start_date).toLocaleDateString()} - {insurance.coverage_end_date ? new Date(insurance.coverage_end_date).toLocaleDateString() : 'Ongoing'}
                            </span>
                        </div>
                    </div>

                    {/* Expired Warning */}
                    {isExpired && (
                        <div className="rounded-md border border-amber-500/50 bg-amber-50 p-2 text-sm dark:bg-amber-950/20">
                            <span className="font-medium text-amber-700 dark:text-amber-400">
                                ⚠️ Coverage Expired - Insurance option unavailable
                            </span>
                        </div>
                    )}

                    {/* Use Insurance Section */}
                    {canUseInsurance && (
                        <div className="space-y-3 rounded-lg border bg-primary/5 p-3">
                            {/* Extension Mode: Verify Button */}
                            {isExtensionMode && isNhisProvider && (
                                <>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={handleVerifyNhis}
                                        disabled={isVerifying}
                                        className="w-full"
                                        size="sm"
                                    >
                                        {isVerifying ? (
                                            <>
                                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                Verifying...
                                            </>
                                        ) : (
                                            <>
                                                <ExternalLink className="mr-2 h-4 w-4" />
                                                Verify NHIS Membership
                                            </>
                                        )}
                                    </Button>
                                    
                                    {cccData && (
                                        <div className="flex items-center gap-2 rounded-md bg-green-50 p-2 text-sm dark:bg-green-950/20">
                                            <CheckCircle2 className="h-4 w-4 text-green-600" />
                                            <span className="text-green-700 dark:text-green-400">
                                                <strong>{cccData.memberName}</strong> • {cccData.status} • {cccData.coverageStart} to {cccData.coverageEnd}
                                            </span>
                                        </div>
                                    )}
                                </>
                            )}

                            {/* Manual Mode: Open Portal Button */}
                            {!isExtensionMode && isNhisProvider && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={handleOpenPortalManual}
                                    className="w-full"
                                    size="sm"
                                >
                                    <ExternalLink className="mr-2 h-4 w-4" />
                                    Open NHIA Portal
                                </Button>
                            )}

                            {/* CCC Input */}
                            <div className="space-y-1">
                                <Label htmlFor="claim_check_code" className="text-sm">
                                    CCC (Claim Check Code) *
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
                                    className={`h-9 ${
                                        error ? 'border-destructive' : cccData?.ccc ? 'border-green-500 bg-green-50 dark:bg-green-950/20' : ''
                                    }`}
                                />
                                {error && <p className="text-xs text-destructive">{error}</p>}
                            </div>

                            <Button
                                onClick={handleUseInsurance}
                                className="w-full"
                                disabled={!claimCheckCode.trim()}
                            >
                                <Shield className="mr-2 h-4 w-4" />
                                Check-in with {isNhisProvider ? 'NHIS' : 'Insurance'}
                            </Button>
                        </div>
                    )}

                    {/* Cash Payment Option - More compact */}
                    <Button
                        onClick={onUseCash}
                        variant="outline"
                        className="w-full"
                    >
                        <Wallet className="mr-2 h-4 w-4" />
                        Pay Cash Instead
                    </Button>

                    {/* Cancel */}
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
