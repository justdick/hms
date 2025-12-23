'use client';

import {
    ImagingResultsModal,
    type ImageAttachment,
} from '@/components/Imaging';
import AsyncLabServiceSelect from '@/components/Lab/AsyncLabServiceSelect';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    ExternalLink,
    Image,
    Plus,
    Scan,
    TestTube,
    Upload,
} from 'lucide-react';
import * as React from 'react';
import { useState } from 'react';
import { ConsultationLabOrdersTable } from './ConsultationLabOrdersTable';
import { ExternalImageUploadDialog } from './ExternalImageUploadDialog';

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

interface ImagingAttachmentBasic {
    id: number;
    file_name: string;
    file_type: string;
    description?: string;
}

interface LabOrder {
    id: number;
    lab_service: LabService;
    status:
        | 'ordered'
        | 'sample_collected'
        | 'in_progress'
        | 'completed'
        | 'cancelled'
        | 'external_referral';
    priority: 'routine' | 'urgent' | 'stat';
    special_instructions?: string;
    ordered_at: string;
    sample_collected_at?: string;
    result_entered_at?: string;
    result_values?: any;
    result_notes?: string;
    ordered_by?: {
        id: number;
        name: string;
    };
    result_entered_by?: {
        id: number;
        name: string;
    };
    imaging_attachments?: ImageAttachment[];
}

interface InvestigationsSectionProps {
    consultationId: number;
    labOrders: LabOrder[];
    consultationStatus: string;
    canUploadExternal?: boolean;
}

