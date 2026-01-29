'use client';

import { useState } from 'react';
import { Switch } from '@/components/ui/switch';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
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
import AsyncLabServiceSelect from '@/components/Lab/AsyncLabServiceSelect';
import { Layers, ListPlus, Plus, TestTube } from 'lucide-react';
import BatchLabOrderForm from './BatchLabOrderForm';

interface LabService {
    id: number;
    name: string;
    code: string;
    category: string;
    price: number | null;
    sample_type: string;
    is_imaging?: boolean;
}

interface LabOrder {
    id: number;
    lab_service: LabService;
    status: string;
    priority: 'routine' | 'urgent' | 'stat';
    special_instructions?: string;
    ordered_at: string;
}

interface Props {
    labOrders: LabOrder[];
    isEditable?: boolean;
    // Single mode props
    showDialog: boolean;
    setShowDialog: (open: boolean) => void;
    selectedService: LabService | null;
    setSelectedService: (service: LabService | null) => void;
    labOrderData: {
        lab_service_id: string;
        priority: string;
        special_instructions: string;
    };
    setLabOrderData: (field: string, value: string) => void;
    onSubmit: (e: React.FormEvent) => void;
    onDelete: (id: number) => void;
    processing: boolean;
    // Batch mode props
    orderableType: 'consultation' | 'ward_round';
    orderableId: number;
    admissionId?: number;
}

export default function LabOrdersSection({
    labOrders,
    isEditable = true,
    showDialog,
    setShowDialog,
    selectedService,
    setSelectedService,
    labOrderData,
    setLabOrderData,
    onSubmit,
    onDelete,
    processing,
    orderableType,
    orderableId,
    admissionId,
}: Props) {
    const [batchMode, setBatchMode] = useState(false);

    const excludedIds = labOrders.map(o => o.lab_service.id);

    // Compact toggle for inside the form header (same style as prescriptions)
    const modeToggle = isEditable && (
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

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-4">
                <CardTitle>Laboratory Orders</CardTitle>
                {modeToggle}
            </CardHeader>
            <CardContent>
                {batchMode ? (
                    /* Batch Mode */
                    <BatchLabOrderForm
                        existingLabOrders={labOrders}
                        orderableType={orderableType}
                        orderableId={orderableId}
                        admissionId={admissionId}
                        isEditable={isEditable}
                        onDelete={onDelete}
                        filterType="laboratory"
                    />
                ) : (
                    /* Single Mode */
                    <div className="space-y-4">
                        {isEditable && (
                            <div className="flex justify-end">
                                <Dialog open={showDialog} onOpenChange={setShowDialog}>
                                    <DialogTrigger asChild>
                                        <Button>
                                            <Plus className="mr-2 h-4 w-4" />
                                            Order Lab Test
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent className="max-w-md">
                                        <DialogHeader>
                                            <DialogTitle>Order Laboratory Test</DialogTitle>
                                        </DialogHeader>
                                        <form onSubmit={onSubmit} className="space-y-4">
                                            <div>
                                                <Label>Search Lab Test</Label>
                                                <AsyncLabServiceSelect
                                                    onSelect={(service) => {
                                                        setSelectedService(service as LabService);
                                                        setLabOrderData('lab_service_id', service.id.toString());
                                                    }}
                                                    excludeIds={excludedIds}
                                                    placeholder={selectedService ? selectedService.name : 'Search by test name or code...'}
                                                />
                                                {selectedService && (
                                                    <div className="mt-2 rounded-md bg-muted p-2 text-sm">
                                                        <p className="font-medium">{selectedService.name}</p>
                                                        <p className="text-muted-foreground">
                                                            {selectedService.code} • {selectedService.category} • {selectedService.sample_type}
                                                        </p>
                                                    </div>
                                                )}
                                            </div>

                                            <div>
                                                <Label htmlFor="priority">Priority</Label>
                                                <Select
                                                    value={labOrderData.priority}
                                                    onValueChange={(value) => setLabOrderData('priority', value)}
                                                >
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
                                                    placeholder="Any special instructions for the lab..."
                                                    value={labOrderData.special_instructions}
                                                    onChange={(e) => setLabOrderData('special_instructions', e.target.value)}
                                                    rows={3}
                                                />
                                            </div>

                                            <div className="flex gap-2 pt-4">
                                                <Button type="button" variant="outline" onClick={() => setShowDialog(false)} className="flex-1">
                                                    Cancel
                                                </Button>
                                                <Button type="submit" disabled={processing || !labOrderData.lab_service_id} className="flex-1">
                                                    {processing ? 'Ordering...' : 'Order Test'}
                                                </Button>
                                            </div>
                                        </form>
                                    </DialogContent>
                                </Dialog>
                            </div>
                        )}

                        {/* Existing Lab Orders List */}
                        {labOrders.length > 0 ? (
                            <div className="space-y-3">
                                {labOrders.map((order) => (
                                    <div
                                        key={order.id}
                                        className="flex items-center justify-between rounded-lg border p-4"
                                    >
                                        <div>
                                            <p className="font-medium">{order.lab_service.name}</p>
                                            <p className="text-sm text-muted-foreground">
                                                Priority: {order.priority} • Status: {order.status}
                                            </p>
                                            {order.special_instructions && (
                                                <p className="text-sm text-muted-foreground">
                                                    {order.special_instructions}
                                                </p>
                                            )}
                                        </div>
                                        {isEditable && order.status === 'ordered' && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => onDelete(order.id)}
                                            >
                                                Remove
                                            </Button>
                                        )}
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="py-8 text-center text-muted-foreground">
                                <TestTube className="mx-auto mb-4 h-16 w-16 opacity-30" />
                                <p className="text-lg font-medium">No lab orders added</p>
                                <p className="mt-2 text-sm">
                                    Click "Order Lab Test" to add lab orders
                                </p>
                            </div>
                        )}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
