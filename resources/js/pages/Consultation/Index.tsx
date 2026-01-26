import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    DateFilterPresets,
    DateFilterValue,
    calculateDateRange,
} from '@/components/ui/date-filter-presets';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Pagination } from '@/components/ui/pagination';
import AppLayout from '@/layouts/app-layout';
import { Head, router, usePoll } from '@inertiajs/react';
import { CheckCircle, Clock, Eye, List, RefreshCw, Search } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Patient {
    id: number;
    patient_number: string;
    first_name: string;
    last_name: string;
    date_of_birth: string;
    phone_number: string;
}

interface Department {
    id: number;
    name: string;
    code?: string;
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

interface VitalSigns {
    temperature: number;
    blood_pressure_systolic: number;
    blood_pressure_diastolic: number;
    heart_rate: number;
    respiratory_rate: number;
}

interface PatientCheckin {
    id: number;
    patient: PatientWithInsurance;
    department: Department;
    checked_in_at: string;
    service_date: string;
    status: string;
    vital_signs?: VitalSigns[];
}

interface Doctor {
    id: number;
    name: string;
}

interface ActiveConsultation {
    id: number;
    started_at: string;
    status: string;
    doctor?: Doctor;
    patient_checkin: {
        patient: Pick<
            Patient,
            | 'id'
            | 'patient_number'
            | 'first_name'
            | 'last_name'
            | 'date_of_birth'
            | 'phone_number'
        > & { active_insurance?: PatientInsurance | null };
        department: Department;
        service_date: string;
    };
}

interface CompletedConsultation {
    id: number;
    started_at: string;
    completed_at: string;
    status: string;
    doctor?: Doctor;
    patient_checkin: {
        patient: Pick<
            Patient,
            | 'id'
            | 'patient_number'
            | 'first_name'
            | 'last_name'
            | 'date_of_birth'
            | 'phone_number'
        > & { active_insurance?: PatientInsurance | null };
        department: Department;
        service_date: string;
    };
}

interface Filters {
    search?: string;
    queue_search?: string;
    completed_search?: string;
    department_id?: string;
    date_from?: string;
    date_to?: string;
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
    awaitingConsultation: PaginatedData<PatientCheckin>;
    activeConsultations: PaginatedData<ActiveConsultation>;
    completedConsultations: PaginatedData<CompletedConsultation>;
    totalAwaitingCount: number;
    totalActiveCount: number;
    totalCompletedCount: number;
    departments: Department[];
    filters: Filters;
    canFilterByDate: boolean;
}

export default function ConsultationIndex({
    awaitingConsultation,
    activeConsultations,
    completedConsultations,
    totalAwaitingCount,
    totalActiveCount,
    totalCompletedCount,
    departments,
    filters,
    canFilterByDate,
}: Props) {
    const [activeTab, setActiveTab] = useState<string>('search');
    const [search, setSearch] = useState(filters.search || '');
    const [queueSearch, setQueueSearch] = useState(filters.queue_search || '');
    const [completedSearch, setCompletedSearch] = useState(filters.completed_search || '');
    const [departmentFilter, setDepartmentFilter] = useState(
        filters.department_id || '',
    );
    // Initialize date filter - default to "today" preset
    const [dateFilter, setDateFilter] = useState<DateFilterValue>(() => {
        if (filters.date_from || filters.date_to) {
            // Check if it matches a preset
            const todayRange = calculateDateRange('today');
            if (
                filters.date_from === todayRange.from &&
                filters.date_to === todayRange.to
            ) {
                return {
                    preset: 'today',
                    from: filters.date_from,
                    to: filters.date_to,
                };
            }
            return {
                preset: 'custom',
                from: filters.date_from,
                to: filters.date_to,
            };
        }
        // Default to today
        const todayRange = calculateDateRange('today');
        return { preset: 'today', ...todayRange };
    });
    const [lastUpdated, setLastUpdated] = useState<Date>(new Date());
    const [confirmDialog, setConfirmDialog] = useState<{
        open: boolean;
        type: 'start' | 'continue';
        data?: PatientCheckin | ActiveConsultation;
    }>({
        open: false,
        type: 'start',
    });

    // Auto-poll every 30 seconds for queue updates
    const { stop, start } = usePoll(
        30000,
        {
            only: [
                'awaitingConsultation',
                'activeConsultations',
                'totalAwaitingCount',
                'totalActiveCount',
            ],
            onFinish: () => {
                setLastUpdated(new Date());
            },
        },
        {
            autoStart: activeTab === 'queue',
        },
    );

    // Start/stop polling based on active tab
    useEffect(() => {
        if (activeTab === 'queue') {
            start();
        } else {
            stop();
        }
    }, [activeTab, start, stop]);

    const formatTime = (dateString: string) => {
        return new Date(dateString).toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    };

    const calculateAge = (dateOfBirth: string) => {
        const today = new Date();
        const birth = new Date(dateOfBirth);
        let age = today.getFullYear() - birth.getFullYear();
        const monthDiff = today.getMonth() - birth.getMonth();

        if (
            monthDiff < 0 ||
            (monthDiff === 0 && today.getDate() < birth.getDate())
        ) {
            age--;
        }

        return age;
    };

    const getTimeSinceUpdate = () => {
        const seconds = Math.floor(
            (new Date().getTime() - lastUpdated.getTime()) / 1000,
        );
        if (seconds < 60) return `${seconds}s ago`;
        const minutes = Math.floor(seconds / 60);
        return `${minutes}m ago`;
    };

    // Debounced search effect
    useEffect(() => {
        if (activeTab !== 'search') return;

        const timeoutId = setTimeout(() => {
            if (search.length >= 2 || search.length === 0) {
                router.get(
                    '/consultation',
                    {
                        search: search || undefined,
                        department_id: departmentFilter || undefined,
                        date_from: dateFilter.from || undefined,
                        date_to: dateFilter.to || undefined,
                    },
                    {
                        preserveState: true,
                        preserveScroll: true,
                        replace: true,
                    },
                );
            }
        }, 500);

        return () => clearTimeout(timeoutId);
    }, [search, activeTab]);

    // Debounced queue search effect
    useEffect(() => {
        if (activeTab !== 'queue') return;

        const timeoutId = setTimeout(() => {
            if (queueSearch.length >= 2 || queueSearch.length === 0) {
                router.get(
                    '/consultation',
                    {
                        queue_search: queueSearch || undefined,
                        department_id: departmentFilter || undefined,
                        date_from: dateFilter.from || undefined,
                        date_to: dateFilter.to || undefined,
                    },
                    {
                        preserveState: true,
                        preserveScroll: true,
                        replace: true,
                    },
                );
            }
        }, 500);

        return () => clearTimeout(timeoutId);
    }, [queueSearch, activeTab]);

    // Debounced completed search effect
    useEffect(() => {
        if (activeTab !== 'completed') return;

        const timeoutId = setTimeout(() => {
            if (completedSearch.length >= 2 || completedSearch.length === 0) {
                router.get(
                    '/consultation',
                    {
                        completed_search: completedSearch || undefined,
                        department_id: departmentFilter || undefined,
                        date_from: dateFilter.from || undefined,
                        date_to: dateFilter.to || undefined,
                    },
                    {
                        preserveState: true,
                        preserveScroll: true,
                        replace: true,
                    },
                );
            }
        }, 500);

        return () => clearTimeout(timeoutId);
    }, [completedSearch, activeTab]);

    // Department filter change
    const handleDepartmentChange = (value: string) => {
        const newValue = value === 'all' ? '' : value;
        setDepartmentFilter(newValue);

        router.get(
            '/consultation',
            {
                search: search || undefined,
                queue_search: queueSearch || undefined,
                completed_search: completedSearch || undefined,
                department_id: newValue || undefined,
                date_from: dateFilter.from || undefined,
                date_to: dateFilter.to || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    // Date filter change
    const handleDateFilterChange = (value: DateFilterValue) => {
        setDateFilter(value);

        router.get(
            '/consultation',
            {
                search: search || undefined,
                queue_search: queueSearch || undefined,
                completed_search: completedSearch || undefined,
                department_id: departmentFilter || undefined,
                date_from: value.from || undefined,
                date_to: value.to || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const handleManualRefresh = () => {
        router.reload({
            only: [
                'awaitingConsultation',
                'activeConsultations',
                'totalAwaitingCount',
                'totalActiveCount',
            ],
            onFinish: () => {
                setLastUpdated(new Date());
            },
        });
    };

    // Pagination handlers
    const handleAwaitingPageChange = (page: number) => {
        router.get(
            '/consultation',
            {
                search: search || undefined,
                queue_search: queueSearch || undefined,
                completed_search: completedSearch || undefined,
                department_id: departmentFilter || undefined,
                date_from: dateFilter.from || undefined,
                date_to: dateFilter.to || undefined,
                awaiting_page: page,
                active_page: activeConsultations.current_page,
                completed_page: completedConsultations.current_page,
            },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const handleActivePageChange = (page: number) => {
        router.get(
            '/consultation',
            {
                search: search || undefined,
                queue_search: queueSearch || undefined,
                completed_search: completedSearch || undefined,
                department_id: departmentFilter || undefined,
                date_from: dateFilter.from || undefined,
                date_to: dateFilter.to || undefined,
                awaiting_page: awaitingConsultation.current_page,
                active_page: page,
                completed_page: completedConsultations.current_page,
            },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const handleCompletedPageChange = (page: number) => {
        router.get(
            '/consultation',
            {
                search: search || undefined,
                queue_search: queueSearch || undefined,
                completed_search: completedSearch || undefined,
                department_id: departmentFilter || undefined,
                date_from: dateFilter.from || undefined,
                date_to: dateFilter.to || undefined,
                awaiting_page: awaitingConsultation.current_page,
                active_page: activeConsultations.current_page,
                completed_page: page,
            },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const openStartDialog = (checkin: PatientCheckin) => {
        setConfirmDialog({
            open: true,
            type: 'start',
            data: checkin,
        });
    };

    const openContinueDialog = (consultation: ActiveConsultation) => {
        setConfirmDialog({
            open: true,
            type: 'continue',
            data: consultation,
        });
    };

    const handleConfirm = () => {
        if (!confirmDialog.data) return;

        if (confirmDialog.type === 'start') {
            const checkin = confirmDialog.data as PatientCheckin;
            router.post(
                '/consultation',
                {
                    patient_checkin_id: checkin.id,
                },
                {
                    onSuccess: () => {
                        setConfirmDialog({ open: false, type: 'start' });
                    },
                },
            );
        } else {
            const consultation = confirmDialog.data as ActiveConsultation;
            router.visit(`/consultation/${consultation.id}`);
            setConfirmDialog({ open: false, type: 'continue' });
        }
    };

    const getDialogPatient = () => {
        if (!confirmDialog.data) return null;

        if (confirmDialog.type === 'start') {
            return (confirmDialog.data as PatientCheckin).patient;
        } else {
            return (confirmDialog.data as ActiveConsultation).patient_checkin
                .patient;
        }
    };

    const getDialogDepartment = () => {
        if (!confirmDialog.data) return null;

        if (confirmDialog.type === 'start') {
            return (confirmDialog.data as PatientCheckin).department;
        } else {
            return (confirmDialog.data as ActiveConsultation).patient_checkin
                .department;
        }
    };

    return (
        <AppLayout
            breadcrumbs={[{ title: 'Consultation', href: '/consultation' }]}
        >
            <Head title="Consultation Dashboard" />

            <div className="space-y-6">
                <div className="flex items-start justify-between">
                    <div>
                        <h1 className="text-3xl font-bold text-gray-900 dark:text-gray-100">
                            Consultation Dashboard
                        </h1>
                        <p className="mt-1 text-gray-600 dark:text-gray-400">
                            Manage patient consultations
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <div className="flex items-center gap-2 rounded-lg bg-blue-50 px-3 py-1.5 dark:bg-blue-950">
                            <span className="text-lg font-bold text-blue-600 dark:text-blue-400">
                                {totalAwaitingCount}
                            </span>
                            <span className="text-sm text-blue-700 dark:text-blue-300">
                                Awaiting
                            </span>
                        </div>
                        <div className="flex items-center gap-2 rounded-lg bg-green-50 px-3 py-1.5 dark:bg-green-950">
                            <span className="text-lg font-bold text-green-600 dark:text-green-400">
                                {totalActiveCount}
                            </span>
                            <span className="text-sm text-green-700 dark:text-green-300">
                                Active
                            </span>
                        </div>
                        <div className="flex items-center gap-2 rounded-lg bg-gray-50 px-3 py-1.5 dark:bg-gray-800">
                            <span className="text-lg font-bold text-gray-600 dark:text-gray-400">
                                {totalCompletedCount}
                            </span>
                            <span className="text-sm text-gray-700 dark:text-gray-300">
                                Completed
                            </span>
                        </div>
                    </div>
                </div>

                <Tabs
                    value={activeTab}
                    onValueChange={setActiveTab}
                    className="w-full"
                >
                    <div className="flex items-center justify-between gap-4">
                        <TabsList>
                            <TabsTrigger value="search" className="gap-2">
                                <Search className="h-4 w-4" />
                                Search Patient
                            </TabsTrigger>
                            <TabsTrigger value="queue" className="gap-2">
                                <List className="h-4 w-4" />
                                Patient Queue
                            </TabsTrigger>
                            <TabsTrigger value="completed" className="gap-2">
                                <CheckCircle className="h-4 w-4" />
                                Completed
                            </TabsTrigger>
                        </TabsList>

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
                                {canFilterByDate && (
                                    <DateFilterPresets
                                        value={dateFilter}
                                        onChange={handleDateFilterChange}
                                    />
                                )}
                                <Select
                                    value={departmentFilter || 'all'}
                                    onValueChange={handleDepartmentChange}
                                >
                                    <SelectTrigger className="w-[180px] border-blue-300 bg-white dark:border-blue-700 dark:bg-blue-900">
                                        <SelectValue placeholder="All Departments" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All Departments
                                        </SelectItem>
                                        {departments.map((dept) => (
                                            <SelectItem
                                                key={dept.id}
                                                value={dept.id.toString()}
                                            >
                                                {dept.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>

                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={handleManualRefresh}
                                    className="gap-2 border-blue-300 bg-white hover:bg-blue-100 dark:border-blue-700 dark:bg-blue-900 dark:hover:bg-blue-800"
                                >
                                    <RefreshCw className="h-4 w-4" />
                                    <span className="text-xs text-muted-foreground">
                                        {getTimeSinceUpdate()}
                                    </span>
                                </Button>
                            </div>
                        )}

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
                                {canFilterByDate && (
                                    <DateFilterPresets
                                        value={dateFilter}
                                        onChange={handleDateFilterChange}
                                    />
                                )}
                                <Select
                                    value={departmentFilter || 'all'}
                                    onValueChange={handleDepartmentChange}
                                >
                                    <SelectTrigger className="w-[180px] border-green-300 bg-white dark:border-green-700 dark:bg-green-900">
                                        <SelectValue placeholder="All Departments" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All Departments
                                        </SelectItem>
                                        {departments.map((dept) => (
                                            <SelectItem
                                                key={dept.id}
                                                value={dept.id.toString()}
                                            >
                                                {dept.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>

                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={handleManualRefresh}
                                    className="gap-2 border-green-300 bg-white hover:bg-green-100 dark:border-green-700 dark:bg-green-900 dark:hover:bg-green-800"
                                >
                                    <RefreshCw className="h-4 w-4" />
                                    Refresh
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
                                        onChange={(e) =>
                                            setSearch(e.target.value)
                                        }
                                        className="pl-10"
                                        autoFocus={activeTab === 'search'}
                                    />
                                </div>
                                {search && search.length < 2 && (
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        Type at least 2 characters to search
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        {/* Search Results */}
                        {filters.search && filters.search.length >= 2 && (
                            <>
                                {awaitingConsultation.data.length === 0 &&
                                activeConsultations.data.length === 0 ? (
                                    <Card>
                                        <CardContent className="py-12">
                                            <div className="text-center text-gray-500">
                                                <Search className="mx-auto mb-3 h-12 w-12 text-gray-300" />
                                                <p className="font-medium">
                                                    No results found
                                                </p>
                                                <p className="mt-1 text-sm">
                                                    No patients found matching "
                                                    {filters.search}"
                                                </p>
                                            </div>
                                        </CardContent>
                                    </Card>
                                ) : (
                                    <div className="space-y-6">
                                        {activeConsultations.data.length > 0 && (
                                            <div className="space-y-3">
                                                <h2 className="text-lg font-semibold">
                                                    Active Consultations (
                                                    {activeConsultations.total}
                                                    )
                                                </h2>
                                                <div className="rounded-md border">
                                                    <Table>
                                                        <TableHeader>
                                                            <TableRow>
                                                                <TableHead>
                                                                    Patient
                                                                </TableHead>
                                                                <TableHead>
                                                                    ID
                                                                </TableHead>
                                                                <TableHead>
                                                                    Age
                                                                </TableHead>
                                                                <TableHead>
                                                                    Department
                                                                </TableHead>
                                                                <TableHead>
                                                                    Doctor
                                                                </TableHead>
                                                                <TableHead>
                                                                    Date
                                                                </TableHead>
                                                                <TableHead className="text-right">
                                                                    Action
                                                                </TableHead>
                                                            </TableRow>
                                                        </TableHeader>
                                                        <TableBody>
                                                            {activeConsultations.data.map(
                                                                (
                                                                    consultation,
                                                                ) => (
                                                                    <TableRow
                                                                        key={
                                                                            consultation.id
                                                                        }
                                                                    >
                                                                        <TableCell className="font-medium">
                                                                            {
                                                                                consultation
                                                                                    .patient_checkin
                                                                                    .patient
                                                                                    .first_name
                                                                            }{' '}
                                                                            {
                                                                                consultation
                                                                                    .patient_checkin
                                                                                    .patient
                                                                                    .last_name
                                                                            }
                                                                            <Badge className="ml-2">
                                                                                In
                                                                                Progress
                                                                            </Badge>
                                                                        </TableCell>
                                                                        <TableCell>
                                                                            {
                                                                                consultation
                                                                                    .patient_checkin
                                                                                    .patient
                                                                                    .patient_number
                                                                            }
                                                                        </TableCell>
                                                                        <TableCell>
                                                                            {calculateAge(
                                                                                consultation
                                                                                    .patient_checkin
                                                                                    .patient
                                                                                    .date_of_birth,
                                                                            )}{' '}
                                                                            yrs
                                                                        </TableCell>
                                                                        <TableCell>
                                                                            {consultation
                                                                                .patient_checkin
                                                                                .department
                                                                                ?.name ??
                                                                                'Unknown'}
                                                                        </TableCell>
                                                                        <TableCell>
                                                                            {consultation
                                                                                .doctor
                                                                                ?.name ??
                                                                                '-'}
                                                                        </TableCell>
                                                                        <TableCell>
                                                                            {formatDate(
                                                                                consultation
                                                                                    .patient_checkin
                                                                                    .service_date,
                                                                            )}
                                                                        </TableCell>
                                                                        <TableCell className="text-right">
                                                                            <Button
                                                                                size="sm"
                                                                                onClick={() =>
                                                                                    openContinueDialog(
                                                                                        consultation,
                                                                                    )
                                                                                }
                                                                            >
                                                                                Continue
                                                                            </Button>
                                                                        </TableCell>
                                                                    </TableRow>
                                                                ),
                                                            )}
                                                        </TableBody>
                                                    </Table>
                                                </div>
                                            </div>
                                        )}

                                        {awaitingConsultation.data.length > 0 && (
                                            <div className="space-y-3">
                                                <h2 className="text-lg font-semibold">
                                                    Awaiting Consultation (
                                                    {
                                                        awaitingConsultation.total
                                                    }
                                                    )
                                                </h2>
                                                <div className="rounded-md border">
                                                    <Table>
                                                        <TableHeader>
                                                            <TableRow>
                                                                <TableHead>
                                                                    Patient
                                                                </TableHead>
                                                                <TableHead>
                                                                    ID
                                                                </TableHead>
                                                                <TableHead>
                                                                    Age
                                                                </TableHead>
                                                                <TableHead>
                                                                    Department
                                                                </TableHead>
                                                                <TableHead>
                                                                    Date
                                                                </TableHead>
                                                                <TableHead>
                                                                    Vitals
                                                                </TableHead>
                                                                <TableHead>
                                                                    Insurance
                                                                </TableHead>
                                                                <TableHead className="text-right">
                                                                    Action
                                                                </TableHead>
                                                            </TableRow>
                                                        </TableHeader>
                                                        <TableBody>
                                                            {awaitingConsultation.data.map(
                                                                (checkin) => (
                                                                    <TableRow
                                                                        key={
                                                                            checkin.id
                                                                        }
                                                                    >
                                                                        <TableCell className="font-medium">
                                                                            {
                                                                                checkin
                                                                                    .patient
                                                                                    .first_name
                                                                            }{' '}
                                                                            {
                                                                                checkin
                                                                                    .patient
                                                                                    .last_name
                                                                            }
                                                                        </TableCell>
                                                                        <TableCell>
                                                                            {
                                                                                checkin
                                                                                    .patient
                                                                                    .patient_number
                                                                            }
                                                                        </TableCell>
                                                                        <TableCell>
                                                                            {calculateAge(
                                                                                checkin
                                                                                    .patient
                                                                                    .date_of_birth,
                                                                            )}{' '}
                                                                            yrs
                                                                        </TableCell>
                                                                        <TableCell>
                                                                            {
                                                                                checkin
                                                                                    .department
                                                                                    .name
                                                                            }
                                                                        </TableCell>
                                                                        <TableCell>
                                                                            {formatDate(
                                                                                checkin.service_date,
                                                                            )}
                                                                        </TableCell>
                                                                        <TableCell>
                                                                            {checkin.vital_signs &&
                                                                            checkin
                                                                                .vital_signs
                                                                                .length >
                                                                                0 ? (
                                                                                <Badge
                                                                                    variant="outline"
                                                                                    className="bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300"
                                                                                >
                                                                                    ✓
                                                                                    Taken
                                                                                </Badge>
                                                                            ) : (
                                                                                <Badge
                                                                                    variant="outline"
                                                                                    className="text-muted-foreground"
                                                                                >
                                                                                    Pending
                                                                                </Badge>
                                                                            )}
                                                                        </TableCell>
                                                                        <TableCell>
                                                                            {checkin
                                                                                .patient
                                                                                .active_insurance ? (
                                                                                <Badge
                                                                                    variant="outline"
                                                                                    className="bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300"
                                                                                >
                                                                                    {
                                                                                        checkin
                                                                                            .patient
                                                                                            .active_insurance
                                                                                            .plan
                                                                                            .provider
                                                                                            .code
                                                                                    }
                                                                                </Badge>
                                                                            ) : (
                                                                                <Badge
                                                                                    variant="outline"
                                                                                    className="text-muted-foreground"
                                                                                >
                                                                                    Cash
                                                                                </Badge>
                                                                            )}
                                                                        </TableCell>
                                                                        <TableCell className="text-right">
                                                                            <Button
                                                                                size="sm"
                                                                                onClick={() =>
                                                                                    openStartDialog(
                                                                                        checkin,
                                                                                    )
                                                                                }
                                                                            >
                                                                                Start
                                                                            </Button>
                                                                        </TableCell>
                                                                    </TableRow>
                                                                ),
                                                            )}
                                                        </TableBody>
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
                        {/* Active Consultations Table */}
                        {activeConsultations.data.length > 0 && (
                            <div className="space-y-3">
                                <h2 className="flex items-center gap-2 text-lg font-semibold">
                                    Active Consultations (
                                    {activeConsultations.total})
                                </h2>
                                <div className="rounded-md border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Patient</TableHead>
                                                <TableHead>ID</TableHead>
                                                <TableHead>Age</TableHead>
                                                <TableHead>
                                                    Department
                                                </TableHead>
                                                <TableHead>Doctor</TableHead>
                                                <TableHead>Date</TableHead>
                                                <TableHead className="text-right">
                                                    Action
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {activeConsultations.data.map(
                                                (consultation) => (
                                                    <TableRow
                                                        key={consultation.id}
                                                    >
                                                        <TableCell className="font-medium">
                                                            {
                                                                consultation
                                                                    .patient_checkin
                                                                    .patient
                                                                    .first_name
                                                            }{' '}
                                                            {
                                                                consultation
                                                                    .patient_checkin
                                                                    .patient
                                                                    .last_name
                                                            }
                                                            <Badge className="ml-2">
                                                                In Progress
                                                            </Badge>
                                                        </TableCell>
                                                        <TableCell>
                                                            {
                                                                consultation
                                                                    .patient_checkin
                                                                    .patient
                                                                    .patient_number
                                                            }
                                                        </TableCell>
                                                        <TableCell>
                                                            {calculateAge(
                                                                consultation
                                                                    .patient_checkin
                                                                    .patient
                                                                    .date_of_birth,
                                                            )}{' '}
                                                            yrs
                                                        </TableCell>
                                                        <TableCell>
                                                            {consultation
                                                                .patient_checkin
                                                                .department
                                                                ?.name ??
                                                                'Unknown'}
                                                        </TableCell>
                                                        <TableCell>
                                                            {consultation.doctor
                                                                ?.name ?? '-'}
                                                        </TableCell>
                                                        <TableCell>
                                                            {formatDate(
                                                                consultation
                                                                    .patient_checkin
                                                                    .service_date,
                                                            )}
                                                        </TableCell>
                                                        <TableCell className="text-right">
                                                            <Button
                                                                size="sm"
                                                                onClick={() =>
                                                                    openContinueDialog(
                                                                        consultation,
                                                                    )
                                                                }
                                                            >
                                                                Continue
                                                            </Button>
                                                        </TableCell>
                                                    </TableRow>
                                                ),
                                            )}
                                        </TableBody>
                                    </Table>
                                </div>
                                <Pagination
                                    currentPage={activeConsultations.current_page}
                                    lastPage={activeConsultations.last_page}
                                    from={activeConsultations.from}
                                    to={activeConsultations.to}
                                    total={activeConsultations.total}
                                    onPageChange={handleActivePageChange}
                                />
                            </div>
                        )}

                        {/* Awaiting Consultation Table */}
                        <div className="space-y-3">
                            <h2 className="flex items-center gap-2 text-lg font-semibold">
                                <Clock className="h-5 w-5 text-blue-600" />
                                Awaiting Consultation (
                                {awaitingConsultation.total})
                            </h2>
                            {awaitingConsultation.data.length > 0 ? (
                                <>
                                <div className="rounded-md border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Patient</TableHead>
                                                <TableHead>ID</TableHead>
                                                <TableHead>Age</TableHead>
                                                <TableHead>
                                                    Department
                                                </TableHead>
                                                <TableHead>Date</TableHead>
                                                <TableHead>Vitals</TableHead>
                                                <TableHead>Insurance</TableHead>
                                                <TableHead className="text-right">
                                                    Action
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {awaitingConsultation.data.map(
                                                (checkin) => (
                                                    <TableRow key={checkin.id}>
                                                        <TableCell className="font-medium">
                                                            {
                                                                checkin.patient
                                                                    .first_name
                                                            }{' '}
                                                            {
                                                                checkin.patient
                                                                    .last_name
                                                            }
                                                        </TableCell>
                                                        <TableCell>
                                                            {
                                                                checkin.patient
                                                                    .patient_number
                                                            }
                                                        </TableCell>
                                                        <TableCell>
                                                            {calculateAge(
                                                                checkin.patient
                                                                    .date_of_birth,
                                                            )}{' '}
                                                            yrs
                                                        </TableCell>
                                                        <TableCell>
                                                            {
                                                                checkin
                                                                    .department
                                                                    .name
                                                            }
                                                        </TableCell>
                                                        <TableCell>
                                                            {formatDate(
                                                                checkin.service_date,
                                                            )}
                                                        </TableCell>
                                                        <TableCell>
                                                            {checkin.vital_signs &&
                                                            checkin.vital_signs
                                                                .length > 0 ? (
                                                                <Badge
                                                                    variant="outline"
                                                                    className="bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300"
                                                                >
                                                                    ✓ Taken
                                                                </Badge>
                                                            ) : (
                                                                <Badge
                                                                    variant="outline"
                                                                    className="text-muted-foreground"
                                                                >
                                                                    Pending
                                                                </Badge>
                                                            )}
                                                        </TableCell>
                                                        <TableCell>
                                                            {checkin.patient
                                                                .active_insurance ? (
                                                                <Badge
                                                                    variant="outline"
                                                                    className="bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300"
                                                                >
                                                                    {
                                                                        checkin
                                                                            .patient
                                                                            .active_insurance
                                                                            .plan
                                                                            .provider
                                                                            .code
                                                                    }
                                                                </Badge>
                                                            ) : (
                                                                <Badge
                                                                    variant="outline"
                                                                    className="text-muted-foreground"
                                                                >
                                                                    Cash
                                                                </Badge>
                                                            )}
                                                        </TableCell>
                                                        <TableCell className="text-right">
                                                            <Button
                                                                size="sm"
                                                                onClick={() =>
                                                                    openStartDialog(
                                                                        checkin,
                                                                    )
                                                                }
                                                            >
                                                                Start
                                                            </Button>
                                                        </TableCell>
                                                    </TableRow>
                                                ),
                                            )}
                                        </TableBody>
                                    </Table>
                                </div>
                                <Pagination
                                    currentPage={awaitingConsultation.current_page}
                                    lastPage={awaitingConsultation.last_page}
                                    from={awaitingConsultation.from}
                                    to={awaitingConsultation.to}
                                    total={awaitingConsultation.total}
                                    onPageChange={handleAwaitingPageChange}
                                />
                                </>
                            ) : (
                                <Card>
                                    <CardContent className="py-12">
                                        <div className="text-center text-gray-500">
                                            <Clock className="mx-auto mb-3 h-12 w-12 text-gray-300" />
                                            <p className="font-medium">
                                                No patients awaiting
                                                consultation
                                            </p>
                                            <p className="mt-1 text-sm">
                                                New check-ins will appear here
                                                automatically
                                            </p>
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
                                Completed Consultations
                                {!canFilterByDate && ' (Last 24 Hours)'}
                            </h2>
                            {completedConsultations.data.length > 0 ? (
                                <>
                                <div className="rounded-md border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Patient</TableHead>
                                                <TableHead>ID</TableHead>
                                                <TableHead>
                                                    Department
                                                </TableHead>
                                                <TableHead>Doctor</TableHead>
                                                <TableHead>Date</TableHead>
                                                <TableHead>Insurance</TableHead>
                                                <TableHead className="text-right">
                                                    Action
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {completedConsultations.data.map(
                                                (consultation) => (
                                                    <TableRow
                                                        key={consultation.id}
                                                    >
                                                        <TableCell className="font-medium">
                                                            {
                                                                consultation
                                                                    .patient_checkin
                                                                    .patient
                                                                    .first_name
                                                            }{' '}
                                                            {
                                                                consultation
                                                                    .patient_checkin
                                                                    .patient
                                                                    .last_name
                                                            }
                                                        </TableCell>
                                                        <TableCell>
                                                            {
                                                                consultation
                                                                    .patient_checkin
                                                                    .patient
                                                                    .patient_number
                                                            }
                                                        </TableCell>
                                                        <TableCell>
                                                            {consultation
                                                                .patient_checkin
                                                                .department
                                                                ?.name ??
                                                                'Unknown'}
                                                        </TableCell>
                                                        <TableCell>
                                                            {consultation.doctor
                                                                ?.name ?? '-'}
                                                        </TableCell>
                                                        <TableCell>
                                                            {formatDate(
                                                                consultation
                                                                    .patient_checkin
                                                                    .service_date,
                                                            )}
                                                        </TableCell>
                                                        <TableCell>
                                                            {consultation
                                                                .patient_checkin
                                                                .patient
                                                                .active_insurance ? (
                                                                <Badge
                                                                    variant="outline"
                                                                    className="bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300"
                                                                >
                                                                    {
                                                                        consultation
                                                                            .patient_checkin
                                                                            .patient
                                                                            .active_insurance
                                                                            .plan
                                                                            .provider
                                                                            .code
                                                                    }
                                                                </Badge>
                                                            ) : (
                                                                <Badge
                                                                    variant="outline"
                                                                    className="text-muted-foreground"
                                                                >
                                                                    Cash
                                                                </Badge>
                                                            )}
                                                        </TableCell>
                                                        <TableCell className="text-right">
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() =>
                                                                    router.visit(
                                                                        `/consultation/${consultation.id}`,
                                                                    )
                                                                }
                                                                className="gap-1"
                                                            >
                                                                <Eye className="h-4 w-4" />
                                                                View
                                                            </Button>
                                                        </TableCell>
                                                    </TableRow>
                                                ),
                                            )}
                                        </TableBody>
                                    </Table>
                                </div>
                                <Pagination
                                    currentPage={completedConsultations.current_page}
                                    lastPage={completedConsultations.last_page}
                                    from={completedConsultations.from}
                                    to={completedConsultations.to}
                                    total={completedConsultations.total}
                                    onPageChange={handleCompletedPageChange}
                                />
                                </>
                            ) : (
                                <Card>
                                    <CardContent className="py-12">
                                        <div className="text-center text-gray-500">
                                            <CheckCircle className="mx-auto mb-3 h-12 w-12 text-gray-300" />
                                            <p className="font-medium">
                                                No completed consultations
                                            </p>
                                            <p className="mt-1 text-sm">
                                                {canFilterByDate
                                                    ? 'Completed consultations for the selected date will appear here'
                                                    : 'Completed consultations from the last 24 hours will appear here'}
                                            </p>
                                        </div>
                                    </CardContent>
                                </Card>
                            )}
                        </div>
                    </TabsContent>
                </Tabs>
            </div>

            {/* Confirmation Dialog */}
            <AlertDialog
                open={confirmDialog.open}
                onOpenChange={(open) =>
                    setConfirmDialog({ ...confirmDialog, open })
                }
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            {confirmDialog.type === 'start'
                                ? 'Start Consultation?'
                                : 'Continue Consultation?'}
                        </AlertDialogTitle>
                        <AlertDialogDescription asChild>
                            <div className="space-y-3 pt-4">
                                <p className="text-base text-gray-700 dark:text-gray-300">
                                    Please verify patient details before
                                    proceeding:
                                </p>
                                {getDialogPatient() && (
                                    <div className="space-y-2 rounded-lg bg-gray-50 p-4 text-sm dark:bg-gray-800">
                                        <div className="flex justify-between">
                                            <span className="font-medium text-gray-700 dark:text-gray-300">
                                                Patient Name:
                                            </span>
                                            <span className="font-semibold text-gray-900 dark:text-gray-100">
                                                {getDialogPatient()?.first_name}{' '}
                                                {getDialogPatient()?.last_name}
                                            </span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="font-medium text-gray-700 dark:text-gray-300">
                                                Patient ID:
                                            </span>
                                            <span className="text-gray-900 dark:text-gray-100">
                                                {
                                                    getDialogPatient()
                                                        ?.patient_number
                                                }
                                            </span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="font-medium text-gray-700 dark:text-gray-300">
                                                Date of Birth:
                                            </span>
                                            <span className="text-gray-900 dark:text-gray-100">
                                                {getDialogPatient()
                                                    ?.date_of_birth &&
                                                    formatDate(
                                                        getDialogPatient()!
                                                            .date_of_birth,
                                                    )}{' '}
                                                (
                                                {getDialogPatient()
                                                    ?.date_of_birth &&
                                                    calculateAge(
                                                        getDialogPatient()!
                                                            .date_of_birth,
                                                    )}{' '}
                                                years)
                                            </span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="font-medium text-gray-700 dark:text-gray-300">
                                                Phone:
                                            </span>
                                            <span className="text-gray-900 dark:text-gray-100">
                                                {
                                                    getDialogPatient()
                                                        ?.phone_number
                                                }
                                            </span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="font-medium text-gray-700 dark:text-gray-300">
                                                Department:
                                            </span>
                                            <span className="text-gray-900 dark:text-gray-100">
                                                {getDialogDepartment()?.name}
                                            </span>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={handleConfirm}>
                            Confirm & Proceed
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
