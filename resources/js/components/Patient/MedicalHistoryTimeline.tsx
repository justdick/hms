import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';
import {
    AlertTriangle,
    ChevronDown,
    ChevronUp,
    Clock,
    FileText,
    Heart,
    Pill,
    Stethoscope,
    User,
} from 'lucide-react';
import { useState } from 'react';

interface Consultation {
    id: number;
    date: string;
    department: string;
    doctor: string;
    chief_complaint: string;
    diagnosis: string;
    status: string;
}

interface Medication {
    id: number;
    name: string;
    dosage: string;
    prescribed_date: string;
    status: 'active' | 'discontinued' | 'completed';
    prescribing_doctor: string;
}

interface Allergy {
    id: number;
    allergen: string;
    reaction: string;
    severity: 'mild' | 'moderate' | 'severe';
    date_noted: string;
}

interface FamilyHistory {
    id: number;
    relationship: string;
    condition: string;
    age_of_onset?: number;
    notes?: string;
}

interface MedicalHistoryTimelineProps {
    patientId: number;
    consultations: Consultation[];
    medications: Medication[];
    allergies: Allergy[];
    familyHistory: FamilyHistory[];
    vitalsHistory?: Array<{
        date: string;
        temperature: number;
        bp_systolic: number;
        bp_diastolic: number;
        heart_rate: number;
    }>;
}

