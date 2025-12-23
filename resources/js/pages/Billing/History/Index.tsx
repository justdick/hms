import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { StatCard } from '@/components/ui/stat-card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import {
    CheckCircle,
    DollarSign,
    Eye,
    Filter,
    Receipt,
    Search,
    TrendingUp,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import DetailSlideOver from './DetailSlideOver';

interface Patient {
    id: number;
    patient_number: string;
    name: string;
}

interface Department {
    id: number;
    name: string;
}

interface ProcessedBy {
    id: number;
    name: string;
}

interface Payment {
    id: number;
    service_type: string;
    service_code: string | null;
    description: string;
    amount: number;
    paid_amount: number;
    status: string;
    receipt_number: string | null;
    paid_at: string;
    metadata: {
        payment_method?: string;
        reference_number?: string;
    } | null;
    patient_checkin: {
        patient: Patient;
        department: Department;
    } | null;
    processed_by_user: ProcessedBy | null;
}

interface Cashier {
    id: number;
    name: string;
}

interface PaymentMethod {
    id: number;
    name: string;
    code: string;
}

interface Summary {
    total_amount: number;
    transaction_count: number;
}

interface Filters {
    start_date: string;
    end_date: string;
    cashier_id: string | null;
    patient_search: string | null;
    payment_method: string | null;
    min_amount: string | null;
    max_amount: string | null;
    receipt_search: string | null;
}

