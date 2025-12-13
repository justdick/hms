import { useCallback, useEffect, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';

import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

import { DashboardLayout } from '@/components/Dashboard/DashboardLayout';
import { QuickActions, type QuickAction } from '@/components/Dashboard/QuickActions';

// Metrics widgets
import { ReceptionistMetrics, type ReceptionistMetricsData } from '@/components/Dashboard/widgets/RecentCheckins';
import { DoctorMetrics, type DoctorMetricsData } from '@/components/Dashboard/widgets/ConsultationQueue';
import { PharmacistMetrics, type PharmacistMetricsData } from '@/components/Dashboard/widgets/PrescriptionQueue';
import { NurseMetrics, type NurseMetricsData } from '@/components/Dashboard/widgets/VitalsQueue';
import { CashierMetrics, type CashierMetricsData } from '@/components/Dashboard/widgets/RecentPayments';
import { InsuranceMetrics, type InsuranceMetricsData } from '@/components/Dashboard/widgets/ClaimsQueue';
import { FinanceMetrics, type FinanceMetricsData } from '@/components/Dashboard/widgets/FinanceMetrics';
import { AdminMetrics, type AdminMetricsData } from '@/components/Dashboard/widgets/AdminMetrics';

// Chart widgets (only for admin/finance)
import { RevenueSummary, type RevenueByPaymentMethod } from '@/components/Dashboard/widgets/RevenueSummary';
import {
    PatientFlowChart,
    type PatientFlowData,
} from '@/components/Dashboard/widgets/PatientFlowChart';
import {
    RevenueTrendChart,
    type RevenueTrendData,
} from '@/components/Dashboard/widgets/RevenueTrendChart';
import {
    DepartmentActivityChart,
    type DepartmentActivityData,
} from '@/components/Dashboard/widgets/DepartmentActivityChart';

import { Skeleton } from '@/components/ui/skeleton';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

interface DashboardMetrics {
    todayCheckins?: number;
    awaitingVitals?: number;
    awaitingConsultation?: number;
    completedToday?: number;
    consultationQueue?: number;
    activeConsultations?: number;
    pendingLabResults?: number;
    pendingPrescriptions?: number;
    dispensedToday?: number;
    lowStockCount?: number;
    expiringCount?: number;
    pendingMedications?: number;
    activeAdmissions?: number;
    vitalsRecordedToday?: number;
    todayCollections?: number;
    transactionCount?: number;
    pendingPayments?: number;
    averageTransaction?: number;
    pendingVetting?: number;
    vettedReady?: number;
    submittedThisMonth?: number;
    totalClaimValue?: number;
    todayRevenue?: number;
    outstandingReceivables?: number;
    pendingInsuranceClaims?: number;
    pendingInsuranceClaimsCount?: number;
    totalPatientsToday?: number;
    totalRevenueToday?: number;
    activeUsersCount?: number;
    totalDepartments?: number;
}


interface DashboardLists {
    revenueByPaymentMethod?: RevenueByPaymentMethod[];
    // Admin charts data
    patientFlowTrend?: PatientFlowData[];
    revenueTrend?: RevenueTrendData[];
    departmentActivity?: DepartmentActivityData[];
}

interface DashboardProps {
    visibleWidgets: string[];
    quickActions: QuickAction[];
    metrics?: DashboardMetrics;
    lists?: DashboardLists;
    [key: string]: unknown;
}

function MetricsSkeleton() {
    return (
        <div className="grid gap-4 grid-cols-2 lg:grid-cols-4">
            {[1, 2, 3, 4].map((i) => (
                <div key={i} className="flex flex-col items-center gap-2 rounded-xl border p-4">
                    <Skeleton className="h-6 w-6" />
                    <Skeleton className="h-8 w-16" />
                    <Skeleton className="h-3 w-20" />
                </div>
            ))}
        </div>
    );
}

const POLLING_INTERVAL = 30000;


export default function Dashboard() {
    const { visibleWidgets, quickActions, metrics, lists } = usePage<DashboardProps>().props;
    const [isRefreshing, setIsRefreshing] = useState(false);

    const hasWidget = useCallback((id: string) => visibleWidgets.includes(id), [visibleWidgets]);

    useEffect(() => {
        const id = setInterval(() => {
            setIsRefreshing(true);
            router.reload({ only: ['metrics', 'lists'], onFinish: () => setIsRefreshing(false) });
        }, POLLING_INTERVAL);
        return () => clearInterval(id);
    }, []);

    const metricsLoaded = metrics !== undefined;

    const receptionistData: ReceptionistMetricsData = {
        todayCheckins: metrics?.todayCheckins ?? 0,
        awaitingVitals: metrics?.awaitingVitals ?? 0,
        awaitingConsultation: metrics?.awaitingConsultation ?? 0,
        completedToday: metrics?.completedToday ?? 0,
    };

    const doctorData: DoctorMetricsData = {
        consultationQueue: metrics?.consultationQueue ?? 0,
        activeConsultations: metrics?.activeConsultations ?? 0,
        pendingLabResults: metrics?.pendingLabResults ?? 0,
        completedToday: metrics?.completedToday ?? 0,
    };

    const pharmacistData: PharmacistMetricsData = {
        pendingPrescriptions: metrics?.pendingPrescriptions ?? 0,
        dispensedToday: metrics?.dispensedToday ?? 0,
        lowStockCount: metrics?.lowStockCount ?? 0,
        expiringCount: metrics?.expiringCount ?? 0,
    };

    const nurseData: NurseMetricsData = {
        awaitingVitals: metrics?.awaitingVitals ?? 0,
        pendingMedications: metrics?.pendingMedications ?? 0,
        activeAdmissions: metrics?.activeAdmissions ?? 0,
        vitalsRecordedToday: metrics?.vitalsRecordedToday ?? 0,
    };

    const cashierData: CashierMetricsData = {
        todayCollections: metrics?.todayCollections ?? 0,
        transactionCount: metrics?.transactionCount ?? 0,
        pendingPayments: metrics?.pendingPayments ?? 0,
        averageTransaction: metrics?.averageTransaction ?? 0,
    };

    const insuranceData: InsuranceMetricsData = {
        pendingVetting: metrics?.pendingVetting ?? 0,
        vettedReady: metrics?.vettedReady ?? 0,
        submittedThisMonth: metrics?.submittedThisMonth ?? 0,
        totalClaimValue: metrics?.totalClaimValue ?? 0,
    };

    const financeData: FinanceMetricsData = {
        todayRevenue: metrics?.todayRevenue ?? 0,
        outstandingReceivables: metrics?.outstandingReceivables ?? 0,
        pendingInsuranceClaims: metrics?.pendingInsuranceClaims ?? 0,
        pendingInsuranceClaimsCount: metrics?.pendingInsuranceClaimsCount ?? 0,
    };

    const adminData: AdminMetricsData = {
        totalPatientsToday: metrics?.totalPatientsToday ?? 0,
        totalRevenueToday: metrics?.totalRevenueToday ?? 0,
        activeUsersCount: metrics?.activeUsersCount ?? 0,
        totalDepartments: metrics?.totalDepartments ?? 0,
    };


    const renderMetrics = () => {
        if (!metricsLoaded) return <MetricsSkeleton />;
        if (hasWidget('admin_metrics')) return <AdminMetrics metrics={adminData} />;
        if (hasWidget('finance_metrics')) return <FinanceMetrics metrics={financeData} />;
        if (hasWidget('claims_metrics')) return <InsuranceMetrics metrics={insuranceData} />;
        if (hasWidget('billing_metrics')) return <CashierMetrics metrics={cashierData} />;
        if (hasWidget('consultation_queue')) return <DoctorMetrics metrics={doctorData} />;
        if (hasWidget('prescription_queue')) return <PharmacistMetrics metrics={pharmacistData} />;
        if (hasWidget('vitals_queue')) return <NurseMetrics metrics={nurseData} />;
        if (hasWidget('checkin_metrics')) return <ReceptionistMetrics metrics={receptionistData} />;
        return null;
    };

    // Only show charts for admin/finance roles
    const renderCharts = () => {
        // Admin: Beautiful trend charts
        if (hasWidget('admin_metrics') && lists) {
            return (
                <div className="space-y-4">
                    {/* Two charts side by side */}
                    <div className="grid gap-4 md:grid-cols-2">
                        {lists.patientFlowTrend && (
                            <PatientFlowChart data={lists.patientFlowTrend} />
                        )}
                        {lists.revenueTrend && (
                            <RevenueTrendChart data={lists.revenueTrend} />
                        )}
                    </div>
                    {/* Department activity full width */}
                    {lists.departmentActivity && (
                        <DepartmentActivityChart data={lists.departmentActivity} />
                    )}
                </div>
            );
        }

        // Finance: Revenue breakdown chart
        if (hasWidget('revenue_summary') && lists?.revenueByPaymentMethod) {
            return <RevenueSummary revenueByPaymentMethod={lists.revenueByPaymentMethod} />;
        }

        return null;
    };


    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                {isRefreshing && (
                    <div className="fixed top-4 right-4 z-50 flex items-center gap-2 rounded-lg bg-background/80 px-3 py-2 text-sm text-muted-foreground shadow-lg backdrop-blur">
                        <RefreshCw className="h-4 w-4 animate-spin" />
                        <span>Refreshing...</span>
                    </div>
                )}
                <DashboardLayout>
                    {/* Stats Cards */}
                    {renderMetrics()}

                    {/* Quick Actions */}
                    {quickActions.length > 0 && <QuickActions actions={quickActions} columns={4} />}

                    {/* Charts (only for admin/finance) */}
                    {renderCharts()}
                </DashboardLayout>
            </div>
        </AppLayout>
    );
}