export default function MedicalHistoryTimeline({
    consultations,
    medications,
    allergies,
    familyHistory,
    vitalsHistory = [],
}: MedicalHistoryTimelineProps) {
    const [expandedConsultations, setExpandedConsultations] = useState<
        Set<number>
    >(new Set());
    const [showAllMedications, setShowAllMedications] = useState(false);
    const [activeTab, setActiveTab] = useState<
        'timeline' | 'medications' | 'allergies' | 'family'
    >('timeline');

    const toggleConsultation = (id: number) => {
        const newExpanded = new Set(expandedConsultations);
        if (newExpanded.has(id)) {
            newExpanded.delete(id);
        } else {
            newExpanded.add(id);
        }
        setExpandedConsultations(newExpanded);
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    };

    const formatDateTime = (dateString: string) => {
        return new Date(dateString).toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const getStatusBadge = (status: string) => {
        const variants = {
            active: 'default',
            discontinued: 'secondary',
            completed: 'outline',
            in_progress: 'default',
            completed_consultation: 'outline',
        } as const;

        return (
            <Badge
                variant={
                    variants[status as keyof typeof variants] || 'secondary'
                }
            >
                {status.replace('_', ' ').toUpperCase()}
            </Badge>
        );
    };

    const getSeverityColor = (severity: string) => {
        const colors = {
            mild: 'text-yellow-600 bg-yellow-50 border-yellow-200',
            moderate: 'text-orange-600 bg-orange-50 border-orange-200',
            severe: 'text-red-600 bg-red-50 border-red-200',
        };
        return colors[severity as keyof typeof colors] || colors.mild;
    };

    const activeMedications = medications.filter(
        (med) => med.status === 'active',
    );
    const inactiveMedications = medications.filter(
        (med) => med.status !== 'active',
    );

    return (
        <div className="space-y-6">
            {/* Alert Section */}
            {allergies.length > 0 && (
                <Card className="border-red-200 bg-red-50/50">
                    <CardHeader className="pb-3">
                        <CardTitle className="flex items-center gap-2 text-red-700">
                            <AlertTriangle className="h-5 w-5" />
                            Known Allergies
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-wrap gap-2">
                            {allergies.map((allergy) => (
                                <Badge
                                    key={allergy.id}
                                    className={cn(
                                        'border',
                                        getSeverityColor(allergy.severity),
                                    )}
                                    variant="outline"
                                >
                                    <AlertTriangle className="mr-1 h-3 w-3" />
                                    {allergy.allergen} ({allergy.reaction})
                                </Badge>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Navigation Tabs */}
            <div className="flex border-b border-gray-200 dark:border-gray-700">
                {[
                    { id: 'timeline', label: 'Timeline', icon: Clock },
                    { id: 'medications', label: 'Medications', icon: Pill },
                    {
                        id: 'allergies',
                        label: 'Allergies',
                        icon: AlertTriangle,
                    },
                    { id: 'family', label: 'Family History', icon: Heart },
                ].map(({ id, label, icon: Icon }) => (
                    <button
                        key={id}
                        onClick={() => setActiveTab(id as any)}
                        className={cn(
                            'flex items-center gap-2 border-b-2 px-4 py-2 text-sm font-medium transition-colors',
                            activeTab === id
                                ? 'border-blue-500 text-blue-600'
                                : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700',
                        )}
                    >
                        <Icon className="h-4 w-4" />
                        {label}
                    </button>
                ))}
            </div>

            {/* Timeline Tab */}
            {activeTab === 'timeline' && (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Clock className="h-5 w-5" />
                            Consultation Timeline
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {consultations.length === 0 ? (
                            <div className="py-8 text-center text-gray-500">
                                <FileText className="mx-auto mb-4 h-12 w-12 text-gray-300" />
                                <p>No consultation history available</p>
                            </div>
                        ) : (
                            <div className="relative">
                                {/* Timeline line */}
                                <div className="absolute top-0 bottom-0 left-4 w-0.5 bg-gray-200 dark:bg-gray-700" />

                                <div className="space-y-6">
                                    {consultations.map(
                                        (consultation, index) => (
                                            <div
                                                key={consultation.id}
                                                className="relative flex gap-4"
                                            >
                                                {/* Timeline dot */}
                                                <div className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full border-2 border-blue-500 bg-blue-100">
                                                    <Stethoscope className="h-4 w-4 text-blue-600" />
                                                </div>

                                                {/* Content */}
                                                <div className="min-w-0 flex-1">
                                                    <Collapsible
                                                        open={expandedConsultations.has(
                                                            consultation.id,
                                                        )}
                                                        onOpenChange={() =>
                                                            toggleConsultation(
                                                                consultation.id,
                                                            )
                                                        }
                                                    >
                                                        <CollapsibleTrigger
                                                            asChild
                                                        >
                                                            <div className="cursor-pointer rounded-lg border bg-white p-4 transition-shadow hover:shadow-md dark:bg-gray-800">
                                                                <div className="mb-2 flex items-start justify-between">
                                                                    <div>
                                                                        <h3 className="font-semibold text-gray-900 dark:text-gray-100">
                                                                            {
                                                                                consultation.chief_complaint
                                                                            }
                                                                        </h3>
                                                                        <p className="text-sm text-gray-600 dark:text-gray-400">
                                                                            {formatDateTime(
                                                                                consultation.date,
                                                                            )}{' '}
                                                                            •
                                                                            Dr.{' '}
                                                                            {
                                                                                consultation.doctor
                                                                            }
                                                                        </p>
                                                                    </div>
                                                                    <div className="flex items-center gap-2">
                                                                        {getStatusBadge(
                                                                            consultation.status,
                                                                        )}
                                                                        {expandedConsultations.has(
                                                                            consultation.id,
                                                                        ) ? (
                                                                            <ChevronUp className="h-4 w-4 text-gray-400" />
                                                                        ) : (
                                                                            <ChevronDown className="h-4 w-4 text-gray-400" />
                                                                        )}
                                                                    </div>
                                                                </div>

                                                                <div className="flex items-center gap-4 text-sm text-gray-600 dark:text-gray-400">
                                                                    <div className="flex items-center gap-1">
                                                                        <User className="h-4 w-4" />
                                                                        {
                                                                            consultation.department
                                                                        }
                                                                    </div>
                                                                    {consultation.diagnosis && (
                                                                        <div className="flex items-center gap-1">
                                                                            <FileText className="h-4 w-4" />
                                                                            {
                                                                                consultation.diagnosis
                                                                            }
                                                                        </div>
                                                                    )}
                                                                </div>
                                                            </div>
                                                        </CollapsibleTrigger>

                                                        <CollapsibleContent>
                                                            <div className="mt-4 rounded-lg border bg-gray-50 p-4 dark:bg-gray-700">
                                                                <div className="grid grid-cols-1 gap-4 text-sm md:grid-cols-2">
                                                                    <div>
                                                                        <h4 className="mb-2 font-medium text-gray-700 dark:text-gray-300">
                                                                            Consultation
                                                                            Details
                                                                        </h4>
                                                                        <p>
                                                                            <strong>
                                                                                Department:
                                                                            </strong>{' '}
                                                                            {
                                                                                consultation.department
                                                                            }
                                                                        </p>
                                                                        <p>
                                                                            <strong>
                                                                                Doctor:
                                                                            </strong>{' '}
                                                                            Dr.{' '}
                                                                            {
                                                                                consultation.doctor
                                                                            }
                                                                        </p>
                                                                        <p>
                                                                            <strong>
                                                                                Status:
                                                                            </strong>{' '}
                                                                            {
                                                                                consultation.status
                                                                            }
                                                                        </p>
                                                                    </div>
                                                                    <div>
                                                                        <h4 className="mb-2 font-medium text-gray-700 dark:text-gray-300">
                                                                            Clinical
                                                                            Summary
                                                                        </h4>
                                                                        <p>
                                                                            <strong>
                                                                                Chief
                                                                                Complaint:
                                                                            </strong>{' '}
                                                                            {
                                                                                consultation.chief_complaint
                                                                            }
                                                                        </p>
                                                                        {consultation.diagnosis && (
                                                                            <p>
                                                                                <strong>
                                                                                    Diagnosis:
                                                                                </strong>{' '}
                                                                                {
                                                                                    consultation.diagnosis
                                                                                }
                                                                            </p>
                                                                        )}
                                                                    </div>
                                                                </div>
                                                                <div className="mt-4 flex justify-end">
                                                                    <Button
                                                                        variant="outline"
                                                                        size="sm"
                                                                    >
                                                                        View
                                                                        Full
                                                                        Record
                                                                    </Button>
                                                                </div>
                                                            </div>
                                                        </CollapsibleContent>
                                                    </Collapsible>
                                                </div>
                                            </div>
                                        ),
                                    )}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            )}

            {/* Medications Tab */}
            {activeTab === 'medications' && (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Pill className="h-5 w-5" />
                                Current Medications ({activeMedications.length})
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    setShowAllMedications(!showAllMedications)
                                }
                            >
                                {showAllMedications
                                    ? 'Show Active Only'
                                    : 'Show All History'}
                            </Button>
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {/* Active Medications */}
                            {activeMedications.map((medication) => (
                                <div
                                    key={medication.id}
                                    className="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-950/30"
                                >
                                    <div className="mb-2 flex items-start justify-between">
                                        <div>
                                            <h3 className="font-semibold text-gray-900 dark:text-gray-100">
                                                {medication.name}
                                            </h3>
                                            <p className="text-sm text-gray-600 dark:text-gray-400">
                                                {medication.dosage}
                                            </p>
                                        </div>
                                        {getStatusBadge(medication.status)}
                                    </div>
                                    <div className="text-sm text-gray-600 dark:text-gray-400">
                                        <p>
                                            Prescribed:{' '}
                                            {formatDate(
                                                medication.prescribed_date,
                                            )}
                                        </p>
                                        <p>
                                            Doctor: Dr.{' '}
                                            {medication.prescribing_doctor}
                                        </p>
                                    </div>
                                </div>
                            ))}

                            {/* Inactive Medications */}
                            {showAllMedications &&
                                inactiveMedications.length > 0 && (
                                    <>
                                        <Separator />
                                        <h4 className="font-medium text-gray-700 dark:text-gray-300">
                                            Medication History
                                        </h4>
                                        {inactiveMedications.map(
                                            (medication) => (
                                                <div
                                                    key={medication.id}
                                                    className="rounded-lg border bg-gray-50 p-4 dark:bg-gray-800"
                                                >
                                                    <div className="mb-2 flex items-start justify-between">
                                                        <div>
                                                            <h3 className="font-semibold text-gray-900 dark:text-gray-100">
                                                                {
                                                                    medication.name
                                                                }
                                                            </h3>
                                                            <p className="text-sm text-gray-600 dark:text-gray-400">
                                                                {
                                                                    medication.dosage
                                                                }
                                                            </p>
                                                        </div>
                                                        {getStatusBadge(
                                                            medication.status,
                                                        )}
                                                    </div>
                                                    <div className="text-sm text-gray-600 dark:text-gray-400">
                                                        <p>
                                                            Prescribed:{' '}
                                                            {formatDate(
                                                                medication.prescribed_date,
                                                            )}
                                                        </p>
                                                        <p>
                                                            Doctor: Dr.{' '}
                                                            {
                                                                medication.prescribing_doctor
                                                            }
                                                        </p>
                                                    </div>
                                                </div>
                                            ),
                                        )}
                                    </>
                                )}

                            {medications.length === 0 && (
                                <div className="py-8 text-center text-gray-500">
                                    <Pill className="mx-auto mb-4 h-12 w-12 text-gray-300" />
                                    <p>No medications on record</p>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Allergies Tab */}
            {activeTab === 'allergies' && (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <AlertTriangle className="h-5 w-5" />
                            Allergies & Adverse Reactions
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {allergies.length === 0 ? (
                            <div className="py-8 text-center text-gray-500">
                                <AlertTriangle className="mx-auto mb-4 h-12 w-12 text-gray-300" />
                                <p>No known allergies</p>
                            </div>
                        ) : (
                            <div className="space-y-4">
                                {allergies.map((allergy) => (
                                    <div
                                        key={allergy.id}
                                        className={cn(
                                            'rounded-lg border p-4',
                                            getSeverityColor(allergy.severity),
                                        )}
                                    >
                                        <div className="mb-2 flex items-start justify-between">
                                            <div>
                                                <h3 className="font-semibold">
                                                    {allergy.allergen}
                                                </h3>
                                                <p className="text-sm">
                                                    Reaction: {allergy.reaction}
                                                </p>
                                            </div>
                                            <Badge
                                                className={getSeverityColor(
                                                    allergy.severity,
                                                )}
                                                variant="outline"
                                            >
                                                {allergy.severity.toUpperCase()}
                                            </Badge>
                                        </div>
                                        <div className="text-sm">
                                            <p>
                                                Noted:{' '}
                                                {formatDate(allergy.date_noted)}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            )}

            {/* Family History Tab */}
            {activeTab === 'family' && (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Heart className="h-5 w-5" />
                            Family Medical History
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {familyHistory.length === 0 ? (
                            <div className="py-8 text-center text-gray-500">
                                <Heart className="mx-auto mb-4 h-12 w-12 text-gray-300" />
                                <p>No family history recorded</p>
                            </div>
                        ) : (
                            <div className="space-y-4">
                                {familyHistory.map((history) => (
                                    <div
                                        key={history.id}
                                        className="rounded-lg border p-4"
                                    >
                                        <div className="mb-2 flex items-start justify-between">
                                            <div>
                                                <h3 className="font-semibold text-gray-900 dark:text-gray-100">
                                                    {history.relationship}
                                                </h3>
                                                <p className="text-sm text-gray-600 dark:text-gray-400">
                                                    {history.condition}
                                                    {history.age_of_onset &&
                                                        ` (Age of onset: ${history.age_of_onset})`}
                                                </p>
                                            </div>
                                        </div>
                                        {history.notes && (
                                            <div className="text-sm text-gray-600 dark:text-gray-400">
                                                <p>{history.notes}</p>
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
