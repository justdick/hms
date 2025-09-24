import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from '@/components/ui/tabs';
import { Search, UserPlus } from 'lucide-react';
import CheckinModal from '@/components/Checkin/CheckinModal';
import VitalsModal from '@/components/Checkin/VitalsModal';
import PatientSearchForm from '@/components/Patient/SearchForm';
import PatientRegistrationForm from '@/components/Patient/RegistrationForm';
import TodaysList from '@/components/Checkin/TodaysList';

interface Patient {
  id: number;
  patient_number: string;
  full_name: string;
  age: number;
  gender: string;
  phone_number: string | null;
  has_checkin_today: boolean;
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

interface Props {
  todayCheckins: Checkin[];
  departments: Department[];
}

export default function CheckinIndex({ todayCheckins, departments }: Props) {
  const [checkinModalOpen, setCheckinModalOpen] = useState(false);
  const [vitalsModalOpen, setVitalsModalOpen] = useState(false);
  const [selectedPatient, setSelectedPatient] = useState<Patient | null>(null);
  const [selectedCheckin, setSelectedCheckin] = useState<Checkin | null>(null);

  const handlePatientSelected = (patient: Patient) => {
    setSelectedPatient(patient);
    setCheckinModalOpen(true);
  };

  const handlePatientRegistered = (patient: Patient) => {
    setSelectedPatient(patient);
    setCheckinModalOpen(true);
  };

  const handleCheckinSuccess = () => {
    setCheckinModalOpen(false);
    setSelectedPatient(null);
    // Refresh the page to show updated data
    router.visit('/checkin', { preserveState: false });
  };

  const handleRecordVitals = (checkin: Checkin) => {
    setSelectedCheckin(checkin);
    setVitalsModalOpen(true);
  };

  const handleVitalsSuccess = () => {
    setVitalsModalOpen(false);
    setSelectedCheckin(null);
    // Refresh the page to show updated data
    router.visit('/checkin', { preserveState: false });
  };

  return (
    <AppLayout>
      <Head title="Check-in Dashboard" />

      <div className="space-y-6">
        {/* Header */}
        <div>
          <h1 className="text-3xl font-bold tracking-tight">Patient Check-in</h1>
          <p className="text-muted-foreground">
            Search or register patients and manage check-ins
          </p>
        </div>

        {/* Main Content - Two Column Layout */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {/* Left Column - Patient Search & Registration */}
          <Card>
            <CardHeader>
              <CardTitle>Find or Register Patient</CardTitle>
              <CardDescription>
                Search for existing patients or register a new patient for check-in.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <Tabs defaultValue="search" className="w-full">
                <TabsList className="grid w-full grid-cols-2">
                  <TabsTrigger value="search" className="gap-2">
                    <Search className="h-4 w-4" />
                    Search Patient
                  </TabsTrigger>
                  <TabsTrigger value="register" className="gap-2">
                    <UserPlus className="h-4 w-4" />
                    Register New
                  </TabsTrigger>
                </TabsList>

                <TabsContent value="search" className="space-y-4">
                  <PatientSearchForm onPatientSelected={handlePatientSelected} />
                </TabsContent>

                <TabsContent value="register" className="space-y-4">
                  <PatientRegistrationForm onPatientRegistered={handlePatientRegistered} />
                </TabsContent>
              </Tabs>
            </CardContent>
          </Card>

          {/* Right Column - Today's Check-ins */}
          <Card>
            <CardHeader>
              <CardTitle>Today's Check-ins</CardTitle>
              <CardDescription>
                Patients checked in today - {new Date().toLocaleDateString()}
              </CardDescription>
            </CardHeader>
            <CardContent>
              <TodaysList
                checkins={todayCheckins}
                onRecordVitals={handleRecordVitals}
              />
            </CardContent>
          </Card>
        </div>
      </div>

      {/* Modals */}
      <CheckinModal
        open={checkinModalOpen}
        onClose={() => setCheckinModalOpen(false)}
        patient={selectedPatient}
        departments={departments}
        onSuccess={handleCheckinSuccess}
      />

      <VitalsModal
        open={vitalsModalOpen}
        onClose={() => setVitalsModalOpen(false)}
        checkin={selectedCheckin}
        onSuccess={handleVitalsSuccess}
      />
    </AppLayout>
  );
}