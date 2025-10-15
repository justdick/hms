import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Form } from '@/components/ui/form';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import admissions from '@/routes/admissions';
import { Head, router } from '@inertiajs/react';
import {
    Activity,
    AlertCircle,
    ArrowLeft,
    Calendar,
    ClipboardList,
    FileText,
    Stethoscope,
    User,
} from 'lucide-react';
import { useState } from 'react';

interface Patient {
    id: number;
    patient_number: string;
    first_name: string;
    last_name: string;
    date_of_birth?: string;
    gender?: string;
    age?: number;
}

interface Ward {
    id: number;
    name: string;
    ward_type?: string;
}

interface User {
    id: number;
    name: string;
}

interface Diagnosis {
    id: number;
    icd_code: string;
    icd_version: string;
    diagnosis_name: string;
    diagnosis_type: string;
    diagnosed_at: string;
    diagnosed_by: User;
    clinical_notes?: string;
    is_active: boolean;
}

interface VitalSign {
    id: number;
    temperature?: number;
    blood_pressure_systolic?: number;
    blood_pressure_diastolic?: number;
    pulse_rate?: number;
    respiratory_rate?: number;
    oxygen_saturation?: number;
    recorded_at: string;
    recorded_by?: User;
}

interface Consultation {
    id: number;
    presenting_complaint?: string;
    assessment_notes?: string;
}

interface PatientAdmission {
    id: number;
    admission_number: string;
    admission_reason?: string;
    admitted_at: string;
    status: string;
    patient: Patient;
    ward: Ward;
    admission_consultation?: Consultation;
    active_diagnoses?: Diagnosis[];
    vital_signs?: VitalSign[];
}

interface WardRoundCreateProps {
    admission: PatientAdmission;
    dayNumber: number;
    encounterType: 'ward_round';
}

