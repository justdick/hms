'use client';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ColumnDef } from '@tanstack/react-table';
import {
    ArrowUpDown,
    Calendar,
    Eye,
    FileCheck,
    Pencil,
    Trash2,
} from 'lucide-react';

export interface InsuranceProvider {
    id: number;
    name: string;
    code: string;
}

export interface PatientInsurance {
    id: number;
    membership_id: string;
    plan: {
        id: number;
        plan_name: string;
        provider: InsuranceProvider;
    };
}

export interface InsuranceClaim {
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

const statusConfig: Record<string, { label: string; className: string }> = {
    pending_vetting: {
        label: 'Pending Vetting',
        className: 'bg-yellow-500 hover:bg-yellow-500',
    },
    vetted: { label: 'Vetted', className: 'bg-blue-500 hover:bg-blue-500' },
    submitted: {
        label: 'Submitted',
        className: 'bg-purple-500 hover:bg-purple-500',
    },
    approved: {
        label: 'Approved',
        className: 'bg-green-500 hover:bg-green-500',
    },
    rejected: { label: 'Rejected', className: 'bg-red-500 hover:bg-red-500' },
    paid: { label: 'Paid', className: 'bg-emerald-600 hover:bg-emerald-600' },
    partial: {
        label: 'Partial Payment',
        className: 'bg-orange-500 hover:bg-orange-500',
    },
};

const getStatusConfig = (status: string) => {
    return (
        statusConfig[status] || {
            label: status,
            className: 'bg-gray-400 hover:bg-gray-400',
        }
    );
};

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

export const createClaimsColumns = (
    onVetClaim: (claimId: number) => void,
    onViewClaim: (claimId: number) => void,
    onEditClaim: (claimId: number) => void,
    onDeleteClaim: (claimId: number) => void,
): ColumnDef<InsuranceClaim>[] => [
    {
        accessorKey: 'claim_check_code',
        id: 'claim_check_code',
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                >
                    Claim Code
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            return (
                <div className="font-mono text-sm font-medium">
                    {row.original.claim_check_code}
                </div>
            );
        },
    },
    {
        accessorKey: 'patient_full_name',
        id: 'patient_full_name',
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                >
                    Patient
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            const claim = row.original;
            return (
                <div className="space-y-1">
                    <div className="font-medium">{claim.patient_full_name}</div>
                    <div className="text-sm text-muted-foreground">
                        {claim.membership_id}
                    </div>
                </div>
            );
        },
    },
    {
        accessorKey: 'folder_id',
        id: 'folder_id',
        header: 'Folder No.',
        cell: ({ row }) => {
            return (
                <div className="font-mono text-sm">
                    {row.original.folder_id || 'N/A'}
                </div>
            );
        },
    },
    {
        accessorKey: 'date_of_attendance',
        id: 'date_of_attendance',
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                >
                    Date of Attendance
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            return (
                <div className="flex items-center gap-1 text-sm">
                    <Calendar className="h-3 w-3 text-muted-foreground" />
                    {formatDate(row.original.date_of_attendance)}
                </div>
            );
        },
    },
    {
        accessorKey: 'type_of_service',
        id: 'type_of_service',
        header: 'Service Type',
        cell: ({ row }) => {
            return (
                <Badge variant="outline" className="capitalize">
                    {row.original.type_of_service}
                </Badge>
            );
        },
        filterFn: (row, id, value) => {
            return value.includes(row.getValue(id));
        },
    },
    {
        accessorKey: 'total_claim_amount',
        id: 'total_claim_amount',
        header: ({ column }) => {
            return (
                <Button
                    variant="ghost"
                    className="w-full justify-end"
                    onClick={() =>
                        column.toggleSorting(column.getIsSorted() === 'asc')
                    }
                >
                    Claim Amount
                    <ArrowUpDown className="ml-2 h-4 w-4" />
                </Button>
            );
        },
        cell: ({ row }) => {
            return (
                <div className="text-right font-medium">
                    {formatCurrency(row.original.total_claim_amount)}
                </div>
            );
        },
    },
    {
        accessorKey: 'status',
        id: 'status',
        header: 'Status',
        cell: ({ row }) => {
            const config = getStatusConfig(row.original.status);
            return <Badge className={config.className}>{config.label}</Badge>;
        },
        filterFn: (row, id, value) => {
            return value.includes(row.getValue(id));
        },
    },
    {
        id: 'actions',
        enableHiding: false,
        cell: ({ row }) => {
            const claim = row.original;

            return (
                <div className="flex items-center justify-end gap-2">
                    {claim.status === 'pending_vetting' && (
                        <Button
                            variant="default"
                            size="sm"
                            onClick={() => onVetClaim(claim.id)}
                            aria-label={`Vet claim ${claim.claim_check_code}`}
                        >
                            <FileCheck className="mr-1 h-4 w-4" />
                            Vet
                        </Button>
                    )}
                    {claim.status === 'vetted' && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => onEditClaim(claim.id)}
                            aria-label={`Edit claim ${claim.claim_check_code}`}
                        >
                            <Pencil className="mr-1 h-4 w-4" />
                            Edit
                        </Button>
                    )}
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => onViewClaim(claim.id)}
                        aria-label={`View claim ${claim.claim_check_code}`}
                    >
                        <Eye className="h-4 w-4" />
                    </Button>
                    {claim.status === 'pending_vetting' && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => onDeleteClaim(claim.id)}
                            aria-label={`Delete claim ${claim.claim_check_code}`}
                            className="text-red-500 hover:bg-red-50 hover:text-red-600"
                        >
                            <Trash2 className="h-4 w-4" />
                        </Button>
                    )}
                </div>
            );
        },
    },
];
