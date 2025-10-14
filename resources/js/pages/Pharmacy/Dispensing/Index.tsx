import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { debounce } from 'lodash';
import {
    ArrowLeft,
    FileText,
    Hash,
    Phone,
    Pill,
    Search,
    User,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

interface SearchResult {
    id: number;
    patient_number: string;
    full_name: string;
    phone_number: string | null;
    pending_prescriptions_count: number;
    last_visit: string | null;
}

interface Props {
    pendingCount: number;
}

export default function DispensingIndex({ pendingCount }: Props) {
    const [searchQuery, setSearchQuery] = useState('');
    const [searchResults, setSearchResults] = useState<SearchResult[]>([]);
    const [isSearching, setIsSearching] = useState(false);

    const performSearch = useCallback(
        debounce(async (query: string) => {
            if (!query.trim()) {
                setSearchResults([]);
                setIsSearching(false);
                return;
            }

            setIsSearching(true);
            try {
                const response = await fetch(
                    `/pharmacy/dispensing/search?query=${encodeURIComponent(query)}`,
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
        performSearch(searchQuery);
    }, [searchQuery, performSearch]);

    const handlePatientSelect = (patientId: number) => {
        router.visit(`/pharmacy/dispensing/patients/${patientId}`);
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
                                            <button
                                                key={patient.id}
                                                onClick={() =>
                                                    handlePatientSelect(
                                                        patient.id,
                                                    )
                                                }
                                                className="flex w-full items-start gap-4 p-4 text-left transition-colors hover:bg-muted/50"
                                            >
                                                <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-primary/10">
                                                    <User className="h-5 w-5 text-primary" />
                                                </div>
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex items-start justify-between gap-2">
                                                        <div>
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
                                                        </div>
                                                        <div className="flex flex-col items-end gap-1">
                                                            <span className="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-1 text-xs font-medium text-blue-700 dark:bg-blue-950 dark:text-blue-300">
                                                                <Pill className="h-3 w-3" />
                                                                {
                                                                    patient.pending_prescriptions_count
                                                                }{' '}
                                                                Rx
                                                            </span>
                                                            {patient.last_visit && (
                                                                <span className="text-xs text-muted-foreground">
                                                                    {
                                                                        patient.last_visit
                                                                    }
                                                                </span>
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>
                                            </button>
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
        </AppLayout>
    );
}
