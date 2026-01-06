import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Activity,
    Droplets,
    Heart,
    Ruler,
    Thermometer,
    Weight,
    Wind,
} from 'lucide-react';

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

interface Props {
    vitalSign: VitalSign;
}

export function ConsultationVitalsCard({ vitalSign }: Props) {
    const formatDateTime = (dateString: string) => {
        return new Date(dateString).toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const vitalItems = [
        {
            icon: Thermometer,
            label: 'Temperature',
            value: vitalSign.temperature,
            unit: '°C',
            show:
                vitalSign.temperature !== undefined &&
                vitalSign.temperature !== null,
        },
        {
            icon: Heart,
            label: 'Blood Pressure',
            value:
                vitalSign.blood_pressure_systolic &&
                vitalSign.blood_pressure_diastolic
                    ? `${Math.round(vitalSign.blood_pressure_systolic)}/${Math.round(vitalSign.blood_pressure_diastolic)}`
                    : null,
            unit: 'mmHg',
            show:
                vitalSign.blood_pressure_systolic !== undefined &&
                vitalSign.blood_pressure_systolic !== null &&
                vitalSign.blood_pressure_diastolic !== undefined &&
                vitalSign.blood_pressure_diastolic !== null,
        },
        {
            icon: Activity,
            label: 'Pulse Rate',
            value: vitalSign.pulse_rate,
            unit: 'bpm',
            show:
                vitalSign.pulse_rate !== undefined &&
                vitalSign.pulse_rate !== null,
        },
        {
            icon: Wind,
            label: 'Respiratory Rate',
            value: vitalSign.respiratory_rate,
            unit: '/min',
            show:
                vitalSign.respiratory_rate !== undefined &&
                vitalSign.respiratory_rate !== null,
        },
        {
            icon: Droplets,
            label: 'Oxygen Saturation',
            value: vitalSign.oxygen_saturation,
            unit: '%',
            show:
                vitalSign.oxygen_saturation !== undefined &&
                vitalSign.oxygen_saturation !== null,
        },
        {
            icon: Weight,
            label: 'Weight',
            value: vitalSign.weight,
            unit: 'kg',
            show: vitalSign.weight !== undefined && vitalSign.weight !== null,
        },
        {
            icon: Ruler,
            label: 'Height',
            value: vitalSign.height,
            unit: 'cm',
            show: vitalSign.height !== undefined && vitalSign.height !== null,
        },
    ];

    const displayedVitals = vitalItems.filter((item) => item.show);

    return (
        <Card className="border-blue-200 dark:border-blue-800">
            <CardHeader>
                <div className="flex items-center justify-between">
                    <CardTitle className="flex items-center gap-2">
                        <Activity className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                        Vitals from Admission Consultation
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
                {displayedVitals.length > 0 ? (
                    <div className="space-y-4">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {displayedVitals.map((item, index) => {
                                const Icon = item.icon;
                                return (
                                    <div
                                        key={index}
                                        className="flex items-start gap-3 rounded-lg border p-3 dark:border-gray-700"
                                    >
                                        <div className="rounded-full bg-blue-100 p-2 dark:bg-blue-900">
                                            <Icon className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                        </div>
                                        <div className="flex-1">
                                            <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                                {item.label}
                                            </p>
                                            <p className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                                {item.value} {item.unit}
                                            </p>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>

                        <div className="mt-4 flex items-center justify-between border-t pt-4 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-400">
                            <div>
                                <span className="font-medium">Recorded:</span>{' '}
                                {formatDateTime(vitalSign.recorded_at)}
                            </div>
                            {vitalSign.recorded_by && (
                                <div>
                                    <span className="font-medium">By:</span>{' '}
                                    {vitalSign.recorded_by.name}
                                </div>
                            )}
                        </div>
                    </div>
                ) : (
                    <div className="py-8 text-center">
                        <Activity className="mx-auto mb-3 h-12 w-12 text-gray-300 dark:text-gray-600" />
                        <p className="text-gray-600 dark:text-gray-400">
                            No vitals were recorded during the admission
                            consultation
                        </p>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
