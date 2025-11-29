import { forwardRef } from 'react';

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

interface PrintableReceiptProps {
    receipt: ReceiptData;
    formatCurrency: (amount: number) => string;
}

/**
 * PrintableReceipt component styled for 80mm thermal paper.
 * Width: 80mm = ~302px at 96dpi, but we use 72mm printable area = ~272px
 */
export const PrintableReceipt = forwardRef<HTMLDivElement, PrintableReceiptProps>(
    ({ receipt, formatCurrency }, ref) => {
        const charges = receipt.charges || (receipt.charge ? [receipt.charge] : []);
        const totals = receipt.totals || {
            amount: receipt.charge?.amount || 0,
            paid: receipt.charge?.paid_amount || 0,
            insurance_covered: receipt.charge?.insurance_covered_amount || 0,
            patient_copay: receipt.charge?.patient_copay_amount || 0,
        };

        return (
            <div
                ref={ref}
                className="receipt-container bg-white text-black"
                style={{
                    width: '72mm',
                    fontFamily: 'monospace',
                    fontSize: '12px',
                    lineHeight: '1.4',
                    padding: '4mm',
                }}
            >
                {/* Header */}
                <div className="text-center mb-2">
                    <div
                        className="font-bold"
                        style={{ fontSize: '14px' }}
                    >
                        {receipt.hospital.name}
                    </div>
                    {receipt.hospital.address && (
                        <div style={{ fontSize: '10px' }}>
                            {receipt.hospital.address}
                        </div>
                    )}
                    {receipt.hospital.phone && (
                        <div style={{ fontSize: '10px' }}>
                            Tel: {receipt.hospital.phone}
                        </div>
                    )}
                </div>

                {/* Separator */}
                <div className="text-center my-1">
                    {'='.repeat(32)}
                </div>

                {/* Receipt Info */}
                <div className="mb-2">
                    <div>Receipt: {receipt.receipt_number}</div>
                    <div>Date: {receipt.datetime}</div>
                </div>

                {/* Separator */}
                <div className="text-center my-1">
                    {'-'.repeat(32)}
                </div>

                {/* Patient Info */}
                <div className="mb-2">
                    <div>Patient: {receipt.patient.name}</div>
                    <div>Patient #: {receipt.patient.patient_number}</div>
                </div>

                {/* Separator */}
                <div className="text-center my-1">
                    {'-'.repeat(32)}
                </div>

                {/* Charges */}
                <div className="mb-2">
                    <div className="font-bold mb-1">Items:</div>
                    {charges.map((charge, index) => (
                        <div key={charge.id || index} className="mb-1">
                            <div className="truncate" style={{ maxWidth: '100%' }}>
                                {charge.description}
                            </div>
                            <div className="flex justify-between">
                                <span>Amount:</span>
                                <span>{formatCurrency(charge.amount)}</span>
                            </div>
                            {charge.is_insurance_claim && charge.insurance_covered_amount > 0 && (
                                <>
                                    <div className="flex justify-between text-xs">
                                        <span>Insurance:</span>
                                        <span>-{formatCurrency(charge.insurance_covered_amount)}</span>
                                    </div>
                                    <div className="flex justify-between text-xs">
                                        <span>Copay:</span>
                                        <span>{formatCurrency(charge.patient_copay_amount)}</span>
                                    </div>
                                </>
                            )}
                        </div>
                    ))}
                </div>

                {/* Separator */}
                <div className="text-center my-1">
                    {'-'.repeat(32)}
                </div>

                {/* Totals */}
                <div className="mb-2">
                    {charges.length > 1 && (
                        <div className="flex justify-between">
                            <span>Subtotal:</span>
                            <span>{formatCurrency(totals.amount)}</span>
                        </div>
                    )}
                    {totals.insurance_covered > 0 && (
                        <div className="flex justify-between">
                            <span>Insurance:</span>
                            <span>-{formatCurrency(totals.insurance_covered)}</span>
                        </div>
                    )}
                    <div className="flex justify-between font-bold" style={{ fontSize: '14px' }}>
                        <span>Amount Paid:</span>
                        <span>{formatCurrency(totals.paid)}</span>
                    </div>
                </div>

                {/* Separator */}
                <div className="text-center my-1">
                    {'-'.repeat(32)}
                </div>

                {/* Cashier */}
                <div className="mb-2">
                    <div>Cashier: {receipt.cashier.name}</div>
                </div>

                {/* Footer */}
                <div className="text-center my-1">
                    {'-'.repeat(32)}
                </div>
                <div className="text-center" style={{ fontSize: '10px' }}>
                    <div>Thank you!</div>
                    <div>Please keep this receipt</div>
                </div>

                {/* Final Separator */}
                <div className="text-center mt-1">
                    {'='.repeat(32)}
                </div>

                {/* Print Styles */}
                <style>{`
                    @media print {
                        @page {
                            size: 80mm auto;
                            margin: 0;
                        }
                        body {
                            margin: 0;
                            padding: 0;
                        }
                        .receipt-container {
                            width: 72mm !important;
                            margin: 0 auto;
                            padding: 4mm !important;
                        }
                    }
                `}</style>
            </div>
        );
    }
);

PrintableReceipt.displayName = 'PrintableReceipt';

export default PrintableReceipt;
