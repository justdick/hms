import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { router } from '@inertiajs/react';
import { Calendar, Loader2, User } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import InsuranceDialog from './InsuranceDialog';

interface Patient {
    id: number;
    patient_number: string;
    full_name: string;
    age: number;
    gender: string;
    phone_number: string | null;
}

interface Department {
    id: number;
    name: string;
    code: string;
    description: string;
}

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

interface CheckinModalProps {
    open: boolean;
    onClose: () => void;
    patient: Patient | null;
    departments: Department[];
    onSuccess: () => void;
}

export default function CheckinModal({
    open,
    onClose,
    patient,
    departments,
    onSuccess,
}: CheckinModalProps) {
    const [insuranceDialogOpen, setInsuranceDialogOpen] = useState(false);
    const [insuranceInfo, setInsuranceInfo] = useState<InsuranceInfo | null>(
        null,
    );
    const [selectedDepartment, setSelectedDepartment] = useState('');
    const [notes, setNotes] = useState('');
    const [checkingInsurance, setCheckingInsurance] = useState(false);

    useEffect(() => {
        if (open && patient) {
            // Check if patient has active insurance
            checkPatientInsurance();
        } else {
            // Reset state when modal closes
            setInsuranceInfo(null);
            setInsuranceDialogOpen(false);
            setSelectedDepartment('');
            setNotes('');
        }
    }, [open, patient]);

    const checkPatientInsurance = async () => {
        if (!patient) return;

        setCheckingInsurance(true);
        try {
            const csrfToken = document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content');

            const response = await fetch(
                `/checkin/checkins/patients/${patient.id}/insurance`,
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken || '',
                    },
                    credentials: 'same-origin',
                },
            );

            if (!response.ok) {
                throw new Error('Failed to check insurance');
            }

            const data = await response.json();

            if (data.has_insurance) {
                setInsuranceInfo(data.insurance);
            }
        } catch (error) {
            console.error('Error checking insurance:', error);
        } finally {
            setCheckingInsurance(false);
        }
    };

    const handleDepartmentSelected = (departmentId: string) => {
        setSelectedDepartment(departmentId);

        // If patient has insurance, show insurance dialog
        // Otherwise, proceed directly to check-in without insurance
        if (insuranceInfo) {
            setInsuranceDialogOpen(true);
        } else {
            submitCheckin(false, null);
        }
    };

    const handleUseCash = () => {
        setInsuranceDialogOpen(false);
        // Proceed with cash check-in
        submitCheckin(false, null);
    };

    const handleUseInsurance = (claimCheckCode: string) => {
        setInsuranceDialogOpen(false);
        // Proceed with insurance check-in
        submitCheckin(true, claimCheckCode);
    };

    const submitCheckin = (
        hasInsurance: boolean,
        claimCheckCode: string | null,
    ) => {
        if (!patient || !selectedDepartment) {
            toast.error('Please select a department');
            return;
        }

        const formData: Record<string, string | number | boolean> = {
            patient_id: patient.id,
            department_id: selectedDepartment,
        };

        if (notes) {
            formData.notes = notes;
        }

        if (hasInsurance && claimCheckCode) {
            formData.has_insurance = true;
            formData.claim_check_code = claimCheckCode;
        }

        router.post('/checkin/checkins', formData, {
            onSuccess: () => {
                toast.success('Patient checked in successfully');
                onSuccess();
            },
            onError: (errors) => {
                if (errors.claim_check_code) {
                    toast.error(errors.claim_check_code);
                } else {
                    toast.error('Failed to check in patient');
                }
            },
        });
    };

    const handleModalClose = () => {
        onClose();
    };

    return (
        <>
            <Dialog open={open && !!patient} onOpenChange={handleModalClose}>
                <DialogContent className="max-w-2xl">
                    {patient && (
                        <>
                            <DialogHeader>
                                <DialogTitle>Check-in Patient</DialogTitle>
                                <DialogDescription>
                                    Check in {patient.full_name} to a clinic for
                                    consultation.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="space-y-6">
                                {/* Patient Information */}
                                <div className="space-y-4 rounded-lg border bg-muted/50 p-4">
                                    <h3 className="flex items-center gap-2 font-medium">
                                        <User className="h-4 w-4" />
                                        Patient Information
                                    </h3>
                                    <div className="grid grid-cols-2 gap-4 text-sm">
                                        <div>
                                            <p className="text-muted-foreground">
                                                Name
                                            </p>
                                            <p className="font-medium">
                                                {patient.full_name}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-muted-foreground">
                                                Patient Number
                                            </p>
                                            <p className="font-medium">
                                                {patient.patient_number}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-muted-foreground">
                                                Age & Gender
                                            </p>
                                            <p className="font-medium">
                                                {patient.age} years,{' '}
                                                {patient.gender}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-muted-foreground">
                                                Phone
                                            </p>
                                            <p className="font-medium">
                                                {patient.phone_number ||
                                                    'Not provided'}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                {/* Insurance Status Alert */}
                                {checkingInsurance && (
                                    <div className="rounded-lg border bg-muted/50 p-4 text-center">
                                        <Loader2 className="mx-auto h-6 w-6 animate-spin text-muted-foreground" />
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            Checking insurance coverage...
                                        </p>
                                    </div>
                                )}

                                {!checkingInsurance && insuranceInfo && (
                                    <div
                                        className={`rounded-lg border p-4 ${
                                            insuranceInfo.is_expired
                                                ? 'border-amber-500/50 bg-amber-50 dark:bg-amber-950/20'
                                                : 'border-primary/20 bg-primary/5'
                                        }`}
                                    >
                                        <p
                                            className={`text-sm font-medium ${
                                                insuranceInfo.is_expired
                                                    ? 'text-amber-700 dark:text-amber-400'
                                                    : 'text-primary'
                                            }`}
                                        >
                                            {insuranceInfo.is_expired
                                                ? '⚠️'
                                                : '✓'}{' '}
                                            {insuranceInfo.plan.provider.name} -{' '}
                                            {insuranceInfo.plan.plan_name}
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            Member ID:{' '}
                                            {insuranceInfo.membership_id}
                                            {insuranceInfo.coverage_end_date && (
                                                <>
                                                    {' '}
                                                    • Expires:{' '}
                                                    {new Date(
                                                        insuranceInfo.coverage_end_date,
                                                    ).toLocaleDateString()}
                                                    {insuranceInfo.is_expired && (
                                                        <span className="text-amber-600 dark:text-amber-500">
                                                            {' '}
                                                            (EXPIRED)
                                                        </span>
                                                    )}
                                                </>
                                            )}
                                        </p>
                                        {insuranceInfo.is_expired && (
                                            <p className="mt-2 text-xs text-amber-700 dark:text-amber-400">
                                                ⚠️ Warning: Insurance coverage
                                                has expired. Insurance payment
                                                option will not be available.
                                            </p>
                                        )}
                                    </div>
                                )}

                                {/* Check-in Details */}
                                <div className="space-y-4">
                                    <h3 className="flex items-center gap-2 font-medium">
                                        <Calendar className="h-4 w-4" />
                                        Check-in Details
                                    </h3>

                                    <div className="space-y-2">
                                        <Label htmlFor="department_id">
                                            Select Clinic/Department *
                                        </Label>
                                        {departments.length === 0 ? (
                                            <div className="rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive">
                                                No active departments available.
                                                Please contact an administrator.
                                            </div>
                                        ) : (
                                            <select
                                                id="department_id"
                                                value={selectedDepartment}
                                                onChange={(e) =>
                                                    setSelectedDepartment(
                                                        e.target.value,
                                                    )
                                                }
                                                required
                                                className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                            >
                                                <option value="">
                                                    Choose a clinic...
                                                </option>
                                                {departments.map(
                                                    (department) => (
                                                        <option
                                                            key={department.id}
                                                            value={
                                                                department.id
                                                            }
                                                        >
                                                            {department.name} -{' '}
                                                            {
                                                                department.description
                                                            }
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="notes">
                                            Notes (Optional)
                                        </Label>
                                        <textarea
                                            id="notes"
                                            value={notes}
                                            onChange={(e) =>
                                                setNotes(e.target.value)
                                            }
                                            placeholder="Any additional notes about the patient's visit..."
                                            rows={3}
                                            className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                        />
                                    </div>
                                </div>

                                {/* Action Buttons */}
                                <div className="flex justify-end gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={handleModalClose}
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        type="button"
                                        onClick={() => {
                                            if (!selectedDepartment) {
                                                toast.error(
                                                    'Please select a department',
                                                );
                                                return;
                                            }
                                            handleDepartmentSelected(
                                                selectedDepartment,
                                            );
                                        }}
                                        disabled={
                                            checkingInsurance ||
                                            departments.length === 0
                                        }
                                    >
                                        {checkingInsurance && (
                                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                        )}
                                        Continue to Check-in
                                    </Button>
                                </div>
                            </div>
                        </>
                    )}
                </DialogContent>
            </Dialog>

            {/* Insurance Dialog */}
            {insuranceInfo && (
                <InsuranceDialog
                    open={insuranceDialogOpen}
                    onClose={() => setInsuranceDialogOpen(false)}
                    insurance={insuranceInfo}
                    onUseCash={handleUseCash}
                    onUseInsurance={handleUseInsurance}
                />
            )}
        </>
    );
}
