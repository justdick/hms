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
import AppLayout from '@/layouts/app-layout';
import { Head, router, usePoll } from '@inertiajs/react';
import { Clock, List, RefreshCw, Search } from 'lucide-react';
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

interface VitalSigns {
    temperature: number;
    blood_pressure_systolic: number;
    blood_pressure_diastolic: number;
    heart_rate: number;
    respiratory_rate: number;
}

interface PatientCheckin {
    id: number;
    patient: Patient;
    department: Department;
    checked_in_at: string;
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
        >;
        department: Department;
    };
}

interface Filters {
    search?: string;
    department_id?: string;
}

interface Props {
    awaitingConsultation: PatientCheckin[];
    activeConsultations: ActiveConsultation[];
    totalAwaitingCount: number;
    totalActiveCount: number;
    departments: Department[];
    filters: Filters;
}

export default function ConsultationIndex({
    awaitingConsultation,
    activeConsultations,
    totalAwaitingCount,
    totalActiveCount,
    departments,
    filters,
}: Props) {
    const [activeTab, setActiveTab] = useState<string>('search');
    const [search, setSearch] = useState(filters.search || '');
    const [departmentFilter, setDepartmentFilter] = useState(
        filters.department_id || '',
    );
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

    // Department filter change
    const handleDepartmentChange = (value: string) => {
        const newValue = value === 'all' ? '' : value;
        setDepartmentFilter(newValue);

        router.get(
            '/consultation',
            {
                search: search || undefined,
                department_id: newValue || undefined,
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
                        </TabsList>

                        {activeTab === 'queue' && (
                            <div className="flex items-center gap-3">
                                <Select
                                    value={departmentFilter || 'all'}
                                    onValueChange={handleDepartmentChange}
                                >
                                    <SelectTrigger className="w-[180px]">
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
                                    className="gap-2"
                                >
                                    <RefreshCw className="h-4 w-4" />
                                    <span className="text-xs text-muted-foreground">
                                        {getTimeSinceUpdate()}
                                    </span>
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
                                {awaitingConsultation.length === 0 &&
                                activeConsultations.length === 0 ? (
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
                                        {activeConsultations.length > 0 && (
                                            <div className="space-y-3">
                                                <h2 className="text-lg font-semibold">
                                                    Active Consultations (
                                                    {activeConsultations.length})
                                                </h2>
                                                <div className="rounded-md border">
                                                    <Table>
                                                        <TableHeader>
                                                            <TableRow>
                                                                <TableHead>Patient</TableHead>
                                                                <TableHead>ID</TableHead>
                                                                <TableHead>Age</TableHead>
                                                                <TableHead>Department</TableHead>
                                                                <TableHead>Doctor</TableHead>
                                                                <TableHead>Started</TableHead>
                                                                <TableHead className="text-right">Action</TableHead>
                                                            </TableRow>
                                                        </TableHeader>
                                                        <TableBody>
                                                            {activeConsultations.map((consultation) => (
                                                                <TableRow key={consultation.id}>
                                                                    <TableCell className="font-medium">
                                                                        {consultation.patient_checkin.patient.first_name}{' '}
                                                                        {consultation.patient_checkin.patient.last_name}
                                                                        <Badge className="ml-2">In Progress</Badge>
                                                                    </TableCell>
                                                                    <TableCell>{consultation.patient_checkin.patient.patient_number}</TableCell>
                                                                    <TableCell>{calculateAge(consultation.patient_checkin.patient.date_of_birth)} yrs</TableCell>
                                                                    <TableCell>{consultation.patient_checkin.department?.name ?? 'Unknown'}</TableCell>
                                                                    <TableCell>{consultation.doctor?.name ?? '-'}</TableCell>
                                                                    <TableCell>{formatTime(consultation.started_at)}</TableCell>
                                                                    <TableCell className="text-right">
                                                                        <Button size="sm" onClick={() => openContinueDialog(consultation)}>
                                                                            Continue
                                                                        </Button>
                                                                    </TableCell>
                                                                </TableRow>
                                                            ))}
                                                        </TableBody>
                                                    </Table>
                                                </div>
                                            </div>
                                        )}

                                        {awaitingConsultation.length > 0 && (
                                            <div className="space-y-3">
                                                <h2 className="text-lg font-semibold">
                                                    Awaiting Consultation (
                                                    {awaitingConsultation.length})
                                                </h2>
                                                <div className="rounded-md border">
                                                    <Table>
                                                        <TableHeader>
                                                            <TableRow>
                                                                <TableHead>Patient</TableHead>
                                                                <TableHead>ID</TableHead>
                                                                <TableHead>Age</TableHead>
                                                                <TableHead>Department</TableHead>
                                                                <TableHead>Vitals</TableHead>
                                                                <TableHead>Checked In</TableHead>
                                                                <TableHead className="text-right">Action</TableHead>
                                                            </TableRow>
                                                        </TableHeader>
                                                        <TableBody>
                                                            {awaitingConsultation.map((checkin) => (
                                                                <TableRow key={checkin.id}>
                                                                    <TableCell className="font-medium">
                                                                        {checkin.patient.first_name}{' '}
                                                                        {checkin.patient.last_name}
                                                                    </TableCell>
                                                                    <TableCell>{checkin.patient.patient_number}</TableCell>
                                                                    <TableCell>{calculateAge(checkin.patient.date_of_birth)} yrs</TableCell>
                                                                    <TableCell>{checkin.department.name}</TableCell>
                                                                    <TableCell>
                                                                        {checkin.vital_signs && checkin.vital_signs.length > 0 ? (
                                                                            <Badge variant="outline" className="bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300">
                                                                                ✓ Taken
                                                                            </Badge>
                                                                        ) : (
                                                                            <Badge variant="outline" className="text-muted-foreground">
                                                                                Pending
                                                                            </Badge>
                                                                        )}
                                                                    </TableCell>
                                                                    <TableCell>{formatTime(checkin.checked_in_at)}</TableCell>
                                                                    <TableCell className="text-right">
                                                                        <Button size="sm" onClick={() => openStartDialog(checkin)}>
                                                                            Start
                                                                        </Button>
                                                                    </TableCell>
                                                                </TableRow>
                                                            ))}
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
                        {activeConsultations.length > 0 && (
                            <div className="space-y-3">
                                <h2 className="flex items-center gap-2 text-lg font-semibold">
                                    Active Consultations ({activeConsultations.length})
                                </h2>
                                <div className="rounded-md border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Patient</TableHead>
                                                <TableHead>ID</TableHead>
                                                <TableHead>Age</TableHead>
                                                <TableHead>Department</TableHead>
                                                <TableHead>Doctor</TableHead>
                                                <TableHead>Started</TableHead>
                                                <TableHead className="text-right">Action</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {activeConsultations.map((consultation) => (
                                                <TableRow key={consultation.id}>
                                                    <TableCell className="font-medium">
                                                        {consultation.patient_checkin.patient.first_name}{' '}
                                                        {consultation.patient_checkin.patient.last_name}
                                                        <Badge className="ml-2">In Progress</Badge>
                                                    </TableCell>
                                                    <TableCell>{consultation.patient_checkin.patient.patient_number}</TableCell>
                                                    <TableCell>{calculateAge(consultation.patient_checkin.patient.date_of_birth)} yrs</TableCell>
                                                    <TableCell>{consultation.patient_checkin.department?.name ?? 'Unknown'}</TableCell>
                                                    <TableCell>{consultation.doctor?.name ?? '-'}</TableCell>
                                                    <TableCell>{formatTime(consultation.started_at)}</TableCell>
                                                    <TableCell className="text-right">
                                                        <Button size="sm" onClick={() => openContinueDialog(consultation)}>
                                                            Continue
                                                        </Button>
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>
                            </div>
                        )}

                        {/* Awaiting Consultation Table */}
                        <div className="space-y-3">
                            <h2 className="flex items-center gap-2 text-lg font-semibold">
                                <Clock className="h-5 w-5 text-blue-600" />
                                Awaiting Consultation ({awaitingConsultation.length})
                            </h2>
                            {awaitingConsultation.length > 0 ? (
                                <div className="rounded-md border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Patient</TableHead>
                                                <TableHead>ID</TableHead>
                                                <TableHead>Age</TableHead>
                                                <TableHead>Department</TableHead>
                                                <TableHead>Vitals</TableHead>
                                                <TableHead>Checked In</TableHead>
                                                <TableHead className="text-right">Action</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {awaitingConsultation.map((checkin) => (
                                                <TableRow key={checkin.id}>
                                                    <TableCell className="font-medium">
                                                        {checkin.patient.first_name}{' '}
                                                        {checkin.patient.last_name}
                                                    </TableCell>
                                                    <TableCell>{checkin.patient.patient_number}</TableCell>
                                                    <TableCell>{calculateAge(checkin.patient.date_of_birth)} yrs</TableCell>
                                                    <TableCell>{checkin.department.name}</TableCell>
                                                    <TableCell>
                                                        {checkin.vital_signs && checkin.vital_signs.length > 0 ? (
                                                            <Badge variant="outline" className="bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300">
                                                                ✓ Taken
                                                            </Badge>
                                                        ) : (
                                                            <Badge variant="outline" className="text-muted-foreground">
                                                                Pending
                                                            </Badge>
                                                        )}
                                                    </TableCell>
                                                    <TableCell>{formatTime(checkin.checked_in_at)}</TableCell>
                                                    <TableCell className="text-right">
                                                        <Button size="sm" onClick={() => openStartDialog(checkin)}>
                                                            Start
                                                        </Button>
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>
                            ) : (
                                <Card>
                                    <CardContent className="py-12">
                                        <div className="text-center text-gray-500">
                                            <Clock className="mx-auto mb-3 h-12 w-12 text-gray-300" />
                                            <p className="font-medium">
                                                No patients awaiting consultation
                                            </p>
                                            <p className="mt-1 text-sm">
                                                New check-ins will appear here automatically
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
                                                {getDialogPatient()?.patient_number}
                                            </span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="font-medium text-gray-700 dark:text-gray-300">
                                                Date of Birth:
                                            </span>
                                            <span className="text-gray-900 dark:text-gray-100">
                                                {getDialogPatient()?.date_of_birth &&
                                                    formatDate(getDialogPatient()!.date_of_birth)}{' '}
                                                ({getDialogPatient()?.date_of_birth &&
                                                    calculateAge(getDialogPatient()!.date_of_birth)}{' '}
                                                years)
                                            </span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="font-medium text-gray-700 dark:text-gray-300">
                                                Phone:
                                            </span>
                                            <span className="text-gray-900 dark:text-gray-100">
                                                {getDialogPatient()?.phone_number}
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
