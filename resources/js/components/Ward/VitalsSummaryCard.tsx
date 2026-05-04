import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDistanceToNow } from 'date-fns';
import { AlertCircle, Heart } from 'lucide-react';

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
    blood_sugar?: number;
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
    vitals: VitalSign[];
    vitalsSchedule?: VitalsSchedule;
    onClick: () => void;
}

function formatBP(vital: VitalSign): string | null {
    if (vital.blood_pressure_systolic && vital.blood_pressure_diastolic) {
        return `${Math.round(vital.blood_pressure_systolic)}/${Math.round(vital.blood_pressure_diastolic)}`;
    }
    return null;
}

export function VitalsSummaryCard({
    vitals,
    vitalsSchedule,
    onClick,
}: Props) {
    const latestVital = vitals[0] ?? null;

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
                {vitals.length > 0 ? (
                    <div className="space-y-2">
                        {/* Scrollable table with all readings */}
                        <div className="max-h-52 overflow-auto rounded-lg border dark:border-gray-700">
                            <table className="w-full text-xs">
                                <thead className="sticky top-0 z-10">
                                    <tr className="border-b bg-gray-50 dark:border-gray-700 dark:bg-gray-800/50">
                                        <th className="px-2 py-1.5 text-left font-medium text-gray-500 dark:text-gray-400">
                                            When
                                        </th>
                                        <th className="px-2 py-1.5 text-left font-medium text-gray-500 dark:text-gray-400">
                                            Temp
                                        </th>
                                        <th className="px-2 py-1.5 text-left font-medium text-gray-500 dark:text-gray-400">
                                            BP
                                        </th>
                                        <th className="px-2 py-1.5 text-left font-medium text-gray-500 dark:text-gray-400">
                                            Pulse
                                        </th>
                                        <th className="px-2 py-1.5 text-left font-medium text-gray-500 dark:text-gray-400">
                                            SpO₂
                                        </th>
                                        <th className="px-2 py-1.5 text-left font-medium text-gray-500 dark:text-gray-400">
                                            Wt
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {vitals.map((vital, index) => (
                                        <tr
                                            key={vital.id}
                                            className={
                                                index !== vitals.length - 1
                                                    ? 'border-b dark:border-gray-700'
                                                    : ''
                                            }
                                        >
                                            <td className="whitespace-nowrap px-2 py-1.5 text-gray-600 dark:text-gray-400">
                                                {formatDistanceToNow(
                                                    new Date(vital.recorded_at),
                                                    { addSuffix: true },
                                                )
                                                    .replace('about ', '~')
                                                    .replace(' minutes', 'm')
                                                    .replace(' minute', 'm')
                                                    .replace(' hours', 'h')
                                                    .replace(' hour', 'h')
                                                    .replace(' days', 'd')
                                                    .replace(' day', 'd')}
                                            </td>
                                            <td className="px-2 py-1.5 font-medium text-gray-900 dark:text-gray-100">
                                                {vital.temperature
                                                    ? `${vital.temperature}°`
                                                    : '—'}
                                            </td>
                                            <td className="px-2 py-1.5 font-medium text-gray-900 dark:text-gray-100">
                                                {formatBP(vital) ?? '—'}
                                            </td>
                                            <td className="px-2 py-1.5 font-medium text-gray-900 dark:text-gray-100">
                                                {vital.pulse_rate ?? '—'}
                                            </td>
                                            <td className="px-2 py-1.5 font-medium text-gray-900 dark:text-gray-100">
                                                {vital.oxygen_saturation
                                                    ? `${vital.oxygen_saturation}%`
                                                    : '—'}
                                            </td>
                                            <td className="px-2 py-1.5 font-medium text-gray-900 dark:text-gray-100">
                                                {vital.weight
                                                    ? `${vital.weight}kg`
                                                    : '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        {vitalsSchedule && vitalsSchedule.is_active && (
                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                Schedule: Every{' '}
                                {vitalsSchedule.interval_minutes} min
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
