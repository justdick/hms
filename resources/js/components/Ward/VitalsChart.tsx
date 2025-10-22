import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
    type ChartConfig,
} from '@/components/ui/chart';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Activity,
    Filter,
    Heart,
    Thermometer,
    TrendingDown,
    TrendingUp,
} from 'lucide-react';
import { useState } from 'react';
import { CartesianGrid, LabelList, Line, LineChart, XAxis } from 'recharts';

interface VitalSign {
    id: number;
    temperature?: number;
    blood_pressure_systolic?: number;
    blood_pressure_diastolic?: number;
    pulse_rate?: number;
    respiratory_rate?: number;
    oxygen_saturation?: number;
    recorded_at: string;
}

interface Props {
    vitals: VitalSign[];
}

type TimeRange = '24h' | '48h' | '7d' | '30d' | 'all';

interface VitalTypeFilter {
    temperature: boolean;
    bloodPressure: boolean;
    pulse: boolean;
    respiratory: boolean;
    oxygen: boolean;
}

interface IndividualChartProps {
    data: Array<{ date: string; value: number | undefined }>;
    dataKey: string;
    label: string;
    color: string;
    unit: string;
    icon: React.ReactNode;
}

function IndividualVitalChart({
    data,
    dataKey,
    label,
    color,
    unit,
    icon,
}: IndividualChartProps) {
    const chartConfig = {
        [dataKey]: {
            label: `${label}`,
            color: color,
        },
    } satisfies ChartConfig;

    // Filter out data points with no value
    const hasData = data.some((d) => d.value !== undefined && d.value !== null);
    const validData = data.filter(
        (d) => d.value !== undefined && d.value !== null,
    );

    // Calculate trend
    const calculateTrend = () => {
        if (validData.length < 2) {
            return null;
        }
        const firstValue = validData[0].value!;
        const lastValue = validData[validData.length - 1].value!;
        const change = lastValue - firstValue;
        const percentChange = (change / firstValue) * 100;
        return {
            direction: change > 0 ? 'up' : change < 0 ? 'down' : 'stable',
            percent: Math.abs(percentChange).toFixed(1),
            value: Math.abs(change).toFixed(1),
        };
    };

    const trend = calculateTrend();

    if (!hasData) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        {icon}
                        {label}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="flex h-[200px] items-center justify-center">
                        <p className="text-sm text-muted-foreground">
                            No data recorded
                        </p>
                    </div>
                </CardContent>
            </Card>
        );
    }

    const latestValue = validData[validData.length - 1]?.value;
    const dateRange =
        validData.length >= 2
            ? `${validData[0].date} - ${validData[validData.length - 1].date}`
            : validData[0]?.date || '';

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    {icon}
                    {label}
                </CardTitle>
                <CardDescription>{dateRange}</CardDescription>
            </CardHeader>
            <CardContent>
                <ChartContainer config={chartConfig}>
                    <LineChart
                        accessibilityLayer
                        data={data}
                        margin={{
                            top: 20,
                            left: 12,
                            right: 12,
                        }}
                    >
                        <CartesianGrid vertical={false} />
                        <XAxis
                            dataKey="date"
                            tickLine={false}
                            axisLine={false}
                            tickMargin={8}
                            tickFormatter={(value) => {
                                // Show only time for short dates
                                const parts = value.split(',');
                                return parts[0];
                            }}
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
                        >
                            <LabelList
                                position="top"
                                offset={12}
                                className="fill-foreground"
                                fontSize={12}
                            />
                        </Line>
                    </LineChart>
                </ChartContainer>
            </CardContent>
            <CardFooter className="flex-col items-start gap-2 text-sm">
                {trend && trend.direction !== 'stable' && (
                    <div className="flex gap-2 leading-none font-medium">
                        {trend.direction === 'up'
                            ? 'Trending up'
                            : 'Trending down'}{' '}
                        by {trend.percent}% this period{' '}
                        {trend.direction === 'up' ? (
                            <TrendingUp className="h-4 w-4" />
                        ) : (
                            <TrendingDown className="h-4 w-4" />
                        )}
                    </div>
                )}
                <div className="leading-none text-muted-foreground">
                    Latest reading: {latestValue} {unit}
                </div>
            </CardFooter>
        </Card>
    );
}

