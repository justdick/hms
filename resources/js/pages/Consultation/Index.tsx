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
import AppLayout from '@/layouts/app-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { Building, Clock, Search, Stethoscope } from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';

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

interface ActiveConsultation {
    id: number;
    started_at: string;
    status: string;
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

interface Props {
    awaitingConsultation: PatientCheckin[];
    activeConsultations: ActiveConsultation[];
    totalAwaitingCount: number;
    totalActiveCount: number;
    search?: string;
}

export default function ConsultationIndex({
    awaitingConsultation,
    activeConsultations,
    totalAwaitingCount,
    totalActiveCount,
    search: initialSearch,
}: Props) {
    const [search, setSearch] = useState(initialSearch || '');
    const [confirmDialog, setConfirmDialog] = useState<{
        open: boolean;
        type: 'start' | 'continue';
        data?: PatientCheckin | ActiveConsultation;
    }>({
        open: false,
        type: 'start',
    });

    const { data, setData, post, processing } = useForm({
        patient_checkin_id: 0,
    });

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

    // Debounced search effect
    useEffect(() => {
        const timeoutId = setTimeout(() => {
            router.get(
                '/consultation',
                { search: search || undefined },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                },
            );
        }, 500);

        return () => clearTimeout(timeoutId);
    }, [search]);

