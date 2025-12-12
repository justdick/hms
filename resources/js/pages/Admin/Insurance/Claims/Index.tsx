import { VettingModal } from '@/components/Insurance';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { StatCard } from '@/components/ui/stat-card';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { ClipboardList, FileCheck, FileText, Send } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { ClaimsDataTable } from './claims-data-table';
import {
    createClaimsColumns,
    InsuranceClaim,
    InsuranceProvider,
} from './claims-columns';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginationMeta {
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
}

interface PaginatedClaims {
    data: InsuranceClaim[];
    links: PaginationLink[];
    meta: PaginationMeta;
}

interface Filters {
    search?: string;
    status?: string;
    provider_id?: string;
    date_from?: string;
    date_to?: string;
}

interface Props {
    claims: PaginatedClaims;
    providers: {
        data: InsuranceProvider[];
    };
    filters: Filters;
    stats: {
        total: number;
        pending_vetting: number;
        vetted: number;
        submitted: number;
    };
}

export default function InsuranceClaimsIndex({
    claims,
    providers,
    filters,
    stats,
}: Props) {
    // Vetting modal state management
    const [vettingModalOpen, setVettingModalOpen] = useState(false);
    const [selectedClaimId, setSelectedClaimId] = useState<number | null>(null);
    const [modalMode, setModalMode] = useState<'vet' | 'view' | 'edit'>('vet');

    /**
     * Opens the vetting modal for a specific claim
     */
    const handleVetClaim = useCallback((claimId: number) => {
        setSelectedClaimId(claimId);
        setModalMode('vet');
        setVettingModalOpen(true);
    }, []);

    /**
     * Opens the modal in view-only mode for a specific claim
     */
    const handleViewClaim = useCallback((claimId: number) => {
        setSelectedClaimId(claimId);
        setModalMode('view');
        setVettingModalOpen(true);
    }, []);

    /**
     * Opens the modal in edit mode for vetted claims
     */
    const handleEditClaim = useCallback((claimId: number) => {
        setSelectedClaimId(claimId);
        setModalMode('edit');
        setVettingModalOpen(true);
    }, []);

    /**
     * Closes the vetting modal and resets selected claim
     */
    const handleCloseVettingModal = useCallback(() => {
        setVettingModalOpen(false);
        setSelectedClaimId(null);
        setModalMode('vet');
    }, []);

    /**
     * Handles successful vetting action
     */
    const handleVetSuccess = useCallback(() => {
        router.reload({ only: ['claims', 'stats'] });
    }, []);

    // Memoize columns to prevent unnecessary re-renders
    const columns = useMemo(
        () => createClaimsColumns(handleVetClaim, handleViewClaim, handleEditClaim),
        [handleVetClaim, handleViewClaim, handleEditClaim],
    );

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                { title: 'Insurance', href: '/admin/insurance' },
                { title: 'Claims', href: '' },
            ]}
        >
            <Head title="Insurance Claims" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="flex items-center gap-2 text-3xl font-bold text-gray-900 dark:text-gray-100">
                            <ClipboardList className="h-8 w-8" />
                            Insurance Claims
                        </h1>
                        <p className="mt-2 text-gray-600 dark:text-gray-400">
                            Review and vet insurance claims for submission
                        </p>
                    </div>
                </div>

                {/* Stats Overview */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <StatCard
                        label="Total Claims"
                        value={stats.total}
                        icon={<FileText className="h-4 w-4" />}
                        variant="info"
                    />
                    <StatCard
                        label="Pending Vetting"
                        value={stats.pending_vetting}
                        icon={<ClipboardList className="h-4 w-4" />}
                        variant="warning"
                    />
                    <StatCard
                        label="Vetted"
                        value={stats.vetted}
                        icon={<FileCheck className="h-4 w-4" />}
                    />
                    <StatCard
                        label="Submitted"
                        value={stats.submitted}
                        icon={<Send className="h-4 w-4" />}
                        variant="success"
                    />
                </div>

                {/* Claims DataTable */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <ClipboardList className="h-5 w-5" />
                            Claims ({claims.meta?.total || 0})
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ClaimsDataTable
                            columns={columns}
                            data={claims.data}
                            pagination={claims}
                            filters={filters}
                        />
                    </CardContent>
                </Card>
            </div>

            {/* Vetting/View Modal for Claims */}
            <VettingModal
                claimId={selectedClaimId}
                isOpen={vettingModalOpen}
                onClose={handleCloseVettingModal}
                onVetSuccess={handleVetSuccess}
                mode={modalMode}
            />
        </AppLayout>
    );
}
