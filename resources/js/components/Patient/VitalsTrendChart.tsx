import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
    type ChartConfig,
} from '@/components/ui/chart';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Activity,
    Droplets,
    Heart,
    Thermometer,
    TrendingDown,
    TrendingUp,
    Weight,
    Wind,
} from 'lucide-react';
import { useMemo } from 'react';
import { CartesianGrid, Line, LineChart, XAxis, YAxis } from 'recharts';

interface VitalSignData {
    id: number;
    recorded_at: string | null;
    recorded_by?: string | null;
    blood_pressure?: string | null;
    blood_pressure_systolic?: number | null;
    blood_pressure_diastolic?: number | null;
    temperature?: number | null;
    pulse_rate?: number | null;
    respiratory_rate?: number | null;
    oxygen_saturation?: number | null;
    blood_sugar?: number | null;
    weight?: number | null;
    height?: number | null;
    bmi?: number | null;
}

interface Props {
    vitals: VitalSignData[];
}

interface ChartDataPoint {
    date: string;
    value: number | undefined;
}

interface BPChartDataPoint {
    date: string;
    systolic: number | undefined;
    diastolic: number | undefined;
}

type VitalTab =
    | 'temperature'
    | 'bloodPressure'
    | 'pulse'
    | 'respiratory'
    | 'oxygen'
    | 'bloodSugar'
    | 'weight';

interface VitalTabConfig {
    key: VitalTab;
    label: string;
    unit: string;
    icon: React.ReactNode;
    color: string;
    tabColor: string;
    tabClassName: string;
}

const VITAL_TABS: VitalTabConfig[] = [
    {
        key: 'temperature',
        label: 'Temperature',
        unit: '°C',
        icon: <Thermometer className="h-3.5 w-3.5" />,
        color: 'var(--chart-1)',
        tabColor: 'hsl(var(--chart-1))',
        tabClassName:
            'bg-orange-50 text-orange-700 hover:bg-orange-100 data-[state=active]:border-orange-600 data-[state=active]:bg-orange-100 data-[state=active]:text-orange-700 dark:bg-orange-950 dark:text-orange-300 dark:hover:bg-orange-900 dark:data-[state=active]:border-orange-400 dark:data-[state=active]:bg-orange-900 dark:data-[state=active]:text-orange-300',
    },
    {
        key: 'bloodPressure',
        label: 'Blood Pressure',
        unit: 'mmHg',
        icon: <Heart className="h-3.5 w-3.5" />,
        color: 'hsl(0, 84%, 60%)',
        tabColor: 'hsl(0, 84%, 60%)',
        tabClassName:
            'bg-red-50 text-red-700 hover:bg-red-100 data-[state=active]:border-red-600 data-[state=active]:bg-red-100 data-[state=active]:text-red-700 dark:bg-red-950 dark:text-red-300 dark:hover:bg-red-900 dark:data-[state=active]:border-red-400 dark:data-[state=active]:bg-red-900 dark:data-[state=active]:text-red-300',
    },
    {
        key: 'pulse',
        label: 'Pulse Rate',
        unit: 'bpm',
        icon: <Activity className="h-3.5 w-3.5" />,
        color: 'var(--chart-2)',
        tabColor: 'hsl(var(--chart-2))',
        tabClassName:
            'bg-blue-50 text-blue-700 hover:bg-blue-100 data-[state=active]:border-blue-600 data-[state=active]:bg-blue-100 data-[state=active]:text-blue-700 dark:bg-blue-950 dark:text-blue-300 dark:hover:bg-blue-900 dark:data-[state=active]:border-blue-400 dark:data-[state=active]:bg-blue-900 dark:data-[state=active]:text-blue-300',
    },
    {
        key: 'respiratory',
        label: 'Respiratory Rate',
        unit: '/min',
        icon: <Wind className="h-3.5 w-3.5" />,
        color: 'var(--chart-3)',
        tabColor: 'hsl(var(--chart-3))',
        tabClassName:
            'bg-teal-50 text-teal-700 hover:bg-teal-100 data-[state=active]:border-teal-600 data-[state=active]:bg-teal-100 data-[state=active]:text-teal-700 dark:bg-teal-950 dark:text-teal-300 dark:hover:bg-teal-900 dark:data-[state=active]:border-teal-400 dark:data-[state=active]:bg-teal-900 dark:data-[state=active]:text-teal-300',
    },
    {
        key: 'oxygen',
        label: 'Oxygen Saturation',
        unit: '%',
        icon: <Droplets className="h-3.5 w-3.5" />,
        color: 'var(--chart-4)',
        tabColor: 'hsl(var(--chart-4))',
        tabClassName:
            'bg-purple-50 text-purple-700 hover:bg-purple-100 data-[state=active]:border-purple-600 data-[state=active]:bg-purple-100 data-[state=active]:text-purple-700 dark:bg-purple-950 dark:text-purple-300 dark:hover:bg-purple-900 dark:data-[state=active]:border-purple-400 dark:data-[state=active]:bg-purple-900 dark:data-[state=active]:text-purple-300',
    },
    {
        key: 'bloodSugar',
        label: 'Blood Sugar',
        unit: 'mmol/L',
        icon: <Droplets className="h-3.5 w-3.5" />,
        color: 'var(--chart-5)',
        tabColor: 'hsl(var(--chart-5))',
        tabClassName:
            'bg-amber-50 text-amber-700 hover:bg-amber-100 data-[state=active]:border-amber-600 data-[state=active]:bg-amber-100 data-[state=active]:text-amber-700 dark:bg-amber-950 dark:text-amber-300 dark:hover:bg-amber-900 dark:data-[state=active]:border-amber-400 dark:data-[state=active]:bg-amber-900 dark:data-[state=active]:text-amber-300',
    },
    {
        key: 'weight',
        label: 'Weight',
        unit: 'kg',
        icon: <Weight className="h-3.5 w-3.5" />,
        color: 'var(--chart-1)',
        tabColor: 'hsl(var(--chart-1))',
        tabClassName:
            'bg-green-50 text-green-700 hover:bg-green-100 data-[state=active]:border-green-600 data-[state=active]:bg-green-100 data-[state=active]:text-green-700 dark:bg-green-950 dark:text-green-300 dark:hover:bg-green-900 dark:data-[state=active]:border-green-400 dark:data-[state=active]:bg-green-900 dark:data-[state=active]:text-green-300',
    },
];

