import React from 'react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Loader2, Calendar, User, MapPin } from 'lucide-react';
import { useForm } from '@inertiajs/react';
import { toast } from 'sonner';

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

interface CheckinModalProps {
  open: boolean;
  onClose: () => void;
  patient: Patient | null;
  departments: Department[];
  onSuccess: () => void;
}

export default function CheckinModal({ open, onClose, patient, departments, onSuccess }: CheckinModalProps) {
  const { data, setData, post, processing, errors, reset } = useForm({
    patient_id: patient?.id || 0,
    department_id: '',
    notes: '',
  });

  React.useEffect(() => {
    if (patient) {
      setData('patient_id', patient.id);
    }
  }, [patient]);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    if (!data.department_id) {
      toast.error('Please select a department');
      return;
    }

    post('/checkin/checkins', {
      onSuccess: () => {
        toast.success('Patient checked in successfully');
        reset();
        onSuccess();
      },
      onError: () => {
        toast.error('Failed to check in patient');
      },
    });
  };

  const handleModalClose = () => {
    reset();
    onClose();
  };

  if (!patient) {
    return null;
  }

  return (
    <Dialog open={open} onOpenChange={handleModalClose}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>Check-in Patient</DialogTitle>
          <DialogDescription>
            Check in {patient.full_name} to a clinic for consultation.
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Patient Information */}
          <div className="space-y-4 p-4 border rounded-lg bg-muted/50">
            <h3 className="font-medium flex items-center gap-2">
              <User className="h-4 w-4" />
              Patient Information
            </h3>
            <div className="grid grid-cols-2 gap-4 text-sm">
              <div>
                <p className="text-muted-foreground">Name</p>
                <p className="font-medium">{patient.full_name}</p>
              </div>
              <div>
                <p className="text-muted-foreground">Patient Number</p>
                <p className="font-medium">{patient.patient_number}</p>
              </div>
              <div>
                <p className="text-muted-foreground">Age & Gender</p>
                <p className="font-medium">{patient.age} years, {patient.gender}</p>
              </div>
              <div>
                <p className="text-muted-foreground">Phone</p>
                <p className="font-medium">{patient.phone_number || 'Not provided'}</p>
              </div>
            </div>
          </div>

          {/* Check-in Details */}
          <div className="space-y-4">
            <h3 className="font-medium flex items-center gap-2">
              <Calendar className="h-4 w-4" />
              Check-in Details
            </h3>

            <div className="space-y-2">
              <Label htmlFor="department">Select Clinic/Department *</Label>
              <Select
                value={data.department_id.toString()}
                onValueChange={(value) => setData('department_id', value)}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Choose a clinic..." />
                </SelectTrigger>
                <SelectContent>
                  {departments.map((department) => (
                    <SelectItem key={department.id} value={department.id.toString()}>
                      <div className="flex items-center gap-2">
                        <MapPin className="h-4 w-4" />
                        <div>
                          <p className="font-medium">{department.name}</p>
                          <p className="text-xs text-muted-foreground">{department.description}</p>
                        </div>
                      </div>
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {errors.department_id && (
                <p className="text-sm text-destructive">{errors.department_id}</p>
              )}
            </div>

            <div className="space-y-2">
              <Label htmlFor="notes">Notes (Optional)</Label>
              <Textarea
                id="notes"
                placeholder="Any additional notes about the patient's visit..."
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
              Check-in Patient
            </Button>
          </div>
        </form>
      </DialogContent>
    </Dialog>
  );
}