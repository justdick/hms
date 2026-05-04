import { Badge } from '@/components/ui/badge';
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
    diagnoses: Diagnosis[];
    onClick: () => void;
}

export function DiagnosisSummaryCard({ diagnoses, onClick }: Props) {
    const displayDiagnoses = diagnoses.slice(0, 6);

    return (
        <Card
            className="cursor-pointer transition-all hover:border-blue-300 hover:shadow-md dark:hover:border-blue-700"
            onClick={onClick}
        >
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-lg">
                    <Stethoscope className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                    Diagnoses
                </CardTitle>
            </CardHeader>
            <CardContent>
                {diagnoses.length > 0 ? (
                    <div className="space-y-3">
                        <div className="grid grid-cols-2 gap-2">
                            {displayDiagnoses.map((diagnosis, index) => (
                                <div
                                    key={`${diagnosis.diagnosis_name}-${index}`}
                                    className="rounded-lg border p-2 dark:border-gray-700"
                                >
                                    <p className="truncate text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {diagnosis.diagnosis_name}
                                    </p>
                                    <div className="mt-1 flex items-center gap-1">
                                        <Badge
                                            variant="outline"
                                            className="text-[10px] capitalize"
                                        >
                                            {diagnosis.diagnosis_type}
                                        </Badge>
                                        {diagnosis.icd_code && (
                                            <span className="text-[10px] text-gray-500 dark:text-gray-400">
                                                {diagnosis.icd_code}
                                            </span>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                        {diagnoses.length > 6 && (
                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                +{diagnoses.length - 6} more
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
