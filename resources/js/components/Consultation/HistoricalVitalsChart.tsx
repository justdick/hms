import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    ChartConfig,
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import { VitalSigns } from '@/types';
import { TrendingUp } from 'lucide-react';
import { CartesianGrid, Line, LineChart, XAxis, YAxis } from 'recharts';

interface HistoricalVital extends VitalSigns {
    recorded_by?: {
        id: number;
        name: string;
    };
}

interface Props {
    vitals: HistoricalVital[];
}

export function HistoricalVitalsChart({ vitals }: Props) {
    // Transform vitals data for the chart
    const chartData = vitals
        .slice()
        .reverse() // Show oldest to newest (left to right)
        .map((vital) => ({
            date: new Date(vital.recorded_at).toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            }),
            temperature: vital.temperature
                ? parseFloat(vital.temperature)
                : null,
            heartRate: vital.heart_rate ? parseInt(vital.heart_rate) : null,
            systolic: vital.blood_pressure_systolic
                ? parseInt(vital.blood_pressure_systolic)
                : null,
            diastolic: vital.blood_pressure_diastolic
                ? parseInt(vital.blood_pressure_diastolic)
                : null,
            respiratoryRate: vital.respiratory_rate
                ? parseInt(vital.respiratory_rate)
                : null,
        }));

    const chartConfig = {
        temperature: {
            label: 'Temperature (°C)',
            color: 'hsl(var(--chart-1))',
        },
        heartRate: {
            label: 'Heart Rate (bpm)',
            color: 'hsl(var(--chart-2))',
        },
        systolic: {
            label: 'BP Systolic (mmHg)',
            color: 'hsl(var(--chart-3))',
        },
        diastolic: {
            label: 'BP Diastolic (mmHg)',
            color: 'hsl(var(--chart-4))',
        },
        respiratoryRate: {
            label: 'Respiratory Rate',
            color: 'hsl(var(--chart-5))',
        },
    } satisfies ChartConfig;

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <TrendingUp className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                    Vitals Trends During Admission
                </CardTitle>
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Visual representation of vital signs over time
                </p>
            </CardHeader>
            <CardContent>
                <ChartContainer
                    config={chartConfig}
                    className="h-[400px] w-full"
                >
                    <LineChart
                        data={chartData}
                        margin={{
                            left: 12,
                            right: 12,
                            top: 12,
                            bottom: 12,
                        }}
                    >
                        <CartesianGrid strokeDasharray="3 3" vertical={false} />
                        <XAxis
                            dataKey="date"
                            tickLine={false}
                            axisLine={false}
                            tickMargin={8}
                            angle={-45}
                            textAnchor="end"
                            height={80}
                        />
                        <YAxis
                            tickLine={false}
                            axisLine={false}
                            tickMargin={8}
                        />
                        <ChartTooltip
                            cursor={false}
                            content={<ChartTooltipContent />}
                        />
                        <Line
                            dataKey="temperature"
                            type="monotone"
                            stroke="var(--color-temperature)"
                            strokeWidth={2}
                            dot={{ r: 4 }}
                            connectNulls
                        />
                        <Line
                            dataKey="heartRate"
                            type="monotone"
                            stroke="var(--color-heartRate)"
                            strokeWidth={2}
                            dot={{ r: 4 }}
                            connectNulls
                        />
                        <Line
                            dataKey="systolic"
                            type="monotone"
                            stroke="var(--color-systolic)"
                            strokeWidth={2}
                            dot={{ r: 4 }}
                            connectNulls
                        />
                        <Line
                            dataKey="diastolic"
                            type="monotone"
                            stroke="var(--color-diastolic)"
                            strokeWidth={2}
                            dot={{ r: 4 }}
                            connectNulls
                        />
                        <Line
                            dataKey="respiratoryRate"
                            type="monotone"
                            stroke="var(--color-respiratoryRate)"
                            strokeWidth={2}
                            dot={{ r: 4 }}
                            connectNulls
                        />
                    </LineChart>
                </ChartContainer>
                <div className="mt-4 grid grid-cols-2 gap-4 text-sm md:grid-cols-5">
                    <div className="flex items-center gap-2">
                        <div
                            className="h-3 w-3 rounded-full"
                            style={{
                                backgroundColor: 'hsl(var(--chart-1))',
                            }}
                        />
                        <span className="text-muted-foreground">
                            Temperature
                        </span>
                    </div>
                    <div className="flex items-center gap-2">
                        <div
                            className="h-3 w-3 rounded-full"
                            style={{
                                backgroundColor: 'hsl(var(--chart-2))',
                            }}
                        />
                        <span className="text-muted-foreground">
                            Heart Rate
                        </span>
                    </div>
                    <div className="flex items-center gap-2">
                        <div
                            className="h-3 w-3 rounded-full"
                            style={{
                                backgroundColor: 'hsl(var(--chart-3))',
                            }}
                        />
                        <span className="text-muted-foreground">
                            BP Systolic
                        </span>
                    </div>
                    <div className="flex items-center gap-2">
                        <div
                            className="h-3 w-3 rounded-full"
                            style={{
                                backgroundColor: 'hsl(var(--chart-4))',
                            }}
                        />
                        <span className="text-muted-foreground">
                            BP Diastolic
                        </span>
                    </div>
                    <div className="flex items-center gap-2">
                        <div
                            className="h-3 w-3 rounded-full"
                            style={{
                                backgroundColor: 'hsl(var(--chart-5))',
                            }}
                        />
                        <span className="text-muted-foreground">
                            Respiratory Rate
                        </span>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
