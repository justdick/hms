import { Link } from '@inertiajs/react';
import { ArrowRight, PieChart as PieChartIcon } from 'lucide-react';
import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';

import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';

export interface RevenueByPaymentMethod {
    payment_method: string;
    payment_method_key: string;
    transaction_count: number;
    total_amount: number;
    percentage: number;
}

export interface RevenueSummaryProps {
    revenueByPaymentMethod: RevenueByPaymentMethod[];
    viewAllHref?: string;
    className?: string;
}

function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('en-GH', {
        style: 'currency',
        currency: 'GHS',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(amount);
}

const COLORS = ['#10b981', '#3b82f6', '#f59e0b', '#8b5cf6', '#06b6d4', '#f97316', '#6b7280'];

export function RevenueSummary({
    revenueByPaymentMethod,
    viewAllHref,
    className,
}: RevenueSummaryProps) {
    const totalRevenue = revenueByPaymentMethod.reduce((sum, item) => sum + item.total_amount, 0);

    const chartData = revenueByPaymentMethod.map((item, index) => ({
        name: item.payment_method,
        value: item.total_amount,
        color: COLORS[index % COLORS.length],
    }));

    return (
        <Card className={cn('', className)}>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
                <div className="flex items-center gap-2">
                    <PieChartIcon className="h-5 w-5 text-primary" />
                    <div>
                        <CardTitle className="text-base font-semibold">Revenue by Payment Method</CardTitle>
                        <CardDescription>Today's revenue breakdown</CardDescription>
                    </div>
                </div>
                {viewAllHref && (
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={viewAllHref}>
                            Reports
                            <ArrowRight className="ml-1 h-4 w-4" />
                        </Link>
                    </Button>
                )}
            </CardHeader>
            <CardContent>
                {revenueByPaymentMethod.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-8 text-muted-foreground">
                        <PieChartIcon className="mb-2 h-8 w-8 opacity-50" />
                        <span>No revenue today</span>
                    </div>
                ) : (
                    <div className="flex flex-col lg:flex-row items-center gap-4">
                        {/* Pie Chart */}
                        <div className="h-[200px] w-full lg:w-1/2">
                            <ResponsiveContainer width="100%" height="100%">
                                <PieChart>
                                    <Pie
                                        data={chartData}
                                        cx="50%"
                                        cy="50%"
                                        innerRadius={50}
                                        outerRadius={80}
                                        paddingAngle={2}
                                        dataKey="value"
                                    >
                                        {chartData.map((entry, index) => (
                                            <Cell key={`cell-${index}`} fill={entry.color} />
                                        ))}
                                    </Pie>
                                    <Tooltip
                                        formatter={(value: number) => formatCurrency(value)}
                                        contentStyle={{ borderRadius: '8px', border: '1px solid #e5e7eb' }}
                                    />
                                </PieChart>
                            </ResponsiveContainer>
                        </div>

                        {/* Legend & Total */}
                        <div className="w-full lg:w-1/2 space-y-3">
                            <div className="text-center lg:text-left">
                                <div className="text-2xl font-bold">{formatCurrency(totalRevenue)}</div>
                                <div className="text-sm text-muted-foreground">Total Revenue</div>
                            </div>
                            <div className="space-y-2">
                                {revenueByPaymentMethod.map((item, index) => (
                                    <div key={item.payment_method_key} className="flex items-center justify-between text-sm">
                                        <div className="flex items-center gap-2">
                                            <div
                                                className="h-3 w-3 rounded-full"
                                                style={{ backgroundColor: COLORS[index % COLORS.length] }}
                                            />
                                            <span>{item.payment_method}</span>
                                        </div>
                                        <span className="font-medium">{formatCurrency(item.total_amount)}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
