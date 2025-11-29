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
    AlertTriangle,
    Calculator,
    CheckCircle,
    Clock,
    Filter,
    Plus,
    Scale,
} from 'lucide-react';
import { useState } from 'react';
import CreateReconciliationModal from './CreateReconciliationModal';

interface Cashier {
    id: number;
    name: string;
}

interface CashierAwaitingReconciliation {
    id: number;
    name: string;
    system_total: number;
}

interface Reconciliation {
    id: number;
    cashier: Cashier | null;
    finance_officer: Cashier | null;
    reconciliation_date: string;
    system_total: number;
    physical_count: number;
    variance: number;
    variance_reason: string | null;
    status: 'balanced' | 'variance' | 'pending';
    created_at: string;
}

interface Summary {
    total_count: number;
    balanced_count: number;
    variance_count: number;
    total_system_amount: number;
    total_physical_amount: number;
    total_variance: number;
    average_variance: number;
}

interface Filters {
    start_date: string;
    end_date: string;
    cashier_id: string | null;
    status: string | null;
}

interface Props {
    reconciliations: Reconciliation[];
    summary: Summary;
    cashiers: Cashier[];
    cashiersAwaitingReconciliation: CashierAwaitingReconciliation[];
    filters: Filters;
}

export default function ReconciliationIndex({
    reconciliations,
    summary,
    cashiers,
    cashiersAwaitingReconciliation,
    filters,
}: Props) {
    const [startDate, setStartDate] = useState(filters.start_date);
    const [endDate, setEndDate] = useState(filters.end_date);
    const [cashierId, setCashierId] = useState(filters.cashier_id || '');
    const [status, setStatus] = useState(filters.status || '');
    const [showCreateModal, setShowCreateModal] = useState(false);

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-GH', {
            style: 'currency',
            currency: 'GHS',
        }).format(amount);
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-GB', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        });
    };

    const handleFilterApply = () => {
        router.get(
            '/billing/accounts/reconciliation',
            {
                start_date: startDate,
                end_date: endDate,
                cashier_id: cashierId || undefined,
                status: status || undefined,
            },
            { preserveState: true },
        );
    };

    const handleClearFilters = () => {
        setStartDate(filters.start_date);
        setEndDate(filters.end_date);
        setCashierId('');
        setStatus('');
        router.get('/billing/accounts/reconciliation', {}, { preserveState: true });
    };

    const getStatusBadge = (status: string, variance: number) => {
        if (status === 'balanced') {
            return (
                <Badge className="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                    <CheckCircle className="mr-1 h-3 w-3" />
                    Balanced
                </Badge>
            );
        }
        if (status === 'variance') {
            const isOverage = variance > 0;
            return (
                <Badge className={isOverage 
                    ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
                    : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                }>
                    <AlertTriangle className="mr-1 h-3 w-3" />
                    {isOverage ? 'Overage' : 'Shortage'}
                </Badge>
            );
        }
        return (
            <Badge className="bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                <Clock className="mr-1 h-3 w-3" />
                Pending
            </Badge>
        );
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Billing', href: '/billing' },
                { title: 'Accounts', href: '/billing/accounts' },
                { title: 'Reconciliation', href: '/billing/accounts/reconciliation' },
            ]}
        >
            <Head title="Cash Reconciliation" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                            Cash Reconciliation
                        </h1>
                        <p className="text-gray-600 dark:text-gray-400">
                            Reconcile cashier collections against physical cash counts
                        </p>
                    </div>
                    <Button onClick={() => setShowCreateModal(true)}>
                        <Plus className="mr-2 h-4 w-4" />
                        New Reconciliation
                    </Button>
                </div>

                {/* Cashiers Awaiting Reconciliation */}
                {cashiersAwaitingReconciliation.length > 0 && (
                    <Card className="border-yellow-200 bg-yellow-50 dark:border-yellow-800 dark:bg-yellow-900/20">
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center gap-2 text-base text-yellow-800 dark:text-yellow-200">
                                <Clock className="h-4 w-4" />
                                Awaiting Reconciliation Today
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex flex-wrap gap-3">
                                {cashiersAwaitingReconciliation.map((cashier) => (
                                    <div
                                        key={cashier.id}
                                        className="flex items-center gap-2 rounded-lg bg-white px-3 py-2 shadow-sm dark:bg-gray-800"
                                    >
                                        <span className="font-medium">{cashier.name}</span>
                                        <span className="text-sm text-gray-500">
                                            {formatCurrency(cashier.system_total)}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Summary Stats */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Total Reconciliations
                            </CardTitle>
                            <Scale className="h-4 w-4 text-blue-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-blue-600">
                                {summary.total_count}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                In selected period
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Balanced
                            </CardTitle>
                            <CheckCircle className="h-4 w-4 text-green-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600">
                                {summary.balanced_count}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                No variance
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                With Variance
                            </CardTitle>
                            <AlertTriangle className="h-4 w-4 text-orange-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-orange-600">
                                {summary.variance_count}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Requires attention
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Total Variance
                            </CardTitle>
                            <Calculator className="h-4 w-4 text-purple-600" />
                        </CardHeader>
                        <CardContent>
                            <div className={`text-2xl font-bold ${
                                summary.total_variance >= 0 ? 'text-blue-600' : 'text-red-600'
                            }`}>
                                {formatCurrency(summary.total_variance)}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Net {summary.total_variance >= 0 ? 'overage' : 'shortage'}
                            </p>
                        </CardContent>
                    </Card>
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
                        <div className="flex flex-wrap items-end gap-4">
                            <div className="min-w-[150px] flex-1">
                                <label className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Start Date
                                </label>
                                <Input
                                    type="date"
                                    value={startDate}
                                    onChange={(e) => setStartDate(e.target.value)}
                                    className="mt-1"
                                />
                            </div>
                            <div className="min-w-[150px] flex-1">
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
                            <div className="min-w-[150px] flex-1">
                                <label className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Cashier
                                </label>
                                <Select value={cashierId || 'all'} onValueChange={(v) => setCashierId(v === 'all' ? '' : v)}>
                                    <SelectTrigger className="mt-1">
                                        <SelectValue placeholder="All Cashiers" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Cashiers</SelectItem>
                                        {cashiers.map((c) => (
                                            <SelectItem key={c.id} value={c.id.toString()}>
                                                {c.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="min-w-[150px] flex-1">
                                <label className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Status
                                </label>
                                <Select value={status || 'all'} onValueChange={(v) => setStatus(v === 'all' ? '' : v)}>
                                    <SelectTrigger className="mt-1">
                                        <SelectValue placeholder="All Statuses" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Statuses</SelectItem>
                                        <SelectItem value="balanced">Balanced</SelectItem>
                                        <SelectItem value="variance">With Variance</SelectItem>
                                        <SelectItem value="pending">Pending</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="flex gap-2">
                                <Button onClick={handleFilterApply}>Apply</Button>
                                <Button variant="outline" onClick={handleClearFilters}>
                                    Clear
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Reconciliations Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>Reconciliation History</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {reconciliations.length > 0 ? (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Cashier</TableHead>
                                        <TableHead className="text-right">System Total</TableHead>
                                        <TableHead className="text-right">Physical Count</TableHead>
                                        <TableHead className="text-right">Variance</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Reconciled By</TableHead>
                                        <TableHead>Reason</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {reconciliations.map((reconciliation) => (
                                        <TableRow key={reconciliation.id}>
                                            <TableCell className="font-medium">
                                                {formatDate(reconciliation.reconciliation_date)}
                                            </TableCell>
                                            <TableCell>
                                                {reconciliation.cashier?.name || 'Unknown'}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {formatCurrency(reconciliation.system_total)}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {formatCurrency(reconciliation.physical_count)}
                                            </TableCell>
                                            <TableCell className={`text-right font-medium ${
                                                reconciliation.variance === 0
                                                    ? 'text-green-600'
                                                    : reconciliation.variance > 0
                                                      ? 'text-blue-600'
                                                      : 'text-red-600'
                                            }`}>
                                                {reconciliation.variance >= 0 ? '+' : ''}
                                                {formatCurrency(reconciliation.variance)}
                                            </TableCell>
                                            <TableCell>
                                                {getStatusBadge(reconciliation.status, reconciliation.variance)}
                                            </TableCell>
                                            <TableCell>
                                                {reconciliation.finance_officer?.name || 'Unknown'}
                                            </TableCell>
                                            <TableCell className="max-w-[200px] truncate">
                                                {reconciliation.variance_reason || '-'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        ) : (
                            <div className="py-8 text-center text-muted-foreground">
                                No reconciliations found for the selected filters
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Create Reconciliation Modal */}
            <CreateReconciliationModal
                open={showCreateModal}
                onOpenChange={setShowCreateModal}
                cashiers={cashiers}
                cashiersAwaitingReconciliation={cashiersAwaitingReconciliation}
            />
        </AppLayout>
    );
}
