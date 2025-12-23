'use client';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import { ArrowUpDown, Eye } from 'lucide-react';

export interface PatientAccountData {
    id: number;
    account_number: string;
    balance: string;
    credit_limit: string;
    credit_reason: string | null;
    is_active: boolean;
    patient: {
        id: number;
        first_name: string;
        last_name: string;
        patient_number: string;
    };
    credit_authorized_by: {
        id: number;
        name: string;
    } | null;
}

const formatCurrency = (amount: string | number) => {
    return new Intl.NumberFormat('en-GH', {
        style: 'currency',
        currency: 'GHS',
    }).format(Number(amount));
};

const getStatusBadge = (account: PatientAccountData) => {
    const balance = Number(account.balance);
    const creditLimit = Number(account.credit_limit);

    if (balance > 0) {
        return (
            <Badge className="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                Prepaid
            </Badge>
        );
    } else if (balance < 0) {
        return <Badge variant="destructive">Owing</Badge>;
    } else if (creditLimit > 0) {
        return (
            <Badge className="bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                Credit
            </Badge>
        );
    }
    return <Badge variant="outline">No Balance</Badge>;
};

export const patientAccountsColumns: ColumnDef<PatientAccountData>[] = [
    {
        accessorKey: 'account_number',
        id: 'account_number',
        header: ({ column }) => (
            <Button
                variant="ghost"
                onClick={() =>
                    column.toggleSorting(column.getIsSorted() === 'asc')
                }
            >
                Account #
                <ArrowUpDown className="ml-2 h-4 w-4" />
            </Button>
        ),
        cell: ({ row }) => (
            <div className="font-mono text-sm">
                {row.original.account_number}
            </div>
        ),
    },
    {
        accessorKey: 'patient.first_name',
        id: 'patient',
        header: ({ column }) => (
            <Button
                variant="ghost"
                onClick={() =>
                    column.toggleSorting(column.getIsSorted() === 'asc')
                }
            >
                Patient
                <ArrowUpDown className="ml-2 h-4 w-4" />
            </Button>
        ),
        cell: ({ row }) => {
            const patient = row.original.patient;
            return (
                <div className="space-y-1">
                    <div className="font-medium">
                        {patient.first_name} {patient.last_name}
                    </div>
                    <div className="text-sm text-muted-foreground">
                        {patient.patient_number}
                    </div>
                </div>
            );
        },
    },
    {
        accessorKey: 'balance',
        id: 'deposit',
        header: ({ column }) => (
            <Button
                variant="ghost"
                onClick={() =>
                    column.toggleSorting(column.getIsSorted() === 'asc')
                }
            >
                Deposit
                <ArrowUpDown className="ml-2 h-4 w-4" />
            </Button>
        ),
        cell: ({ row }) => {
            const balance = Number(row.original.balance);
            const deposit = Math.max(0, balance);
            return deposit > 0 ? (
                <span className="font-medium text-green-600">
                    {formatCurrency(deposit)}
                </span>
            ) : (
                <span className="text-gray-400">-</span>
            );
        },
    },
    {
        id: 'owing',
        header: 'Owing',
        cell: ({ row }) => {
            const balance = Number(row.original.balance);
            const owing = balance < 0 ? Math.abs(balance) : 0;
            return owing > 0 ? (
                <span className="font-medium text-red-600">
                    {formatCurrency(owing)}
                </span>
            ) : (
                <span className="text-gray-400">-</span>
            );
        },
    },
    {
        accessorKey: 'credit_limit',
        id: 'credit_limit',
        header: 'Credit Limit',
        cell: ({ row }) => {
            const creditLimit = Number(row.original.credit_limit);
            const isUnlimited = creditLimit >= 999999999;
            if (isUnlimited) {
                return (
                    <span className="font-medium text-blue-600">Unlimited</span>
                );
            }
            return creditLimit > 0 ? (
                <span className="text-blue-600">
                    {formatCurrency(creditLimit)}
                </span>
            ) : (
                <span className="text-gray-400">-</span>
            );
        },
    },
    {
        id: 'status',
        header: 'Status',
        cell: ({ row }) => getStatusBadge(row.original),
        filterFn: (row, id, value) => {
            const balance = Number(row.original.balance);
            const creditLimit = Number(row.original.credit_limit);
            if (value === 'prepaid') return balance > 0;
            if (value === 'owing') return balance < 0;
            if (value === 'credit') return creditLimit > 0;
            return true;
        },
    },
    {
        id: 'actions',
        enableHiding: false,
        cell: ({ row }) => {
            const account = row.original;
            return (
                <Button size="sm" variant="ghost" asChild>
                    <Link
                        href={`/billing/patient-accounts/patient/${account.patient.id}`}
                    >
                        <Eye className="mr-1 h-3 w-3" />
                        View
                    </Link>
                </Button>
            );
        },
    },
];
