import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    AlertTriangle,
    Download,
    FileText,
    Loader2,
    Printer,
    Search,
    User,
} from 'lucide-react';
import { useEffect, useState } from 'react';

interface Patient {
    id: number;
    patient_number: string;
    first_name: string;
    last_name: string;
    full_name: string;
    phone_number: string | null;
}

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    preselectedPatient?: Patient | null;
}

export default function GenerateStatementModal({
    open,
    onOpenChange,
    preselectedPatient = null,
}: Props) {
    const [patient, setPatient] = useState<Patient | null>(preselectedPatient);
    const [patientSearch, setPatientSearch] = useState('');
    const [searchResults, setSearchResults] = useState<Patient[]>([]);
    const [isSearching, setIsSearching] = useState(false);
    const [startDate, setStartDate] = useState<string>('');
    const [endDate, setEndDate] = useState<string>('');
    const [isGenerating, setIsGenerating] = useState(false);
    const [error, setError] = useState<string>('');

    // Set default date range (last 30 days)
    useEffect(() => {
        if (open) {
            const today = new Date();
            const thirtyDaysAgo = new Date();
            thirtyDaysAgo.setDate(today.getDate() - 30);

            setEndDate(today.toISOString().split('T')[0]);
            setStartDate(thirtyDaysAgo.toISOString().split('T')[0]);
            setError('');

            if (preselectedPatient) {
                setPatient(preselectedPatient);
                setPatientSearch('');
                setSearchResults([]);
            } else {
                setPatient(null);
            }
        }
    }, [open, preselectedPatient]);

    // Search patients
    const handlePatientSearch = async () => {
        if (!patientSearch.trim() || patientSearch.length < 2) {
            setSearchResults([]);
            return;
        }

        setIsSearching(true);
        setError('');

        try {
            const response = await fetch(
                `/billing/patients/search?search=${encodeURIComponent(patientSearch)}`,
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                },
            );

            if (!response.ok) {
                throw new Error('Failed to search patients');
            }

            const data = await response.json();
            setSearchResults(data.patients || []);
        } catch {
            setError('Failed to search patients. Please try again.');
            setSearchResults([]);
        } finally {
            setIsSearching(false);
        }
    };

    // Debounced search
    useEffect(() => {
        const timer = setTimeout(() => {
            if (patientSearch.length >= 2) {
                handlePatientSearch();
            } else {
                setSearchResults([]);
            }
        }, 300);

        return () => clearTimeout(timer);
    }, [patientSearch]);

    const handleSelectPatient = (selectedPatient: Patient) => {
        setPatient(selectedPatient);
        setPatientSearch('');
        setSearchResults([]);
    };

    const handleClearPatient = () => {
        setPatient(null);
        setPatientSearch('');
        setSearchResults([]);
    };

    const handleDownload = async () => {
        if (!patient || !startDate || !endDate) {
            setError('Please select a patient and date range');
            return;
        }

        if (new Date(startDate) > new Date(endDate)) {
            setError('Start date must be before end date');
            return;
        }

        setIsGenerating(true);
        setError('');

        try {
            const response = await fetch(
                `/billing/accounts/statements/${patient.id}/generate`,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/pdf',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN':
                            document
                                .querySelector('meta[name="csrf-token"]')
                                ?.getAttribute('content') || '',
                    },
                    body: JSON.stringify({
                        start_date: startDate,
                        end_date: endDate,
                    }),
                },
            );

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(
                    errorData.message || 'Failed to generate statement',
                );
            }

            // Get the blob and create download link
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `statement_${patient.patient_number}_${startDate}_to_${endDate}.pdf`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);

            onOpenChange(false);
        } catch (err) {
            setError(
                err instanceof Error
                    ? err.message
                    : 'Failed to generate statement',
            );
        } finally {
            setIsGenerating(false);
        }
    };

    const handlePrint = async () => {
        if (!patient || !startDate || !endDate) {
            setError('Please select a patient and date range');
            return;
        }

        if (new Date(startDate) > new Date(endDate)) {
            setError('Start date must be before end date');
            return;
        }

        setIsGenerating(true);
        setError('');

        try {
            const response = await fetch(
                `/billing/accounts/statements/${patient.id}/generate`,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/pdf',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN':
                            document
                                .querySelector('meta[name="csrf-token"]')
                                ?.getAttribute('content') || '',
                    },
                    body: JSON.stringify({
                        start_date: startDate,
                        end_date: endDate,
                    }),
                },
            );

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(
                    errorData.message || 'Failed to generate statement',
                );
            }

            // Get the blob and open in new window for printing
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const printWindow = window.open(url, '_blank');
            if (printWindow) {
                printWindow.onload = () => {
                    printWindow.print();
                };
            }

            onOpenChange(false);
        } catch (err) {
            setError(
                err instanceof Error
                    ? err.message
                    : 'Failed to generate statement',
            );
        } finally {
            setIsGenerating(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <FileText className="h-5 w-5" />
                        Generate Patient Statement
                    </DialogTitle>
                    <DialogDescription>
                        Generate a PDF statement showing all charges and
                        payments for a patient
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-6 py-4">
                    {/* Error Alert */}
                    {error && (
                        <Alert variant="destructive">
                            <AlertTriangle className="h-4 w-4" />
                            <AlertDescription>{error}</AlertDescription>
                        </Alert>
                    )}

                    {/* Patient Selection */}
                    <div className="space-y-2">
                        <Label>Patient</Label>
                        {patient ? (
                            <div className="flex items-center justify-between rounded-lg border bg-muted/50 p-3">
                                <div className="flex items-center gap-3">
                                    <div className="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900">
                                        <User className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                    </div>
                                    <div>
                                        <p className="font-medium">
                                            {patient.full_name}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {patient.patient_number}
                                            {patient.phone_number &&
                                                ` • ${patient.phone_number}`}
                                        </p>
                                    </div>
                                </div>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={handleClearPatient}
                                    disabled={isGenerating}
                                >
                                    Change
                                </Button>
                            </div>
                        ) : (
                            <div className="space-y-2">
                                <div className="relative">
                                    <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
                                    <Input
                                        type="text"
                                        placeholder="Search by name or patient number..."
                                        value={patientSearch}
                                        onChange={(e) =>
                                            setPatientSearch(e.target.value)
                                        }
                                        className="pl-9"
                                    />
                                    {isSearching && (
                                        <Loader2 className="absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 animate-spin text-gray-400" />
                                    )}
                                </div>

                                {/* Search Results */}
                                {searchResults.length > 0 && (
                                    <div className="max-h-48 overflow-y-auto rounded-lg border">
                                        {searchResults.map((p) => (
                                            <button
                                                key={p.id}
                                                type="button"
                                                className="flex w-full items-center gap-3 p-3 text-left hover:bg-muted/50"
                                                onClick={() =>
                                                    handleSelectPatient(p)
                                                }
                                            >
                                                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                                                    <User className="h-4 w-4 text-gray-600 dark:text-gray-400" />
                                                </div>
                                                <div>
                                                    <p className="font-medium">
                                                        {p.full_name}
                                                    </p>
                                                    <p className="text-sm text-muted-foreground">
                                                        {p.patient_number}
                                                    </p>
                                                </div>
                                            </button>
                                        ))}
                                    </div>
                                )}

                                {patientSearch.length >= 2 &&
                                    !isSearching &&
                                    searchResults.length === 0 && (
                                        <p className="text-sm text-muted-foreground">
                                            No patients found matching "
                                            {patientSearch}"
                                        </p>
                                    )}
                            </div>
                        )}
                    </div>

                    {/* Date Range */}
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="start-date">Start Date</Label>
                            <Input
                                id="start-date"
                                type="date"
                                value={startDate}
                                onChange={(e) => setStartDate(e.target.value)}
                                max={endDate || undefined}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="end-date">End Date</Label>
                            <Input
                                id="end-date"
                                type="date"
                                value={endDate}
                                onChange={(e) => setEndDate(e.target.value)}
                                min={startDate || undefined}
                                max={new Date().toISOString().split('T')[0]}
                            />
                        </div>
                    </div>

                    {/* Quick Date Presets */}
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => {
                                const today = new Date();
                                const thirtyDaysAgo = new Date();
                                thirtyDaysAgo.setDate(today.getDate() - 30);
                                setStartDate(
                                    thirtyDaysAgo.toISOString().split('T')[0],
                                );
                                setEndDate(today.toISOString().split('T')[0]);
                            }}
                        >
                            Last 30 Days
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => {
                                const today = new Date();
                                const ninetyDaysAgo = new Date();
                                ninetyDaysAgo.setDate(today.getDate() - 90);
                                setStartDate(
                                    ninetyDaysAgo.toISOString().split('T')[0],
                                );
                                setEndDate(today.toISOString().split('T')[0]);
                            }}
                        >
                            Last 90 Days
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => {
                                const today = new Date();
                                const startOfYear = new Date(
                                    today.getFullYear(),
                                    0,
                                    1,
                                );
                                setStartDate(
                                    startOfYear.toISOString().split('T')[0],
                                );
                                setEndDate(today.toISOString().split('T')[0]);
                            }}
                        >
                            This Year
                        </Button>
                    </div>
                </div>

                <DialogFooter className="flex-col gap-2 sm:flex-row">
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={isGenerating}
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="outline"
                        onClick={handlePrint}
                        disabled={
                            isGenerating || !patient || !startDate || !endDate
                        }
                    >
                        {isGenerating ? (
                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                        ) : (
                            <Printer className="mr-2 h-4 w-4" />
                        )}
                        Print
                    </Button>
                    <Button
                        onClick={handleDownload}
                        disabled={
                            isGenerating || !patient || !startDate || !endDate
                        }
                    >
                        {isGenerating ? (
                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                        ) : (
                            <Download className="mr-2 h-4 w-4" />
                        )}
                        Download PDF
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
