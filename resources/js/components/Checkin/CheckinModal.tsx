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
import AdmissionWarningDialog from './AdmissionWarningDialog';
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

interface NhisSettings {
    verification_mode: 'manual' | 'extension';
    nhia_portal_url: string;
    auto_open_portal: boolean;
    credentials?: {
        username: string;
        password: string;
    } | null;
}

interface AdmissionDetails {
    id: number;
    admission_number: string;
    ward: string;
    admitted_at: string;
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
    const [nhisSettings, setNhisSettings] = useState<NhisSettings | null>(null);
    const [selectedDepartment, setSelectedDepartment] = useState('');
    const [notes, setNotes] = useState('');
    const [serviceDate, setServiceDate] = useState('');
    const [checkingInsurance, setCheckingInsurance] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);

    // Admission warning state
    const [admissionWarning, setAdmissionWarning] = useState<{
        show: boolean;
        admission: AdmissionDetails | null;
        pendingCheckinData: {
            hasInsurance: boolean;
            claimCheckCode: string | null;
        } | null;
    }>({ show: false, admission: null, pendingCheckinData: null });

    // Get today's date in YYYY-MM-DD format for date input
    const today = new Date().toISOString().split('T')[0];

    useEffect(() => {
        if (open && patient) {
            // Check if patient has active insurance
            checkPatientInsurance();
        } else {
            // Reset state when modal closes
            setInsuranceInfo(null);
            setNhisSettings(null);
            setInsuranceDialogOpen(false);
            setSelectedDepartment('');
            setNotes('');
            setServiceDate('');
            setIsSubmitting(false);
            setAdmissionWarning({
                show: false,
                admission: null,
                pendingCheckinData: null,
            });
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

            if (data.nhis_settings) {
                setNhisSettings(data.nhis_settings);
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
        confirmAdmissionOverride: boolean = false,
    ) => {
        if (!patient || !selectedDepartment) {
            toast.error('Please select a department');
            return;
        }

        // Prevent double submission
        if (isSubmitting) {
            return;
        }

        setIsSubmitting(true);

        const formData: Record<string, string | number | boolean> = {
            patient_id: patient.id,
            department_id: selectedDepartment,
        };

        if (notes) {
            formData.notes = notes;
        }

        // Include service_date if backdating (not today)
        if (serviceDate && serviceDate !== today) {
            formData.service_date = serviceDate;
        }

        if (hasInsurance && claimCheckCode) {
            formData.has_insurance = true;
            formData.claim_check_code = claimCheckCode;
        }

        // Include admission override confirmation if user confirmed
        if (confirmAdmissionOverride) {
            formData.confirm_admission_override = true;
        }

        router.post('/checkin/checkins', formData, {
            onSuccess: () => {
                toast.success('Patient checked in successfully');
                setIsSubmitting(false);
                setAdmissionWarning({
                    show: false,
                    admission: null,
                    pendingCheckinData: null,
                });
                onSuccess();
            },
            onError: (errors) => {
                setIsSubmitting(false);

                // Handle admission warning - show dialog instead of error
                // The admission details are passed as JSON in the error value
                if (errors.admission_warning) {
                    try {
                        const admissionDetails =
                            typeof errors.admission_warning === 'string'
                                ? JSON.parse(errors.admission_warning)
                                : null;
                        if (admissionDetails) {
                            setAdmissionWarning({
                                show: true,
                                admission: admissionDetails,
                                pendingCheckinData: {
                                    hasInsurance,
                                    claimCheckCode,
                                },
                            });
                            return;
                        }
                    } catch {
                        // If parsing fails, fall through to generic error handling
                    }
                }

                // Display specific error messages based on field
                // Priority: department_id > claim_check_code > patient_id > other errors
                if (errors.department_id) {
                    toast.error(errors.department_id);
                } else if (errors.claim_check_code) {
                    toast.error(errors.claim_check_code);
                } else if (errors.patient_id) {
                    toast.error(errors.patient_id);
                } else {
                    // Fallback to first available error message
                    const firstError = Object.values(errors)[0];
                    if (firstError && typeof firstError === 'string') {
                        toast.error(firstError);
                    } else {
                        toast.error(
                            'Check-in failed. Please review the form and try again.',
                        );
                    }
                }
            },
        });
    };

    const handleAdmissionWarningConfirm = () => {
        if (admissionWarning.pendingCheckinData) {
            submitCheckin(
                admissionWarning.pendingCheckinData.hasInsurance,
                admissionWarning.pendingCheckinData.claimCheckCode,
                true, // confirmAdmissionOverride
            );
        }
    };

    const handleAdmissionWarningClose = () => {
        setAdmissionWarning({
            show: false,
            admission: null,
            pendingCheckinData: null,
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

                            <div className="space-y-3">
                                {/* Patient Information */}
                                <div className="space-y-2 rounded-lg border bg-muted/50 p-3">
                                    <h3 className="flex items-center gap-2 text-sm font-medium">
                                        <User className="h-4 w-4" />
                                        Patient Information
                                    </h3>
                                    <div className="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                                        <div className="flex items-baseline gap-1">
                                            <span className="text-muted-foreground">
                                                Name:
                                            </span>
                                            <span className="font-medium">
                                                {patient.full_name}
                                            </span>
                                        </div>
                                        <div className="flex items-baseline gap-1">
                                            <span className="text-muted-foreground">
                                                Patient #:
                                            </span>
                                            <span className="font-medium">
                                                {patient.patient_number}
                                            </span>
                                        </div>
                                        <div className="flex items-baseline gap-1">
                                            <span className="text-muted-foreground">
                                                Age/Gender:
                                            </span>
                                            <span className="font-medium">
                                                {patient.age}y, {patient.gender}
                                            </span>
                                        </div>
                                        <div className="flex items-baseline gap-1">
                                            <span className="text-muted-foreground">
                                                Phone:
                                            </span>
                                            <span className="font-medium">
                                                {patient.phone_number || 'N/A'}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                {/* Insurance Status Alert */}
                                {checkingInsurance && (
                                    <div className="flex items-center justify-center gap-2 rounded-lg border bg-muted/50 p-2 text-sm text-muted-foreground">
                                        <Loader2 className="h-4 w-4 animate-spin" />
                                        Checking insurance...
                                    </div>
                                )}

                                {!checkingInsurance && insuranceInfo && (
                                    <div
                                        className={`rounded-lg border p-2 ${
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
                                            <span className="ml-2 font-normal text-muted-foreground">
                                                ({insuranceInfo.membership_id})
                                            </span>
                                            {insuranceInfo.coverage_end_date && (
                                                <span className="ml-2 text-xs font-normal text-muted-foreground">
                                                    Exp:{' '}
                                                    {new Date(
                                                        insuranceInfo.coverage_end_date,
                                                    ).toLocaleDateString()}
                                                    {insuranceInfo.is_expired && (
                                                        <span className="ml-1 text-amber-600 dark:text-amber-500">
                                                            (EXPIRED)
                                                        </span>
                                                    )}
                                                </span>
                                            )}
                                        </p>
                                        {insuranceInfo.is_expired && (
                                            <p className="mt-1 text-xs text-amber-700 dark:text-amber-400">
                                                Insurance expired - cash payment
                                                only
                                            </p>
                                        )}
                                    </div>
                                )}

                                {/* Check-in Details */}
                                <div className="space-y-2">
                                    <h3 className="flex items-center gap-2 text-sm font-medium">
                                        <Calendar className="h-4 w-4" />
                                        Check-in Details
                                    </h3>

                                    <div className="space-y-1">
                                        <Label
                                            htmlFor="department_id"
                                            className="text-sm"
                                        >
                                            Clinic/Department *
                                        </Label>
                                        {departments.length === 0 ? (
                                            <div className="rounded-md border border-destructive/50 bg-destructive/10 p-2 text-sm text-destructive">
                                                No active departments available.
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
                                                className="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
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

                                    <div className="space-y-1">
                                        <Label
                                            htmlFor="service_date"
                                            className="text-sm"
                                        >
                                            Service Date
                                        </Label>
                                        <input
                                            type="date"
                                            id="service_date"
                                            value={serviceDate || today}
                                            onChange={(e) =>
                                                setServiceDate(e.target.value)
                                            }
                                            max={today}
                                            className="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                        />
                                    </div>

                                    <div className="space-y-1">
                                        <Label
                                            htmlFor="notes"
                                            className="text-sm"
                                        >
                                            Notes{' '}
                                            <span className="text-xs text-muted-foreground">
                                                (optional)
                                            </span>
                                        </Label>
                                        <textarea
                                            id="notes"
                                            value={notes}
                                            onChange={(e) =>
                                                setNotes(e.target.value)
                                            }
                                            placeholder="Additional notes..."
                                            rows={2}
                                            className="flex min-h-[50px] w-full rounded-md border border-input bg-background px-3 py-1.5 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                        />
                                    </div>
                                </div>

                                {/* Action Buttons */}
                                <div className="flex justify-end gap-2 pt-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={handleModalClose}
                                        disabled={isSubmitting}
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
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
                                            departments.length === 0 ||
                                            isSubmitting
                                        }
                                    >
                                        {(checkingInsurance ||
                                            isSubmitting) && (
                                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                        )}
                                        {isSubmitting
                                            ? 'Checking in...'
                                            : 'Check-in'}
                                    </Button>
                                </div>
                            </div>
                        </>
                    )}
                </DialogContent>
            </Dialog>

            {/* Insurance Dialog */}
            {insuranceInfo && patient && (
                <InsuranceDialog
                    open={insuranceDialogOpen}
                    onClose={() => setInsuranceDialogOpen(false)}
                    insurance={insuranceInfo}
                    onUseCash={handleUseCash}
                    onUseInsurance={handleUseInsurance}
                    nhisSettings={nhisSettings ?? undefined}
                    patientId={patient.id}
                    serviceDate={serviceDate || today}
                />
            )}

            {/* Admission Warning Dialog */}
            <AdmissionWarningDialog
                open={admissionWarning.show}
                onClose={handleAdmissionWarningClose}
                onConfirm={handleAdmissionWarningConfirm}
                admission={admissionWarning.admission}
                isSubmitting={isSubmitting}
            />
        </>
    );
}
