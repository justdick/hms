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
import admissions from '@/routes/admissions';
import {
    Activity,
    AlertCircle,
    ArrowLeft,
    Bed,
    Calendar,
    ClipboardList,
    FileText,
    Heart,
    Pill,
    Stethoscope,
    Thermometer,
    User,
} from 'lucide-react';
import { useState } from 'react';

interface Patient {
    id: number;
    patient_number: string;
    first_name: string;
    last_name: string;
    date_of_birth?: string;
    gender?: string;
    phone_number?: string;
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
    dosage?: string;
    frequency?: string;
    route?: string;
}

interface VitalSign {
    id: number;
    temperature?: number;
    blood_pressure_systolic?: number;
    blood_pressure_diastolic?: number;
    pulse_rate?: number;
    respiratory_rate?: number;
    oxygen_saturation?: number;
    weight?: number;
    height?: number;
    recorded_at: string;
    recorded_by?: User;
}

interface MedicationAdministration {
    id: number;
    prescription: Prescription;
    scheduled_time: string;
    administered_time?: string;
    status: 'scheduled' | 'administered' | 'missed' | 'refused';
    administered_by?: User;
    notes?: string;
}

interface NursingNote {
    id: number;
    note: string;
    note_type?: string;
    created_at: string;
    created_by?: User;
}

interface WardRound {
    id: number;
    notes?: string;
    findings?: string;
    plan?: string;
    created_at: string;
    doctor?: Doctor;
}

interface Bed {
    id: number;
    ward_id: number;
    bed_number: string;
    status: string;
    type: string;
}

interface Ward {
    id: number;
    name: string;
    code: string;
}

interface Consultation {
    id: number;
    doctor: Doctor;
    chief_complaint?: string;
    diagnosis?: string;
}

interface PatientAdmission {
    id: number;
    admission_number: string;
    patient: Patient;
    status: string;
    admitted_at: string;
    discharged_at?: string;
    bed_id?: number;
    bed?: Bed;
    ward?: Ward;
    consultation?: Consultation;
    vital_signs?: VitalSign[];
    medication_administrations?: MedicationAdministration[];
    nursing_notes?: NursingNote[];
    ward_rounds?: WardRound[];
}

interface Props {
    admission: PatientAdmission;
}

