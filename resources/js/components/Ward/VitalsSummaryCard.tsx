import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDistanceToNow } from 'date-fns';
import { Activity, AlertCircle, Heart, Thermometer, Wind } from 'lucide-react';

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
    recorded_at: string;
    recorded_by?: User;
}

interface VitalsSchedule {
    id: number;
    interval_minutes: number;
    is_active: boolean;
}

interface Props {
    latestVital: VitalSign | null;
    vitalsSchedule?: VitalsSchedule;
    onClick: () => void;
}

export function VitalsSummaryCard({
    latestVital,
    vitalsSchedule,
    onClick,
}: Props) {
    const isOverdue =
        latestVital &&
        new Date(latestVital.recorded_at) <
            new Date(Date.now() - 4 * 60 * 60 * 1000);

    return (
        <Card
            className={`cursor-pointer transition-all hover:border-blue-300 hover:shadow-md dark:hover:border-blue-700 ${
                isOverdue
                    ? 'border-yellow-300 bg-yellow-50 dark:border-yellow-800 dark:bg-yellow-950/20'
                    : ''
            }`}
            onClick={onClick}
        >
            <CardHeader>
                <div className="flex items-center justify-between">
                    <CardTitle className="flex items-center gap-2 text-lg">
                        <Heart className="h-5 w-5 text-red-600 dark:text-red-400" />
                        Vital Signs
                    </CardTitle>
                    {isOverdue && (
                        <Badge
                            variant="outline"
                            className="border-yellow-500 text-yellow-700 dark:border-yellow-600 dark:text-yellow-400"
                        >
                            <AlertCircle className="mr-1 h-3 w-3" />
                            Overdue
                        </Badge>
                    )}
                </div>
            </CardHeader>
            <CardContent>
                {latestVital ? (
                    <div className="space-y-3">
                        <div className="grid grid-cols-2 gap-2">
                            {latestVital.temperature && (
                                <div className="flex items-center gap-2">
                                    <Thermometer className="h-4 w-4 text-gray-500 dark:text-gray-400" />
                                    <div>
                                        <p className="text-xs text-gray-600 dark:text-gray-400">
                                            Temp
                                        </p>
                                        <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                            {latestVital.temperature}°C
                                        </p>
                                    </div>
                                </div>
                            )}
                            {latestVital.blood_pressure_systolic &&
                                latestVital.blood_pressure_diastolic && (
                                    <div className="flex items-center gap-2">
                                        <Heart className="h-4 w-4 text-gray-500 dark:text-gray-400" />
                                        <div>
                                            <p className="text-xs text-gray-600 dark:text-gray-400">
                                                BP
                                            </p>
                                            <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                {Math.round(
                                                    latestVital.blood_pressure_systolic,
                                                )}
                                                /
                                                {Math.round(
                                                    latestVital.blood_pressure_diastolic,
                                                )}
                                            </p>
                                        </div>
                                    </div>
                                )}
                            {latestVital.pulse_rate && (
                                <div className="flex items-center gap-2">
                                    <Activity className="h-4 w-4 text-gray-500 dark:text-gray-400" />
                                    <div>
                                        <p className="text-xs text-gray-600 dark:text-gray-400">
                                            Pulse
                                        </p>
                                        <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                            {latestVital.pulse_rate} bpm
                                        </p>
                                    </div>
                                </div>
                            )}
                            {latestVital.respiratory_rate && (
                                <div className="flex items-center gap-2">
                                    <Wind className="h-4 w-4 text-gray-500 dark:text-gray-400" />
                                    <div>
                                        <p className="text-xs text-gray-600 dark:text-gray-400">
                                            Resp
                                        </p>
                                        <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                            {latestVital.respiratory_rate}/min
                                        </p>
                                    </div>
                                </div>
                            )}
                        </div>
                        <p className="text-xs text-gray-600 dark:text-gray-400">
                            Recorded{' '}
                            {formatDistanceToNow(
                                new Date(latestVital.recorded_at),
                                { addSuffix: true },
                            )}
                        </p>
                        {vitalsSchedule && vitalsSchedule.is_active && (
                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                Schedule: Every{' '}
                                {vitalsSchedule.interval_minutes} minutes
                            </p>
                        )}
                    </div>
                ) : (
                    <div className="py-4 text-center">
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            No vitals recorded
                        </p>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
