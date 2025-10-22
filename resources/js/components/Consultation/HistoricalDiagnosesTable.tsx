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
import { Stethoscope } from 'lucide-react';

interface Diagnosis {
    id: number;
    diagnosis: string;
    code?: string;
    icd_10?: string;
    g_drg?: string;
}

interface Doctor {
    id: number;
    name: string;
}

interface ConsultationDiagnosis {
    id: number;
    type: 'provisional' | 'principal' | 'differential';
    diagnosis: Diagnosis;
    notes?: string;
    created_at: string;
    consultation?: {
        doctor: Doctor;
    };
}

interface Props {
    diagnoses: ConsultationDiagnosis[];
}

export function HistoricalDiagnosesTable({ diagnoses }: Props) {
    const formatDateTime = (dateString: string) => {
        return new Date(dateString).toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const getTypeBadge = (type: string) => {
        const typeConfig: Record<string, { variant: any; label: string }> = {
            provisional: { variant: 'secondary', label: 'Provisional' },
            principal: { variant: 'default', label: 'Principal' },
            differential: { variant: 'outline', label: 'Differential' },
        };

        const config = typeConfig[type] || { variant: 'default', label: type };
        return (
            <Badge variant={config.variant as any} className="capitalize">
                {config.label}
            </Badge>
        );
    };

    if (diagnoses.length === 0) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Stethoscope className="h-5 w-5" />
                        Diagnosis History During Admission
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="py-8 text-center text-gray-500 dark:text-gray-400">
                        <Stethoscope className="mx-auto mb-3 h-12 w-12 text-gray-300 dark:text-gray-600" />
                        <p>No diagnoses recorded yet for this admission</p>
                    </div>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Stethoscope className="h-5 w-5 text-indigo-600 dark:text-indigo-400" />
                    Diagnosis History During Admission
                </CardTitle>
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {diagnoses.length} diagnosis/diagnoses during this admission
                </p>
            </CardHeader>
            <CardContent>
                <div className="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Date</TableHead>
                                <TableHead>Diagnosis</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>ICD-10 Code</TableHead>
                                <TableHead>Notes</TableHead>
                                <TableHead>Diagnosed By</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {diagnoses.map((item) => (
                                <TableRow key={item.id}>
                                    <TableCell className="font-medium">
                                        {formatDateTime(item.created_at)}
                                    </TableCell>
                                    <TableCell>
                                        <div>
                                            <p className="font-medium">
                                                {item.diagnosis.diagnosis}
                                            </p>
                                            {item.diagnosis.code && (
                                                <p className="text-xs text-gray-500 dark:text-gray-400">
                                                    Code: {item.diagnosis.code}
                                                </p>
                                            )}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        {getTypeBadge(item.type)}
                                    </TableCell>
                                    <TableCell>
                                        <span className="font-mono text-sm">
                                            {item.diagnosis.icd_10 || 'N/A'}
                                        </span>
                                    </TableCell>
                                    <TableCell className="max-w-xs truncate">
                                        {item.notes || '-'}
                                    </TableCell>
                                    <TableCell className="text-sm text-gray-600 dark:text-gray-400">
                                        {item.consultation?.doctor?.name ||
                                            'N/A'}
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
