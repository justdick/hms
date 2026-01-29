'use client';

import AsyncLabServiceSelect from '@/components/Lab/AsyncLabServiceSelect';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import {
    AlertTriangle,
    ExternalLink,
    Plus,
    Save,
    Trash2,
    TestTube,
} from 'lucide-react';
import { useState } from 'react';

interface LabService {
    id: number;
    name: string;
    code: string;
    category: string;
    price: number | null;
    sample_type: string;
    is_imaging?: boolean;
    modality?: string | null;
}

interface PendingLabOrder {
    id: string;
    lab_service_id: number;
    lab_service: LabService;
    priority: 'routine' | 'urgent' | 'stat';
    special_instructions: string;
}

interface ExistingLabOrder {
    id: number;
    lab_service: LabService;
    status: string;
    priority: 'routine' | 'urgent' | 'stat';
    special_instructions?: string;
    ordered_at: string;
}

interface Props {
    existingLabOrders: ExistingLabOrder[];
    orderableType: 'consultation' | 'ward_round';
    orderableId: number;
    admissionId?: number; // Required for ward_round type
    isEditable?: boolean;
    onDelete?: (id: number) => void;
    filterType?: 'laboratory' | 'imaging';
    headerExtra?: React.ReactNode;
}

export default function BatchLabOrderForm({
    existingLabOrders,
    orderableType,
    orderableId,
    admissionId,
    isEditable = true,
    onDelete,
    filterType = 'laboratory',
    headerExtra,
}: Props) {
    const [pendingOrders, setPendingOrders] = useState<PendingLabOrder[]>([]);
    const [isSaving, setIsSaving] = useState(false);
    
    // Form state
    const [selectedService, setSelectedService] = useState<LabService | null>(null);
    const [priority, setPriority] = useState<'routine' | 'urgent' | 'stat'>('routine');
    const [specialInstructions, setSpecialInstructions] = useState('');

    // Get IDs to exclude (already ordered or pending)
    const excludedIds = [
        ...existingLabOrders.map(o => o.lab_service.id),
        ...pendingOrders.map(o => o.lab_service_id),
    ];

    const resetForm = () => {
        setSelectedService(null);
        setPriority('routine');
        setSpecialInstructions('');
    };

    const handleAddToPending = () => {
        if (!selectedService) return;
        
        const newOrder: PendingLabOrder = {
            id: crypto.randomUUID(),
            lab_service_id: selectedService.id,
            lab_service: selectedService,
            priority,
            special_instructions: specialInstructions,
        };
        
        setPendingOrders(prev => [...prev, newOrder]);
        resetForm();
    };

    const handleRemovePending = (id: string) => {
        setPendingOrders(prev => prev.filter(o => o.id !== id));
    };

    const handleSaveAll = () => {
        if (pendingOrders.length === 0) return;
        
        setIsSaving(true);
        
        const endpoint = orderableType === 'consultation'
            ? `/consultation/${orderableId}/lab-orders/batch`
            : `/admissions/${admissionId}/ward-rounds/${orderableId}/lab-orders/batch`;
        
        router.post(endpoint, {
            lab_orders: pendingOrders.map(o => ({
                lab_service_id: o.lab_service_id,
                priority: o.priority,
                special_instructions: o.special_instructions,
            })),
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setPendingOrders([]);
                setIsSaving(false);
            },
            onError: () => {
                setIsSaving(false);
            },
        });
    };

    const isSelectedUnpriced = selectedService && 
        (selectedService.price === null || selectedService.price === 0);

    const typeLabel = filterType === 'imaging' ? 'Imaging Study' : 'Lab Test';
    const typeLabelPlural = filterType === 'imaging' ? 'Imaging Studies' : 'Lab Tests';

    return (
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {/* Left Column: Add New Order Form */}
            {isEditable && (
                <div className="rounded-lg border border-blue-200 bg-gradient-to-br from-blue-50 to-indigo-50 p-6 dark:border-blue-800 dark:from-blue-950/20 dark:to-indigo-950/20">
                    <div className="mb-4 flex items-center justify-between">
                        <h3 className="flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                            <Plus className="h-5 w-5" />
                            Add {typeLabel}
                        </h3>
                        {headerExtra}
                    </div>
                    
                    <div className="space-y-4">
                        <div>
                            <Label>Search {typeLabel}</Label>
                            <AsyncLabServiceSelect
                                onSelect={(service) => setSelectedService(service as LabService)}
                                excludeIds={excludedIds}
                                placeholder={selectedService ? selectedService.name : `Search by ${filterType === 'imaging' ? 'study' : 'test'} name or code...`}
                                filterType={filterType}
                            />
                            {selectedService && (
                                <div className="mt-2 rounded-md bg-muted p-2 text-sm">
                                    <p className="font-medium">{selectedService.name}</p>
                                    <p className="text-muted-foreground">
                                        {selectedService.code} • {selectedService.category}
                                        {selectedService.sample_type && ` • ${selectedService.sample_type}`}
                                    </p>
                                </div>
                            )}
                        </div>

                        {/* Unpriced Warning */}
                        {isSelectedUnpriced && (
                            <Alert className="border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30">
                                <AlertTriangle className="h-4 w-4 text-amber-600 dark:text-amber-400" />
                                <AlertTitle className="text-amber-800 dark:text-amber-200">
                                    Unpriced {typeLabel} - External Referral
                                </AlertTitle>
                                <AlertDescription className="text-amber-700 dark:text-amber-300">
                                    <p>This {typeLabel.toLowerCase()} has no price configured.</p>
                                    <p className="mt-1 flex items-center gap-1">
                                        <ExternalLink className="h-3 w-3" />
                                        Patient will need to do this at an external facility.
                                    </p>
                                </AlertDescription>
                            </Alert>
                        )}

                        <div>
                            <Label htmlFor="priority">Priority</Label>
                            <Select value={priority} onValueChange={(v) => setPriority(v as any)}>
                                <SelectTrigger id="priority">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="routine">Routine</SelectItem>
                                    <SelectItem value="urgent">Urgent</SelectItem>
                                    <SelectItem value="stat">STAT (Immediate)</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div>
                            <Label htmlFor="special_instructions">Special Instructions (Optional)</Label>
                            <Textarea
                                id="special_instructions"
                                placeholder={`Any special instructions for the ${filterType === 'imaging' ? 'radiology' : 'lab'}...`}
                                value={specialInstructions}
                                onChange={(e) => setSpecialInstructions(e.target.value)}
                                rows={2}
                            />
                        </div>

                        <Button
                            type="button"
                            onClick={handleAddToPending}
                            disabled={!selectedService}
                            className="w-full bg-blue-600 hover:bg-blue-700 text-white"
                        >
                            <Plus className="mr-2 h-4 w-4" />
                            Add to List
                        </Button>
                    </div>
                </div>
            )}

            {/* Right Column: Pending & Existing Orders */}
            <div className="space-y-4">
                {/* Pending Orders (not yet saved) */}
                {pendingOrders.length > 0 && (
                    <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/20">
                        <div className="mb-3 flex items-center justify-between">
                            <h4 className="flex items-center gap-2 font-semibold text-amber-800 dark:text-amber-200">
                                <AlertTriangle className="h-4 w-4" />
                                Pending ({pendingOrders.length}) - Not yet saved
                            </h4>
                            <Button
                                onClick={handleSaveAll}
                                disabled={isSaving}
                                size="sm"
                                className="bg-green-600 hover:bg-green-700"
                            >
                                <Save className="mr-1.5 h-4 w-4" />
                                {isSaving ? 'Saving...' : 'Save All'}
                            </Button>
                        </div>
                        <div className="space-y-2">
                            {pendingOrders.map((order) => (
                                <div
                                    key={order.id}
                                    className="flex items-center justify-between rounded-md border bg-white p-3 dark:bg-gray-900"
                                >
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center gap-2">
                                            <TestTube className="h-4 w-4 text-blue-600" />
                                            <span className="font-medium">{order.lab_service.name}</span>
                                            <Badge
                                                variant={order.priority === 'stat' ? 'destructive' : order.priority === 'urgent' ? 'default' : 'secondary'}
                                                className="text-xs"
                                            >
                                                {order.priority}
                                            </Badge>
                                        </div>
                                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                            {order.lab_service.code} • {order.lab_service.category}
                                        </p>
                                        {order.special_instructions && (
                                            <p className="mt-1 text-xs text-gray-500 italic">{order.special_instructions}</p>
                                        )}
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => handleRemovePending(order.id)}
                                        className="text-red-600 hover:text-red-700"
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Existing Orders (already saved) */}
                <div className="rounded-lg border p-4">
                    <h4 className="mb-3 font-semibold text-gray-900 dark:text-gray-100">
                        Ordered {typeLabelPlural} ({existingLabOrders.length})
                    </h4>
                    {existingLabOrders.length === 0 ? (
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            No {typeLabelPlural.toLowerCase()} ordered yet.
                        </p>
                    ) : (
                        <div className="space-y-2">
                            {existingLabOrders.map((order) => {
                                const canDelete = order.status === 'ordered' && isEditable;
                                return (
                                    <div
                                        key={order.id}
                                        className="flex items-center justify-between rounded-md border bg-gray-50 p-3 dark:bg-gray-800"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center gap-2">
                                                <TestTube className="h-4 w-4 text-blue-600" />
                                                <span className="font-medium">{order.lab_service.name}</span>
                                                <Badge
                                                    variant={order.priority === 'stat' ? 'destructive' : order.priority === 'urgent' ? 'default' : 'secondary'}
                                                    className="text-xs"
                                                >
                                                    {order.priority}
                                                </Badge>
                                                <Badge variant="outline" className="text-xs">
                                                    {order.status}
                                                </Badge>
                                            </div>
                                            <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                                {order.lab_service.code} • {order.lab_service.category}
                                            </p>
                                            {order.special_instructions && (
                                                <p className="mt-1 text-xs text-gray-500 italic">{order.special_instructions}</p>
                                            )}
                                        </div>
                                        {canDelete && onDelete && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => onDelete(order.id)}
                                                className="text-red-600 hover:text-red-700"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
