import ProcedureForm from '@/components/MinorProcedure/ProcedureForm';
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
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { Activity, Search, Settings, Users } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Patient {
    id: number;
    patient_number: string;
    first_name: string;
    last_name: string;
    date_of_birth: string;
    phone_number: string | null;
}

interface VitalSign {
    id: number;
    temperature: number | null;
    blood_pressure_systolic: number | null;
    blood_pressure_diastolic: number | null;
    pulse_rate: number | null;
    respiratory_rate: number | null;
    weight: number | null;
    height: number | null;
    bmi: number | null;
    recorded_at: string;
}

interface Department {
    id: number;
    name: string;
}

interface PatientCheckin {
    id: number;
    patient: Patient;
    department: Department;
    status: string;
    checked_in_at: string;
    vitals_taken_at: string | null;
    vital_signs: VitalSign[];
}

interface ProcedureType {
    id: number;
    name: string;
    code: string;
    category: string;
    description: string | null;
    price: number;
    is_active: boolean;
}

interface Drug {
    id: number;
    name: string;
    generic_name: string | null;
    brand_name: string | null;
    drug_code: string;
    form: string;
    strength: string | null;
    unit_price: number;
    unit_type: string;
}

interface Diagnosis {
    id: number;
    diagnosis: string;
    code: string | null;
    g_drg: string | null;
    icd_10: string | null;
}

interface Props {
    queueCount: number;
    procedureTypes: ProcedureType[];
    availableDrugs: Drug[];
    availableDiagnoses: Diagnosis[];
    canManageTypes: boolean;
}

