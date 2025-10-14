import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle,
    CreditCard,
    DollarSign,
    Loader2,
    Search,
    Settings,
    TrendingUp,
    User,
} from 'lucide-react';
import React, { useState } from 'react';

interface Patient {
    id: number;
    first_name: string;
    last_name: string;
    patient_number: string;
    phone_number: string;
}

interface Department {
    id: number;
    name: string;
}

interface Visit {
    checkin_id: number;
    department: Department;
    checked_in_at: string;
    total_pending: number;
    charges_count: number;
    charges: any[];
}

interface PatientSearchResult {
    patient_id: number;
    patient: Patient;
    total_pending: number;
    total_charges: number;
    visits_with_charges: number;
    visits: Visit[];
}

interface SelectedPatient extends PatientSearchResult {
    // Additional fields loaded when selected
}

interface Stats {
    pending_charges: number;
    pending_amount: number;
    paid_today: number;
    total_outstanding: number;
}

interface Props {
    stats: Stats;
}

export default function PaymentIndex({ stats }: Props) {
    const [searchQuery, setSearchQuery] = useState('');
    const [searchResults, setSearchResults] = useState<PatientSearchResult[]>(
        [],
    );
    const [isSearching, setIsSearching] = useState(false);
    const [selectedPatient, setSelectedPatient] =
        useState<SelectedPatient | null>(null);
    const [isLoadingPatient, setIsLoadingPatient] = useState(false);

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-GH', {
            style: 'currency',
            currency: 'GHS',
        }).format(amount);
    };

    const searchPatients = async (query: string) => {
        if (query.length < 2) {
            setSearchResults([]);
            return;
        }

        setIsSearching(true);
        try {
            const response = await fetch(
                `/billing/patients/search?search=${encodeURIComponent(query)}`,
            );
            const result = await response.json();
            setSearchResults(result.patients || []);
        } catch (error) {
            console.error('Search error:', error);
            setSearchResults([]);
        } finally {
            setIsSearching(false);
        }
    };

    const handleSearchInput = (query: string) => {
        setSearchQuery(query);
        const timeoutId = setTimeout(() => {
            searchPatients(query);
        }, 300);

        return () => clearTimeout(timeoutId);
    };

    const handlePatientSelect = async (patient: PatientSearchResult) => {
        setIsLoadingPatient(true);
        try {
            // Patient data already includes all visit and charge details
            setSelectedPatient(patient);
        } catch (error) {
            console.error('Failed to load patient billing details:', error);
        } finally {
            setIsLoadingPatient(false);
        }
    };

    const handlePaymentClick = () => {
        if (selectedPatient) {
            router.visit(
                `/billing/checkin/${selectedPatient.checkin_id}/billing`,
            );
        }
    };

    React.useEffect(() => {
        const timeoutId = setTimeout(() => {
            searchPatients(searchQuery);
        }, 300);

        return () => clearTimeout(timeoutId);
    }, [searchQuery]);

    return (
        <AppLayout breadcrumbs={[{ title: 'Billing', href: '/billing' }]}>
            <Head title="Billing - Payments & Collections" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">
                            Billing & Payments
                        </h1>
                        <p className="text-gray-600">
                            Search for patients and manage payments
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.visit('/billing/configuration')
                            }
                        >
                            <Settings className="mr-2 h-4 w-4" />
                            Configuration
                        </Button>
                    </div>
                </div>

                {/* Stats Cards */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Pending Charges
                            </CardTitle>
                            <AlertTriangle className="h-4 w-4 text-red-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-red-600">
                                {stats.pending_charges}
                            </div>
                            <p className="text-xs text-gray-600">
                                {formatCurrency(stats.pending_amount)}{' '}
                                outstanding
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Today's Revenue
                            </CardTitle>
                            <TrendingUp className="h-4 w-4 text-green-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600">
                                {formatCurrency(stats.paid_today)}
                            </div>
                            <p className="text-xs text-gray-600">
                                Collected today
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Total Outstanding
                            </CardTitle>
                            <DollarSign className="h-4 w-4 text-amber-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-amber-600">
                                {formatCurrency(stats.total_outstanding)}
                            </div>
                            <p className="text-xs text-gray-600">
                                All unpaid charges
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Collection Rate
                            </CardTitle>
                            <CheckCircle className="h-4 w-4 text-blue-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-blue-600">
                                {stats.total_outstanding > 0
                                    ? Math.round(
                                          (stats.paid_today /
                                              (stats.paid_today +
                                                  stats.total_outstanding)) *
                                              100,
                                      )
                                    : 100}
                                %
                            </div>
                            <p className="text-xs text-gray-600">
                                Payment efficiency
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Main Content - Two Column Layout */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* Left Column - Patient Search */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Search className="h-5 w-5" />
                                Search Patient
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="search">
                                        Search by name, patient number, or phone
                                    </Label>
                                    <div className="relative">
                                        <Search className="absolute top-3 left-3 h-4 w-4 text-muted-foreground" />
                                        <Input
                                            id="search"
                                            placeholder="Enter patient name, number, or phone..."
                                            value={searchQuery}
                                            onChange={(e) =>
                                                setSearchQuery(e.target.value)
                                            }
                                            className="pl-10"
                                        />
                                        {isSearching && (
                                            <Loader2 className="absolute top-3 right-3 h-4 w-4 animate-spin text-muted-foreground" />
                                        )}
                                    </div>
                                </div>

                                <div className="max-h-96 space-y-2 overflow-y-auto">
                                    {searchResults.length === 0 &&
                                        searchQuery.length >= 2 &&
                                        !isSearching && (
                                            <div className="py-8 text-center text-muted-foreground">
                                                No patients found with pending
                                                charges matching "{searchQuery}"
                                            </div>
                                        )}

                                    {searchQuery.length === 0 && (
                                        <div className="py-8 text-center text-muted-foreground">
                                            <CreditCard className="mx-auto mb-3 h-12 w-12 opacity-50" />
                                            <p>
                                                Search for patients with pending
                                                charges
                                            </p>
                                        </div>
                                    )}

                                    {searchResults.map((patient) => (
                                        <div
                                            key={patient.patient_id}
                                            className={`cursor-pointer rounded-lg border p-4 transition-colors hover:bg-muted/50 ${
                                                selectedPatient?.patient_id ===
                                                patient.patient_id
                                                    ? 'border-blue-200 bg-blue-50'
                                                    : ''
                                            }`}
                                            onClick={() =>
                                                handlePatientSelect(patient)
                                            }
                                        >
                                            <div className="flex items-center justify-between">
                                                <div>
                                                    <h3 className="font-medium">
                                                        {
                                                            patient.patient
                                                                .first_name
                                                        }{' '}
                                                        {
                                                            patient.patient
                                                                .last_name
                                                        }
                                                    </h3>
                                                    <p className="text-sm text-muted-foreground">
                                                        {
                                                            patient.patient
                                                                .patient_number
                                                        }
                                                    </p>
                                                    {patient.patient
                                                        .phone_number && (
                                                        <p className="text-sm text-muted-foreground">
                                                            {
                                                                patient.patient
                                                                    .phone_number
                                                            }
                                                        </p>
                                                    )}
                                                    <p className="text-xs text-muted-foreground">
                                                        {
                                                            patient.visits_with_charges
                                                        }{' '}
                                                        visit
                                                        {patient.visits_with_charges !==
                                                        1
                                                            ? 's'
                                                            : ''}{' '}
                                                        with charges
                                                    </p>
                                                </div>
                                                <div className="text-right">
                                                    <div className="font-medium text-red-600">
                                                        {formatCurrency(
                                                            patient.total_pending,
                                                        )}
                                                    </div>
                                                    <Badge
                                                        variant="outline"
                                                        className="text-xs"
                                                    >
                                                        {patient.total_charges}{' '}
                                                        charge
                                                        {patient.total_charges !==
                                                        1
                                                            ? 's'
                                                            : ''}
                                                    </Badge>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Right Column - Selected Patient Details */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <User className="h-5 w-5" />
                                Patient Billing Details
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {isLoadingPatient && (
                                <div className="flex items-center justify-center py-12">
                                    <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
                                    <span className="ml-2 text-muted-foreground">
                                        Loading patient details...
                                    </span>
                                </div>
                            )}

                            {!selectedPatient && !isLoadingPatient && (
                                <div className="py-12 text-center text-muted-foreground">
                                    <CreditCard className="mx-auto mb-3 h-12 w-12 opacity-50" />
                                    <p>
                                        Select a patient to view billing details
                                    </p>
                                </div>
                            )}

                            {selectedPatient && !isLoadingPatient && (
                                <div className="space-y-4">
                                    {/* Patient Info */}
                                    <div className="rounded-lg bg-muted/30 p-4">
                                        <h3 className="text-lg font-semibold">
                                            {selectedPatient.patient.first_name}{' '}
                                            {selectedPatient.patient.last_name}
                                        </h3>
                                        <p className="text-sm text-muted-foreground">
                                            {
                                                selectedPatient.patient
                                                    .patient_number
                                            }
                                        </p>
                                        {selectedPatient.patient
                                            .phone_number && (
                                            <p className="text-sm text-muted-foreground">
                                                {
                                                    selectedPatient.patient
                                                        .phone_number
                                                }
                                            </p>
                                        )}
                                        <p className="text-xs text-muted-foreground">
                                            {
                                                selectedPatient.visits_with_charges
                                            }{' '}
                                            visit
                                            {selectedPatient.visits_with_charges !==
                                            1
                                                ? 's'
                                                : ''}{' '}
                                            with outstanding charges
                                        </p>
                                    </div>

                                    {/* Total Outstanding Amount */}
                                    <div className="rounded-lg border border-red-200 bg-red-50 p-4">
                                        <div className="flex items-center justify-between">
                                            <span className="font-medium">
                                                Total Outstanding (All Visits):
                                            </span>
                                            <span className="text-xl font-bold text-red-600">
                                                {formatCurrency(
                                                    selectedPatient.total_pending,
                                                )}
                                            </span>
                                        </div>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {selectedPatient.total_charges}{' '}
                                            charge
                                            {selectedPatient.total_charges !== 1
                                                ? 's'
                                                : ''}{' '}
                                            across{' '}
                                            {
                                                selectedPatient.visits_with_charges
                                            }{' '}
                                            visit
                                            {selectedPatient.visits_with_charges !==
                                            1
                                                ? 's'
                                                : ''}
                                        </p>
                                    </div>

                                    {/* Visits Breakdown */}
                                    <div className="space-y-3">
                                        <h4 className="font-medium">
                                            Visits with Outstanding Charges:
                                        </h4>
                                        {selectedPatient.visits.map(
                                            (visit, index) => (
                                                <div
                                                    key={visit.checkin_id}
                                                    className="rounded-lg border bg-gray-50 p-3"
                                                >
                                                    <div className="mb-2 flex items-center justify-between">
                                                        <div>
                                                            <p className="text-sm font-medium">
                                                                {
                                                                    visit
                                                                        .department
                                                                        .name
                                                                }{' '}
                                                                •{' '}
                                                                {
                                                                    visit.checked_in_at
                                                                }
                                                            </p>
                                                            <p className="text-xs text-muted-foreground">
                                                                {
                                                                    visit.charges_count
                                                                }{' '}
                                                                charge
                                                                {visit.charges_count !==
                                                                1
                                                                    ? 's'
                                                                    : ''}
                                                            </p>
                                                        </div>
                                                        <div className="text-right">
                                                            <span className="font-medium text-red-600">
                                                                {formatCurrency(
                                                                    visit.total_pending,
                                                                )}
                                                            </span>
                                                        </div>
                                                    </div>

                                                    {/* Charges for this visit */}
                                                    <div className="space-y-1 border-l-2 border-gray-300 pl-3">
                                                        {visit.charges.map(
                                                            (
                                                                charge: any,
                                                                chargeIndex: number,
                                                            ) => (
                                                                <div
                                                                    key={
                                                                        chargeIndex
                                                                    }
                                                                    className="flex items-center justify-between text-xs"
                                                                >
                                                                    <span>
                                                                        {
                                                                            charge.description
                                                                        }
                                                                    </span>
                                                                    <span className="font-medium">
                                                                        {formatCurrency(
                                                                            charge.amount,
                                                                        )}
                                                                    </span>
                                                                </div>
                                                            ),
                                                        )}
                                                    </div>
                                                </div>
                                            ),
                                        )}
                                    </div>

                                    {/* Payment Action Buttons */}
                                    <div className="space-y-2 pt-4">
                                        <Button
                                            onClick={() => {
                                                // Navigate to patient billing page (use most recent visit for now)
                                                const mostRecentVisit =
                                                    selectedPatient.visits[0];
                                                router.visit(
                                                    `/billing/checkin/${mostRecentVisit.checkin_id}/billing`,
                                                );
                                            }}
                                            className="w-full bg-green-600 hover:bg-green-700"
                                        >
                                            <CreditCard className="mr-2 h-4 w-4" />
                                            Pay All Visits (
                                            {formatCurrency(
                                                selectedPatient.total_pending,
                                            )}
                                            )
                                        </Button>

                                        {selectedPatient.visits.length > 1 && (
                                            <div className="space-y-1">
                                                <p className="text-xs text-muted-foreground">
                                                    Or pay individual visits:
                                                </p>
                                                {selectedPatient.visits.map(
                                                    (visit, index) => (
                                                        <Button
                                                            key={
                                                                visit.checkin_id
                                                            }
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() => {
                                                                router.visit(
                                                                    `/billing/checkin/${visit.checkin_id}/billing`,
                                                                );
                                                            }}
                                                            className="w-full text-xs"
                                                        >
                                                            Pay{' '}
                                                            {
                                                                visit.department
                                                                    .name
                                                            }{' '}
                                                            -{' '}
                                                            {
                                                                visit.checked_in_at
                                                            }{' '}
                                                            (
                                                            {formatCurrency(
                                                                visit.total_pending,
                                                            )}
                                                            )
                                                        </Button>
                                                    ),
                                                )}
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
