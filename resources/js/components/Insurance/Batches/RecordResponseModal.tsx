import { Button } from '@/components/ui/button';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatCurrency } from '@/lib/utils';
import { router } from '@inertiajs/react';
import { ClipboardList, Loader2 } from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';

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

interface ClaimBatch {
    id: number;
    batch_number: string;
    name: string;
    total_amount: string;
    batch_items: BatchItem[];
}

interface ClaimResponse {
    status: 'approved' | 'rejected' | 'paid';
    approved_amount?: string;
    rejection_reason?: string;
}

interface Props {
    isOpen: boolean;
    onClose: () => void;
    batch: ClaimBatch;
}

export default function RecordResponseModal({ isOpen, onClose, batch }: Props) {
    const [responses, setResponses] = useState<Record<number, ClaimResponse>>(
        {},
    );
    const [paidAt, setPaidAt] = useState('');
    const [paidAmount, setPaidAmount] = useState('');
    const [processing, setProcessing] = useState(false);

    // Initialize responses from existing batch items
    useEffect(() => {
        if (isOpen) {
            const initialResponses: Record<number, ClaimResponse> = {};
            batch.batch_items.forEach((item) => {
                if (item.status !== 'pending') {
                    initialResponses[item.insurance_claim_id] = {
                        status: item.status,
                        approved_amount: item.approved_amount || undefined,
                        rejection_reason: item.rejection_reason || undefined,
                    };
                }
            });
            setResponses(initialResponses);
        }
    }, [isOpen, batch.batch_items]);

    const handleResponseChange = (
        claimId: number,
        field: keyof ClaimResponse,
        value: string,
    ) => {
        setResponses((prev) => ({
            ...prev,
            [claimId]: {
                ...prev[claimId],
                [field]: value,
            },
        }));
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();

        // Transform responses to the format expected by the API
        const formattedResponses: Record<number, any> = {};
        Object.entries(responses).forEach(([claimId, response]) => {
            if (response.status) {
                formattedResponses[parseInt(claimId)] = {
                    status: response.status,
                    approved_amount:
                        response.status === 'approved' ||
                        response.status === 'paid'
                            ? response.approved_amount
                            : null,
                    rejection_reason:
                        response.status === 'rejected'
                            ? response.rejection_reason
                            : null,
                };
            }
        });

        setProcessing(true);
        router.post(
            `/admin/insurance/batches/${batch.id}/response`,
            {
                responses: formattedResponses,
                paid_at: paidAt || null,
                paid_amount: paidAmount || null,
            },
            {
                onSuccess: () => {
                    onClose();
                },
                onFinish: () => setProcessing(false),
            },
        );
    };

    const handleClose = () => {
        setResponses({});
        setPaidAt('');
        setPaidAmount('');
        onClose();
    };

    // Calculate totals
    const totalApproved = Object.values(responses)
        .filter((r) => r.status === 'approved' || r.status === 'paid')
        .reduce(
            (sum, r) => sum + (parseFloat(r.approved_amount || '0') || 0),
            0,
        );

    const totalRejected = batch.batch_items
        .filter(
            (item) => responses[item.insurance_claim_id]?.status === 'rejected',
        )
        .reduce((sum, item) => sum + parseFloat(item.claim_amount), 0);

    return (
        <Dialog open={isOpen} onOpenChange={handleClose}>
            <DialogContent className="flex max-h-[85vh] max-w-5xl flex-col">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <ClipboardList className="h-5 w-5" />
                        Record NHIA Response
                    </DialogTitle>
                    <DialogDescription>
                        Record the NHIA response for each claim in batch{' '}
                        {batch.batch_number}. Update the status and amounts
                        based on the NHIA feedback.
                    </DialogDescription>
                </DialogHeader>

                <form
                    onSubmit={handleSubmit}
                    className="flex flex-1 flex-col overflow-hidden"
                >
                    {/* Summary */}
                    <div className="mb-4 grid grid-cols-3 gap-4">
                        <div className="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                            <p className="text-sm text-gray-500">
                                Total Claimed
                            </p>
                            <p className="text-lg font-bold">
                                {formatCurrency(batch.total_amount)}
                            </p>
                        </div>
                        <div className="rounded-lg bg-green-50 p-3 dark:bg-green-900/20">
                            <p className="text-sm text-green-600">
                                Total Approved
                            </p>
                            <p className="text-lg font-bold text-green-600">
                                {formatCurrency(totalApproved.toString())}
                            </p>
                        </div>
                        <div className="rounded-lg bg-red-50 p-3 dark:bg-red-900/20">
                            <p className="text-sm text-red-600">
                                Total Rejected
                            </p>
                            <p className="text-lg font-bold text-red-600">
                                {formatCurrency(totalRejected.toString())}
                            </p>
                        </div>
                    </div>

                    {/* Claims Table */}
                    <div className="mb-4 flex-1 overflow-auto rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Claim</TableHead>
                                    <TableHead>Patient</TableHead>
                                    <TableHead className="text-right">
                                        Claimed
                                    </TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">
                                        Approved Amount
                                    </TableHead>
                                    <TableHead>Rejection Reason</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {batch.batch_items.map((item) => (
                                    <TableRow key={item.id}>
                                        <TableCell className="font-mono text-sm">
                                            {item.claim?.claim_check_code ||
                                                '-'}
                                        </TableCell>
                                        <TableCell>
                                            <div className="text-sm">
                                                <div className="font-medium">
                                                    {item.claim?.patient_name ||
                                                        '-'}
                                                </div>
                                                <div className="text-gray-500">
                                                    {item.claim
                                                        ?.membership_id || '-'}
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-right font-medium">
                                            {formatCurrency(item.claim_amount)}
                                        </TableCell>
                                        <TableCell>
                                            <Select
                                                value={
                                                    responses[
                                                        item.insurance_claim_id
                                                    ]?.status || ''
                                                }
                                                onValueChange={(value) =>
                                                    handleResponseChange(
                                                        item.insurance_claim_id,
                                                        'status',
                                                        value,
                                                    )
                                                }
                                            >
                                                <SelectTrigger className="w-32">
                                                    <SelectValue placeholder="Select..." />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="approved">
                                                        Approved
                                                    </SelectItem>
                                                    <SelectItem value="rejected">
                                                        Rejected
                                                    </SelectItem>
                                                    <SelectItem value="paid">
                                                        Paid
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </TableCell>
                                        <TableCell>
                                            <Input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                placeholder="0.00"
                                                className="w-28"
                                                value={
                                                    responses[
                                                        item.insurance_claim_id
                                                    ]?.approved_amount || ''
                                                }
                                                onChange={(e) =>
                                                    handleResponseChange(
                                                        item.insurance_claim_id,
                                                        'approved_amount',
                                                        e.target.value,
                                                    )
                                                }
                                                disabled={
                                                    responses[
                                                        item.insurance_claim_id
                                                    ]?.status === 'rejected'
                                                }
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <Input
                                                placeholder="Reason..."
                                                className="w-40"
                                                value={
                                                    responses[
                                                        item.insurance_claim_id
                                                    ]?.rejection_reason || ''
                                                }
                                                onChange={(e) =>
                                                    handleResponseChange(
                                                        item.insurance_claim_id,
                                                        'rejection_reason',
                                                        e.target.value,
                                                    )
                                                }
                                                disabled={
                                                    responses[
                                                        item.insurance_claim_id
                                                    ]?.status !== 'rejected'
                                                }
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>

                    {/* Payment Details */}
                    <div className="grid grid-cols-2 gap-4 border-t pt-4">
                        <div className="space-y-2">
                            <Label htmlFor="paid_at">
                                Payment Date (Optional)
                            </Label>
                            <Input
                                id="paid_at"
                                type="date"
                                value={paidAt}
                                onChange={(e) => setPaidAt(e.target.value)}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="paid_amount">
                                Total Payment Received (Optional)
                            </Label>
                            <Input
                                id="paid_amount"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="0.00"
                                value={paidAmount}
                                onChange={(e) => setPaidAmount(e.target.value)}
                            />
                        </div>
                    </div>

                    <DialogFooter className="mt-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={handleClose}
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={
                                processing ||
                                Object.keys(responses).length === 0
                            }
                        >
                            {processing ? (
                                <>
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    Saving...
                                </>
                            ) : (
                                'Save Responses'
                            )}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
