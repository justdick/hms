import CheckinModal from '@/components/Checkin/CheckinModal';
import CheckinPromptDialog from '@/components/Checkin/CheckinPromptDialog';
import TodaysList from '@/components/Checkin/TodaysList';
import VitalsModal from '@/components/Checkin/VitalsModal';
import PatientRegistrationForm from '@/components/Patient/RegistrationForm';
import PatientSearchForm from '@/components/Patient/SearchForm';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import patientNumbering from '@/routes/patient-numbering';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { CalendarIcon, Search, Settings, UserPlus } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Patient {
    id: number;
    patient_number: string;
    full_name: string;
    age: number;
    gender: string;
    phone_number: string | null;
    has_checkin_today?: boolean;
}

interface Department {
    id: number;
    name: string;
    code: string;
    description: string;
}

interface InsurancePlan {
    id: number;
    plan_name: string;
    plan_code: string;
    provider: {
        id: number;
        name: string;
        code: string;
        is_nhis?: boolean;
    };
}

interface NhisSettings {
    verification_mode: 'manual' | 'extension';
    nhia_portal_url: string;
    auto_open_portal: boolean;
    credentials?: {
        username: string;
        password: string;
    } | null;
}

interface VitalSigns {
    id: number;
    blood_pressure_systolic: number | null;
    blood_pressure_diastolic: number | null;
    temperature: number | null;
    pulse_rate: number | null;
    respiratory_rate: number | null;
    oxygen_saturation: number | null;
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
    vitals_taken_at: string | null;
    vital_signs?: VitalSigns[];
}

interface Props {
    todayCheckins: Checkin[];
    departments: Department[];
    insurancePlans: InsurancePlan[];
    nhisSettings?: NhisSettings;
    permissions: {
        canViewAnyDate: boolean;
        canViewAnyDepartment: boolean;
        canUpdateDate: boolean;
        canCancelCheckin: boolean;
        canEditVitals: boolean;
    };
    patient?: {
        id: number;
        patient_number: string;
        full_name: string;
        age: number;
        gender: string;
        phone_number: string | null;
        has_checkin_today?: boolean;
    };
}

