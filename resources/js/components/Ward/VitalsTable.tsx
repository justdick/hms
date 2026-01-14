import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Activity, Edit2, Heart, Thermometer } from 'lucide-react';

interface User {
    id: number;
    name: string;
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
    notes?: string | null;
    recorded_at: string;
    recorded_by?: User;
}

interface Props {
    vitals: VitalSign[];
    onEdit?: (vital: VitalSign) => void;
}

export function VitalsTable({ vitals, onEdit }: Props) {
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
            <div className="py-12 text-center">
                <Thermometer className="mx-auto mb-4 h-12 w-12 text-gray-300 dark:text-gray-600" />
                <p className="text-gray-500 dark:text-gray-400">
                    No vital signs recorded yet
                </p>
            </div>
        );
    }

    return (
        <div className="overflow-x-auto">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Date & Time</TableHead>
                        <TableHead>
                            <div className="flex items-center gap-2">
                                <Thermometer className="h-3 w-3" />
                                Temp (°C)
                            </div>
                        </TableHead>
                        <TableHead>
                            <div className="flex items-center gap-2">
                                <Heart className="h-3 w-3" />
                                BP
                            </div>
                        </TableHead>
                        <TableHead>
                            <div className="flex items-center gap-2">
                                <Activity className="h-3 w-3" />
                                Pulse (bpm)
                            </div>
                        </TableHead>
                        <TableHead>RR (/min)</TableHead>
                        <TableHead>SpO₂ (%)</TableHead>
                        <TableHead>Recorded By</TableHead>
                        {onEdit && (
                            <TableHead className="w-16">Actions</TableHead>
                        )}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {vitals.map((vital) => (
                        <TableRow key={vital.id}>
                            <TableCell className="font-medium">
                                {formatDateTime(vital.recorded_at)}
                            </TableCell>
                            <TableCell>
                                {vital.temperature ? (
                                    <span className="font-mono">
                                        {vital.temperature}
                                    </span>
                                ) : (
                                    <span className="text-gray-400 dark:text-gray-500">
                                        -
                                    </span>
                                )}
                            </TableCell>
                            <TableCell>
                                {vital.blood_pressure_systolic &&
                                vital.blood_pressure_diastolic ? (
                                    <span className="font-mono">
                                        {Math.round(
                                            vital.blood_pressure_systolic,
                                        )}
                                        /
                                        {Math.round(
                                            vital.blood_pressure_diastolic,
                                        )}
                                    </span>
                                ) : (
                                    <span className="text-gray-400 dark:text-gray-500">
                                        -
                                    </span>
                                )}
                            </TableCell>
                            <TableCell>
                                {vital.pulse_rate ? (
                                    <span className="font-mono">
                                        {vital.pulse_rate}
                                    </span>
                                ) : (
                                    <span className="text-gray-400 dark:text-gray-500">
                                        -
                                    </span>
                                )}
                            </TableCell>
                            <TableCell>
                                {vital.respiratory_rate ? (
                                    <span className="font-mono">
                                        {vital.respiratory_rate}
                                    </span>
                                ) : (
                                    <span className="text-gray-400 dark:text-gray-500">
                                        -
                                    </span>
                                )}
                            </TableCell>
                            <TableCell>
                                {vital.oxygen_saturation ? (
                                    <span className="font-mono">
                                        {vital.oxygen_saturation}
                                    </span>
                                ) : (
                                    <span className="text-gray-400 dark:text-gray-500">
                                        -
                                    </span>
                                )}
                            </TableCell>
                            <TableCell className="text-sm text-gray-600 dark:text-gray-400">
                                {vital.recorded_by?.name || 'N/A'}
                            </TableCell>
                            {onEdit && (
                                <TableCell>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => onEdit(vital)}
                                        title="Edit vital signs"
                                    >
                                        <Edit2 className="h-4 w-4" />
                                    </Button>
                                </TableCell>
                            )}
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}
