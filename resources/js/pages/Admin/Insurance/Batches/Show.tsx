import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Calendar,
    CheckCircle,
    ClipboardList,
    Clock,
    Download,
    FileCheck,
    FileSpreadsheet,
    FileText,
    Lock,
    Package,
    RefreshCw,
    Send,
    Trash2,
    Unlock,
    User,
} from 'lucide-react';
import { lazy, Suspense, useState } from 'react';

// Lazy load modals
const RecordResponseModal = lazy(
    () => import('@/components/Insurance/Batches/RecordResponseModal'),
);

interface BatchItem {
    id: number;
    insurance_claim_id: number;
    claim_amount: string;
    approved_amount: string | null;
    status: 'pending' | 'approved' | 'rejected' | 'paid';
    status_label: string;
    rejection_reason: string | null;
    claim?: {
        id: number;
        claim_check_code: string;
        patient_name: string;
        membership_id: string;
        date_of_attendance: string;
        total_claim_amount: string;
        provider_name: string;
    };
}

interface StatusHistoryItem {
    id: number;
    previous_status: string | null;
    new_status: string;
    notes: string | null;
    created_at: string;
    user?: {
        id: number;
        name: string;
    };
}

interface ClaimBatch {
    id: number;
    batch_number: string;
    name: string;
    submission_period: string;
    submission_period_formatted: string;
    status: 'draft' | 'finalized' | 'submitted' | 'processing' | 'completed';
    status_label: string;
    total_claims: number;
    total_amount: string;
    approved_amount: string | null;
    paid_amount: string | null;
    submitted_at: string | null;
    exported_at: string | null;
    paid_at: string | null;
    created_at: string;
    notes: string | null;
    is_draft: boolean;
    is_finalized: boolean;
    is_submitted: boolean;
    is_completed: boolean;
    can_be_modified: boolean;
    creator?: {
        id: number;
        name: string;
    };
    batch_items: BatchItem[];
    status_history?: StatusHistoryItem[];
}

interface AvailableClaim {
    id: number;
    claim_check_code: string;
    patient_name: string;
    membership_id: string;
    date_of_attendance: string;
    total_claim_amount: string;
    provider_name: string;
}

interface Props {
    batch: ClaimBatch;
    availableClaims: AvailableClaim[];
    can: {
        modify: boolean;
        finalize: boolean;
        submit: boolean;
        export: boolean;
        recordResponse: boolean;
        revertToDraft: boolean;
        delete: boolean;
    };
}

const statusConfig: Record<
    string,
    { label: string; color: string; icon: React.ReactNode }
> = {
    draft: {
        label: 'Draft',
        color: 'bg-gray-500',
        icon: <FileText className="h-4 w-4" />,
    },
    finalized: {
        label: 'Finalized',
        color: 'bg-blue-500',
        icon: <FileCheck className="h-4 w-4" />,
    },
    submitted: {
        label: 'Submitted',
        color: 'bg-purple-500',
        icon: <Send className="h-4 w-4" />,
    },
    processing: {
        label: 'Processing',
        color: 'bg-yellow-500',
        icon: <Clock className="h-4 w-4" />,
    },
    completed: {
        label: 'Completed',
        color: 'bg-green-500',
        icon: <CheckCircle className="h-4 w-4" />,
    },
};

const itemStatusConfig: Record<string, { label: string; color: string }> = {
    pending: { label: 'Pending', color: 'bg-gray-500' },
    approved: { label: 'Approved', color: 'bg-green-500' },
    rejected: { label: 'Rejected', color: 'bg-red-500' },
    paid: { label: 'Paid', color: 'bg-emerald-600' },
};

