import { ManageCreditModal } from '@/components/Patient/ManageCreditModal';
import { Badge } from '@/components/ui/badge';
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
    CreditCard,
    DollarSign,
    Search,
    Settings,
    Star,
    Users,
} from 'lucide-react';
import { useState } from 'react';

interface CreditPatient {
    id: number;
    patient_number: string;
    full_name: string;
    first_name: string;
    last_name: string;
    phone_number: string | null;
    is_credit_eligible: boolean;
    credit_reason: string | null;
    credit_authorized_by: string | null;
    credit_authorized_at: string | null;
    total_owing: number;
}

interface Filters {
    search: string | null;
}

interface Props {
    patients: CreditPatient[];
    totalPatients: number;
    totalOwing: number;
    filters: Filters;
}

export default function CreditPatientsIndex({
    patients,
    totalPatients,
    totalOwing,
    filters,
}: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [selectedPatient, setSelectedPatient] = useState<CreditPatient | null>(null);
    const [isManageModalOpen, setIsManageModalOpen] = useState(false);

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-GH', {
            style: 'currency',
            currency: 'GHS',
        }).format(amount);
    };

    const handleSearch = () => {
        router.get(
            '/billing/accounts/credit-patients',
            { search: search || undefined },
            { preserveState: true },
        );
    };

    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter') {
            handleSearch();
        }
    };

    const handleClearSearch = () => {
        setSearch('');
        router.get('/billing/accounts/credit-patients', {}, { preserveState: true });
    };

    const handleManageCredit = (patient: CreditPatient) => {
        setSelectedPatient(patient);
        setIsManageModalOpen(true);
    };

    const handleCloseModal = () => {
        setIsManageModalOpen(false);
        setSelectedPatient(null);
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Billing', href: '/billing' },
                { title: 'Accounts', href: '/billing/accounts' },
                { title: 'Credit Patients', href: '/billing/accounts/credit-patients' },
            ]}
        >
            <Head title="Credit Patients" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                            Credit Patients
                        </h1>
                        <p className="text-gray-600 dark:text-gray-400">
                            Manage patients with credit account privileges
                        </p>
                    </div>
                </div>

                {/* Summary Stats */}
                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Credit Patients
                            </CardTitle>
                            <Users className="h-4 w-4 text-amber-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-amber-600">
                                {totalPatients}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Patients with credit privileges
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Total Owing
                            </CardTitle>
                            <DollarSign className="h-4 w-4 text-orange-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-orange-600">
                                {formatCurrency(totalOwing)}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Outstanding balance from credit patients
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Avg. Owing per Patient
                            </CardTitle>
                            <CreditCard className="h-4 w-4 text-blue-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-blue-600">
                                {formatCurrency(
                                    totalPatients > 0 ? totalOwing / totalPatients : 0,
                                )}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Average outstanding per patient
                            </p>
                        </CardContent>
                    </Card>
                </div>


                {/* Search */}
                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Search className="h-4 w-4" />
                            Search Credit Patients
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex gap-4">
                            <div className="flex-1">
                                <Input
                                    type="text"
                                    placeholder="Search by name, patient number, or phone..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    onKeyDown={handleKeyDown}
                                />
                            </div>
                            <Button onClick={handleSearch}>Search</Button>
                            {filters.search && (
                                <Button variant="outline" onClick={handleClearSearch}>
                                    Clear
                                </Button>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Credit Patients Table */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Star className="h-5 w-5 text-amber-600" />
                            Credit Patients List
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {patients.length > 0 ? (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Patient</TableHead>
                                        <TableHead>Patient Number</TableHead>
                                        <TableHead>Phone</TableHead>
                                        <TableHead>Credit Reason</TableHead>
                                        <TableHead>Authorized By</TableHead>
                                        <TableHead>Authorized At</TableHead>
                                        <TableHead className="text-right">
                                            Total Owing
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {patients.map((patient) => (
                                        <TableRow key={patient.id}>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <Badge
                                                        variant="outline"
                                                        className="border-amber-300 bg-amber-50 text-amber-700 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-300"
                                                    >
                                                        <Star className="mr-1 h-3 w-3" />
                                                        VIP
                                                    </Badge>
                                                    <span className="font-medium">
                                                        {patient.full_name}
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell className="font-mono text-sm">
                                                {patient.patient_number}
                                            </TableCell>
                                            <TableCell>
                                                {patient.phone_number || '-'}
                                            </TableCell>
                                            <TableCell>
                                                <span
                                                    className="max-w-[200px] truncate block"
                                                    title={patient.credit_reason || ''}
                                                >
                                                    {patient.credit_reason || '-'}
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                {patient.credit_authorized_by || '-'}
                                            </TableCell>
                                            <TableCell>
                                                {patient.credit_authorized_at || '-'}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {patient.total_owing > 0 ? (
                                                    <span className="font-medium text-orange-600">
                                                        {formatCurrency(patient.total_owing)}
                                                    </span>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        {formatCurrency(0)}
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => handleManageCredit(patient)}
                                                >
                                                    <Settings className="mr-1 h-4 w-4" />
                                                    Manage
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        ) : (
                            <div className="text-center py-12">
                                <Star className="mx-auto h-12 w-12 text-muted-foreground/50" />
                                <h3 className="mt-4 text-lg font-medium text-gray-900 dark:text-gray-100">
                                    No credit patients found
                                </h3>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    {filters.search
                                        ? 'No patients match your search criteria.'
                                        : 'No patients have been granted credit privileges yet.'}
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Manage Credit Modal */}
            {selectedPatient && (
                <ManageCreditModal
                    isOpen={isManageModalOpen}
                    onClose={handleCloseModal}
                    patientId={selectedPatient.id}
                    patientName={selectedPatient.full_name}
                    isCreditEligible={selectedPatient.is_credit_eligible}
                    totalOwing={selectedPatient.total_owing}
                    formatCurrency={formatCurrency}
                />
            )}
        </AppLayout>
    );
}
