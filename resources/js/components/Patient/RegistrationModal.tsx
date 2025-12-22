import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import PatientRegistrationForm from './RegistrationForm';

interface Patient {
    id: number;
    patient_number: string;
    full_name: string;
    age: number;
    gender: string;
    phone_number: string | null;
    has_checkin_today: boolean;
}

interface InsurancePlan {
    id: number;
    plan_name: string;
    plan_code: string;
    provider: {
        id: number;
        name: string;
        code: string;
        is_nhis?: boolean;
    };
}

interface NhisSettings {
    verification_mode: 'manual' | 'extension';
    nhia_portal_url: string;
    auto_open_portal: boolean;
    credentials?: {
        username: string;
        password: string;
    } | null;
}

interface PatientRegistrationModalProps {
    open: boolean;
    onClose: () => void;
    onPatientRegistered: (patient: Patient) => void;
    insurancePlans?: InsurancePlan[];
    nhisSettings?: NhisSettings;
    registrationEndpoint?: string;
}

export default function PatientRegistrationModal({
    open,
    onClose,
    onPatientRegistered,
    insurancePlans = [],
    nhisSettings,
    registrationEndpoint = '/checkin/patients',
}: PatientRegistrationModalProps) {
    const handlePatientRegistered = (patient: Patient) => {
        onPatientRegistered(patient);
        onClose();
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-h-[90vh] max-w-3xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Register New Patient</DialogTitle>
                    <DialogDescription>
                        Enter patient information to create a new patient
                        record. Insurance information is optional.
                    </DialogDescription>
                </DialogHeader>

                <PatientRegistrationForm
                    onPatientRegistered={handlePatientRegistered}
                    onCancel={onClose}
                    registrationEndpoint={registrationEndpoint}
                    showCancelButton={true}
                    insurancePlans={insurancePlans}
                    nhisSettings={nhisSettings}
                />
            </DialogContent>
        </Dialog>
    );
}
