import CheckinModal from '@/components/Checkin/CheckinModal';
import CheckinPromptDialog from '@/components/Checkin/CheckinPromptDialog';
import PatientRegistrationModal from '@/components/Patient/RegistrationModal';
import AppLayout from '@/layouts/app-layout';
import { Head, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { PatientData, patientsColumns } from './patients-columns';
import { DataTable } from './patients-data-table';

interface Patient {
    id: number;
    patient_number: string;
    full_name: string;
    age: number;
    gender: string;
    phone_number: string | null;
}

interface Department {
    id: number;
    name: string;
    code: string;
    description: string;
}

interface InsurancePlan {
    id: number;
    plan_name: string;
    plan_code: string;
    provider: {
        id: number;
        name: string;
        code: string;
    };
}

interface Props {
    patients: {
        data: PatientData[];
    };
    departments?: Department[];
    insurancePlans?: InsurancePlan[];
    patient?: Patient;
}

export default function PatientsIndex({
    patients,
    departments = [],
    insurancePlans = [],
}: Props) {
    const page = usePage<Props>();
    const [registrationModalOpen, setRegistrationModalOpen] = useState(false);
    const [checkinPromptOpen, setCheckinPromptOpen] = useState(false);
    const [checkinModalOpen, setCheckinModalOpen] = useState(false);
    const [selectedPatient, setSelectedPatient] = useState<Patient | null>(
        null,
    );

    // Check for newly registered patient from flash data
    useEffect(() => {
        if (page.props.patient && !selectedPatient) {
            setSelectedPatient(page.props.patient);
            setCheckinPromptOpen(true);
        }
    }, [page.props.patient]);

    const handlePatientRegistered = (patient: {
        id: number;
        patient_number: string;
        full_name: string;
        age: number;
        gender: string;
        phone_number: string | null;
    }) => {
        // Close the registration modal
        setRegistrationModalOpen(false);

        // Convert the registered patient to the Patient type for the check-in prompt
        const fullPatient: Patient = {
            id: patient.id,
            patient_number: patient.patient_number,
            full_name: patient.full_name,
            age: patient.age,
            gender: patient.gender,
            phone_number: patient.phone_number,
        };
        setSelectedPatient(fullPatient);
        setCheckinPromptOpen(true);
    };

    const handleCheckinNow = () => {
        setCheckinPromptOpen(false);
        setCheckinModalOpen(true);
    };

    const handleCheckinLater = () => {
        setCheckinPromptOpen(false);
        setSelectedPatient(null);
    };

    const handleCheckinSuccess = () => {
        setCheckinModalOpen(false);
        setSelectedPatient(null);
    };

    return (
        <AppLayout>
            <Head title="Patients" />

            <div className="space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">
                        Patients
                    </h1>
                    <p className="text-muted-foreground">
                        Search and manage patient records
                    </p>
                </div>

                {/* DataTable */}
                <DataTable
                    columns={patientsColumns}
                    data={patients?.data || []}
                    onRegisterClick={() => setRegistrationModalOpen(true)}
                />
            </div>

            {/* Modals */}
            <PatientRegistrationModal
                open={registrationModalOpen}
                onClose={() => setRegistrationModalOpen(false)}
                onPatientRegistered={handlePatientRegistered}
                insurancePlans={insurancePlans}
                registrationEndpoint="/checkin/patients"
            />

            <CheckinPromptDialog
                open={checkinPromptOpen}
                onClose={handleCheckinLater}
                patient={selectedPatient}
                onCheckinNow={handleCheckinNow}
            />

            <CheckinModal
                open={checkinModalOpen}
                onClose={() => setCheckinModalOpen(false)}
                patient={selectedPatient}
                departments={departments}
                onSuccess={handleCheckinSuccess}
            />
        </AppLayout>
    );
}
