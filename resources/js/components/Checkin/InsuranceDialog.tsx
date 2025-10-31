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
import { Building2, CreditCard, Shield } from 'lucide-react';
import { useState } from 'react';

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
        };
    };
    coverage_start_date: string;
    coverage_end_date: string | null;
}

interface InsuranceDialogProps {
    open: boolean;
    onClose: () => void;
    insurance: InsuranceInfo;
    onUseCash: () => void;
    onUseInsurance: (claimCheckCode: string) => void;
}

export default function InsuranceDialog({
    open,
    onClose,
    insurance,
    onUseCash,
    onUseInsurance,
}: InsuranceDialogProps) {
    const [claimCheckCode, setClaimCheckCode] = useState('');
    const [error, setError] = useState('');

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
        onClose();
    };

    return (
        <Dialog open={open} onOpenChange={handleModalClose}>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Shield className="h-5 w-5 text-primary" />
                        Active Insurance Detected
                    </DialogTitle>
                    <DialogDescription>
                        This patient has active insurance coverage. Choose how
                        to proceed with check-in.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-6">
                    {/* Insurance Information Display */}
                    <div className="space-y-4 rounded-lg border bg-muted/50 p-4">
                        <h3 className="flex items-center gap-2 font-medium">
                            <Building2 className="h-4 w-4" />
                            Insurance Details
                        </h3>

                        <div className="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <p className="text-muted-foreground">
                                    Insurance Provider
                                </p>
                                <p className="font-medium">
                                    {insurance.plan.provider.name}
                                </p>
                            </div>
                            <div>
                                <p className="text-muted-foreground">Plan</p>
                                <p className="font-medium">
                                    {insurance.plan.plan_name}
                                </p>
                            </div>
                            <div>
                                <p className="text-muted-foreground">
                                    Membership ID
                                </p>
                                <p className="font-medium">
                                    {insurance.membership_id}
                                </p>
                            </div>
                            {insurance.policy_number && (
                                <div>
                                    <p className="text-muted-foreground">
                                        Policy Number
                                    </p>
                                    <p className="font-medium">
                                        {insurance.policy_number}
                                    </p>
                                </div>
                            )}
                            <div>
                                <p className="text-muted-foreground">
                                    Coverage Start
                                </p>
                                <p className="font-medium">
                                    {new Date(
                                        insurance.coverage_start_date,
                                    ).toLocaleDateString()}
                                </p>
                            </div>
                            {insurance.coverage_end_date && (
                                <div>
                                    <p className="text-muted-foreground">
                                        Coverage End
                                    </p>
                                    <p className="font-medium">
                                        {new Date(
                                            insurance.coverage_end_date,
                                        ).toLocaleDateString()}
                                    </p>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Claim Check Code Input */}
                    <div className="space-y-4 rounded-lg border bg-primary/5 p-4">
                        <div className="flex items-start gap-3">
                            <CreditCard className="mt-1 h-5 w-5 text-primary" />
                            <div className="flex-1 space-y-4">
                                <div>
                                    <h4 className="font-medium">
                                        Use Insurance for this Visit
                                    </h4>
                                    <p className="text-sm text-muted-foreground">
                                        Enter the Claim Check Code (CCC) to
                                        process this visit under insurance
                                        coverage.
                                    </p>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="claim_check_code">
                                        Claim Check Code (CCC) *
                                    </Label>
                                    <Input
                                        id="claim_check_code"
                                        type="text"
                                        placeholder="Enter CCC manually..."
                                        value={claimCheckCode}
                                        onChange={(e) => {
                                            setClaimCheckCode(e.target.value);
                                            setError('');
                                        }}
                                        maxLength={50}
                                        className={
                                            error ? 'border-destructive' : ''
                                        }
                                    />
                                    {error && (
                                        <p className="text-sm text-destructive">
                                            {error}
                                        </p>
                                    )}
                                    <p className="text-xs text-muted-foreground">
                                        The CCC must be entered manually as
                                        provided by the insurance company or
                                        patient.
                                    </p>
                                </div>

                                <Button
                                    onClick={handleUseInsurance}
                                    className="w-full"
                                >
                                    <Shield className="mr-2 h-4 w-4" />
                                    Check-in with Insurance
                                </Button>
                            </div>
                        </div>
                    </div>

                    {/* Cash Payment Option */}
                    <div className="space-y-4 rounded-lg border p-4">
                        <div>
                            <h4 className="font-medium">
                                Or Pay Cash for this Visit
                            </h4>
                            <p className="text-sm text-muted-foreground">
                                Patient chooses to pay out-of-pocket instead of
                                using insurance for this visit.
                            </p>
                        </div>

                        <Button
                            onClick={handleUseCash}
                            variant="outline"
                            className="w-full"
                        >
                            Proceed without Insurance (Cash Payment)
                        </Button>
                    </div>

                    {/* Cancel Option */}
                    <div className="flex justify-end">
                        <Button
                            onClick={handleModalClose}
                            variant="ghost"
                            size="sm"
                        >
                            Cancel Check-in
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
