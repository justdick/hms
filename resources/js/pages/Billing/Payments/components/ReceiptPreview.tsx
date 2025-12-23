import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Loader2, Printer, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { PrintableReceipt } from './PrintableReceipt';

interface ChargeItem {
    id: number;
    description: string;
    service_type: string;
    amount: number;
    paid_amount: number;
    is_insurance_claim: boolean;
    insurance_covered_amount: number;
    patient_copay_amount: number;
}

interface ReceiptData {
    receipt_number: string;
    hospital: {
        name: string;
        address: string;
        phone: string;
    };
    date: string;
    time: string;
    datetime: string;
    patient: {
        name: string;
        patient_number: string;
    };
    charge?: ChargeItem;
    charges?: ChargeItem[];
    totals?: {
        amount: number;
        paid: number;
        insurance_covered: number;
        patient_copay: number;
    };
    cashier: {
        name: string;
    };
}

interface ReceiptPreviewProps {
    isOpen: boolean;
    onClose: () => void;
    chargeIds: number[];
    formatCurrency: (amount: number) => string;
    onPrintSuccess?: () => void;
}

export function ReceiptPreview({
    isOpen,
    onClose,
    chargeIds,
    formatCurrency,
    onPrintSuccess,
}: ReceiptPreviewProps) {
    const [receipt, setReceipt] = useState<ReceiptData | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [isPrinting, setIsPrinting] = useState(false);
    const receiptRef = useRef<HTMLDivElement>(null);

    const fetchReceipt = useCallback(async () => {
        if (!chargeIds.length) return;

        setIsLoading(true);
        setError(null);

        try {
            const response = await fetch('/billing/receipt', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        document.querySelector<HTMLMetaElement>(
                            'meta[name="csrf-token"]',
                        )?.content || '',
                },
                body: JSON.stringify({ charge_ids: chargeIds }),
            });

            if (!response.ok) {
                const data = await response.json();
                throw new Error(data.error || 'Failed to fetch receipt');
            }

            const data = await response.json();
            setReceipt(data.receipt);
        } catch (err) {
            setError(
                err instanceof Error ? err.message : 'Failed to load receipt',
            );
        } finally {
            setIsLoading(false);
        }
    }, [chargeIds]);

    useEffect(() => {
        if (isOpen && chargeIds.length > 0) {
            fetchReceipt();
        }
    }, [isOpen, chargeIds, fetchReceipt]);

    const handlePrint = async () => {
        if (!receipt || !receiptRef.current) return;

        setIsPrinting(true);

        try {
            // Log the print action
            await fetch('/billing/receipt/log-print', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        document.querySelector<HTMLMetaElement>(
                            'meta[name="csrf-token"]',
                        )?.content || '',
                },
                body: JSON.stringify({
                    charge_ids: chargeIds,
                    receipt_number: receipt.receipt_number,
                }),
            });

            // Create a new window for printing
            const printWindow = window.open(
                '',
                '_blank',
                'width=400,height=600',
            );
            if (!printWindow) {
                throw new Error(
                    'Could not open print window. Please allow popups.',
                );
            }

            // Write the receipt content to the new window
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Receipt - ${receipt.receipt_number}</title>
                    <style>
                        @page {
                            size: 80mm auto;
                            margin: 0;
                        }
                        body {
                            margin: 0;
                            padding: 0;
                            font-family: monospace;
                            font-size: 12px;
                            line-height: 1.4;
                        }
                        .receipt-container {
                            width: 72mm;
                            padding: 4mm;
                            margin: 0 auto;
                        }
                        .text-center { text-align: center; }
                        .font-bold { font-weight: bold; }
                        .mb-1 { margin-bottom: 4px; }
                        .mb-2 { margin-bottom: 8px; }
                        .my-1 { margin-top: 4px; margin-bottom: 4px; }
                        .mt-1 { margin-top: 4px; }
                        .flex { display: flex; }
                        .justify-between { justify-content: space-between; }
                        .truncate { 
                            overflow: hidden;
                            text-overflow: ellipsis;
                            white-space: nowrap;
                        }
                        .text-xs { font-size: 10px; }
                    </style>
                </head>
                <body>
                    ${receiptRef.current.innerHTML}
                </body>
                </html>
            `);

            printWindow.document.close();
            printWindow.focus();

            // Wait for content to load then print
            setTimeout(() => {
                printWindow.print();
                printWindow.close();
                onPrintSuccess?.();
            }, 250);
        } catch (err) {
            console.error('Print error:', err);
            setError(
                err instanceof Error ? err.message : 'Failed to print receipt',
            );
        } finally {
            setIsPrinting(false);
        }
    };

    const handleClose = () => {
        setReceipt(null);
        setError(null);
        onClose();
    };

    return (
        <Dialog open={isOpen} onOpenChange={handleClose}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>Receipt Preview</DialogTitle>
                    <DialogDescription>
                        Preview and print the payment receipt
                    </DialogDescription>
                </DialogHeader>

                <div className="py-4">
                    {isLoading && (
                        <div className="flex items-center justify-center py-8">
                            <Loader2 className="h-8 w-8 animate-spin text-gray-400" />
                        </div>
                    )}

                    {error && (
                        <div className="rounded-md bg-red-50 p-4 text-red-700 dark:bg-red-900/20 dark:text-red-400">
                            {error}
                        </div>
                    )}

                    {receipt && !isLoading && (
                        <div className="flex justify-center overflow-auto rounded-lg border bg-white p-4 dark:border-gray-700">
                            <PrintableReceipt
                                ref={receiptRef}
                                receipt={receipt}
                                formatCurrency={formatCurrency}
                            />
                        </div>
                    )}
                </div>

                <DialogFooter className="gap-2 sm:gap-0">
                    <Button variant="outline" onClick={handleClose}>
                        <X className="mr-2 h-4 w-4" />
                        Close
                    </Button>
                    <Button
                        onClick={handlePrint}
                        disabled={!receipt || isLoading || isPrinting}
                    >
                        {isPrinting ? (
                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                        ) : (
                            <Printer className="mr-2 h-4 w-4" />
                        )}
                        Print Receipt
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default ReceiptPreview;
