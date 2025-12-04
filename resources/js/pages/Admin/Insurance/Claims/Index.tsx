import { VettingModal } from '@/components/Insurance';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import {
    ClipboardList,
    Eye,
    FileCheck,
    FileText,
    Filter,
    Search,
    X,
} from 'lucide-react';
import { FormEvent, useCallback, useEffect, useState } from 'react';

interface InsuranceProvider {
    id: number;
    name: string;
    code: string;
}

interface PatientInsurance {
    id: number;
    membership_id: string;
    plan: {
        id: number;
        plan_name: string;
        provider: InsuranceProvider;
    };
}

interface InsuranceClaim {
    id: number;
    claim_check_code: string;
    folder_id?: string;
    patient_full_name: string;
    membership_id: string;
    date_of_attendance: string;
    type_of_service: 'inpatient' | 'outpatient';
    type_of_attendance: 'emergency' | 'acute' | 'routine';
    total_claim_amount: string;
    approved_amount: string;
    status:
        | 'pending_vetting'
        | 'vetted'
        | 'submitted'
        | 'approved'
        | 'rejected'
        | 'paid'
        | 'partial';
    patient_insurance?: PatientInsurance;
    vetted_by_user?: {
        id: number;
        name: string;
    };
    submitted_by_user?: {
        id: number;
        name: string;
    };
    created_at: string;
}

interface Filters {
    [key: string]: string | undefined;
    status?: string;
    provider_id?: string;
    date_from?: string;
    date_to?: string;
    search?: string;
}

