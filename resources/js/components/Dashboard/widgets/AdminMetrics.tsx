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
    datePreset?: 'today' | 'week' | 'month' | 'year' | 'custom';
}

// Helper function to get the appropriate label based on date preset
function getAttendanceLabel(preset?: string): string {
    switch (preset) {
        case 'week':
            return 'Patients this week';
        case 'month':
            return 'Patients this month';
        case 'year':
            return 'Patients this year';
        case 'custom':
            return 'Patients (selected period)';
        case 'today':
        default:
            return 'Patients today';
    }
}

export function AdminMetrics({ metrics, patientsHref, datePreset = 'today' }: AdminMetricsProps) {
    return (
        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
            {/* Total Attendance Group */}
            <div className="rounded-xl border border-gray-200 bg-gray-50/50 p-4 dark:border-gray-700 dark:bg-gray-900/20">
                <div className="mb-3 flex items-center gap-2">
                    <UsersRound className="h-5 w-5 text-gray-600 dark:text-gray-400" />
                    <h3 className="font-semibold text-gray-900 dark:text-gray-100">
                        Total Attendance
                    </h3>
                </div>
                <div className="text-center">
                    <div className="text-4xl font-bold text-gray-900 dark:text-gray-100">
                        {metrics.totalPatientsToday}
                    </div>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {getAttendanceLabel(datePreset)}
                    </p>
                </div>
            </div>

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
    );
}
