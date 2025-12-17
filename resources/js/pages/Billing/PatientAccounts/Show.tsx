import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { StatCard } from '@/components/ui/stat-card';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { ArrowLeft, CreditCard, Hash, Infinity, Plus, PlusCircle, Shield, Wallet } from 'lucide-react';
import { useState } from 'react';
import { AdjustmentModal } from './components/AdjustmentModal';
import { CreditLimitModal } from './components/CreditLimitModal';
import { DepositModal } from './components/DepositModal';
import { TransactionData, transactionsColumns } from './transactions-columns';
import { TransactionsDataTable } from './transactions-data-table';

interface Patient {
    id: number;
    first_name: string;
    last_name: string;
    patient_number: string;
    phone_number: string;
}

interface User {
    id: number;
    name: string;
}

interface PaymentMethod {
    id: number;
    name: string;
    code: string;
}

interface Account {
    id: number;
    account_number: string;
    balance: string;
    credit_limit: string;
    credit_reason: string | null;
    credit_authorized_at: string | null;
    credit_authorized_by: User | null;
}

interface PaginatedTransactions {
    data: TransactionData[];
    current_page: number;
    last_page: number;
    links: { url: string | null; label: string; active: boolean }[];
}

interface Props {
    patient: Patient;
    account: Account | null;
    transactions: PaginatedTransactions | null;
    paymentMethods: PaymentMethod[];
}

const UNLIMITED_CREDIT_VALUE = 999999999;

export default function PatientAccountShow({ patient, account, transactions, paymentMethods }: Props) {
    const [depositModalOpen, setDepositModalOpen] = useState(false);
    const [creditLimitModalOpen, setCreditLimitModalOpen] = useState(false);
    const [adjustmentModalOpen, setAdjustmentModalOpen] = useState(false);

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

    const balance = account ? Number(account.balance) : 0;
    const creditLimit = account ? Number(account.credit_limit) : 0;
    const isUnlimitedCredit = creditLimit >= UNLIMITED_CREDIT_VALUE;
    
    // Deposit balance = positive balance only
    const depositBalance = Math.max(0, balance);
    // Amount owed = absolute value of negative balance
    const amountOwed = balance < 0 ? Math.abs(balance) : 0;
    // Remaining credit = credit limit minus amount owed
    const remainingCredit = Math.max(0, creditLimit - amountOwed);

    const getBalanceVariant = () => {
        if (balance > 0) return 'success';
        if (balance < 0) return 'error';
        return 'default';
    };

    return (
        <AppLayout breadcrumbs={[
            { title: 'Billing', href: '/billing' },
            { title: 'Patient Accounts', href: '/billing/patient-accounts' },
            { title: `${patient.first_name} ${patient.last_name}`, href: '#' },
        ]}>
            <Head title={`Account - ${patient.first_name} ${patient.last_name}`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="ghost" size="sm" onClick={() => router.visit('/billing/patient-accounts')}>
                            <ArrowLeft className="h-4 w-4" />
                        </Button>
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                {patient.first_name} {patient.last_name}
                            </h1>
                            <p className="text-gray-600 dark:text-gray-400">
                                {patient.patient_number} • {patient.phone_number}
                            </p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={() => setAdjustmentModalOpen(true)}>
                            <PlusCircle className="mr-2 h-4 w-4" />
                            Make Adjustment
                        </Button>
                        <Button variant="outline" onClick={() => setCreditLimitModalOpen(true)}>
                            <Shield className="mr-2 h-4 w-4" />
                            Set Credit Limit
                        </Button>
                        <Button onClick={() => setDepositModalOpen(true)}>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Deposit
                        </Button>
                    </div>
                </div>

                {/* Summary Cards using StatCard */}
                <div className="grid gap-4 md:grid-cols-4">
                    <StatCard
                        label="Deposit Balance"
                        value={formatCurrency(depositBalance)}
                        icon={<Wallet className="h-5 w-5" />}
                        variant={depositBalance > 0 ? 'success' : 'default'}
                    />
                    <StatCard
                        label="Amount Owed"
                        value={formatCurrency(amountOwed)}
                        icon={<CreditCard className="h-5 w-5" />}
                        variant={amountOwed > 0 ? 'error' : 'default'}
                    />
                    <StatCard
                        label="Credit Limit"
                        value={isUnlimitedCredit ? 'Unlimited' : formatCurrency(creditLimit)}
                        icon={isUnlimitedCredit ? <Infinity className="h-5 w-5" /> : <Shield className="h-5 w-5" />}
                        variant={creditLimit > 0 ? 'info' : 'default'}
                    />
                    <StatCard
                        label="Remaining Credit"
                        value={isUnlimitedCredit ? 'Unlimited' : formatCurrency(remainingCredit)}
                        icon={<Hash className="h-5 w-5" />}
                        variant="default"
                    />
                </div>

                {/* Credit Reason */}
                {account?.credit_reason && (
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm">Credit Authorization</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-gray-700 dark:text-gray-300">{account.credit_reason}</p>
                            {account.credit_authorized_at && (
                                <p className="text-sm text-gray-500 mt-2">
                                    Authorized on {formatDate(account.credit_authorized_at)}
                                    {account.credit_authorized_by && ` by ${account.credit_authorized_by.name}`}
                                </p>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Transaction History */}
                <Card>
                    <CardHeader>
                        <CardTitle>Transaction History</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <TransactionsDataTable
                            columns={transactionsColumns}
                            data={transactions?.data || []}
                            pagination={transactions}
                        />
                    </CardContent>
                </Card>
            </div>

            {/* Modals */}
            <DepositModal
                isOpen={depositModalOpen}
                onClose={() => setDepositModalOpen(false)}
                paymentMethods={paymentMethods}
                formatCurrency={formatCurrency}
                preselectedPatient={{
                    id: patient.id,
                    full_name: `${patient.first_name} ${patient.last_name}`,
                    patient_number: patient.patient_number,
                    phone_number: patient.phone_number,
                    account_balance: balance,
                    credit_limit: creditLimit,
                    available_balance: isUnlimitedCredit ? 999999999 : balance + creditLimit,
                }}
            />

            <CreditLimitModal
                isOpen={creditLimitModalOpen}
                onClose={() => setCreditLimitModalOpen(false)}
                patient={patient}
                currentLimit={creditLimit}
                formatCurrency={formatCurrency}
            />

            <AdjustmentModal
                isOpen={adjustmentModalOpen}
                onClose={() => setAdjustmentModalOpen(false)}
                patient={patient}
                currentBalance={balance}
                formatCurrency={formatCurrency}
            />
        </AppLayout>
    );
}