export function InvestigationsSection({
    consultationId,
    labOrders,
    consultationStatus,
    canUploadExternal = false,
}: InvestigationsSectionProps) {
    const [activeTab, setActiveTab] = useState('laboratory');
    const [showLabOrderDialog, setShowLabOrderDialog] = useState(false);
    const [showImagingOrderDialog, setShowImagingOrderDialog] = useState(false);
    const [showExternalUploadDialog, setShowExternalUploadDialog] =
        useState(false);
    const [selectedLabService, setSelectedLabService] =
        useState<LabService | null>(null);
    const [selectedImagingService, setSelectedImagingService] =
        useState<LabService | null>(null);

    const {
        data: labOrderData,
        setData: setLabOrderData,
        post: postLabOrder,
        processing: labOrderProcessing,
        reset: resetLabOrder,
    } = useForm({
        lab_service_id: '',
        priority: 'routine',
        special_instructions: '',
    });

    const {
        data: imagingOrderData,
        setData: setImagingOrderData,
        post: postImagingOrder,
        processing: imagingOrderProcessing,
        reset: resetImagingOrder,
    } = useForm({
        lab_service_id: '',
        priority: 'routine',
        special_instructions: '',
    });

    // Separate lab orders into laboratory tests and imaging studies
    const laboratoryOrders = labOrders.filter(
        (order) => !order.lab_service.is_imaging,
    );
    const imagingOrders = labOrders.filter(
        (order) => order.lab_service.is_imaging,
    );

    const handleLabOrderSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        postLabOrder(`/consultation/${consultationId}/lab-orders`, {
            onSuccess: () => {
                resetLabOrder();
                setSelectedLabService(null);
                setShowLabOrderDialog(false);
            },
        });
    };

    const handleImagingOrderSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        postImagingOrder(`/consultation/${consultationId}/lab-orders`, {
            onSuccess: () => {
                resetImagingOrder();
                setSelectedImagingService(null);
                setShowImagingOrderDialog(false);
            },
        });
    };

    const canOrder = consultationStatus === 'in_progress';

    // Get counts for tab badges
    const labCount = laboratoryOrders.length;
    const imagingCount = imagingOrders.length;

    // Get excluded IDs for each type
    const excludedLabIds = laboratoryOrders.map((o) => o.lab_service.id);
    const excludedImagingIds = imagingOrders.map((o) => o.lab_service.id);

    return (
        <Card>
            <CardHeader className="pb-3">
                <CardTitle>Investigations</CardTitle>
            </CardHeader>
            <CardContent>
                <Tabs value={activeTab} onValueChange={setActiveTab}>
                    <TabsList className="grid w-full grid-cols-2">
                        <TabsTrigger
                            value="laboratory"
                            className="flex items-center gap-2"
                        >
                            <TestTube className="h-4 w-4" />
                            Laboratory Tests
                            {labCount > 0 && (
                                <Badge variant="secondary" className="ml-1">
                                    {labCount}
                                </Badge>
                            )}
                        </TabsTrigger>
                        <TabsTrigger
                            value="imaging"
                            className="flex items-center gap-2"
                        >
                            <Scan className="h-4 w-4" />
                            Imaging
                            {imagingCount > 0 && (
                                <Badge variant="secondary" className="ml-1">
                                    {imagingCount}
                                </Badge>
                            )}
                        </TabsTrigger>
                    </TabsList>

                    {/* Laboratory Tests Tab */}
                    <TabsContent value="laboratory" className="mt-4">
                        <div className="space-y-4">
                            {canOrder && (
                                <div className="flex justify-end">
                                    <Dialog
                                        open={showLabOrderDialog}
                                        onOpenChange={setShowLabOrderDialog}
                                    >
                                        <DialogTrigger asChild>
                                            <Button>
                                                <Plus className="mr-2 h-4 w-4" />
                                                Order Lab Test
                                            </Button>
                                        </DialogTrigger>
                                        <DialogContent className="max-w-md">
                                            <DialogHeader>
                                                <DialogTitle>
                                                    Order Laboratory Test
                                                </DialogTitle>
                                            </DialogHeader>
                                            <form
                                                onSubmit={handleLabOrderSubmit}
                                                className="space-y-4"
                                            >
                                                <div>
                                                    <Label>
                                                        Search Lab Test
                                                    </Label>
                                                    <AsyncLabServiceSelect
                                                        onSelect={(service) => {
                                                            setSelectedLabService(
                                                                service as LabService,
                                                            );
                                                            setLabOrderData(
                                                                'lab_service_id',
                                                                service.id.toString(),
                                                            );
                                                        }}
                                                        excludeIds={
                                                            excludedLabIds
                                                        }
                                                        placeholder={
                                                            selectedLabService
                                                                ? selectedLabService.name
                                                                : 'Search by test name or code...'
                                                        }
                                                        filterType="laboratory"
                                                    />
                                                    {selectedLabService && (
                                                        <div className="mt-2 rounded-md bg-muted p-2 text-sm">
                                                            <p className="font-medium">
                                                                {
                                                                    selectedLabService.name
                                                                }
                                                            </p>
                                                            <p className="text-muted-foreground">
                                                                {
                                                                    selectedLabService.code
                                                                }{' '}
                                                                •{' '}
                                                                {
                                                                    selectedLabService.category
                                                                }{' '}
                                                                •{' '}
                                                                {
                                                                    selectedLabService.sample_type
                                                                }
                                                            </p>
                                                        </div>
                                                    )}
                                                    {/* Unpriced Lab Service Warning */}
                                                    {selectedLabService &&
                                                        (selectedLabService.price ===
                                                            null ||
                                                            selectedLabService.price ===
                                                                0) && (
                                                            <Alert className="mt-3 border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30">
                                                                <AlertTriangle className="h-4 w-4 text-amber-600 dark:text-amber-400" />
                                                                <AlertTitle className="text-amber-800 dark:text-amber-200">
                                                                    Unpriced
                                                                    Test -
                                                                    External
                                                                    Referral
                                                                </AlertTitle>
                                                                <AlertDescription className="text-amber-700 dark:text-amber-300">
                                                                    <p>
                                                                        This
                                                                        test has
                                                                        no price
                                                                        configured
                                                                        in the
                                                                        system.
                                                                    </p>
                                                                    <p className="mt-1 flex items-center gap-1">
                                                                        <ExternalLink className="h-3 w-3" />
                                                                        Patient
                                                                        will
                                                                        need to
                                                                        do this
                                                                        test at
                                                                        an
                                                                        external
                                                                        facility.
                                                                    </p>
                                                                </AlertDescription>
                                                            </Alert>
                                                        )}
                                                </div>

                                                <div>
                                                    <Label htmlFor="lab-priority">
                                                        Priority
                                                    </Label>
                                                    <Select
                                                        value={
                                                            labOrderData.priority
                                                        }
                                                        onValueChange={(
                                                            value,
                                                        ) =>
                                                            setLabOrderData(
                                                                'priority',
                                                                value,
                                                            )
                                                        }
                                                    >
                                                        <SelectTrigger id="lab-priority">
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="routine">
                                                                Routine
                                                            </SelectItem>
                                                            <SelectItem value="urgent">
                                                                Urgent
                                                            </SelectItem>
                                                            <SelectItem value="stat">
                                                                STAT (Immediate)
                                                            </SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>

                                                <div>
                                                    <Label htmlFor="lab-instructions">
                                                        Special Instructions
                                                        (Optional)
                                                    </Label>
                                                    <Textarea
                                                        id="lab-instructions"
                                                        placeholder="Any special instructions for the lab..."
                                                        value={
                                                            labOrderData.special_instructions
                                                        }
                                                        onChange={(e) =>
                                                            setLabOrderData(
                                                                'special_instructions',
                                                                e.target.value,
                                                            )
                                                        }
                                                        rows={3}
                                                    />
                                                </div>

                                                <div className="flex gap-2 pt-4">
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        onClick={() =>
                                                            setShowLabOrderDialog(
                                                                false,
                                                            )
                                                        }
                                                        className="flex-1"
                                                    >
                                                        Cancel
                                                    </Button>
                                                    <Button
                                                        type="submit"
                                                        disabled={
                                                            labOrderProcessing ||
                                                            !labOrderData.lab_service_id
                                                        }
                                                        className="flex-1"
                                                    >
                                                        {labOrderProcessing
                                                            ? 'Ordering...'
                                                            : 'Order Test'}
                                                    </Button>
                                                </div>
                                            </form>
                                        </DialogContent>
                                    </Dialog>
                                </div>
                            )}

                            <ConsultationLabOrdersTable
                                labOrders={laboratoryOrders}
                                consultationId={consultationId}
                                canDelete={canOrder}
                            />
                        </div>
                    </TabsContent>

                    {/* Imaging Tab */}
                    <TabsContent value="imaging" className="mt-4">
                        <div className="space-y-4">
                            {canOrder && (
                                <div className="flex justify-end gap-2">
                                    {canUploadExternal && (
                                        <Button
                                            variant="outline"
                                            onClick={() =>
                                                setShowExternalUploadDialog(
                                                    true,
                                                )
                                            }
                                        >
                                            <Upload className="mr-2 h-4 w-4" />
                                            Upload External
                                        </Button>
                                    )}
                                    <Dialog
                                        open={showImagingOrderDialog}
                                        onOpenChange={setShowImagingOrderDialog}
                                    >
                                        <DialogTrigger asChild>
                                            <Button>
                                                <Plus className="mr-2 h-4 w-4" />
                                                Order Imaging Study
                                            </Button>
                                        </DialogTrigger>
                                        <DialogContent className="max-w-md">
                                            <DialogHeader>
                                                <DialogTitle>
                                                    Order Imaging Study
                                                </DialogTitle>
                                            </DialogHeader>
                                            <form
                                                onSubmit={
                                                    handleImagingOrderSubmit
                                                }
                                                className="space-y-4"
                                            >
                                                <div>
                                                    <Label>
                                                        Search Imaging Study
                                                    </Label>
                                                    <AsyncLabServiceSelect
                                                        onSelect={(service) => {
                                                            setSelectedImagingService(
                                                                service as LabService,
                                                            );
                                                            setImagingOrderData(
                                                                'lab_service_id',
                                                                service.id.toString(),
                                                            );
                                                        }}
                                                        excludeIds={
                                                            excludedImagingIds
                                                        }
                                                        placeholder={
                                                            selectedImagingService
                                                                ? selectedImagingService.name
                                                                : 'Search by study name or code...'
                                                        }
                                                        filterType="imaging"
                                                    />
                                                    {selectedImagingService && (
                                                        <div className="mt-2 rounded-md bg-muted p-2 text-sm">
                                                            <p className="font-medium">
                                                                {
                                                                    selectedImagingService.name
                                                                }
                                                            </p>
                                                            <p className="text-muted-foreground">
                                                                {
                                                                    selectedImagingService.code
                                                                }{' '}
                                                                •{' '}
                                                                {selectedImagingService.modality ||
                                                                    selectedImagingService.category}
                                                            </p>
                                                        </div>
                                                    )}
                                                    {/* Unpriced Imaging Service Warning */}
                                                    {selectedImagingService &&
                                                        (selectedImagingService.price ===
                                                            null ||
                                                            selectedImagingService.price ===
                                                                0) && (
                                                            <Alert className="mt-3 border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30">
                                                                <AlertTriangle className="h-4 w-4 text-amber-600 dark:text-amber-400" />
                                                                <AlertTitle className="text-amber-800 dark:text-amber-200">
                                                                    Unpriced
                                                                    Study -
                                                                    External
                                                                    Referral
                                                                </AlertTitle>
                                                                <AlertDescription className="text-amber-700 dark:text-amber-300">
                                                                    <p>
                                                                        This
                                                                        imaging
                                                                        study
                                                                        has no
                                                                        price
                                                                        configured
                                                                        in the
                                                                        system.
                                                                    </p>
                                                                    <p className="mt-1 flex items-center gap-1">
                                                                        <ExternalLink className="h-3 w-3" />
                                                                        Patient
                                                                        will
                                                                        need to
                                                                        do this
                                                                        study at
                                                                        an
                                                                        external
                                                                        facility.
                                                                    </p>
                                                                </AlertDescription>
                                                            </Alert>
                                                        )}
                                                </div>

                                                <div>
                                                    <Label htmlFor="imaging-priority">
                                                        Priority
                                                    </Label>
                                                    <Select
                                                        value={
                                                            imagingOrderData.priority
                                                        }
                                                        onValueChange={(
                                                            value,
                                                        ) =>
                                                            setImagingOrderData(
                                                                'priority',
                                                                value,
                                                            )
                                                        }
                                                    >
                                                        <SelectTrigger id="imaging-priority">
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="routine">
                                                                Routine
                                                            </SelectItem>
                                                            <SelectItem value="urgent">
                                                                Urgent
                                                            </SelectItem>
                                                            <SelectItem value="stat">
                                                                STAT (Immediate)
                                                            </SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>

                                                <div>
                                                    <Label htmlFor="imaging-indication">
                                                        Clinical Indication
                                                    </Label>
                                                    <Textarea
                                                        id="imaging-indication"
                                                        placeholder="Clinical indication for the imaging study..."
                                                        value={
                                                            imagingOrderData.special_instructions
                                                        }
                                                        onChange={(e) =>
                                                            setImagingOrderData(
                                                                'special_instructions',
                                                                e.target.value,
                                                            )
                                                        }
                                                        rows={3}
                                                    />
                                                </div>

                                                <div className="flex gap-2 pt-4">
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        onClick={() =>
                                                            setShowImagingOrderDialog(
                                                                false,
                                                            )
                                                        }
                                                        className="flex-1"
                                                    >
                                                        Cancel
                                                    </Button>
                                                    <Button
                                                        type="submit"
                                                        disabled={
                                                            imagingOrderProcessing ||
                                                            !imagingOrderData.lab_service_id
                                                        }
                                                        className="flex-1"
                                                    >
                                                        {imagingOrderProcessing
                                                            ? 'Ordering...'
                                                            : 'Order Study'}
                                                    </Button>
                                                </div>
                                            </form>
                                        </DialogContent>
                                    </Dialog>
                                </div>
                            )}

                            <ImagingOrdersTable
                                imagingOrders={imagingOrders}
                                consultationId={consultationId}
                                canDelete={canOrder}
                            />
                        </div>
                    </TabsContent>
                </Tabs>

                {/* External Image Upload Dialog */}
                <ExternalImageUploadDialog
                    open={showExternalUploadDialog}
                    onOpenChange={setShowExternalUploadDialog}
                    consultationId={consultationId}
                />
            </CardContent>
        </Card>
    );
}