interface Props {
    claims: {
        data: InsuranceClaim[];
        links: any;
        meta: any;
    };
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

const statusConfig = {
    pending_vetting: { label: 'Pending Vetting', color: 'bg-yellow-500' },
    vetted: { label: 'Vetted', color: 'bg-blue-500' },
    submitted: { label: 'Submitted', color: 'bg-purple-500' },
    approved: { label: 'Approved', color: 'bg-green-500' },
    rejected: { label: 'Rejected', color: 'bg-red-500' },
    paid: { label: 'Paid', color: 'bg-emerald-600' },
    partial: { label: 'Partial Payment', color: 'bg-orange-500' },
};

export default function InsuranceClaimsIndex({
    claims,
    providers,
    filters,
    stats,
}: Props) {
    const [showFilters, setShowFilters] = useState(false);
    const [localFilters, setLocalFilters] = useState<Filters>(filters);

    // Vetting modal state management
    const [vettingModalOpen, setVettingModalOpen] = useState(false);
    const [selectedClaimId, setSelectedClaimId] = useState<number | null>(null);
    const [modalMode, setModalMode] = useState<'vet' | 'view'>('vet');

    useEffect(() => {
        setLocalFilters(filters);
    }, [filters]);

    const handleFilterChange = (key: keyof Filters, value: string) => {
        setLocalFilters((prev) => ({
            ...prev,
            [key]: value === 'all' || !value ? undefined : value,
        }));
    };

    const handleApplyFilters = (e: FormEvent) => {
        e.preventDefault();
        router.get('/admin/insurance/claims', localFilters, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleClearFilters = () => {
        setLocalFilters({});
        router.get(
            '/admin/insurance/claims',
            {},
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const hasActiveFilters = Object.keys(filters).some(
        (key) => filters[key as keyof Filters],
    );

    const formatCurrency = (amount: string) => {
        return new Intl.NumberFormat('en-GH', {
            style: 'currency',
            currency: 'GHS',
        }).format(parseFloat(amount));
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        });
    };

    /**
     * Opens the vetting modal for a specific claim
     * Fetches vetting data on click as per Requirements 8.1
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
     * Closes the vetting modal and resets selected claim
     */
    const handleCloseVettingModal = useCallback(() => {
        setVettingModalOpen(false);
        setSelectedClaimId(null);
        setModalMode('vet');
    }, []);

    /**
     * Handles successful vetting action
     * Refreshes claims list after approval as per Requirements 13.3
     */
    const handleVetSuccess = useCallback(() => {
        router.reload({ only: ['claims', 'stats'] });
    }, []);

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
                    <div className="flex items-center gap-2">
                        {hasActiveFilters && (
                            <Badge variant="secondary">
                                {Object.keys(filters).length} filter
                                {Object.keys(filters).length > 1
                                    ? 's'
                                    : ''}{' '}
                                active
                            </Badge>
                        )}
                        <Button
                            variant="outline"
                            onClick={() => setShowFilters(!showFilters)}
                        >
                            <Filter className="mr-2 h-4 w-4" />
                            {showFilters ? 'Hide Filters' : 'Show Filters'}
                        </Button>
                    </div>
                </div>

                {/* Stats Overview */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Total Claims
                                    </p>
                                    <p className="text-3xl font-bold text-gray-900 dark:text-gray-100">
                                        {stats.total}
                                    </p>
                                </div>
                                <FileText className="h-8 w-8 text-blue-600" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Pending Vetting
                                    </p>
                                    <p className="text-3xl font-bold text-yellow-600">
                                        {stats.pending_vetting}
                                    </p>
                                </div>
                                <ClipboardList className="h-8 w-8 text-yellow-600" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Vetted
                                    </p>
                                    <p className="text-3xl font-bold text-blue-600">
                                        {stats.vetted}
                                    </p>
                                </div>
                                <FileCheck className="h-8 w-8 text-blue-600" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Submitted
                                    </p>
                                    <p className="text-3xl font-bold text-purple-600">
                                        {stats.submitted}
                                    </p>
                                </div>
                                <FileCheck className="h-8 w-8 text-purple-600" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters Panel */}
                {showFilters && (
                    <Card>
                        <CardContent className="p-6">
                            <form
                                onSubmit={handleApplyFilters}
                                className="space-y-4"
                            >
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    {/* Search */}
                                    <div className="space-y-2">
                                        <Label htmlFor="search">Search</Label>
                                        <div className="relative">
                                            <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-500" />
                                            <Input
                                                id="search"
                                                placeholder="Claim code, patient..."
                                                value={
                                                    localFilters.search || ''
                                                }
                                                onChange={(e) =>
                                                    handleFilterChange(
                                                        'search',
                                                        e.target.value,
                                                    )
                                                }
                                                className="pl-9"
                                            />
                                        </div>
                                    </div>

                                    {/* Status Filter */}
                                    <div className="space-y-2">
                                        <Label htmlFor="status">Status</Label>
                                        <Select
                                            value={localFilters.status || 'all'}
                                            onValueChange={(value) =>
                                                handleFilterChange(
                                                    'status',
                                                    value,
                                                )
                                            }
                                        >
                                            <SelectTrigger id="status">
                                                <SelectValue placeholder="All statuses" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">
                                                    All statuses
                                                </SelectItem>
                                                <SelectItem value="pending_vetting">
                                                    Pending Vetting
                                                </SelectItem>
                                                <SelectItem value="vetted">
                                                    Vetted
                                                </SelectItem>
                                                <SelectItem value="submitted">
                                                    Submitted
                                                </SelectItem>
                                                <SelectItem value="approved">
                                                    Approved
                                                </SelectItem>
                                                <SelectItem value="rejected">
                                                    Rejected
                                                </SelectItem>
                                                <SelectItem value="paid">
                                                    Paid
                                                </SelectItem>
                                                <SelectItem value="partial">
                                                    Partial Payment
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>

                                <div className="flex items-center gap-2">
                                    <Button type="submit">Apply Filters</Button>
                                    {hasActiveFilters && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={handleClearFilters}
                                        >
                                            <X className="mr-2 h-4 w-4" />
                                            Clear All Filters
                                        </Button>
                                    )}
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {/* Claims Table */}
                <Card>
                    <CardContent className="p-0">
                        {claims.data.length > 0 ? (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Claim Code</TableHead>
                                            <TableHead>Patient</TableHead>
                                            <TableHead>Insurance</TableHead>
                                            <TableHead>
                                                Date of Attendance
                                            </TableHead>
                                            <TableHead>Service Type</TableHead>
                                            <TableHead className="text-right">
                                                Claim Amount
                                            </TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead className="text-right">
                                                Actions
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {claims.data.map((claim) => (
                                            <TableRow key={claim.id}>
                                                <TableCell className="font-medium">
                                                    {claim.claim_check_code}
                                                </TableCell>
                                                <TableCell>
                                                    <div>
                                                        <div className="font-medium">
                                                            {
                                                                claim.patient_full_name
                                                            }
                                                        </div>
                                                        <div className="text-sm text-gray-600 dark:text-gray-400">
                                                            {
                                                                claim.membership_id
                                                            }
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <div>
                                                        <div className="font-medium">
                                                            {claim
                                                                .patient_insurance
                                                                ?.plan?.provider
                                                                .name || 'N/A'}
                                                        </div>
                                                        <div className="text-sm text-gray-600 dark:text-gray-400">
                                                            {claim
                                                                .patient_insurance
                                                                ?.plan
                                                                ?.plan_name ||
                                                                ''}
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    {formatDate(
                                                        claim.date_of_attendance,
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant="outline">
                                                        {claim.type_of_service}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-right font-medium">
                                                    {formatCurrency(
                                                        claim.total_claim_amount,
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge
                                                        className={
                                                            statusConfig[
                                                                claim.status
                                                            ].color
                                                        }
                                                    >
                                                        {
                                                            statusConfig[
                                                                claim.status
                                                            ].label
                                                        }
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <div className="flex items-center justify-end gap-2">
                                                        {claim.status ===
                                                            'pending_vetting' && (
                                                            <Button
                                                                variant="default"
                                                                size="sm"
                                                                onClick={() =>
                                                                    handleVetClaim(
                                                                        claim.id,
                                                                    )
                                                                }
                                                                aria-label={`Vet claim ${claim.claim_check_code}`}
                                                            >
                                                                <FileCheck className="mr-1 h-4 w-4" />
                                                                Vet Claim
                                                            </Button>
                                                        )}
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                handleViewClaim(
                                                                    claim.id,
                                                                )
                                                            }
                                                            aria-label={`View claim ${claim.claim_check_code}`}
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        ) : (
                            <div className="p-12 text-center">
                                <ClipboardList className="mx-auto mb-4 h-16 w-16 text-gray-300 dark:text-gray-600" />
                                <h3 className="mb-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    No claims found
                                </h3>
                                <p className="text-gray-600 dark:text-gray-400">
                                    {hasActiveFilters
                                        ? 'Try adjusting your filters'
                                        : 'Insurance claims will appear here'}
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Pagination */}
                {claims.data.length > 0 &&
                    claims.links &&
                    Array.isArray(claims.links) && (
                        <div className="flex items-center justify-between">
                            <div className="text-sm text-gray-600 dark:text-gray-400">
                                Showing {claims.meta?.from} to {claims.meta?.to}{' '}
                                of {claims.meta?.total} claims
                            </div>
                            <div className="flex gap-2">
                                {claims.links.map(
                                    (link: any, index: number) => {
                                        if (link.url === null) return null;

                                        return (
                                            <Button
                                                key={index}
                                                variant={
                                                    link.active
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                size="sm"
                                                onClick={() =>
                                                    router.get(
                                                        link.url,
                                                        localFilters,
                                                        {
                                                            preserveState: true,
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                                dangerouslySetInnerHTML={{
                                                    __html: link.label,
                                                }}
                                            />
                                        );
                                    },
                                )}
                            </div>
                        </div>
                    )}
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