export default function WardRoundCreate({
    admission,
    dayNumber,
    encounterType,
}: WardRoundCreateProps) {
    const [formData, setFormData] = useState({
        presenting_complaint: '',
        history_presenting_complaint: '',
        on_direct_questioning: '',
        examination_findings: '',
        assessment_notes: '',
        plan_notes: '',
        patient_status: 'stable',
        round_type: 'daily_round',
    });

    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmitting(true);

        router.post(
            admissions.wardRounds.store.url(admission),
            formData,
            {
                onSuccess: () => {
                    // Will redirect back to ward patient show
                },
                onError: () => {
                    setIsSubmitting(false);
                },
                onFinish: () => {
                    setIsSubmitting(false);
                },
            }
        );
    };

    const handleChange = (
        field: string,
        value: string
    ) => {
        setFormData((prev) => ({
            ...prev,
            [field]: value,
        }));
    };

    return (
        <AppLayout>
            <Head title={`Ward Round - Day ${dayNumber}`} />

            <div className="container mx-auto max-w-7xl space-y-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => window.history.back()}
                        >
                            <ArrowLeft className="h-4 w-4" />
                        </Button>
                        <div>
                            <h1 className="text-3xl font-bold">Ward Round</h1>
                            <p className="text-muted-foreground">
                                {admission.patient.first_name}{' '}
                                {admission.patient.last_name} •{' '}
                                {admission.patient.patient_number}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Badge variant="default" className="text-lg">
                            Day {dayNumber}
                        </Badge>
                        <Badge variant="outline">
                            {admission.ward.name}
                        </Badge>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Sidebar - Patient Info & Context */}
                    <div className="space-y-6 lg:col-span-1">
                        {/* Patient Summary */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <User className="h-4 w-4" />
                                    Patient Information
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2 text-sm">
                                <div>
                                    <span className="font-medium">Age:</span>{' '}
                                    {admission.patient.age || 'N/A'}
                                </div>
                                <div>
                                    <span className="font-medium">Gender:</span>{' '}
                                    {admission.patient.gender || 'N/A'}
                                </div>
                                <div>
                                    <span className="font-medium">Admission #:</span>{' '}
                                    {admission.admission_number}
                                </div>
                                <div>
                                    <span className="font-medium">Admitted:</span>{' '}
                                    {new Date(admission.admitted_at).toLocaleDateString()}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Active Diagnoses */}
                        {admission.active_diagnoses && admission.active_diagnoses.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <ClipboardList className="h-4 w-4" />
                                        Active Diagnoses
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    {admission.active_diagnoses.map((diagnosis) => (
                                        <div
                                            key={diagnosis.id}
                                            className="rounded-md border p-3 text-sm"
                                        >
                                            <div className="font-medium">
                                                {diagnosis.diagnosis_name}
                                            </div>
                                            <div className="mt-1 text-xs text-muted-foreground">
                                                ICD-{diagnosis.icd_version}: {diagnosis.icd_code}
                                            </div>
                                            <Badge variant="secondary" className="mt-2 text-xs">
                                                {diagnosis.diagnosis_type}
                                            </Badge>
                                        </div>
                                    ))}
                                </CardContent>
                            </Card>
                        )}

                        {/* Recent Vitals */}
                        {admission.vital_signs && admission.vital_signs.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Activity className="h-4 w-4" />
                                        Latest Vitals
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-2 text-sm">
                                    {admission.vital_signs[0].temperature && (
                                        <div className="flex justify-between">
                                            <span>Temperature:</span>
                                            <span className="font-medium">
                                                {admission.vital_signs[0].temperature}°C
                                            </span>
                                        </div>
                                    )}
                                    {admission.vital_signs[0].blood_pressure_systolic && (
                                        <div className="flex justify-between">
                                            <span>BP:</span>
                                            <span className="font-medium">
                                                {admission.vital_signs[0].blood_pressure_systolic}/
                                                {admission.vital_signs[0].blood_pressure_diastolic} mmHg
                                            </span>
                                        </div>
                                    )}
                                    {admission.vital_signs[0].pulse_rate && (
                                        <div className="flex justify-between">
                                            <span>Pulse:</span>
                                            <span className="font-medium">
                                                {admission.vital_signs[0].pulse_rate} bpm
                                            </span>
                                        </div>
                                    )}
                                    {admission.vital_signs[0].oxygen_saturation && (
                                        <div className="flex justify-between">
                                            <span>SpO2:</span>
                                            <span className="font-medium">
                                                {admission.vital_signs[0].oxygen_saturation}%
                                            </span>
                                        </div>
                                    )}
                                    <div className="mt-3 border-t pt-2 text-xs text-muted-foreground">
                                        Recorded {new Date(admission.vital_signs[0].recorded_at).toLocaleString()}
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    {/* Main Form */}
                    <div className="lg:col-span-2">
                        <form onSubmit={handleSubmit}>
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Stethoscope className="h-5 w-5" />
                                        Ward Round Documentation
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-6">
                                    {/* Interval History / New Concerns */}
                                    <div className="space-y-2">
                                        <Label htmlFor="presenting_complaint">
                                            New Concerns / Interval Update
                                        </Label>
                                        <Textarea
                                            id="presenting_complaint"
                                            value={formData.presenting_complaint}
                                            onChange={(e) =>
                                                handleChange('presenting_complaint', e.target.value)
                                            }
                                            placeholder="Any new symptoms, complaints, or concerns since last review..."
                                            rows={3}
                                        />
                                    </div>

                                    {/* Progress Since Last Review */}
                                    <div className="space-y-2">
                                        <Label htmlFor="history_presenting_complaint">
                                            Progress / Clinical Course
                                        </Label>
                                        <Textarea
                                            id="history_presenting_complaint"
                                            value={formData.history_presenting_complaint}
                                            onChange={(e) =>
                                                handleChange('history_presenting_complaint', e.target.value)
                                            }
                                            placeholder="How is the patient progressing? Response to treatment? Any changes in condition..."
                                            rows={4}
                                        />
                                    </div>

                                    {/* Review of Systems */}
                                    <div className="space-y-2">
                                        <Label htmlFor="on_direct_questioning">
                                            Review of Systems
                                        </Label>
                                        <Textarea
                                            id="on_direct_questioning"
                                            value={formData.on_direct_questioning}
                                            onChange={(e) =>
                                                handleChange('on_direct_questioning', e.target.value)
                                            }
                                            placeholder="Systematic review of body systems..."
                                            rows={3}
                                        />
                                    </div>

                                    {/* Physical Examination */}
                                    <div className="space-y-2">
                                        <Label htmlFor="examination_findings">
                                            Physical Examination
                                        </Label>
                                        <Textarea
                                            id="examination_findings"
                                            value={formData.examination_findings}
                                            onChange={(e) =>
                                                handleChange('examination_findings', e.target.value)
                                            }
                                            placeholder="General appearance, vital signs, systemic examination findings..."
                                            rows={5}
                                        />
                                    </div>

                                    {/* Clinical Assessment */}
                                    <div className="space-y-2">
                                        <Label htmlFor="assessment_notes" className="flex items-center gap-1">
                                            Clinical Assessment
                                            <span className="text-destructive">*</span>
                                        </Label>
                                        <Textarea
                                            id="assessment_notes"
                                            value={formData.assessment_notes}
                                            onChange={(e) =>
                                                handleChange('assessment_notes', e.target.value)
                                            }
                                            placeholder="Clinical impression, diagnosis, assessment of current condition..."
                                            rows={5}
                                            required
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            Required field - minimum 10 characters
                                        </p>
                                    </div>

                                    {/* Plan */}
                                    <div className="space-y-2">
                                        <Label htmlFor="plan_notes">
                                            Management Plan
                                        </Label>
                                        <Textarea
                                            id="plan_notes"
                                            value={formData.plan_notes}
                                            onChange={(e) =>
                                                handleChange('plan_notes', e.target.value)
                                            }
                                            placeholder="Treatment plan, investigations needed, medication adjustments, discharge planning..."
                                            rows={5}
                                        />
                                    </div>

                                    {/* Patient Status */}
                                    <div className="space-y-2">
                                        <Label htmlFor="patient_status" className="flex items-center gap-1">
                                            Patient Status
                                            <span className="text-destructive">*</span>
                                        </Label>
                                        <Select
                                            value={formData.patient_status}
                                            onValueChange={(value) =>
                                                handleChange('patient_status', value)
                                            }
                                        >
                                            <SelectTrigger id="patient_status">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="improving">
                                                    ✓ Improving
                                                </SelectItem>
                                                <SelectItem value="stable">
                                                    = Stable
                                                </SelectItem>
                                                <SelectItem value="deteriorating">
                                                    ↓ Deteriorating
                                                </SelectItem>
                                                <SelectItem value="critical">
                                                    ⚠ Critical
                                                </SelectItem>
                                                <SelectItem value="discharge_ready">
                                                    → Discharge Ready
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    {/* Round Type */}
                                    <div className="space-y-2">
                                        <Label htmlFor="round_type">
                                            Round Type
                                        </Label>
                                        <Select
                                            value={formData.round_type}
                                            onValueChange={(value) =>
                                                handleChange('round_type', value)
                                            }
                                        >
                                            <SelectTrigger id="round_type">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="daily_round">
                                                    Daily Round
                                                </SelectItem>
                                                <SelectItem value="specialist_consult">
                                                    Specialist Consultation
                                                </SelectItem>
                                                <SelectItem value="procedure_note">
                                                    Procedure Note
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    {/* TODO: Add sections for lab orders, prescriptions, and diagnoses */}
                                    <div className="rounded-md border border-dashed p-4 text-center text-sm text-muted-foreground">
                                        <FileText className="mx-auto mb-2 h-8 w-8 opacity-50" />
                                        <p>Lab orders, prescriptions, and diagnosis management</p>
                                        <p className="text-xs">Coming soon...</p>
                                    </div>

                                    {/* Actions */}
                                    <div className="flex items-center justify-between border-t pt-6">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => window.history.back()}
                                        >
                                            Cancel
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={isSubmitting || formData.assessment_notes.length < 10}
                                        >
                                            {isSubmitting ? 'Saving...' : 'Complete Ward Round'}
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        </form>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
