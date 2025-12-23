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
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { format, formatDistanceToNow } from 'date-fns';
import {
    AlertCircle,
    ArrowLeft,
    Calendar,
    CheckCircle,
    Clock,
    Download,
    FileText,
    Image,
    Phone,
    Play,
    Scan,
    Trash2,
    Upload,
    User,
} from 'lucide-react';
import * as React from 'react';
import { ImageUploadZone } from './ImageUploadZone';

interface Patient {
    id: number;
    patient_number: string;
    first_name: string;
    last_name: string;
    date_of_birth: string;
    gender: string;
    phone_number?: string;
}

interface LabService {
    id: number;
    name: string;
    code: string;
    modality: string | null;
    category: string;
    description?: string;
    preparation_instructions?: string;
}

interface ImagingAttachment {
    id: number;
    file_path: string;
    file_name: string;
    file_type: string;
    file_size: number;
    description: string | null;
    is_external: boolean;
    external_facility_name: string | null;
    external_study_date: string | null;
    uploaded_by: { id: number; name: string };
    uploaded_at: string;
    url: string;
    thumbnail_url: string | null;
}

interface LabOrder {
    id: number;
    status: string;
    priority: string;
    clinical_notes: string | null;
    ordered_at: string;
    result_entered_at: string | null;
    result_notes: string | null;
    lab_service: LabService;
    ordered_by: { id: number; name: string };
    imaging_attachments: ImagingAttachment[];
}

interface Context {
    type: 'consultation' | 'ward_round';
    department?: string;
    ward?: string;
    doctor?: string;
    presenting_complaint?: string;
    day_number?: number;
}

interface Props {
    labOrder: LabOrder;
    patient: Patient | null;
    context: Context | null;
}

const statusConfig: Record<
    string,
    {
        label: string;
        variant: 'default' | 'secondary' | 'destructive' | 'outline';
    }
> = {
    ordered: { label: 'Ordered', variant: 'secondary' },
    in_progress: { label: 'In Progress', variant: 'default' },
    completed: { label: 'Completed', variant: 'outline' },
};

const priorityConfig: Record<string, { label: string; className: string }> = {
    stat: {
        label: 'STAT',
        className:
            'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 font-bold',
    },
    urgent: {
        label: 'URGENT',
        className:
            'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200 font-semibold',
    },
    routine: {
        label: 'Routine',
        className:
            'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
    },
};