function formatDateTime(dateString: string | null): string {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

function extractBPValues(vital: VitalSignData): {
    systolic: number | undefined;
    diastolic: number | undefined;
} {
    if (vital.blood_pressure_systolic && vital.blood_pressure_diastolic) {
        return {
            systolic: vital.blood_pressure_systolic,
            diastolic: vital.blood_pressure_diastolic,
        };
    }
    if (vital.blood_pressure && vital.blood_pressure !== 'N/A') {
        const parts = vital.blood_pressure.split('/');
        if (parts.length === 2) {
            const sys = parseInt(parts[0]);
            const dia = parseInt(parts[1]);
            if (!isNaN(sys) && !isNaN(dia)) {
                return { systolic: sys, diastolic: dia };
            }
        }
    }
    return { systolic: undefined, diastolic: undefined };
}

function SingleVitalChart({
    data,
    dataKey,
    color,
    unit,
}: {
    data: ChartDataPoint[];
    dataKey: string;
    color: string;
    unit: string;
}) {
    const validData = data.filter(
        (d) => d.value !== undefined && d.value !== null,
    );

    if (validData.length === 0) {
        return (
            <div className="flex h-[200px] items-center justify-center">
                <p className="text-sm text-muted-foreground">
                    No data recorded
                </p>
            </div>
        );
    }

    const chartConfig = {
        [dataKey]: {
            label: `Value (${unit})`,
            color: color,
        },
    } satisfies ChartConfig;

    const values = validData.map((d) => d.value!);
    const minValue = Math.min(...values);
    const maxValue = Math.max(...values);
    const padding = (maxValue - minValue) * 0.1 || 5;
    const yMin = Math.floor(minValue - padding);
    const yMax = Math.ceil(maxValue + padding);

    const trend = useMemo(() => {
        if (validData.length < 2) return null;
        const first = validData[0].value!;
        const last = validData[validData.length - 1].value!;
        const change = last - first;
        const percent = Math.abs((change / first) * 100).toFixed(1);
        return {
            direction: change > 0 ? 'up' : change < 0 ? 'down' : 'stable',
            percent,
        };
    }, [validData]);

    const latestValue = validData[validData.length - 1]?.value;

    return (
        <div className="space-y-2">
            <ChartContainer config={chartConfig} className="h-[200px] w-full">
                <LineChart
                    accessibilityLayer
                    data={validData}
                    margin={{ top: 20, left: 12, right: 12, bottom: 5 }}
                >
                    <CartesianGrid vertical={false} />
                    <XAxis
                        dataKey="date"
                        tickLine={false}
                        axisLine={false}
                        tickMargin={8}
                        tickFormatter={(value) => {
                            const parts = value.split(',');
                            return parts[0];
                        }}
                    />
                    <YAxis
                        domain={[yMin, yMax]}
                        tickLine={false}
                        axisLine={false}
                        tickMargin={8}
                        width={40}
                        tickFormatter={(value) => value.toFixed(0)}
                    />
                    <ChartTooltip
                        cursor={false}
                        content={<ChartTooltipContent indicator="line" />}
                    />
                    <Line
                        dataKey="value"
                        type="natural"
                        stroke={`var(--color-${dataKey})`}
                        strokeWidth={2}
                        dot={{
                            fill: `var(--color-${dataKey})`,
                        }}
                        activeDot={{
                            r: 6,
                        }}
                    />
                </LineChart>
            </ChartContainer>
            <div className="flex items-center justify-between px-1 text-sm text-muted-foreground">
                <span>
                    Latest:{' '}
                    <span className="font-medium text-foreground">
                        {latestValue} {unit}
                    </span>
                </span>
                <div className="flex items-center gap-2">
                    {trend && trend.direction !== 'stable' && (
                        <span className="flex items-center gap-1">
                            {trend.direction === 'up' ? (
                                <TrendingUp className="h-3.5 w-3.5" />
                            ) : (
                                <TrendingDown className="h-3.5 w-3.5" />
                            )}
                            {trend.percent}%
                        </span>
                    )}
                    <span>
                        {validData.length} reading
                        {validData.length !== 1 ? 's' : ''}
                    </span>
                </div>
            </div>
        </div>
    );
}

function BPChart({ data }: { data: BPChartDataPoint[] }) {
    const validData = data.filter(
        (d) => d.systolic !== undefined || d.diastolic !== undefined,
    );

    if (validData.length === 0) {
        return (
            <div className="flex h-[200px] items-center justify-center">
                <p className="text-sm text-muted-foreground">
                    No data recorded
                </p>
            </div>
        );
    }

    const chartConfig = {
        systolic: {
            label: 'Systolic (mmHg)',
            color: 'hsl(0, 84%, 60%)',
        },
        diastolic: {
            label: 'Diastolic (mmHg)',
            color: 'hsl(221, 83%, 53%)',
        },
    } satisfies ChartConfig;

    const allValues = validData.flatMap((d) =>
        [d.systolic, d.diastolic].filter(
            (v) => v !== undefined && v !== null,
        ) as number[],
    );
    const minValue = Math.min(...allValues);
    const maxValue = Math.max(...allValues);
    const padding = (maxValue - minValue) * 0.1 || 10;
    const yMin = Math.floor(minValue - padding);
    const yMax = Math.ceil(maxValue + padding);

    const latestSystolic = validData[validData.length - 1]?.systolic;
    const latestDiastolic = validData[validData.length - 1]?.diastolic;

    return (
        <div className="space-y-2">
            <ChartContainer config={chartConfig} className="h-[200px] w-full">
                <LineChart
                    accessibilityLayer
                    data={validData}
                    margin={{ top: 20, left: 12, right: 12, bottom: 5 }}
                >
                    <CartesianGrid vertical={false} />
                    <XAxis
                        dataKey="date"
                        tickLine={false}
                        axisLine={false}
                        tickMargin={8}
                        tickFormatter={(value) => {
                            const parts = value.split(',');
                            return parts[0];
                        }}
                    />
                    <YAxis
                        domain={[yMin, yMax]}
                        tickLine={false}
                        axisLine={false}
                        tickMargin={8}
                        width={40}
                        tickFormatter={(value) => value.toFixed(0)}
                    />
                    <ChartTooltip
                        cursor={false}
                        content={<ChartTooltipContent indicator="line" />}
                    />
                    <Line
                        dataKey="systolic"
                        type="natural"
                        stroke="var(--color-systolic)"
                        strokeWidth={2}
                        dot={{
                            fill: 'var(--color-systolic)',
                        }}
                        activeDot={{
                            r: 6,
                        }}
                    />
                    <Line
                        dataKey="diastolic"
                        type="natural"
                        stroke="var(--color-diastolic)"
                        strokeWidth={2}
                        dot={{
                            fill: 'var(--color-diastolic)',
                        }}
                        activeDot={{
                            r: 6,
                        }}
                    />
                </LineChart>
            </ChartContainer>
            <div className="flex items-center justify-between px-1 text-sm text-muted-foreground">
                <span>
                    Latest:{' '}
                    <span className="font-medium text-foreground">
                        {latestSystolic ?? '-'}/{latestDiastolic ?? '-'} mmHg
                    </span>
                </span>
                <div className="flex items-center gap-2">
                    <div className="flex items-center gap-1">
                        <div
                            className="h-2.5 w-2.5 rounded-full"
                            style={{ backgroundColor: 'hsl(0, 84%, 60%)' }}
                        />
                        <span className="text-xs">Sys</span>
                    </div>
                    <div className="flex items-center gap-1">
                        <div
                            className="h-2.5 w-2.5 rounded-full"
                            style={{ backgroundColor: 'hsl(221, 83%, 53%)' }}
                        />
                        <span className="text-xs">Dia</span>
                    </div>
                    <span>
                        {validData.length} reading
                        {validData.length !== 1 ? 's' : ''}
                    </span>
                </div>
            </div>
        </div>
    );
}

export function VitalsTrendChart({ vitals }: Props) {
    const sortedVitals = useMemo(() => {
        return [...vitals].sort((a, b) => {
            const dateA = a.recorded_at ? new Date(a.recorded_at).getTime() : 0;
            const dateB = b.recorded_at ? new Date(b.recorded_at).getTime() : 0;
            return dateA - dateB;
        });
    }, [vitals]);

    const chartData = useMemo(() => {
        const temperature: ChartDataPoint[] = sortedVitals.map((v) => ({
            date: formatDateTime(v.recorded_at),
            value: v.temperature
                ? parseFloat(String(v.temperature))
                : undefined,
        }));

        const bp: BPChartDataPoint[] = sortedVitals.map((v) => {
            const { systolic, diastolic } = extractBPValues(v);
            return {
                date: formatDateTime(v.recorded_at),
                systolic,
                diastolic,
            };
        });

        const pulse: ChartDataPoint[] = sortedVitals.map((v) => ({
            date: formatDateTime(v.recorded_at),
            value: v.pulse_rate ? parseInt(String(v.pulse_rate)) : undefined,
        }));

        const respiratory: ChartDataPoint[] = sortedVitals.map((v) => ({
            date: formatDateTime(v.recorded_at),
            value: v.respiratory_rate
                ? parseInt(String(v.respiratory_rate))
                : undefined,
        }));

        const oxygen: ChartDataPoint[] = sortedVitals.map((v) => ({
            date: formatDateTime(v.recorded_at),
            value: v.oxygen_saturation
                ? parseInt(String(v.oxygen_saturation))
                : undefined,
        }));

        const bloodSugar: ChartDataPoint[] = sortedVitals.map((v) => ({
            date: formatDateTime(v.recorded_at),
            value: v.blood_sugar
                ? parseFloat(String(v.blood_sugar))
                : undefined,
        }));

        const weight: ChartDataPoint[] = sortedVitals.map((v) => ({
            date: formatDateTime(v.recorded_at),
            value: v.weight ? parseInt(String(v.weight)) : undefined,
        }));

        return {
            temperature,
            bp,
            pulse,
            respiratory,
            oxygen,
            bloodSugar,
            weight,
        };
    }, [sortedVitals]);

    const tabsWithData = VITAL_TABS;

    if (vitals.length === 0) {
        return null;
    }

    const defaultTab = tabsWithData[0]?.key || 'temperature';

    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="flex items-center gap-2 text-sm font-medium">
                    <TrendingUp className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                    Vitals Trend
                </CardTitle>
                <CardDescription className="text-xs">
                    Trends over the course of this admission
                </CardDescription>
            </CardHeader>
            <CardContent className="pb-3">
                <Tabs defaultValue={defaultTab}>
                    <TabsList className="mb-3 h-auto w-full flex-wrap justify-start gap-1 bg-transparent p-1">
                        {tabsWithData.map((tab) => (
                            <TabsTrigger
                                key={tab.key}
                                value={tab.key}
                                className={`flex items-center gap-1.5 rounded-md border-b-2 border-transparent px-3 py-1.5 text-xs shadow-none transition-all data-[state=active]:shadow-none ${tab.tabClassName}`}
                            >
                                {tab.icon}
                                {tab.label}
                            </TabsTrigger>
                        ))}
                    </TabsList>

                    <TabsContent value="temperature">
                        <SingleVitalChart
                            data={chartData.temperature}
                            dataKey="temperature"
                            color="var(--chart-1)"
                            unit="°C"
                        />
                    </TabsContent>

                    <TabsContent value="bloodPressure">
                        <BPChart data={chartData.bp} />
                    </TabsContent>

                    <TabsContent value="pulse">
                        <SingleVitalChart
                            data={chartData.pulse}
                            dataKey="pulse"
                            color="var(--chart-2)"
                            unit="bpm"
                        />
                    </TabsContent>

                    <TabsContent value="respiratory">
                        <SingleVitalChart
                            data={chartData.respiratory}
                            dataKey="respiratory"
                            color="var(--chart-3)"
                            unit="/min"
                        />
                    </TabsContent>

                    <TabsContent value="oxygen">
                        <SingleVitalChart
                            data={chartData.oxygen}
                            dataKey="oxygen"
                            color="var(--chart-4)"
                            unit="%"
                        />
                    </TabsContent>

                    <TabsContent value="bloodSugar">
                        <SingleVitalChart
                            data={chartData.bloodSugar}
                            dataKey="bloodSugar"
                            color="var(--chart-5)"
                            unit="mmol/L"
                        />
                    </TabsContent>

                    <TabsContent value="weight">
                        <SingleVitalChart
                            data={chartData.weight}
                            dataKey="weight"
                            color="var(--chart-1)"
                            unit="kg"
                        />
                    </TabsContent>
                </Tabs>
            </CardContent>
        </Card>
    );
}
