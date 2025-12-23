import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    FileBarChart,
    FileText,
    Package,
    TrendingDown,
} from 'lucide-react';

export default function Index() {
    return (
        <AppLayout>
            <Head title="Insurance Reports" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-bold">Insurance Reports</h1>
                    <p className="mt-2 text-muted-foreground">
                        Access detailed reports for insurance claims management
                    </p>
                </div>

                {/* Report Links */}
                <div className="grid gap-4 md:grid-cols-2">
                    <Link href="/admin/insurance/reports/claims-summary">
                        <Card className="transition-colors hover:bg-gray-50 dark:hover:bg-gray-800">
                            <CardHeader className="flex flex-row items-center gap-4">
                                <div className="rounded-lg bg-blue-100 p-3 dark:bg-blue-900/30">
                                    <FileText className="h-6 w-6 text-blue-600 dark:text-blue-400" />
                                </div>
                                <div className="flex-1">
                                    <CardTitle className="flex items-center justify-between">
                                        Claims Summary
                                        <ArrowRight className="h-4 w-4 text-gray-400" />
                                    </CardTitle>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm text-muted-foreground">
                                    Overview of all claims with status
                                    breakdown, amounts claimed, approved, and
                                    paid by provider.
                                </p>
                            </CardContent>
                        </Card>
                    </Link>

                    <Link href="/admin/insurance/reports/outstanding-claims">
                        <Card className="transition-colors hover:bg-gray-50 dark:hover:bg-gray-800">
                            <CardHeader className="flex flex-row items-center gap-4">
                                <div className="rounded-lg bg-orange-100 p-3 dark:bg-orange-900/30">
                                    <FileBarChart className="h-6 w-6 text-orange-600 dark:text-orange-400" />
                                </div>
                                <div className="flex-1">
                                    <CardTitle className="flex items-center justify-between">
                                        Outstanding Claims
                                        <ArrowRight className="h-4 w-4 text-gray-400" />
                                    </CardTitle>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm text-muted-foreground">
                                    Track unpaid claims with aging analysis
                                    (0-30, 31-60, 61-90, 90+ days) by provider.
                                </p>
                            </CardContent>
                        </Card>
                    </Link>

                    <Link href="/admin/insurance/reports/rejection-analysis">
                        <Card className="transition-colors hover:bg-gray-50 dark:hover:bg-gray-800">
                            <CardHeader className="flex flex-row items-center gap-4">
                                <div className="rounded-lg bg-red-100 p-3 dark:bg-red-900/30">
                                    <TrendingDown className="h-6 w-6 text-red-600 dark:text-red-400" />
                                </div>
                                <div className="flex-1">
                                    <CardTitle className="flex items-center justify-between">
                                        Rejection Analysis
                                        <ArrowRight className="h-4 w-4 text-gray-400" />
                                    </CardTitle>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm text-muted-foreground">
                                    Review rejection reasons, trends, and
                                    identify common issues to improve claim
                                    approval rates.
                                </p>
                            </CardContent>
                        </Card>
                    </Link>

                    <Link href="/admin/insurance/reports/tariff-coverage">
                        <Card className="transition-colors hover:bg-gray-50 dark:hover:bg-gray-800">
                            <CardHeader className="flex flex-row items-center gap-4">
                                <div className="rounded-lg bg-green-100 p-3 dark:bg-green-900/30">
                                    <Package className="h-6 w-6 text-green-600 dark:text-green-400" />
                                </div>
                                <div className="flex-1">
                                    <CardTitle className="flex items-center justify-between">
                                        Tariff Coverage
                                        <ArrowRight className="h-4 w-4 text-gray-400" />
                                    </CardTitle>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm text-muted-foreground">
                                    NHIS tariff mapping status showing which
                                    services have tariff codes configured.
                                </p>
                            </CardContent>
                        </Card>
                    </Link>
                </div>
            </div>
        </AppLayout>
    );
}
