import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { router } from '@inertiajs/react';
import { Activity, Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

interface Patient {
    id: number;
    first_name: string;
    last_name: string;
    date_of_birth?: string;
    gender?: string;
}

interface Bed {
    id: number;
    bed_number: string;
}

interface VitalSigns {
    id: number;
    blood_pressure_systolic?: number | null;
    blood_pressure_diastolic?: number | null;
    temperature?: number | null;
    pulse_rate?: number | null;
    respiratory_rate?: number | null;
    oxygen_saturation?: number | null;
    blood_sugar?: number | null;
    weight?: number | null;
    height?: number | null;
    notes?: string | null;
    recorded_at: string;
}

interface PatientAdmission {
    id: number;
    admission_number: string;
    patient: Patient;
    bed?: Bed;
    admitted_at: string;
    vital_signs?: VitalSigns[];
}

interface RecordVitalsModalProps {
    open: boolean;
    onClose: () => void;
    admission: PatientAdmission | null;
    onSuccess: () => void;
    mode?: 'create' | 'edit';
    editVitals?: VitalSigns | null;
    canEditTimestamp?: boolean;
}

export function RecordVitalsModal({
    open,
    onClose,
    admission,
    onSuccess,
    mode = 'create',
    editVitals = null,
    canEditTimestamp = false,
}: RecordVitalsModalProps) {
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const [formData, setFormData] = useState({
        blood_pressure_systolic: '',
        blood_pressure_diastolic: '',
        temperature: '',
        pulse_rate: '',
        respiratory_rate: '',
        oxygen_saturation: '',
        blood_sugar: '',
        weight: '',
        height: '',
        notes: '',
        recorded_at: '',
    });

    const isEditMode = mode === 'edit' && editVitals;

    const calculateAge = (dateOfBirth?: string) => {
        if (!dateOfBirth) return null;
        const today = new Date();
        const birthDate = new Date(dateOfBirth);
        let age = today.getFullYear() - birthDate.getFullYear();
        const monthDiff = today.getMonth() - birthDate.getMonth();
        if (
            monthDiff < 0 ||
            (monthDiff === 0 && today.getDate() < birthDate.getDate())
        ) {
            age--;
        }
        return age;
    };

    useEffect(() => {
        if (open && admission) {
            if (isEditMode && editVitals) {
                // Format recorded_at for datetime-local input (YYYY-MM-DDTHH:mm)
                const recordedAt = editVitals.recorded_at
                    ? new Date(editVitals.recorded_at)
                          .toISOString()
                          .slice(0, 16)
                    : '';
                // Format blood pressure as integers (remove .00)
                const formatBP = (value: number | null | undefined) =>
                    value != null ? Math.round(value).toString() : '';

                setFormData({
                    blood_pressure_systolic: formatBP(
                        editVitals.blood_pressure_systolic,
                    ),
                    blood_pressure_diastolic: formatBP(
                        editVitals.blood_pressure_diastolic,
                    ),
                    temperature: editVitals.temperature?.toString() || '',
                    pulse_rate: editVitals.pulse_rate?.toString() || '',
                    respiratory_rate:
                        editVitals.respiratory_rate?.toString() || '',
                    oxygen_saturation:
                        editVitals.oxygen_saturation?.toString() || '',
                    blood_sugar: editVitals.blood_sugar?.toString() || '',
                    weight: editVitals.weight?.toString() || '',
                    height: editVitals.height?.toString() || '',
                    notes: editVitals.notes || '',
                    recorded_at: recordedAt,
                });
            } else {
                setFormData({
                    blood_pressure_systolic: '',
                    blood_pressure_diastolic: '',
                    temperature: '',
                    pulse_rate: '',
                    respiratory_rate: '',
                    oxygen_saturation: '',
                    blood_sugar: '',
                    weight: '',
                    height: '',
                    notes: '',
                    recorded_at: '',
                });
            }
            setErrors({});
        }
    }, [open, admission, isEditMode, editVitals]);

    const handleModalClose = () => {
        setErrors({});
        onClose();
    };

    const handleInputChange = (field: string, value: string) => {
        setFormData((prev) => ({ ...prev, [field]: value }));
        if (errors[field]) {
            setErrors((prev) => {
                const newErrors = { ...prev };
                delete newErrors[field];
                return newErrors;
            });
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        const submitData: Record<string, string | number | null> = {
            // All fields are optional
            blood_pressure_systolic: formData.blood_pressure_systolic
                ? parseInt(formData.blood_pressure_systolic)
                : null,
            blood_pressure_diastolic: formData.blood_pressure_diastolic
                ? parseInt(formData.blood_pressure_diastolic)
                : null,
            temperature: formData.temperature
                ? parseFloat(formData.temperature)
                : null,
            pulse_rate: formData.pulse_rate
                ? parseInt(formData.pulse_rate)
                : null,
            respiratory_rate: formData.respiratory_rate
                ? parseInt(formData.respiratory_rate)
                : null,
            // Optional fields
            oxygen_saturation: formData.oxygen_saturation
                ? parseInt(formData.oxygen_saturation)
                : null,
            blood_sugar: formData.blood_sugar
                ? parseFloat(formData.blood_sugar)
                : null,
            weight: formData.weight ? parseInt(formData.weight) : null,
            height: formData.height ? parseFloat(formData.height) : null,
            notes: formData.notes || null,
        };

        // Include recorded_at if user has permission and it's set
        if (canEditTimestamp && formData.recorded_at) {
            submitData.recorded_at = formData.recorded_at;
        }

        if (isEditMode && editVitals) {
            router.patch(
                `/admissions/${admission?.id}/vitals/${editVitals.id}`,
                submitData,
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        toast.success('Vital signs updated successfully');
                        setProcessing(false);
                        onSuccess();
                    },
                    onError: (errs) => {
                        setErrors(errs as Record<string, string>);
                        toast.error('Failed to update vital signs');
                        setProcessing(false);
                    },
                },
            );
        } else {
            router.post(`/admissions/${admission?.id}/vitals`, submitData, {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Vital signs recorded successfully');
                    setProcessing(false);
                    onSuccess();
                },
                onError: (errs) => {
                    setErrors(errs as Record<string, string>);
                    toast.error('Failed to record vital signs');
                    setProcessing(false);
                },
            });
        }
    };

    if (!admission) {
        return null;
    }

    const patientAge = calculateAge(admission.patient.date_of_birth);

    return (
        <Dialog open={open} onOpenChange={handleModalClose}>
            <DialogContent className="max-w-xl">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Activity className="h-5 w-5 text-primary" />
                        {isEditMode ? 'Edit Vital Signs' : 'Record Vital Signs'}
                    </DialogTitle>
                    <p className="text-sm text-muted-foreground">
                        {admission.patient.first_name}{' '}
                        {admission.patient.last_name} •{' '}
                        {patientAge ? `${patientAge} yrs` : 'N/A'},{' '}
                        {admission.patient.gender} •{' '}
                        {admission.bed
                            ? `Bed ${admission.bed.bed_number}`
                            : 'Ward'}
                    </p>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    {/* Blood Pressure - prominent */}
                    <div className="rounded-lg border bg-muted/30 p-3">
                        <Label className="mb-2 block text-sm font-medium">
                            Blood Pressure (mmHg)
                        </Label>
                        <div className="flex items-center gap-2">
                            <Input
                                value={formData.blood_pressure_systolic}
                                onChange={(e) =>
                                    handleInputChange(
                                        'blood_pressure_systolic',
                                        e.target.value,
                                    )
                                }
                                type="number"
                                className="h-11 text-center text-lg font-semibold"
                            />
                            <span className="text-xl font-bold text-muted-foreground">
                                /
                            </span>
                            <Input
                                value={formData.blood_pressure_diastolic}
                                onChange={(e) =>
                                    handleInputChange(
                                        'blood_pressure_diastolic',
                                        e.target.value,
                                    )
                                }
                                type="number"
                                className="h-11 text-center text-lg font-semibold"
                            />
                        </div>
                        {(errors.blood_pressure_systolic ||
                            errors.blood_pressure_diastolic) && (
                            <p className="mt-1 text-xs text-destructive">
                                {errors.blood_pressure_systolic ||
                                    errors.blood_pressure_diastolic}
                            </p>
                        )}
                    </div>

                    {/* Row 1: Temp, Pulse, Resp */}
                    <div className="grid grid-cols-3 gap-3">
                        <div>
                            <Label className="text-xs text-muted-foreground">
                                Temp (°C)
                            </Label>
                            <Input
                                value={formData.temperature}
                                onChange={(e) =>
                                    handleInputChange(
                                        'temperature',
                                        e.target.value,
                                    )
                                }
                                type="number"
                                step="0.01"
                                className="text-center"
                            />
                            {errors.temperature && (
                                <p className="mt-1 text-xs text-destructive">
                                    {errors.temperature}
                                </p>
                            )}
                        </div>
                        <div>
                            <Label className="text-xs text-muted-foreground">
                                Pulse (BPM)
                            </Label>
                            <Input
                                value={formData.pulse_rate}
                                onChange={(e) =>
                                    handleInputChange(
                                        'pulse_rate',
                                        e.target.value,
                                    )
                                }
                                type="number"
                                className="text-center"
                            />
                            {errors.pulse_rate && (
                                <p className="mt-1 text-xs text-destructive">
                                    {errors.pulse_rate}
                                </p>
                            )}
                        </div>
                        <div>
                            <Label className="text-xs text-muted-foreground">
                                Resp (/min)
                            </Label>
                            <Input
                                value={formData.respiratory_rate}
                                onChange={(e) =>
                                    handleInputChange(
                                        'respiratory_rate',
                                        e.target.value,
                                    )
                                }
                                type="number"
                                className="text-center"
                            />
                            {errors.respiratory_rate && (
                                <p className="mt-1 text-xs text-destructive">
                                    {errors.respiratory_rate}
                                </p>
                            )}
                        </div>
                    </div>

                    {/* Row 2: SpO2, Blood Sugar, Weight, Height */}
                    <div className="grid grid-cols-4 gap-3">
                        <div>
                            <Label className="text-xs text-muted-foreground">
                                SpO₂ (%)
                            </Label>
                            <Input
                                value={formData.oxygen_saturation}
                                onChange={(e) =>
                                    handleInputChange(
                                        'oxygen_saturation',
                                        e.target.value,
                                    )
                                }
                                type="number"
                                className="text-center"
                            />
                        </div>
                        <div>
                            <Label className="text-xs text-muted-foreground">
                                Sugar (mmol/L)
                            </Label>
                            <Input
                                value={formData.blood_sugar}
                                onChange={(e) =>
                                    handleInputChange(
                                        'blood_sugar',
                                        e.target.value,
                                    )
                                }
                                type="number"
                                step="0.1"
                                className="text-center"
                            />
                        </div>
                        <div>
                            <Label className="text-xs text-muted-foreground">
                                Weight (kg)
                            </Label>
                            <Input
                                value={formData.weight}
                                onChange={(e) =>
                                    handleInputChange('weight', e.target.value)
                                }
                                type="number"
                                className="text-center"
                            />
                        </div>
                        <div>
                            <Label className="text-xs text-muted-foreground">
                                Height (cm)
                            </Label>
                            <Input
                                value={formData.height}
                                onChange={(e) =>
                                    handleInputChange('height', e.target.value)
                                }
                                type="number"
                                step="0.1"
                                className="text-center"
                            />
                        </div>
                    </div>

                    {/* Notes - compact */}
                    <div>
                        <Label className="text-xs text-muted-foreground">
                            Notes (Optional)
                        </Label>
                        <Textarea
                            value={formData.notes}
                            onChange={(e) =>
                                handleInputChange('notes', e.target.value)
                            }
                            placeholder="Any additional observations..."
                            rows={2}
                        />
                    </div>

                    {/* Recorded At - show when user has permission (both create and edit) */}
                    {canEditTimestamp && (
                        <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-950">
                            <Label className="text-xs font-medium text-amber-900 dark:text-amber-100">
                                Recorded Date & Time
                            </Label>
                            <Input
                                value={formData.recorded_at}
                                onChange={(e) =>
                                    handleInputChange(
                                        'recorded_at',
                                        e.target.value,
                                    )
                                }
                                type="datetime-local"
                                className="mt-1"
                            />
                            {errors.recorded_at && (
                                <p className="mt-1 text-xs text-destructive">
                                    {errors.recorded_at}
                                </p>
                            )}
                            <p className="mt-1 text-xs text-amber-700 dark:text-amber-300">
                                {isEditMode
                                    ? 'Only modify if the original recording time was incorrect.'
                                    : 'Leave empty to use current time, or set a specific time for backdated entry.'}
                            </p>
                        </div>
                    )}

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={handleModalClose}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && (
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            )}
                            {isEditMode ? 'Update Vitals' : 'Save Vitals'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
