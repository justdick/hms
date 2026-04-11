import ProcedureForm from '@/components/MinorProcedure/ProcedureForm';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    DateFilterPresets,
    DateFilterValue,
    calculateDateRange,
} from '@/components/ui/date-filter-presets';
import { Input } from '@/components/ui/input';
import { Pagination } from '@/components/ui/pagination';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, usePoll } from '@inertiajs/react';
import {
    CheckCircle,
    Clock,
    Eye,
    List,
    RefreshCw,
    Search,
    Settings,
} from 'lucide-react';
import { useEffect, useState } from 'react';

interface Patient {
    id: number;
    patient_number: string;
    first_name: string;
    last_name: string;
    date_of_birth: string;
    phone_number: string | null;
}

interface InsuranceProvider {
    id: number;
    name: string;
    code: string;
}

interface InsurancePlan {
    id: number;
    name: string;
    provider: InsuranceProvider;
}

interface PatientInsurance {
    id: number;
    plan: InsurancePlan;
}

interface PatientWithInsurance extends Patient {
    active_insurance?: PatientInsurance | null;
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
    patient: PatientWithInsurance;
    department: Department;
    status: string;
    checked_in_at: string;
    service_date: string;
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

interface CompletedProcedure {
    id: number;
    performed_at: string;
    status: string;
    procedure_notes: string | null;
    minor_procedure_type_id?: number;
    nurse?: { id: number; name: string };
    procedure_type?: { id: number; name: string; code: string };
    diagnoses?: { id: number; diagnosis: string; code: string | null; icd_10: string | null }[];
    supplies?: { id: number; drug_id: number; quantity: number; dispensed: boolean; drug: { id: number; name: string; generic_name: string | null; brand_name: string | null; drug_code: string; form: string; strength: string | null; unit_price: number; unit_type: string } }[];
    patient_checkin: {
        id: number;
        patient: PatientWithInsurance;
        department: Department;
        status: string;
        checked_in_at: string;
        service_date: string;
        vitals_taken_at: string | null;
        vital_signs: VitalSign[];
    };
}

interface Filters {
    search?: string;
    queue_search?: string;
    completed_search?: string;
    date_from?: string;
    date_to?: string;
    per_page?: number;
}

interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

interface Props {
    queuePatients: PaginatedData<PatientCheckin>;
    completedProcedures: PaginatedData<CompletedProcedure>;
    totalQueueCount: number;
    totalCompletedCount: number;
    procedureTypes: ProcedureType[];
    availableDrugs: Drug[];
    canManageTypes: boolean;
    filters: Filters;
}

export default function MinorProcedureIndex({
    queuePatients,
    completedProcedures,
    totalQueueCount,
    totalCompletedCount,
    procedureTypes,
    availableDrugs,
    canManageTypes,
    filters,
}: Props) {
    const [activeTab, setActiveTab] = useState<string>(() => {
        try {
            return sessionStorage.getItem('minor_procedure_active_tab') || 'search';
        } catch {
            return 'search';
        }
    });
    const [search, setSearch] = useState(filters.search || '');
    const [queueSearch, setQueueSearch] = useState(filters.queue_search || '');
    const [completedSearch, setCompletedSearch] = useState(filters.completed_search || '');
    const [perPage, setPerPage] = useState(filters.per_page || 5);
    const [dateFilter, setDateFilter] = useState<DateFilterValue>(() => {
        const urlParams = new URLSearchParams(window.location.search);
        const urlHasDateParams = urlParams.has('date_from') || urlParams.has('date_to');

        if (urlHasDateParams && (filters.date_from || filters.date_to)) {
            const presets = ['today', 'yesterday', 'this_week', 'last_week', 'this_month', 'last_month'] as const;
            for (const preset of presets) {
                const range = calculateDateRange(preset);
                if (filters.date_from === range.from && filters.date_to === range.to) {
                    return { preset, from: filters.date_from, to: filters.date_to };
                }
            }
            return { preset: 'custom', from: filters.date_from, to: filters.date_to };
        }

        try {
            const saved = sessionStorage.getItem('minor_procedure_date_filter');
            if (saved) {
                const parsed: DateFilterValue = JSON.parse(saved);
                if (parsed.preset && parsed.preset !== 'custom') {
                    const range = calculateDateRange(parsed.preset);
                    return { preset: parsed.preset, ...range };
                }
                if (parsed.from || parsed.to) {
                    return { preset: 'custom', from: parsed.from, to: parsed.to };
                }
            }
        } catch {
            // Ignore parse errors
        }

        const todayRange = calculateDateRange('today');
        return { preset: 'today', ...todayRange };
    });
    const [lastUpdated, setLastUpdated] = useState<Date>(new Date());
    const [selectedPatient, setSelectedPatient] = useState<PatientCheckin | null>(null);
    const [procedureFormOpen, setProcedureFormOpen] = useState(false);
    const [editProcedure, setEditProcedure] = useState<CompletedProcedure | null>(null);

    // Sync restored date filter with server on fresh navigation
    useEffect(() => {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('date_from') || urlParams.has('date_to')) return;
        const todayRange = calculateDateRange('today');
        if (dateFilter.from !== todayRange.from || dateFilter.to !== todayRange.to) {
            router.get(
                '/minor-procedures',
                { date_from: dateFilter.from || undefined, date_to: dateFilter.to || undefined, per_page: perPage },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Auto-poll every 30 seconds for queue updates
    const { stop, start } = usePoll(
        30000,
        {
            only: ['queuePatients', 'totalQueueCount'],
            onFinish: () => setLastUpdated(new Date()),
        },
        { autoStart: activeTab === 'queue' },
    );

    useEffect(() => {
        if (activeTab === 'queue') { start(); } else { stop(); }
    }, [activeTab, start, stop]);

    const formatTime = (dateString: string) =>
        new Date(dateString).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });

    const formatDate = (dateString: string) =>
        new Date(dateString).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });

