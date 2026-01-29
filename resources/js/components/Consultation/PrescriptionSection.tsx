'use client';

import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';
import { Layers, ListPlus } from 'lucide-react';
import PrescriptionFormSection from './PrescriptionFormSection';
import BatchPrescriptionForm from './BatchPrescriptionForm';

interface Drug {
    id: number;
    name: string;
    form: string;
    strength?: string;
    generic_name?: string;
    brand_name?: string;
    unit_type: string;
    bottle_size?: number;
    unit_price?: number | null;
}

interface Prescription {
    id: number;
    medication_name: string;
    frequency: string;
    duration: string;
    dose_quantity?: string;
    quantity_to_dispense?: number;
    instructions?: string;
    status: string;
    drug_id?: number;
}

interface PreviousPrescription {
    id: number;
    medication_name: string;
    dose_quantity?: string;
    frequency: string;
    duration: string;
    instructions?: string;
    status: string;
    drug?: Drug;
    consultation: {
        id: number;
        started_at: string;
        doctor: { id: number; name: string };
        patient_checkin: { department: { id: number; name: string } };
    };
}

interface Props {
    drugs: Drug[];
    prescriptions: Prescription[];
    prescriptionData: any;
    setPrescriptionData: (field: string, value: any) => void;
    onSubmit: (e: React.FormEvent) => void;
    onDelete: (id: number) => void;
    onEdit: (prescription: Prescription) => void;
    onCancelEdit: () => void;
    onUpdate: (e: React.FormEvent) => void;
    editingPrescription: Prescription | null;
    processing: boolean;
    consultationId: number;
    isEditable?: boolean;
    consultationStatus?: string;
    previousPrescriptions?: PreviousPrescription[];
    // For batch mode
    prescribableType?: 'consultation' | 'ward_round';
    prescribableId?: number;
    admissionId?: number; // Required for ward_round type
    // Card wrapper control
    withCard?: boolean;
    cardTitle?: string;
}

export default function PrescriptionSection({
    drugs,
    prescriptions,
    prescriptionData,
    setPrescriptionData,
    onSubmit,
    onDelete,
    onEdit,
    onCancelEdit,
    onUpdate,
    editingPrescription,
    processing,
    consultationId,
    isEditable,
    consultationStatus,
    previousPrescriptions = [],
    prescribableType = 'consultation',
    prescribableId,
    admissionId,
    withCard = false,
    cardTitle = 'Prescriptions',
}: Props) {
    const [batchMode, setBatchMode] = useState(false);
    const canEdit = isEditable ?? consultationStatus === 'in_progress';

    // Compact toggle for inside the form header
    const modeToggle = canEdit && (
        <div className="flex items-center gap-1.5 rounded-md border bg-white/80 px-2 py-1 dark:bg-gray-800/80">
            <ListPlus className="h-3.5 w-3.5 text-muted-foreground" />
            <Switch
                checked={batchMode}
                onCheckedChange={setBatchMode}
                aria-label="Toggle batch mode"
                className="scale-90"
            />
            <Layers className="h-3.5 w-3.5 text-muted-foreground" />
            <span className="text-xs text-muted-foreground">
                {batchMode ? 'Batch' : 'Single'}
            </span>
        </div>
    );

    const content = batchMode ? (
        <BatchPrescriptionForm
            drugs={drugs}
            existingPrescriptions={prescriptions}
            prescribableType={prescribableType}
            prescribableId={prescribableId ?? consultationId}
            admissionId={admissionId}
            isEditable={canEdit}
            onDelete={onDelete}
            onEdit={onEdit}
            headerExtra={modeToggle}
            previousPrescriptions={previousPrescriptions}
            consultationId={consultationId}
        />
    ) : (
        <PrescriptionFormSection
            drugs={drugs}
            prescriptions={prescriptions}
            prescriptionData={prescriptionData}
            setPrescriptionData={setPrescriptionData}
            onSubmit={onSubmit}
            onDelete={onDelete}
            onEdit={onEdit}
            onCancelEdit={onCancelEdit}
            onUpdate={onUpdate}
            editingPrescription={editingPrescription}
            processing={processing}
            consultationId={consultationId}
            isEditable={isEditable}
            consultationStatus={consultationStatus}
            previousPrescriptions={previousPrescriptions}
            headerExtra={modeToggle}
        />
    );

    if (withCard) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>{cardTitle}</CardTitle>
                </CardHeader>
                <CardContent>{content}</CardContent>
            </Card>
        );
    }

    return content;
}
