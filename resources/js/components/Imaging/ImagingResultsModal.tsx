'use client';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';
import { format } from 'date-fns';
import {
    Building2,
    Calendar,
    Check,
    ExternalLink,
    FileText,
    Image,
    Scan,
} from 'lucide-react';
import { ImageGallery, type ImageAttachment } from './ImageGallery';

interface LabService {
    id: number;
    name: string;
    code: string;
    category: string;
    modality?: string | null;
    is_imaging?: boolean;
}

interface ImagingOrder {
    id: number;
    lab_service: LabService;
    status: string;
    priority: string;
    special_instructions?: string;
    ordered_at: string;
    result_entered_at?: string;
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

interface ImagingResultsModalProps {
    order: ImagingOrder;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

const formatDateTime = (dateString: string) => {
    return format(new Date(dateString), 'PPpp');
};

const getStatusBadgeClasses = (status: string) => {
    switch (status) {
        case 'completed':
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
        case 'in_progress':
            return 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400';
        case 'external_referral':
            return 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400';
        case 'cancelled':
            return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
        default:
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400';
    }
};

// Parse report into findings and impression sections
const parseReport = (reportNotes: string | undefined) => {
    if (!reportNotes) return { findings: null, impression: null, raw: null };

    const findingsMatch = reportNotes.match(
        /\*\*FINDINGS:\*\*\n([\s\S]*?)(?=\n\n\*\*IMPRESSION:|$)/,
    );
    const impressionMatch = reportNotes.match(
        /\*\*IMPRESSION:\*\*\n([\s\S]*?)$/,
    );

    if (findingsMatch || impressionMatch) {
        return {
            findings: findingsMatch ? findingsMatch[1].trim() : null,
            impression: impressionMatch ? impressionMatch[1].trim() : null,
            raw: null,
        };
    }

    // If no structured format, return as raw
    return { findings: null, impression: null, raw: reportNotes };
};

// Check if any attachment is external
const hasExternalImages = (attachments: ImageAttachment[] | undefined) => {
    return attachments?.some((a) => a.is_external) ?? false;
};

// Get external facility info from attachments
const getExternalInfo = (attachments: ImageAttachment[] | undefined) => {
    const external = attachments?.find((a) => a.is_external);
    if (!external) return null;
    return {
        facilityName: external.external_facility_name,
        studyDate: external.external_study_date,
    };
};

export function ImagingResultsModal({
    order,
    open,
    onOpenChange,
}: ImagingResultsModalProps) {
    const report = parseReport(order.result_notes);
    const attachments = order.imaging_attachments || [];
    const hasImages = attachments.length > 0;
    const isExternal = hasExternalImages(attachments);
    const externalInfo = getExternalInfo(attachments);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] max-w-4xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2 text-xl">
                        <Scan className="h-5 w-5 text-purple-600" />
                        {order.lab_service.name}
                    </DialogTitle>
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <span>{order.lab_service.code}</span>
                        <span>•</span>
                        <span>
                            {order.lab_service.modality ||
                                order.lab_service.category}
                        </span>
                    </div>
                </DialogHeader>

                <div className="space-y-6">
                    {/* External Indicator */}
                    {isExternal && externalInfo && (
                        <div className="flex items-start gap-3 rounded-lg border border-purple-200 bg-purple-50 p-4 dark:border-purple-800 dark:bg-purple-950/30">
                            <Building2 className="mt-0.5 h-5 w-5 text-purple-600 dark:text-purple-400" />
                            <div>
                                <div className="flex items-center gap-2">
                                    <span className="font-medium text-purple-900 dark:text-purple-200">
                                        External Study
                                    </span>
                                    <Badge
                                        variant="outline"
                                        className="border-purple-300 bg-purple-100 text-purple-700 dark:border-purple-700 dark:bg-purple-900/50 dark:text-purple-300"
                                    >
                                        <ExternalLink className="mr-1 h-3 w-3" />
                                        External
                                    </Badge>
                                </div>
                                {externalInfo.facilityName && (
                                    <p className="mt-1 text-sm text-purple-800 dark:text-purple-300">
                                        <span className="font-medium">
                                            Facility:
                                        </span>{' '}
                                        {externalInfo.facilityName}
                                    </p>
                                )}
                                {externalInfo.studyDate && (
                                    <p className="text-sm text-purple-800 dark:text-purple-300">
                                        <span className="font-medium">
                                            Study Date:
                                        </span>{' '}
                                        {format(
                                            new Date(externalInfo.studyDate),
                                            'PPP',
                                        )}
                                    </p>
                                )}
                            </div>
                        </div>
                    )}

                    {/* Metadata Grid */}
                    <div className="grid grid-cols-2 gap-4 rounded-lg bg-muted/50 p-4 md:grid-cols-4">
                        <div className="flex items-start gap-2">
                            <Calendar className="mt-0.5 h-4 w-4 text-muted-foreground" />
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Ordered
                                </p>
                                <p className="text-sm font-medium">
                                    {formatDateTime(order.ordered_at)}
                                </p>
                                {order.ordered_by && (
                                    <p className="text-xs text-muted-foreground">
                                        by {order.ordered_by.name}
                                    </p>
                                )}
                            </div>
                        </div>