export function VitalsChart({ vitals }: Props) {
    const [timeRange, setTimeRange] = useState<TimeRange>('7d');
    const [vitalTypeFilter, setVitalTypeFilter] = useState<VitalTypeFilter>({
        temperature: true,
        bloodPressure: true,
        pulse: true,
        respiratory: true,
        oxygen: true,
    });

    const formatDateTime = (dateString: string) => {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
        });
    };

    // Filter vitals based on selected time range
    const filterVitalsByTimeRange = (vitalsData: VitalSign[]) => {
        if (timeRange === 'all') return vitalsData;

        const now = new Date();
        const timeRangeMs: Record<TimeRange, number> = {
            '24h': 24 * 60 * 60 * 1000,
            '48h': 48 * 60 * 60 * 1000,
            '7d': 7 * 24 * 60 * 60 * 1000,
            '30d': 30 * 24 * 60 * 60 * 1000,
            all: 0,
        };

        const cutoffTime = new Date(now.getTime() - timeRangeMs[timeRange]);
        return vitalsData.filter(
            (vital) => new Date(vital.recorded_at) >= cutoffTime,
        );
    };

    const toggleVitalType = (type: keyof VitalTypeFilter) => {
        setVitalTypeFilter((prev) => ({
            ...prev,
            [type]: !prev[type],
        }));
    };

    const getTimeRangeLabel = (range: TimeRange) => {
        const labels: Record<TimeRange, string> = {
            '24h': 'Last 24 Hours',
            '48h': 'Last 48 Hours',
            '7d': 'Last 7 Days',
            '30d': 'Last 30 Days',
            all: 'All Time',
        };
        return labels[range];
    };

    if (vitals.length === 0) {
        return (
            <div className="flex h-full items-center justify-center py-12">
                <div className="text-center">
                    <Thermometer className="mx-auto mb-4 h-12 w-12 text-gray-300 dark:text-gray-600" />
                    <p className="text-gray-500 dark:text-gray-400">
                        No vital signs to display
                    </p>
                </div>
            </div>
        );
    }

    const filteredVitals = filterVitalsByTimeRange(vitals);

    // Transform vitals data for the charts (reverse to show oldest to newest)
    const reversedVitals = [...filteredVitals].reverse();

    const temperatureData = reversedVitals.map((vital) => ({
        date: formatDateTime(vital.recorded_at),
        value: vital.temperature,
    }));

    const systolicData = reversedVitals.map((vital) => ({
        date: formatDateTime(vital.recorded_at),
        value: vital.blood_pressure_systolic,
    }));

    const diastolicData = reversedVitals.map((vital) => ({
        date: formatDateTime(vital.recorded_at),
        value: vital.blood_pressure_diastolic,
    }));

    const pulseData = reversedVitals.map((vital) => ({
        date: formatDateTime(vital.recorded_at),
        value: vital.pulse_rate,
    }));

    const respiratoryData = reversedVitals.map((vital) => ({
        date: formatDateTime(vital.recorded_at),
        value: vital.respiratory_rate,
    }));

    const oxygenData = reversedVitals.map((vital) => ({
        date: formatDateTime(vital.recorded_at),
        value: vital.oxygen_saturation,
    }));

    return (
        <div className="space-y-4">
            {/* Filter Controls */}
            <div className="flex flex-wrap items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
                <div className="flex items-center gap-2">
                    <Label className="text-sm font-medium">Time Range:</Label>
                    <Select
                        value={timeRange}
                        onValueChange={(value) =>
                            setTimeRange(value as TimeRange)
                        }
                    >
                        <SelectTrigger className="w-[160px]">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="24h">Last 24 Hours</SelectItem>
                            <SelectItem value="48h">Last 48 Hours</SelectItem>
                            <SelectItem value="7d">Last 7 Days</SelectItem>
                            <SelectItem value="30d">Last 30 Days</SelectItem>
                            <SelectItem value="all">All Time</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div className="flex items-center gap-2">
                    <Label className="text-sm font-medium">Show:</Label>
                    <Popover>
                        <PopoverTrigger asChild>
                            <Button variant="outline" size="sm">
                                <Filter className="mr-2 h-4 w-4" />
                                Vital Signs
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent className="w-64">
                            <div className="space-y-3">
                                <h4 className="font-medium">
                                    Select Vital Signs
                                </h4>
                                <div className="space-y-2">
                                    <div className="flex items-center space-x-2">
                                        <Checkbox
                                            id="temperature"
                                            checked={
                                                vitalTypeFilter.temperature
                                            }
                                            onCheckedChange={() =>
                                                toggleVitalType('temperature')
                                            }
                                        />
                                        <Label
                                            htmlFor="temperature"
                                            className="flex cursor-pointer items-center gap-2 text-sm font-normal"
                                        >
                                            <Thermometer className="h-4 w-4 text-chart-1" />
                                            Temperature
                                        </Label>
                                    </div>
                                    <div className="flex items-center space-x-2">
                                        <Checkbox
                                            id="bloodPressure"
                                            checked={
                                                vitalTypeFilter.bloodPressure
                                            }
                                            onCheckedChange={() =>
                                                toggleVitalType('bloodPressure')
                                            }
                                        />
                                        <Label
                                            htmlFor="bloodPressure"
                                            className="flex cursor-pointer items-center gap-2 text-sm font-normal"
                                        >
                                            <Heart className="h-4 w-4 text-chart-2" />
                                            Blood Pressure
                                        </Label>
                                    </div>
                                    <div className="flex items-center space-x-2">
                                        <Checkbox
                                            id="pulse"
                                            checked={vitalTypeFilter.pulse}
                                            onCheckedChange={() =>
                                                toggleVitalType('pulse')
                                            }
                                        />
                                        <Label
                                            htmlFor="pulse"
                                            className="flex cursor-pointer items-center gap-2 text-sm font-normal"
                                        >
                                            <Activity className="h-4 w-4 text-chart-4" />
                                            Pulse Rate
                                        </Label>
                                    </div>
                                    <div className="flex items-center space-x-2">
                                        <Checkbox
                                            id="respiratory"
                                            checked={
                                                vitalTypeFilter.respiratory
                                            }
                                            onCheckedChange={() =>
                                                toggleVitalType('respiratory')
                                            }
                                        />
                                        <Label
                                            htmlFor="respiratory"
                                            className="flex cursor-pointer items-center gap-2 text-sm font-normal"
                                        >
                                            <Activity className="h-4 w-4 text-chart-5" />
                                            Respiratory Rate
                                        </Label>
                                    </div>
                                    <div className="flex items-center space-x-2">
                                        <Checkbox
                                            id="oxygen"
                                            checked={vitalTypeFilter.oxygen}
                                            onCheckedChange={() =>
                                                toggleVitalType('oxygen')
                                            }
                                        />
                                        <Label
                                            htmlFor="oxygen"
                                            className="flex cursor-pointer items-center gap-2 text-sm font-normal"
                                        >
                                            <Activity className="h-4 w-4 text-chart-1" />
                                            Oxygen Saturation
                                        </Label>
                                    </div>
                                </div>
                            </div>
                        </PopoverContent>
                    </Popover>
                </div>

                <div className="text-sm text-gray-600 dark:text-gray-400">
                    {filteredVitals.length} reading
                    {filteredVitals.length !== 1 ? 's' : ''} in{' '}
                    {getTimeRangeLabel(timeRange).toLowerCase()}
                </div>
            </div>

            {/* Charts */}
            {filteredVitals.length === 0 ? (
                <div className="flex h-full items-center justify-center py-12">
                    <div className="text-center">
                        <Thermometer className="mx-auto mb-4 h-12 w-12 text-gray-300 dark:text-gray-600" />
                        <p className="text-gray-500 dark:text-gray-400">
                            No vital signs in selected time range
                        </p>
                    </div>
                </div>
            ) : (
                <>
                    {vitalTypeFilter.temperature && (
                        <IndividualVitalChart
                            data={temperatureData}
                            dataKey="temperature"
                            label="Temperature"
                            color="var(--chart-1)"
                            unit="°C"
                            icon={
                                <Thermometer className="h-4 w-4 text-chart-1" />
                            }
                        />
                    )}

                    {vitalTypeFilter.bloodPressure && (
                        <>
                            <IndividualVitalChart
                                data={systolicData}
                                dataKey="systolic"
                                label="Blood Pressure (Systolic)"
                                color="var(--chart-2)"
                                unit="mmHg"
                                icon={
                                    <Heart className="h-4 w-4 text-chart-2" />
                                }
                            />

                            <IndividualVitalChart
                                data={diastolicData}
                                dataKey="diastolic"
                                label="Blood Pressure (Diastolic)"
                                color="var(--chart-3)"
                                unit="mmHg"
                                icon={
                                    <Heart className="h-4 w-4 text-chart-3" />
                                }
                            />
                        </>
                    )}

                    {vitalTypeFilter.pulse && (
                        <IndividualVitalChart
                            data={pulseData}
                            dataKey="pulse"
                            label="Pulse Rate"
                            color="var(--chart-4)"
                            unit="bpm"
                            icon={<Activity className="h-4 w-4 text-chart-4" />}
                        />
                    )}

                    {vitalTypeFilter.respiratory && (
                        <IndividualVitalChart
                            data={respiratoryData}
                            dataKey="respiratory"
                            label="Respiratory Rate"
                            color="var(--chart-5)"
                            unit="/min"
                            icon={<Activity className="h-4 w-4 text-chart-5" />}
                        />
                    )}

                    {vitalTypeFilter.oxygen && (
                        <IndividualVitalChart
                            data={oxygenData}
                            dataKey="oxygen"
                            label="Oxygen Saturation"
                            color="var(--chart-1)"
                            unit="%"
                            icon={<Activity className="h-4 w-4 text-chart-1" />}
                        />
                    )}
                </>
            )}
        </div>
    );
}