export default function RadiologyShow({ labOrder, patient, context }: Props) {
    const [showCompleteDialog, setShowCompleteDialog] = React.useState(false);
    const [showUploadDialog, setShowUploadDialog] = React.useState(false);
    const [showImageViewer, setShowImageViewer] = React.useState(false);
    const [selectedImage, setSelectedImage] =
        React.useState<ImagingAttachment | null>(null);
    const [reportFindings, setReportFindings] = React.useState('');
    const [reportImpression, setReportImpression] = React.useState('');
    const [isProcessing, setIsProcessing] = React.useState(false);

    const calculateAge = (dateOfBirth: string) => {
        const today = new Date();
        const birth = new Date(dateOfBirth);
        let age = today.getFullYear() - birth.getFullYear();
        const monthDiff = today.getMonth() - birth.getMonth();
        if (
            monthDiff < 0 ||
            (monthDiff === 0 && today.getDate() < birth.getDate())
        ) {
            age--;
        }
        return age;
    };

    const formatFileSize = (bytes: number) => {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    const handleMarkInProgress = () => {
        setIsProcessing(true);
        router.patch(
            `/radiology/orders/${labOrder.id}/in-progress`,
            {},
            {
                onFinish: () => setIsProcessing(false),
            },
        );
    };

    const handleComplete = () => {
        if (!reportFindings.trim() && !reportImpression.trim()) {
            return;
        }

        setIsProcessing(true);
        const resultNotes = `**FINDINGS:**\n${reportFindings}\n\n**IMPRESSION:**\n${reportImpression}`;

        router.patch(
            `/radiology/orders/${labOrder.id}/complete`,
            {
                result_notes: resultNotes,
            },
            {
                onSuccess: () => {
                    setShowCompleteDialog(false);
                    setReportFindings('');
                    setReportImpression('');
                },
                onFinish: () => setIsProcessing(false),
            },
        );
    };

    const handleDeleteAttachment = (attachmentId: number) => {
        if (!confirm('Are you sure you want to delete this image?')) {
            return;
        }

        router.delete(`/radiology/attachments/${attachmentId}`, {
            preserveScroll: true,
        });
    };

    const openImageViewer = (attachment: ImagingAttachment) => {
        setSelectedImage(attachment);
        setShowImageViewer(true);
    };

    const canMarkInProgress = labOrder.status === 'ordered';
    const canUploadImages = ['ordered', 'in_progress'].includes(
        labOrder.status,
    );
    const canComplete = ['ordered', 'in_progress'].includes(labOrder.status);
    const isCompleted = labOrder.status === 'completed';

    // Parse existing report if completed
    React.useEffect(() => {
        if (labOrder.result_notes) {
            const findingsMatch = labOrder.result_notes.match(
                /\*\*FINDINGS:\*\*\n([\s\S]*?)(?=\n\n\*\*IMPRESSION:|$)/,
            );
            const impressionMatch = labOrder.result_notes.match(
                /\*\*IMPRESSION:\*\*\n([\s\S]*?)$/,
            );

            if (findingsMatch) setReportFindings(findingsMatch[1].trim());
            if (impressionMatch) setReportImpression(impressionMatch[1].trim());
        }
    }, [labOrder.result_notes]);

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Investigations', href: '#' },
                { title: 'Radiology', href: '/radiology' },
                {
                    title: `Order #${labOrder.id}`,
                    href: `/radiology/orders/${labOrder.id}`,
                },
            ]}
        >
            <Head title={`Imaging Order #${labOrder.id}`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => router.visit('/radiology')}
                        >
                            <ArrowLeft className="mr-1 h-4 w-4" />
                            Back to Worklist
                        </Button>
                        <div>
                            <h1 className="text-2xl font-bold">
                                Imaging Order #{labOrder.id}
                            </h1>
                            <p className="text-muted-foreground">
                                {labOrder.lab_service.name}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <span
                            className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs ${priorityConfig[labOrder.priority]?.className || ''}`}
                        >
                            {labOrder.priority === 'stat' && (
                                <AlertCircle className="mr-1 h-3 w-3" />
                            )}
                            {priorityConfig[labOrder.priority]?.label ||
                                labOrder.priority}
                        </span>
                        <Badge
                            variant={
                                statusConfig[labOrder.status]?.variant ||
                                'secondary'
                            }
                        >
                            {statusConfig[labOrder.status]?.label ||
                                labOrder.status}
                        </Badge>
                    </div>
                </div>

                {/* Action Buttons */}
                {!isCompleted && (
                    <div className="flex items-center gap-2">
                        {canMarkInProgress && (
                            <Button
                                onClick={handleMarkInProgress}
                                disabled={isProcessing}
                                variant="outline"
                            >
                                <Play className="mr-2 h-4 w-4" />
                                Start Processing
                            </Button>
                        )}
                        {canUploadImages && (
                            <Button
                                onClick={() => setShowUploadDialog(true)}
                                variant="outline"
                            >
                                <Upload className="mr-2 h-4 w-4" />
                                Upload Images
                            </Button>
                        )}
                        {canComplete && (
                            <Button onClick={() => setShowCompleteDialog(true)}>
                                <CheckCircle className="mr-2 h-4 w-4" />
                                Complete with Report
                            </Button>
                        )}
                    </div>
                )}

                <div className="grid gap-6 md:grid-cols-2">
                    {/* Patient Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <User className="h-5 w-5" />
                                Patient Information
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {patient ? (
                                <>
                                    <div>
                                        <Label className="text-sm font-medium">
                                            Name
                                        </Label>
                                        <p className="text-sm">
                                            {patient.first_name}{' '}
                                            {patient.last_name}
                                        </p>
                                    </div>
                                    <div>
                                        <Label className="text-sm font-medium">
                                            Patient Number
                                        </Label>
                                        <p className="font-mono text-sm">
                                            {patient.patient_number}
                                        </p>
                                    </div>
                                    <div>
                                        <Label className="text-sm font-medium">
                                            Age & Gender
                                        </Label>
                                        <p className="text-sm">
                                            {calculateAge(
                                                patient.date_of_birth,
                                            )}{' '}
                                            years old, {patient.gender}
                                        </p>
                                    </div>
                                    <div>
                                        <Label className="text-sm font-medium">
                                            Date of Birth
                                        </Label>
                                        <p className="text-sm">
                                            {format(
                                                new Date(patient.date_of_birth),
                                                'PPP',
                                            )}
                                        </p>
                                    </div>
                                    {patient.phone_number && (
                                        <div className="flex items-center gap-1">
                                            <Phone className="h-4 w-4 text-muted-foreground" />
                                            <span className="text-sm">
                                                {patient.phone_number}
                                            </span>
                                        </div>
                                    )}
                                </>
                            ) : (
                                <p className="text-muted-foreground">
                                    Patient information not available
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    {/* Study Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Scan className="h-5 w-5" />
                                Study Information
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div>
                                <Label className="text-sm font-medium">
                                    Study Type
                                </Label>
                                <p className="text-sm font-medium">
                                    {labOrder.lab_service.name}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Code: {labOrder.lab_service.code}
                                </p>
                            </div>
                            {labOrder.lab_service.modality && (
                                <div>
                                    <Label className="text-sm font-medium">
                                        Modality
                                    </Label>
                                    <p className="text-sm">
                                        <Badge variant="outline">
                                            {labOrder.lab_service.modality}
                                        </Badge>
                                    </p>
                                </div>
                            )}
                            <div>
                                <Label className="text-sm font-medium">
                                    Category
                                </Label>
                                <p className="text-sm">
                                    {labOrder.lab_service.category}
                                </p>
                            </div>
                            {labOrder.clinical_notes && (
                                <div>
                                    <Label className="text-sm font-medium">
                                        Clinical Indication
                                    </Label>
                                    <p className="rounded border bg-muted/50 p-2 text-sm">
                                        {labOrder.clinical_notes}
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Order Details */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <FileText className="h-5 w-5" />
                                Order Details
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div>
                                <Label className="text-sm font-medium">
                                    Ordered By
                                </Label>
                                <p className="text-sm">
                                    {labOrder.ordered_by.name}
                                </p>
                            </div>
                            <div className="flex items-center gap-1">
                                <Calendar className="h-4 w-4 text-muted-foreground" />
                                <span className="text-sm">
                                    Ordered:{' '}
                                    {format(
                                        new Date(labOrder.ordered_at),
                                        'PPpp',
                                    )}
                                </span>
                            </div>
                            <div className="flex items-center gap-1">
                                <Clock className="h-4 w-4 text-muted-foreground" />
                                <span className="text-sm">
                                    {formatDistanceToNow(
                                        new Date(labOrder.ordered_at),
                                        { addSuffix: true },
                                    )}
                                </span>
                            </div>
                            {context && (
                                <div>
                                    <Label className="text-sm font-medium">
                                        Context
                                    </Label>
                                    <p className="text-sm">
                                        {context.type === 'consultation' ? (
                                            <>
                                                Consultation -{' '}
                                                {context.department}
                                            </>
                                        ) : (
                                            <>
                                                Ward Round Day{' '}
                                                {context.day_number} -{' '}
                                                {context.ward}
                                            </>
                                        )}
                                    </p>
                                    {context.doctor && (
                                        <p className="text-xs text-muted-foreground">
                                            Doctor: {context.doctor}
                                        </p>
                                    )}
                                </div>
                            )}
                            {labOrder.result_entered_at && (
                                <div className="flex items-center gap-1">
                                    <CheckCircle className="h-4 w-4 text-green-600" />
                                    <span className="text-sm">
                                        Completed:{' '}
                                        {format(
                                            new Date(
                                                labOrder.result_entered_at,
                                            ),
                                            'PPpp',
                                        )}
                                    </span>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Preparation Instructions */}
                    {labOrder.lab_service.preparation_instructions && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <AlertCircle className="h-5 w-5" />
                                    Preparation Instructions
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm">
                                    {
                                        labOrder.lab_service
                                            .preparation_instructions
                                    }
                                </p>
                            </CardContent>
                        </Card>
                    )}
                </div>

                {/* Images Section */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Image className="h-5 w-5" />
                            Images ({labOrder.imaging_attachments.length})
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {labOrder.imaging_attachments.length > 0 ? (
                            <div className="grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                                {labOrder.imaging_attachments.map(
                                    (attachment) => (
                                        <div
                                            key={attachment.id}
                                            className="group relative rounded-lg border bg-muted/30 p-2"
                                        >
                                            <div
                                                className="aspect-square cursor-pointer overflow-hidden rounded-md bg-muted"
                                                onClick={() =>
                                                    openImageViewer(attachment)
                                                }
                                            >
                                                {attachment.file_type.startsWith(
                                                    'image/',
                                                ) ? (
                                                    <img
                                                        src={attachment.url}
                                                        alt={
                                                            attachment.description ||
                                                            attachment.file_name
                                                        }
                                                        className="h-full w-full object-cover transition-transform group-hover:scale-105"
                                                    />
                                                ) : (
                                                    <div className="flex h-full w-full items-center justify-center">
                                                        <FileText className="h-12 w-12 text-muted-foreground" />
                                                    </div>
                                                )}
                                            </div>
                                            <div className="mt-2 space-y-1">
                                                <p
                                                    className="truncate text-sm font-medium"
                                                    title={attachment.file_name}
                                                >
                                                    {attachment.description ||
                                                        attachment.file_name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {formatFileSize(
                                                        attachment.file_size,
                                                    )}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    By{' '}
                                                    {
                                                        attachment.uploaded_by
                                                            .name
                                                    }
                                                </p>
                                                {attachment.is_external && (
                                                    <Badge
                                                        variant="outline"
                                                        className="text-xs"
                                                    >
                                                        External
                                                    </Badge>
                                                )}
                                            </div>
                                            <div className="absolute top-3 right-3 flex gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                                                <Button
                                                    size="icon"
                                                    variant="secondary"
                                                    className="h-8 w-8"
                                                    asChild
                                                >
                                                    <a
                                                        href={`/radiology/attachments/${attachment.id}/download`}
                                                        download
                                                    >
                                                        <Download className="h-4 w-4" />
                                                    </a>
                                                </Button>
                                                {canUploadImages && (
                                                    <Button
                                                        size="icon"
                                                        variant="destructive"
                                                        className="h-8 w-8"
                                                        onClick={() =>
                                                            handleDeleteAttachment(
                                                                attachment.id,
                                                            )
                                                        }
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                    ),
                                )}
                            </div>
                        ) : (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <Image className="h-12 w-12 text-muted-foreground" />
                                <p className="mt-2 text-sm text-muted-foreground">
                                    No images uploaded yet
                                </p>
                                {canUploadImages && (
                                    <Button
                                        variant="outline"
                                        className="mt-4"
                                        onClick={() =>
                                            setShowUploadDialog(true)
                                        }
                                    >
                                        <Upload className="mr-2 h-4 w-4" />
                                        Upload Images
                                    </Button>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Report Section (if completed) */}
                {isCompleted && labOrder.result_notes && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <FileText className="h-5 w-5" />
                                Radiologist Report
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <Label className="text-sm font-medium">
                                    Findings
                                </Label>
                                <div className="mt-1 rounded-md bg-muted p-3 text-sm whitespace-pre-wrap">
                                    {reportFindings || 'No findings recorded'}
                                </div>
                            </div>
                            <Separator />
                            <div>
                                <Label className="text-sm font-medium">
                                    Impression
                                </Label>
                                <div className="mt-1 rounded-md bg-muted p-3 text-sm whitespace-pre-wrap">
                                    {reportImpression ||
                                        'No impression recorded'}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>

            {/* Upload Dialog */}
            <Dialog open={showUploadDialog} onOpenChange={setShowUploadDialog}>
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Upload Images</DialogTitle>
                        <DialogDescription>
                            Upload imaging files for this study. Supported
                            formats: JPEG, PNG, PDF (max 50MB each).
                        </DialogDescription>
                    </DialogHeader>
                    <ImageUploadZone
                        labOrderId={labOrder.id}
                        onUploadComplete={() => {
                            setShowUploadDialog(false);
                            router.reload({ only: ['labOrder'] });
                        }}
                    />
                </DialogContent>
            </Dialog>

            {/* Complete Dialog */}
            <Dialog
                open={showCompleteDialog}
                onOpenChange={setShowCompleteDialog}
            >
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Complete Imaging Study</DialogTitle>
                        <DialogDescription>
                            Enter the radiologist report to complete this
                            imaging study.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div>
                            <Label htmlFor="findings">Findings *</Label>
                            <Textarea
                                id="findings"
                                placeholder="Describe the imaging findings..."
                                value={reportFindings}
                                onChange={(e) =>
                                    setReportFindings(e.target.value)
                                }
                                rows={6}
                                className="mt-1"
                            />
                        </div>
                        <div>
                            <Label htmlFor="impression">Impression *</Label>
                            <Textarea
                                id="impression"
                                placeholder="Provide the clinical impression..."
                                value={reportImpression}
                                onChange={(e) =>
                                    setReportImpression(e.target.value)
                                }
                                rows={4}
                                className="mt-1"
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setShowCompleteDialog(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            onClick={handleComplete}
                            disabled={
                                isProcessing ||
                                (!reportFindings.trim() &&
                                    !reportImpression.trim())
                            }
                        >
                            {isProcessing ? 'Completing...' : 'Complete Study'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Image Viewer Dialog */}
            <Dialog open={showImageViewer} onOpenChange={setShowImageViewer}>
                <DialogContent className="max-w-4xl">
                    <DialogHeader>
                        <DialogTitle>
                            {selectedImage?.description ||
                                selectedImage?.file_name}
                        </DialogTitle>
                    </DialogHeader>
                    {selectedImage && (
                        <div className="space-y-4">
                            <div className="flex max-h-[70vh] items-center justify-center overflow-hidden rounded-lg bg-black">
                                {selectedImage.file_type.startsWith(
                                    'image/',
                                ) ? (
                                    <img
                                        src={selectedImage.url}
                                        alt={
                                            selectedImage.description ||
                                            selectedImage.file_name
                                        }
                                        className="max-h-[70vh] object-contain"
                                    />
                                ) : (
                                    <div className="flex flex-col items-center justify-center py-12">
                                        <FileText className="h-16 w-16 text-white" />
                                        <p className="mt-2 text-white">
                                            PDF Document
                                        </p>
                                        <Button
                                            variant="secondary"
                                            className="mt-4"
                                            asChild
                                        >
                                            <a
                                                href={selectedImage.url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                Open PDF
                                            </a>
                                        </Button>
                                    </div>
                                )}
                            </div>
                            <div className="flex items-center justify-between text-sm text-muted-foreground">
                                <div>
                                    <p>
                                        Uploaded by{' '}
                                        {selectedImage.uploaded_by.name}
                                    </p>
                                    <p>
                                        {format(
                                            new Date(selectedImage.uploaded_at),
                                            'PPpp',
                                        )}
                                    </p>
                                </div>
                                <div className="flex gap-2">
                                    <Button variant="outline" size="sm" asChild>
                                        <a
                                            href={`/radiology/attachments/${selectedImage.id}/download`}
                                            download
                                        >
                                            <Download className="mr-2 h-4 w-4" />
                                            Download
                                        </a>
                                    </Button>
                                </div>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
