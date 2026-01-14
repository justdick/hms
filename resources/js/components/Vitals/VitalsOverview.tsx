import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Activity,
    AlertTriangle,
    Clock,
    Gauge,
    Heart,
    Plus,
    Thermometer,
} from 'lucide-react';
import VitalCard from './VitalCard';

interface VitalSigns {
    id: number;
    temperature: number;
    blood_pressure_systolic: number;
    blood_pressure_diastolic: number;
    heart_rate: number;
    respiratory_rate: number;
    recorded_at: string;
}

interface VitalsOverviewProps {
    vitals: VitalSigns[];
    onAddVitals?: () => void;
    showAddButton?: boolean;
}

export default function VitalsOverview({
    vitals,
    onAddVitals,
    showAddButton = false,
}: VitalsOverviewProps) {
    const latestVitals = vitals?.[0];

    if (!latestVitals) {
        return (
            <Card>
                <CardHeader className="flex flex-row items-center justify-between">
                    <CardTitle className="flex items-center gap-2">
                        <Activity className="h-5 w-5 text-blue-600" />
                        Vital Signs
                    </CardTitle>
                    {showAddButton && onAddVitals && (
                        <Button onClick={onAddVitals} size="sm">
                            <Plus className="mr-1 h-4 w-4" />
                            Record Vitals
                        </Button>
                    )}
                </CardHeader>
                <CardContent>
                    <div className="py-12 text-center text-gray-500">
                        <Activity className="mx-auto mb-4 h-16 w-16 text-gray-300" />
                        <p className="mb-2 text-lg font-medium">
                            No vital signs recorded
                        </p>
                        <p className="text-sm">
                            Record the patient's first set of vital signs
                        </p>
                        {onAddVitals && (
                            <Button onClick={onAddVitals} className="mt-4">
                                <Plus className="mr-2 h-4 w-4" />
                                Record Vitals
                            </Button>
                        )}
                    </div>
                </CardContent>
            </Card>
        );
    }

    // Calculate BMI if we had height/weight (placeholder for future)
    const calculateBMI = () => {
        // Placeholder - would need height/weight data
        return null;
    };

    // Determine vital status based on normal ranges (Celsius)
    const getTemperatureStatus = (temp: number) => {
        if (temp < 35.5) return 'low';
        if (temp > 38) return 'high';
        if (temp > 37.2) return 'elevated';
        return 'normal';
    };

    const getBPStatus = (systolic: number, diastolic: number) => {
        if (systolic >= 180 || diastolic >= 120) return 'critical';
        if (systolic >= 140 || diastolic >= 90) return 'high';
        if (systolic >= 130 || diastolic >= 80) return 'elevated';
        if (systolic < 90 || diastolic < 60) return 'low';
        return 'normal';
    };

    const getHeartRateStatus = (hr: number) => {
        if (hr > 100) return 'high';
        if (hr < 60) return 'low';
        return 'normal';
    };

    const getRespiratoryRateStatus = (rr: number) => {
        if (rr > 20) return 'high';
        if (rr < 12) return 'low';
        return 'normal';
    };

    // Extract trend data from historical vitals
    const getTrendData = (type: string) => {
        return vitals
            .slice(0, 5)
            .reverse()
            .map((vital) => {
                switch (type) {
                    case 'temperature':
                        return vital.temperature;
                    case 'systolic':
                        return vital.blood_pressure_systolic;
                    case 'diastolic':
                        return vital.blood_pressure_diastolic;
                    case 'heart_rate':
                        return vital.heart_rate;
                    case 'respiratory_rate':
                        return vital.respiratory_rate;
                    default:
                        return 0;
                }
            });
    };

    const formatDateTime = (dateString: string) => {
        return new Date(dateString).toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const tempStatus = getTemperatureStatus(latestVitals.temperature);
    const bpStatus = getBPStatus(
        latestVitals.blood_pressure_systolic,
        latestVitals.blood_pressure_diastolic,
    );
    const hrStatus = getHeartRateStatus(latestVitals.heart_rate);
    const rrStatus = getRespiratoryRateStatus(latestVitals.respiratory_rate);

    const hasAbnormalVitals = [tempStatus, bpStatus, hrStatus, rrStatus].some(
        (status) => status !== 'normal',
    );

    return (
        <div className="space-y-6">
            <Card>
                <CardHeader className="flex flex-row items-center justify-between">
                    <div>
                        <CardTitle className="flex items-center gap-2">
                            <Activity className="h-5 w-5 text-blue-600" />
                            Vital Signs
                            {hasAbnormalVitals && (
                                <Badge
                                    variant="secondary"
                                    className="ml-2 bg-orange-100 text-orange-600"
                                >
                                    <AlertTriangle className="mr-1 h-3 w-3" />
                                    Abnormal Values
                                </Badge>
                            )}
                        </CardTitle>
                        <div className="mt-1 flex items-center gap-2 text-sm text-gray-600">
                            <Clock className="h-4 w-4" />
                            Latest: {formatDateTime(latestVitals.recorded_at)}
                        </div>
                    </div>
                    {showAddButton && onAddVitals && (
                        <Button onClick={onAddVitals} size="sm">
                            <Plus className="mr-1 h-4 w-4" />
                            Record New
                        </Button>
                    )}
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                        <VitalCard
                            title="Temperature"
                            current={latestVitals.temperature.toString()}
                            unit="°C"
                            normalRange="36.1-37.2°C"
                            status={tempStatus}
                            trend={getTrendData('temperature')}
                            lastReading={formatDateTime(
                                latestVitals.recorded_at,
                            )}
                            alert={
                                tempStatus === 'high'
                                    ? 'Fever detected'
                                    : tempStatus === 'low'
                                      ? 'Hypothermia risk'
                                      : undefined
                            }
                            icon={<Thermometer className="h-4 w-4" />}
                        />

                        <VitalCard
                            title="Blood Pressure"
                            current={`${Math.round(latestVitals.blood_pressure_systolic)}/${Math.round(latestVitals.blood_pressure_diastolic)}`}
                            unit="mmHg"
                            normalRange="<120/80"
                            status={bpStatus}
                            trend={getTrendData('systolic')}
                            lastReading={formatDateTime(
                                latestVitals.recorded_at,
                            )}
                            alert={
                                bpStatus === 'critical'
                                    ? 'Hypertensive crisis'
                                    : bpStatus === 'high'
                                      ? 'High blood pressure'
                                      : bpStatus === 'low'
                                        ? 'Low blood pressure'
                                        : undefined
                            }
                            icon={<Gauge className="h-4 w-4" />}
                        />

                        <VitalCard
                            title="Heart Rate"
                            current={latestVitals.heart_rate.toString()}
                            unit="bpm"
                            normalRange="60-100 bpm"
                            status={hrStatus}
                            trend={getTrendData('heart_rate')}
                            lastReading={formatDateTime(
                                latestVitals.recorded_at,
                            )}
                            alert={
                                hrStatus === 'high'
                                    ? 'Tachycardia'
                                    : hrStatus === 'low'
                                      ? 'Bradycardia'
                                      : undefined
                            }
                            icon={<Heart className="h-4 w-4" />}
                        />

                        <VitalCard
                            title="Respiratory Rate"
                            current={latestVitals.respiratory_rate.toString()}
                            unit="/min"
                            normalRange="12-20/min"
                            status={rrStatus}
                            trend={getTrendData('respiratory_rate')}
                            lastReading={formatDateTime(
                                latestVitals.recorded_at,
                            )}
                            alert={
                                rrStatus === 'high'
                                    ? 'Tachypnea'
                                    : rrStatus === 'low'
                                      ? 'Bradypnea'
                                      : undefined
                            }
                            icon={<Activity className="h-4 w-4" />}
                        />
                    </div>

                    {vitals.length > 1 && (
                        <div className="mt-6 border-t border-gray-200 pt-6 dark:border-gray-700">
                            <h4 className="mb-3 text-sm font-medium text-gray-700 dark:text-gray-300">
                                Recent History ({vitals.length} readings)
                            </h4>
                            <div className="max-h-32 space-y-2 overflow-y-auto">
                                {vitals.slice(1, 6).map((vital, index) => (
                                    <div
                                        key={vital.id}
                                        className="flex items-center justify-between rounded bg-gray-50 p-2 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-400"
                                    >
                                        <span>
                                            {formatDateTime(vital.recorded_at)}
                                        </span>
                                        <div className="flex gap-4">
                                            <span>
                                                T: {vital.temperature}°C
                                            </span>
                                            <span>
                                                BP:{' '}
                                                {Math.round(
                                                    vital.blood_pressure_systolic,
                                                )}
                                                /
                                                {Math.round(
                                                    vital.blood_pressure_diastolic,
                                                )}
                                            </span>
                                            <span>HR: {vital.heart_rate}</span>
                                            <span>
                                                RR: {vital.respiratory_rate}
                                            </span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
