import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Pill } from 'lucide-react';

interface Drug {
    id: number;
    name: string;
    strength?: string;
    form?: string;
}

interface Prescription {
    id: number;
    medication_name: string;
    drug?: Drug;
    dosage?: string;
    dose_quantity?: string;
    frequency?: string;
    duration?: string;
    route?: string;
    status?: string;
}

interface Props {
    prescriptions: Prescription[];
    onClick: () => void;
}

export function PrescriptionsSummaryCard({ prescriptions, onClick }: Props) {
    const activePrescriptions = prescriptions.filter(
        (p) => !p.status || p.status === 'active',
    );
    const displayPrescriptions = activePrescriptions.slice(0, 5);

    return (
        <Card
            className="cursor-pointer transition-all hover:border-blue-300 hover:shadow-md dark:hover:border-blue-700"
            onClick={onClick}
        >
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-lg">
                    <Pill className="h-5 w-5 text-green-600 dark:text-green-400" />
                    Prescriptions
                </CardTitle>
            </CardHeader>
            <CardContent>
                {activePrescriptions.length > 0 ? (
                    <div className="space-y-3">
                        <div className="space-y-2">
                            {displayPrescriptions.map((prescription) => (
                                <div
                                    key={prescription.id}
                                    className="rounded-lg border p-2 dark:border-gray-700"
                                >
                                    <p className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {prescription.medication_name}
                                    </p>
                                    <p className="text-xs text-gray-600 dark:text-gray-400">
                                        {prescription.dose_quantity ||
                                            prescription.dosage}{' '}
                                        {prescription.frequency &&
                                            `• ${prescription.frequency}`}
                                    </p>
                                </div>
                            ))}
                        </div>
                        {activePrescriptions.length > 5 && (
                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                +{activePrescriptions.length - 5} more
                                prescription
                                {activePrescriptions.length - 5 !== 1
                                    ? 's'
                                    : ''}
                            </p>
                        )}
                        <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                            Total: {activePrescriptions.length} active
                            prescription
                            {activePrescriptions.length !== 1 ? 's' : ''}
                        </p>
                    </div>
                ) : (
                    <div className="py-4 text-center">
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            No active prescriptions
                        </p>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
