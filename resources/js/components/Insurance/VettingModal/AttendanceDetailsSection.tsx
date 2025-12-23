import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Calendar, ClipboardList, Stethoscope, UserCheck } from 'lucide-react';
import type { AttendanceDetails } from './types';

interface AttendanceDetailsSectionProps {
    attendance: AttendanceDetails;
    onAttendanceChange?: (field: string, value: string) => void;
    disabled?: boolean;
}

const attendanceTypeColors: Record<string, string> = {
    EAE: 'bg-blue-500',
    ANC: 'bg-pink-500',
    PNC: 'bg-purple-500',
    FP: 'bg-green-500',
    CWC: 'bg-yellow-500',
    REV: 'bg-gray-500',
};

/**
 * AttendanceDetailsSection - Displays and allows editing of attendance and service details for a claim
 *
 * Shows:
 * - Type of attendance (NHIS codes: EAE, ANC, PNC, etc.)
 * - Date of attendance and discharge
 * - Type of service (OPD/IPD)
 * - Specialty attended (selectable NHIS specialty codes)
 * - Attending prescriber (editable)
 * - Claim check code
 *
 * @example
 * ```tsx
 * <AttendanceDetailsSection
 *   attendance={vettingData.attendance}
 *   onAttendanceChange={(field, value) => handleChange(field, value)}
 * />
 * ```
 */
export function AttendanceDetailsSection({
    attendance,
    onAttendanceChange,
    disabled = false,
}: AttendanceDetailsSectionProps) {
    const formatDate = (dateString: string | null) => {
        if (!dateString) return 'N/A';
        return new Date(dateString).toLocaleDateString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        });
    };

    const attendanceTypeOptions = attendance.attendance_type_options || {
        EAE: 'Emergency/Acute Episode',
        ANC: 'Antenatal Care',
        PNC: 'Postnatal Care',
        FP: 'Family Planning',
        CWC: 'Child Welfare Clinic',
        REV: 'Review/Follow-up',
    };

    const serviceTypeOptions = attendance.service_type_options || {
        OPD: 'Outpatient',
        IPD: 'Inpatient',
    };

    const specialtyOptions = attendance.specialty_options || {
        OPDC: 'OPD Clinic (General)',
        DENT: 'Dental',
        ENT: 'ENT',
        EYE: 'Eye/Ophthalmology',
        GYNA: 'Gynaecology',
        OBST: 'Obstetrics',
        PAED: 'Paediatrics',
        SURG: 'Surgery',
        ORTH: 'Orthopaedics',
        PSYC: 'Psychiatry',
        DERM: 'Dermatology',
        PHYS: 'Physiotherapy',
        MEDI: 'Internal Medicine',
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
                        <label className="text-sm text-gray-500 dark:text-gray-400">
                            Type of Attendance
                        </label>
                        {onAttendanceChange && !disabled ? (
                            <Select
                                value={attendance.type_of_attendance || 'EAE'}
                                onValueChange={(value) =>
                                    onAttendanceChange(
                                        'type_of_attendance',
                                        value,
                                    )
                                }
                            >
                                <SelectTrigger className="mt-1">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(attendanceTypeOptions).map(
                                        ([code, label]) => (
                                            <SelectItem key={code} value={code}>
                                                {code} - {label}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                        ) : (
                            <div className="mt-1">
                                <Badge
                                    className={
                                        attendanceTypeColors[
                                            attendance.type_of_attendance
                                        ] || 'bg-gray-500'
                                    }
                                >
                                    {attendance.type_of_attendance} -{' '}
                                    {attendanceTypeOptions[
                                        attendance.type_of_attendance
                                    ] || attendance.type_of_attendance}
                                </Badge>
                            </div>
                        )}
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
                        <label className="text-sm text-gray-500 dark:text-gray-400">
                            Type of Service
                        </label>
                        {onAttendanceChange && !disabled ? (
                            <Select
                                value={attendance.type_of_service || 'OPD'}
                                onValueChange={(value) =>
                                    onAttendanceChange('type_of_service', value)
                                }
                            >
                                <SelectTrigger className="mt-1">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.entries(serviceTypeOptions).map(
                                        ([code, label]) => (
                                            <SelectItem key={code} value={code}>
                                                {code} - {label}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                        ) : (
                            <div className="mt-1">
                                <Badge variant="outline">
                                    {attendance.type_of_service} -{' '}
                                    {serviceTypeOptions[
                                        attendance.type_of_service
                                    ] || attendance.type_of_service}
                                </Badge>
                            </div>
                        )}
                    </div>

                    {/* Specialty Attended */}
                    <div>
                        <label className="text-sm text-gray-500 dark:text-gray-400">
                            Specialty Attended
                        </label>
                        {onAttendanceChange && !disabled ? (
                            <Select
                                value={attendance.specialty_attended || 'OPDC'}
                                onValueChange={(value) =>
                                    onAttendanceChange(
                                        'specialty_attended',
                                        value,
                                    )
                                }
                            >
                                <SelectTrigger className="mt-1">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent
                                    className="max-h-60 overflow-y-auto"
                                    position="popper"
                                    sideOffset={4}
                                >
                                    {Object.entries(specialtyOptions).map(
                                        ([code, label]) => (
                                            <SelectItem key={code} value={code}>
                                                {code} - {label}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                        ) : (
                            <p className="flex items-center gap-2 font-medium text-gray-900 dark:text-gray-100">
                                <Stethoscope
                                    className="h-4 w-4 text-gray-400"
                                    aria-hidden="true"
                                />
                                {attendance.specialty_attended || 'N/A'} -{' '}
                                {specialtyOptions[
                                    attendance.specialty_attended || ''
                                ] || ''}
                            </p>
                        )}
                    </div>

                    {/* Attending Prescriber */}
                    <div>
                        <label className="text-sm text-gray-500 dark:text-gray-400">
                            Attending Prescriber
                        </label>
                        {onAttendanceChange && !disabled ? (
                            <Input
                                className="mt-1"
                                value={attendance.attending_prescriber || ''}
                                onChange={(e) =>
                                    onAttendanceChange(
                                        'attending_prescriber',
                                        e.target.value,
                                    )
                                }
                                placeholder="Enter prescriber name"
                            />
                        ) : (
                            <p className="flex items-center gap-2 font-medium text-gray-900 dark:text-gray-100">
                                <UserCheck
                                    className="h-4 w-4 text-gray-400"
                                    aria-hidden="true"
                                />
                                {attendance.attending_prescriber || 'N/A'}
                            </p>
                        )}
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
