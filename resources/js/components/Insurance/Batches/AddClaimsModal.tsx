import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
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
import { Loader2, Plus, Search } from 'lucide-react';
import { FormEvent, useMemo, useState } from 'react';

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
    isOpen: boolean;
    onClose: () => void;
    batchId: number;
    availableClaims: AvailableClaim[];
}

export default function AddClaimsModal({
    isOpen,
    onClose,
    batchId,
    availableClaims,
}: Props) {
    const [searchTerm, setSearchTerm] = useState('');
    const [selectedClaimIds, setSelectedClaimIds] = useState<number[]>([]);
    const [processing, setProcessing] = useState(false);

    const filteredClaims = useMemo(() => {
        if (!searchTerm) return availableClaims;
        const term = searchTerm.toLowerCase();
        return availableClaims.filter(
            (claim) =>
                claim.claim_check_code.toLowerCase().includes(term) ||
                claim.patient_name.toLowerCase().includes(term) ||
                claim.membership_id.toLowerCase().includes(term) ||
                claim.provider_name?.toLowerCase().includes(term),
        );
    }, [availableClaims, searchTerm]);

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        });
    };

    const handleToggleClaim = (claimId: number) => {
        setSelectedClaimIds((prev) =>
            prev.includes(claimId)
                ? prev.filter((id) => id !== claimId)
                : [...prev, claimId],
        );
    };

    const handleToggleAll = () => {
        if (selectedClaimIds.length === filteredClaims.length) {
            setSelectedClaimIds([]);
        } else {
            setSelectedClaimIds(filteredClaims.map((c) => c.id));
        }
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        router.post(
            `/admin/insurance/batches/${batchId}/claims`,
            {
                claim_ids: selectedClaimIds,
            },
            {
                onSuccess: () => {
                    setSelectedClaimIds([]);
                    setSearchTerm('');
                    onClose();
                },
                onFinish: () => setProcessing(false),
            },
        );
    };

    const handleClose = () => {
        setSelectedClaimIds([]);
        setSearchTerm('');
        onClose();
    };

    const selectedTotal = useMemo(() => {
        return availableClaims
            .filter((c) => selectedClaimIds.includes(c.id))
            .reduce((sum, c) => sum + parseFloat(c.total_claim_amount), 0);
    }, [availableClaims, selectedClaimIds]);

    return (
        <Dialog open={isOpen} onOpenChange={handleClose}>
            <DialogContent className="flex max-h-[80vh] max-w-4xl flex-col">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Plus className="h-5 w-5" />
                        Add Claims to Batch
                    </DialogTitle>
                    <DialogDescription>
                        Select vetted claims to add to this batch. Only vetted
                        claims that are not already in a batch are shown.
                    </DialogDescription>
                </DialogHeader>

                <div className="flex flex-1 flex-col overflow-hidden">
                    {/* Search */}
                    <div className="relative mb-4">
                        <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-500" />
                        <Input
                            placeholder="Search by claim code, patient, or provider..."
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            className="pl-9"
                        />
                    </div>

                    {/* Selection Summary */}
                    {selectedClaimIds.length > 0 && (
                        <div className="mb-4 flex items-center justify-between rounded-lg bg-blue-50 p-3 dark:bg-blue-900/20">
                            <span className="text-sm font-medium text-blue-700 dark:text-blue-300">
                                {selectedClaimIds.length} claim(s) selected
                            </span>
                            <span className="text-sm font-bold text-blue-700 dark:text-blue-300">
                                Total:{' '}
                                {formatCurrency(selectedTotal.toString())}
                            </span>
                        </div>
                    )}

                    {/* Claims Table */}
                    <div className="flex-1 overflow-auto rounded-lg border">
                        {filteredClaims.length > 0 ? (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-12">
                                            <Checkbox
                                                checked={
                                                    selectedClaimIds.length ===
                                                        filteredClaims.length &&
                                                    filteredClaims.length > 0
                                                }
                                                onCheckedChange={
                                                    handleToggleAll
                                                }
                                            />
                                        </TableHead>
                                        <TableHead>Claim Code</TableHead>
                                        <TableHead>Patient</TableHead>
                                        <TableHead>Provider</TableHead>
                                        <TableHead>Date</TableHead>
                                        <TableHead className="text-right">
                                            Amount
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {filteredClaims.map((claim) => (
                                        <TableRow
                                            key={claim.id}
                                            className="cursor-pointer"
                                            onClick={() =>
                                                handleToggleClaim(claim.id)
                                            }
                                        >
                                            <TableCell>
                                                <Checkbox
                                                    checked={selectedClaimIds.includes(
                                                        claim.id,
                                                    )}
                                                    onCheckedChange={() =>
                                                        handleToggleClaim(
                                                            claim.id,
                                                        )
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell className="font-mono font-medium">
                                                {claim.claim_check_code}
                                            </TableCell>
                                            <TableCell>
                                                <div>
                                                    <div className="font-medium">
                                                        {claim.patient_name}
                                                    </div>
                                                    <div className="text-sm text-gray-500">
                                                        {claim.membership_id}
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {claim.provider_name}
                                            </TableCell>
                                            <TableCell>
                                                {formatDate(
                                                    claim.date_of_attendance,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right font-medium">
                                                {formatCurrency(
                                                    claim.total_claim_amount,
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        ) : (
                            <div className="p-8 text-center text-gray-500">
                                {searchTerm
                                    ? 'No claims match your search'
                                    : 'No vetted claims available'}
                            </div>
                        )}
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
                        onClick={handleSubmit}
                        disabled={processing || selectedClaimIds.length === 0}
                    >
                        {processing ? (
                            <>
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                Adding...
                            </>
                        ) : (
                            <>
                                <Plus className="mr-2 h-4 w-4" />
                                Add {selectedClaimIds.length} Claim(s)
                            </>
                        )}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
