import { Building2 } from 'lucide-react';
import { Bar, BarChart, Cell, XAxis, YAxis } from 'recharts';

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

export interface DepartmentActivityData {
    name: string;
    code: string;
    checkins: number;
    fill: string;
}

export interface DepartmentActivityChartProps {
    data: DepartmentActivityData[];
    className?: string;
}

const chartConfig = {
    checkins: {
        label: 'Check-ins',
    },
} satisfies ChartConfig;

export function DepartmentActivityChart({
    data,
    className,
}: DepartmentActivityChartProps) {
    const totalCheckins = data.reduce((sum, d) => sum + d.checkins, 0);
    const topDepartment = data.length > 0 ? data[0] : null;

    // Filter out departments with 0 check-ins for cleaner display
    const activeData = data.filter((d) => d.checkins > 0);

    return (
        <Card className={cn('', className)}>
            <CardHeader className="pb-2">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <Building2 className="h-5 w-5 text-violet-500" />
                        <div>
                            <CardTitle className="text-base font-semibold">
                                Department Activity
                            </CardTitle>
                            <CardDescription>
                                Today's check-ins by department
                            </CardDescription>
                        </div>
                    </div>
                    <div className="text-right">
                        <div className="text-lg font-bold text-violet-500">
                            {totalCheckins}
                        </div>
                        <div className="text-xs text-muted-foreground">
                            Total check-ins
                        </div>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="pt-0">
                {activeData.length === 0 ? (
                    <div className="flex h-[200px] flex-col items-center justify-center text-muted-foreground">
                        <Building2 className="mb-2 h-8 w-8 opacity-50" />
                        <span>No check-ins today</span>
                    </div>
                ) : (
                    <div className="space-y-3">
                        <ChartContainer
                            config={chartConfig}
                            className="h-[200px] w-full"
                        >
                            <BarChart
                                data={activeData}
                                layout="vertical"
                                margin={{
                                    top: 5,
                                    right: 30,
                                    left: 0,
                                    bottom: 5,
                                }}
                            >
                                <XAxis
                                    type="number"
                                    tickLine={false}
                                    axisLine={false}
                                    className="text-xs"
                                />
                                <YAxis
                                    type="category"
                                    dataKey="code"
                                    tickLine={false}
                                    axisLine={false}
                                    width={60}
                                    className="text-xs"
                                />
                                <ChartTooltip
                                    content={
                                        <ChartTooltipContent
                                            labelFormatter={(_, payload) => {
                                                if (
                                                    payload?.[0]?.payload?.name
                                                ) {
                                                    return payload[0].payload
                                                        .name;
                                                }
                                                return '';
                                            }}
                                        />
                                    }
                                />
                                <Bar
                                    dataKey="checkins"
                                    radius={[0, 4, 4, 0]}
                                    barSize={20}
                                >
                                    {activeData.map((entry, index) => (
                                        <Cell
                                            key={`cell-${index}`}
                                            fill={entry.fill}
                                        />
                                    ))}
                                </Bar>
                            </BarChart>
                        </ChartContainer>

                        {topDepartment && topDepartment.checkins > 0 && (
                            <div className="flex items-center justify-center gap-2 rounded-lg bg-muted/50 px-3 py-2 text-sm">
                                <span className="text-muted-foreground">
                                    Busiest:
                                </span>
                                <span
                                    className="font-medium"
                                    style={{ color: topDepartment.fill }}
                                >
                                    {topDepartment.name}
                                </span>
                                <span className="text-muted-foreground">
                                    ({topDepartment.checkins} check-ins)
                                </span>
                            </div>
                        )}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
