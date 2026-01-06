import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useForm } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import React from 'react';
import { toast } from 'sonner';

interface Checkin {
    id: number;
    patient: {
        id: number;
        full_name: string;
        patient_number: string;
    };
}

interface VitalsRecordingFormProps {
    checkin: Checkin;
    onSuccess: () => void;
    onCancel?: () => void;
    vitalsEndpoint?: string;
    showCancelButton?: boolean;
}

export default function VitalsRecordingForm({
    checkin,
    onSuccess,
    onCancel,
    vitalsEndpoint = '/checkin/vitals',
    showCancelButton = false,
}: VitalsRecordingFormProps) {
    const { data, setData, post, processing, errors, reset } = useForm({
        patient_checkin_id: checkin?.id || 0,
        blood_pressure_systolic: '',
        blood_pressure_diastolic: '',
        temperature: '',
        pulse_rate: '',
        respiratory_rate: '',
        weight: '',
        height: '',
        oxygen_saturation: '',
        notes: '',
    });

    React.useEffect(() => {
        if (checkin) {
            setData('patient_checkin_id', checkin.id);
        }
    }, [checkin]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        post(vitalsEndpoint, {
            onSuccess: () => {
                toast.success('Vital signs recorded successfully');
                reset();
                onSuccess();
            },
            onError: () => {
                toast.error('Failed to record vital signs');
            },
        });
    };

    if (!checkin) {
        return (
            <div className="py-8 text-center text-muted-foreground">
                No patient selected for vitals recording.
            </div>
        );
    }

    return (
        <div className="space-y-4">
            {/* Patient Info */}
            <div className="rounded-lg bg-muted/50 p-4">
                <h3 className="font-medium">{checkin.patient.full_name}</h3>
                <p className="text-sm text-muted-foreground">
                    Patient ID: {checkin.patient.patient_number}
                </p>
            </div>

            <form onSubmit={handleSubmit} className="space-y-4">
                {/* Blood Pressure */}
                <div className="grid grid-cols-2 gap-4">
                    <div className="space-y-2">
                        <Label htmlFor="blood_pressure_systolic">
                            Systolic BP (mmHg)
                        </Label>
                        <Input
                            id="blood_pressure_systolic"
                            type="number"
                            placeholder="120"
                            value={data.blood_pressure_systolic}
                            onChange={(e) =>
                                setData(
                                    'blood_pressure_systolic',
                                    e.target.value,
                                )
                            }
                        />
                        {errors.blood_pressure_systolic && (
                            <p className="text-sm text-destructive">
                                {errors.blood_pressure_systolic}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="blood_pressure_diastolic">
                            Diastolic BP (mmHg)
                        </Label>
                        <Input
                            id="blood_pressure_diastolic"
                            type="number"
                            placeholder="80"
                            value={data.blood_pressure_diastolic}
                            onChange={(e) =>
                                setData(
                                    'blood_pressure_diastolic',
                                    e.target.value,
                                )
                            }
                        />
                        {errors.blood_pressure_diastolic && (
                            <p className="text-sm text-destructive">
                                {errors.blood_pressure_diastolic}
                            </p>
                        )}
                    </div>
                </div>

                {/* Temperature and Pulse */}
                <div className="grid grid-cols-2 gap-4">
                    <div className="space-y-2">
                        <Label htmlFor="temperature">Temperature (°C)</Label>
                        <Input
                            id="temperature"
                            type="number"
                            placeholder="37"
                            value={data.temperature}
                            onChange={(e) =>
                                setData('temperature', e.target.value)
                            }
                        />
                        {errors.temperature && (
                            <p className="text-sm text-destructive">
                                {errors.temperature}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="pulse_rate">Pulse Rate (bpm)</Label>
                        <Input
                            id="pulse_rate"
                            type="number"
                            placeholder="72"
                            value={data.pulse_rate}
                            onChange={(e) =>
                                setData('pulse_rate', e.target.value)
                            }
                        />
                        {errors.pulse_rate && (
                            <p className="text-sm text-destructive">
                                {errors.pulse_rate}
                            </p>
                        )}
                    </div>
                </div>

                {/* Respiratory Rate and Oxygen Saturation */}
                <div className="grid grid-cols-2 gap-4">
                    <div className="space-y-2">
                        <Label htmlFor="respiratory_rate">
                            Respiratory Rate (breaths/min)
                        </Label>
                        <Input
                            id="respiratory_rate"
                            type="number"
                            placeholder="16"
                            value={data.respiratory_rate}
                            onChange={(e) =>
                                setData('respiratory_rate', e.target.value)
                            }
                        />
                        {errors.respiratory_rate && (
                            <p className="text-sm text-destructive">
                                {errors.respiratory_rate}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="oxygen_saturation">
                            Oxygen Saturation (%)
                        </Label>
                        <Input
                            id="oxygen_saturation"
                            type="number"
                            placeholder="98"
                            value={data.oxygen_saturation}
                            onChange={(e) =>
                                setData('oxygen_saturation', e.target.value)
                            }
                        />
                        {errors.oxygen_saturation && (
                            <p className="text-sm text-destructive">
                                {errors.oxygen_saturation}
                            </p>
                        )}
                    </div>
                </div>

                {/* Weight and Height */}
                <div className="grid grid-cols-2 gap-4">
                    <div className="space-y-2">
                        <Label htmlFor="weight">Weight (kg)</Label>
                        <Input
                            id="weight"
                            type="number"
                            step="0.1"
                            placeholder="70.0"
                            value={data.weight}
                            onChange={(e) => setData('weight', e.target.value)}
                        />
                        {errors.weight && (
                            <p className="text-sm text-destructive">
                                {errors.weight}
                            </p>
                        )}
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="height">Height (cm)</Label>
                        <Input
                            id="height"
                            type="number"
                            placeholder="170"
                            value={data.height}
                            onChange={(e) => setData('height', e.target.value)}
                        />
                        {errors.height && (
                            <p className="text-sm text-destructive">
                                {errors.height}
                            </p>
                        )}
                    </div>
                </div>

                {/* Notes */}
                <div className="space-y-2">
                    <Label htmlFor="notes">Notes</Label>
                    <Textarea
                        id="notes"
                        placeholder="Additional observations or notes..."
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                    />
                    {errors.notes && (
                        <p className="text-sm text-destructive">
                            {errors.notes}
                        </p>
                    )}
                </div>

                {/* Action Buttons */}
                <div className="flex justify-end gap-2">
                    {showCancelButton && onCancel && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onCancel}
                        >
                            Cancel
                        </Button>
                    )}
                    <Button type="submit" disabled={processing}>
                        {processing && (
                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                        )}
                        Record Vitals
                    </Button>
                </div>
            </form>
        </div>
    );
}
