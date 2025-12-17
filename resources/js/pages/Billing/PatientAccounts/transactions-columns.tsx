'use client';

import { Badge } from '@/components/ui/badge';
import { ColumnDef } from '@tanstack/react-table';

interface PaymentMethod {
    id: number;
    name: string;
    code: string;
}

interface User {
    id: number;
    name: string;
}

interface Charge {
    id: number;
    description: string;
}

export interface TransactionData {
    id: number;
    transaction_number: string;
    type: string;
    amount: string;
    balance_before: string;
    balance_after: string;
    description: string;
    notes: string | null;
    transacted_at: string;
    payment_method: PaymentMethod | null;
    processed_by: User | null;
    charge: Charge | null;
}

const formatCurrency = (amount: string | number) => {
    return new Intl.NumberFormat('en-GH', {
        style: 'currency',
        currency: 'GHS',
    }).format(Number(amount));
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getTransactionTypeBadge = (type: string) => {
    switch (type) {
        case 'deposit':
            return <Badge variant="default">Deposit</Badge>;
        case 'charge_deduction':
            return <Badge variant="secondary">Charge</Badge>;
        case 'payment':
            return <Badge variant="default">Payment</Badge>;
        case 'refund':
            return <Badge variant="outline">Refund</Badge>;
        case 'adjustment':
            return <Badge className="bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300">Adjustment</Badge>;
        case 'credit_limit_change':
            return <Badge variant="secondary">Credit Change</Badge>;
        default:
            return <Badge variant="outline">{type}</Badge>;
    }
};

export const transactionsColumns: ColumnDef<TransactionData>[] = [
    {
        accessorKey: 'transacted_at',
        id: 'date',
        header: 'Date',
        cell: ({ row }) => (
            <div className="text-sm">{formatDate(row.original.transacted_at)}</div>
        ),
    },
    {
        accessorKey: 'transaction_number',
        id: 'transaction_number',
        header: 'Transaction #',
        cell: ({ row }) => (
            <div className="font-mono text-sm">{row.original.transaction_number}</div>
        ),
    },
    {
        accessorKey: 'type',
        id: 'type',
        header: 'Type',
        cell: ({ row }) => getTransactionTypeBadge(row.original.type),
    },
    {
        accessorKey: 'description',
        id: 'description',
        header: 'Description',
        cell: ({ row }) => {
            const txn = row.original;
            return (
                <div>
                    <div>{txn.description}</div>
                    {txn.notes && (
                        <div className="text-sm text-gray-500 italic">{txn.notes}</div>
                    )}
                    {txn.payment_method && (
                        <div className="text-sm text-gray-500">via {txn.payment_method.name}</div>
                    )}
                </div>
            );
        },
    },
    {
        accessorKey: 'amount',
        id: 'amount',
        header: () => <div className="text-right">Amount</div>,
        cell: ({ row }) => {
            const amount = Number(row.original.amount);
            return (
                <div className={`text-right font-medium ${amount >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                    {amount >= 0 ? '+' : ''}{formatCurrency(amount)}
                </div>
            );
        },
    },
    {
        accessorKey: 'balance_after',
        id: 'balance_after',
        header: () => <div className="text-right">Balance After</div>,
        cell: ({ row }) => (
            <div className="text-right">{formatCurrency(row.original.balance_after)}</div>
        ),
    },
];
