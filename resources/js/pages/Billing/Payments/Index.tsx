import { PatientCreditBadge } from '@/components/Patient/PatientCreditBadge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { Printer, Settings, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { BillAdjustmentModal } from './components/BillAdjustmentModal';
import { BillingStatsCards } from './components/BillingStatsCards';
import { BillWaiverModal } from './components/BillWaiverModal';
import { ChargeSelectionList, type ChargeItem } from './components/ChargeSelectionList';
import { InlinePaymentForm } from './components/InlinePaymentForm';
import { MyCollectionsCard } from './components/MyCollectionsCard';
import { MyCollectionsModal } from './components/MyCollectionsModal';
import { PatientBillingDetails } from './components/PatientBillingDetails';
import { PatientSearchBar } from './components/PatientSearchBar';
import { PatientSearchResults } from './components/PatientSearchResults';
import { PaymentModal } from './components/PaymentModal';
import { ReceiptPreview } from './components/ReceiptPreview';
import { ServiceAccessOverrideModal } from './components/ServiceAccessOverrideModal';

interface Patient {
    id: number;
    first_name: string;
    last_name: string;
    patient_number: string;
    phone_number: string;
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
    status: string;
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

interface ServiceAccessStatus {
    service_type: string;
    is_blocked: boolean;
    pending_amount: number;
    has_active_override: boolean;
}

interface OverrideHistoryItem {
    id: number;
    type: 'override' | 'waiver' | 'adjustment';
    service_type?: string;
    charge_description?: string;
    reason: string;
    authorized_by: {
        id: number;
        name: string;
    };
    authorized_at: string;
    expires_at?: string;
    is_active?: boolean;
    remaining_duration?: string;
    original_amount?: number;
    adjustment_amount?: number;
    final_amount?: number;
    adjustment_type?: string;
}

interface PatientSearchResult {
    patient_id: number;
    patient: Patient & {
        is_credit_eligible?: boolean;
        credit_reason?: string | null;
        credit_authorized_by?: { name: string } | null;
        credit_authorized_at?: string | null;
        total_owing?: number;
    };
    total_pending: number;
    total_patient_owes: number;
    total_insurance_covered: number;
    total_charges: number;
    visits_with_charges: number;
    visits: Visit[];
    service_access_status?: ServiceAccessStatus[];
    override_history?: OverrideHistoryItem[];
}

interface Stats {
    pending_charges: number;
    pending_amount: number;
    paid_today: number;
    total_outstanding: number;
}

interface BillingStats {
    pending_charges_count: number;
    pending_charges_amount: number;
    todays_revenue: number;
    total_outstanding: number;
    collection_rate: number;
}

interface BillingPermissions {
    canProcessPayment: boolean;
    canWaiveCharges: boolean;
    canAdjustCharges: boolean;
    canOverrideServices: boolean;
    canCancelCharges: boolean;
}

interface Props {
    stats: Stats;
    permissions: BillingPermissions;
}

export default function PaymentIndex({ stats, permissions }: Props) {
    const [searchQuery, setSearchQuery] = useState('');
    const [searchResults, setSearchResults] = useState<PatientSearchResult[]>(
        [],
    );
    const [isSearching, setIsSearching] = useState(false);
    const [selectedPatient, setSelectedPatient] =
        useState<PatientSearchResult | null>(null);
    const [expandedPatients, setExpandedPatients] = useState<Set<number>>(
        new Set(),
    );
    const [selectedCharges, setSelectedCharges] = useState<number[]>([]);
    const [processingPayment, setProcessingPayment] = useState(false);

    // Modal states
    const [waiverModalOpen, setWaiverModalOpen] = useState(false);
    const [adjustmentModalOpen, setAdjustmentModalOpen] = useState(false);
    const [overrideModalOpen, setOverrideModalOpen] = useState(false);
    const [collectionsModalOpen, setCollectionsModalOpen] = useState(false);
    const [paymentModalOpen, setPaymentModalOpen] = useState(false);
    const [receiptPreviewOpen, setReceiptPreviewOpen] = useState(false);
    const [paidChargeIds, setPaidChargeIds] = useState<number[]>([]);
    const [selectedChargeId, setSelectedChargeId] = useState<number | null>(
        null,
    );
    const [selectedServiceType, setSelectedServiceType] = useState<
        string | null
    >(null);

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-GH', {
            style: 'currency',
            currency: 'GHS',
        }).format(amount);
    };

    const searchPatients = async (query: string) => {
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
    };

    const handlePatientSelect = (patient: PatientSearchResult) => {
        setSelectedPatient(patient);
        // Expand the patient details
        setExpandedPatients((prev) => {
            const newSet = new Set(prev);
            newSet.add(patient.patient_id);
            return newSet;
        });
        // Auto-select all charges for this patient
        const allChargeIds = patient.visits.flatMap((visit) =>
            visit.charges.map((charge) => charge.id),
        );
        setSelectedCharges(allChargeIds);
    };

    const togglePatientExpanded = (patientId: number) => {
        setExpandedPatients((prev) => {
            const newSet = new Set(prev);
            if (newSet.has(patientId)) {
                newSet.delete(patientId);
            } else {
                newSet.add(patientId);
            }
            return newSet;
        });
    };

    const handleQuickPayAll = (patient: PatientSearchResult) => {
        // Navigate to most recent visit for quick pay
        const mostRecentVisit = patient.visits[0];
        if (mostRecentVisit) {
            router.post(`/billing/charges/quick-pay-all`, {
                patient_checkin_id: mostRecentVisit.checkin_id,
                payment_method: 'cash',
                charges: patient.visits.flatMap((v) =>
                    v.charges.map((c) => c.id),
                ),
            });
        }
    };

    const handleWaiveCharge = (chargeId: number) => {
        setSelectedChargeId(chargeId);
        setWaiverModalOpen(true);
    };

    const handleAdjustCharge = (chargeId: number) => {
        setSelectedChargeId(chargeId);
        setAdjustmentModalOpen(true);
    };

    const handleOverrideService = (serviceType: string) => {
        setSelectedServiceType(serviceType);
        setOverrideModalOpen(true);
    };

    const handlePaymentSubmit = (paymentData: {
        charges: number[];
        payment_method: string;
        amount_paid: number;
        notes?: string;
    }) => {
        if (!selectedPatient) return;

        setProcessingPayment(true);
        const mostRecentVisit = selectedPatient.visits[0];

        router.post(
            `/billing/checkin/${mostRecentVisit.checkin_id}/payment`,
            paymentData,
            {
                onSuccess: () => {
                    setProcessingPayment(false);
                    // Store paid charge IDs for receipt printing
                    setPaidChargeIds(paymentData.charges);
                    // Show receipt preview option
                    setReceiptPreviewOpen(true);
                    // Refresh search results
                    searchPatients(searchQuery);
                },
                onError: () => {
                    setProcessingPayment(false);
                },
            },
        );
    };

    const handlePaymentSuccess = (chargeIds: number[]) => {
        setPaidChargeIds(chargeIds);
        setPaymentModalOpen(false);
        setReceiptPreviewOpen(true);
        searchPatients(searchQuery);
    };

    const handleChargeSelectionChange = (selectedIds: number[]) => {
        setSelectedCharges(selectedIds);
    };

    // Get all charges from selected patient for ChargeSelectionList
    const getAllChargesFromPatient = (): ChargeItem[] => {
        if (!selectedPatient) return [];
        return selectedPatient.visits.flatMap((visit) =>
            visit.charges.map((charge) => ({
                ...charge,
                status: 'pending',
            })),
        );
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
                            onClick={() =>
                                router.visit('/billing/configuration')
                            }
                        >
                            <Settings className="mr-2 h-4 w-4" />
                            Configuration
                        </Button>
                    </div>
                </div>

                {/* Stats Cards and My Collections */}
                <div className="grid gap-6 lg:grid-cols-4">
                    <div className="lg:col-span-3">
                        <BillingStatsCards
                            stats={{
                                pending_charges_count: stats.pending_charges,
                                pending_charges_amount: stats.pending_amount,
                                todays_revenue: stats.paid_today,
                                total_outstanding: stats.total_outstanding,
                                collection_rate:
                                    stats.total_outstanding > 0
                                        ? (stats.paid_today /
                                              (stats.paid_today +
                                                  stats.total_outstanding)) *
                                          100
                                        : 100,
                            }}
                            formatCurrency={formatCurrency}
                        />
                    </div>
                    <div className="lg:col-span-1">
                        <MyCollectionsCard
                            formatCurrency={formatCurrency}
                            onViewDetails={() => setCollectionsModalOpen(true)}
                        />
                    </div>
                </div>

                {/* Main Content - Single Page Layout */}
                <div className="space-y-6">
                    {/* Patient Search Section */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Search Patient</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <PatientSearchBar
                                onSearch={searchPatients}
                                isSearching={isSearching}
                            />
                            <div className="mt-4">
                                <PatientSearchResults
                                    results={searchResults}
                                    searchQuery={searchQuery}
                                    selectedPatientId={
                                        selectedPatient?.patient_id || null
                                    }
                                    onPatientSelect={handlePatientSelect}
                                    onQuickPayAll={handleQuickPayAll}
                                    formatCurrency={formatCurrency}
                                />
                            </div>
                        </CardContent>
                    </Card>

                    {/* Expandable Patient Details */}
                    {selectedPatient && (
                        <div className="space-y-6">
                            {/* Credit Badge for eligible patients - Requirement 14.3 */}
                            {selectedPatient.patient.is_credit_eligible && (
                                <Card className="border-amber-200 bg-amber-50/50 dark:border-amber-800 dark:bg-amber-950/30">
                                    <CardContent className="flex items-center justify-between p-4">
                                        <div className="flex items-center gap-3">
                                            <ShieldCheck className="h-5 w-5 text-amber-600" />
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <span className="font-medium text-amber-700 dark:text-amber-300">
                                                        Credit Account Patient
                                                    </span>
                                                    <PatientCreditBadge
                                                        isCreditEligible={true}
                                                        totalOwing={selectedPatient.patient.total_owing}
                                                        creditReason={selectedPatient.patient.credit_reason}
                                                        creditAuthorizedBy={selectedPatient.patient.credit_authorized_by?.name}
                                                        creditAuthorizedAt={selectedPatient.patient.credit_authorized_at}
                                                        showTooltip={true}
                                                    />
                                                </div>
                                                <p className="text-sm text-amber-600 dark:text-amber-400">
                                                    Services can proceed without immediate payment
                                                </p>
                                            </div>
                                        </div>
                                        {selectedPatient.patient.total_owing && selectedPatient.patient.total_owing > 0 && (
                                            <div className="text-right">
                                                <p className="text-xs text-muted-foreground">Total Owing</p>
                                                <p className="text-lg font-bold text-orange-600">
                                                    {formatCurrency(selectedPatient.patient.total_owing)}
                                                </p>
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>
                            )}

                            <PatientBillingDetails
                                patient={selectedPatient}
                                isExpanded={expandedPatients.has(
                                    selectedPatient.patient_id,
                                )}
                                onToggle={() =>
                                    togglePatientExpanded(
                                        selectedPatient.patient_id,
                                    )
                                }
                                permissions={permissions}
                                formatCurrency={formatCurrency}
                                onWaiveCharge={
                                    permissions.canWaiveCharges
                                        ? handleWaiveCharge
                                        : undefined
                                }
                                onAdjustCharge={
                                    permissions.canAdjustCharges
                                        ? handleAdjustCharge
                                        : undefined
                                }
                                onOverrideService={
                                    permissions.canOverrideServices
                                        ? handleOverrideService
                                        : undefined
                                }
                            />

                            {/* Charge Selection and Payment Section */}
                            {expandedPatients.has(
                                selectedPatient.patient_id,
                            ) && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-base">Select Charges to Pay</CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-6">
                                        {/* Charge Selection List - Requirements 1.1, 1.2, 1.5 */}
                                        <ChargeSelectionList
                                            charges={getAllChargesFromPatient()}
                                            selectedChargeIds={selectedCharges}
                                            onSelectionChange={handleChargeSelectionChange}
                                            formatCurrency={formatCurrency}
                                            showSummary={true}
                                        />

                                        {/* Payment Actions */}
                                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                            <Button
                                                variant="default"
                                                size="lg"
                                                onClick={() => setPaymentModalOpen(true)}
                                                disabled={selectedCharges.length === 0}
                                                className="w-full sm:w-auto"
                                            >
                                                Process Payment ({selectedCharges.length} charges)
                                            </Button>
                                            
                                            {paidChargeIds.length > 0 && (
                                                <Button
                                                    variant="outline"
                                                    onClick={() => setReceiptPreviewOpen(true)}
                                                >
                                                    <Printer className="mr-2 h-4 w-4" />
                                                    Print Last Receipt
                                                </Button>
                                            )}
                                        </div>
                                    </CardContent>
                                </Card>
                            )}
                        </div>
                    )}
                </div>

                {/* Modals */}
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
                                        onClose={() =>
                                            setWaiverModalOpen(false)
                                        }
                                        charge={charge}
                                        formatCurrency={formatCurrency}
                                        onSuccess={() => {
                                            setWaiverModalOpen(false);
                                            searchPatients(searchQuery);
                                        }}
                                    />

                                    <BillAdjustmentModal
                                        isOpen={adjustmentModalOpen}
                                        onClose={() =>
                                            setAdjustmentModalOpen(false)
                                        }
                                        charge={charge}
                                        formatCurrency={formatCurrency}
                                        onSuccess={() => {
                                            setAdjustmentModalOpen(false);
                                            searchPatients(searchQuery);
                                        }}
                                    />
                                </>
                            ) : null;
                        })()}
                    </>
                )}

                {selectedServiceType &&
                    selectedPatient &&
                    selectedPatient.visits[0] && (
                        <ServiceAccessOverrideModal
                            isOpen={overrideModalOpen}
                            onClose={() => setOverrideModalOpen(false)}
                            serviceType={selectedServiceType}
                            checkinId={selectedPatient.visits[0].checkin_id}
                            pendingCharges={selectedPatient.visits.flatMap(
                                (v) =>
                                    v.charges.filter(
                                        (c) =>
                                            c.service_type ===
                                            selectedServiceType,
                                    ),
                            )}
                            formatCurrency={formatCurrency}
                        />
                    )}

                {/* My Collections Modal */}
                <MyCollectionsModal
                    isOpen={collectionsModalOpen}
                    onClose={() => setCollectionsModalOpen(false)}
                    formatCurrency={formatCurrency}
                />

                {/* Payment Modal - Requirement 11.7 */}
                {selectedPatient && selectedPatient.visits[0] && (
                    <PaymentModal
                        isOpen={paymentModalOpen}
                        onClose={() => setPaymentModalOpen(false)}
                        checkinId={selectedPatient.visits[0].checkin_id}
                        charges={getAllChargesFromPatient().filter((c) =>
                            selectedCharges.includes(c.id),
                        )}
                        patientName={`${selectedPatient.patient.first_name} ${selectedPatient.patient.last_name}`}
                        patientNumber={selectedPatient.patient.patient_number}
                        formatCurrency={formatCurrency}
                        onSuccess={handlePaymentSuccess}
                    />
                )}

                {/* Receipt Preview - Requirements 3.1, 3.2 */}
                <ReceiptPreview
                    isOpen={receiptPreviewOpen}
                    onClose={() => setReceiptPreviewOpen(false)}
                    chargeIds={paidChargeIds}
                    formatCurrency={formatCurrency}
                    onPrintSuccess={() => {
                        // Optionally close after print
                    }}
                />
            </div>
        </AppLayout>
    );
}
