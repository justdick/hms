import { Badge } from '@/components/ui/badge';
import { Calendar, ClipboardList, Stethoscope, UserCheck } from 'lucide-react';
import type { AttendanceDetails } from './types';

interface AttendanceDetailsSectionProps {
    attendance: AttendanceDetails;
}

const attendanceTypeLabels: Record<string, string> = {
    emergency: 'Emergency',
    acute: 'Acute',
    routine: 'Routine',
};

const serviceTypeLabels: Record<string, string> = {
    inpatient: 'Inpatient',
    outpatient: 'Outpatient',
};

const attendanceTypeColors: Record<string, string> = {
    emergency: 'bg-red-500',
    acute: 'bg-orange-500',
    routine: 'bg-blue-500',
};

/**
 * AttendanceDetailsSection - Displays attendance and service details for a claim
 *
 * Shows:
 * - Type of attendance (emergency, acute, routine)
 * - Date of attendance and discharge
 * - Type of service (inpatient/outpatient)
 * - Specialty attended
 * - Attending prescriber
 * - Claim check code
 *
 * @example
 * ```tsx
 * <AttendanceDetailsSection attendance={vettingData.attendance} />
 * ```
 */
export function AttendanceDetailsSection({
    attendance,
}: AttendanceDetailsSectionProps) {
    const formatDate = (dateString: string | null) => {
        if (!dateString) return 'N/A';
        return new Date(dateString).toLocaleDateString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        });
    };

    return (
        <section aria-labelledby="attendance-details-heading">
            <h3
                id="attendance-details-heading"
                className="mb-4 flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-gray-100"
            >
                <ClipboardList className="h-5 w-5" aria-hidden="true" />
                Attendance Details
            </h3>

            <div className="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {/* Type of Attendance */}
                    <div>
                        <span className="text-sm text-gray-500 dark:text-gray-400">
                            Type of Attendance
                        </span>
                        <div className="mt-1">
                            <Badge
                                className={
                                    attendanceTypeColors[
                                        attendance.type_of_attendance
                                    ] || 'bg-gray-500'
                                }
                            >
                                {attendanceTypeLabels[
                                    attendance.type_of_attendance
                                ] || attendance.type_of_attendance}
                            </Badge>
                        </div>
                    </div>

                    {/* Date of Attendance */}
                    <div>
                        <span className="text-sm text-gray-500 dark:text-gray-400">
                            Date of Attendance
                        </span>
                        <p className="flex items-center gap-2 font-medium text-gray-900 dark:text-gray-100">
                            <Calendar
                                className="h-4 w-4 text-gray-400"
                                aria-hidden="true"
                            />
                            {formatDate(attendance.date_of_attendance)}
                        </p>
                    </div>

                    {/* Date of Discharge */}
                    <div>
                        <span className="text-sm text-gray-500 dark:text-gray-400">
                            Date of Discharge
                        </span>
                        <p className="flex items-center gap-2 font-medium text-gray-900 dark:text-gray-100">
                            <Calendar
                                className="h-4 w-4 text-gray-400"
                                aria-hidden="true"
                            />
                            {formatDate(attendance.date_of_discharge)}
                        </p>
                    </div>

                    {/* Type of Service */}
                    <div>
                        <span className="text-sm text-gray-500 dark:text-gray-400">
                            Type of Service
                        </span>
                        <div className="mt-1">
                            <Badge variant="outline">
                                {serviceTypeLabels[
                                    attendance.type_of_service
                                ] || attendance.type_of_service}
                            </Badge>
                        </div>
                    </div>

                    {/* Specialty Attended */}
                    <div>
                        <span className="text-sm text-gray-500 dark:text-gray-400">
                            Specialty Attended
                        </span>
                        <p className="flex items-center gap-2 font-medium text-gray-900 dark:text-gray-100">
                            <Stethoscope
                                className="h-4 w-4 text-gray-400"
                                aria-hidden="true"
                            />
                            {attendance.specialty_attended || 'N/A'}
                        </p>
                    </div>

                    {/* Attending Prescriber */}
                    <div>
                        <span className="text-sm text-gray-500 dark:text-gray-400">
                            Attending Prescriber
                        </span>
                        <p className="flex items-center gap-2 font-medium text-gray-900 dark:text-gray-100">
                            <UserCheck
                                className="h-4 w-4 text-gray-400"
                                aria-hidden="true"
                            />
                            {attendance.attending_prescriber || 'N/A'}
                        </p>
                    </div>
                </div>

                {/* Claim Check Code */}
                <div className="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700">
                    <div className="flex items-center justify-between">
                        <span className="text-sm text-gray-500 dark:text-gray-400">
                            Claim Check Code
                        </span>
                        <span className="font-mono text-sm font-medium text-gray-900 dark:text-gray-100">
                            {attendance.claim_check_code}
                        </span>
                    </div>
                </div>
            </div>
        </section>
    );
}
