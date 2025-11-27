import { Badge } from '@/components/ui/badge';
import { AlertTriangle, Calendar, CreditCard, User } from 'lucide-react';
import type { PatientInfo } from './types';

interface PatientInfoSectionProps {
    patient: PatientInfo;
}

/**
 * PatientInfoSection - Displays patient demographics and NHIS member information
 *
 * Shows:
 * - Patient name, gender, date of birth
 * - Folder number
 * - NHIS member ID
 * - NHIS expiry status with warning if expired
 *
 * @example
 * ```tsx
 * <PatientInfoSection patient={vettingData.patient} />
 * ```
 */
export function PatientInfoSection({ patient }: PatientInfoSectionProps) {
    const formatDate = (dateString: string | null) => {
        if (!dateString) return 'N/A';
        return new Date(dateString).toLocaleDateString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        });
    };

    const calculateAge = (dob: string) => {
        const birthDate = new Date(dob);
        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const monthDiff = today.getMonth() - birthDate.getMonth();
        if (
            monthDiff < 0 ||
            (monthDiff === 0 && today.getDate() < birthDate.getDate())
        ) {
            age--;
        }
        return age;
    };

    return (
        <section aria-labelledby="patient-info-heading">
            <h3
                id="patient-info-heading"
                className="mb-4 flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-gray-100"
            >
                <User className="h-5 w-5" aria-hidden="true" />
                Patient Information
            </h3>

            <div className="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {/* Patient Name */}
                    <div>
                        <span className="text-sm text-gray-500 dark:text-gray-400">
                            Full Name
                        </span>
                        <p className="font-medium text-gray-900 dark:text-gray-100">
                            {patient.name}
                        </p>
                    </div>

                    {/* Gender */}
                    <div>
                        <span className="text-sm text-gray-500 dark:text-gray-400">
                            Gender
                        </span>
                        <p className="font-medium text-gray-900 capitalize dark:text-gray-100">
                            {patient.gender}
                        </p>
                    </div>

                    {/* Date of Birth & Age */}
                    <div>
                        <span className="text-sm text-gray-500 dark:text-gray-400">
                            Date of Birth
                        </span>
                        <p className="flex items-center gap-2 font-medium text-gray-900 dark:text-gray-100">
                            <Calendar
                                className="h-4 w-4 text-gray-400"
                                aria-hidden="true"
                            />
                            {formatDate(patient.date_of_birth)}
                            <span className="text-sm text-gray-500">
                                ({calculateAge(patient.date_of_birth)} years)
                            </span>
                        </p>
                    </div>

                    {/* Folder Number */}
                    <div>
                        <span className="text-sm text-gray-500 dark:text-gray-400">
                            Folder Number
                        </span>
                        <p className="font-medium text-gray-900 dark:text-gray-100">
                            {patient.folder_number || 'N/A'}
                        </p>
                    </div>

                    {/* NHIS Member ID */}
                    <div>
                        <span className="text-sm text-gray-500 dark:text-gray-400">
                            NHIS Member ID
                        </span>
                        <p className="flex items-center gap-2 font-medium text-gray-900 dark:text-gray-100">
                            <CreditCard
                                className="h-4 w-4 text-gray-400"
                                aria-hidden="true"
                            />
                            {patient.nhis_member_id || 'N/A'}
                        </p>
                    </div>

                    {/* NHIS Expiry */}
                    <div>
                        <span className="text-sm text-gray-500 dark:text-gray-400">
                            NHIS Expiry
                        </span>
                        <div className="flex items-center gap-2">
                            <p className="font-medium text-gray-900 dark:text-gray-100">
                                {formatDate(patient.nhis_expiry_date)}
                            </p>
                            {patient.is_nhis_expired && (
                                <Badge
                                    variant="destructive"
                                    className="flex items-center gap-1"
                                >
                                    <AlertTriangle
                                        className="h-3 w-3"
                                        aria-hidden="true"
                                    />
                                    Expired
                                </Badge>
                            )}
                            {!patient.is_nhis_expired &&
                                patient.nhis_expiry_date && (
                                    <Badge
                                        variant="outline"
                                        className="text-green-600"
                                    >
                                        Active
                                    </Badge>
                                )}
                        </div>
                    </div>
                </div>

                {/* Expired Warning */}
                {patient.is_nhis_expired && (
                    <div
                        className="mt-4 flex items-center gap-2 rounded-md border border-yellow-200 bg-yellow-50 p-3 text-sm text-yellow-800 dark:border-yellow-800 dark:bg-yellow-950 dark:text-yellow-200"
                        role="alert"
                    >
                        <AlertTriangle
                            className="h-4 w-4 shrink-0"
                            aria-hidden="true"
                        />
                        <span>
                            This patient's NHIS membership has expired. The
                            claim may be rejected by NHIA.
                        </span>
                    </div>
                )}
            </div>
        </section>
    );
}
