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

    // Compact toggle for inside the form header (same style as prescriptions)
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

    // If not in batch mode, use the original InvestigationsSection
    if (!batchMode) {
        return (
            <InvestigationsSection
                consultationId={consultationId}
                labOrders={labOrders}
                isEditable={isEditable}
                consultationStatus={consultationStatus}
                canUploadExternal={canUploadExternal}
                headerExtra={modeToggle}
            />
        );
    }

    // Batch mode UI
    return (
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
                            headerExtra={modeToggle}
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
                            headerExtra={modeToggle}
                        />
                    </TabsContent>
                </Tabs>
            </CardContent>
        </Card>
    );
}
