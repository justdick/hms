import React from 'react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';

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

interface Checkin {
  id: number;
  patient: Patient;
  department: Department;
  status: string;
  checked_in_at: string;
  vitals_taken_at: string | null;
}

interface TodaysListProps {
  checkins: Checkin[];
  onRecordVitals: (checkin: Checkin) => void;
  emptyMessage?: string;
}

export default function TodaysList({
  checkins,
  onRecordVitals,
  emptyMessage = "No patients checked in today yet."
}: TodaysListProps) {

  const getStatusBadge = (status: string) => {
    const statusConfig = {
      checked_in: { label: 'Checked In', variant: 'default' as const },
      vitals_taken: { label: 'Vitals Taken', variant: 'secondary' as const },
      awaiting_consultation: { label: 'Awaiting Doctor', variant: 'outline' as const },
      in_consultation: { label: 'In Consultation', variant: 'destructive' as const },
      completed: { label: 'Completed', variant: 'default' as const },
    };

    const config = statusConfig[status as keyof typeof statusConfig] || statusConfig.checked_in;
    return <Badge variant={config.variant}>{config.label}</Badge>;
  };

  if (checkins.length === 0) {
    return (
      <div className="text-center py-8 text-muted-foreground">
        {emptyMessage}
      </div>
    );
  }

  return (
    <div className="space-y-4 max-h-96 overflow-y-auto">
      {checkins.map((checkin) => (
        <div
          key={checkin.id}
          className="flex items-center justify-between p-4 border rounded-lg"
        >
          <div className="flex-1">
            <div className="space-y-1">
              <h3 className="font-medium">{checkin.patient.full_name}</h3>
              <p className="text-sm text-muted-foreground">
                {checkin.patient.patient_number} • {checkin.patient.age} years • {checkin.patient.gender}
              </p>
              <p className="text-sm text-muted-foreground">
                {checkin.department.name} • {new Date(checkin.checked_in_at).toLocaleTimeString()}
              </p>
            </div>
          </div>
          <div className="flex flex-col items-end gap-2">
            {getStatusBadge(checkin.status)}
            {checkin.status === 'checked_in' && (
              <Button
                size="sm"
                variant="outline"
                onClick={() => onRecordVitals(checkin)}
              >
                Record Vitals
              </Button>
            )}
          </div>
        </div>
      ))}
    </div>
  );
}