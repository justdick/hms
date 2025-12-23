import { Badge } from '@/components/ui/badge';
import { CreditCard, ShieldCheck } from 'lucide-react';

interface Patient {
    id: number;
    first_name: string;
    last_name: string;
    patient_number: string;
    phone_number: string;
}

interface Visit {
    checkin_id: number;
    department: { id: number; name: string };
    checked_in_at: string;
    total_pending: number;
    patient_copay: number;
    insurance_covered: number;
    charges_count: number;
    charges: any[];
}

interface PatientSearchResult {
    patient_id: number;
    patient: Patient;
    total_pending: number;
    total_patient_owes: number;
    total_insurance_covered: number;
    total_charges: number;
    visits_with_charges: number;
    visits: Visit[];
}

interface PatientSearchResultsProps {
    results: PatientSearchResult[];
    searchQuery: string;
    selectedPatientId: number | null;
    onPatientSelect: (patient: PatientSearchResult) => void;
    formatCurrency: (amount: number) => string;
}

export function PatientSearchResults({
    results,
    searchQuery,
    selectedPatientId,
    onPatientSelect,
    formatCurrency,
}: PatientSearchResultsProps) {
    if (searchQuery.length === 0) {
        return (
            <div className="py-8 text-center text-muted-foreground">
                <CreditCard className="mx-auto mb-3 h-12 w-12 opacity-50" />
                <p>Search for patients with pending charges</p>
            </div>
        );
    }

    if (results.length === 0 && searchQuery.length >= 2) {
        return (
            <div className="py-8 text-center text-muted-foreground">
                No patients found with pending charges matching "{searchQuery}"
            </div>
        );
    }

    return (
        <div className="max-h-96 space-y-2 overflow-y-auto">
            {results.map((patient) => (
                <div
                    key={patient.patient_id}
                    className={`cursor-pointer rounded-lg border p-4 transition-colors hover:bg-muted/50 ${
                        selectedPatientId === patient.patient_id
                            ? 'border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-950/20'
                            : ''
                    }`}
                    onClick={() => onPatientSelect(patient)}
                >
                    <div className="flex items-start justify-between gap-4">
                        <div className="flex-1">
                            <h3 className="font-medium">
                                {patient.patient.first_name}{' '}
                                {patient.patient.last_name}
                            </h3>
                            <p className="text-sm text-muted-foreground">
                                {patient.patient.patient_number}
                            </p>
                            {patient.patient.phone_number && (
                                <p className="text-sm text-muted-foreground">
                                    {patient.patient.phone_number}
                                </p>
                            )}
                            <p className="text-xs text-muted-foreground">
                                {patient.visits_with_charges} visit
                                {patient.visits_with_charges !== 1
                                    ? 's'
                                    : ''}{' '}
                                with charges
                            </p>
                        </div>
                        <div className="text-right">
                            <div className="space-y-1">
                                <div className="font-medium text-orange-600">
                                    Patient Owes:{' '}
                                    {formatCurrency(patient.total_patient_owes)}
                                </div>
                                {patient.total_insurance_covered > 0 && (
                                    <div className="text-xs text-green-600">
                                        <ShieldCheck className="inline h-3 w-3" />{' '}
                                        Insurance:{' '}
                                        {formatCurrency(
                                            patient.total_insurance_covered,
                                        )}
                                    </div>
                                )}
                            </div>
                            <Badge variant="outline" className="mt-1 text-xs">
                                {patient.total_charges} charge
                                {patient.total_charges !== 1 ? 's' : ''}
                            </Badge>
                        </div>
                    </div>
                </div>
            ))}
        </div>
    );
}
