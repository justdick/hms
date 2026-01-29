import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { router } from '@inertiajs/react';
import { format } from 'date-fns';
import {
    Baby,
    Calendar,
    Edit2,
    Plus,
    Scissors,
    Trash2,
    User,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

interface BabyOutcome {
    time_of_delivery: string;
    sex: 'male' | 'female' | 'unknown';
    apgar_1min?: number;
    apgar_5min?: number;
    apgar_10min?: number;
    birth_weight?: number;
    head_circumference?: number;
    full_length?: number;
    notes?: string;
}

interface DeliveryRecord {
    id: number;
    delivery_date: string;
    gestational_age?: string;
    parity?: string;
    delivery_mode: string;
    delivery_mode_label: string;
    outcomes: BabyOutcome[];
    surgical_notes?: string;
    notes?: string;
    recorded_by?: { id: number; name: string };
    last_edited_by?: { id: number; name: string };
    created_at: string;
    updated_at: string;
}

interface DeliveryModes {
    [key: string]: string;
}

interface Props {
    admissionId: number;
    deliveryRecords: DeliveryRecord[];
    deliveryModes: DeliveryModes;
}

const EMPTY_OUTCOME: BabyOutcome = {
    time_of_delivery: '',
    sex: 'unknown',
    apgar_1min: undefined,
    apgar_5min: undefined,
    apgar_10min: undefined,
    birth_weight: undefined,
    head_circumference: undefined,
    full_length: undefined,
    notes: '',
};

export function DeliveryRecordTab({
    admissionId,
    deliveryRecords,
    deliveryModes,
}: Props) {
    const [modalOpen, setModalOpen] = useState(false);
    const [editingRecord, setEditingRecord] = useState<DeliveryRecord | null>(null);
    const [deleteConfirmOpen, setDeleteConfirmOpen] = useState(false);
    const [recordToDelete, setRecordToDelete] = useState<DeliveryRecord | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    // Form state
    const [deliveryDate, setDeliveryDate] = useState('');
    const [gestationalAge, setGestationalAge] = useState('');
    const [parity, setParity] = useState('');
    const [deliveryMode, setDeliveryMode] = useState('');
    const [outcomes, setOutcomes] = useState<BabyOutcome[]>([{ ...EMPTY_OUTCOME }]);
    const [surgicalNotes, setSurgicalNotes] = useState('');
    const [notes, setNotes] = useState('');

    const isCSection = deliveryMode.includes('cs');

    const resetForm = () => {
        setDeliveryDate('');
        setGestationalAge('');
        setParity('');
        setDeliveryMode('');
        setOutcomes([{ ...EMPTY_OUTCOME }]);
        setSurgicalNotes('');
        setNotes('');
        setEditingRecord(null);
    };

    const openCreateModal = () => {
        resetForm();
        setDeliveryDate(format(new Date(), 'yyyy-MM-dd'));
        setModalOpen(true);
    };

    const openEditModal = (record: DeliveryRecord) => {
        setEditingRecord(record);
        setDeliveryDate(record.delivery_date);
        setGestationalAge(record.gestational_age || '');
        setParity(record.parity || '');
        setDeliveryMode(record.delivery_mode);
        setOutcomes(record.outcomes?.length > 0 ? record.outcomes : [{ ...EMPTY_OUTCOME }]);
        setSurgicalNotes(record.surgical_notes || '');
        setNotes(record.notes || '');
        setModalOpen(true);
    };

    const handleCloseModal = () => {
        setModalOpen(false);
        resetForm();
    };

    const addBabyOutcome = () => {
        setOutcomes([...outcomes, { ...EMPTY_OUTCOME }]);
    };

    const removeBabyOutcome = (index: number) => {
        if (outcomes.length > 1) {
            setOutcomes(outcomes.filter((_, i) => i !== index));
        }
    };

    const updateOutcome = (index: number, field: keyof BabyOutcome, value: any) => {
        const updated = [...outcomes];
        updated[index] = { ...updated[index], [field]: value };
        setOutcomes(updated);
    };

    const handleSubmit = () => {
        if (!deliveryDate || !deliveryMode) {
            toast.error('Please fill in required fields');
            return;
        }

        setIsSubmitting(true);

        const data = {
            delivery_date: deliveryDate,
            gestational_age: gestationalAge || null,
            parity: parity || null,
            delivery_mode: deliveryMode,
            outcomes: outcomes.filter(o => o.time_of_delivery || o.sex !== 'unknown'),
            surgical_notes: isCSection ? surgicalNotes : null,
            notes: notes || null,
        };

        if (editingRecord) {
            router.put(`/admissions/delivery-records/${editingRecord.id}`, data, {
                onSuccess: () => {
                    toast.success('Delivery record updated');
                    handleCloseModal();
                },
                onError: (errors) => {
                    const firstError = Object.values(errors)[0];
                    toast.error(typeof firstError === 'string' ? firstError : 'Failed to update record');
                },
                onFinish: () => setIsSubmitting(false),
            });
        } else {
            router.post(`/admissions/${admissionId}/delivery-records`, data, {
                onSuccess: () => {
                    toast.success('Delivery record created');
                    handleCloseModal();
                },
                onError: (errors) => {
                    const firstError = Object.values(errors)[0];
                    toast.error(typeof firstError === 'string' ? firstError : 'Failed to create record');
                },
                onFinish: () => setIsSubmitting(false),
            });
        }
    };

    const handleDelete = () => {
        if (!recordToDelete) return;

        router.delete(`/admissions/delivery-records/${recordToDelete.id}`, {
            onSuccess: () => {
                toast.success('Delivery record deleted');
                setDeleteConfirmOpen(false);
                setRecordToDelete(null);
            },
            onError: () => {
                toast.error('Failed to delete record');
            },
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

    return (
        <div className="space-y-4">
            <Card>
                <CardHeader className="flex flex-row items-center justify-between">
                    <CardTitle className="flex items-center gap-2">
                        <Baby className="h-5 w-5" />
                        Delivery Records
                    </CardTitle>
                    <Button onClick={openCreateModal}>
                        <Plus className="mr-2 h-4 w-4" />
                        Add Delivery Record
                    </Button>
                </CardHeader>
                <CardContent>
                    {deliveryRecords.length > 0 ? (
                        <div className="space-y-4">
                            {deliveryRecords.map((record) => (
                                <div
                                    key={record.id}
                                    className="rounded-lg border p-4 dark:border-gray-700"
                                >
                                    <div className="mb-3 flex items-start justify-between">
                                        <div className="flex items-center gap-3">
                                            <Badge
                                                variant={record.delivery_mode.includes('cs') ? 'destructive' : 'default'}
                                                className="flex items-center gap-1"
                                            >
                                                {record.delivery_mode.includes('cs') && (
                                                    <Scissors className="h-3 w-3" />
                                                )}
                                                {record.delivery_mode_label}
                                            </Badge>
                                            <span className="flex items-center gap-1 text-sm text-gray-600 dark:text-gray-400">
                                                <Calendar className="h-4 w-4" />
                                                {format(new Date(record.delivery_date), 'MMM d, yyyy')}
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-1">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => openEditModal(record)}
                                            >
                                                <Edit2 className="h-4 w-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => {
                                                    setRecordToDelete(record);
                                                    setDeleteConfirmOpen(true);
                                                }}
                                            >
                                                <Trash2 className="h-4 w-4 text-destructive" />
                                            </Button>
                                        </div>
                                    </div>

                                    <div className="mb-3 grid grid-cols-2 gap-4 text-sm md:grid-cols-4">
                                        {record.gestational_age && (
                                            <div>
                                                <span className="font-medium text-gray-600 dark:text-gray-400">EGA:</span>{' '}
                                                <span className="text-gray-900 dark:text-gray-100">{record.gestational_age}</span>
                                            </div>
                                        )}
                                        {record.parity && (
                                            <div>
                                                <span className="font-medium text-gray-600 dark:text-gray-400">Parity:</span>{' '}
                                                <span className="text-gray-900 dark:text-gray-100">{record.parity}</span>
                                            </div>
                                        )}
                                    </div>

                                    {/* Baby Outcomes */}
                                    {record.outcomes && record.outcomes.length > 0 && (
                                        <div className="space-y-2">
                                            <h4 className="text-sm font-semibold text-gray-700 dark:text-gray-300">
                                                Baby Outcome{record.outcomes.length > 1 ? 's' : ''}
                                            </h4>
                                            <div className="grid gap-2 md:grid-cols-2">
                                                {record.outcomes.map((outcome, idx) => (
                                                    <div
                                                        key={idx}
                                                        className="rounded-md bg-pink-50 p-3 dark:bg-pink-950"
                                                    >
                                                        <div className="mb-2 flex items-center gap-2">
                                                            <Baby className="h-4 w-4 text-pink-600 dark:text-pink-400" />
                                                            <span className="font-medium text-pink-700 dark:text-pink-300">
                                                                Baby {record.outcomes.length > 1 ? idx + 1 : ''}
                                                            </span>
                                                            <Badge variant="outline" className="text-xs">
                                                                {outcome.sex === 'male' ? '♂ Male' : outcome.sex === 'female' ? '♀ Female' : 'Unknown'}
                                                            </Badge>
                                                        </div>
                                                        <div className="grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                                                            {outcome.time_of_delivery && (
                                                                <div>
                                                                    <span className="text-gray-500">Time:</span>{' '}
                                                                    <span className="font-medium">{outcome.time_of_delivery}</span>
                                                                </div>
                                                            )}
                                                            {outcome.birth_weight && (
                                                                <div>
                                                                    <span className="text-gray-500">Weight:</span>{' '}
                                                                    <span className="font-medium">{outcome.birth_weight}g</span>
                                                                </div>
                                                            )}
                                                            {(outcome.apgar_1min || outcome.apgar_5min || outcome.apgar_10min) && (
                                                                <div className="col-span-2">
                                                                    <span className="text-gray-500">APGAR:</span>{' '}
                                                                    <span className="font-medium">
                                                                        {outcome.apgar_1min && `1min: ${outcome.apgar_1min}`}
                                                                        {outcome.apgar_5min && ` | 5min: ${outcome.apgar_5min}`}
                                                                        {outcome.apgar_10min && ` | 10min: ${outcome.apgar_10min}`}
                                                                    </span>
                                                                </div>
                                                            )}
                                                            {outcome.head_circumference && (
                                                                <div>
                                                                    <span className="text-gray-500">HC:</span>{' '}
                                                                    <span className="font-medium">{outcome.head_circumference}cm</span>
                                                                </div>
                                                            )}
                                                            {outcome.full_length && (
                                                                <div>
                                                                    <span className="text-gray-500">Length:</span>{' '}
                                                                    <span className="font-medium">{outcome.full_length}cm</span>
                                                                </div>
                                                            )}
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}

                                    {/* Surgical Notes for C-Section */}
                                    {record.surgical_notes && (
                                        <div className="mt-3 rounded-md bg-amber-50 p-3 dark:bg-amber-950">
                                            <h4 className="mb-1 flex items-center gap-1 text-sm font-semibold text-amber-700 dark:text-amber-300">
                                                <Scissors className="h-4 w-4" />
                                                Surgical Notes
                                            </h4>
                                            <p className="text-sm text-amber-800 dark:text-amber-200">
                                                {record.surgical_notes}
                                            </p>
                                        </div>
                                    )}

                                    {record.notes && (
                                        <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                            {record.notes}
                                        </p>
                                    )}

                                    <div className="mt-3 flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
                                        {record.recorded_by && (
                                            <span className="flex items-center gap-1">
                                                <User className="h-3 w-3" />
                                                Recorded by: {record.recorded_by.name}
                                            </span>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="py-12 text-center">
                            <Baby className="mx-auto mb-4 h-12 w-12 text-gray-300 dark:text-gray-600" />
                            <p className="text-gray-500 dark:text-gray-400">
                                No delivery records yet
                            </p>
                            <p className="mt-1 text-sm text-gray-400 dark:text-gray-500">
                                Click "Add Delivery Record" to document a delivery
                            </p>
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Create/Edit Modal */}
            <Dialog open={modalOpen} onOpenChange={setModalOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-[900px]">
                    <DialogHeader>
                        <DialogTitle>
                            {editingRecord ? 'Edit Delivery Record' : 'Add Delivery Record'}
                        </DialogTitle>
                        <DialogDescription>
                            Document the delivery details and baby outcomes
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-6 py-4">
                        {/* Basic Info */}
                        <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                            <div className="space-y-2">
                                <Label htmlFor="delivery_date">Delivery Date *</Label>
                                <Input
                                    id="delivery_date"
                                    type="date"
                                    value={deliveryDate}
                                    onChange={(e) => setDeliveryDate(e.target.value)}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="gestational_age">EGA (weeks)</Label>
                                <Input
                                    id="gestational_age"
                                    placeholder="e.g., 38+2"
                                    value={gestationalAge}
                                    onChange={(e) => setGestationalAge(e.target.value)}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="parity">Parity</Label>
                                <Input
                                    id="parity"
                                    placeholder="e.g., G2P1"
                                    value={parity}
                                    onChange={(e) => setParity(e.target.value)}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="delivery_mode">Delivery Mode *</Label>
                                <Select value={deliveryMode} onValueChange={setDeliveryMode}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select mode" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {Object.entries(deliveryModes).map(([key, label]) => (
                                            <SelectItem key={key} value={key}>
                                                {label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        {/* Baby Outcomes */}
                        <div className="space-y-3">
                            <div className="flex items-center justify-between">
                                <Label className="text-base font-semibold">Baby Outcomes</Label>
                                <Button type="button" variant="outline" size="sm" onClick={addBabyOutcome}>
                                    <Plus className="mr-1 h-4 w-4" />
                                    Add Baby
                                </Button>
                            </div>

                            {outcomes.map((outcome, index) => (
                                <div
                                    key={index}
                                    className="rounded-lg border bg-pink-50/50 p-4 dark:border-pink-800 dark:bg-pink-950/30"
                                >
                                    <div className="mb-3 flex items-center justify-between">
                                        <span className="font-medium text-pink-700 dark:text-pink-300">
                                            Baby {outcomes.length > 1 ? index + 1 : ''}
                                        </span>
                                        {outcomes.length > 1 && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => removeBabyOutcome(index)}
                                            >
                                                <Trash2 className="h-4 w-4 text-destructive" />
                                            </Button>
                                        )}
                                    </div>

                                    <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                                        <div className="space-y-1">
                                            <Label className="text-xs">Time of Delivery</Label>
                                            <Input
                                                type="time"
                                                value={outcome.time_of_delivery}
                                                onChange={(e) => updateOutcome(index, 'time_of_delivery', e.target.value)}
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label className="text-xs">Sex</Label>
                                            <Select
                                                value={outcome.sex}
                                                onValueChange={(v) => updateOutcome(index, 'sex', v)}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="male">Male</SelectItem>
                                                    <SelectItem value="female">Female</SelectItem>
                                                    <SelectItem value="unknown">Unknown</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="space-y-1">
                                            <Label className="text-xs">Birth Weight (g)</Label>
                                            <Input
                                                type="number"
                                                placeholder="e.g., 3200"
                                                value={outcome.birth_weight || ''}
                                                onChange={(e) => updateOutcome(index, 'birth_weight', e.target.value ? Number(e.target.value) : undefined)}
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label className="text-xs">Head Circ. (cm)</Label>
                                            <Input
                                                type="number"
                                                step="0.1"
                                                placeholder="e.g., 34"
                                                value={outcome.head_circumference || ''}
                                                onChange={(e) => updateOutcome(index, 'head_circumference', e.target.value ? Number(e.target.value) : undefined)}
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label className="text-xs">Full Length (cm)</Label>
                                            <Input
                                                type="number"
                                                step="0.1"
                                                placeholder="e.g., 50"
                                                value={outcome.full_length || ''}
                                                onChange={(e) => updateOutcome(index, 'full_length', e.target.value ? Number(e.target.value) : undefined)}
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label className="text-xs">APGAR 1min</Label>
                                            <Input
                                                type="number"
                                                min="0"
                                                max="10"
                                                value={outcome.apgar_1min || ''}
                                                onChange={(e) => updateOutcome(index, 'apgar_1min', e.target.value ? Number(e.target.value) : undefined)}
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label className="text-xs">APGAR 5min</Label>
                                            <Input
                                                type="number"
                                                min="0"
                                                max="10"
                                                value={outcome.apgar_5min || ''}
                                                onChange={(e) => updateOutcome(index, 'apgar_5min', e.target.value ? Number(e.target.value) : undefined)}
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label className="text-xs">APGAR 10min</Label>
                                            <Input
                                                type="number"
                                                min="0"
                                                max="10"
                                                value={outcome.apgar_10min || ''}
                                                onChange={(e) => updateOutcome(index, 'apgar_10min', e.target.value ? Number(e.target.value) : undefined)}
                                            />
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>

                        {/* Surgical Notes (for C-Section) */}
                        {isCSection && (
                            <div className="space-y-2">
                                <Label htmlFor="surgical_notes" className="flex items-center gap-2">
                                    <Scissors className="h-4 w-4" />
                                    Surgical Notes
                                </Label>
                                <Textarea
                                    id="surgical_notes"
                                    placeholder="Document surgical procedure details, findings, complications..."
                                    value={surgicalNotes}
                                    onChange={(e) => setSurgicalNotes(e.target.value)}
                                    rows={4}
                                />
                            </div>
                        )}

                        {/* General Notes */}
                        <div className="space-y-2">
                            <Label htmlFor="notes">Additional Notes</Label>
                            <Textarea
                                id="notes"
                                placeholder="Any additional notes about the delivery..."
                                value={notes}
                                onChange={(e) => setNotes(e.target.value)}
                                rows={3}
                            />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" onClick={handleCloseModal}>
                            Cancel
                        </Button>
                        <Button onClick={handleSubmit} disabled={isSubmitting}>
                            {isSubmitting ? 'Saving...' : editingRecord ? 'Update Record' : 'Save Record'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete Confirmation */}
            <Dialog open={deleteConfirmOpen} onOpenChange={setDeleteConfirmOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete Delivery Record</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete this delivery record? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeleteConfirmOpen(false)}>
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={handleDelete}>
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
