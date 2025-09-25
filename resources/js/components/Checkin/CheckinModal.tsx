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
import { Form } from '@inertiajs/react';
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
  const handleModalClose = () => {
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

        <Form
          action="/checkin/checkins"
          method="post"
          onSuccess={() => {
            toast.success('Patient checked in successfully');
            onSuccess();
          }}
          onError={() => {
            toast.error('Failed to check in patient');
          }}
          className="space-y-6"
        >
          {({ processing, errors }) => (
            <>
              {/* Hidden patient_id field */}
              <input type="hidden" name="patient_id" defaultValue={patient.id} />

              {/* Patient Information */}
              <div className="space-y-4 p-4 border rounded-lg bg-muted/50">
                <h3 className="font-medium flex items-center gap-2">
                  <User className="h-4 w-4" />
                  Patient Information
                </h3>
                {errors.patient_id && (
                  <p className="text-sm text-destructive bg-destructive/10 p-2 rounded">
                    {errors.patient_id}
                  </p>
                )}
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
                  <Label htmlFor="department_id">Select Clinic/Department *</Label>
                  <select
                    name="department_id"
                    id="department_id"
                    required
                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                  >
                    <option value="">Choose a clinic...</option>
                    {departments.map((department) => (
                      <option key={department.id} value={department.id}>
                        {department.name} - {department.description}
                      </option>
                    ))}
                  </select>
                  {errors.department_id && (
                    <p className="text-sm text-destructive">{errors.department_id}</p>
                  )}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="notes">Notes (Optional)</Label>
                  <textarea
                    name="notes"
                    id="notes"
                    placeholder="Any additional notes about the patient's visit..."
                    rows={3}
                    className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
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
            </>
          )}
        </Form>
      </DialogContent>
    </Dialog>
  );
}