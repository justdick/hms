import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { Settings } from 'lucide-react';
import { useState } from 'react';
import { BillAdjustmentModal } from './components/BillAdjustmentModal';
import { BillingStatsCards } from './components/BillingStatsCards';
import { BillWaiverModal } from './components/BillWaiverModal';
import { InlinePaymentForm } from './components/InlinePaymentForm';
import { PatientBillingDetails } from './components/PatientBillingDetails';
import { PatientSearchBar } from './components/PatientSearchBar';
import { PatientSearchResults } from './components/PatientSearchResults';
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
    patient: Patient;
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
                    // Refresh search results
                    searchPatients(searchQuery);
                },
                onError: () => {
                    setProcessingPayment(false);
                },
            },
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

                {/* Stats Cards */}
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

                            {/* Inline Payment Form */}
                            {expandedPatients.has(
                                selectedPatient.patient_id,
                            ) && (
                                <InlinePaymentForm
                                    checkinId={
                                        selectedPatient.visits[0]?.checkin_id
                                    }
                                    selectedCharges={selectedCharges}
                                    totalAmount={selectedPatient.visits
                                        .flatMap((v) => v.charges)
                                        .filter((c) =>
                                            selectedCharges.includes(c.id),
                                        )
                                        .reduce(
                                            (sum, c) =>
                                                sum +
                                                (c.is_insurance_claim
                                                    ? c.patient_copay_amount
                                                    : c.amount),
                                            0,
                                        )}
                                    formatCurrency={formatCurrency}
                                    onSuccess={() => {
                                        searchPatients(searchQuery);
                                    }}
                                />
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
            </div>
        </AppLayout>
    );
}
