import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Activity } from 'lucide-react';

interface VitalSign {
    id: number;
    temperature: number;
    blood_pressure_systolic: number;
    blood_pressure_diastolic: number;
    pulse_rate: number;
    respiratory_rate: number;
    recorded_at: string;
    recorded_by?: {
        id: number;
        name: string;
    };
}

interface Props {
    vitals: VitalSign[];
}

export function HistoricalVitalsTable({ vitals }: Props) {
    const formatDateTime = (dateString: string) => {
        return new Date(dateString).toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    if (vitals.length === 0) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Activity className="h-5 w-5" />
                        Vitals History During Admission
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="py-8 text-center text-gray-500 dark:text-gray-400">
                        <Activity className="mx-auto mb-3 h-12 w-12 text-gray-300 dark:text-gray-600" />
                        <p>No vitals recorded yet for this admission</p>
                    </div>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Activity className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                    Vitals History During Admission
                </CardTitle>
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {vitals.length} recording(s) during this admission
                </p>
            </CardHeader>
            <CardContent>
                <div className="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Date & Time</TableHead>
                                <TableHead>Temperature</TableHead>
                                <TableHead>Blood Pressure</TableHead>
                                <TableHead>Heart Rate</TableHead>
                                <TableHead>Respiratory Rate</TableHead>
                                <TableHead>Recorded By</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {vitals.map((vital) => (
                                <TableRow key={vital.id}>
                                    <TableCell className="font-medium">
                                        {formatDateTime(vital.recorded_at)}
                                    </TableCell>
                                    <TableCell>
                                        <span className="font-mono">
                                            {vital.temperature}°C
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <span className="font-mono">
                                            {Math.round(
                                                vital.blood_pressure_systolic,
                                            )}
                                            /
                                            {Math.round(
                                                vital.blood_pressure_diastolic,
                                            )}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <span className="font-mono">
                                            {vital.pulse_rate} bpm
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <span className="font-mono">
                                            {vital.respiratory_rate}/min
                                        </span>
                                    </TableCell>
                                    <TableCell className="text-sm text-gray-600 dark:text-gray-400">
                                        {vital.recorded_by?.name || 'N/A'}
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