    const handleSearch = (e: FormEvent) => {
        e.preventDefault();
        // Search already triggered by useEffect
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

    const hasResults =
        awaitingConsultation.length > 0 || activeConsultations.length > 0;

    return (
        <AppLayout
            breadcrumbs={[{ title: 'Consultation', href: '/consultation' }]}
        >
            <Head title="Consultation Dashboard" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-bold text-gray-900">
                        Consultation Dashboard
                    </h1>
                    <p className="mt-2 text-gray-600">
                        Search for patients to start or continue consultations
                    </p>
                </div>

                {/* Search Bar */}
                <Card>
                    <CardContent className="pt-6">
                        <div className="relative">
                            <Search className="absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2 text-gray-400" />
                            <Input
                                type="text"
                                placeholder="Start typing patient name, ID, or phone number..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="pl-10"
                                autoFocus
                            />
                        </div>
                    </CardContent>
                </Card>

                {/* Empty State with Counts */}
                {!initialSearch && (
                    <Card>
                        <CardContent className="py-16">
                            <div className="text-center">
                                <Stethoscope className="mx-auto mb-4 h-16 w-16 text-gray-300" />
                                <h3 className="mb-2 text-lg font-semibold text-gray-900">
                                    Search for Patients
                                </h3>
                                <p className="mb-6 text-gray-600">
                                    Type at least 2 characters to search
                                    patients
                                </p>

                                <div className="mt-8 flex justify-center gap-8">
                                    <div className="text-center">
                                        <div className="text-3xl font-bold text-blue-600">
                                            {totalAwaitingCount}
                                        </div>
                                        <div className="mt-1 text-sm text-gray-600">
                                            Awaiting Consultation
                                        </div>
                                    </div>
                                    <div className="text-center">
                                        <div className="text-3xl font-bold text-green-600">
                                            {totalActiveCount}
                                        </div>
                                        <div className="mt-1 text-sm text-gray-600">
                                            Active Consultations
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Less than 2 characters message */}
                {initialSearch && initialSearch.length < 2 && (
                    <Card>
                        <CardContent className="py-16">
                            <div className="text-center text-gray-500">
                                <Search className="mx-auto mb-4 h-16 w-16 text-gray-300" />
                                <h3 className="mb-2 text-lg font-semibold text-gray-700">
                                    Keep Typing...
                                </h3>
                                <p>
                                    Please enter at least 2 characters to search
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Search Results */}
                {initialSearch && !hasResults && (
                    <Card>
                        <CardContent className="py-16">
                            <div className="text-center text-gray-500">
                                <Search className="mx-auto mb-4 h-16 w-16 text-gray-300" />
                                <h3 className="mb-2 text-lg font-semibold text-gray-700">
                                    No Results Found
                                </h3>
                                <p>
                                    No patients found matching "{initialSearch}"
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Results - Awaiting Consultation */}
                {awaitingConsultation.length > 0 && (
                    <div className="space-y-4">
                        <h2 className="text-xl font-semibold text-gray-900">
                            Awaiting Consultation ({awaitingConsultation.length}
                            )
                        </h2>
                        <div className="space-y-3">
                            {awaitingConsultation.map((checkin) => (
                                <Card
                                    key={checkin.id}
                                    className="transition-shadow hover:shadow-md"
                                >
                                    <CardContent className="p-6">
                                        <div className="mb-4 flex items-start justify-between">
                                            <div className="flex-1">
                                                <div className="mb-2 flex items-center gap-3">
                                                    <h3 className="text-lg font-semibold text-gray-900">
                                                        {
                                                            checkin.patient
                                                                .first_name
                                                        }{' '}
                                                        {
                                                            checkin.patient
                                                                .last_name
                                                        }
                                                    </h3>
                                                    <Badge variant="secondary">
                                                        Awaiting
                                                    </Badge>
                                                </div>
                                                <div className="grid grid-cols-2 gap-x-6 gap-y-2 text-sm text-gray-600">
                                                    <div>
                                                        <span className="font-medium">
                                                            Patient ID:
                                                        </span>{' '}
                                                        {
                                                            checkin.patient
                                                                .patient_number
                                                        }
                                                    </div>
                                                    <div>
                                                        <span className="font-medium">
                                                            Age:
                                                        </span>{' '}
                                                        {calculateAge(
                                                            checkin.patient
                                                                .date_of_birth,
                                                        )}{' '}
                                                        years
                                                    </div>
                                                    <div>
                                                        <span className="font-medium">
                                                            DOB:
                                                        </span>{' '}
                                                        {formatDate(
                                                            checkin.patient
                                                                .date_of_birth,
                                                        )}
                                                    </div>
                                                    <div>
                                                        <span className="font-medium">
                                                            Phone:
                                                        </span>{' '}
                                                        {
                                                            checkin.patient
                                                                .phone_number
                                                        }
                                                    </div>
                                                    <div className="flex items-center gap-1">
                                                        <Building className="h-4 w-4" />
                                                        {
                                                            checkin.department
                                                                .name
                                                        }
                                                    </div>
                                                    <div className="flex items-center gap-1">
                                                        <Clock className="h-4 w-4" />
                                                        Checked in:{' '}
                                                        {formatTime(
                                                            checkin.checked_in_at,
                                                        )}
                                                    </div>
                                                </div>
                                                {checkin.vital_signs &&
                                                    checkin.vital_signs.length >
                                                        0 && (
                                                        <div className="mt-3 inline-flex items-center gap-2 rounded-full bg-green-50 px-3 py-1 text-xs font-medium text-green-700">
                                                            ✓ Vitals taken
                                                        </div>
                                                    )}
                                            </div>
                                            <Button
                                                onClick={() =>
                                                    openStartDialog(checkin)
                                                }
                                                size="lg"
                                            >
                                                Start Consultation
                                            </Button>
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    </div>
                )}

                {/* Results - Active Consultations */}
                {activeConsultations.length > 0 && (
                    <div className="space-y-4">
                        <h2 className="text-xl font-semibold text-gray-900">
                            Active Consultations ({activeConsultations.length})
                        </h2>
                        <div className="space-y-3">
                            {activeConsultations.map((consultation) => (
                                <Card
                                    key={consultation.id}
                                    className="transition-shadow hover:shadow-md"
                                >
                                    <CardContent className="p-6">
                                        <div className="mb-4 flex items-start justify-between">
                                            <div className="flex-1">
                                                <div className="mb-2 flex items-center gap-3">
                                                    <h3 className="text-lg font-semibold text-gray-900">
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
                                                    </h3>
                                                    <Badge>In Progress</Badge>
                                                </div>
                                                <div className="grid grid-cols-2 gap-x-6 gap-y-2 text-sm text-gray-600">
                                                    <div>
                                                        <span className="font-medium">
                                                            Patient ID:
                                                        </span>{' '}
                                                        {
                                                            consultation
                                                                .patient_checkin
                                                                .patient
                                                                .patient_number
                                                        }
                                                    </div>
                                                    <div>
                                                        <span className="font-medium">
                                                            Age:
                                                        </span>{' '}
                                                        {calculateAge(
                                                            consultation
                                                                .patient_checkin
                                                                .patient
                                                                .date_of_birth,
                                                        )}{' '}
                                                        years
                                                    </div>
                                                    <div>
                                                        <span className="font-medium">
                                                            DOB:
                                                        </span>{' '}
                                                        {formatDate(
                                                            consultation
                                                                .patient_checkin
                                                                .patient
                                                                .date_of_birth,
                                                        )}
                                                    </div>
                                                    <div>
                                                        <span className="font-medium">
                                                            Phone:
                                                        </span>{' '}
                                                        {
                                                            consultation
                                                                .patient_checkin
                                                                .patient
                                                                .phone_number
                                                        }
                                                    </div>
                                                    <div className="flex items-center gap-1">
                                                        <Building className="h-4 w-4" />
                                                        {consultation
                                                            .patient_checkin
                                                            .department?.name ??
                                                            'Unknown Department'}
                                                    </div>
                                                    <div className="flex items-center gap-1">
                                                        <Clock className="h-4 w-4" />
                                                        Started:{' '}
                                                        {formatTime(
                                                            consultation.started_at,
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                            <Button
                                                onClick={() =>
                                                    openContinueDialog(
                                                        consultation,
                                                    )
                                                }
                                                size="lg"
                                                variant="default"
                                            >
                                                Continue Consultation
                                            </Button>
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    </div>
                )}
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
                                <p className="text-base text-gray-700">
                                    Please verify patient details before
                                    proceeding:
                                </p>
                                {getDialogPatient() && (
                                    <div className="space-y-2 rounded-lg bg-gray-50 p-4 text-sm">
                                        <div className="flex justify-between">
                                            <span className="font-medium text-gray-700">
                                                Patient Name:
                                            </span>
                                            <span className="font-semibold text-gray-900">
                                                {getDialogPatient()?.first_name}{' '}
                                                {getDialogPatient()?.last_name}
                                            </span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="font-medium text-gray-700">
                                                Patient ID:
                                            </span>
                                            <span className="text-gray-900">
                                                {
                                                    getDialogPatient()
                                                        ?.patient_number
                                                }
                                            </span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="font-medium text-gray-700">
                                                Date of Birth:
                                            </span>
                                            <span className="text-gray-900">
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
                                            <span className="font-medium text-gray-700">
                                                Phone:
                                            </span>
                                            <span className="text-gray-900">
                                                {
                                                    getDialogPatient()
                                                        ?.phone_number
                                                }
                                            </span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="font-medium text-gray-700">
                                                Department:
                                            </span>
                                            <span className="text-gray-900">
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
                        <AlertDialogAction
                            onClick={handleConfirm}
                            disabled={processing}
                        >
                            {processing
                                ? 'Please wait...'
                                : 'Confirm & Proceed'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