// Imaging Orders Table Component
interface ImagingOrdersTableProps {
    imagingOrders: LabOrder[];
    consultationId: number;
    canDelete?: boolean;
}

function ImagingOrdersTable({
    imagingOrders,
    consultationId,
    canDelete = true,
}: ImagingOrdersTableProps) {
    const [selectedOrder, setSelectedOrder] = useState<LabOrder | null>(null);
    const [modalOpen, setModalOpen] = useState(false);

    const handleViewResults = (order: LabOrder) => {
        setSelectedOrder(order);
        setModalOpen(true);
    };

    const getStatusBadgeClasses = (status: string) => {
        switch (status) {
            case 'completed':
                return 'bg-green-100 text-green-800 hover:bg-green-100 dark:bg-green-900/30 dark:text-green-400';
            case 'in_progress':
                return 'bg-orange-100 text-orange-800 hover:bg-orange-100 dark:bg-orange-900/30 dark:text-orange-400';
            case 'sample_collected':
                return 'bg-yellow-100 text-yellow-800 hover:bg-yellow-100 dark:bg-yellow-900/30 dark:text-yellow-400';
            case 'cancelled':
                return 'bg-red-100 text-red-800 hover:bg-red-100 dark:bg-red-900/30 dark:text-red-400';
            case 'external_referral':
                return 'bg-purple-100 text-purple-800 hover:bg-purple-100 dark:bg-purple-900/30 dark:text-purple-400';
            default:
                return 'bg-blue-100 text-blue-800 hover:bg-blue-100 dark:bg-blue-900/30 dark:text-blue-400';
        }
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

    const hasImages = (order: LabOrder) => {
        return (order.imaging_attachments?.length ?? 0) > 0;
    };

    if (imagingOrders.length === 0) {
        return (
            <div className="flex flex-col items-center justify-center py-12 text-center">
                <Scan className="h-12 w-12 text-muted-foreground/50" />
                <h3 className="mt-4 text-lg font-medium">
                    No imaging studies ordered
                </h3>
                <p className="mt-1 text-sm text-muted-foreground">
                    Order imaging studies like X-rays, CT scans, or MRIs for
                    this patient.
                </p>
            </div>
        );
    }

    return (
        <div className="space-y-3">
            {imagingOrders.map((order) => (
                <div
                    key={order.id}
                    className="flex items-center justify-between rounded-lg border p-4 hover:bg-muted/50"
                >
                    <div className="flex items-start gap-3">
                        <div className="rounded-lg bg-purple-100 p-2 dark:bg-purple-900/30">
                            <Scan className="h-5 w-5 text-purple-600 dark:text-purple-400" />
                        </div>
                        <div>
                            <div className="flex items-center gap-2">
                                <span className="font-medium">
                                    {order.lab_service.name}
                                </span>
                                {hasImages(order) && (
                                    <Badge
                                        variant="outline"
                                        className="flex items-center gap-1 border-green-300 bg-green-50 text-green-700 dark:border-green-700 dark:bg-green-900/30 dark:text-green-400"
                                    >
                                        <Image className="h-3 w-3" />
                                        {order.imaging_attachments?.length}{' '}
                                        image
                                        {(order.imaging_attachments?.length ??
                                            0) !== 1
                                            ? 's'
                                            : ''}
                                    </Badge>
                                )}
                            </div>
                            <div className="mt-1 flex items-center gap-2 text-sm text-muted-foreground">
                                <span>{order.lab_service.code}</span>
                                <span>•</span>
                                <span>
                                    {order.lab_service.modality ||
                                        order.lab_service.category}
                                </span>
                            </div>
                            <div className="mt-1 text-xs text-muted-foreground">
                                Ordered: {formatDateTime(order.ordered_at)}
                                {order.ordered_by &&
                                    ` by ${order.ordered_by.name}`}
                            </div>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Badge
                            variant="outline"
                            className={getStatusBadgeClasses(order.status)}
                        >
                            {order.status.replace('_', ' ').toUpperCase()}
                        </Badge>
                        {order.priority !== 'routine' && (
                            <Badge variant="destructive">
                                {order.priority.toUpperCase()}
                            </Badge>
                        )}
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => handleViewResults(order)}
                        >
                            View
                        </Button>
                    </div>
                </div>
            ))}

            {/* Imaging Results Modal */}
            {selectedOrder && (
                <ImagingResultsModal
                    order={selectedOrder}
                    open={modalOpen}
                    onOpenChange={setModalOpen}
                />
            )}
        </div>
    );
}

export default InvestigationsSection;
