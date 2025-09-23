import React from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Loader2, Activity, User, Thermometer, Heart, Gauge } from 'lucide-react';
import { useForm } from '@inertiajs/react';
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

interface Checkin {
  id: number;
  patient: Patient;
  department: Department;
  status: string;
  checked_in_at: string;
}

interface VitalsModalProps {
  open: boolean;
  onClose: () => void;
  checkin: Checkin | null;
  onSuccess: () => void;
}

export default function VitalsModal({ open, onClose, checkin, onSuccess }: VitalsModalProps) {
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

    post('/checkin/vitals', {
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

  const handleModalClose = () => {
    reset();
    onClose();
  };

  if (!checkin) {
    return null;
  }

  return (
    <Dialog open={open} onOpenChange={handleModalClose}>
      <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Record Vital Signs</DialogTitle>
          <DialogDescription>
            Record vital signs for {checkin.patient.full_name} in {checkin.department.name}.
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Patient Information */}
          <div className="space-y-4 p-4 border rounded-lg bg-muted/50">
            <h3 className="font-medium flex items-center gap-2">
              <User className="h-4 w-4" />
              Patient Information
            </h3>
            <div className="grid grid-cols-3 gap-4 text-sm">
              <div>
                <p className="text-muted-foreground">Name</p>
                <p className="font-medium">{checkin.patient.full_name}</p>
              </div>
              <div>
                <p className="text-muted-foreground">Age & Gender</p>
                <p className="font-medium">{checkin.patient.age} years, {checkin.patient.gender}</p>
              </div>
              <div>
                <p className="text-muted-foreground">Department</p>
                <p className="font-medium">{checkin.department.name}</p>
              </div>
            </div>
          </div>

          {/* Vital Signs */}
          <div className="space-y-4">
            <h3 className="font-medium flex items-center gap-2">
              <Activity className="h-4 w-4" />
              Vital Signs
            </h3>

            {/* Blood Pressure & Temperature */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className="space-y-2">
                <Label className="flex items-center gap-2">
                  <Gauge className="h-4 w-4" />
                  Blood Pressure (mmHg)
                </Label>
                <div className="flex gap-2">
                  <Input
                    placeholder="Systolic"
                    type="number"
                    step="0.01"
                    min="0"
                    max="300"
                    value={data.blood_pressure_systolic}
                    onChange={(e) => setData('blood_pressure_systolic', e.target.value)}
                  />
                  <span className="flex items-center px-2">/</span>
                  <Input
                    placeholder="Diastolic"
                    type="number"
                    step="0.01"
                    min="0"
                    max="200"
                    value={data.blood_pressure_diastolic}
                    onChange={(e) => setData('blood_pressure_diastolic', e.target.value)}
                  />
                </div>
                {(errors.blood_pressure_systolic || errors.blood_pressure_diastolic) && (
                  <p className="text-sm text-destructive">
                    {errors.blood_pressure_systolic || errors.blood_pressure_diastolic}
                  </p>
                )}
              </div>

              <div className="space-y-2">
                <Label htmlFor="temperature" className="flex items-center gap-2">
                  <Thermometer className="h-4 w-4" />
                  Temperature (°C)
                </Label>
                <Input
                  id="temperature"
                  type="number"
                  step="0.1"
                  min="30"
                  max="45"
                  placeholder="37.0"
                  value={data.temperature}
                  onChange={(e) => setData('temperature', e.target.value)}
                />
                {errors.temperature && (
                  <p className="text-sm text-destructive">{errors.temperature}</p>
                )}
              </div>

              <div className="space-y-2">
                <Label htmlFor="pulse_rate" className="flex items-center gap-2">
                  <Heart className="h-4 w-4" />
                  Pulse Rate (BPM)
                </Label>
                <Input
                  id="pulse_rate"
                  type="number"
                  min="30"
                  max="200"
                  placeholder="72"
                  value={data.pulse_rate}
                  onChange={(e) => setData('pulse_rate', e.target.value)}
                />
                {errors.pulse_rate && (
                  <p className="text-sm text-destructive">{errors.pulse_rate}</p>
                )}
              </div>
            </div>

            {/* Respiratory Rate & Oxygen Saturation */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="respiratory_rate">Respiratory Rate (per min)</Label>
                <Input
                  id="respiratory_rate"
                  type="number"
                  min="5"
                  max="60"
                  placeholder="16"
                  value={data.respiratory_rate}
                  onChange={(e) => setData('respiratory_rate', e.target.value)}
                />
                {errors.respiratory_rate && (
                  <p className="text-sm text-destructive">{errors.respiratory_rate}</p>
                )}
              </div>

              <div className="space-y-2">
                <Label htmlFor="oxygen_saturation">Oxygen Saturation (%)</Label>
                <Input
                  id="oxygen_saturation"
                  type="number"
                  min="50"
                  max="100"
                  placeholder="98"
                  value={data.oxygen_saturation}
                  onChange={(e) => setData('oxygen_saturation', e.target.value)}
                />
                {errors.oxygen_saturation && (
                  <p className="text-sm text-destructive">{errors.oxygen_saturation}</p>
                )}
              </div>
            </div>

            {/* Weight & Height */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="weight">Weight (kg)</Label>
                <Input
                  id="weight"
                  type="number"
                  step="0.1"
                  min="0"
                  max="500"
                  placeholder="70.0"
                  value={data.weight}
                  onChange={(e) => setData('weight', e.target.value)}
                />
                {errors.weight && (
                  <p className="text-sm text-destructive">{errors.weight}</p>
                )}
              </div>

              <div className="space-y-2">
                <Label htmlFor="height">Height (cm)</Label>
                <Input
                  id="height"
                  type="number"
                  step="0.1"
                  min="20"
                  max="300"
                  placeholder="170.0"
                  value={data.height}
                  onChange={(e) => setData('height', e.target.value)}
                />
                {errors.height && (
                  <p className="text-sm text-destructive">{errors.height}</p>
                )}
              </div>
            </div>

            {/* Notes */}
            <div className="space-y-2">
              <Label htmlFor="notes">Clinical Notes (Optional)</Label>
              <Textarea
                id="notes"
                placeholder="Any additional observations or notes..."
                value={data.notes}
                onChange={(e) => setData('notes', e.target.value)}
                rows={3}
              />
              {errors.notes && (
                <p className="text-sm text-destructive">{errors.notes}</p>
              )}
            </div>
          </div>

          {/* Action Buttons */}
          <div className="flex justify-end gap-2">
            <Button type="button" variant="outline" onClick={handleModalClose}>
              Cancel
            </Button>
            <Button type="submit" disabled={processing}>
              {processing && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
              Record Vitals
            </Button>
          </div>
        </form>
      </DialogContent>
    </Dialog>
  );
}