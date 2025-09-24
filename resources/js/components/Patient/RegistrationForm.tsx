import React from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Loader2 } from 'lucide-react';
import { useForm } from '@inertiajs/react';
import { toast } from 'sonner';

interface Patient {
  id: number;
  patient_number: string;
  full_name: string;
  age: number;
  gender: string;
  phone_number: string | null;
  has_checkin_today: boolean;
}

interface PatientRegistrationFormProps {
  onPatientRegistered: (patient: Patient) => void;
  onCancel?: () => void;
  registrationEndpoint?: string;
  showCancelButton?: boolean;
}

export default function PatientRegistrationForm({
  onPatientRegistered,
  onCancel,
  registrationEndpoint = '/checkin/patients',
  showCancelButton = false
}: PatientRegistrationFormProps) {
  const { data, setData, post, processing, errors, reset } = useForm({
    first_name: '',
    last_name: '',
    gender: '',
    date_of_birth: '',
    phone_number: '',
    address: '',
    emergency_contact_name: '',
    emergency_contact_phone: '',
    national_id: '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    post(registrationEndpoint, {
      onSuccess: (response: any) => {
        toast.success('Patient registered successfully');
        reset();
        if (response.props?.patient) {
          onPatientRegistered(response.props.patient);
        }
      },
      onError: () => {
        toast.error('Failed to register patient');
      },
    });
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-2">
          <Label htmlFor="first_name">First Name *</Label>
          <Input
            id="first_name"
            value={data.first_name}
            onChange={(e) => setData('first_name', e.target.value)}
            required
          />
          {errors.first_name && (
            <p className="text-sm text-destructive">{errors.first_name}</p>
          )}
        </div>

        <div className="space-y-2">
          <Label htmlFor="last_name">Last Name *</Label>
          <Input
            id="last_name"
            value={data.last_name}
            onChange={(e) => setData('last_name', e.target.value)}
            required
          />
          {errors.last_name && (
            <p className="text-sm text-destructive">{errors.last_name}</p>
          )}
        </div>
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-2">
          <Label htmlFor="gender">Gender *</Label>
          <select
            id="gender"
            value={data.gender}
            onChange={(e) => setData('gender', e.target.value)}
            className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background"
            required
          >
            <option value="">Select gender</option>
            <option value="male">Male</option>
            <option value="female">Female</option>
          </select>
          {errors.gender && (
            <p className="text-sm text-destructive">{errors.gender}</p>
          )}
        </div>

        <div className="space-y-2">
          <Label htmlFor="date_of_birth">Date of Birth *</Label>
          <Input
            id="date_of_birth"
            type="date"
            value={data.date_of_birth}
            onChange={(e) => setData('date_of_birth', e.target.value)}
            required
          />
          {errors.date_of_birth && (
            <p className="text-sm text-destructive">{errors.date_of_birth}</p>
          )}
        </div>
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-2">
          <Label htmlFor="phone_number">Phone Number</Label>
          <Input
            id="phone_number"
            value={data.phone_number}
            onChange={(e) => setData('phone_number', e.target.value)}
            placeholder="+255..."
          />
        </div>

        <div className="space-y-2">
          <Label htmlFor="national_id">National ID</Label>
          <Input
            id="national_id"
            value={data.national_id}
            onChange={(e) => setData('national_id', e.target.value)}
          />
        </div>
      </div>

      <div className="space-y-2">
        <Label htmlFor="address">Address</Label>
        <Input
          id="address"
          value={data.address}
          onChange={(e) => setData('address', e.target.value)}
        />
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div className="space-y-2">
          <Label htmlFor="emergency_contact_name">Emergency Contact Name</Label>
          <Input
            id="emergency_contact_name"
            value={data.emergency_contact_name}
            onChange={(e) => setData('emergency_contact_name', e.target.value)}
          />
        </div>

        <div className="space-y-2">
          <Label htmlFor="emergency_contact_phone">Emergency Contact Phone</Label>
          <Input
            id="emergency_contact_phone"
            value={data.emergency_contact_phone}
            onChange={(e) => setData('emergency_contact_phone', e.target.value)}
          />
        </div>
      </div>

      <div className="flex justify-end gap-2">
        {showCancelButton && onCancel && (
          <Button type="button" variant="outline" onClick={onCancel}>
            Cancel
          </Button>
        )}
        <Button type="submit" disabled={processing}>
          {processing && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
          Register & Check-in
        </Button>
      </div>
    </form>
  );
}