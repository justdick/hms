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
    patient_number: string;
    full_name: string;
    age: number;
    gender: string;
}

interface Department {
    id: number;
    name: string;
}

interface VitalSigns {
    id: number;
    blood_pressure_systolic: number | null;
    blood_pressure_diastolic: number | null;
    temperature: number | null;
    pulse_rate: number | null;
    respiratory_rate: number | null;
    oxygen_saturation: number | null;
    blood_sugar: number | null;
    weight: number | null;
    height: number | null;
    notes: string | null;
    recorded_at: string;
}

interface Checkin {
    id: number;
    patient: Patient;
    department: Department;
    status: string;
    checked_in_at: string;
    vital_signs?: VitalSigns[];
}

interface VitalsModalProps {
    open: boolean;
    onClose: () => void;
    checkin: Checkin | null;
    onSuccess: () => void;
    mode?: 'create' | 'edit';
}

export default function VitalsModal({
    open,
    onClose,
    checkin,
    onSuccess,
    mode = 'create',
}: VitalsModalProps) {
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
    });

    const existingVitals = checkin?.vital_signs?.[0];
    const isEditMode = mode === 'edit' && existingVitals;

    useEffect(() => {
        if (open && checkin) {
            if (isEditMode && existingVitals) {
                // Format blood pressure as integers (remove .00)
                const formatBP = (value: number | null | undefined) =>
                    value != null ? Math.round(value).toString() : '';

                setFormData({
                    blood_pressure_systolic: formatBP(
                        existingVitals.blood_pressure_systolic,
                    ),
                    blood_pressure_diastolic: formatBP(
                        existingVitals.blood_pressure_diastolic,
                    ),
                    temperature: existingVitals.temperature?.toString() || '',
                    pulse_rate: existingVitals.pulse_rate?.toString() || '',
                    respiratory_rate:
                        existingVitals.respiratory_rate?.toString() || '',
                    oxygen_saturation:
                        existingVitals.oxygen_saturation?.toString() || '',
                    blood_sugar: existingVitals.blood_sugar?.toString() || '',
                    weight: existingVitals.weight?.toString() || '',
                    height: existingVitals.height?.toString() || '',
                    notes: existingVitals.notes || '',
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
                });
            }
            setErrors({});
        }
    }, [open, checkin, isEditMode, existingVitals]);

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

        const submitData: Record<string, string | number | null> = {};

        if (formData.blood_pressure_systolic)
            submitData.blood_pressure_systolic = parseInt(
                formData.blood_pressure_systolic,
            );
        if (formData.blood_pressure_diastolic)
            submitData.blood_pressure_diastolic = parseInt(
                formData.blood_pressure_diastolic,
            );
        if (formData.temperature)
            submitData.temperature = parseFloat(formData.temperature);
        if (formData.pulse_rate)
            submitData.pulse_rate = parseInt(formData.pulse_rate);
        if (formData.respiratory_rate)
            submitData.respiratory_rate = parseInt(formData.respiratory_rate);
        if (formData.oxygen_saturation)
            submitData.oxygen_saturation = parseInt(formData.oxygen_saturation);
        if (formData.blood_sugar)
            submitData.blood_sugar = parseFloat(formData.blood_sugar);
        if (formData.weight) submitData.weight = parseInt(formData.weight);
        if (formData.height) submitData.height = parseFloat(formData.height);
        if (formData.notes) submitData.notes = formData.notes;

        if (isEditMode && existingVitals) {
            router.patch(`/checkin/vitals/${existingVitals.id}`, submitData, {
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
            });
        } else {
            router.post(
                '/checkin/vitals',
                {
                    patient_checkin_id: checkin?.id,
                    ...submitData,
                },
                {
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
                },
            );
        }
    };

    if (!checkin) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={handleModalClose}>
            <DialogContent className="max-w-xl">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Activity className="h-5 w-5 text-primary" />
                        {isEditMode ? 'Edit Vital Signs' : 'Record Vital Signs'}
                    </DialogTitle>
                    <p className="text-sm text-muted-foreground">
                        {checkin.patient.full_name} • {checkin.patient.age} yrs,{' '}
                        {checkin.patient.gender} • {checkin.department.name}
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
