import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';

import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

export interface AttendanceBreakdownData {
    type: string;
    label: string;
    count: number;
    percentage: number;
    fill: string;
}

interface AttendanceBreakdownChartProps {
    data: AttendanceBreakdownData[];
    title?: string;
    description?: string;
}

export function AttendanceBreakdownChart({
    data,
    title = 'Attendance by Insurance',
    description = 'Patient distribution by insurance type',
}: AttendanceBreakdownChartProps) {
    const total = data.reduce((sum, item) => sum + item.count, 0);

    if (total === 0) {
        return (
            <Card>
                <CardHeader className="pb-2">
                    <CardTitle className="text-base">{title}</CardTitle>
                    <CardDescription>{description}</CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="flex h-[200px] items-center justify-center text-muted-foreground">
                        No attendance data for this period
                    </div>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-base">{title}</CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent>
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                    {/* Pie Chart */}
                    <div className="h-[180px] w-full sm:w-[180px]">
                        <ResponsiveContainer width="100%" height="100%">
                            <PieChart>
                                <Pie
                                    data={data}
                                    cx="50%"
                                    cy="50%"
                                    innerRadius={45}
                                    outerRadius={70}
                                    paddingAngle={2}
                                    dataKey="count"
                                    nameKey="label"
                                >
                                    {data.map((entry) => (
                                        <Cell
                                            key={entry.type}
                                            fill={entry.fill}
                                            stroke="none"
                                        />
                                    ))}
                                </Pie>
                                <Tooltip
                                    content={({ active, payload }) => {
                                        if (active && payload && payload.length) {
                                            const item = payload[0]
                                                .payload as AttendanceBreakdownData;
                                            return (
                                                <div className="rounded-lg border bg-background px-3 py-2 shadow-md">
                                                    <p className="font-medium">
                                                        {item.label}
                                                    </p>
                                                    <p className="text-sm text-muted-foreground">
                                                        {item.count} patients (
                                                        {item.percentage}%)
                                                    </p>
                                                </div>
                                            );
                                        }
                                        return null;
                                    }}
                                />
                            </PieChart>
                        </ResponsiveContainer>
                    </div>

                    {/* Legend */}
                    <div className="flex flex-1 flex-col gap-3">
                        {data.map((item) => (
                            <div
                                key={item.type}
                                className="flex items-center justify-between gap-4"
                            >
                                <div className="flex items-center gap-2">
                                    <div
                                        className="h-3 w-3 rounded-full"
                                        style={{ backgroundColor: item.fill }}
                                    />
                                    <span className="text-sm font-medium">
                                        {item.label}
                                    </span>
                                </div>
                                <div className="flex items-center gap-3 text-right">
                                    <span className="text-lg font-bold tabular-nums">
                                        {item.count}
                                    </span>
                                    <span className="w-12 text-sm text-muted-foreground tabular-nums">
                                        {item.percentage}%
                                    </span>
                                </div>
                            </div>
                        ))}
                        <div className="mt-2 border-t pt-2">
                            <div className="flex items-center justify-between">
                                <span className="text-sm font-medium text-muted-foreground">
                                    Total
                                </span>
                                <span className="text-lg font-bold tabular-nums">
                                    {total}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
