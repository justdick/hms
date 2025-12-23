import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { DepositModal } from './components/DepositModal';
import { SetCreditModal } from './components/SetCreditModal';
import {
    PatientAccountData,
    patientAccountsColumns,
} from './patient-accounts-columns';
import { PatientAccountsDataTable } from './patient-accounts-data-table';

interface PaymentMethod {
    id: number;
    name: string;
    code: string;
}

interface PaginatedAccounts {
    data: PatientAccountData[];
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

interface Props {
    accounts: PaginatedAccounts;
    filters: { search?: string; filter?: string };
    paymentMethods: PaymentMethod[];
}

export default function PatientAccountsIndex({
    accounts,
    filters,
    paymentMethods,
}: Props) {
    const [depositModalOpen, setDepositModalOpen] = useState(false);
    const [setCreditModalOpen, setSetCreditModalOpen] = useState(false);

    const formatCurrency = (amount: string | number) => {
        return new Intl.NumberFormat('en-GH', {
            style: 'currency',
            currency: 'GHS',
        }).format(Number(amount));
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Billing', href: '/billing' },
                {
                    title: 'Patient Accounts',
                    href: '/billing/patient-accounts',
                },
            ]}
        >
            <Head title="Patient Accounts" />

            <div className="space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">
                        Patient Accounts
                    </h1>
                    <p className="text-muted-foreground">
                        Manage patient prepaid balances and credit limits
                    </p>
                </div>

                {/* DataTable */}
                <PatientAccountsDataTable
                    columns={patientAccountsColumns}
                    data={accounts?.data || []}
                    pagination={accounts}
                    onNewDepositClick={() => setDepositModalOpen(true)}
                    onSetCreditClick={() => setSetCreditModalOpen(true)}
                    searchValue={filters.search}
                    filterValue={filters.filter}
                />
            </div>

            {/* Deposit Modal */}
            <DepositModal
                isOpen={depositModalOpen}
                onClose={() => setDepositModalOpen(false)}
                paymentMethods={paymentMethods}
                formatCurrency={formatCurrency}
            />

            {/* Set Credit Modal */}
            <SetCreditModal
                isOpen={setCreditModalOpen}
                onClose={() => setSetCreditModalOpen(false)}
                formatCurrency={formatCurrency}
            />
        </AppLayout>
    );
}
