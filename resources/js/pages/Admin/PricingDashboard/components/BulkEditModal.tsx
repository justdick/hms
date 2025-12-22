import { Badge } from '@/components/ui/badge';
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
import { ScrollArea } from '@/components/ui/scroll-area';
import { router } from '@inertiajs/react';
import { AlertCircle, CheckCircle } from 'lucide-react';
import { useState } from 'react';
import type { PricingItem } from '../Index';

interface BulkEditModalProps {
    open: boolean;
    onClose: () => void;
    selectedItems: PricingItem[];
    planId: number;
    isNhis: boolean;
}

export function BulkEditModal({
    open,
    onClose,
    selectedItems,
    planId,
    isNhis,
}: BulkEditModalProps) {
    const [copayAmount, setCopayAmount] = useState('');
    const [processing, setProcessing] = useState(false);
    const [result, setResult] = useState<{
        success: boolean;
        message: string;
    } | null>(null);

    const handleSubmit = () => {
        const numValue = parseFloat(copayAmount);
        if (isNaN(numValue) || numValue < 0) {
            setResult({
                success: false,
                message: 'Please enter a valid copay amount (0 or greater)',
            });
            return;
        }

        setProcessing(true);
        setResult(null);

        // Include is_mapped status for NHIS plans so backend knows which method to use
        const items = selectedItems.map((item) => ({
            type: item.type,
            id: item.id,
            code: item.code,
            is_mapped: item.is_mapped,
        }));

        router.post(
            '/admin/pricing-dashboard/bulk-update',
            {
                plan_id: planId,
                items,
                copay: numValue,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setProcessing(false);
                    setResult({
                        success: true,
                        message: `Successfully updated ${selectedItems.length} items`,
                    });
                    setTimeout(() => {
                        onClose();
                        setCopayAmount('');
                        setResult(null);
                    }, 1500);
                },
                onError: (errors) => {
                    setProcessing(false);
                    setResult({
                        success: false,
                        message:
                            Object.values(errors).flat().join(', ') ||
                            'Failed to update items',
                    });
                },
            },
        );
    };

    const handleClose = () => {
        if (!processing) {
            onClose();
            setCopayAmount('');
            setResult(null);
        }
    };

    // Group items by category for display
    const groupedItems = selectedItems.reduce(
        (acc, item) => {
            if (!acc[item.category]) {
                acc[item.category] = [];
            }
            acc[item.category].push(item);
            return acc;
        },
        {} as Record<string, PricingItem[]>,
    );

    // Count mapped vs unmapped for NHIS
    const mappedCount = selectedItems.filter((item) => item.is_mapped).length;
    const unmappedCount = selectedItems.length - mappedCount;

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Bulk Edit Copay</DialogTitle>
                    <DialogDescription>
                        Set the same copay amount for {selectedItems.length} selected
                        items.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4 py-4">
                    {/* NHIS Info about mapped/unmapped items */}
                    {isNhis && unmappedCount > 0 && (
                        <div className="flex items-start gap-2 rounded-md border border-blue-200 bg-blue-50 p-3 text-blue-800 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-200">
                            <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
                            <div className="text-sm">
                                <strong>{unmappedCount}</strong> item(s) are not mapped to NHIS tariffs.
                                {mappedCount > 0 && (
                                    <> <strong>{mappedCount}</strong> item(s) are mapped.</>
                                )}
                                {' '}Unmapped items will use flexible copay (patient pays full amount).
                            </div>
                        </div>
                    )}

                    {/* Selected Items Summary */}
                    <div className="space-y-2">
                        <Label>Selected Items ({selectedItems.length})</Label>
                        <ScrollArea className="h-40 rounded-md border p-3">
                            {Object.entries(groupedItems).map(([category, items]) => (
                                <div key={category} className="mb-3 last:mb-0">
                                    <div className="mb-1 flex items-center gap-2">
                                        <Badge variant="outline" className="text-xs">
                                            {category}
                                        </Badge>
                                        <span className="text-xs text-gray-500">
                                            ({items.length} items)
                                        </span>
                                    </div>
                                    <ul className="space-y-1 pl-2">
                                        {items.slice(0, 5).map((item) => (
                                            <li
                                                key={`${item.type}-${item.id}`}
                                                className="text-sm text-gray-600 dark:text-gray-400"
                                            >
                                                {item.name}
                                                {item.code && (
                                                    <span className="ml-1 font-mono text-xs text-gray-400">
                                                        ({item.code})
                                                    </span>
                                                )}
                                            </li>
                                        ))}
                                        {items.length > 5 && (
                                            <li className="text-xs text-gray-400">
                                                ... and {items.length - 5} more
                                            </li>
                                        )}
                                    </ul>
                                </div>
                            ))}
                        </ScrollArea>
                    </div>

                    {/* Copay Input */}
                    <div className="space-y-2">
                        <Label htmlFor="copay-amount">
                            {isNhis ? 'Patient Copay Amount' : 'Fixed Copay Amount'}
                        </Label>
                        <div className="flex items-center gap-2">
                            <span className="text-gray-500">GH₵</span>
                            <Input
                                id="copay-amount"
                                type="number"
                                min="0"
                                step="0.01"
                                value={copayAmount}
                                onChange={(e) => setCopayAmount(e.target.value)}
                                placeholder="0.00"
                                disabled={processing}
                            />
                        </div>
                        <p className="text-xs text-gray-500">
                            This amount will be applied to all selected items.
                        </p>
                    </div>

                    {/* Result Message */}
                    {result && (
                        <div
                            className={`flex items-center gap-2 rounded-md p-3 ${
                                result.success
                                    ? 'bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300'
                                    : 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300'
                            }`}
                        >
                            {result.success ? (
                                <CheckCircle className="h-4 w-4" />
                            ) : (
                                <AlertCircle className="h-4 w-4" />
                            )}
                            <span className="text-sm">{result.message}</span>
                        </div>
                    )}
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={handleClose}
                        disabled={processing}
                    >
                        Cancel
                    </Button>
                    <Button onClick={handleSubmit} disabled={processing || !copayAmount}>
                        {processing ? 'Updating...' : 'Update All'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