export default function WardPatientShow({ admission }: Props) {
    const [vitalsModalOpen, setVitalsModalOpen] = useState(false);
    const [nursingNotesModalOpen, setNursingNotesModalOpen] = useState(false);
    const [wardRoundModalOpen, setWardRoundModalOpen] = useState(false);
    const [medicationPanelOpen, setMedicationPanelOpen] = useState(false);

    const formatDateTime = (dateString: string) => {
        return new Date(dateString).toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    };

    const calculateAge = (dob?: string) => {
        if (!dob) return null;
        const birthDate = new Date(dob);
        const today = new Date();
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

    const latestVital = admission.vital_signs?.[0];
    const pendingMeds = admission.medication_administrations?.filter(
        (med) => med.status === 'scheduled',
    );

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Wards', href: '/wards' },
                {
                    title: admission.ward?.name || 'Ward',
                    href: `/wards/${admission.ward?.id}`,
                },
                {
                    title: `${admission.patient.first_name} ${admission.patient.last_name}`,
                    href: '',
                },
            ]}
        >
            <Head
                title={`${admission.patient.first_name} ${admission.patient.last_name} - Ward Patient`}
            />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div className="flex items-center gap-4">
                        <Link href={`/wards/${admission.ward?.id}`}>
                            <Button variant="ghost" size="sm">
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Back to Ward
                            </Button>
                        </Link>
                        <div>
                            <div className="flex items-center gap-3">
                                <h1 className="flex items-center gap-2 text-3xl font-bold text-gray-900 dark:text-gray-100">
                                    <User className="h-8 w-8" />
                                    {admission.patient.first_name}{' '}
                                    {admission.patient.last_name}
                                </h1>
                                <Badge
                                    variant={
                                        admission.status === 'admitted'
                                            ? 'default'
                                            : 'secondary'
                                    }
                                >
                                    {admission.status.replace('_', ' ')}
                                </Badge>
                            </div>
                            <p className="mt-2 text-gray-600 dark:text-gray-400">
                                Admission: {admission.admission_number}
                            </p>
                        </div>
                    </div>

                    {admission.consultation && (
                        <Link href={`/consultation/${admission.consultation.id}`}>
                            <Button>
                                <FileText className="mr-2 h-4 w-4" />
                                View Consultation
                            </Button>
                        </Link>
                    )}
                </div>

                {/* Patient Info Cards */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardContent className="p-6">
                            <div className="space-y-2">
                                <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                    Patient Information
                                </p>
                                <div className="space-y-1 text-sm">
                                    {admission.patient.date_of_birth && (
                                        <p className="text-gray-900 dark:text-gray-100">
                                            Age: {calculateAge(admission.patient.date_of_birth)} years
                                        </p>
                                    )}
                                    {admission.patient.gender && (
                                        <p className="text-gray-900 dark:text-gray-100">
                                            Gender: {admission.patient.gender}
                                        </p>
                                    )}
                                    {admission.patient.phone_number && (
                                        <p className="text-gray-900 dark:text-gray-100">
                                            Phone: {admission.patient.phone_number}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="space-y-2">
                                <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                    Ward & Bed
                                </p>
                                <div className="space-y-1">
                                    <div className="flex items-center gap-2 text-sm text-gray-900 dark:text-gray-100">
                                        <Bed className="h-4 w-4" />
                                        {admission.bed ? (
                                            <span>
                                                Bed {admission.bed.bed_number}
                                            </span>
                                        ) : (
                                            <span className="text-gray-500 dark:text-gray-400">
                                                No bed assigned
                                            </span>
                                        )}
                                    </div>
                                    {admission.ward && (
                                        <p className="text-sm text-gray-900 dark:text-gray-100">
                                            Ward: {admission.ward.name} (
                                            {admission.ward.code})
                                        </p>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="space-y-2">
                                <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                    Attending Physician
                                </p>
                                {admission.consultation?.doctor ? (
                                    <div className="flex items-center gap-2 text-sm text-gray-900 dark:text-gray-100">
                                        <Stethoscope className="h-4 w-4" />
                                        <span>
                                            Dr. {admission.consultation.doctor.name}
                                        </span>
                                    </div>
                                ) : (
                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                        Not assigned
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="space-y-2">
                                <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                    Admission Date
                                </p>
                                <div className="flex items-center gap-2 text-sm text-gray-900 dark:text-gray-100">
                                    <Calendar className="h-4 w-4" />
                                    {formatDateTime(admission.admitted_at)}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Alert Badges */}
                {(pendingMeds && pendingMeds.length > 0) ||
                    (!latestVital ||
                        new Date(latestVital.recorded_at) <
                            new Date(Date.now() - 4 * 60 * 60 * 1000)) ? (
                    <div className="flex gap-2">
                        {pendingMeds && pendingMeds.length > 0 && (
                            <Badge
                                variant="outline"
                                className="border-orange-500 text-orange-700 dark:border-orange-600 dark:text-orange-400"
                            >
                                <Pill className="mr-2 h-3 w-3" />
                                {pendingMeds.length} Pending Medication
                                {pendingMeds.length !== 1 ? 's' : ''}
                            </Badge>
                        )}
                        {(!latestVital ||
                            new Date(latestVital.recorded_at) <
                                new Date(Date.now() - 4 * 60 * 60 * 1000)) && (
                            <Badge
                                variant="outline"
                                className="border-yellow-500 text-yellow-700 dark:border-yellow-600 dark:text-yellow-400"
                            >
                                <AlertCircle className="mr-2 h-3 w-3" />
                                Vitals Overdue
                            </Badge>
                        )}
                    </div>
                ) : null}

                {/* Tabbed Content */}
                <Tabs defaultValue="overview" className="w-full">
                    <TabsList>
                        <TabsTrigger
                            value="overview"
                            className="flex items-center gap-2"
                        >
                            <ClipboardList className="h-4 w-4" />
                            Overview
                        </TabsTrigger>
                        <TabsTrigger
                            value="vitals"
                            className="flex items-center gap-2"
                        >
                            <Heart className="h-4 w-4" />
                            Vital Signs
                        </TabsTrigger>
                        <TabsTrigger
                            value="medications"
                            className="flex items-center gap-2"
                        >
                            <Pill className="h-4 w-4" />
                            Medications
                            {pendingMeds && pendingMeds.length > 0 && (
                                <Badge variant="destructive" className="ml-1">
                                    {pendingMeds.length}
                                </Badge>
                            )}
                        </TabsTrigger>
                        <TabsTrigger
                            value="notes"
                            className="flex items-center gap-2"
                        >
                            <FileText className="h-4 w-4" />
                            Nursing Notes
                        </TabsTrigger>
                        <TabsTrigger
                            value="rounds"
                            className="flex items-center gap-2"
                        >
                            <Stethoscope className="h-4 w-4" />
                            Ward Rounds
                        </TabsTrigger>
                    </TabsList>

                    {/* Overview Tab */}
                    <TabsContent value="overview">
                        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                            {/* Latest Vitals */}
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between">
                                    <CardTitle>Latest Vital Signs</CardTitle>
                                    <Button
                                        size="sm"
                                        onClick={() => setVitalsModalOpen(true)}
                                    >
                                        <Activity className="mr-2 h-4 w-4" />
                                        Record Vitals
                                    </Button>
                                </CardHeader>
                                <CardContent>
                                    {latestVital ? (
                                        <div className="space-y-3">
                                            <p className="text-sm text-gray-600 dark:text-gray-400">
                                                Recorded:{' '}
                                                {formatDateTime(
                                                    latestVital.recorded_at,
                                                )}
                                            </p>
                                            <div className="grid grid-cols-2 gap-3">
                                                {latestVital.temperature && (
                                                    <div className="rounded-md border p-3 dark:border-gray-700">
                                                        <div className="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                                                            <Thermometer className="h-3 w-3" />
                                                            Temperature
                                                        </div>
                                                        <div className="mt-1 text-lg font-bold text-gray-900 dark:text-gray-100">
                                                            {latestVital.temperature}°C
                                                        </div>
                                                    </div>
                                                )}
                                                {latestVital.blood_pressure_systolic &&
                                                    latestVital.blood_pressure_diastolic && (
                                                        <div className="rounded-md border p-3 dark:border-gray-700">
                                                            <div className="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                                                                <Heart className="h-3 w-3" />
                                                                Blood Pressure
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
                                                    <div className="rounded-md border p-3 dark:border-gray-700">
                                                        <div className="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                                                            <Activity className="h-3 w-3" />
                                                            Pulse
                                                        </div>
                                                        <div className="mt-1 text-lg font-bold text-gray-900 dark:text-gray-100">
                                                            {latestVital.pulse_rate} bpm
                                                        </div>
                                                    </div>
                                                )}
                                                {latestVital.oxygen_saturation && (
                                                    <div className="rounded-md border p-3 dark:border-gray-700">
                                                        <div className="text-xs text-gray-600 dark:text-gray-400">
                                                            SpO₂
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
                                        <div className="py-8 text-center">
                                            <Thermometer className="mx-auto mb-2 h-8 w-8 text-gray-300 dark:text-gray-600" />
                                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                                No vital signs recorded yet
                                            </p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Pending Medications */}
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between">
                                    <CardTitle>Pending Medications</CardTitle>
                                    <Button
                                        size="sm"
                                        onClick={() =>
                                            setMedicationPanelOpen(true)
                                        }
                                    >
                                        <Pill className="mr-2 h-4 w-4" />
                                        Administer
                                    </Button>
                                </CardHeader>
                                <CardContent>
                                    {pendingMeds && pendingMeds.length > 0 ? (
                                        <div className="space-y-2">
                                            {pendingMeds.map((med) => (
                                                <div
                                                    key={med.id}
                                                    className="flex items-start justify-between rounded-md border p-3 dark:border-gray-700"
                                                >
                                                    <div>
                                                        <p className="font-medium text-gray-900 dark:text-gray-100">
                                                            {med.prescription.drug.name}
                                                        </p>
                                                        {med.prescription
                                                            .dosage && (
                                                            <p className="text-sm text-gray-600 dark:text-gray-400">
                                                                {
                                                                    med
                                                                        .prescription
                                                                        .dosage
                                                                }
                                                            </p>
                                                        )}
                                                    </div>
                                                    <p className="text-sm text-gray-600 dark:text-gray-400">
                                                        {formatDateTime(
                                                            med.scheduled_time,
                                                        )}
                                                    </p>
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="py-8 text-center">
                                            <Pill className="mx-auto mb-2 h-8 w-8 text-gray-300 dark:text-gray-600" />
                                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                                No pending medications
                                            </p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Recent Notes */}
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between">
                                    <CardTitle>Recent Nursing Notes</CardTitle>
                                    <Button
                                        size="sm"
                                        onClick={() =>
                                            setNursingNotesModalOpen(true)
                                        }
                                    >
                                        <FileText className="mr-2 h-4 w-4" />
                                        Add Note
                                    </Button>
                                </CardHeader>
                                <CardContent>
                                    {admission.nursing_notes &&
                                    admission.nursing_notes.length > 0 ? (
                                        <div className="space-y-3">
                                            {admission.nursing_notes
                                                .slice(0, 3)
                                                .map((note) => (
                                                    <div
                                                        key={note.id}
                                                        className="border-l-2 border-blue-500 pl-3 dark:border-blue-400"
                                                    >
                                                        <p className="text-sm text-gray-900 dark:text-gray-100">
                                                            {note.note}
                                                        </p>
                                                        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                            {formatDateTime(
                                                                note.created_at,
                                                            )}
                                                            {note.created_by &&
                                                                ` by ${note.created_by.name}`}
                                                        </p>
                                                    </div>
                                                ))}
                                        </div>
                                    ) : (
                                        <div className="py-8 text-center">
                                            <FileText className="mx-auto mb-2 h-8 w-8 text-gray-300 dark:text-gray-600" />
                                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                                No nursing notes yet
                                            </p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Recent Ward Rounds */}
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between">
                                    <CardTitle>Recent Ward Rounds</CardTitle>
                                    <Link href={admissions.wardRounds.create.url(admission)}>
                                        <Button size="sm">
                                            <Stethoscope className="mr-2 h-4 w-4" />
                                            Start Ward Round
                                        </Button>
                                    </Link>
                                </CardHeader>
                                <CardContent>
                                    {admission.ward_rounds &&
                                    admission.ward_rounds.length > 0 ? (
                                        <div className="space-y-3">
                                            {admission.ward_rounds
                                                .slice(0, 3)
                                                .map((round) => (
                                                    <div
                                                        key={round.id}
                                                        className="rounded-md border p-3 dark:border-gray-700"
                                                    >
                                                        <div className="mb-2 flex items-center justify-between">
                                                            {round.doctor && (
                                                                <p className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                                    Dr.{' '}
                                                                    {
                                                                        round
                                                                            .doctor
                                                                            .name
                                                                    }
                                                                </p>
                                                            )}
                                                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                                                {formatDateTime(
                                                                    round.created_at,
                                                                )}
                                                            </p>
                                                        </div>
                                                        {round.findings && (
                                                            <p className="text-sm text-gray-700 dark:text-gray-300">
                                                                {round.findings}
                                                            </p>
                                                        )}
                                                    </div>
                                                ))}
                                        </div>
                                    ) : (
                                        <div className="py-8 text-center">
                                            <Stethoscope className="mx-auto mb-2 h-8 w-8 text-gray-300 dark:text-gray-600" />
                                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                                No ward rounds yet
                                            </p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </div>
                    </TabsContent>

                    {/* Vital Signs Tab */}
                    <TabsContent value="vitals">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <CardTitle>Vital Signs History</CardTitle>
                                <Button onClick={() => setVitalsModalOpen(true)}>
                                    <Activity className="mr-2 h-4 w-4" />
                                    Record Vitals
                                </Button>
                            </CardHeader>
                            <CardContent>
                                {admission.vital_signs &&
                                admission.vital_signs.length > 0 ? (
                                    <div className="space-y-4">
                                        {admission.vital_signs.map((vital) => (
                                            <div
                                                key={vital.id}
                                                className="rounded-lg border p-4 dark:border-gray-700"
                                            >
                                                <div className="mb-3 flex items-center justify-between border-b pb-2 dark:border-gray-700">
                                                    <p className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                        {formatDateTime(
                                                            vital.recorded_at,
                                                        )}
                                                    </p>
                                                    {vital.recorded_by && (
                                                        <p className="text-sm text-gray-600 dark:text-gray-400">
                                                            by{' '}
                                                            {vital.recorded_by.name}
                                                        </p>
                                                    )}
                                                </div>
                                                <div className="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-5">
                                                    {vital.temperature && (
                                                        <div className="rounded-md bg-gray-50 p-3 dark:bg-gray-800">
                                                            <div className="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                                                                <Thermometer className="h-3 w-3" />
                                                                Temperature
                                                            </div>
                                                            <div className="mt-1 text-lg font-bold text-gray-900 dark:text-gray-100">
                                                                {vital.temperature}°C
                                                            </div>
                                                        </div>
                                                    )}
                                                    {vital.blood_pressure_systolic &&
                                                        vital.blood_pressure_diastolic && (
                                                            <div className="rounded-md bg-gray-50 p-3 dark:bg-gray-800">
                                                                <div className="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                                                                    <Heart className="h-3 w-3" />
                                                                    Blood Pressure
                                                                </div>
                                                                <div className="mt-1 text-lg font-bold text-gray-900 dark:text-gray-100">
                                                                    {
                                                                        vital.blood_pressure_systolic
                                                                    }
                                                                    /
                                                                    {
                                                                        vital.blood_pressure_diastolic
                                                                    }
                                                                </div>
                                                            </div>
                                                        )}
                                                    {vital.pulse_rate && (
                                                        <div className="rounded-md bg-gray-50 p-3 dark:bg-gray-800">
                                                            <div className="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-400">
                                                                <Activity className="h-3 w-3" />
                                                                Pulse
                                                            </div>
                                                            <div className="mt-1 text-lg font-bold text-gray-900 dark:text-gray-100">
                                                                {vital.pulse_rate} bpm
                                                            </div>
                                                        </div>
                                                    )}
                                                    {vital.respiratory_rate && (
                                                        <div className="rounded-md bg-gray-50 p-3 dark:bg-gray-800">
                                                            <div className="text-xs text-gray-600 dark:text-gray-400">
                                                                Respiratory Rate
                                                            </div>
                                                            <div className="mt-1 text-lg font-bold text-gray-900 dark:text-gray-100">
                                                                {
                                                                    vital.respiratory_rate
                                                                }{' '}
                                                                /min
                                                            </div>
                                                        </div>
                                                    )}
                                                    {vital.oxygen_saturation && (
                                                        <div className="rounded-md bg-gray-50 p-3 dark:bg-gray-800">
                                                            <div className="text-xs text-gray-600 dark:text-gray-400">
                                                                SpO₂
                                                            </div>
                                                            <div className="mt-1 text-lg font-bold text-gray-900 dark:text-gray-100">
                                                                {vital.oxygen_saturation}%
                                                            </div>
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <div className="py-12 text-center">
                                        <Thermometer className="mx-auto mb-4 h-12 w-12 text-gray-300 dark:text-gray-600" />
                                        <p className="text-gray-500 dark:text-gray-400">
                                            No vital signs recorded yet
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Medications Tab */}
                    <TabsContent value="medications">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <CardTitle>Medication Administration</CardTitle>
                                <Button
                                    onClick={() => setMedicationPanelOpen(true)}
                                >
                                    <Pill className="mr-2 h-4 w-4" />
                                    Administer Medication
                                </Button>
                            </CardHeader>
                            <CardContent>
                                {admission.medication_administrations &&
                                admission.medication_administrations.length >
                                    0 ? (
                                    <div className="space-y-4">
                                        {admission.medication_administrations.map(
                                            (med) => (
                                                <div
                                                    key={med.id}
                                                    className={`rounded-lg border p-4 ${
                                                        med.status === 'scheduled'
                                                            ? 'border-orange-200 bg-orange-50 dark:border-orange-900 dark:bg-orange-950/20'
                                                            : 'dark:border-gray-700'
                                                    }`}
                                                >
                                                    <div className="flex items-start justify-between">
                                                        <div className="flex-1">
                                                            <div className="flex items-center gap-2">
                                                                <Pill className="h-4 w-4 text-gray-500 dark:text-gray-400" />
                                                                <span className="font-medium text-gray-900 dark:text-gray-100">
                                                                    {
                                                                        med
                                                                            .prescription
                                                                            .drug
                                                                            .name
                                                                    }
                                                                </span>
                                                                {med.prescription
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
                                                            {med.prescription
                                                                .dosage && (
                                                                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                                                    Dosage:{' '}
                                                                    {
                                                                        med
                                                                            .prescription
                                                                            .dosage
                                                                    }
                                                                </p>
                                                            )}
                                                        </div>
                                                        <div className="text-right">
                                                            <Badge
                                                                variant={
                                                                    med.status ===
                                                                    'administered'
                                                                        ? 'default'
                                                                        : med.status ===
                                                                            'scheduled'
                                                                          ? 'outline'
                                                                          : 'secondary'
                                                                }
                                                            >
                                                                {med.status}
                                                            </Badge>
                                                            <p className="mt-2 text-xs text-gray-600 dark:text-gray-400">
                                                                Scheduled:{' '}
                                                                {formatDateTime(
                                                                    med.scheduled_time,
                                                                )}
                                                            </p>
                                                            {med.administered_time && (
                                                                <p className="text-xs text-gray-600 dark:text-gray-400">
                                                                    Given:{' '}
                                                                    {formatDateTime(
                                                                        med.administered_time,
                                                                    )}
                                                                </p>
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>
                                            ),
                                        )}
                                    </div>
                                ) : (
                                    <div className="py-12 text-center">
                                        <Pill className="mx-auto mb-4 h-12 w-12 text-gray-300 dark:text-gray-600" />
                                        <p className="text-gray-500 dark:text-gray-400">
                                            No medication records
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Nursing Notes Tab */}
                    <TabsContent value="notes">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <CardTitle>Nursing Notes</CardTitle>
                                <Button
                                    onClick={() => setNursingNotesModalOpen(true)}
                                >
                                    <FileText className="mr-2 h-4 w-4" />
                                    Add Note
                                </Button>
                            </CardHeader>
                            <CardContent>
                                {admission.nursing_notes &&
                                admission.nursing_notes.length > 0 ? (
                                    <div className="space-y-3">
                                        {admission.nursing_notes.map((note) => (
                                            <div
                                                key={note.id}
                                                className="rounded-lg border p-4 dark:border-gray-700"
                                            >
                                                <div className="mb-2 flex items-start justify-between">
                                                    <div className="flex-1">
                                                        {note.note_type && (
                                                            <Badge
                                                                variant="outline"
                                                                className="mb-2"
                                                            >
                                                                {note.note_type}
                                                            </Badge>
                                                        )}
                                                        <p className="text-sm text-gray-900 dark:text-gray-100">
                                                            {note.note}
                                                        </p>
                                                    </div>
                                                </div>
                                                <div className="mt-3 flex items-center justify-between border-t pt-2 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                                    <span>
                                                        {formatDateTime(
                                                            note.created_at,
                                                        )}
                                                    </span>
                                                    {note.created_by && (
                                                        <span>
                                                            by {note.created_by.name}
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <div className="py-12 text-center">
                                        <FileText className="mx-auto mb-4 h-12 w-12 text-gray-300 dark:text-gray-600" />
                                        <p className="text-gray-500 dark:text-gray-400">
                                            No nursing notes yet
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Ward Rounds Tab */}
                    <TabsContent value="rounds">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <CardTitle>Ward Rounds</CardTitle>
                                <Link href={admissions.wardRounds.create.url(admission)}>
                                    <Button>
                                        <Stethoscope className="mr-2 h-4 w-4" />
                                        Start Ward Round
                                    </Button>
                                </Link>
                            </CardHeader>
                            <CardContent>
                                {admission.ward_rounds &&
                                admission.ward_rounds.length > 0 ? (
                                    <div className="space-y-4">
                                        {admission.ward_rounds.map((round) => (
                                            <div
                                                key={round.id}
                                                className="rounded-lg border p-4 dark:border-gray-700"
                                            >
                                                <div className="mb-3 flex items-center justify-between border-b pb-2 dark:border-gray-700">
                                                    <div>
                                                        {round.doctor && (
                                                            <p className="font-medium text-gray-900 dark:text-gray-100">
                                                                Dr. {round.doctor.name}
                                                            </p>
                                                        )}
                                                        <p className="text-xs text-gray-500 dark:text-gray-400">
                                                            {formatDateTime(
                                                                round.created_at,
                                                            )}
                                                        </p>
                                                    </div>
                                                </div>
                                                {round.findings && (
                                                    <div className="mb-3">
                                                        <p className="mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                                            Findings:
                                                        </p>
                                                        <p className="text-sm text-gray-900 dark:text-gray-100">
                                                            {round.findings}
                                                        </p>
                                                    </div>
                                                )}
                                                {round.plan && (
                                                    <div className="mb-3">
                                                        <p className="mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                                            Plan:
                                                        </p>
                                                        <p className="text-sm text-gray-900 dark:text-gray-100">
                                                            {round.plan}
                                                        </p>
                                                    </div>
                                                )}
                                                {round.notes && (
                                                    <div>
                                                        <p className="mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                                            Notes:
                                                        </p>
                                                        <p className="text-sm text-gray-900 dark:text-gray-100">
                                                            {round.notes}
                                                        </p>
                                                    </div>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <div className="py-12 text-center">
                                        <Stethoscope className="mx-auto mb-4 h-12 w-12 text-gray-300 dark:text-gray-600" />
                                        <p className="text-gray-500 dark:text-gray-400">
                                            No ward rounds yet
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>

                {/* Modals */}
                <RecordVitalsModal
                    open={vitalsModalOpen}
                    onClose={() => setVitalsModalOpen(false)}
                    admission={admission}
                    onSuccess={() => setVitalsModalOpen(false)}
                />

                <NursingNotesModal
                    open={nursingNotesModalOpen}
                    onClose={() => setNursingNotesModalOpen(false)}
                    admission={admission}
                />

                <WardRoundModal
                    open={wardRoundModalOpen}
                    onClose={() => setWardRoundModalOpen(false)}
                    admission={admission}
                />

                <MedicationAdministrationPanel
                    admission={admission}
                    open={medicationPanelOpen}
                    onOpenChange={setMedicationPanelOpen}
                />
            </div>
        </AppLayout>
    );
}
