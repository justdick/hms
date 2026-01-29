'use client';

import { useState } from 'react';
import { Switch } from '@/components/ui/switch';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Layers, ListPlus, Scan, TestTube } from 'lucide-react';
import { InvestigationsSection } from './InvestigationsSection';
import BatchLabOrderForm from './BatchLabOrderForm';
import { type ImageAttachment } from '@/components/Imaging';

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

interface LabOrder {
    id: number;
    lab_service: LabService;
    status: 'ordered' | 'sample_collected' | 'in_progress' | 'completed' | 'cancelled' | 'external_referral';
    priority: 'routine' | 'urgent' | 'stat';
    special_instructions?: string;
    ordered_at: string;
    sample_collected_at?: string;
    result_entered_at?: string;
    result_values?: any;
    result_notes?: string;
    ordered_by?: { id: number; name: string };
    result_entered_by?: { id: number; name: string };
    imaging_attachments?: ImageAttachment[];
}

interface Props {
    consultationId: number;
    labOrders: LabOrder[];
    isEditable?: boolean;
    consultationStatus?: string;
    canUploadExternal?: boolean;
    // For batch mode
    orderableType?: 'consultation' | 'ward_round';
    orderableId?: number;
    admissionId?: number;
}

export function InvestigationsWithBatch({
    consultationId,
    labOrders,
    isEditable,
    consultationStatus,
    canUploadExternal = false,
    orderableType = 'consultation',
    orderableId,
    admissionId,
}: Props) {
    const [batchMode, setBatchMode] = useState(false);
    const [activeTab, setActiveTab] = useState('laboratory');
    
    const canEdit = isEditable ?? consultationStatus === 'in_progress';

    // Separate lab orders into laboratory tests and imaging studies
    const laboratoryOrders = labOrders.filter(order => !order.lab_service.is_imaging);
    const imagingOrders = labOrders.filter(order => order.lab_service.is_imaging);

    const labCount = laboratoryOrders.length;
    const imagingCount = imagingOrders.length;

    // If not in batch mode, use the original InvestigationsSection
    if (!batchMode) {
        return (
            <div className="space-y-4">
                {/* Mode Toggle */}
                {canEdit && (
                    <div className="flex items-center justify-end gap-3 rounded-lg border bg-muted/50 p-3">
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <ListPlus className="h-4 w-4" />
                            <span>Single</span>
                        </div>
                        <Switch
                            checked={batchMode}
                            onCheckedChange={setBatchMode}
                            aria-label="Toggle batch mode"
                        />
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Layers className="h-4 w-4" />
                            <span>Batch</span>
                        </div>
                        <span className="ml-2 text-xs text-muted-foreground">
                            Add one at a time
                        </span>
                    </div>
                )}
                <InvestigationsSection
                    consultationId={consultationId}
                    labOrders={labOrders}
                    isEditable={isEditable}
                    consultationStatus={consultationStatus}
                    canUploadExternal={canUploadExternal}
                />
            </div>
        );
    }

    // Batch mode UI
    return (
        <div className="space-y-4">
            {/* Mode Toggle */}
            {canEdit && (
                <div className="flex items-center justify-end gap-3 rounded-lg border bg-muted/50 p-3">
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <ListPlus className="h-4 w-4" />
                        <span>Single</span>
                    </div>
                    <Switch
                        checked={batchMode}
                        onCheckedChange={setBatchMode}
                        aria-label="Toggle batch mode"
                    />
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Layers className="h-4 w-4" />
                        <span>Batch</span>
                    </div>
                    <span className="ml-2 text-xs text-muted-foreground">
                        Add multiple, save all at once
                    </span>
                </div>
            )}

            <Card>
                <CardHeader className="pb-3">
                    <CardTitle>Investigations</CardTitle>
                </CardHeader>
                <CardContent>
                    <Tabs value={activeTab} onValueChange={setActiveTab}>
                        <TabsList className="grid w-full grid-cols-2">
                            <TabsTrigger value="laboratory" className="flex items-center gap-2">
                                <TestTube className="h-4 w-4" />
                                Laboratory Tests
                                {labCount > 0 && (
                                    <Badge variant="secondary" className="ml-1">{labCount}</Badge>
                                )}
                            </TabsTrigger>
                            <TabsTrigger value="imaging" className="flex items-center gap-2">
                                <Scan className="h-4 w-4" />
                                Imaging
                                {imagingCount > 0 && (
                                    <Badge variant="secondary" className="ml-1">{imagingCount}</Badge>
                                )}
                            </TabsTrigger>
                        </TabsList>

                        {/* Laboratory Tests Tab */}
                        <TabsContent value="laboratory" className="mt-4">
                            <BatchLabOrderForm
                                existingLabOrders={laboratoryOrders}
                                orderableType={orderableType}
                                orderableId={orderableId ?? consultationId}
                                admissionId={admissionId}
                                isEditable={canEdit}
                                filterType="laboratory"
                            />
                        </TabsContent>

                        {/* Imaging Tab */}
                        <TabsContent value="imaging" className="mt-4">
                            <BatchLabOrderForm
                                existingLabOrders={imagingOrders}
                                orderableType={orderableType}
                                orderableId={orderableId ?? consultationId}
                                admissionId={admissionId}
                                isEditable={canEdit}
                                filterType="imaging"
                            />
                        </TabsContent>
                    </Tabs>
                </CardContent>
            </Card>
        </div>
    );
}