                        {order.result_entered_at && (
                            <div className="flex items-start gap-2">
                                <Check className="mt-0.5 h-4 w-4 text-green-600" />
                                <div>
                                    <p className="text-xs text-muted-foreground">
                                        Completed
                                    </p>
                                    <p className="text-sm font-medium">
                                        {formatDateTime(
                                            order.result_entered_at,
                                        )}
                                    </p>
                                    {order.result_entered_by && (
                                        <p className="text-xs text-muted-foreground">
                                            by {order.result_entered_by.name}
                                        </p>
                                    )}
                                </div>
                            </div>
                        )}

                        <div>
                            <p className="text-xs text-muted-foreground">
                                Status
                            </p>
                            <Badge
                                variant="outline"
                                className={getStatusBadgeClasses(order.status)}
                            >
                                {order.status.replace('_', ' ').toUpperCase()}
                            </Badge>
                        </div>

                        <div>
                            <p className="text-xs text-muted-foreground">
                                Priority
                            </p>
                            {order.priority !== 'routine' ? (
                                <Badge variant="destructive">
                                    {order.priority.toUpperCase()}
                                </Badge>
                            ) : (
                                <span className="text-sm">Routine</span>
                            )}
                        </div>
                    </div>

                    {/* Clinical Indication */}
                    {order.special_instructions && (
                        <div className="rounded-lg border-l-4 border-yellow-400 bg-yellow-50 p-4 dark:border-yellow-600 dark:bg-yellow-950/30">
                            <p className="mb-1 text-sm font-semibold text-yellow-900 dark:text-yellow-200">
                                Clinical Indication
                            </p>
                            <p className="text-sm text-yellow-800 dark:text-yellow-300">
                                {order.special_instructions}
                            </p>
                        </div>
                    )}

                    {/* Images Section */}
                    {hasImages && (
                        <div>
                            <h3 className="mb-3 flex items-center gap-2 text-lg font-semibold">
                                <Image className="h-5 w-5" />
                                Images ({attachments.length})
                            </h3>
                            <ImageGallery images={attachments} columns={3} />
                        </div>
                    )}

                    {/* Radiologist Report */}
                    {order.status === 'completed' && order.result_notes && (
                        <>
                            <Separator />
                            <div>
                                <h3 className="mb-4 flex items-center gap-2 text-lg font-semibold">
                                    <FileText className="h-5 w-5 text-green-600" />
                                    Radiologist Report
                                </h3>

                                {report.findings || report.impression ? (
                                    <div className="space-y-4">
                                        {report.findings && (
                                            <div>
                                                <h4 className="mb-2 text-sm font-semibold text-muted-foreground">
                                                    FINDINGS
                                                </h4>
                                                <div className="rounded-lg bg-muted/50 p-4">
                                                    <p className="text-sm whitespace-pre-wrap">
                                                        {report.findings}
                                                    </p>
                                                </div>
                                            </div>
                                        )}
                                        {report.impression && (
                                            <div>
                                                <h4 className="mb-2 text-sm font-semibold text-muted-foreground">
                                                    IMPRESSION
                                                </h4>
                                                <div className="rounded-lg border-l-4 border-green-500 bg-green-50 p-4 dark:bg-green-950/30">
                                                    <p className="text-sm whitespace-pre-wrap">
                                                        {report.impression}
                                                    </p>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                ) : report.raw ? (
                                    <div className="rounded-lg bg-muted/50 p-4">
                                        <p className="text-sm whitespace-pre-wrap">
                                            {report.raw}
                                        </p>
                                    </div>
                                ) : null}
                            </div>
                        </>
                    )}

                    {/* Pending State */}
                    {order.status !== 'completed' &&
                        order.status !== 'cancelled' &&
                        !hasImages && (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <Scan className="h-16 w-16 text-muted-foreground/50" />
                                <p className="mt-4 text-lg font-medium">
                                    Results Pending
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {order.status === 'ordered' &&
                                        'Waiting for imaging to be performed'}
                                    {order.status === 'in_progress' &&
                                        'Imaging in progress'}
                                    {order.status === 'external_referral' &&
                                        'Patient referred to external facility'}
                                </p>
                            </div>
                        )}

                    {/* Cancelled State */}
                    {order.status === 'cancelled' && (
                        <div className="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-950/30">
                            <p className="font-medium text-red-900 dark:text-red-200">
                                This imaging order was cancelled
                            </p>
                            {order.result_notes && (
                                <p className="mt-2 text-sm text-red-800 dark:text-red-300">
                                    Reason: {order.result_notes}
                                </p>
                            )}
                        </div>
                    )}
                </div>

                <div className="flex justify-end gap-2 border-t pt-4">
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Close
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}

export default ImagingResultsModal;
