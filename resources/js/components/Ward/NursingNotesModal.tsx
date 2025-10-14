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
    AlertCircle,
    ClipboardList,
    Edit2,
    Eye,
    FileText,
    Loader2,
    Plus,
    Stethoscope,
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

interface Nurse {
    id: number;
    name: string;
}

interface NursingNote {
    id: number;
    type: 'assessment' | 'care' | 'observation' | 'incident' | 'handover';
    note: string;
    noted_at: string;
    nurse: Nurse;
    created_at: string;
}

interface PatientAdmission {
    id: number;
    admission_number: string;
    patient: Patient;
    bed?: Bed;
    admitted_at: string;
}

interface NursingNotesModalProps {
    open: boolean;
    onClose: () => void;
    admission: PatientAdmission | null;
}

const NOTE_TYPES = [
    { value: 'assessment', label: 'Assessment', icon: Stethoscope },
    { value: 'care', label: 'Care', icon: UserCheck },
    { value: 'observation', label: 'Observation', icon: Eye },
    { value: 'incident', label: 'Incident', icon: AlertCircle },
    { value: 'handover', label: 'Handover', icon: ClipboardList },
];

export function NursingNotesModal({
    open,
    onClose,
    admission,
}: NursingNotesModalProps) {
    const [notes, setNotes] = useState<NursingNote[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [editingNote, setEditingNote] = useState<NursingNote | null>(null);
    const [viewMode, setViewMode] = useState<'list' | 'create' | 'edit'>(
        'list',
    );

    useEffect(() => {
        if (open && admission) {
            fetchNotes();
        }
    }, [open, admission]);

    const fetchNotes = async () => {
        if (!admission) return;

        setIsLoading(true);
        try {
            const response = await fetch(
                `/admissions/${admission.id}/nursing-notes`,
            );
            const data = await response.json();
            setNotes(data.nursing_notes || []);
        } catch (error) {
            toast.error('Failed to load nursing notes');
        } finally {
            setIsLoading(false);
        }
    };

    const handleClose = () => {
        setViewMode('list');
        setEditingNote(null);
        onClose();
    };

    const handleSuccess = () => {
        toast.success(
            editingNote
                ? 'Nursing note updated successfully'
                : 'Nursing note added successfully',
        );
        setViewMode('list');
        setEditingNote(null);
        fetchNotes();
    };

    const getNoteTypeStyle = (
        type: string,
    ): { badge: string; icon: any; label: string } => {
        const typeConfig = NOTE_TYPES.find((t) => t.value === type);
        const colorMap = {
            assessment:
                'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            care: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            observation:
                'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
            incident:
                'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
            handover:
                'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
        };

        return {
            badge: colorMap[type as keyof typeof colorMap] || '',
            icon: typeConfig?.icon || FileText,
            label: typeConfig?.label || type,
        };
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

    const canEditNote = (note: NursingNote) => {
        const createdAt = new Date(note.created_at);
        const hoursSinceCreation =
            (Date.now() - createdAt.getTime()) / (1000 * 60 * 60);
        return hoursSinceCreation < 24;
    };

    const canDeleteNote = (note: NursingNote) => {
        const createdAt = new Date(note.created_at);
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
                    <DialogTitle>Nursing Notes</DialogTitle>
                    <DialogDescription>
                        {viewMode === 'list' &&
                            `View and manage nursing notes for ${admission.patient.first_name} ${admission.patient.last_name}`}
                        {viewMode === 'create' && 'Add a new nursing note'}
                        {viewMode === 'edit' && 'Edit nursing note'}
                    </DialogDescription>
                </DialogHeader>

                {/* List View */}
                {viewMode === 'list' && (
                    <div className="space-y-4">
                        <div className="flex items-center justify-between">
                            <h3 className="font-medium text-gray-900 dark:text-gray-100">
                                {notes.length} Note
                                {notes.length !== 1 ? 's' : ''}
                            </h3>
                            <Button
                                size="sm"
                                onClick={() => setViewMode('create')}
                            >
                                <Plus className="mr-2 h-4 w-4" />
                                Add Note
                            </Button>
                        </div>

                        {isLoading ? (
                            <div className="flex items-center justify-center py-8">
                                <Loader2 className="h-8 w-8 animate-spin text-gray-400" />
                            </div>
                        ) : notes.length > 0 ? (
                            <div className="space-y-3">
                                {notes.map((note) => {
                                    const typeStyle = getNoteTypeStyle(
                                        note.type,
                                    );
                                    const Icon = typeStyle.icon;

                                    return (
                                        <div
                                            key={note.id}
                                            className="rounded-lg border p-4 dark:border-gray-700"
                                        >
                                            <div className="mb-2 flex items-start justify-between">
                                                <div className="flex items-center gap-2">
                                                    <Badge
                                                        className={
                                                            typeStyle.badge
                                                        }
                                                    >
                                                        <Icon className="mr-1 h-3 w-3" />
                                                        {typeStyle.label}
                                                    </Badge>
                                                </div>
                                                <div className="flex items-center gap-1">
                                                    {canEditNote(note) && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => {
                                                                setEditingNote(
                                                                    note,
                                                                );
                                                                setViewMode(
                                                                    'edit',
                                                                );
                                                            }}
                                                        >
                                                            <Edit2 className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                    {canDeleteNote(note) && (
                                                        <Form
                                                            action={`/admissions/${admission.id}/nursing-notes/${note.id}`}
                                                            method="delete"
                                                            onSuccess={() => {
                                                                toast.success(
                                                                    'Nursing note deleted successfully',
                                                                );
                                                                fetchNotes();
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

                                            <p className="text-sm text-gray-700 dark:text-gray-300">
                                                {note.note}
                                            </p>

                                            <div className="mt-2 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                                <span className="flex items-center gap-1">
                                                    <UserCheck className="h-3 w-3" />
                                                    {note.nurse.name}
                                                </span>
                                                <span>
                                                    {formatDateTime(
                                                        note.noted_at,
                                                    )}
                                                </span>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        ) : (
                            <div className="py-8 text-center text-gray-500 dark:text-gray-400">
                                <FileText className="mx-auto mb-4 h-12 w-12 text-gray-300 dark:text-gray-600" />
                                <p>No nursing notes recorded yet</p>
                                <Button
                                    className="mt-4"
                                    size="sm"
                                    onClick={() => setViewMode('create')}
                                >
                                    <Plus className="mr-2 h-4 w-4" />
                                    Add First Note
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
                            editingNote
                                ? `/admissions/${admission.id}/nursing-notes/${editingNote.id}`
                                : `/admissions/${admission.id}/nursing-notes`
                        }
                        method={editingNote ? 'put' : 'post'}
                        onSuccess={handleSuccess}
                        onError={() => {
                            toast.error(
                                editingNote
                                    ? 'Failed to update nursing note'
                                    : 'Failed to add nursing note',
                            );
                        }}
                        className="space-y-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                {/* Note Type */}
                                <div className="space-y-2">
                                    <Label htmlFor="type">
                                        Note Type{' '}
                                        <span className="text-red-500">*</span>
                                    </Label>
                                    <Select
                                        name="type"
                                        defaultValue={editingNote?.type}
                                        required
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select note type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {NOTE_TYPES.map((type) => {
                                                const Icon = type.icon;
                                                return (
                                                    <SelectItem
                                                        key={type.value}
                                                        value={type.value}
                                                    >
                                                        <div className="flex items-center gap-2">
                                                            <Icon className="h-4 w-4" />
                                                            {type.label}
                                                        </div>
                                                    </SelectItem>
                                                );
                                            })}
                                        </SelectContent>
                                    </Select>
                                    {errors.type && (
                                        <p className="text-sm text-destructive">
                                            {errors.type}
                                        </p>
                                    )}
                                </div>

                                {/* Note Content */}
                                <div className="space-y-2">
                                    <Label htmlFor="note">
                                        Note{' '}
                                        <span className="text-red-500">*</span>
                                    </Label>
                                    <Textarea
                                        name="note"
                                        id="note"
                                        defaultValue={editingNote?.note || ''}
                                        placeholder="Enter detailed nursing note..."
                                        rows={6}
                                        required
                                        minLength={10}
                                    />
                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                        Minimum 10 characters required
                                    </p>
                                    {errors.note && (
                                        <p className="text-sm text-destructive">
                                            {errors.note}
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
                                            setEditingNote(null);
                                        }}
                                    >
                                        Cancel
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        {processing && (
                                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                        )}
                                        {editingNote
                                            ? 'Update Note'
                                            : 'Add Note'}
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
