import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Form } from '@inertiajs/react';
import {
    Activity,
    ArrowDownCircle,
    ArrowUpCircle,
    CheckCircle2,
    Edit2,
    Loader2,
    MinusCircle,
    Plus,
    Trash2,
    UserCheck,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';

interface Patient {
    id: number;
    first_name: string;
    last_name: string;
    date_of_birth?: string;
    gender?: string;
}

interface Bed {
    id: number;
    bed_number: string;
}

interface Doctor {
    id: number;
    name: string;
}

interface WardRound {
    id: number;
    progress_note: string;
    patient_status:
        | 'improving'
        | 'stable'
        | 'deteriorating'
        | 'discharge_ready';
    clinical_impression?: string;
    plan?: string;
    round_datetime: string;
    doctor: Doctor;
    created_at: string;
}

interface PatientAdmission {
    id: number;
    admission_number: string;
    patient: Patient;
    bed?: Bed;
    admitted_at: string;
}

interface WardRoundModalProps {
    open: boolean;
    onClose: () => void;
    admission: PatientAdmission | null;
}

const PATIENT_STATUSES = [
    {
        value: 'improving',
        label: 'Improving',
        icon: ArrowUpCircle,
        color: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    },
    {
        value: 'stable',
        label: 'Stable',
        icon: MinusCircle,
        color: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    },
    {
        value: 'deteriorating',
        label: 'Deteriorating',
        icon: ArrowDownCircle,
        color: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    },
    {
        value: 'discharge_ready',
        label: 'Discharge Ready',
        icon: CheckCircle2,
        color: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
    },
];

export function WardRoundModal({
    open,
    onClose,
    admission,
}: WardRoundModalProps) {
    const [rounds, setRounds] = useState<WardRound[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [editingRound, setEditingRound] = useState<WardRound | null>(null);
    const [viewMode, setViewMode] = useState<'list' | 'create' | 'edit'>(
        'list',
    );

    useEffect(() => {
        if (open && admission) {
            fetchRounds();
        }
    }, [open, admission]);

    const fetchRounds = async () => {
        if (!admission) return;

        setIsLoading(true);
        try {
            const response = await fetch(
                `/admissions/${admission.id}/ward-rounds`,
            );
            const data = await response.json();
            setRounds(data.ward_rounds || []);
        } catch (error) {
            toast.error('Failed to load ward rounds');
        } finally {
            setIsLoading(false);
        }
    };

    const handleClose = () => {
        setViewMode('list');
        setEditingRound(null);
        onClose();
    };

    const handleSuccess = () => {
        toast.success(
            editingRound
                ? 'Ward round updated successfully'
                : 'Ward round recorded successfully',
        );
        setViewMode('list');
        setEditingRound(null);
        fetchRounds();
    };

    const getStatusStyle = (status: string) => {
        return (
            PATIENT_STATUSES.find((s) => s.value === status) ||
            PATIENT_STATUSES[1]
        );
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

    const canEditRound = (round: WardRound) => {
        const createdAt = new Date(round.created_at);
        const hoursSinceCreation =
            (Date.now() - createdAt.getTime()) / (1000 * 60 * 60);
        return hoursSinceCreation < 24;
    };

    const canDeleteRound = (round: WardRound) => {
        const createdAt = new Date(round.created_at);
        const hoursSinceCreation =
            (Date.now() - createdAt.getTime()) / (1000 * 60 * 60);
        return hoursSinceCreation < 2;
    };

    if (!admission) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent className="max-h-[90vh] max-w-4xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Ward Rounds</DialogTitle>
                    <DialogDescription>
                        {viewMode === 'list' &&
                            `View and manage ward rounds for ${admission.patient.first_name} ${admission.patient.last_name}`}
                        {viewMode === 'create' && 'Record a new ward round'}
                        {viewMode === 'edit' && 'Edit ward round'}
                    </DialogDescription>
                </DialogHeader>

                {/* List View */}
                {viewMode === 'list' && (
                    <div className="space-y-4">
                        <div className="flex items-center justify-between">
                            <h3 className="font-medium text-gray-900 dark:text-gray-100">
                                {rounds.length} Round
                                {rounds.length !== 1 ? 's' : ''}
                            </h3>
                            <Button
                                size="sm"
                                onClick={() => setViewMode('create')}
                            >
                                <Plus className="mr-2 h-4 w-4" />
                                Record Round
                            </Button>
                        </div>

                        {isLoading ? (
                            <div className="flex items-center justify-center py-8">
                                <Loader2 className="h-8 w-8 animate-spin text-gray-400" />
                            </div>
                        ) : rounds.length > 0 ? (
                            <div className="space-y-4">
                                {rounds.map((round) => {
                                    const statusStyle = getStatusStyle(
                                        round.patient_status,
                                    );
                                    const StatusIcon = statusStyle.icon;

                                    return (
                                        <div
                                            key={round.id}
                                            className="rounded-lg border p-4 dark:border-gray-700"
                                        >
                                            <div className="mb-3 flex items-start justify-between">
                                                <div className="flex items-center gap-2">
                                                    <Badge
                                                        className={
                                                            statusStyle.color
                                                        }
                                                    >
                                                        <StatusIcon className="mr-1 h-3 w-3" />
                                                        {statusStyle.label}
                                                    </Badge>
                                                </div>
                                                <div className="flex items-center gap-1">
                                                    {canEditRound(round) && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => {
                                                                setEditingRound(
                                                                    round,
                                                                );
                                                                setViewMode(
                                                                    'edit',
                                                                );
                                                            }}
                                                        >
                                                            <Edit2 className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                    {canDeleteRound(round) && (
                                                        <Form
                                                            action={`/admissions/${admission.id}/ward-rounds/${round.id}`}
                                                            method="delete"
                                                            onSuccess={() => {
                                                                toast.success(
                                                                    'Ward round deleted successfully',
                                                                );
                                                                fetchRounds();
                                                            }}
                                                        >
                                                            {({
                                                                processing,
                                                            }) => (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    type="submit"
                                                                    disabled={
                                                                        processing
                                                                    }
                                                                >
                                                                    <Trash2 className="h-4 w-4 text-destructive" />
                                                                </Button>
                                                            )}
                                                        </Form>
                                                    )}
                                                </div>
                                            </div>

                                            <div className="space-y-3">
                                                {/* Progress Note */}
                                                <div>
                                                    <h4 className="mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        Progress Note
                                                    </h4>
                                                    <p className="text-sm text-gray-600 dark:text-gray-400">
                                                        {round.progress_note}
                                                    </p>
                                                </div>

                                                {/* Clinical Impression */}
                                                {round.clinical_impression && (
                                                    <div>
                                                        <h4 className="mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                                            Clinical Impression
                                                        </h4>
                                                        <p className="text-sm text-gray-600 dark:text-gray-400">
                                                            {
                                                                round.clinical_impression
                                                            }
                                                        </p>
                                                    </div>
                                                )}

                                                {/* Plan */}
                                                {round.plan && (
                                                    <div>
                                                        <h4 className="mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                                            Plan
                                                        </h4>
                                                        <p className="text-sm text-gray-600 dark:text-gray-400">
                                                            {round.plan}
                                                        </p>
                                                    </div>
                                                )}
                                            </div>

                                            <div className="mt-3 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                                <span className="flex items-center gap-1">
                                                    <UserCheck className="h-3 w-3" />
                                                    {round.doctor.name}
                                                </span>
                                                <span>
                                                    {formatDateTime(
                                                        round.round_datetime,
                                                    )}
                                                </span>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        ) : (
                            <div className="py-8 text-center text-gray-500 dark:text-gray-400">
                                <Activity className="mx-auto mb-4 h-12 w-12 text-gray-300 dark:text-gray-600" />
                                <p>No ward rounds recorded yet</p>
                                <Button
                                    className="mt-4"
                                    size="sm"
                                    onClick={() => setViewMode('create')}
                                >
                                    <Plus className="mr-2 h-4 w-4" />
                                    Record First Round
                                </Button>
                            </div>
                        )}

                        <div className="flex justify-end">
                            <Button variant="outline" onClick={handleClose}>
                                Close
                            </Button>
                        </div>
                    </div>
                )}

                {/* Create/Edit Form */}
                {(viewMode === 'create' || viewMode === 'edit') && (
                    <Form
                        action={
                            editingRound
                                ? `/admissions/${admission.id}/ward-rounds/${editingRound.id}`
                                : `/admissions/${admission.id}/ward-rounds`
                        }
                        method={editingRound ? 'put' : 'post'}
                        onSuccess={handleSuccess}
                        onError={() => {
                            toast.error(
                                editingRound
                                    ? 'Failed to update ward round'
                                    : 'Failed to record ward round',
                            );
                        }}
                        className="space-y-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                {/* Patient Status */}
                                <div className="space-y-2">
                                    <Label htmlFor="patient_status">
                                        Patient Status{' '}
                                        <span className="text-red-500">*</span>
                                    </Label>
                                    <Select
                                        name="patient_status"
                                        defaultValue={
                                            editingRound?.patient_status
                                        }
                                        required
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select patient status" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {PATIENT_STATUSES.map((status) => {
                                                const Icon = status.icon;
                                                return (
                                                    <SelectItem
                                                        key={status.value}
                                                        value={status.value}
                                                    >
                                                        <div className="flex items-center gap-2">
                                                            <Icon className="h-4 w-4" />
                                                            {status.label}
                                                        </div>
                                                    </SelectItem>
                                                );
                                            })}
                                        </SelectContent>
                                    </Select>
                                    {errors.patient_status && (
                                        <p className="text-sm text-destructive">
                                            {errors.patient_status}
                                        </p>
                                    )}
                                </div>

                                {/* Progress Note */}
                                <div className="space-y-2">
                                    <Label htmlFor="progress_note">
                                        Progress Note{' '}
                                        <span className="text-red-500">*</span>
                                    </Label>
                                    <Textarea
                                        name="progress_note"
                                        id="progress_note"
                                        defaultValue={
                                            editingRound?.progress_note || ''
                                        }
                                        placeholder="Enter detailed progress note..."
                                        rows={4}
                                        required
                                        minLength={10}
                                    />
                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                        Minimum 10 characters required
                                    </p>
                                    {errors.progress_note && (
                                        <p className="text-sm text-destructive">
                                            {errors.progress_note}
                                        </p>
                                    )}
                                </div>

                                {/* Clinical Impression */}
                                <div className="space-y-2">
                                    <Label htmlFor="clinical_impression">
                                        Clinical Impression
                                    </Label>
                                    <Textarea
                                        name="clinical_impression"
                                        id="clinical_impression"
                                        defaultValue={
                                            editingRound?.clinical_impression ||
                                            ''
                                        }
                                        placeholder="Enter clinical assessment and impressions..."
                                        rows={3}
                                    />
                                    {errors.clinical_impression && (
                                        <p className="text-sm text-destructive">
                                            {errors.clinical_impression}
                                        </p>
                                    )}
                                </div>

                                {/* Plan */}
                                <div className="space-y-2">
                                    <Label htmlFor="plan">Treatment Plan</Label>
                                    <Textarea
                                        name="plan"
                                        id="plan"
                                        defaultValue={editingRound?.plan || ''}
                                        placeholder="Enter treatment plan and next steps..."
                                        rows={3}
                                    />
                                    {errors.plan && (
                                        <p className="text-sm text-destructive">
                                            {errors.plan}
                                        </p>
                                    )}
                                </div>

                                {/* Action Buttons */}
                                <div className="flex justify-end gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => {
                                            setViewMode('list');
                                            setEditingRound(null);
                                        }}
                                    >
                                        Cancel
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        {processing && (
                                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                        )}
                                        {editingRound
                                            ? 'Update Round'
                                            : 'Record Round'}
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                )}
            </DialogContent>
        </Dialog>
    );
}
