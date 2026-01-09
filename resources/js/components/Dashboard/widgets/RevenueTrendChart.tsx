import { TrendingUp } from 'lucide-react';
import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from 'recharts';

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
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import { cn } from '@/lib/utils';

export interface RevenueTrendData {
    date: string;
    day: string;
    fullDate: string;
    revenue: number;
}

export interface RevenueTrendChartProps {
    data: RevenueTrendData[];
    className?: string;
    dateLabel?: string;
}

const chartConfig = {
    revenue: {
        label: 'Revenue',
        color: '#10b981',
    },
} satisfies ChartConfig;

function formatCurrency(amount: number): string {
    if (amount >= 1000) {
        return `GH₵${(amount / 1000).toFixed(1)}k`;
    }
    return `GH₵${amount.toFixed(0)}`;
}

function formatFullCurrency(amount: number): string {
    return new Intl.NumberFormat('en-GH', {
        style: 'currency',
        currency: 'GHS',
        minimumFractionDigits: 2,
    }).format(amount);
}

export function RevenueTrendChart({ data, className, dateLabel }: RevenueTrendChartProps) {
    const totalRevenue = data.reduce((sum, d) => sum + d.revenue, 0);
    const avgRevenue = data.length > 0 ? totalRevenue / data.length : 0;

    // Calculate trend (compare last 3 days to previous 3 days)
    const recentDays = data.slice(-3);
    const previousDays = data.slice(-6, -3);
    const recentAvg =
        recentDays.length > 0
            ? recentDays.reduce((s, d) => s + d.revenue, 0) / recentDays.length
            : 0;
    const previousAvg =
        previousDays.length > 0
            ? previousDays.reduce((s, d) => s + d.revenue, 0) /
              previousDays.length
            : 0;
    const trendPercent =
        previousAvg > 0 ? ((recentAvg - previousAvg) / previousAvg) * 100 : 0;

    // Generate dynamic label from data if not provided
    const displayLabel = dateLabel || (data.length > 0 
        ? `${data[0].fullDate} - ${data[data.length - 1].fullDate}`
        : 'No data');

    return (
        <Card className={cn('', className)}>
            <CardHeader className="pb-2">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <TrendingUp className="h-5 w-5 text-emerald-500" />
                        <div>
                            <CardTitle className="text-base font-semibold">
                                Revenue Trend
                            </CardTitle>
                            <CardDescription>{displayLabel}</CardDescription>
                        </div>
                    </div>
                    <div className="text-right">
                        <div className="text-lg font-bold text-emerald-500">
                            {formatFullCurrency(totalRevenue)}
                        </div>
                        <div className="flex items-center justify-end gap-1 text-xs">
                            {trendPercent !== 0 && (
                                <span
                                    className={cn(
                                        'font-medium',
                                        trendPercent > 0
                                            ? 'text-emerald-500'
                                            : 'text-red-500',
                                    )}
                                >
                                    {trendPercent > 0 ? '+' : ''}
                                    {trendPercent.toFixed(1)}%
                                </span>
                            )}
                            <span className="text-muted-foreground">
                                avg {formatCurrency(avgRevenue)}/day
                            </span>
                        </div>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="pt-0">
                {data.length === 0 ? (
                    <div className="flex h-[200px] items-center justify-center text-muted-foreground">
                        No revenue data
                    </div>
                ) : (
                    <ChartContainer
                        config={chartConfig}
                        className="h-[220px] w-full"
                    >
                        <BarChart
                            data={data}
                            margin={{ top: 10, right: 10, left: 0, bottom: 0 }}
                        >
                            <defs>
                                <linearGradient
                                    id="fillRevenue"
                                    x1="0"
                                    y1="0"
                                    x2="0"
                                    y2="1"
                                >
                                    <stop
                                        offset="0%"
                                        stopColor="#10b981"
                                        stopOpacity={1}
                                    />
                                    <stop
                                        offset="100%"
                                        stopColor="#059669"
                                        stopOpacity={0.8}
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
                                width={50}
                                tickFormatter={formatCurrency}
                                className="text-xs"
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
                                        formatter={(value) =>
                                            formatFullCurrency(value as number)
                                        }
                                    />
                                }
                            />
                            <Bar
                                dataKey="revenue"
                                fill="url(#fillRevenue)"
                                radius={[4, 4, 0, 0]}
                            />
                        </BarChart>
                    </ChartContainer>
                )}
            </CardContent>
        </Card>
    );
}
