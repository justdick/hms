import { DiagnosisSummaryCard } from '@/components/Ward/DiagnosisSummaryCard';
import { LabsSummaryCard } from '@/components/Ward/LabsSummaryCard';
import { PrescriptionsSummaryCard } from '@/components/Ward/PrescriptionsSummaryCard';
import { VitalsSummaryCard } from '@/components/Ward/VitalsSummaryCard';
import { isToday } from 'date-fns';
import { useMemo } from 'react';

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

interface ConsultationPrescription {
    id: number;
    drug?: Drug;
    medication_name: string;
    dosage?: string;
    dosage_form?: string;
    frequency: string;
    duration: string;
    route?: string;
    dose_quantity?: string;
    instructions?: string;
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

interface Prescription {
    id: number;
    drug: Drug;
    medication_name: string;
    dosage?: string;
    frequency?: string;
    duration?: string;
    route?: string;
}

interface MedicationAdministration {
    id: number;
    prescription_id: number;
    administered_at: string;
    status: 'given' | 'held' | 'refused' | 'omitted';
    administered_by?: User;
    notes?: string;
    dosage_given?: string;
    route?: string;
}

interface WardRoundDiagnosis {
    id: number;
    diagnosis_name: string;
    icd_code: string;
    diagnosis_type: string;
    diagnosed_by?: {
        id: number;
        name: string;
    };
}

interface DiagnosisRecord {
    id: number;
    diagnosis: string;
    icd_10?: string;
}

interface ConsultationDiagnosis {
    id: number;
    type: string;
    diagnosis?: DiagnosisRecord;
}

interface LabService {
    id: number;
    name: string;
    code: string;
    price: number;
}

interface LabOrder {
    id: number;
    lab_service?: LabService;
    status: string;
    ordered_at: string;
    priority: string;
    special_instructions?: string;
    result_values?: any;
    result_notes?: string;
}

interface WardRoundPrescription {
    id: number;
    medication_name: string;
    drug?: Drug;
    dose_quantity?: string;
    frequency: string;
    duration: string;
    instructions?: string;
    status: string;
}

interface WardRound {
    id: number;
    patient_admission_id: number;
    doctor_id: number;
    doctor?: Doctor;
    day_number: number;
    round_type: string;
    status: 'in_progress' | 'completed';
    round_datetime: string;
    presenting_complaint?: string;
    history_presenting_complaint?: string;
    on_direct_questioning?: string;
    examination_findings?: string;
    assessment_notes?: string;
    plan_notes?: string;
    notes?: string;
    findings?: string;
    plan?: string;
    created_at: string;
    updated_at: string;
    diagnoses?: WardRoundDiagnosis[];
    prescriptions?: WardRoundPrescription[];
    lab_orders?: LabOrder[];
}

interface PatientCheckin {
    id: number;
    vital_signs?: VitalSign[];
}

interface Consultation {
    id: number;
    doctor: Doctor;
    chief_complaint?: string;
    patient_checkin?: PatientCheckin;
    prescriptions?: ConsultationPrescription[];
    lab_orders?: LabOrder[];
    diagnoses?: ConsultationDiagnosis[];
}

interface VitalsSchedule {
    id: number;
    interval_minutes: number;
    is_active: boolean;
}

interface PatientAdmission {
    id: number;
    admission_number: string;
    status: string;
    admitted_at: string;
    discharged_at?: string;
    consultation?: Consultation;
    vital_signs?: VitalSign[];
    medication_administrations?: MedicationAdministration[];
    ward_rounds?: WardRound[];
    vitals_schedule?: VitalsSchedule;
}

interface Props {
    admission: PatientAdmission;
    onNavigateToTab: (tabValue: string) => void;
}

export function OverviewTab({ admission, onNavigateToTab }: Props) {
    // Compute latest diagnosis from consultation or most recent ward round
    const latestDiagnosis = useMemo(() => {
        // Get ward round diagnoses
        const wardRoundDiagnoses = admission.ward_rounds
            ?.flatMap((round) => round.diagnoses || [])
            .sort((a, b) => b.id - a.id);

        // If there are ward round diagnoses, use the most recent one
        if (wardRoundDiagnoses && wardRoundDiagnoses.length > 0) {
            return wardRoundDiagnoses[0];
        }

        // Fall back to consultation diagnoses (principal first, then provisional)
        const consultationDiagnoses = admission.consultation?.diagnoses || [];
        const principalDiagnosis = consultationDiagnoses.find(
            (d) => d.type === 'principal',
        );
        const firstDiagnosis = principalDiagnosis || consultationDiagnoses[0];

        if (firstDiagnosis?.diagnosis) {
            return {
                diagnosis_name: firstDiagnosis.diagnosis.diagnosis,
                icd_code: firstDiagnosis.diagnosis.icd_10 || null,
                diagnosis_type: firstDiagnosis.type,
                diagnosed_by: admission.consultation?.doctor,
            };
        }

        return null;
    }, [admission]);

    // Compute all prescriptions from consultation and ward rounds
    const allPrescriptions = useMemo(() => {
        return [
            ...(admission.consultation?.prescriptions || []),
            ...(admission.ward_rounds?.flatMap(
                (round) => round.prescriptions || [],
            ) || []),
        ];
    }, [admission]);

    // Merge consultation vitals with ward vitals
    const allVitals = useMemo(() => {
        return [
            ...(admission.vital_signs || []),
            ...(admission.consultation?.patient_checkin?.vital_signs || []),
        ].sort(
            (a, b) =>
                new Date(b.recorded_at).getTime() -
                new Date(a.recorded_at).getTime(),
        );
    }, [admission]);

    const latestVital = allVitals[0];

    // Calculate today's medication administrations
    const todayMedications = useMemo(() => {
        return (
            admission.medication_administrations?.filter((med) =>
                isToday(new Date(med.administered_at)),
            ) || []
        );
    }, [admission]);

    // Collect all lab orders from consultation and ward rounds
    const allLabOrders = useMemo(() => {
        const consultationLabs = admission.consultation?.lab_orders || [];
        const wardRoundLabs =
            admission.ward_rounds?.flatMap((round) => round.lab_orders || []) ||
            [];

        return [...consultationLabs, ...wardRoundLabs].sort(
            (a, b) =>
                new Date(b.ordered_at).getTime() -
                new Date(a.ordered_at).getTime(),
        );
    }, [admission]);

    // Calculate admission day number
    const admissionDayNumber = useMemo(() => {
        const admissionDate = new Date(admission.admitted_at);
        const today = new Date();
        const diffTime = Math.abs(today.getTime() - admissionDate.getTime());
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        return diffDays;
    }, [admission.admitted_at]);

    return (
        <div className="space-y-4">
            {/* Admission Info Banner */}
            <div className="rounded-lg border bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-950/20">
                <div className="flex items-center justify-between">
                    <div>
                        <p className="text-sm font-medium text-blue-900 dark:text-blue-100">
                            Admission Day {admissionDayNumber}
                        </p>
                        <p className="text-xs text-blue-700 dark:text-blue-300">
                            Admitted on{' '}
                            {new Date(admission.admitted_at).toLocaleDateString(
                                'en-US',
                                {
                                    year: 'numeric',
                                    month: 'long',
                                    day: 'numeric',
                                },
                            )}
                        </p>
                    </div>
                </div>
            </div>

            {/* Summary Cards Grid - 2x2 on desktop, stacked on mobile */}
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                <DiagnosisSummaryCard
                    diagnosis={latestDiagnosis}
                    onClick={() => onNavigateToTab('rounds')}
                />
                <PrescriptionsSummaryCard
                    prescriptions={allPrescriptions}
                    onClick={() => onNavigateToTab('medications')}
                />
                <VitalsSummaryCard
                    latestVital={latestVital}
                    vitalsSchedule={admission.vitals_schedule}
                    onClick={() => onNavigateToTab('vitals')}
                />
                <LabsSummaryCard
                    labOrders={allLabOrders}
                    onClick={() => onNavigateToTab('labs')}
                />
            </div>
        </div>
    );
}