export default function CheckinIndex({
    todayCheckins,
    departments,
    insurancePlans,
    nhisSettings,
    permissions,
}: Props) {
    const page = usePage();
    const [checkinModalOpen, setCheckinModalOpen] = useState(false);
    const [checkinPromptOpen, setCheckinPromptOpen] = useState(false);
    const [vitalsModalOpen, setVitalsModalOpen] = useState(false);
    const [vitalsMode, setVitalsMode] = useState<'create' | 'edit'>('create');
    const [selectedPatient, setSelectedPatient] = useState<Patient | null>(
        null,
    );
    const [selectedCheckin, setSelectedCheckin] = useState<Checkin | null>(
        null,
    );

    // Search state
    const [searchDate, setSearchDate] = useState<string>(
        new Date().toISOString().split('T')[0],
    );
    const [searchDepartment, setSearchDepartment] = useState<string>('');
    const [searchResults, setSearchResults] = useState<Checkin[]>([]);
    const [isSearching, setIsSearching] = useState(false);
    const [searchError, setSearchError] = useState<string | null>(null);

    // Check for newly registered patient from flash data
    useEffect(() => {
        const flashPatient = page.props.patient as Patient | undefined;
        if (flashPatient && !selectedPatient) {
            setSelectedPatient({
                ...flashPatient,
                has_checkin_today: flashPatient.has_checkin_today ?? false,
            });
            setCheckinPromptOpen(true);
        }
    }, [page.props.patient]);

    const handlePatientSelected = (patient: Patient) => {
        setSelectedPatient(patient);
        setCheckinModalOpen(true);
    };

    const handlePatientRegistered = (patient: Patient) => {
        setSelectedPatient(patient);
        setCheckinPromptOpen(true);
    };

    const handleCheckinNow = () => {
        setCheckinPromptOpen(false);
        setCheckinModalOpen(true);
    };

    const handleCheckinLater = () => {
        setCheckinPromptOpen(false);
        setSelectedPatient(null);
    };

    const handleCheckinSuccess = () => {
        setCheckinModalOpen(false);
        setSelectedPatient(null);
        // Refresh the page to show updated data
        router.reload({ only: ['todayCheckins'] });
    };

    const handleRecordVitals = (checkin: Checkin) => {
        setSelectedCheckin(checkin);
        setVitalsMode('create');
        setVitalsModalOpen(true);
    };

    const handleEditVitals = (checkin: Checkin) => {
        setSelectedCheckin(checkin);
        setVitalsMode('edit');
        setVitalsModalOpen(true);
    };

    const handleVitalsSuccess = () => {
        setVitalsModalOpen(false);
        setSelectedCheckin(null);
        setVitalsMode('create');
        // Refresh the page to show updated data
        router.reload({ only: ['todayCheckins'] });
    };

    const handleDateUpdated = (checkinId: number, newDate: string) => {
        // Update the check-in date in search results without refetching
        setSearchResults((prev) =>
            prev.map((checkin) =>
                checkin.id === checkinId
                    ? { ...checkin, checked_in_at: newDate }
                    : checkin,
            ),
        );
    };

    const handleDepartmentUpdated = (
        checkinId: number,
        departmentId: number,
    ) => {
        // Update the check-in department in search results without refetching
        const department = departments.find((d) => d.id === departmentId);
        if (department) {
            setSearchResults((prev) =>
                prev.map((checkin) =>
                    checkin.id === checkinId
                        ? { ...checkin, department }
                        : checkin,
                ),
            );
        }
    };

    const handleSearch = async () => {
        if (!searchDate) {
            setSearchError('Please select a date');
            return;
        }

        setIsSearching(true);
        setSearchError(null);

        try {
            const params = new URLSearchParams({
                date: searchDate,
                ...(searchDepartment && { department_id: searchDepartment }),
            });

            const csrfToken = document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content');

            const response = await fetch(
                `/checkin/checkins/search?${params.toString()}`,
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken || '',
                    },
                    credentials: 'same-origin',
                },
            );

            if (!response.ok) {
                const error = await response.json().catch(() => ({}));
                throw new Error(error.error || 'Failed to search check-ins');
            }

            const data = await response.json();
            setSearchResults(data.checkins);
        } catch (error) {
            setSearchError(
                error instanceof Error
                    ? error.message
                    : 'Failed to search check-ins',
            );
        } finally {
            setIsSearching(false);
        }
    };

    return (
        <AppLayout>
            <Head title="Check-in Dashboard" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">
                            Patient Check-in
                        </h1>
                        <p className="text-muted-foreground">
                            Search or register patients and manage check-ins
                        </p>
                    </div>
                    <Link href={patientNumbering.index.url()}>
                        <Button variant="outline" size="sm" className="gap-2">
                            <Settings className="h-4 w-4" />
                            Patient Configuration
                        </Button>
                    </Link>
                </div>

                {/* Main Content - Two Column Layout */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* Left Column - Patient Search & Registration */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Find or Register Patient</CardTitle>
                            <CardDescription>
                                Search for existing patients or register a new
                                patient for check-in.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Tabs defaultValue="search" className="w-full">
                                <TabsList className="grid w-full grid-cols-2">
                                    <TabsTrigger
                                        value="search"
                                        className="gap-2"
                                    >
                                        <Search className="h-4 w-4" />
                                        Search Patient
                                    </TabsTrigger>
                                    <TabsTrigger
                                        value="register"
                                        className="gap-2"
                                    >
                                        <UserPlus className="h-4 w-4" />
                                        Register New
                                    </TabsTrigger>
                                </TabsList>

                                <TabsContent
                                    value="search"
                                    className="space-y-4"
                                >
                                    <PatientSearchForm
                                        onPatientSelected={
                                            handlePatientSelected
                                        }
                                    />
                                </TabsContent>

                                <TabsContent
                                    value="register"
                                    className="space-y-4"
                                >
                                    <PatientRegistrationForm
                                        onPatientRegistered={
                                            handlePatientRegistered
                                        }
                                        insurancePlans={insurancePlans}
                                        nhisSettings={nhisSettings}
                                    />
                                </TabsContent>
                            </Tabs>
                        </CardContent>
                    </Card>

                    {/* Right Column - Check-ins List with Tabs */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Check-ins Management</CardTitle>
                            <CardDescription>
                                View today's check-ins or search by date and
                                department
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Tabs defaultValue="today" className="w-full">
                                <TabsList className="grid w-full grid-cols-2">
                                    <TabsTrigger value="today">
                                        Today's List
                                    </TabsTrigger>
                                    {permissions.canViewAnyDate && (
                                        <TabsTrigger
                                            value="search"
                                            className="gap-2"
                                        >
                                            <CalendarIcon className="h-4 w-4" />
                                            Search by Date
                                        </TabsTrigger>
                                    )}
                                </TabsList>

                                <TabsContent
                                    value="today"
                                    className="space-y-4"
                                >
                                    <div className="mb-2 text-sm text-muted-foreground">
                                        {new Date().toLocaleDateString(
                                            'en-US',
                                            {
                                                weekday: 'long',
                                                year: 'numeric',
                                                month: 'long',
                                                day: 'numeric',
                                            },
                                        )}
                                    </div>
                                    <TodaysList
                                        checkins={todayCheckins}
                                        departments={departments}
                                        onRecordVitals={handleRecordVitals}
                                        onEditVitals={handleEditVitals}
                                        canCancelCheckin={
                                            permissions.canCancelCheckin
                                        }
                                        canEditVitals={
                                            permissions.canEditVitals
                                        }
                                    />
                                </TabsContent>

                                {permissions.canViewAnyDate && (
                                    <TabsContent
                                        value="search"
                                        className="space-y-4"
                                    >
                                        <div className="space-y-4">
                                            <div className="grid gap-4 md:grid-cols-2">
                                                <div className="space-y-2">
                                                    <Label htmlFor="search-date">
                                                        Date
                                                    </Label>
                                                    <Input
                                                        id="search-date"
                                                        type="date"
                                                        value={searchDate}
                                                        onChange={(e) =>
                                                            setSearchDate(
                                                                e.target.value,
                                                            )
                                                        }
                                                        max={
                                                            new Date()
                                                                .toISOString()
                                                                .split('T')[0]
                                                        }
                                                    />
                                                </div>

                                                <div className="space-y-2">
                                                    <Label htmlFor="search-department">
                                                        Department (Optional)
                                                    </Label>
                                                    <Select
                                                        value={
                                                            searchDepartment ||
                                                            'all'
                                                        }
                                                        onValueChange={(
                                                            value,
                                                        ) =>
                                                            setSearchDepartment(
                                                                value === 'all'
                                                                    ? ''
                                                                    : value,
                                                            )
                                                        }
                                                    >
                                                        <SelectTrigger id="search-department">
                                                            <SelectValue placeholder="All Departments" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="all">
                                                                All Departments
                                                            </SelectItem>
                                                            {departments.map(
                                                                (dept) => (
                                                                    <SelectItem
                                                                        key={
                                                                            dept.id
                                                                        }
                                                                        value={dept.id.toString()}
                                                                    >
                                                                        {
                                                                            dept.name
                                                                        }
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </div>

                                            <Button
                                                onClick={handleSearch}
                                                disabled={isSearching}
                                                className="w-full"
                                            >
                                                {isSearching
                                                    ? 'Searching...'
                                                    : 'Search Check-ins'}
                                            </Button>

                                            {searchError && (
                                                <div className="rounded-md bg-destructive/10 p-3 text-sm text-destructive">
                                                    {searchError}
                                                </div>
                                            )}

                                            {searchResults.length > 0 && (
                                                <div>
                                                    <div className="mb-2 text-sm text-muted-foreground">
                                                        Found{' '}
                                                        {searchResults.length}{' '}
                                                        check-in
                                                        {searchResults.length !==
                                                            1 && 's'}{' '}
                                                        on{' '}
                                                        {new Date(
                                                            searchDate,
                                                        ).toLocaleDateString(
                                                            'en-US',
                                                            {
                                                                weekday: 'long',
                                                                year: 'numeric',
                                                                month: 'long',
                                                                day: 'numeric',
                                                            },
                                                        )}
                                                    </div>
                                                    <TodaysList
                                                        checkins={searchResults}
                                                        departments={
                                                            departments
                                                        }
                                                        onRecordVitals={
                                                            handleRecordVitals
                                                        }
                                                        onEditVitals={
                                                            handleEditVitals
                                                        }
                                                        canUpdateDate={
                                                            permissions.canUpdateDate
                                                        }
                                                        canCancelCheckin={
                                                            permissions.canCancelCheckin
                                                        }
                                                        canEditVitals={
                                                            permissions.canEditVitals
                                                        }
                                                        isSearchResults={true}
                                                        onDateUpdated={
                                                            handleDateUpdated
                                                        }
                                                        onDepartmentUpdated={
                                                            handleDepartmentUpdated
                                                        }
                                                    />
                                                </div>
                                            )}

                                            {!isSearching &&
                                                searchResults.length === 0 &&
                                                !searchError &&
                                                searchDate && (
                                                    <div className="rounded-md bg-muted p-6 text-center text-sm text-muted-foreground">
                                                        No check-ins found for
                                                        the selected date
                                                        {searchDepartment &&
                                                            ' and department'}
                                                        .
                                                    </div>
                                                )}
                                        </div>
                                    </TabsContent>
                                )}
                            </Tabs>
                        </CardContent>
                    </Card>
                </div>
            </div>

            {/* Modals */}
            <CheckinPromptDialog
                open={checkinPromptOpen}
                onClose={handleCheckinLater}
                patient={selectedPatient}
                onCheckinNow={handleCheckinNow}
            />

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
                mode={vitalsMode}
            />
        </AppLayout>
    );
}