export default function MinorProcedureIndex({
    queueCount,
    procedureTypes,
    availableDrugs,
    availableDiagnoses,
    canManageTypes,
}: Props) {
    const [searchQuery, setSearchQuery] = useState('');
    const [searchResults, setSearchResults] = useState<PatientCheckin[]>([]);
    const [isSearching, setIsSearching] = useState(false);
    const [searchError, setSearchError] = useState<string | null>(null);
    const [selectedPatient, setSelectedPatient] =
        useState<PatientCheckin | null>(null);
    const [procedureFormOpen, setProcedureFormOpen] = useState(false);

    // Debounced search
    useEffect(() => {
        if (searchQuery.length < 2) {
            setSearchResults([]);
            setSearchError(null);
            return;
        }

        const timeoutId = setTimeout(() => {
            handleSearch();
        }, 300);

        return () => clearTimeout(timeoutId);
    }, [searchQuery]);

    const handleSearch = async () => {
        if (searchQuery.length < 2) {
            return;
        }

        setIsSearching(true);
        setSearchError(null);

        try {
            const csrfToken = document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content');

            const response = await fetch(
                `/minor-procedures/search?search=${encodeURIComponent(searchQuery)}`,
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
                throw new Error(error.error || 'Failed to search patients');
            }

            const data = await response.json();
            setSearchResults(data.patients);
        } catch (error) {
            setSearchError(
                error instanceof Error
                    ? error.message
                    : 'Failed to search patients',
            );
        } finally {
            setIsSearching(false);
        }
    };

    const handleSelectPatient = (patient: PatientCheckin) => {
        setSelectedPatient(patient);
        setProcedureFormOpen(true);
    };

    const handleProcedureSuccess = () => {
        setProcedureFormOpen(false);
        setSelectedPatient(null);
        setSearchQuery('');
        setSearchResults([]);
        // Reload page to update queue count
        window.location.reload();
    };

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

    const formatTime = (datetime: string) => {
        return new Date(datetime).toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    return (
        <AppLayout>
            <Head title="Minor Procedures" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">
                            Minor Procedures
                        </h1>
                        <p className="text-muted-foreground">
                            Perform minor procedures and manage patient queue
                        </p>
                    </div>
                    {canManageTypes && (
                        <Link href="/minor-procedures/types">
                            <Button
                                variant="outline"
                                size="sm"
                                className="gap-2"
                            >
                                <Settings className="h-4 w-4" />
                                Configure Procedures
                            </Button>
                        </Link>
                    )}
                </div>

                {/* Queue Count Card */}
                <Card className="border-primary/20 bg-primary/5">
                    <CardContent className="flex items-center gap-4 p-6">
                        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10">
                            <Users className="h-6 w-6 text-primary" />
                        </div>
                        <div>
                            <p className="text-sm font-medium text-muted-foreground">
                                Patients in Queue
                            </p>
                            <p className="text-3xl font-bold">{queueCount}</p>
                        </div>
                    </CardContent>
                </Card>

                {/* Main Content */}
                <div className="grid grid-cols-1 gap-6">
                    {/* Patient Search */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Find Patient</CardTitle>
                            <CardDescription>
                                Search for patients in the Minor Procedures
                                queue by name, patient number, or phone number
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="search">Search Patient</Label>
                                <div className="relative">
                                    <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        id="search"
                                        type="text"
                                        placeholder="Type patient name, number, or phone..."
                                        value={searchQuery}
                                        onChange={(e) =>
                                            setSearchQuery(e.target.value)
                                        }
                                        className="pl-10"
                                    />
                                </div>
                                {isSearching && (
                                    <p className="text-sm text-muted-foreground">
                                        Searching...
                                    </p>
                                )}
                            </div>

                            {searchError && (
                                <div className="rounded-md bg-destructive/10 p-3 text-sm text-destructive">
                                    {searchError}
                                </div>
                            )}

                            {/* Search Results */}
                            {searchQuery.length >= 2 &&
                                !isSearching &&
                                searchResults.length === 0 &&
                                !searchError && (
                                    <div className="rounded-md bg-muted p-6 text-center text-sm text-muted-foreground">
                                        No patients found in the queue matching
                                        "{searchQuery}"
                                    </div>
                                )}

                            {searchResults.length > 0 && (
                                <div className="space-y-2">
                                    <p className="text-sm font-medium">
                                        Found {searchResults.length} patient
                                        {searchResults.length !== 1 ? 's' : ''}
                                    </p>
                                    <div className="space-y-2">
                                        {searchResults.map((checkin) => (
                                            <div
                                                key={checkin.id}
                                                className="flex items-center justify-between rounded-lg border p-4 transition-colors hover:bg-muted/50"
                                            >
                                                <div className="space-y-1">
                                                    <div className="flex items-center gap-2">
                                                        <p className="font-medium">
                                                            {
                                                                checkin.patient
                                                                    .first_name
                                                            }{' '}
                                                            {
                                                                checkin.patient
                                                                    .last_name
                                                            }
                                                        </p>
                                                        <span className="text-sm text-muted-foreground">
                                                            •
                                                        </span>
                                                        <span className="text-sm text-muted-foreground">
                                                            {
                                                                checkin.patient
                                                                    .patient_number
                                                            }
                                                        </span>
                                                    </div>
                                                    <div className="flex items-center gap-4 text-sm text-muted-foreground">
                                                        <span>
                                                            Age:{' '}
                                                            {calculateAge(
                                                                checkin.patient
                                                                    .date_of_birth,
                                                            )}{' '}
                                                            years
                                                        </span>
                                                        {checkin.patient
                                                            .phone_number && (
                                                            <>
                                                                <span>•</span>
                                                                <span>
                                                                    {
                                                                        checkin
                                                                            .patient
                                                                            .phone_number
                                                                    }
                                                                </span>
                                                            </>
                                                        )}
                                                        <span>•</span>
                                                        <span>
                                                            Checked in at{' '}
                                                            {formatTime(
                                                                checkin.checked_in_at,
                                                            )}
                                                        </span>
                                                    </div>
                                                    {checkin.vital_signs &&
                                                        checkin.vital_signs
                                                            .length > 0 && (
                                                            <div className="flex items-center gap-2 text-sm text-green-600 dark:text-green-400">
                                                                <Activity className="h-3 w-3" />
                                                                <span>
                                                                    Vitals
                                                                    recorded
                                                                </span>
                                                            </div>
                                                        )}
                                                </div>
                                                <Button
                                                    onClick={() =>
                                                        handleSelectPatient(
                                                            checkin,
                                                        )
                                                    }
                                                >
                                                    Select
                                                </Button>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {queueCount === 0 && searchQuery.length < 2 && (
                                <div className="rounded-md bg-muted p-6 text-center text-sm text-muted-foreground">
                                    No patients currently in the Minor
                                    Procedures queue. Patients will appear here
                                    when they check in to the Minor Procedures
                                    department.
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>

            {/* Procedure Form Modal */}
            {selectedPatient && (
                <ProcedureForm
                    open={procedureFormOpen}
                    onClose={() => {
                        setProcedureFormOpen(false);
                        setSelectedPatient(null);
                    }}
                    patientCheckin={selectedPatient}
                    procedureTypes={procedureTypes}
                    availableDrugs={availableDrugs}
                    availableDiagnoses={availableDiagnoses}
                    onSuccess={handleProcedureSuccess}
                />
            )}
        </AppLayout>
    );
}
