import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { format } from 'date-fns';
import { RefreshCw, Search } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Transaction {
    id: number;
    receipt_number: string | null;
    patient: {
        id: number;
        name: string;
        patient_number: string;
    } | null;
    description: string;
    service_type: string;
    amount: number;
    paid_amount: number;
    payment_method: string;
    paid_at: string;
    status: string;
}

interface CollectionSummary {
    cashier: {
        id: number;
        name: string;
    };
    date: string;
    total_amount: number;
    transaction_count: number;
}

interface MyCollectionsModalProps {
    isOpen: boolean;
    onClose: () => void;
    formatCurrency: (amount: number) => string;
}

const serviceTypeLabels: Record<string, string> = {
    consultation: 'Consultation',
    laboratory: 'Laboratory',
    pharmacy: 'Pharmacy',
    ward: 'Ward',
    procedure: 'Procedure',
};

const paymentMethodLabels: Record<string, string> = {
    cash: 'Cash',
    card: 'Card',
    mobile_money: 'Mobile Money',
    bank_transfer: 'Bank Transfer',
    insurance: 'Insurance',
};

export function MyCollectionsModal({
    isOpen,
    onClose,
    formatCurrency,
}: MyCollectionsModalProps) {
    const [summary, setSummary] = useState<CollectionSummary | null>(null);
    const [transactions, setTransactions] = useState<Transaction[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');

    const fetchCollections = async () => {
        try {
            const response = await fetch('/billing/my-collections');
            const data = await response.json();
            setSummary(data.summary);
            setTransactions(data.transactions);
        } catch (error) {
            console.error('Failed to fetch collections:', error);
        } finally {
            setIsLoading(false);
            setIsRefreshing(false);
        }
    };

    useEffect(() => {
        if (isOpen) {
            setIsLoading(true);
            fetchCollections();
        }
    }, [isOpen]);

    const handleRefresh = () => {
        setIsRefreshing(true);
        fetchCollections();
    };

    const filteredTransactions = transactions.filter((transaction) => {
        if (!searchQuery) return true;
        const query = searchQuery.toLowerCase();
        return (
            transaction.patient?.name.toLowerCase().includes(query) ||
            transaction.patient?.patient_number.toLowerCase().includes(query) ||
            transaction.receipt_number?.toLowerCase().includes(query) ||
            transaction.description.toLowerCase().includes(query)
        );
    });

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="flex max-h-[85vh] max-w-4xl flex-col overflow-hidden">
                <DialogHeader>
                    <DialogTitle>My Collections Today</DialogTitle>
                    <DialogDescription>
                        {summary && (
                            <span>
                                Total: {formatCurrency(summary.total_amount)} •{' '}
                                {summary.transaction_count} transaction
                                {summary.transaction_count !== 1 ? 's' : ''}
                            </span>
                        )}
                    </DialogDescription>
                </DialogHeader>

                <div className="flex items-center gap-2 py-2">
                    <div className="relative flex-1">
                        <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder="Search by patient, receipt number..."
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            className="pl-9"
                        />
                    </div>
                    <Button
                        variant="outline"
                        size="icon"
                        onClick={handleRefresh}
                        disabled={isRefreshing}
                    >
                        <RefreshCw
                            className={`h-4 w-4 ${isRefreshing ? 'animate-spin' : ''}`}
                        />
                    </Button>
                </div>

                <div className="flex-1 overflow-auto">
                    {isLoading ? (
                        <div className="space-y-2">
                            {[...Array(5)].map((_, i) => (
                                <Skeleton key={i} className="h-12 w-full" />
                            ))}
                        </div>
                    ) : filteredTransactions.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-12 text-center">
                            <p className="text-muted-foreground">
                                {searchQuery
                                    ? 'No transactions match your search'
                                    : 'No collections yet today'}
                            </p>
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Time</TableHead>
                                    <TableHead>Receipt #</TableHead>
                                    <TableHead>Patient</TableHead>
                                    <TableHead>Description</TableHead>
                                    <TableHead>Method</TableHead>
                                    <TableHead className="text-right">
                                        Amount
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filteredTransactions.map((transaction) => (
                                    <TableRow key={transaction.id}>
                                        <TableCell className="whitespace-nowrap">
                                            {transaction.paid_at
                                                ? format(
                                                      new Date(
                                                          transaction.paid_at,
                                                      ),
                                                      'HH:mm',
                                                  )
                                                : '-'}
                                        </TableCell>
                                        <TableCell className="font-mono text-xs">
                                            {transaction.receipt_number || '-'}
                                        </TableCell>
                                        <TableCell>
                                            {transaction.patient ? (
                                                <div>
                                                    <p className="font-medium">
                                                        {
                                                            transaction.patient
                                                                .name
                                                        }
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {
                                                            transaction.patient
                                                                .patient_number
                                                        }
                                                    </p>
                                                </div>
                                            ) : (
                                                '-'
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <div>
                                                <p className="max-w-[200px] truncate">
                                                    {transaction.description}
                                                </p>
                                                <Badge
                                                    variant="outline"
                                                    className="mt-1 text-xs"
                                                >
                                                    {serviceTypeLabels[
                                                        transaction.service_type
                                                    ] ||
                                                        transaction.service_type}
                                                </Badge>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="secondary">
                                                {paymentMethodLabels[
                                                    transaction.payment_method
                                                ] || transaction.payment_method}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-right font-medium">
                                            {formatCurrency(
                                                transaction.paid_amount,
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                </div>

                {/* Summary Footer */}
                {summary && summary.transaction_count > 0 && (
                    <div className="border-t pt-4">
                        <div className="flex items-center justify-between">
                            <span className="text-sm text-muted-foreground">
                                Showing {filteredTransactions.length} of{' '}
                                {transactions.length} transactions
                            </span>
                            <div className="text-right">
                                <p className="text-sm text-muted-foreground">
                                    Total Collected
                                </p>
                                <p className="text-xl font-bold text-primary">
                                    {formatCurrency(summary.total_amount)}
                                </p>
                            </div>
                        </div>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
