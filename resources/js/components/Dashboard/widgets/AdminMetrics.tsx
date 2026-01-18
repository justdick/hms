import {
    Banknote,
    Bed,
    ShieldCheck,
    Stethoscope,
    UsersRound,
} from 'lucide-react';

import { MetricCard } from '@/components/Dashboard/MetricCard';

export interface AdminMetricsData {
    totalPatientsToday: number;
    opdAttendance: number;
    opdNhisAttendance: number;
    opdNonInsuredAttendance: number;
    ipdAttendance: number;
    ipdNhisAttendance: number;
    ipdNonInsuredAttendance: number;
}

export interface AdminMetricsProps {
    metrics: AdminMetricsData;
    patientsHref?: string;
}

export function AdminMetrics({ metrics, patientsHref }: AdminMetricsProps) {
    return (
        <div className="space-y-4">
            {/* Total Attendance - Full width */}
            <div className="grid grid-cols-1">
                <MetricCard
                    title="Total Attendance"
                    value={metrics.totalPatientsToday}
                    icon={UsersRound}
                    variant="primary"
                    href={patientsHref}
                />
            </div>

            {/* OPD and IPD Groups */}
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                {/* OPD Group */}
                <div className="rounded-xl border border-blue-200 bg-blue-50/50 p-4 dark:border-blue-900 dark:bg-blue-950/20">
                    <div className="mb-3 flex items-center gap-2">
                        <Stethoscope className="h-5 w-5 text-blue-600" />
                        <h3 className="font-semibold text-blue-900 dark:text-blue-100">
                            OPD (Outpatient)
                        </h3>
                        <span className="ml-auto text-2xl font-bold text-blue-600">
                            {metrics.opdAttendance}
                        </span>
                    </div>
                    <div className="grid grid-cols-2 gap-2">
                        <MetricCard
                            title="NHIS"
                            value={metrics.opdNhisAttendance}
                            icon={ShieldCheck}
                            variant="success"
                        />
                        <MetricCard
                            title="Non-Insured"
                            value={metrics.opdNonInsuredAttendance}
                            icon={Banknote}
                            variant="warning"
                        />
                    </div>
                </div>

                {/* IPD Group */}
                <div className="rounded-xl border border-purple-200 bg-purple-50/50 p-4 dark:border-purple-900 dark:bg-purple-950/20">
                    <div className="mb-3 flex items-center gap-2">
                        <Bed className="h-5 w-5 text-purple-600" />
                        <h3 className="font-semibold text-purple-900 dark:text-purple-100">
                            IPD (Admitted)
                        </h3>
                        <span className="ml-auto text-2xl font-bold text-purple-600">
                            {metrics.ipdAttendance}
                        </span>
                    </div>
                    <div className="grid grid-cols-2 gap-2">
                        <MetricCard
                            title="NHIS"
                            value={metrics.ipdNhisAttendance}
                            icon={ShieldCheck}
                            variant="success"
                        />
                        <MetricCard
                            title="Non-Insured"
                            value={metrics.ipdNonInsuredAttendance}
                            icon={Banknote}
                            variant="warning"
                        />
                    </div>
                </div>
            </div>
        </div>
    );
}


