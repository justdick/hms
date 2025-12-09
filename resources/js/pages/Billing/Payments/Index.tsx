import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { Settings } from 'lucide-react';
import { useCallback, useState } from 'react';
import { BillAdjustmentModal } from './components/BillAdjustmentModal';
import { BillWaiverModal } from './components/BillWaiverModal';
import { MyCollectionsCard } from './components/MyCollectionsCard';
import { MyCollectionsModal } from './components/MyCollectionsModal';
import { PatientBillingModal } from './components/PatientBillingModal';
import { PatientSearchBar } from './components/PatientSearchBar';
import { PatientSearchResults } from './components/PatientSearchResults';
import { ReceiptPreview } from './components/ReceiptPreview';

interface Patient {
    id: number;
    first_name: string;
    last_name: string;
    patient_number: string;
    phone_number: string;
    is_credit_eligible?: boolean;
    credit_reason?: string | null;
    credit_authorized_by?: { name: string } | null;
    credit_authorized_at?: string | null;
    total_owing?: number;
}

interface Department {
    id: number;
    name: string;
}

interface ChargeItem {
    id: number;
    description: string;
    amount: number;
    is_insurance_claim: boolean;
    insurance_covered_amount: number;
    patient_copay_amount: number;
    service_type: string;
    charged_at: string;
}

interface Visit {
    checkin_id: number;
    department: Department;
    checked_in_at: string;
    total_pending: number;
    patient_copay: number;
    insurance_covered: number;
    charges_count: number;
    charges: ChargeItem[];
}

interface PatientSearchResult {
    patient_id: number;
    patient: Patient;
    total_pending: number;
    total_patient_owes: number;
    total_insurance_covered: number;
    total_charges: number;
    visits_with_charges: number;
    visits: Visit[];
}

interface BillingPermissions {
    canProcessPayment: boolean;
    canWaiveCharges: boolean;
    canAdjustCharges: boolean;
    canOverrideServices: boolean;
    canCancelCharges: boolean;
}

interface Props {
    permissions: BillingPermissions;
}