interface PaginatedPayments {
    data: Payment[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}

interface Permissions {
    canVoid: boolean;
    canRefund: boolean;
}

interface Props {
    payments: PaginatedPayments;
    cashiers: Cashier[];
    paymentMethods: PaymentMethod[];
    summary: Summary;
    filters: Filters;
    permissions?: Permissions;
}

export default function HistoryIndex({
    payments,
    cashiers,
    paymentMethods,
    summary,
    filters,
    permissions = { canVoid: false, canRefund: false },
}: Props) {
    const [startDate, setStartDate] = useState(filters.start_date);
    const [endDate, setEndDate] = useState(filters.end_date);
    const [cashierId, setCashierId] = useState(filters.cashier_id || '');
    const [patientSearch, setPatientSearch] = useState(
        filters.patient_search || '',
    );
    const [paymentMethod, setPaymentMethod] = useState(
        filters.payment_method || '',
    );
    const [minAmount, setMinAmount] = useState(filters.min_amount || '');
    const [maxAmount, setMaxAmount] = useState(filters.max_amount || '');
    const [receiptSearch, setReceiptSearch] = useState(
        filters.receipt_search || '',
    );
    const [selectedPayment, setSelectedPayment] = useState<Payment | null>(
        null,
    );
    const [showDetail, setShowDetail] = useState(false);

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-GH', {
            style: 'currency',
            currency: 'GHS',
        }).format(amount);
    };

    const formatDateTime = (dateString: string) => {
        return new Date(dateString).toLocaleString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const handleFilterApply = () => {
        router.get(
            '/billing/accounts/history',
            {
                start_date: startDate,
                end_date: endDate,
                cashier_id: cashierId || undefined,
                patient_search: patientSearch || undefined,
                payment_method: paymentMethod || undefined,
                min_amount: minAmount || undefined,
                max_amount: maxAmount || undefined,
                receipt_search: receiptSearch || undefined,
            },
            { preserveState: true },
        );
    };

    const handleClearFilters = () => {
        setStartDate(filters.start_date);
        setEndDate(filters.end_date);
        setCashierId('');
        setPatientSearch('');
        setPaymentMethod('');
        setMinAmount('');
        setMaxAmount('');
        setReceiptSearch('');
        router.get('/billing/accounts/history', {}, { preserveState: true });
    };

    const handlePageChange = (url: string | null) => {
        if (url) {
            router.get(url, {}, { preserveState: true });
        }
    };

    const handleViewDetail = (payment: Payment) => {
        setSelectedPayment(payment);
        setShowDetail(true);
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'paid':
                return (
                    <Badge className="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                        <CheckCircle className="mr-1 h-3 w-3" />
                        Paid
                    </Badge>
                );
            case 'partial':
                return (
                    <Badge className="bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                        Partial
                    </Badge>
                );
            case 'voided':
                return (
                    <Badge className="bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                        <XCircle className="mr-1 h-3 w-3" />
                        Voided
                    </Badge>
                );
            case 'refunded':
                return (
                    <Badge className="bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                        Refunded
                    </Badge>
                );
            default:
                return <Badge>{status}</Badge>;
        }
    };

    const getPaymentMethodLabel = (code: string | undefined) => {
        if (!code) return 'Cash';
        const method = paymentMethods.find((m) => m.code === code);
        return method?.name || code;
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Billing', href: '/billing' },
                { title: 'Accounts', href: '/billing/accounts' },
                { title: 'Payment History', href: '/billing/accounts/history' },
            ]}
        >
            <Head title="Payment History" />

            <div className="space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                        Payment History
                    </h1>
                    <p className="text-gray-600 dark:text-gray-400">
                        View and search all payment transactions
                    </p>
                </div>

                {/* Summary Stats */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <StatCard
                        label="Total Amount"
                        value={formatCurrency(summary.total_amount)}
                        icon={<DollarSign className="h-4 w-4" />}
                        variant="success"
                    />
                    <StatCard
                        label="Transactions"
                        value={summary.transaction_count}
                        icon={<TrendingUp className="h-4 w-4" />}
                        variant="info"
                    />
                    <StatCard
                        label={`Showing ${payments.data.length} of ${payments.total}`}
                        value={`Page ${payments.current_page} of ${payments.last_page}`}
                        icon={<Receipt className="h-4 w-4" />}
                    />
                </div>

                {/* Filters */}
                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Filter className="h-4 w-4" />
                            Filters
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <label className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Start Date
                                </label>
                                <Input
                                    type="date"
                                    value={startDate}
                                    onChange={(e) =>
                                        setStartDate(e.target.value)
                                    }
                                    className="mt-1"
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    End Date
                                </label>
                                <Input
                                    type="date"
                                    value={endDate}
                                    onChange={(e) => setEndDate(e.target.value)}
                                    className="mt-1"
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Cashier
                                </label>
                                <Select
                                    value={cashierId || 'all'}
                                    onValueChange={(v) =>
                                        setCashierId(v === 'all' ? '' : v)
                                    }
                                >
                                    <SelectTrigger className="mt-1">
                                        <SelectValue placeholder="All Cashiers" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All Cashiers
                                        </SelectItem>
                                        {cashiers.map((c) => (
                                            <SelectItem
                                                key={c.id}
                                                value={c.id.toString()}
                                            >
                                                {c.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Payment Method
                                </label>
                                <Select
                                    value={paymentMethod || 'all'}
                                    onValueChange={(v) =>
                                        setPaymentMethod(v === 'all' ? '' : v)
                                    }
                                >
                                    <SelectTrigger className="mt-1">
                                        <SelectValue placeholder="All Methods" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All Methods
                                        </SelectItem>
                                        {paymentMethods.map((m) => (
                                            <SelectItem
                                                key={m.id}
                                                value={m.code}
                                            >
                                                {m.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Patient Search
                                </label>
                                <div className="relative mt-1">
                                    <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
                                    <Input
                                        type="text"
                                        placeholder="Name or patient number"
                                        value={patientSearch}
                                        onChange={(e) =>
                                            setPatientSearch(e.target.value)
                                        }
                                        className="pl-9"
                                    />
                                </div>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Receipt Number
                                </label>
                                <div className="relative mt-1">
                                    <Receipt className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400" />
                                    <Input
                                        type="text"
                                        placeholder="Search receipt"
                                        value={receiptSearch}
                                        onChange={(e) =>
                                            setReceiptSearch(e.target.value)
                                        }
                                        className="pl-9"
                                    />
                                </div>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Min Amount
                                </label>
                                <Input
                                    type="number"
                                    placeholder="0.00"
                                    value={minAmount}
                                    onChange={(e) =>
                                        setMinAmount(e.target.value)
                                    }
                                    className="mt-1"
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Max Amount
                                </label>
                                <Input
                                    type="number"
                                    placeholder="0.00"
                                    value={maxAmount}
                                    onChange={(e) =>
                                        setMaxAmount(e.target.value)
                                    }
                                    className="mt-1"
                                />
                            </div>
                        </div>
                        <div className="mt-4 flex gap-2">
                            <Button onClick={handleFilterApply}>
                                Apply Filters
                            </Button>
                            <Button
                                variant="outline"
                                onClick={handleClearFilters}
                            >
                                Clear
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                {/* Payments Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>Payment Transactions</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {payments.data.length > 0 ? (
                            <>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Date/Time</TableHead>
                                            <TableHead>Receipt #</TableHead>
                                            <TableHead>Patient</TableHead>
                                            <TableHead>Description</TableHead>
                                            <TableHead>Method</TableHead>
                                            <TableHead className="text-right">
                                                Amount
                                            </TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Cashier</TableHead>
                                            <TableHead className="text-right">
                                                Actions
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {payments.data.map((payment) => (
                                            <TableRow key={payment.id}>
                                                <TableCell className="whitespace-nowrap">
                                                    {formatDateTime(
                                                        payment.paid_at,
                                                    )}
                                                </TableCell>
                                                <TableCell className="font-mono text-sm">
                                                    {payment.receipt_number ||
                                                        '-'}
                                                </TableCell>
                                                <TableCell>
                                                    <div>
                                                        <div className="font-medium">
                                                            {payment
                                                                .patient_checkin
                                                                ?.patient
                                                                .name ||
                                                                'Unknown'}
                                                        </div>
                                                        <div className="text-xs text-muted-foreground">
                                                            {
                                                                payment
                                                                    .patient_checkin
                                                                    ?.patient
                                                                    .patient_number
                                                            }
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="max-w-[200px] truncate">
                                                    {payment.description}
                                                </TableCell>
                                                <TableCell>
                                                    {getPaymentMethodLabel(
                                                        payment.metadata
                                                            ?.payment_method,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right font-medium text-green-600">
                                                    {formatCurrency(
                                                        payment.paid_amount,
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {getStatusBadge(
                                                        payment.status,
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {payment.processed_by_user
                                                        ?.name || '-'}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            handleViewDetail(
                                                                payment,
                                                            )
                                                        }
                                                    >
                                                        <Eye className="h-4 w-4" />
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>

                                {/* Pagination */}
                                {payments.last_page > 1 && (
                                    <div className="mt-4 flex items-center justify-between">
                                        <div className="text-sm text-muted-foreground">
                                            Showing{' '}
                                            {(payments.current_page - 1) *
                                                payments.per_page +
                                                1}{' '}
                                            to{' '}
                                            {Math.min(
                                                payments.current_page *
                                                    payments.per_page,
                                                payments.total,
                                            )}{' '}
                                            of {payments.total} results
                                        </div>
                                        <div className="flex gap-1">
                                            {payments.links.map(
                                                (link, index) => (
                                                    <Button
                                                        key={index}
                                                        variant={
                                                            link.active
                                                                ? 'default'
                                                                : 'outline'
                                                        }
                                                        size="sm"
                                                        disabled={!link.url}
                                                        onClick={() =>
                                                            handlePageChange(
                                                                link.url,
                                                            )
                                                        }
                                                        dangerouslySetInnerHTML={{
                                                            __html: link.label,
                                                        }}
                                                    />
                                                ),
                                            )}
                                        </div>
                                    </div>
                                )}
                            </>
                        ) : (
                            <div className="py-8 text-center text-muted-foreground">
                                No payment transactions found for the selected
                                filters
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Detail Slide Over */}
            <DetailSlideOver
                open={showDetail}
                onOpenChange={setShowDetail}
                payment={selectedPayment}
                canVoid={permissions.canVoid}
                canRefund={permissions.canRefund}
            />
        </AppLayout>
    );
}
