import React, { useState, useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import {
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from '@/components/ui/tabs';
import { Search, UserPlus, Activity, Clock, Stethoscope, Users, Loader2 } from 'lucide-react';
import CheckinModal from '@/components/Checkin/CheckinModal';
import VitalsModal from '@/components/Checkin/VitalsModal';
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

export default function OPDIndex({ todayCheckins, departments }: Props) {
  const [checkinModalOpen, setCheckinModalOpen] = useState(false);
  const [vitalsModalOpen, setVitalsModalOpen] = useState(false);
  const [selectedPatient, setSelectedPatient] = useState<Patient | null>(null);
  const [selectedCheckin, setSelectedCheckin] = useState<Checkin | null>(null);

  // Patient search state
  const [searchQuery, setSearchQuery] = useState('');
  const [searchResults, setSearchResults] = useState<Patient[]>([]);
  const [isSearching, setIsSearching] = useState(false);

  // Patient registration form
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

  // Search functionality
  const searchPatients = async (query: string) => {
    if (query.length < 2) {
      setSearchResults([]);
      return;
    }

    setIsSearching(true);
    try {
      const response = await fetch(`/checkin/patients/search?search=${encodeURIComponent(query)}`);
      const result = await response.json();
      setSearchResults(result.patients || []);
    } catch (error) {
      console.error('Search error:', error);
      toast.error('Failed to search patients');
    } finally {
      setIsSearching(false);
    }
  };

  useEffect(() => {
    const timeoutId = setTimeout(() => {
      searchPatients(searchQuery);
    }, 300);

    return () => clearTimeout(timeoutId);
  }, [searchQuery]);

  const handlePatientSelected = (patient: Patient) => {
    if (patient.has_checkin_today) {
      toast.error('Patient is already checked in today');
      return;
    }
    setSelectedPatient(patient);
    setCheckinModalOpen(true);
  };

  const handleRegisterPatient = (e: React.FormEvent) => {
    e.preventDefault();

    post('/checkin/patients', {
      onSuccess: (response: any) => {
        toast.success('Patient registered successfully');
        reset();
        if (response.props?.patient) {
          handlePatientSelected(response.props.patient);
        }
      },
      onError: () => {
        toast.error('Failed to register patient');
      },
    });
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
                  <div className="space-y-2">
                    <Label htmlFor="search">Search by name, patient number, or phone</Label>
                    <div className="relative">
                      <Search className="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
                      <Input
                        id="search"
                        placeholder="Enter patient name, number, or phone..."
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        className="pl-10"
                      />
                      {isSearching && (
                        <Loader2 className="absolute right-3 top-3 h-4 w-4 animate-spin text-muted-foreground" />
                      )}
                    </div>
                  </div>

                  <div className="space-y-2 max-h-96 overflow-y-auto">
                    {searchResults.length === 0 && searchQuery.length >= 2 && !isSearching && (
                      <div className="text-center py-8 text-muted-foreground">
                        No patients found matching "{searchQuery}"
                      </div>
                    )}

                    {searchResults.map((patient) => (
                      <div
                        key={patient.id}
                        className={`p-4 border rounded-lg cursor-pointer transition-colors ${
                          patient.has_checkin_today
                            ? 'bg-muted/50 cursor-not-allowed'
                            : 'hover:bg-muted/50'
                        }`}
                        onClick={() => handlePatientSelected(patient)}
                      >
                        <div className="flex items-center justify-between">
                          <div>
                            <h3 className="font-medium">{patient.full_name}</h3>
                            <p className="text-sm text-muted-foreground">
                              {patient.patient_number} • {patient.age} years • {patient.gender}
                            </p>
                            {patient.phone_number && (
                              <p className="text-sm text-muted-foreground">{patient.phone_number}</p>
                            )}
                          </div>
                          <div className="flex gap-2">
                            {patient.has_checkin_today && (
                              <Badge variant="secondary">Checked in today</Badge>
                            )}
                            {!patient.has_checkin_today && (
                              <Badge variant="outline">Available</Badge>
                            )}
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                </TabsContent>

                <TabsContent value="register" className="space-y-4">
                  <form onSubmit={handleRegisterPatient} className="space-y-4">
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

                    <div className="flex justify-end">
                      <Button type="submit" disabled={processing}>
                        {processing && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                        Register & Check-in
                      </Button>
                    </div>
                  </form>
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
              {todayCheckins.length === 0 ? (
                <div className="text-center py-8 text-muted-foreground">
                  No patients checked in today yet.
                </div>
              ) : (
                <div className="space-y-4 max-h-96 overflow-y-auto">
                  {todayCheckins.map((checkin) => (
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
                            onClick={() => handleRecordVitals(checkin)}
                          >
                            Record Vitals
                          </Button>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              )}
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