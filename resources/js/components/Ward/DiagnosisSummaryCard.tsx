import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Stethoscope } from 'lucide-react';

interface Doctor {
    id: number;
    name: string;
}

interface Diagnosis {
    diagnosis_name: string;
    icd_code?: string | null;
    diagnosis_type: string;
    diagnosed_by?: Doctor;
}

interface Props {
    diagnosis: Diagnosis | null;
    onClick: () => void;
}

export function DiagnosisSummaryCard({ diagnosis, onClick }: Props) {
    return (
        <Card
            className="cursor-pointer transition-all hover:border-blue-300 hover:shadow-md dark:hover:border-blue-700"
            onClick={onClick}
        >
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-lg">
                    <Stethoscope className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                    Diagnosis
                </CardTitle>
            </CardHeader>
            <CardContent>
                {diagnosis ? (
                    <div className="space-y-2">
                        <p className="text-base font-semibold text-gray-900 dark:text-gray-100">
                            {diagnosis.diagnosis_name}
                        </p>
                        {diagnosis.icd_code && (
                            <p className="text-sm text-gray-600 dark:text-gray-400">
                                ICD Code: {diagnosis.icd_code}
                            </p>
                        )}
                        {diagnosis.diagnosed_by && (
                            <p className="text-sm text-gray-600 dark:text-gray-400">
                                By: Dr. {diagnosis.diagnosed_by.name}
                            </p>
                        )}
                    </div>
                ) : (
                    <div className="py-4 text-center">
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            No diagnosis recorded
                        </p>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