    const calculateAge = (dateOfBirth: string) => {
        const today = new Date();
        const birth = new Date(dateOfBirth);
        let age = today.getFullYear() - birth.getFullYear();
        const monthDiff = today.getMonth() - birth.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) age--;
        return age;
    };

    const getTimeSinceUpdate = () => {
        const seconds = Math.floor((new Date().getTime() - lastUpdated.getTime()) / 1000);
        if (seconds < 60) return `${seconds}s ago`;
        return `${Math.floor(seconds / 60)}m ago`;
    };

    const buildParams = (overrides: Record<string, string | number | undefined> = {}) => {
        return {
            search: search || undefined,
            queue_search: queueSearch || undefined,
            completed_search: completedSearch || undefined,
            date_from: dateFilter.from || undefined,
            date_to: dateFilter.to || undefined,
            per_page: perPage,
            ...overrides,
        };
    };

    // Debounced search effect
    useEffect(() => {
        if (activeTab !== 'search') return;
        const timeoutId = setTimeout(() => {
            if (search.length >= 2 || search.length === 0) {
                router.get('/minor-procedures', buildParams({ queue_search: undefined, completed_search: undefined }), {
                    preserveState: true, preserveScroll: true, replace: true,
                });
            }
        }, 500);
        return () => clearTimeout(timeoutId);
    }, [search, activeTab]);

    // Debounced queue search effect
    useEffect(() => {
        if (activeTab !== 'queue') return;
        const timeoutId = setTimeout(() => {
            if (queueSearch.length >= 2 || queueSearch.length === 0) {
                router.get('/minor-procedures', buildParams({ search: undefined, completed_search: undefined }), {
                    preserveState: true, preserveScroll: true, replace: true,
                });
            }
        }, 500);
        return () => clearTimeout(timeoutId);
    }, [queueSearch, activeTab]);

    // Debounced completed search effect
    useEffect(() => {
        if (activeTab !== 'completed') return;
        const timeoutId = setTimeout(() => {
            if (completedSearch.length >= 2 || completedSearch.length === 0) {
                router.get('/minor-procedures', buildParams({ search: undefined, queue_search: undefined }), {
                    preserveState: true, preserveScroll: true, replace: true,
                });
            }
        }, 500);
        return () => clearTimeout(timeoutId);
    }, [completedSearch, activeTab]);

    const handleDateFilterChange = (value: DateFilterValue) => {
        setDateFilter(value);
        try { sessionStorage.setItem('minor_procedure_date_filter', JSON.stringify(value)); } catch { /* ignore */ }
        router.get('/minor-procedures', {
            ...buildParams(), date_from: value.from || undefined, date_to: value.to || undefined,
        }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const handlePerPageChange = (value: string) => {
        const newPerPage = parseInt(value, 10);
        setPerPage(newPerPage);
        router.get('/minor-procedures', { ...buildParams(), per_page: newPerPage }, {
            preserveState: true, preserveScroll: true, replace: true,
        });
    };

    const handleManualRefresh = () => {
        router.reload({
            only: ['queuePatients', 'totalQueueCount', 'completedProcedures', 'totalCompletedCount'],
            onFinish: () => setLastUpdated(new Date()),
        });
    };

    const handleQueuePageChange = (page: number) => {
        router.get('/minor-procedures', {
            ...buildParams(), queue_page: page, completed_page: completedProcedures.current_page,
        }, { preserveState: true, preserveScroll: true });
    };

    const handleCompletedPageChange = (page: number) => {
        router.get('/minor-procedures', {
            ...buildParams(), queue_page: queuePatients.current_page, completed_page: page,
        }, { preserveState: true, preserveScroll: true });
    };

    const handleSelectPatient = (patient: PatientCheckin) => {
        setSelectedPatient(patient);
        setProcedureFormOpen(true);
    };

    const handleProcedureSuccess = () => {
        setProcedureFormOpen(false);
        setSelectedPatient(null);
        setSearch('');
        router.reload();
    };

    // Reusable patient row for queue tables
    const renderQueueRow = (checkin: PatientCheckin) => (
        <TableRow key={checkin.id}>
            <TableCell className="font-medium">
                {checkin.patient.first_name} {checkin.patient.last_name}
            </TableCell>
            <TableCell>{checkin.patient.patient_number}</TableCell>
            <TableCell>{calculateAge(checkin.patient.date_of_birth)} yrs</TableCell>
            <TableCell>{formatDate(checkin.service_date)}</TableCell>
            <TableCell>
                {checkin.vital_signs?.length > 0 ? (
                    <Badge variant="outline" className="bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300">✓ Taken</Badge>
                ) : (
                    <Badge variant="outline" className="text-muted-foreground">Pending</Badge>
                )}
            </TableCell>
            <TableCell>
                {checkin.patient.active_insurance ? (
                    <Badge variant="outline" className="bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300">
                        {checkin.patient.active_insurance.plan.provider.code}
                    </Badge>
                ) : (
                    <Badge variant="outline" className="text-muted-foreground">Cash</Badge>
                )}
            </TableCell>
            <TableCell className="text-right">
                <Button size="sm" onClick={() => handleSelectPatient(checkin)}>Select</Button>
            </TableCell>
        </TableRow>
    );

    const queueTableHeaders = (
        <TableRow>
            <TableHead>Patient</TableHead>
            <TableHead>ID</TableHead>
            <TableHead>Age</TableHead>
            <TableHead>Date</TableHead>
            <TableHead>Vitals</TableHead>
            <TableHead>Insurance</TableHead>
            <TableHead className="text-right">Action</TableHead>
        </TableRow>
    );

    const renderCompletedRow = (procedure: CompletedProcedure) => (
        <TableRow key={procedure.id}>
            <TableCell className="font-medium">
                {procedure.patient_checkin.patient.first_name} {procedure.patient_checkin.patient.last_name}
            </TableCell>
            <TableCell>{procedure.patient_checkin.patient.patient_number}</TableCell>
            <TableCell>{calculateAge(procedure.patient_checkin.patient.date_of_birth)} yrs</TableCell>
            <TableCell>{procedure.procedure_type?.name ?? '-'}</TableCell>
            <TableCell>{procedure.nurse?.name ?? '-'}</TableCell>
            <TableCell>{formatTime(procedure.performed_at)}</TableCell>
            <TableCell>
                {procedure.patient_checkin.patient.active_insurance ? (
                    <Badge variant="outline" className="bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300">
                        {procedure.patient_checkin.patient.active_insurance.plan.provider.code}
                    </Badge>
                ) : (
                    <Badge variant="outline" className="text-muted-foreground">Cash</Badge>
                )}
            </TableCell>
            <TableCell className="text-right">
                <Button size="sm" variant="outline" onClick={() => setEditProcedure(procedure)} className="gap-1">
                    <Eye className="h-4 w-4" /> View
                </Button>
            </TableCell>
        </TableRow>
    );

    const completedTableHeaders = (
        <TableRow>
            <TableHead>Patient</TableHead>
            <TableHead>ID</TableHead>
            <TableHead>Age</TableHead>
            <TableHead>Procedure</TableHead>
            <TableHead>Performed By</TableHead>
            <TableHead>Time</TableHead>
            <TableHead>Insurance</TableHead>
            <TableHead className="text-right">Action</TableHead>
        </TableRow>
    );

    return (
        <AppLayout breadcrumbs={[{ title: 'Minor Procedures', href: '/minor-procedures' }]}>
            <Head title="Minor Procedures" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div>
                        <h1 className="text-3xl font-bold text-gray-900 dark:text-gray-100">Minor Procedures</h1>
                        <p className="mt-1 text-gray-600 dark:text-gray-400">Perform minor procedures and manage patient queue</p>
                    </div>
                    <div className="flex items-center gap-3">
                        <div className="flex items-center gap-2 rounded-lg bg-blue-50 px-3 py-1.5 dark:bg-blue-950">
                            <span className="text-lg font-bold text-blue-600 dark:text-blue-400">{totalQueueCount}</span>
                            <span className="text-sm text-blue-700 dark:text-blue-300">In Queue</span>
                        </div>
                        <div className="flex items-center gap-2 rounded-lg bg-gray-50 px-3 py-1.5 dark:bg-gray-800">
                            <span className="text-lg font-bold text-gray-600 dark:text-gray-400">{totalCompletedCount}</span>
                            <span className="text-sm text-gray-700 dark:text-gray-300">Completed</span>
                        </div>
                        {canManageTypes && (
                            <Link href="/minor-procedures/types">
                                <Button variant="outline" size="sm" className="gap-2">
                                    <Settings className="h-4 w-4" /> Configure Procedures
                                </Button>
                            </Link>
                        )}
                    </div>
                </div>

                {/* Tabs */}
                <Tabs
                    value={activeTab}
                    onValueChange={(value) => {
                        setActiveTab(value);
                        try { sessionStorage.setItem('minor_procedure_active_tab', value); } catch { /* ignore */ }
                    }}
                    className="w-full"
                >
                    <div className="flex items-center justify-between gap-4">
                        <TabsList>
                            <TabsTrigger value="search" className="gap-2">
                                <Search className="h-4 w-4" /> Search Patient
                            </TabsTrigger>
                            <TabsTrigger value="queue" className="gap-2">
                                <List className="h-4 w-4" /> Patient Queue
                            </TabsTrigger>
                            <TabsTrigger value="completed" className="gap-2">
                                <CheckCircle className="h-4 w-4" /> Completed
                            </TabsTrigger>
                        </TabsList>

                        {/* Queue tab toolbar */}
                        {activeTab === 'queue' && (
                            <div className="flex items-center gap-3 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 dark:border-blue-800 dark:bg-blue-950/50">
                                <div className="relative">
                                    <Search className="absolute top-1/2 left-2.5 h-4 w-4 -translate-y-1/2 text-gray-400" />
                                    <Input
                                        type="text"
                                        placeholder="Search patient..."
                                        value={queueSearch}
                                        onChange={(e) => setQueueSearch(e.target.value)}
                                        className="w-[200px] pl-8 border-blue-300 bg-white dark:border-blue-700 dark:bg-blue-900"
                                    />
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="text-sm text-blue-700 dark:text-blue-300">Show</span>
                                    <select
                                        value={perPage}
                                        onChange={(e) => handlePerPageChange(e.target.value)}
                                        className="h-8 rounded-md border border-blue-300 bg-white px-2 text-sm dark:border-blue-700 dark:bg-blue-900"
                                    >
                                        <option value="5">5</option>
                                        <option value="10">10</option>
                                        <option value="25">25</option>
                                        <option value="50">50</option>
                                    </select>
                                </div>
                                <DateFilterPresets value={dateFilter} onChange={handleDateFilterChange} />
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={handleManualRefresh}
                                    className="gap-2 border-blue-300 bg-white hover:bg-blue-100 dark:border-blue-700 dark:bg-blue-900 dark:hover:bg-blue-800"
                                >
                                    <RefreshCw className="h-4 w-4" />
                                    <span className="text-xs text-muted-foreground">{getTimeSinceUpdate()}</span>
                                </Button>
                            </div>
                        )}

                        {/* Completed tab toolbar */}
                        {activeTab === 'completed' && (
                            <div className="flex items-center gap-3 rounded-lg border border-green-200 bg-green-50 px-3 py-2 dark:border-green-800 dark:bg-green-950/50">
                                <div className="relative">
                                    <Search className="absolute top-1/2 left-2.5 h-4 w-4 -translate-y-1/2 text-gray-400" />
                                    <Input
                                        type="text"
                                        placeholder="Search patient..."
                                        value={completedSearch}
                                        onChange={(e) => setCompletedSearch(e.target.value)}
                                        className="w-[200px] pl-8 border-green-300 bg-white dark:border-green-700 dark:bg-green-900"
                                    />
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="text-sm text-green-700 dark:text-green-300">Show</span>
                                    <select
                                        value={perPage}
                                        onChange={(e) => handlePerPageChange(e.target.value)}
                                        className="h-8 rounded-md border border-green-300 bg-white px-2 text-sm dark:border-green-700 dark:bg-green-900"
                                    >
                                        <option value="5">5</option>
                                        <option value="10">10</option>
                                        <option value="25">25</option>
                                        <option value="50">50</option>
                                    </select>
                                </div>
                                <DateFilterPresets value={dateFilter} onChange={handleDateFilterChange} />
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={handleManualRefresh}
                                    className="gap-2 border-green-300 bg-white hover:bg-green-100 dark:border-green-700 dark:bg-green-900 dark:hover:bg-green-800"
                                >
                                    <RefreshCw className="h-4 w-4" /> Refresh
                                </Button>
                            </div>
                        )}
                    </div>

                    {/* Search Tab */}
                    <TabsContent value="search" className="mt-6 space-y-6">
                        <Card>
                            <CardContent className="pt-6">
                                <div className="relative">
                                    <Search className="absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2 text-gray-400" />
                                    <Input
                                        type="text"
                                        placeholder="Search by patient name, ID, or phone number..."
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        className="pl-10"
                                        autoFocus={activeTab === 'search'}
                                    />
                                </div>
                                {search && search.length < 2 && (
                                    <p className="mt-2 text-sm text-muted-foreground">Type at least 2 characters to search</p>
                                )}
                            </CardContent>
                        </Card>

                        {filters.search && filters.search.length >= 2 && (
                            <>
                                {queuePatients.data.length === 0 && completedProcedures.data.length === 0 ? (
                                    <Card>
                                        <CardContent className="py-12">
                                            <div className="text-center text-gray-500">
                                                <Search className="mx-auto mb-3 h-12 w-12 text-gray-300" />
                                                <p className="font-medium">No results found</p>
                                                <p className="mt-1 text-sm">No patients found matching &quot;{filters.search}&quot;</p>
                                            </div>
                                        </CardContent>
                                    </Card>
                                ) : (
                                    <div className="space-y-6">
                                        {queuePatients.data.length > 0 && (
                                            <div className="space-y-3">
                                                <h2 className="text-lg font-semibold">In Queue ({queuePatients.total})</h2>
                                                <div className="rounded-md border">
                                                    <Table>
                                                        <TableHeader>{queueTableHeaders}</TableHeader>
                                                        <TableBody>{queuePatients.data.map(renderQueueRow)}</TableBody>
                                                    </Table>
                                                </div>
                                            </div>
                                        )}

                                        {completedProcedures.data.length > 0 && (
                                            <div className="space-y-3">
                                                <h2 className="text-lg font-semibold">Completed ({completedProcedures.total})</h2>
                                                <div className="rounded-md border">
                                                    <Table>
                                                        <TableHeader>{completedTableHeaders}</TableHeader>
                                                        <TableBody>{completedProcedures.data.map(renderCompletedRow)}</TableBody>
                                                    </Table>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                )}
                            </>
                        )}
                    </TabsContent>

                    {/* Queue Tab */}
                    <TabsContent value="queue" className="mt-6 space-y-6">
                        <div className="space-y-3">
                            <h2 className="flex items-center gap-2 text-lg font-semibold">
                                <Clock className="h-5 w-5 text-blue-600" />
                                Patients in Queue ({queuePatients.total})
                            </h2>
                            {queuePatients.data.length > 0 ? (
                                <>
                                    <div className="rounded-md border">
                                        <Table>
                                            <TableHeader>{queueTableHeaders}</TableHeader>
                                            <TableBody>{queuePatients.data.map(renderQueueRow)}</TableBody>
                                        </Table>
                                    </div>
                                    <Pagination
                                        currentPage={queuePatients.current_page}
                                        lastPage={queuePatients.last_page}
                                        from={queuePatients.from}
                                        to={queuePatients.to}
                                        total={queuePatients.total}
                                        onPageChange={handleQueuePageChange}
                                    />
                                </>
                            ) : (
                                <Card>
                                    <CardContent className="py-12">
                                        <div className="text-center text-gray-500">
                                            <Clock className="mx-auto mb-3 h-12 w-12 text-gray-300" />
                                            <p className="font-medium">No patients in queue</p>
                                            <p className="mt-1 text-sm">Patients will appear here when they check in to the Minor Procedures department</p>
                                        </div>
                                    </CardContent>
                                </Card>
                            )}
                        </div>
                    </TabsContent>

                    {/* Completed Tab */}
                    <TabsContent value="completed" className="mt-6 space-y-6">
                        <div className="space-y-3">
                            <h2 className="flex items-center gap-2 text-lg font-semibold">
                                <CheckCircle className="h-5 w-5 text-gray-600" />
                                Completed Procedures ({completedProcedures.total})
                            </h2>
                            {completedProcedures.data.length > 0 ? (
                                <>
                                    <div className="rounded-md border">
                                        <Table>
                                            <TableHeader>{completedTableHeaders}</TableHeader>
                                            <TableBody>{completedProcedures.data.map(renderCompletedRow)}</TableBody>
                                        </Table>
                                    </div>
                                    <Pagination
                                        currentPage={completedProcedures.current_page}
                                        lastPage={completedProcedures.last_page}
                                        from={completedProcedures.from}
                                        to={completedProcedures.to}
                                        total={completedProcedures.total}
                                        onPageChange={handleCompletedPageChange}
                                    />
                                </>
                            ) : (
                                <Card>
                                    <CardContent className="py-12">
                                        <div className="text-center text-gray-500">
                                            <CheckCircle className="mx-auto mb-3 h-12 w-12 text-gray-300" />
                                            <p className="font-medium">No completed procedures</p>
                                            <p className="mt-1 text-sm">Completed procedures for the selected date will appear here</p>
                                        </div>
                                    </CardContent>
                                </Card>
                            )}
                        </div>
                    </TabsContent>
                </Tabs>
            </div>

            {/* Procedure Form Modal - New */}
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
                    onSuccess={handleProcedureSuccess}
                />
            )}

            {/* Procedure Form Modal - Edit */}
            {editProcedure && (
                <ProcedureForm
                    open={!!editProcedure}
                    onClose={() => setEditProcedure(null)}
                    patientCheckin={editProcedure.patient_checkin as PatientCheckin}
                    procedureTypes={procedureTypes}
                    availableDrugs={availableDrugs}
                    onSuccess={() => {
                        setEditProcedure(null);
                        router.reload();
                    }}
                    existingProcedure={editProcedure}
                />
            )}
        </AppLayout>
    );
}
