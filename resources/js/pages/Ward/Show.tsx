import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { MedicationAdministrationPanel } from '@/components/Ward/MedicationAdministrationPanel';
import { NursingNotesModal } from '@/components/Ward/NursingNotesModal';
import { RecordVitalsModal } from '@/components/Ward/RecordVitalsModal';
import { WardRoundModal } from '@/components/Ward/WardRoundModal';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import {
    Activity,
    AlertCircle,
    ArrowLeft,
    Bed,
    Calendar,
    Clock,
    Edit,
    FileText,
    Heart,
    Hospital,
    Pill,
    Settings,
    Stethoscope,
    Thermometer,
    User,
    UserCheck,
    Users,
} from 'lucide-react';
import { useState } from 'react';

interface Patient {
    id: number;
    first_name: string;
    last_name: string;
    date_of_birth?: string;
    gender?: string;
}

interface Doctor {
    id: number;
    name: string;
}

interface User {
    id: number;
    name: string;
}

interface Drug {
    id: number;
    name: string;
    strength?: string;
    form?: string;
}

interface Prescription {
    id: number;
    drug: Drug;
}

interface VitalSign {
    id: number;
    temperature?: number;
    blood_pressure_systolic?: number;
    blood_pressure_diastolic?: number;
    pulse_rate?: number;
    respiratory_rate?: number;
    oxygen_saturation?: number;
    recorded_at: string;
    recorded_by?: User;
}

interface MedicationAdministration {
    id: number;
    prescription: Prescription;
    scheduled_time: string;
    status: 'scheduled' | 'administered' | 'missed' | 'refused';
}

interface Bed {
    id: number;
    ward_id: number;
    bed_number: string;
    status: 'available' | 'occupied' | 'maintenance' | 'cleaning';
    type: 'standard' | 'icu' | 'isolation' | 'private';
    is_active: boolean;
}

interface Consultation {
    id: number;
    doctor: Doctor;
}

interface PatientAdmission {
    id: number;
    admission_number: string;
    patient: Patient;
    status: string;
    admitted_at: string;
    bed_id?: number;
    bed?: Bed;
    consultation?: Consultation;
    latest_vital_signs?: VitalSign[];
    pending_medications?: MedicationAdministration[];
    ward_rounds_count?: number;
    nursing_notes_count?: number;
}

interface Ward {
    id: number;
    name: string;
    code: string;
    description?: string;
    total_beds: number;
    available_beds: number;
    is_active: boolean;
    beds: Bed[];
    admissions: PatientAdmission[];
    created_at: string;
}

interface WardStats {
    total_patients: number;
    pending_meds_count: number;
    patients_needing_vitals: number;
}

interface Props {
    ward: Ward;
    stats: WardStats;
}

