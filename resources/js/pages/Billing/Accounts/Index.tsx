import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
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
    Building2,
    CreditCard,
    DollarSign,
    Filter,
    TrendingUp,
    Users,
} from 'lucide-react';
import { useState } from 'react';

interface PaymentMethod {
    id: number;
    name: string;
    code: string;
}

interface CashierCollection {
    cashier_id: number;
    cashier_name: string;
    total_amount: number;
    transaction_count: number;
}

interface PaymentMethodCollection {
    name: string;
    code: string;
    total_amount: number;
    transaction_count: number;
}

interface DepartmentCollection {
    department_id: number;
    department_name: string;
    total_amount: number;
    transaction_count: number;
}

interface Filters {
    start_date: string;
    end_date: string;
}

interface Props {
    totalCollections: number;
    transactionCount: number;
    collectionsByCashier: CashierCollection[];
    collectionsByPaymentMethod: PaymentMethodCollection[];
    collectionsByDepartment: DepartmentCollection[];
    paymentMethods: PaymentMethod[];
    filters: Filters;
}


export default function AccountsIndex({
    totalCollections,
    transactionCount,
    collectionsByCashier,
    collectionsByPaymentMethod,
    collectionsByDepartment,
    filters,
}: Props) {
    const [startDate, setStartDate] = useState(filters.start_date);
    const [endDate, setEndDate] = useState(filters.end_date);

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-GH', {
            style: 'currency',
            currency: 'GHS',
        }).format(amount);
    };

    const handleFilterApply = () => {
        router.get(
            '/billing/accounts',
            { start_date: startDate, end_date: endDate },
            { preserveState: true },
        );
    };

    const handleQuickFilter = (days: number) => {
        const end = new Date();
        const start = new Date();
        start.setDate(start.getDate() - days);

        const formatDate = (date: Date) => date.toISOString().split('T')[0];

        setStartDate(formatDate(start));
        setEndDate(formatDate(end));

        router.get(
            '/billing/accounts',
            { start_date: formatDate(start), end_date: formatDate(end) },
            { preserveState: true },
        );
    };

    // Calculate totals for charts
    const totalByPaymentMethod = collectionsByPaymentMethod.reduce(
        (sum, m) => sum + m.total_amount,
        0,
    );
    const totalByDepartment = collectionsByDepartment.reduce(
        (sum, d) => sum + d.total_amount,
        0,
    );

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Billing', href: '/billing' },
                { title: 'Accounts Dashboard', href: '/billing/accounts' },
            ]}
        >
            <Head title="Finance Officer Dashboard" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                            Finance Officer Dashboard
                        </h1>
                        <p className="text-gray-600 dark:text-gray-400">
                            Monitor collections and financial performance
                        </p>
                    </div>
                </div>

                {/* Date Range Filter */}
                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Filter className="h-4 w-4" />
                            Date Range Filter
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-wrap items-end gap-4">
                            <div className="flex-1 min-w-[150px]">
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
                            <div className="flex-1 min-w-[150px]">
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
                            <Button onClick={handleFilterApply}>
                                Apply Filter
                            </Button>
                            <div className="flex gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => handleQuickFilter(0)}
                                >
                                    Today
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => handleQuickFilter(7)}
                                >
                                    Last 7 Days
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => handleQuickFilter(30)}
                                >
                                    Last 30 Days
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>


                {/* Summary Stats */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Total Collections
                            </CardTitle>
                            <DollarSign className="h-4 w-4 text-green-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600">
                                {formatCurrency(totalCollections)}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                For selected period
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Transactions
                            </CardTitle>
                            <TrendingUp className="h-4 w-4 text-blue-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-blue-600">
                                {transactionCount}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Total transactions
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Active Cashiers
                            </CardTitle>
                            <Users className="h-4 w-4 text-purple-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-purple-600">
                                {collectionsByCashier.length}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Cashiers with collections
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Avg per Transaction
                            </CardTitle>
                            <CreditCard className="h-4 w-4 text-orange-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-orange-600">
                                {formatCurrency(
                                    transactionCount > 0
                                        ? totalCollections / transactionCount
                                        : 0,
                                )}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Average transaction value
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Collections by Cashier */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Users className="h-5 w-5" />
                            Collections by Cashier
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {collectionsByCashier.length > 0 ? (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Cashier</TableHead>
                                        <TableHead className="text-right">
                                            Transactions
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Total Amount
                                        </TableHead>
                                        <TableHead className="text-right">
                                            % of Total
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {collectionsByCashier.map((cashier) => (
                                        <TableRow key={cashier.cashier_id}>
                                            <TableCell className="font-medium">
                                                {cashier.cashier_name}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {cashier.transaction_count}
                                            </TableCell>
                                            <TableCell className="text-right font-medium text-green-600">
                                                {formatCurrency(
                                                    cashier.total_amount,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {totalCollections > 0
                                                    ? (
                                                          (cashier.total_amount /
                                                              totalCollections) *
                                                          100
                                                      ).toFixed(1)
                                                    : 0}
                                                %
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        ) : (
                            <div className="text-center py-8 text-muted-foreground">
                                No collections found for the selected period
                            </div>
                        )}
                    </CardContent>
                </Card>


                {/* Collections by Payment Method and Department */}
                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Collections by Payment Method */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <CreditCard className="h-5 w-5" />
                                Collections by Payment Method
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {collectionsByPaymentMethod.length > 0 ? (
                                <div className="space-y-4">
                                    {collectionsByPaymentMethod
                                        .filter((m) => m.total_amount > 0)
                                        .map((method) => (
                                            <div
                                                key={method.code}
                                                className="space-y-2"
                                            >
                                                <div className="flex items-center justify-between">
                                                    <span className="text-sm font-medium">
                                                        {method.name}
                                                    </span>
                                                    <span className="text-sm font-medium text-green-600">
                                                        {formatCurrency(
                                                            method.total_amount,
                                                        )}
                                                    </span>
                                                </div>
                                                <div className="h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                                    <div
                                                        className="h-full bg-blue-600 rounded-full transition-all"
                                                        style={{
                                                            width: `${totalByPaymentMethod > 0 ? (method.total_amount / totalByPaymentMethod) * 100 : 0}%`,
                                                        }}
                                                    />
                                                </div>
                                                <div className="flex justify-between text-xs text-muted-foreground">
                                                    <span>
                                                        {method.transaction_count}{' '}
                                                        transactions
                                                    </span>
                                                    <span>
                                                        {totalByPaymentMethod >
                                                        0
                                                            ? (
                                                                  (method.total_amount /
                                                                      totalByPaymentMethod) *
                                                                  100
                                                              ).toFixed(1)
                                                            : 0}
                                                        %
                                                    </span>
                                                </div>
                                            </div>
                                        ))}
                                    {collectionsByPaymentMethod.filter(
                                        (m) => m.total_amount > 0,
                                    ).length === 0 && (
                                        <div className="text-center py-4 text-muted-foreground">
                                            No collections found
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <div className="text-center py-8 text-muted-foreground">
                                    No payment method data available
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Collections by Department */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Building2 className="h-5 w-5" />
                                Collections by Department
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {collectionsByDepartment.length > 0 ? (
                                <div className="space-y-4">
                                    {collectionsByDepartment.map((dept) => (
                                        <div
                                            key={dept.department_id}
                                            className="space-y-2"
                                        >
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm font-medium">
                                                    {dept.department_name}
                                                </span>
                                                <span className="text-sm font-medium text-green-600">
                                                    {formatCurrency(
                                                        dept.total_amount,
                                                    )}
                                                </span>
                                            </div>
                                            <div className="h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                                <div
                                                    className="h-full bg-purple-600 rounded-full transition-all"
                                                    style={{
                                                        width: `${totalByDepartment > 0 ? (dept.total_amount / totalByDepartment) * 100 : 0}%`,
                                                    }}
                                                />
                                            </div>
                                            <div className="flex justify-between text-xs text-muted-foreground">
                                                <span>
                                                    {dept.transaction_count}{' '}
                                                    transactions
                                                </span>
                                                <span>
                                                    {totalByDepartment > 0
                                                        ? (
                                                              (dept.total_amount /
                                                                  totalByDepartment) *
                                                              100
                                                          ).toFixed(1)
                                                        : 0}
                                                    %
                                                </span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="text-center py-8 text-muted-foreground">
                                    No department data available
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
