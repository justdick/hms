import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import patients from '@/routes/patients';
import { Link } from '@inertiajs/react';
import { ClipboardPlus, Eye, Phone, Shield } from 'lucide-react';

interface PatientInsurance {
    id: number;
    insurance_plan: {
        id: number;
        name: string;
    };
    membership_id: string;
    coverage_start_date: string;
    coverage_end_date: string | null;
}

interface PatientCheckin {
    id: number;
    checked_in_at: string;
    status: string;
}

interface Patient {
    id: number;
    patient_number: string;
    full_name: string;
    first_name: string;
    last_name: string;
    age: number;
    gender: 'male' | 'female';
    phone_number: string | null;
    date_of_birth: string;
    address: string | null;
    status: string;
    active_insurance: PatientInsurance | null;
    recent_checkin: PatientCheckin | null;
}

interface PatientCardProps {
    patient: Patient;
    onCheckin?: (patient: Patient) => void;
}

export default function PatientCard({ patient, onCheckin }: PatientCardProps) {
    const formatGender = (gender: string) => {
        return gender.charAt(0).toUpperCase() + gender.slice(1);
    };

    const formatLastCheckin = (checkin: PatientCheckin | null) => {
        if (!checkin) {
            return 'No recent check-in';
        }

        const checkinDate = new Date(checkin.checked_in_at);
        const now = new Date();
        const diffTime = Math.abs(now.getTime() - checkinDate.getTime());
        const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));

        if (diffDays === 0) {
            return 'Today';
        }

        if (diffDays === 1) {
            return '1 day ago';
        }

        if (diffDays < 7) {
            return `${diffDays} days ago`;
        }

        if (diffDays < 30) {
            const weeks = Math.floor(diffDays / 7);
            return `${weeks} week${weeks > 1 ? 's' : ''} ago`;
        }

        const months = Math.floor(diffDays / 30);
        return `${months} month${months > 1 ? 's' : ''} ago`;
    };

    return (
        <Card className="transition-colors hover:bg-accent/50">
            <CardContent className="flex items-center justify-between gap-4 py-4">
                <div className="flex min-w-0 flex-1 items-start gap-4">
                    <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary dark:bg-primary/20">
                        <span className="text-lg font-semibold">
                            {patient.first_name.charAt(0)}
                            {patient.last_name.charAt(0)}
                        </span>
                    </div>

                    <div className="min-w-0 flex-1 space-y-1">
                        <div className="flex items-center gap-2">
                            <h3 className="truncate font-semibold">
                                {patient.full_name}
                            </h3>
                            {patient.active_insurance && (
                                <Badge
                                    variant="secondary"
                                    className="shrink-0 gap-1"
                                >
                                    <Shield className="h-3 w-3" />
                                    Insured
                                </Badge>
                            )}
                        </div>

                        <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground">
                            <span className="font-mono text-xs">
                                {patient.patient_number}
                            </span>
                            <span>
                                {formatGender(patient.gender)}, {patient.age}{' '}
                                years
                            </span>
                            {patient.phone_number && (
                                <span className="flex items-center gap-1">
                                    <Phone className="h-3 w-3" />
                                    {patient.phone_number}
                                </span>
                            )}
                        </div>

                        <div className="text-xs text-muted-foreground">
                            Last check-in:{' '}
                            {formatLastCheckin(patient.recent_checkin)}
                        </div>
                    </div>
                </div>

                <div className="flex shrink-0 gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        asChild
                        className="gap-1.5"
                    >
                        <Link href={patients.show.url(patient.id)}>
                            <Eye className="h-4 w-4" />
                            View
                        </Link>
                    </Button>
                    {onCheckin && (
                        <Button
                            variant="default"
                            size="sm"
                            onClick={() => onCheckin(patient)}
                            className="gap-1.5"
                        >
                            <ClipboardPlus className="h-4 w-4" />
                            Check-in
                        </Button>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}
