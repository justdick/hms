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

interface Doctor {
    id: number;
    name: string;
}

interface Prescription {
    id: number;
    medication_name: string;
    drug?: Drug;
    dosage?: string;
    frequency: string;
    duration: string;
    instructions?: string;
    status: string;
    created_at: string;
    consultation?: {
        doctor: Doctor;
    };
}

interface Props {
    prescriptions: Prescription[];
}

export function HistoricalPrescriptionsTable({ prescriptions }: Props) {
    const formatDateTime = (dateString: string) => {
        return new Date(dateString).toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const getStatusBadge = (status: string) => {
        const statusConfig: Record<string, { variant: any; label: string }> = {
            prescribed: { variant: 'default', label: 'Prescribed' },
            dispensed: { variant: 'secondary', label: 'Dispensed' },
            administered: { variant: 'secondary', label: 'Administered' },
            completed: { variant: 'secondary', label: 'Completed' },
            discontinued: { variant: 'destructive', label: 'Discontinued' },
        };

        const config = statusConfig[status] || {
            variant: 'default',
            label: status,
        };
        return (
            <Badge variant={config.variant as any} className="capitalize">
                {config.label}
            </Badge>
        );
    };

    if (prescriptions.length === 0) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Pill className="h-5 w-5" />
                        Prescription History During Admission
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="py-8 text-center text-gray-500 dark:text-gray-400">
                        <Pill className="mx-auto mb-3 h-12 w-12 text-gray-300 dark:text-gray-600" />
                        <p>No prescriptions recorded yet for this admission</p>
                    </div>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Pill className="h-5 w-5 text-purple-600 dark:text-purple-400" />
                    Prescription History During Admission
                </CardTitle>
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {prescriptions.length} prescription(s) during this admission
                </p>
            </CardHeader>
            <CardContent>
                <div className="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Date Prescribed</TableHead>
                                <TableHead>Medication</TableHead>
                                <TableHead>Dosage</TableHead>
                                <TableHead>Frequency</TableHead>
                                <TableHead>Duration</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Prescribed By</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {prescriptions.map((prescription) => (
                                <TableRow key={prescription.id}>
                                    <TableCell className="font-medium">
                                        {formatDateTime(
                                            prescription.created_at,
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <div>
                                            <p className="font-medium">
                                                {prescription.medication_name}
                                            </p>
                                            {prescription.drug?.form && (
                                                <p className="text-xs text-gray-500 dark:text-gray-400">
                                                    {prescription.drug.form}
                                                    {prescription.drug
                                                        .strength &&
                                                        ` • ${prescription.drug.strength}`}
                                                </p>
                                            )}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        {prescription.dosage || 'As directed'}
                                    </TableCell>
                                    <TableCell>
                                        {prescription.frequency}
                                    </TableCell>
                                    <TableCell>
                                        {prescription.duration}
                                    </TableCell>
                                    <TableCell>
                                        {getStatusBadge(prescription.status)}
                                    </TableCell>
                                    <TableCell className="text-sm text-gray-600 dark:text-gray-400">
                                        {prescription.consultation?.doctor
                                            ?.name || 'N/A'}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </CardContent>
        </Card>
    );
}