export default function PaymentIndex({ permissions }: Props) {
    const [searchQuery, setSearchQuery] = useState('');
    const [searchResults, setSearchResults] = useState<PatientSearchResult[]>([]);
    const [isSearching, setIsSearching] = useState(false);
    const [selectedPatient, setSelectedPatient] = useState<PatientSearchResult | null>(null);

    // Modal states
    const [billingModalOpen, setBillingModalOpen] = useState(false);
    const [waiverModalOpen, setWaiverModalOpen] = useState(false);
    const [adjustmentModalOpen, setAdjustmentModalOpen] = useState(false);
    const [collectionsModalOpen, setCollectionsModalOpen] = useState(false);
    const [receiptPreviewOpen, setReceiptPreviewOpen] = useState(false);
    const [paidChargeIds, setPaidChargeIds] = useState<number[]>([]);
    const [selectedChargeId, setSelectedChargeId] = useState<number | null>(null);

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-GH', {
            style: 'currency',
            currency: 'GHS',
        }).format(amount);
    };

    const searchPatients = useCallback(async (query: string) => {
        setSearchQuery(query);

        if (query.length < 2) {
            setSearchResults([]);
            return;
        }

        setIsSearching(true);
        try {
            const response = await fetch(
                `/billing/patients/search?search=${encodeURIComponent(query)}`,
            );
            const result = await response.json();
            setSearchResults(result.patients || []);
        } catch (error) {
            console.error('Search error:', error);
            setSearchResults([]);
        } finally {
            setIsSearching(false);
        }
    }, []);

    const handlePatientSelect = (patient: PatientSearchResult) => {
        setSelectedPatient(patient);
        setBillingModalOpen(true);
    };

    const handleQuickPayAll = (patient: PatientSearchResult) => {
        const mostRecentVisit = patient.visits[0];
        if (mostRecentVisit) {
            router.post(`/billing/charges/quick-pay-all`, {
                patient_checkin_id: mostRecentVisit.checkin_id,
                payment_method: 'cash',
                charges: patient.visits.flatMap((v) => v.charges.map((c) => c.id)),
            });
        }
    };

    const handleWaiveCharge = (chargeId: number) => {
        setSelectedChargeId(chargeId);
        setBillingModalOpen(false);
        setWaiverModalOpen(true);
    };

    const handleAdjustCharge = (chargeId: number) => {
        setSelectedChargeId(chargeId);
        setBillingModalOpen(false);
        setAdjustmentModalOpen(true);
    };

    const handlePaymentSuccess = (chargeIds: number[]) => {
        setPaidChargeIds(chargeIds);
        searchPatients(searchQuery);
    };

    const handlePrintReceipt = async (chargeIds: number[]) => {
        setPaidChargeIds(chargeIds);
        // Fetch receipt data and trigger print
        try {
            const response = await fetch('/billing/receipt', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({ charge_ids: chargeIds }),
            });
            const data = await response.json();
            if (data.receipt) {
                // Create a printable window
                const printWindow = window.open('', '_blank');
                if (printWindow) {
                    printWindow.document.write(`
                        <html>
                        <head><title>Receipt</title>
                        <style>
                            body { font-family: monospace; padding: 20px; max-width: 300px; margin: 0 auto; }
                            .header { text-align: center; margin-bottom: 20px; }
                            .amount { font-size: 24px; font-weight: bold; text-align: center; margin: 20px 0; }
                            .details { font-size: 12px; }
                            .footer { text-align: center; margin-top: 20px; font-size: 10px; }
                        </style>
                        </head>
                        <body>
                            <div class="header">
                                <strong>${data.receipt.facility_name || 'Hospital'}</strong><br/>
                                Receipt #${data.receipt.receipt_number}
                            </div>
                            <div class="details">
                                Patient: ${data.receipt.patient_name}<br/>
                                Date: ${new Date().toLocaleDateString()}
                            </div>
                            <div class="amount">${formatCurrency(data.receipt.total_amount)}</div>
                            <div class="details">
                                Payment: ${data.receipt.payment_method}<br/>
                                Cashier: ${data.receipt.cashier_name}
                            </div>
                            <div class="footer">Thank you!</div>
                        </body>
                        </html>
                    `);
                    printWindow.document.close();
                    printWindow.print();
                }
            }
        } catch (error) {
            console.error('Print error:', error);
        }
        setBillingModalOpen(false);
    };

    const handleViewReceipt = (chargeIds: number[]) => {
        setPaidChargeIds(chargeIds);
        setBillingModalOpen(false);
        setReceiptPreviewOpen(true);
    };

    const handleWaiverSuccess = () => {
        setWaiverModalOpen(false);
        searchPatients(searchQuery);
        // Reopen billing modal if patient still selected
        if (selectedPatient) {
            setBillingModalOpen(true);
        }
    };

    const handleAdjustmentSuccess = () => {
        setAdjustmentModalOpen(false);
        searchPatients(searchQuery);
        // Reopen billing modal if patient still selected
        if (selectedPatient) {
            setBillingModalOpen(true);
        }
    };


    return (
        <AppLayout breadcrumbs={[{ title: 'Billing', href: '/billing' }]}>
            <Head title="Billing - Payments & Collections" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                            Billing & Payments
                        </h1>
                        <p className="text-gray-600 dark:text-gray-400">
                            Search for patients and manage payments
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <Button
                            variant="outline"
                            onClick={() => router.visit('/billing/configuration')}
                        >
                            <Settings className="mr-2 h-4 w-4" />
                            Configuration
                        </Button>
                    </div>
                </div>

                {/* My Collections Card */}
                <MyCollectionsCard
                    formatCurrency={formatCurrency}
                    onViewDetails={() => setCollectionsModalOpen(true)}
                />

                {/* Patient Search Section */}
                <Card>
                    <CardHeader>
                        <CardTitle>Search Patient</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <PatientSearchBar onSearch={searchPatients} isSearching={isSearching} />
                        <div className="mt-4">
                            <PatientSearchResults
                                results={searchResults}
                                searchQuery={searchQuery}
                                selectedPatientId={selectedPatient?.patient_id || null}
                                onPatientSelect={handlePatientSelect}
                                formatCurrency={formatCurrency}
                            />
                        </div>
                    </CardContent>
                </Card>

                {/* Patient Billing Modal */}
                <PatientBillingModal
                    isOpen={billingModalOpen}
                    onClose={() => setBillingModalOpen(false)}
                    patient={selectedPatient}
                    permissions={permissions}
                    formatCurrency={formatCurrency}
                    onWaiveCharge={permissions.canWaiveCharges ? handleWaiveCharge : undefined}
                    onAdjustCharge={permissions.canAdjustCharges ? handleAdjustCharge : undefined}
                    onPaymentSuccess={handlePaymentSuccess}
                    onPrintReceipt={handlePrintReceipt}
                    onViewReceipt={handleViewReceipt}
                />

                {/* Waiver Modal */}
                {selectedChargeId && selectedPatient && (
                    <>
                        {(() => {
                            const charge = selectedPatient.visits
                                .flatMap((v) => v.charges)
                                .find((c) => c.id === selectedChargeId);
                            return charge ? (
                                <>
                                    <BillWaiverModal
                                        isOpen={waiverModalOpen}
                                        onClose={() => {
                                            setWaiverModalOpen(false);
                                            setBillingModalOpen(true);
                                        }}
                                        charge={charge}
                                        formatCurrency={formatCurrency}
                                        onSuccess={handleWaiverSuccess}
                                    />
                                    <BillAdjustmentModal
                                        isOpen={adjustmentModalOpen}
                                        onClose={() => {
                                            setAdjustmentModalOpen(false);
                                            setBillingModalOpen(true);
                                        }}
                                        charge={charge}
                                        formatCurrency={formatCurrency}
                                        onSuccess={handleAdjustmentSuccess}
                                    />
                                </>
                            ) : null;
                        })()}
                    </>
                )}

                {/* My Collections Modal */}
                <MyCollectionsModal
                    isOpen={collectionsModalOpen}
                    onClose={() => setCollectionsModalOpen(false)}
                    formatCurrency={formatCurrency}
                />

                {/* Receipt Preview */}
                <ReceiptPreview
                    isOpen={receiptPreviewOpen}
                    onClose={() => setReceiptPreviewOpen(false)}
                    chargeIds={paidChargeIds}
                    formatCurrency={formatCurrency}
                    onPrintSuccess={() => {}}
                />
            </div>
        </AppLayout>
    );
}