export default function BatchShow({ batch, availableClaims, can }: Props) {
    const [recordResponseModalOpen, setRecordResponseModalOpen] =
        useState(false);
    const [confirmFinalizeOpen, setConfirmFinalizeOpen] = useState(false);
    const [confirmSubmitOpen, setConfirmSubmitOpen] = useState(false);
    const [confirmRevertOpen, setConfirmRevertOpen] = useState(false);
    const [confirmDeleteOpen, setConfirmDeleteOpen] = useState(false);
    const [removingClaimId, setRemovingClaimId] = useState<number | null>(null);
    const [refreshing, setRefreshing] = useState(false);

    const formatCurrency = (amount: string | null) => {
        if (!amount) return 'GHS 0.00';
        return new Intl.NumberFormat('en-GH', {
            style: 'currency',
            currency: 'GHS',
        }).format(parseFloat(amount));
    };

    const formatDate = (dateString: string | null) => {
        if (!dateString) return '-';
        return new Date(dateString).toLocaleDateString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        });
    };

    const formatDateTime = (dateString: string | null) => {
        if (!dateString) return '-';
        return new Date(dateString).toLocaleString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const handleRemoveClaim = (claimId: number) => {
        router.delete(
            `/admin/insurance/batches/${batch.id}/claims/${claimId}`,
            {
                onSuccess: () => setRemovingClaimId(null),
            },
        );
    };

    const handleFinalize = () => {
        router.post(
            `/admin/insurance/batches/${batch.id}/finalize`,
            {},
            {
                onSuccess: () => setConfirmFinalizeOpen(false),
            },
        );
    };

    const handleSubmit = () => {
        router.post(
            `/admin/insurance/batches/${batch.id}/submit`,
            {},
            {
                onSuccess: () => setConfirmSubmitOpen(false),
            },
        );
    };

    const handleExport = () => {
        window.location.href = `/admin/insurance/batches/${batch.id}/export`;
    };

    const handleExcelExport = () => {
        window.location.href = `/admin/insurance/batches/${batch.id}/export-excel`;
    };

    const handleRefreshClaims = () => {
        setRefreshing(true);
        router.post(
            `/admin/insurance/batches/${batch.id}/refresh-claims`,
            {},
            {
                onFinish: () => setRefreshing(false),
            },
        );
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                { title: 'Insurance', href: '/admin/insurance' },
                { title: 'Claim Batches', href: '/admin/insurance/batches' },
                { title: batch.name, href: '' },
            ]}
        >
            <Head title={`Batch: ${batch.name}`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div>
                        <Button
                            variant="ghost"
                            size="sm"
                            className="mb-2"
                            onClick={() =>
                                router.get('/admin/insurance/batches')
                            }
                        >
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to Batches
                        </Button>
                        <h1 className="flex items-center gap-2 text-3xl font-bold text-gray-900 dark:text-gray-100">
                            <Package className="h-8 w-8" />
                            {batch.name}
                        </h1>
                        <p className="mt-1 font-mono text-gray-600 dark:text-gray-400">
                            {batch.batch_number}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Badge
                            className={
                                statusConfig[batch.status]?.color ||
                                'bg-gray-500'
                            }
                        >
                            {statusConfig[batch.status]?.label || batch.status}
                        </Badge>
                    </div>
                </div>

                {/* Batch Details */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* Main Info */}
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Batch Details</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                                <div>
                                    <p className="text-sm text-gray-500">
                                        Submission Period
                                    </p>
                                    <p className="flex items-center gap-1 font-medium">
                                        <Calendar className="h-4 w-4 text-gray-400" />
                                        {batch.submission_period_formatted}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500">
                                        Total Claims
                                    </p>
                                    <p className="font-medium">
                                        {batch.total_claims}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500">
                                        Total Amount
                                    </p>
                                    <p className="font-medium text-blue-600">
                                        {formatCurrency(batch.total_amount)}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500">
                                        Approved Amount
                                    </p>
                                    <p className="font-medium text-green-600">
                                        {batch.approved_amount
                                            ? formatCurrency(
                                                batch.approved_amount,
                                            )
                                            : '-'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500">
                                        Created By
                                    </p>
                                    <p className="flex items-center gap-1 font-medium">
                                        <User className="h-4 w-4 text-gray-400" />
                                        {batch.creator?.name || 'Unknown'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500">
                                        Created At
                                    </p>
                                    <p className="font-medium">
                                        {formatDateTime(batch.created_at)}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500">
                                        Exported At
                                    </p>
                                    <p className="font-medium">
                                        {batch.exported_at
                                            ? formatDateTime(batch.exported_at)
                                            : '-'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-sm text-gray-500">
                                        Submitted At
                                    </p>
                                    <p className="font-medium">
                                        {batch.submitted_at
                                            ? formatDateTime(batch.submitted_at)
                                            : '-'}
                                    </p>
                                </div>
                            </div>
                            {batch.notes && (
                                <div className="mt-4 border-t pt-4">
                                    <p className="text-sm text-gray-500">
                                        Notes
                                    </p>
                                    <p className="mt-1 text-gray-700 dark:text-gray-300">
                                        {batch.notes}
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Actions */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Actions</CardTitle>
                            <CardDescription>
                                {batch.is_draft &&
                                    'This batch is in draft mode. Use Refresh Claims to pull in new vetted claims, then finalize when ready.'}
                                {batch.is_finalized &&
                                    !batch.is_submitted &&
                                    'This batch is finalized. Export and submit to NHIA.'}
                                {batch.is_submitted &&
                                    !batch.is_completed &&
                                    'This batch has been submitted. Record NHIA response when received.'}
                                {batch.is_completed &&
                                    'This batch is completed.'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {can.modify && (
                                <Button
                                    className="w-full"
                                    variant="outline"
                                    onClick={handleRefreshClaims}
                                    disabled={refreshing}
                                >
                                    <RefreshCw
                                        className={`mr-2 h-4 w-4 ${refreshing ? 'animate-spin' : ''}`}
                                    />
                                    {refreshing
                                        ? 'Refreshing...'
                                        : 'Refresh Claims'}
                                </Button>
                            )}
                            {can.revertToDraft && (
                                <Button
                                    className="w-full"
                                    variant="outline"
                                    onClick={() => setConfirmRevertOpen(true)}
                                >
                                    <Unlock className="mr-2 h-4 w-4" />
                                    Revert to Draft
                                </Button>
                            )}
                            {can.finalize && (
                                <Button
                                    className="w-full"
                                    variant="secondary"
                                    onClick={() => setConfirmFinalizeOpen(true)}
                                    disabled={batch.total_claims === 0}
                                >
                                    <Lock className="mr-2 h-4 w-4" />
                                    Finalize Batch
                                </Button>
                            )}
                            {can.export && (
                                <Button
                                    className="w-full"
                                    variant="outline"
                                    onClick={handleExport}
                                >
                                    <Download className="mr-2 h-4 w-4" />
                                    Export XML
                                </Button>
                            )}
                            {can.export && (
                                <Button
                                    className="w-full"
                                    variant="outline"
                                    onClick={handleExcelExport}
                                >
                                    <FileSpreadsheet className="mr-2 h-4 w-4" />
                                    Export Excel
                                </Button>
                            )}
                            {can.submit && (
                                <Button
                                    className="w-full"
                                    variant="default"
                                    onClick={() => setConfirmSubmitOpen(true)}
                                >
                                    <Send className="mr-2 h-4 w-4" />
                                    Mark as Submitted
                                </Button>
                            )}
                            {can.recordResponse && (
                                <Button
                                    className="w-full"
                                    variant="default"
                                    onClick={() =>
                                        setRecordResponseModalOpen(true)
                                    }
                                >
                                    <ClipboardList className="mr-2 h-4 w-4" />
                                    Record NHIA Response
                                </Button>
                            )}
                            {can.delete && (
                                <Button
                                    className="w-full"
                                    variant="destructive"
                                    onClick={() => setConfirmDeleteOpen(true)}
                                >
                                    <Trash2 className="mr-2 h-4 w-4" />
                                    Delete Batch
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Claims List */}
                <Card>
                    <CardHeader>
                        <CardTitle>
                            Claims in Batch ({batch.batch_items.length})
                        </CardTitle>
                        <CardDescription>
                            All claims included in this batch for submission
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="p-0">
                        {batch.batch_items.length > 0 ? (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Claim Code</TableHead>
                                            <TableHead>Patient</TableHead>
                                            <TableHead>Provider</TableHead>
                                            <TableHead>Date</TableHead>
                                            <TableHead className="text-right">
                                                Claim Amount
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Approved
                                            </TableHead>
                                            <TableHead>Status</TableHead>
                                            {can.modify && (
                                                <TableHead className="text-right">
                                                    Actions
                                                </TableHead>
                                            )}
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {batch.batch_items.map((item) => (
                                            <TableRow key={item.id}>
                                                <TableCell className="font-mono font-medium">
                                                    {item.claim
                                                        ?.claim_check_code ||
                                                        '-'}
                                                </TableCell>
                                                <TableCell>
                                                    <div>
                                                        <div className="font-medium">
                                                            {item.claim
                                                                ?.patient_name ||
                                                                '-'}
                                                        </div>
                                                        <div className="text-sm text-gray-500">
                                                            {item.claim
                                                                ?.membership_id ||
                                                                '-'}
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    {item.claim
                                                        ?.provider_name || '-'}
                                                </TableCell>
                                                <TableCell>
                                                    {formatDate(
                                                        item.claim
                                                            ?.date_of_attendance ||
                                                        null,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right font-medium">
                                                    {formatCurrency(
                                                        item.claim_amount,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    {item.approved_amount ? (
                                                        <span className="font-medium text-green-600">
                                                            {formatCurrency(
                                                                item.approved_amount,
                                                            )}
                                                        </span>
                                                    ) : (
                                                        <span className="text-gray-400">
                                                            -
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="space-y-1">
                                                        <Badge
                                                            className={
                                                                itemStatusConfig[
                                                                    item.status
                                                                ]?.color ||
                                                                'bg-gray-500'
                                                            }
                                                        >
                                                            {itemStatusConfig[
                                                                item.status
                                                            ]?.label ||
                                                                item.status}
                                                        </Badge>
                                                        {item.rejection_reason && (
                                                            <p className="text-xs text-red-500">
                                                                {
                                                                    item.rejection_reason
                                                                }
                                                            </p>
                                                        )}
                                                    </div>
                                                </TableCell>
                                                {can.modify && (
                                                    <TableCell className="text-right">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                setRemovingClaimId(
                                                                    item.insurance_claim_id,
                                                                )
                                                            }
                                                            className="text-red-600 hover:text-red-700"
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </TableCell>
                                                )}
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        ) : (
                            <div className="p-12 text-center">
                                <FileText className="mx-auto mb-4 h-16 w-16 text-gray-300 dark:text-gray-600" />
                                <h3 className="mb-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    No claims in this batch
                                </h3>
                                <p className="text-gray-600 dark:text-gray-400">
                                    Use the Refresh Claims button to pull in
                                    vetted claims for this month.
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Status History */}
                {batch.status_history && batch.status_history.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Status History</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                {batch.status_history.map((history) => (
                                    <div
                                        key={history.id}
                                        className="flex items-start gap-4 border-l-2 border-gray-200 pl-4 dark:border-gray-700"
                                    >
                                        <div className="flex-1">
                                            <div className="flex items-center gap-2">
                                                {history.previous_status && (
                                                    <>
                                                        <Badge variant="outline">
                                                            {
                                                                history.previous_status
                                                            }
                                                        </Badge>
                                                        <span className="text-gray-400">
                                                            →
                                                        </span>
                                                    </>
                                                )}
                                                <Badge
                                                    className={
                                                        statusConfig[
                                                            history.new_status
                                                        ]?.color ||
                                                        'bg-gray-500'
                                                    }
                                                >
                                                    {statusConfig[
                                                        history.new_status
                                                    ]?.label ||
                                                        history.new_status}
                                                </Badge>
                                            </div>
                                            {history.notes && (
                                                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                                    {history.notes}
                                                </p>
                                            )}
                                            <p className="mt-1 text-xs text-gray-500">
                                                {history.user?.name || 'System'}{' '}
                                                •{' '}
                                                {formatDateTime(
                                                    history.created_at,
                                                )}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>

            {/* Record Response Modal */}
            <Suspense fallback={null}>
                <RecordResponseModal
                    isOpen={recordResponseModalOpen}
                    onClose={() => setRecordResponseModalOpen(false)}
                    batch={batch}
                />
            </Suspense>

            {/* Confirm Finalize Dialog */}
            <AlertDialog
                open={confirmFinalizeOpen}
                onOpenChange={setConfirmFinalizeOpen}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Finalize Batch?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Once finalized, you cannot add or remove claims from
                            this batch. Make sure all claims are correct before
                            proceeding.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={handleFinalize}>
                            Finalize Batch
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {/* Confirm Submit Dialog */}
            <AlertDialog
                open={confirmSubmitOpen}
                onOpenChange={setConfirmSubmitOpen}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Mark as Submitted?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This will mark the batch as submitted to NHIA. Make
                            sure you have uploaded the exported XML file to the
                            NHIA portal.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={handleSubmit}>
                            Mark as Submitted
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {/* Confirm Remove Claim Dialog */}
            <AlertDialog
                open={removingClaimId !== null}
                onOpenChange={() => setRemovingClaimId(null)}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Remove Claim?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to remove this claim from the
                            batch? The claim will remain vetted and can be added
                            to another batch.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={() =>
                                removingClaimId &&
                                handleRemoveClaim(removingClaimId)
                            }
                            className="bg-red-600 hover:bg-red-700"
                        >
                            Remove Claim
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {/* Confirm Revert to Draft Dialog */}
            <AlertDialog
                open={confirmRevertOpen}
                onOpenChange={setConfirmRevertOpen}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Revert Batch to Draft?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            This will revert the batch back to draft status,
                            allowing you to add or remove claims. The batch will
                            need to be finalized and submitted again.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={() => {
                                router.post(
                                    `/admin/insurance/batches/${batch.id}/unfinalize`,
                                    {},
                                    {
                                        onSuccess: () =>
                                            setConfirmRevertOpen(false),
                                    },
                                );
                            }}
                        >
                            Revert to Draft
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {/* Confirm Delete Dialog */}
            <AlertDialog
                open={confirmDeleteOpen}
                onOpenChange={setConfirmDeleteOpen}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete Batch?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This will permanently delete this batch and remove
                            all claims from it. The claims themselves will not be
                            deleted and can be added to a new batch.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={() => {
                                router.delete(
                                    `/admin/insurance/batches/${batch.id}`,
                                );
                            }}
                            className="bg-red-600 hover:bg-red-700"
                        >
                            Delete Batch
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
