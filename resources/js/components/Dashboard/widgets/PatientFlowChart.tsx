import { Activity } from 'lucide-react';
import { Area, AreaChart, CartesianGrid, XAxis, YAxis } from 'recharts';

import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    ChartConfig,
    ChartContainer,
    ChartLegend,
    ChartLegendContent,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import { cn } from '@/lib/utils';

export interface PatientFlowData {
    date: string;
    day: string;
    fullDate: string;
    checkins: number;
    consultations: number;
}

export interface PatientFlowChartProps {
    data: PatientFlowData[];
    className?: string;
    dateLabel?: string;
}

const chartConfig = {
    checkins: {
        label: 'Check-ins',
        color: '#3b82f6',
    },
    consultations: {
        label: 'Consultations',
        color: '#10b981',
    },
} satisfies ChartConfig;

export function PatientFlowChart({ data, className, dateLabel }: PatientFlowChartProps) {
    const totalCheckins = data.reduce((sum, d) => sum + d.checkins, 0);
    const totalConsultations = data.reduce(
        (sum, d) => sum + d.consultations,
        0,
    );

    // Generate dynamic label from data if not provided
    const displayLabel = dateLabel || (data.length > 0 
        ? `${data[0].fullDate} - ${data[data.length - 1].fullDate}`
        : 'No data');

    return (
        <Card className={cn('', className)}>
            <CardHeader className="pb-2">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <Activity className="h-5 w-5 text-blue-500" />
                        <div>
                            <CardTitle className="text-base font-semibold">
                                Patient Flow
                            </CardTitle>
                            <CardDescription>{displayLabel}</CardDescription>
                        </div>
                    </div>
                    <div className="flex gap-4 text-sm">
                        <div className="text-center">
                            <div className="text-lg font-bold text-blue-500">
                                {totalCheckins}
                            </div>
                            <div className="text-xs text-muted-foreground">
                                Check-ins
                            </div>
                        </div>
                        <div className="text-center">
                            <div className="text-lg font-bold text-emerald-500">
                                {totalConsultations}
                            </div>
                            <div className="text-xs text-muted-foreground">
                                Consultations
                            </div>
                        </div>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="pt-0">
                {data.length === 0 ? (
                    <div className="flex h-[200px] items-center justify-center text-muted-foreground">
                        No data available
                    </div>
                ) : (
                    <ChartContainer
                        config={chartConfig}
                        className="h-[220px] w-full"
                    >
                        <AreaChart
                            data={data}
                            margin={{ top: 10, right: 10, left: 0, bottom: 0 }}
                        >
                            <defs>
                                <linearGradient
                                    id="fillCheckins"
                                    x1="0"
                                    y1="0"
                                    x2="0"
                                    y2="1"
                                >
                                    <stop
                                        offset="5%"
                                        stopColor="#3b82f6"
                                        stopOpacity={0.8}
                                    />
                                    <stop
                                        offset="95%"
                                        stopColor="#3b82f6"
                                        stopOpacity={0.1}
                                    />
                                </linearGradient>
                                <linearGradient
                                    id="fillConsultations"
                                    x1="0"
                                    y1="0"
                                    x2="0"
                                    y2="1"
                                >
                                    <stop
                                        offset="5%"
                                        stopColor="#10b981"
                                        stopOpacity={0.8}
                                    />
                                    <stop
                                        offset="95%"
                                        stopColor="#10b981"
                                        stopOpacity={0.1}
                                    />
                                </linearGradient>
                            </defs>
                            <CartesianGrid
                                strokeDasharray="3 3"
                                vertical={false}
                                className="stroke-muted"
                            />
                            <XAxis
                                dataKey="day"
                                tickLine={false}
                                axisLine={false}
                                tickMargin={8}
                                className="text-xs"
                            />
                            <YAxis
                                tickLine={false}
                                axisLine={false}
                                tickMargin={8}
                                width={45}
                                className="text-xs"
                                tickFormatter={(value) => {
                                    if (value >= 1000) {
                                        return `${(value / 1000).toFixed(0)}k`;
                                    }
                                    return value.toString();
                                }}
                            />
                            <ChartTooltip
                                content={
                                    <ChartTooltipContent
                                        labelFormatter={(_, payload) => {
                                            if (
                                                payload?.[0]?.payload?.fullDate
                                            ) {
                                                return payload[0].payload
                                                    .fullDate;
                                            }
                                            return '';
                                        }}
                                    />
                                }
                            />
                            <Area
                                type="monotone"
                                dataKey="checkins"
                                stroke="#3b82f6"
                                strokeWidth={2}
                                fill="url(#fillCheckins)"
                            />
                            <Area
                                type="monotone"
                                dataKey="consultations"
                                stroke="#10b981"
                                strokeWidth={2}
                                fill="url(#fillConsultations)"
                            />
                            <ChartLegend content={<ChartLegendContent />} />
                        </AreaChart>
                    </ChartContainer>
                )}
            </CardContent>
        </Card>
    );
}
