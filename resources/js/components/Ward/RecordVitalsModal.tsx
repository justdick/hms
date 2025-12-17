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
import { Textarea } from '@/components/ui/textarea';
import { Form } from '@inertiajs/react';
import {
    Activity,
    Gauge,
    Heart,
    Loader2,
    Thermometer,
    User,
} from 'lucide-react';
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

interface PatientAdmission {
    id: number;
    admission_number: string;
    patient: Patient;
    bed?: Bed;
    admitted_at: string;
}

interface RecordVitalsModalProps {
    open: boolean;
    onClose: () => void;
    admission: PatientAdmission | null;
    onSuccess: () => void;
}

export function RecordVitalsModal({
    open,
    onClose,
    admission,
    onSuccess,
}: RecordVitalsModalProps) {
    if (!admission) {
        return null;
    }

    const calculateAge = (dateOfBirth: string) => {
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

    const patientAge = admission.patient.date_of_birth
        ? calculateAge(admission.patient.date_of_birth)
        : null;

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-h-[90vh] max-w-4xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Record Vital Signs</DialogTitle>
                    <DialogDescription>
                        Record vital signs for {admission.patient.first_name}{' '}
                        {admission.patient.last_name} in{' '}
                        {admission.bed
                            ? `Bed ${admission.bed.bed_number}`
                            : 'ward'}
                        .
                    </DialogDescription>
                </DialogHeader>

                <Form
                    action={`/admissions/${admission.id}/vitals`}
                    method="post"
                    onSuccess={() => {
                        toast.success('Vital signs recorded successfully');
                        onSuccess();
                    }}
                    onError={() => {
                        toast.error('Failed to record vital signs');
                    }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            {/* Patient Information */}
                            <div className="space-y-4 rounded-lg border bg-muted/50 p-4 dark:bg-muted/20">
                                <h3 className="flex items-center gap-2 font-medium">
                                    <User className="h-4 w-4" />
                                    Patient Information
                                </h3>
                                <div className="grid grid-cols-3 gap-4 text-sm">
                                    <div>
                                        <p className="text-muted-foreground">
                                            Name
                                        </p>
                                        <p className="font-medium">
                                            {admission.patient.first_name}{' '}
                                            {admission.patient.last_name}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground">
                                            Age & Gender
                                        </p>
                                        <p className="font-medium">
                                            {patientAge
                                                ? `${patientAge} years`
                                                : 'N/A'}
                                            , {admission.patient.gender}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-muted-foreground">
                                            Admission
                                        </p>
                                        <p className="font-medium">
                                            {admission.admission_number}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {/* Vital Signs */}
                            <div className="space-y-4">
                                <h3 className="flex items-center gap-2 font-medium">
                                    <Activity className="h-4 w-4" />
                                    Vital Signs
                                </h3>

                                {/* Blood Pressure & Temperature */}
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                    <div className="space-y-2">
                                        <Label className="flex items-center gap-2">
                                            <Gauge className="h-4 w-4" />
                                            Blood Pressure (mmHg){' '}
                                            <span className="text-red-500">
                                                *
                                            </span>
                                        </Label>
                                        <div className="flex gap-2">
                                            <Input
                                                name="blood_pressure_systolic"
                                                placeholder="Systolic"
                                                type="number"
                                                min="60"
                                                max="250"
                                                required
                                            />
                                            <span className="flex items-center px-2">
                                                /
                                            </span>
                                            <Input
                                                name="blood_pressure_diastolic"
                                                placeholder="Diastolic"
                                                type="number"
                                                min="40"
                                                max="150"
                                                required
                                            />
                                        </div>
                                        {(errors.blood_pressure_systolic ||
                                            errors.blood_pressure_diastolic) && (
                                            <p className="text-sm text-destructive">
                                                {errors.blood_pressure_systolic ||
                                                    errors.blood_pressure_diastolic}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label
                                            htmlFor="temperature"
                                            className="flex items-center gap-2"
                                        >
                                            <Thermometer className="h-4 w-4" />
                                            Temperature (°C){' '}
                                            <span className="text-red-500">
                                                *
                                            </span>
                                        </Label>
                                        <Input
                                            name="temperature"
                                            id="temperature"
                                            type="number"
                                            step="0.1"
                                            min="35"
                                            max="45"
                                            placeholder="37.0"
                                            required
                                        />
                                        {errors.temperature && (
                                            <p className="text-sm text-destructive">
                                                {errors.temperature}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label
                                            htmlFor="pulse_rate"
                                            className="flex items-center gap-2"
                                        >
                                            <Heart className="h-4 w-4" />
                                            Pulse Rate (BPM){' '}
                                            <span className="text-red-500">
                                                *
                                            </span>
                                        </Label>
                                        <Input
                                            name="pulse_rate"
                                            id="pulse_rate"
                                            type="number"
                                            min="40"
                                            max="200"
                                            placeholder="72"
                                            required
                                        />
                                        {errors.pulse_rate && (
                                            <p className="text-sm text-destructive">
                                                {errors.pulse_rate}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                {/* Respiratory Rate & Oxygen Saturation */}
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="respiratory_rate">
                                            Respiratory Rate (per min){' '}
                                            <span className="text-red-500">
                                                *
                                            </span>
                                        </Label>
                                        <Input
                                            name="respiratory_rate"
                                            id="respiratory_rate"
                                            type="number"
                                            min="8"
                                            max="60"
                                            placeholder="16"
                                            required
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
                                            name="oxygen_saturation"
                                            id="oxygen_saturation"
                                            type="number"
                                            min="70"
                                            max="100"
                                            placeholder="98"
                                        />
                                        {errors.oxygen_saturation && (
                                            <p className="text-sm text-destructive">
                                                {errors.oxygen_saturation}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                {/* Weight & Height */}
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="weight">
                                            Weight (kg)
                                        </Label>
                                        <Input
                                            name="weight"
                                            id="weight"
                                            type="number"
                                            step="0.1"
                                            min="0"
                                            max="500"
                                            placeholder="70.0"
                                        />
                                        {errors.weight && (
                                            <p className="text-sm text-destructive">
                                                {errors.weight}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="height">
                                            Height (cm)
                                        </Label>
                                        <Input
                                            name="height"
                                            id="height"
                                            type="number"
                                            step="0.1"
                                            min="20"
                                            max="300"
                                            placeholder="170.0"
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
                                    <Label htmlFor="notes">
                                        Clinical Notes (Optional)
                                    </Label>
                                    <Textarea
                                        name="notes"
                                        id="notes"
                                        placeholder="Any additional observations or notes..."
                                        rows={3}
                                    />
                                    {errors.notes && (
                                        <p className="text-sm text-destructive">
                                            {errors.notes}
                                        </p>
                                    )}
                                </div>
                            </div>

                            {/* Action Buttons */}
                            <div className="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={onClose}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing && (
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    )}
                                    Record Vitals
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
