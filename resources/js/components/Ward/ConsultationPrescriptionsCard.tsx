import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Pill } from 'lucide-react';

interface Drug {
    id: number;
    name: string;
    form?: string;
    strength?: string;
}

interface Prescription {
    id: number;
    medication_name: string;
    drug?: Drug;
    dosage_form?: string;
    frequency: string;
    duration: string;
    dose_quantity?: string;
    instructions?: string;
}

interface Props {
    prescriptions: Prescription[];
}

export function ConsultationPrescriptionsCard({ prescriptions }: Props) {
    return (
        <Card className="border-blue-200 dark:border-blue-800">
            <CardHeader>
                <div className="flex items-center justify-between">
                    <CardTitle className="flex items-center gap-2">
                        <Pill className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                        Prescriptions from Admission Consultation
                    </CardTitle>
                    <Badge
                        variant="outline"
                        className="bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300"
                    >
                        Consultation
                    </Badge>
                </div>
            </CardHeader>
            <CardContent>
                {prescriptions.length > 0 ? (
                    <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Medication</TableHead>
                                    <TableHead>Dosage Form</TableHead>
                                    <TableHead>Frequency</TableHead>
                                    <TableHead>Duration</TableHead>
                                    <TableHead>Instructions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {prescriptions.map((prescription) => (
                                    <TableRow key={prescription.id}>
                                        <TableCell>
                                            <div>
                                                <p className="font-medium">
                                                    {
                                                        prescription.medication_name
                                                    }
                                                </p>
                                                {prescription.drug && (
                                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                                        {prescription.drug
                                                            .form &&
                                                            prescription.drug
                                                                .form}
                                                        {prescription.drug
                                                            .strength &&
                                                            ` • ${prescription.drug.strength}`}
                                                    </p>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            {prescription.dosage_form || '-'}
                                        </TableCell>
                                        <TableCell>
                                            {prescription.frequency}
                                        </TableCell>
                                        <TableCell>
                                            {prescription.duration}
                                        </TableCell>
                                        <TableCell className="max-w-xs">
                                            {prescription.instructions || '-'}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                ) : (
                    <div className="py-8 text-center">
                        <Pill className="mx-auto mb-3 h-12 w-12 text-gray-300 dark:text-gray-600" />
                        <p className="text-gray-600 dark:text-gray-400">
                            No medications were prescribed during the admission
                            consultation
                        </p>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
