import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Form } from '@inertiajs/react';
import { format, isPast } from 'date-fns';
import {
    AlertTriangle,
    CheckCircle2,
    Clock,
    Edit2,
    Pill,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

interface Drug {
    id: number;
    name: string;
    strength?: string;
    form?: string;
}

interface Prescription {
    id: number;
    medication_name: string;
    dosage?: string;
    dose_quantity?: string;
    frequency?: string;
    duration?: string;
    route?: string;
    drug?: Drug;
}

interface User {
    id: number;
    name: string;
}

interface MedicationAdministration {
    id: number;
    prescription: Prescription;
    scheduled_time: string;
    status:
        | 'scheduled'
        | 'given'
        | 'held'
        | 'refused'
        | 'omitted'
        | 'cancelled';
    dosage_given?: string;
    route?: string;
    notes?: string;
    administered_at?: string;
    administered_by?: User;
    is_adjusted?: boolean;
}

interface MedicationAdministrationCardProps {
    medication: MedicationAdministration;
    onAdminister: (administrationId: number, data: any) => void;
    onHold: (administrationId: number, notes: string) => void;
    onRefuse: (administrationId: number) => void;
    onAdjustTime: (
        administrationId: number,
        newTime: string,
        reason?: string,
    ) => void;
}

export function MedicationAdministrationCard({
    medication,
    onAdminister,
    onHold,
    onRefuse,
    onAdjustTime,
}: MedicationAdministrationCardProps) {
    const [administerDialogOpen, setAdministerDialogOpen] = useState(false);
    const [holdDialogOpen, setHoldDialogOpen] = useState(false);
    const [adjustTimeDialogOpen, setAdjustTimeDialogOpen] = useState(false);

    const scheduledTime = new Date(medication.scheduled_time);
    const isOverdue =
        isPast(scheduledTime) && medication.status === 'scheduled';

    const getStatusBadge = () => {
        switch (medication.status) {
            case 'given':
                return (
                    <Badge variant="default" className="bg-green-600">
                        <CheckCircle2 className="mr-1 h-3 w-3" />
                        Given
                    </Badge>
                );
            case 'held':
                return (
                    <Badge variant="secondary">
                        <AlertTriangle className="mr-1 h-3 w-3" />
                        Held
                    </Badge>
                );
            case 'refused':
                return (
                    <Badge variant="destructive">
                        <X className="mr-1 h-3 w-3" />
                        Refused
                    </Badge>
                );
            case 'cancelled':
                return <Badge variant="outline">Cancelled</Badge>;
            case 'omitted':
                return <Badge variant="outline">Omitted</Badge>;
            default:
                return null;
        }
    };

    return (
        <>
            <Card
                className={
                    isOverdue
                        ? 'border-red-300 bg-red-50 dark:border-red-800 dark:bg-red-950/20'
                        : medication.status === 'given'
                          ? 'border-green-300 bg-green-50 dark:border-green-800 dark:bg-green-950/20'
                          : ''
                }
            >
                <CardContent cl