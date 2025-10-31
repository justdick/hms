import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import {
    BarChart3,
    FileBarChart,
    FileText,
    TrendingDown,
    TrendingUp,
    Users,
} from 'lucide-react';

export default function Index() {
    const reports = [
        {
            name: 'Claims Summary',
            description:
                'Overview of all claims with status breakdown and totals by provider',
            icon: FileText,
            href: '/admin/insurance/reports/claims-summary',
            color: 'text-blue-600 dark:text-blue-400',
        },
        {
            name: 'Revenue Analysis',
            description:
                'Compare insurance vs cash revenue with monthly trends',
            icon: TrendingUp,
            href: '/admin/insurance/reports/revenue-analysis',
            color: 'text-green-600 dark:text-green-400',
        },
        {
            name: 'Outstanding Claims',
            description:
                'Track unpaid claims with aging analysis (30/60/90 days)',
            icon: FileBarChart,
            href: '/admin/insurance/reports/outstanding-claims',
            color: 'text-orange-600 dark:text-orange-400',
        },
        {
            name: 'Vetting Performance',
            description:
                'Monitor vetting officer productivity and turnaround times',
            icon: Users,
            href: '/admin/insurance/reports/vetting-performance',
            color: 'text-purple-600 dark:text-purple-400',
        },
        {
            name: 'Utilization Report',
            description:
                'Analyze most used services and coverage patterns by provider',
            icon: BarChart3,
            href: '/admin/insurance/reports/utilization',
            color: 'text-cyan-600 dark:text-cyan-400',
        },
        {
            name: 'Rejection Analysis',
            description: 'Review rejection reasons, trends, and patterns',
            icon: TrendingDown,
            href: '/admin/insurance/reports/rejection-analysis',
            color: 'text-red-600 dark:text-red-400',
        },
    ];

    return (
        <AppLayout>
            <Head title="Insurance Reports" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-bold">
                        Insurance Reports & Analytics
                    </h1>
                    <p className="mt-2 text-muted-foreground">
                        Comprehensive reporting suite for insurance claims
                        management
                    </p>
                </div>

                <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    {reports.map((report) => {
                        const Icon = report.icon;
                        return (
                            <Link key={report.name} href={report.href}>
                                <Card className="transition-all hover:shadow-lg dark:hover:shadow-primary/20">
                                    <CardHeader>
                                        <div className="flex items-center gap-4">
                                            <div
                                                className={`rounded-lg bg-muted p-3 ${report.color}`}
                                            >
                                                <Icon className="h-6 w-6" />
                                            </div>
                                            <div className="flex-1">
                                                <CardTitle className="text-lg">
                                                    {report.name}
                                                </CardTitle>
                                            </div>
                                        </div>
                                        <CardDescription className="mt-2">
                                            {report.description}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <Button
                                            variant="ghost"
                                            className="w-full"
                                        >
                                            View Report
                                        </Button>
                                    </CardContent>
                                </Card>
                            </Link>
                        );
                    })}
                </div>
            </div>
        </AppLayout>
    );
}