export default function WardShow({ ward, stats }: Props) {
    const [vitalsModalOpen, setVitalsModalOpen] = useState(false);
    const [nursingNotesModalOpen, setNursingNotesModalOpen] = useState(false);
    const [wardRoundModalOpen, setWardRoundModalOpen] = useState(false);
    const [medicationPanelOpen, setMedicationPanelOpen] = useState(false);
    const [selectedAdmission, setSelectedAdmission] =
        useState<PatientAdmission | null>(null);

    const openVitalsModal = (admission: PatientAdmission) => {
        setSelectedAdmission(admission);
        setVitalsModalOpen(true);
    };

    const closeVitalsModal = () => {
        setVitalsModalOpen(false);
        setSelectedAdmission(null);
    };

    const openNursingNotesModal = (admission: PatientAdmission) => {
        setSelectedAdmission(admission);
        setNursingNotesModalOpen(true);
    };

    const closeNursingNotesModal = () => {
        setNursingNotesModalOpen(false);
        setSelectedAdmission(null);
    };

    const openWardRoundModal = (admission: PatientAdmission) => {
        setSelectedAdmission(admission);
        setWardRoundModalOpen(true);
    };

    const closeWardRoundModal = () => {
        setWardRoundModalOpen(false);
        setSelectedAdmission(null);
    };

    const openMedicationPanel = (admission: PatientAdmission) => {
        setSelectedAdmission(admission);
        setMedicationPanelOpen(true);
    };

    const closeMedicationPanel = () => {
        setMedicationPanelOpen(false);
        setSelectedAdmission(null);
    };

    const getBedStatusColor = (status: string) => {
        const colors = {
            available:
                'bg-green-100 text-green-800 border-green-200 dark:bg-green-900 dark:text-green-200 dark:border-green-800',
            occupied:
                'bg-red-100 text-red-800 border-red-200 dark:bg-red-900 dark:text-red-200 dark:border-red-800',
            maintenance:
                'bg-yellow-100 text-yellow-800 border-yellow-200 dark:bg-yellow-900 dark:text-yellow-200 dark:border-yellow-800',
            cleaning:
                'bg-blue-100 text-blue-800 border-blue-200 dark:bg-blue-900 dark:text-blue-200 dark:border-blue-800',
        };
        return (
            colors[status as keyof typeof colors] ||
            'bg-gray-100 text-gray-800 border-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700'
        );
    };

    const getBedTypeIcon = (type: string) => {
        switch (type) {
            case 'icu':
                return <Activity className="h-4 w-4" />;
            case 'isolation':
                return <Settings className="h-4 w-4" />;
            case 'private':
                return <UserCheck className="h-4 w-4" />;
            default:
                return <Bed className="h-4 w-4" />;
        }
    };

    const getOccupancyRate = () => {
        if (ward.total_beds === 0) return 0;
        const occupied = ward.total_beds - ward.available_beds;
        return Math.round((occupied / ward.total_beds) * 100);
    };

    const formatDateTime = (dateString: string) => {
        return new Date(dateString).toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Wards', href: '/wards' },
                { title: ward.name, href: '' },
            ]}
        >
            <Head title={`${ward.name} - Ward Details`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div className="flex items-center gap-4">
                        <Link href="/wards">
                            <Button variant="ghost" size="sm">
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Back to Wards
                            </Button>
                        </Link>
                        <div>
                            <div className="flex items-center gap-3">
                                <h1 className="flex items-center gap-2 text-3xl font-bold text-gray-900 dark:text-gray-100">
                                    <Hospital className="h-8 w-8" />
                                    {ward.name}
                                </h1>
                                <Badge
                                    variant={
                                        ward.is_active ? 'default' : 'secondary'
                                    }
                                >
                                    {ward.is_active ? 'Active' : 'Inactive'}
                                </Badge>
                            </div>
                            <p className="mt-2 text-gray-600 dark:text-gray-400">
                                Code: {ward.code}
                            </p>
                            {ward.description && (
                                <p className="mt-1 text-gray-600 dark:text-gray-400">
                                    {ward.description}
                                </p>
                            )}
                        </div>
                    </div>

                    <Link href={`/wards/${ward.id}/edit`}>
                        <Button>
                            <Edit className="mr-2 h-4 w-4" />
                            Edit Ward
                        </Button>
                    </Link>
                </div>

                {/* Stats Cards */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-3 lg:grid-cols-6">
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Total Beds
                                    </p>
                                    <p className="text-3xl font-bold text-gray-900 dark:text-gray-100">
                                        {ward.total_beds}
                                    </p>
                                </div>
                                <Bed className="h-8 w-8 text-blue-600 dark:text-blue-400" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Available
                                    </p>
                                    <p className="text-3xl font-bold text-green-600 dark:text-green-400">
                                        {ward.available_beds}
                                    </p>
                                </div>
                                <Users className="h-8 w-8 text-green-600 dark:text-green-400" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Occupied
                                    </p>
                                    <p className="text-3xl font-bold text-red-600 dark:text-red-400">
                                        {ward.total_beds - ward.available_beds}
                                    </p>
                                </div>
                                <User className="h-8 w-8 text-red-600 dark:text-red-400" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Occupancy
                                    </p>
                                    <p className="text-3xl font-bold text-purple-600 dark:text-purple-400">
                                        {getOccupancyRate()}%
                                    </p>
                                </div>
                                <Activity className="h-8 w-8 text-purple-600 dark:text-purple-400" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Pending Meds
                                    </p>
                                    <p className="text-3xl font-bold text-orange-600 dark:text-orange-400">
                                        {stats.pending_meds_count}
                                    </p>
                                </div>
                                <Pill className="h-8 w-8 text-orange-600 dark:text-orange-400" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Need Vitals
                                    </p>
                                    <p className="text-3xl font-bold text-yellow-600 dark:text-yellow-400">
                                        {stats.patients_needing_vitals}
                                    </p>
                                </div>
                                <Thermometer className="h-8 w-8 text-yellow-600 dark:text-yellow-400" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Tabs defaultValue="patients" className="w-full">
                    <TabsList>
                        <TabsTrigger
                            value="patients"
                            className="flex items-center gap-2"
                        >
                            <Users className="h-4 w-4" />
                            Current Patients ({ward.admissions.length})
                        </TabsTrigger>
                        <TabsTrigger
                            value="beds"
                            className="flex items-center gap-2"
                        >
                            <Bed className="h-4 w-4" />
                            Beds
                        </TabsTrigger>
                        <TabsTrigger
                            value="medications"
                            className="flex items-center gap-2"
                        >
                            <Pill className="h-4 w-4" />
                            Medications
                            {stats.pending_meds_count > 0 && (
                                <Badge variant="destructive" className="ml-1">
                                    {stats.pending_meds_count}
                                </Badge>
                            )}
                        </TabsTrigger>
                        <TabsTrigger
                            value="vitals"
                            className="flex items-center gap-2"
                        >
                            <Heart className="h-4 w-4" />
                            Vital Signs
                        </TabsTrigger>
                    </TabsList>

                    {/* Beds Tab */}
                    <TabsContent value="beds">
                        <Card>
                            <CardHeader>
                                <CardTitle>Bed Management</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {ward.beds.length > 0 ? (
                                    <div className="grid grid-cols-2 gap-4 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8">
                                        {ward.beds
                                            .sort((a, b) =>
                                                a.bed_number.localeCompare(
                                                    b.bed_number,
                                                ),
                                            )
                                            .map((bed) => {
                                                const currentPatient =
                                                    ward.admissions.find(
                                                        (admission) =>
                                                            admission.bed_id ===
                                                            bed.id,
                                                    );
                                                return (
                                                    <div
                                                        key={bed.id}
                                                        className={`relative rounded-lg border-2 p-4 ${getBedStatusColor(bed.status)} ${!bed.is_active ? 'opacity-50' : ''}`}
                                                    >
                                                        <div className="text-center">
                                                            <div className="mb-2 flex justify-center">
                                                                {getBedTypeIcon(
                                                                    bed.type,
                                                                )}
                                                            </div>
                                                            <div className="text-sm font-semibold">
                                                                Bed{' '}
                                                                {bed.bed_number}
                                                            </div>
                                                            <div className="text-xs text-gray-600 capitalize dark:text-gray-400">
                                                                {bed.type}
                                                            </div>
                                                            <div className="mt-2">
                                                                <Badge
                                                                    variant="outline"
                                                                    className={`text-xs ${getBedStatusColor(bed.status).split(' ')[1]} ${getBedStatusColor(bed.status).split(' ')[2]}`}
                                                                >
                                                                    {bed.status}
                                                                </Badge>
                                                            </div>
                                                            {currentPatient && (
                                                                <div className="bg-opacity-50 dark:bg-opacity-50 mt-2 rounded bg-white p-2 text-xs dark:bg-gray-900">
                                                                    <div className="font-medium dark:text-gray-100">
                                                                        {
                                                                            currentPatient
                                                                                .patient
                                                                                .first_name
                                                                        }{' '}
                                                                        {
                                                                            currentPatient
                                                                                .patient
                                                                                .last_name
                                                                        }
                                                                    </div>
                                                                    <div className="text-gray-600 dark:text-gray-400">
                                                                        {
                                                                            currentPatient.admission_number
                                                                        }
                                                                    </div>
                                                                </div>
                                                            )}
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                    </div>
                                ) : (
                                    <div className="py-8 text-center text-gray-500 dark:text-gray-400">
                                        <Bed className="mx-auto mb-4 h-12 w-12 text-gray-300 dark:text-gray-600" />
                                        <p>No beds configured for this ward</p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Current Patients Tab */}
                    <TabsContent value="patients">
                        <Card>
                            <CardHeader>
                                <CardTitle>Current Patients</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {ward.admissions.length > 0 ? (
                                    <div className="space-y-4">
                                        {ward.admissions.map((admission) => {
                                            const latestVital =
                                                admission
                                                    .latest_vital_signs?.[0];
                                            const vitalsOverdue =
                                                !latestVital ||
                                                new Date(
                                                    latestVital.recorded_at,
                                                ) <
                                                    new Date(
                                                        Date.now() -
                                                            4 * 60 * 60 * 1000,
                                                    );

                                            return (
                                                <div
                                                    key={admission.id}
                                                    className="rounded-lg border p-4 dark:border-gray-700"
                                                >
                                                    <div className="flex items-start justify-between">
                                                        <div className="flex-1">
                                                            <div className="flex items-start gap-3">
                                                                <div className="flex-1">
                                                                    <h3 className="font-semibold text-gray-900 dark:text-gray-100">
                                                                        {
                                                                            admission
                                                                                .patient
                                                                                .first_name
                                                                        }{' '}
                                                                        {
                                                                            admission
                                                                                .patient
                                                                                .last_name
                                                                        }
                                                                    </h3>
                                                                    <p className="text-sm text-gray-600 dark:text-gray-400">
                                                                        Admission:{' '}
                                                                        {
                                                                            admission.admission_number
                                                                        }
                                                                    </p>
                                                                    {admission
                                                                        .consultation
                                                                        ?.doctor && (
                                                                        <div className="mt-2 flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                                                            <Stethoscope className="h-4 w-4" />
                                                                            <span>
                                                                                Dr.{' '}
                                                                                {
                                                                                    admission
                                                                                        .consultation
                                                                                        .doctor
                                                                                        .name
                                                                                }
                                                                            </span>
                                                                        </div>
                                                                    )}
                                                                </div>
                                                            </div>

                                                            {/* Patient Metrics Row */}
                                                            <div className="mt-3 flex flex-wrap gap-4">
                                                                {admission.bed && (
                                                                    <div className="flex items-center gap-2 text-sm">
                                                                        <Bed className="h-4 w-4 text-gray-500 dark:text-gray-400" />
                                                                        <span className="font-medium text-gray-900 dark:text-gray-100">
                                                                            Bed{' '}
                                                                            {
                                                                                admission
                                                                                    .bed
                                                                                    .bed_number
                                                                            }
                                                                        </span>
                                                                    </div>
                                                                )}

                                                                {admission.pending_medications &&
                                                                    admission
                                                                        .pending_medications
                                                                        .length >
                                                                        0 && (
                                                                        <div className="flex items-center gap-2 text-sm">
                                                                            <Pill className="h-4 w-4 text-orange-500 dark:text-orange-400" />
                                                                            <span className="font-medium text-orange-600 dark:text-orange-400">
                                                                                {
                                                                                    admission
                                                                                        .pending_medications
                                                                                        .length
                                                                                }{' '}
                                                                                pending
                                                                                med
                                                                                {admission
                                                                                    .pending_medications
                                                                                    .length !==
                                                                                1
                                                                                    ? 's'
                                                                                    : ''}
                                                                            </span>
                                                                        </div>
                                                                    )}

                                                                {vitalsOverdue && (
                                                                    <div className="flex items-center gap-2 text-sm">
                                                                        <AlertCircle className="h-4 w-4 text-yellow-500 dark:text-yellow-400" />
                                                                        <span className="font-medium text-yellow-600 dark:text-yellow-400">
                                                                            Vitals
                                                                            needed
                                                                        </span>
                                                                    </div>
                                                                )}

                                                                {admission.ward_rounds_count !==
                                                                    undefined && (
                                                                    <div className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                                                        <FileText className="h-4 w-4" />
                                                                        <span>
                                                                            {
                                                                                admission.ward_rounds_count
                                                                            }{' '}
                                                                            round
                                                                            {admission.ward_rounds_count !==
                                                                            1
                                                                                ? 's'
                                                                                : ''}
                                                                        </span>
                                                                    </div>
                                                                )}

                                                                {admission.nursing_notes_count !==
                                                                    undefined && (
                                                                    <div className="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                                                        <FileText className="h-4 w-4" />
                                                                        <span>
                                                                            {
                                                                                admission.nursing_notes_count
                                                                            }{' '}
                                                                            nursing
                                                                            note
                                                                            {admission.nursing_notes_count !==
                                                                            1
                                                                                ? 's'
                                                                                : ''}
                                                                        </span>
                                                                    </div>
                                                                )}
                                                            </div>

                                                            {/* Latest Vital Signs */}
                                                            {latestVital && (
                                                                <div className="mt-3 rounded-md bg-gray-50 p-3 dark:bg-gray-800">
                                                                    <div className="mb-2 flex items-center justify-between">
                                                                        <span className="text-xs font-medium text-gray-600 dark:text-gray-400">
                                                                            Latest
                                                                            Vitals
                                                                        </span>
                                                                        <span className="text-xs text-gray-500 dark:text-gray-500">
                                                                            {formatDateTime(
                                                                                latestVital.recorded_at,
                                                                            )}
                                                                        </span>
                                                                    </div>
                                                                    <div className="grid grid-cols-2 gap-2 text-sm md:grid-cols-3 lg:grid-cols-6">
                                                                        {latestVital.temperature && (
                                                                            <div>
                                                                                <span className="text-gray-600 dark:text-gray-400">
                                                                                    Temp:
                                                                                </span>{' '}
                                                                                <span className="font-medium text-gray-900 dark:text-gray-100">
                                                                                    {
                                                                                        latestVital.temperature
                                                                                    }
                                                                                    °C
                                                                                </span>
                                                                            </div>
                                                                        )}
                                                                        {latestVital.blood_pressure_systolic &&
                                                                            latestVital.blood_pressure_diastolic && (
                                                                                <div>
                                                                                    <span className="text-gray-600 dark:text-gray-400">
                                                                                        BP:
                                                                                    </span>{' '}
                                                                                    <span className="font-medium text-gray-900 dark:text-gray-100">
                                                                                        {
                                                                                            latestVital.blood_pressure_systolic
                                                                                        }

                                                                                        /
                                                                                        {
                                                                                            latestVital.blood_pressure_diastolic
                                                                                        }
                                                                                    </span>
                                                                                </div>
                                                                            )}
                                                                        {latestVital.pulse_rate && (
                                                                            <div>
                                                                                <span className="text-gray-600 dark:text-gray-400">
                                                                                    Pulse:
                                                                                </span>{' '}
                                                                                <span className="font-medium text-gray-900 dark:text-gray-100">
                                                                                    {
                                                                                        latestVital.pulse_rate
                                                                                    }{' '}
                                                                                    bpm
                                                                                </span>
                                                                            </div>
                                                                        )}
                                                                        {latestVital.respiratory_rate && (
                                                                            <div>
                                                                                <span className="text-gray-600 dark:text-gray-400">
                                                                                    RR:
                                                                                </span>{' '}
                                                                                <span className="font-medium text-gray-900 dark:text-gray-100">
                                                                                    {
                                                                                        latestVital.respiratory_rate
                                                                                    }
                                                                                    /min
                                                                                </span>
                                                                            </div>
                                                                        )}
                                                                        {latestVital.oxygen_saturation && (
                                                                            <div>
                                                                                <span className="text-gray-600 dark:text-gray-400">
                                                                                    SpO₂:
                                                                                </span>{' '}
                                                                                <span className="font-medium text-gray-900 dark:text-gray-100">
                                                                                    {
                                                                                        latestVital.oxygen_saturation
                                                                                    }

                                                                                    %
                                                                                </span>
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                </div>
                                                            )}
                                                        </div>

                                                        <div className="ml-4 flex flex-col items-end gap-2">
                                                            <Badge variant="default">
                                                                {admission.status
                                                                    .replace(
                                                                        '_',
                                                                        ' ',
                                                                    )
                                                                    .toUpperCase()}
                                                            </Badge>
                                                            <p className="text-xs text-gray-500 dark:text-gray-500">
                                                                <Calendar className="mr-1 inline h-3 w-3" />
                                                                {formatDateTime(
                                                                    admission.admitted_at,
                                                                )}
                                                            </p>
                                                            <div className="flex flex-col gap-2">
                                                                <div className="flex gap-2">
                                                                    <Button
                                                                        size="sm"
                                                                        variant="outline"
                                                                        onClick={() =>
                                                                            openMedicationPanel(
                                                                                admission,
                                                                            )
                                                                        }
                                                                    >
                                                                        <Pill className="mr-2 h-4 w-4" />
                                                                        Meds
                                                                    </Button>
                                                                    <Button
                                                                        size="sm"
                                                                        variant="outline"
                                                                        onClick={() =>
                                                                            openNursingNotesModal(
                                                                                admission,
                                                                            )
                                                                        }
                                                                    >
                                                                        <FileText className="mr-2 h-4 w-4" />
                                                                        Notes
                                                                    </Button>
                                                                </div>
                                                                <div className="flex gap-2">
                                                                    <Button
                                                                        size="sm"
                                                                        variant="outline"
                                                                        onClick={() =>
                                                                            openWardRoundModal(
                                                                                admission,
                                                                            )
                                                                        }
                                                                    >
                                                                        <Stethoscope className="mr-2 h-4 w-4" />
                                                                        Rounds
                                                                    </Button>
                                                                    <Button
                                                                        size="sm"
                                                                        onClick={() =>
                                                                            openVitalsModal(
                                                                                admission,
                                                                            )
                                                                        }
                                                                    >
                                                                        <Activity className="mr-2 h-4 w-4" />
                                                                        Vitals
                                                                    </Button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                ) : (
                                    <div className="py-8 text-center text-gray-500 dark:text-gray-400">
                                        <Users className="mx-auto mb-4 h-12 w-12 text-gray-300 dark:text-gray-600" />
                                        <p>
                                            No patients currently admitted to
                                            this ward
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Medications Tab */}
                    <TabsContent value="medications">
                        <Card>
                            <CardHeader>
                                <CardTitle>Pending Medications</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {ward.admissions.some(
                                    (a) =>
                                        a.pending_medications &&
                                        a.pending_medications.length > 0,
                                ) ? (
                                    <div className="space-y-6">
                                        {ward.admissions
                                            .filter(
                                                (a) =>
                                                    a.pending_medications &&
                                                    a.pending_medications
                                                        .length > 0,
                                            )
                                            .map((admission) => (
                                                <div
                                                    key={admission.id}
                                                    className="space-y-3"
                                                >
                                                    <div className="flex items-center gap-3 border-b pb-2 dark:border-gray-700">
                                                        <User className="h-5 w-5 text-gray-500 dark:text-gray-400" />
                                                        <div>
                                                            <h3 className="font-semibold text-gray-900 dark:text-gray-100">
                                                                {
                                                                    admission
                                                                        .patient
                                                                        .first_name
                                                                }{' '}
                                                                {
                                                                    admission
                                                                        .patient
                                                                        .last_name
                                                                }
                                                            </h3>
                                                            <p className="text-sm text-gray-600 dark:text-gray-400">
                                                                {admission.bed
                                                                    ? `Bed ${admission.bed.bed_number}`
                                                                    : 'No bed assigned'}{' '}
                                                                •{' '}
                                                                {
                                                                    admission.admission_number
                                                                }
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div className="space-y-2 pl-8">
                                                        {admission.pending_medications?.map(
                                                            (med) => {
                                                                const scheduledTime =
                                                                    new Date(
                                                                        med.scheduled_time,
                                                                    );
                                                                const isOverdue =
                                                                    scheduledTime <
                                                                    new Date();

                                                                return (
                                                                    <div
                                                                        key={
                                                                            med.id
                                                                        }
                                                                        className={`rounded-lg border p-3 ${isOverdue ? 'border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/20' : 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800'}`}
                                                                    >
                                                                        <div className="flex items-start justify-between">
                                                                            <div className="flex-1">
                                                                                <div className="flex items-center gap-2">
                                                                                    <Pill
                                                                                        className={`h-4 w-4 ${isOverdue ? 'text-red-600 dark:text-red-400' : 'text-gray-500 dark:text-gray-400'}`}
                                                                                    />
                                                                                    <span className="font-medium text-gray-900 dark:text-gray-100">
                                                                                        {
                                                                                            med
                                                                                                .prescription
                                                                                                .drug
                                                                                                .name
                                                                                        }
                                                                                    </span>
                                                                                    {med
                                                                                        .prescription
                                                                                        .drug
                                                                                        .strength && (
                                                                                        <span className="text-sm text-gray-600 dark:text-gray-400">
                                                                                            {
                                                                                                med
                                                                                                    .prescription
                                                                                                    .drug
                                                                                                    .strength
                                                                                            }
                                                                                        </span>
                                                                                    )}
                                                                                </div>
                                                                            </div>
                                                                            <div className="text-right">
                                                                                <div className="flex items-center gap-2">
                                                                                    <Clock
                                                                                        className={`h-3 w-3 ${isOverdue ? 'text-red-600 dark:text-red-400' : 'text-gray-500 dark:text-gray-400'}`}
                                                                                    />
                                                                                    <span
                                                                                        className={`text-sm font-medium ${isOverdue ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-gray-100'}`}
                                                                                    >
                                                                                        {formatDateTime(
                                                                                            med.scheduled_time,
                                                                                        )}
                                                                                    </span>
                                                                                </div>
                                                                                {isOverdue && (
                                                                                    <Badge
                                                                                        variant="destructive"
                                                                                        className="mt-1 text-xs"
                                                                                    >
                                                                                        Overdue
                                                                                    </Badge>
                                                                                )}
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                );
                                                            },
                                                        )}
                                                    </div>
                                                </div>
                                            ))}
                                    </div>
                                ) : (
                                    <div className="py-8 text-center text-gray-500 dark:text-gray-400">
                                        <Pill className="mx-auto mb-4 h-12 w-12 text-gray-300 dark:text-gray-600" />
                                        <p>
                                            No pending medications at this time
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Vital Signs Tab */}
                    <TabsContent value="vitals">
                        <Card>
                            <CardHeader>
                                <CardTitle>Vital Signs Overview</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {ward.admissions.length > 0 ? (
                                    <div className="space-y-4">
                                        {ward.admissions.map((admission) => {
                                            const latestVital =
                                                admission
                                                    .latest_vital_signs?.[0];
                                            const vitalsOverdue =
                                                !latestVital ||
                                                new Date(
                                                    latestVital.recorded_at,
                                                ) <
                                                    new Date(
                                                        Date.now() -
                                                            4 * 60 * 60 * 1000,
                                                    );

                                            return (
                                                <div
                                                    key={admission.id}
                                                    className={`rounded-lg border p-4 ${vitalsOverdue ? 'border-yellow-200 bg-yellow-50 dark:border-yellow-900 dark:bg-yellow-950/20' : 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800'}`}
                                                >
                                                    <div className="mb-3 flex items-center justify-between border-b pb-2 dark:border-gray-700">
                                                        <div className="flex items-center gap-3">
                                                            <User className="h-5 w-5 text-gray-500 dark:text-gray-400" />
                                                            <div>
                                                                <h3 className="font-semibold text-gray-900 dark:text-gray-100">
                                                                    {
                                                                        admission
                                                                            .patient
                                                                            .first_name
                                                                    }{' '}
                                                                    {
                                                                        admission
                                                                            .patient
                                                                            .last_name
                                                                    }
                                                                </h3>
                                                                <p className="text-sm text-gray-600 dark:text-gray-400">
                                                                    {admission.bed
                                                                        ? `Bed ${admission.bed.bed_number}`
                                                                        : 'No bed assigned'}
                                                                </p>
                                                            </div>
                                                        </div>
                                                        <div className="flex items-center gap-2">
                                                            {vitalsOverdue && (
                                                                <Badge
                                                                    variant="outline"
                                                                    className="border-yellow-500 text-yellow-700 dark:border-yellow-600 dark:text-yellow-400"
                                                                >
                                                                    <AlertCircle className="mr-1 h-3 w-3" />
                                                                    Vitals
                                                                    Needed
                                                                </Badge>
                                                            )}
                                                            <Button
                                                                size="sm"
                                                                onClick={() =>
                                                                    openVitalsModal(
                                                                        admission,
                                                                    )
                                                                }
                                                            >
                                                                <Activity className="mr-2 h-4 w-4" />
                                                                Record Vitals
                                                            </Button>
                                                        </div>
                                                    </div>

                                                    {latestVital ? (
                                                        <div className="space-y-3">
                                                            <div className="flex items-center justify-between text-sm">
                                                                <span className="text-gray-600 dark:text-gray-400">
                                                                    Last
                                                                    recorded:
                                                                </span>
                                                                <span className="font-medium text-gray-900 dark:text-gray-100">
                                                                    {formatDateTime(
                                                                        latestVital.recorded_at,
                                                                    )}
                                                                    {latestVital.recorded_by && (
                                                                        <span className="ml-2 text-gray-600 dark:text-gray-400">
                                                                            by{' '}
                                                                            {
                                                                                latestVital
                                                                                    .recorded_by
                                                                                    .name
                                                                            }
                                                                        </span>
                                                                    )}
                                                                </span>
                                                            </div>
                                                            <div className="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-5">
                                                                {latestVital.temperature && (
                                                                    <div className="rounded-md bg-gray-50 p-3 dark:bg-gray-900">
                                                                        <div className="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                                                                            <Thermometer className="h-3 w-3" />
                                                                            Temperature
                                                                        </div>
                                                                        <div className="mt-1 text-lg font-bold text-gray-900 dark:text-gray-100">
                                                                            {
                                                                                latestVital.temperature
                                                                            }
                                                                            °C
                                                                        </div>
                                                                    </div>
                                                                )}
                                                                {latestVital.blood_pressure_systolic &&
                                                                    latestVital.blood_pressure_diastolic && (
                                                                        <div className="rounded-md bg-gray-50 p-3 dark:bg-gray-900">
                                                                            <div className="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                                                                                <Heart className="h-3 w-3" />
                                                                                Blood
                                                                                Pressure
                                                                            </div>
                                                                            <div className="mt-1 text-lg font-bold text-gray-900 dark:text-gray-100">
                                                                                {
                                                                                    latestVital.blood_pressure_systolic
                                                                                }

                                                                                /
                                                                                {
                                                                                    latestVital.blood_pressure_diastolic
                                                                                }
                                                                            </div>
                                                                        </div>
                                                                    )}
                                                                {latestVital.pulse_rate && (
                                                                    <div className="rounded-md bg-gray-50 p-3 dark:bg-gray-900">
                                                                        <div className="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                                                                            <Activity className="h-3 w-3" />
                                                                            Pulse
                                                                        </div>
                                                                        <div className="mt-1 text-lg font-bold text-gray-900 dark:text-gray-100">
                                                                            {
                                                                                latestVital.pulse_rate
                                                                            }{' '}
                                                                            <span className="text-sm font-normal">
                                                                                bpm
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                )}
                                                                {latestVital.respiratory_rate && (
                                                                    <div className="rounded-md bg-gray-50 p-3 dark:bg-gray-900">
                                                                        <div className="text-xs text-gray-600 dark:text-gray-400">
                                                                            Respiratory
                                                                            Rate
                                                                        </div>
                                                                        <div className="mt-1 text-lg font-bold text-gray-900 dark:text-gray-100">
                                                                            {
                                                                                latestVital.respiratory_rate
                                                                            }{' '}
                                                                            <span className="text-sm font-normal">
                                                                                /min
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                )}
                                                                {latestVital.oxygen_saturation && (
                                                                    <div className="rounded-md bg-gray-50 p-3 dark:bg-gray-900">
                                                                        <div className="text-xs text-gray-600 dark:text-gray-400">
                                                                            Oxygen
                                                                            Saturation
                                                                        </div>
                                                                        <div className="mt-1 text-lg font-bold text-gray-900 dark:text-gray-100">
                                                                            {
                                                                                latestVital.oxygen_saturation
                                                                            }
                                                                            %
                                                                        </div>
                                                                    </div>
                                                                )}
                                                            </div>
                                                        </div>
                                                    ) : (
                                                        <div className="py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                                            <Thermometer className="mx-auto mb-2 h-8 w-8 text-gray-300 dark:text-gray-600" />
                                                            No vital signs
                                                            recorded yet
                                                        </div>
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>
                                ) : (
                                    <div className="py-8 text-center text-gray-500 dark:text-gray-400">
                                        <Heart className="mx-auto mb-4 h-12 w-12 text-gray-300 dark:text-gray-600" />
                                        <p>
                                            No patients currently admitted to
                                            this ward
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>

                {/* Record Vitals Modal */}
                <RecordVitalsModal
                    open={vitalsModalOpen}
                    onClose={closeVitalsModal}
                    admission={selectedAdmission}
                    onSuccess={closeVitalsModal}
                />

                {/* Nursing Notes Modal */}
                <NursingNotesModal
                    open={nursingNotesModalOpen}
                    onClose={closeNursingNotesModal}
                    admission={selectedAdmission}
                />

                {/* Ward Round Modal */}
                <WardRoundModal
                    open={wardRoundModalOpen}
                    onClose={closeWardRoundModal}
                    admission={selectedAdmission}
                />

                {/* Medication Administration Panel */}
                <MedicationAdministrationPanel
                    admission={selectedAdmission}
                    open={medicationPanelOpen}
                    onOpenChange={setMedicationPanelOpen}
                />
            </div>
        </AppLayout>
    );
}
