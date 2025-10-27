import { DispenseModal } from '@/components/Pharmacy/DispenseModal';
import { ReviewPrescriptionsModal } from '@/components/Pharmacy/ReviewPrescriptionsModal';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { debounce } from 'lodash';
import {
    ArrowLeft,
    Calendar,
    CheckCircle,
    ClipboardCheck,
    FileText,
    Hash,
    Phone,
    Pill,
    Search,
    Stethoscope,
    User,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

interface Visit {
    type: 'consultation' | 'ward_round';
    date: string;
    date_formatted: string;
    date_relative: string;
    is_today: boolean;
    prescription_count: number;
    prescribed_count: number;
    reviewed_count: number;
    dispensed_count: number;
}

interface SearchResult {
    id: number;
    patient_number: string;
    full_name: string;
    phone_number: string | null;
    prescription_status: 'needs_review' | 'ready_to_dispense' | 'completed';
    prescribed_count: number;
    reviewed_count: number;
    dispensed_count: number;
    total_prescriptions: number;
    last_visit: string | null;
    last_visit_date: string | null;
    visit_count: number;
    visits: Visit[];
}

interface Props {
    pendingCount: number;
}

export default function DispensingIndex({ pendingCount }: Props) {
    const [searchQuery, setSearchQuery] = useState('');
    const [searchResults, setSearchResults] = useState<SearchResult[]>([]);
    const [isSearching, setIsSearching] = useState(false);
    const [dateFilter, setDateFilter] = useState<'today' | 'week' | 'all'>(
        'today',
    );

    // Modal states
    const [reviewModalOpen, setReviewModalOpen] = useState(false);
    const [dispenseModalOpen, setDispenseModalOpen] = useState(false);
    const [selectedPatient, setSelectedPatient] = useState<SearchResult | null>(
        null,
    );
    const [reviewData, setReviewData] = useState<any>(null);
    const [dispenseData, setDispenseData] = useState<any>(null);
    const [loadingData, setLoadingData] = useState(false);

    const performSearch = useCallback(
        debounce(async (query: string, filter: string) => {
            if (!query.trim()) {
                setSearchResults([]);
                setIsSearching(false);
                return;
            }

            setIsSearching(true);
            try {
                const response = await fetch(
                    `/pharmacy/dispensing/search?query=${encodeURIComponent(query)}&date_filter=${filter}`,
                );
                const data = await response.json();
                setSearchResults(data);
            } catch (error) {
                console.error('Search error:', error);
                setSearchResults([]);
            } finally {
                setIsSearching(false);
            }
        }, 300),
        [],
    );

    useEffect(() => {
        performSearch(searchQuery, dateFilter);
    }, [searchQuery, dateFilter, performSearch]);

    const handleReviewClick = async (patient: SearchResult) => {
        setSelectedPatient(patient);
        setLoadingData(true);

        try {
            const response = await fetch(
                `/pharmacy/dispensing/patients/${patient.id}?date_filter=${dateFilter}`,
            );
            const data = await response.json();
            setReviewData(data.prescriptionsData);
            setReviewModalOpen(true);
        } catch (error) {
            console.error('Error loading review data:', error);
        } finally {
            setLoadingData(false);
        }
    };

    const handleDispenseClick = async (patient: SearchResult) => {
        setSelectedPatient(patient);
        setLoadingData(true);

        try {
            const response = await fetch(
                `/pharmacy/dispensing/patients/${patient.id}/prescriptions?date_filter=${dateFilter}`,
            );
            const data = await response.json();
            setDispenseData(data);
            setDispenseModalOpen(true);
        } catch (error) {
            console.error('Error loading dispense data:', error);
        } finally {
            setLoadingData(false);
        }
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'needs_review':
                return (
                    <span className="inline-flex items-center gap-1 rounded-full bg-yellow-100 px-2 py-1 text-xs font-medium text-yellow-700 dark:bg-yellow-950 dark:text-yellow-300">
                        <ClipboardCheck className="h-3 w-3" />
                        Needs Review
                    </span>
                );
            case 'ready_to_dispense':
                return (
                    <span className="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-1 text-xs font-medium text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                        <Pill className="h-3 w-3" />
                        Ready to Dispense
                    </span>
                );
            case 'completed':
                return (
                    <span className="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-700 dark:bg-green-950 dark:text-green-300">
                        <CheckCircle className="h-3 w-3" />
                        Completed
                    </span>
                );
        }
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Pharmacy', href: '/pharmacy' },
                { title: 'Dispensing', href: '/pharmacy/dispensing' },
            ]}
        >
            <Head title="Dispensing" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="ghost" size="sm" asChild>
                            <Link href="/pharmacy">
                                <ArrowLeft className="mr-1 h-4 w-4" />
                                Back to Dashboard
                            </Link>
                        </Button>
                        <div>
                            <h1 className="flex items-center gap-2 text-2xl font-bold">
                                <Pill className="h-6 w-6" />
                                Dispensing
                            </h1>
                            <p className="text-muted-foreground">
                                Search for patients to dispense medications
                            </p>
                        </div>
                    </div>

                    {pendingCount > 0 && (
                        <div className="flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 dark:border-blue-800 dark:bg-blue-950">
                            <FileText className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                            <span className="text-sm font-medium text-blue-900 dark:text-blue-100">
                                {pendingCount} Pending Prescription
                                {pendingCount !== 1 ? 's' : ''}
                            </span>
                        </div>
                    )}
                </div>

                {/* Search Card */}
                <Card className="border-2">
                    <CardContent className="pt-6">
                        <div className="mx-auto max-w-2xl space-y-6">
                            <div className="space-y-2 text-center">
                                <div className="mb-2 inline-flex h-16 w-16 items-center justify-center rounded-full bg-primary/10">
                                    <Search className="h-8 w-8 text-primary" />
                                </div>
                                <h2 className="text-xl font-semibold">
                                    Search for Patient
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Enter patient name, MRN, or phone number
                                </p>
                            </div>

                            <div className="space-y-3">
                                <div className="relative">
                                    <Search className="absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        type="text"
                                        placeholder="Search by name, patient number, or phone..."
                                        value={searchQuery}
                                        onChange={(e) =>
                                            setSearchQuery(e.target.value)
                                        }
                                        className="h-12 pl-10 text-base"
                                        autoFocus
                                    />
                                </div>

                                {/* Date Filter */}
                                <div className="flex items-center justify-between rounded-lg border bg-muted/30 p-2">
                                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                        <Calendar className="h-4 w-4" />
                                        <span>Show prescriptions from:</span>
                                    </div>
                                    <div className="flex gap-1">
                                        <Button
                                            size="sm"
                                            variant={
                                                dateFilter === 'today'
                                                    ? 'default'
                                                    : 'ghost'
                                            }
                                            onClick={() =>
                                                setDateFilter('today')
                                            }
                                            className="h-7 text-xs"
                                        >
                                            Today
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant={
                                                dateFilter === 'week'
                                                    ? 'default'
                                                    : 'ghost'
                                            }
                                            onClick={() =>
                                                setDateFilter('week')
                                            }
                                            className="h-7 text-xs"
                                        >
                                            This Week
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant={
                                                dateFilter === 'all'
                                                    ? 'default'
                                                    : 'ghost'
                                            }
                                            onClick={() => setDateFilter('all')}
                                            className="h-7 text-xs"
                                        >
                                            All Pending
                                        </Button>
                                    </div>
                                </div>
                            </div>

                            {/* Search Results */}
                            {searchQuery && (
                                <div className="max-h-96 divide-y overflow-y-auto rounded-lg border">
                                    {isSearching ? (
                                        <div className="p-8 text-center text-muted-foreground">
                                            <div className="animate-pulse">
                                                Searching...
                                            </div>
                                        </div>
                                    ) : searchResults.length > 0 ? (
                                        searchResults.map((patient) => (
                                            <div
                                                key={patient.id}
                                                className="p-4 transition-colors hover:bg-muted/50"
                                            >
                                                <div className="flex w-full items-start gap-4">
                                                    <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-primary/10">
                                                        <User className="h-5 w-5 text-primary" />
                                                    </div>
                                                    <div className="min-w-0 flex-1">
                                                        <div className="flex items-start justify-between gap-2">
                                                            <div className="flex-1">
                                                                <h3 className="font-medium">
                                                                    {
                                                                        patient.full_name
                                                                    }
                                                                </h3>
                                                                <div className="mt-1 flex items-center gap-4 text-sm text-muted-foreground">
                                                                    <span className="flex items-center gap-1">
                                                                        <Hash className="h-3 w-3" />
                                                                        {
                                                                            patient.patient_number
                                                                        }
                                                                    </span>
                                                                    {patient.phone_number && (
                                                                        <span className="flex items-center gap-1">
                                                                            <Phone className="h-3 w-3" />
                                                                            {
                                                                                patient.phone_number
                                                                            }
                                                                        </span>
                                                                    )}
                                                                </div>
                                                                <div className="mt-2 flex flex-wrap items-center gap-2">
                                                                    {getStatusBadge(
                                                                        patient.prescription_status,
                                                                    )}
                                                                    <span className="text-xs text-muted-foreground">
                                                                        {
                                                                            patient.total_prescriptions
                                                                        }{' '}
                                                                        prescription
                                                                        {patient.total_prescriptions !==
                                                                        1
                                                                            ? 's'
                                                                            : ''}
                                                                    </span>
                                                                    {patient.visit_count >
                                                                        1 && (
                                                                        <span className="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-xs font-medium">
                                                                            <Calendar className="h-3 w-3" />
                                                                            {
                                                                                patient.visit_count
                                                                            }{' '}
                                                                            visits
                                                                        </span>
                                                                    )}
                                                                    {patient.last_visit && (
                                                                        <span className="text-xs text-muted-foreground">
                                                                            •{' '}
                                                                            {
                                                                                patient.last_visit
                                                                            }
                                                                        </span>
                                                                    )}
                                                                </div>

                                                                {/* Visit Details */}
                                                                {patient.visit_count >
                                                                    1 && (
                                                                    <div className="mt-3 space-y-1.5">
                                                                        {patient.visits.map(
                                                                            (
                                                                                visit,
                                                                                idx,
                                                                            ) => (
                                                                                <div
                                                                                    key={
                                                                                        idx
                                                                                    }
                                                                                    className={`flex items-center gap-2 rounded-md border px-2.5 py-1.5 text-xs ${
                                                                                        visit.is_today
                                                                                            ? 'border-primary/20 bg-primary/5 dark:border-primary/30'
                                                                                            : 'border-muted bg-muted/30'
                                                                                    }`}
                                                                                >
                                                                                    <Stethoscope className="h-3 w-3 text-muted-foreground" />
                                                                                    <span className="font-medium">
                                                                                        {
                                                                                            visit.date_formatted
                                                                                        }
                                                                                    </span>
                                                                                    <span className="text-muted-foreground">
                                                                                        (
                                                                                        {
                                                                                            visit.date_relative
                                                                                        }
                                                                                        )
                                                                                    </span>
                                                                                    <span className="text-muted-foreground">
                                                                                        •{' '}
                                                                                        {
                                                                                            visit.prescription_count
                                                                                        }{' '}
                                                                                        Rx
                                                                                    </span>
                                                                                    {visit.prescribed_count >
                                                                                        0 && (
                                                                                        <span className="rounded bg-yellow-100 px-1.5 py-0.5 text-yellow-700 dark:bg-yellow-950 dark:text-yellow-300">
                                                                                            {
                                                                                                visit.prescribed_count
                                                                                            }{' '}
                                                                                            to
                                                                                            review
                                                                                        </span>
                                                                                    )}
                                                                                    {visit.reviewed_count >
                                                                                        0 && (
                                                                                        <span className="rounded bg-blue-100 px-1.5 py-0.5 text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                                                                                            {
                                                                                                visit.reviewed_count
                                                                                            }{' '}
                                                                                            ready
                                                                                        </span>
                                                                                    )}
                                                                                    {visit.is_today && (
                                                                                        <span className="ml-auto rounded bg-primary px-1.5 py-0.5 text-primary-foreground">
                                                                                            Today
                                                                                        </span>
                                                                                    )}
                                                                                </div>
                                                                            ),
                                                                        )}
                                                                    </div>
                                                                )}
                                                            </div>
                                                            <div className="flex flex-col gap-2">
                                                                {patient.prescription_status ===
                                                                    'needs_review' && (
                                                                    <Button
                                                                        size="sm"
                                                                        onClick={() =>
                                                                            handleReviewClick(
                                                                                patient,
                                                                            )
                                                                        }
                                                                        disabled={
                                                                            loadingData
                                                                        }
                                                                    >
                                                                        Review
                                                                    </Button>
                                                                )}
                                                                {patient.prescription_status ===
                                                                    'ready_to_dispense' && (
                                                                    <>
                                                                        <Button
                                                                            size="sm"
                                                                            onClick={() =>
                                                                                handleDispenseClick(
                                                                                    patient,
                                                                                )
                                                                            }
                                                                            disabled={
                                                                                loadingData
                                                                            }
                                                                        >
                                                                            Dispense
                                                                        </Button>
                                                                        <Button
                                                                            size="sm"
                                                                            variant="outline"
                                                                            onClick={() =>
                                                                                handleReviewClick(
                                                                                    patient,
                                                                                )
                                                                            }
                                                                            disabled={
                                                                                loadingData
                                                                            }
                                                                        >
                                                                            Re-review
                                                                        </Button>
                                                                    </>
                                                                )}
                                                                {patient.prescription_status ===
                                                                    'completed' && (
                                                                    <Button
                                                                        size="sm"
                                                                        variant="outline"
                                                                        disabled
                                                                    >
                                                                        Completed
                                                                    </Button>
                                                                )}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        ))
                                    ) : (
                                        <div className="p-8 text-center text-muted-foreground">
                                            <User className="mx-auto mb-2 h-12 w-12 opacity-50" />
                                            <p>
                                                No patients found with pending
                                                prescriptions
                                            </p>
                                            <p className="mt-1 text-sm">
                                                Try a different search term
                                            </p>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Quick Stats */}
                {!searchQuery && pendingCount > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm font-medium">
                                Today's Activity
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center gap-2 text-sm">
                                <FileText className="h-4 w-4 text-muted-foreground" />
                                <span className="text-muted-foreground">
                                    {pendingCount} prescription
                                    {pendingCount !== 1 ? 's' : ''} waiting to
                                    be dispensed
                                </span>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>

            {/* Review Modal */}
            {selectedPatient && reviewData && (
                <ReviewPrescriptionsModal
                    open={reviewModalOpen}
                    onOpenChange={setReviewModalOpen}
                    patientId={selectedPatient.id}
                    prescriptionsData={reviewData}
                />
            )}

            {/* Dispense Modal */}
            {selectedPatient && dispenseData && (
                <DispenseModal
                    open={dispenseModalOpen}
                    onOpenChange={setDispenseModalOpen}
                    patient={{
                        id: selectedPatient.id,
                        patient_number: selectedPatient.patient_number,
                        full_name: selectedPatient.full_name,
                    }}
                    prescriptionsData={dispenseData}
                />
            )}
        </AppLayout>
    );
}
