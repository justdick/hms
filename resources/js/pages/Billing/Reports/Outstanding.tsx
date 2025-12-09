import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { StatCard } from '@/components/ui/stat-card';
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
    AlertCircle,
    Calendar,
    Download,
    FileSpreadsheet,
    FileText,
    Filter,
    Users,
} from 'lucide-react';
import { useState } from 'react';

interface Department {
    id: number;
    name: string;
}

interface AgingBuckets {
    current: number;
    days_30: number;
    days_60: number;
    days_90_plus: number;
}

interface ServiceBreakdown {
    [key: string]: {
        count: number;
        amount: number;
    };
}

interface PatientBalance {
    patient_id: number;
    patient_name: string;
    patient_number: string;
    has_insurance: boolean;
    insurance_provider: string | null;
    total_outstanding: number;
    charge_count: number;
    aging: AgingBuckets;
    service_breakdown: ServiceBreakdown;
    oldest_charge_date: string | null;
    departments: string[];
}

interface Summary {
    total_outstanding: number;
    patient_count: number;
    charge_count: number;
    aging_totals: AgingBuckets;
    insured_count: number;
    uninsured_count: number;
}

interface Filters {
    department_id: string;
    has_insurance: string;
    min_amount: string;
    max_amount: string;
}

interface Props {
    balances: PatientBalance[];
    summary: Summary;
    departments: Department[];
    filters: Filters;
}

export default function OutstandingReport({
    balances,
    summary,
    departments,
    filters,
}: Props) {
    const [localFilters, setLocalFilters] = useState<Filters>(filters);
    const [expandedPatient, setExpandedPatient] = useState<number | null>(null);

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-GH', {
            style: 'currency',
            currency: 'GHS',
        }).format(amount);
    };

    const handleFilterChange = (key: keyof Filters, value: string) => {
        setLocalFilters((prev) => ({ ...prev, [key]: value }));
    };

    const applyFilters = () => {
        const queryParams: Record<string, string> = {};
        if (localFilters.department_id)
            queryParams.department_id = localFilters.department_id;
        if (localFilters.has_insurance)
            queryParams.has_insurance = localFilters.has_insurance;
        if (localFilters.min_amount)
            queryParams.min_amount = localFilters.min_amount;
        if (localFilters.max_amount)
            queryParams.max_amount = localFilters.max_amount;

        router.get('/billing/accounts/reports/outstanding', queryParams, {
            preserveState: true,
        });
    };

    const clearFilters = () => {
        setLocalFilters({
            department_id: '',
            has_insurance: '',
            min_amount: '',
            max_amount: '',
        });
        router.get('/billing/accounts/reports/outstanding', {}, { preserveState: true });
    };

    const exportToExcel = () => {
        const queryParams = new URLSearchParams();
        if (localFilters.department_id)
            queryParams.append('department_id', localFilters.department_id);
        if (localFilters.has_insurance)
            queryParams.append('has_insurance', localFilters.has_insurance);
        if (localFilters.min_amount)
            queryParams.append('min_amount', localFilters.min_amount);
        if (localFilters.max_amount)
            queryParams.append('max_amount', localFilters.max_amount);

        window.location.href = `/billing/accounts/reports/outstanding/export/excel?${queryParams.toString()}`;
    };

    const exportToPdf = () => {
        const queryParams = new URLSearchParams();
        if (localFilters.department_id)
            queryParams.append('department_id', localFilters.department_id);
        if (localFilters.has_insurance)
            queryParams.append('has_insurance', localFilters.has_insurance);
        if (localFilters.min_amount)
            queryParams.append('min_amount', localFilters.min_amount);
        if (localFilters.max_amount)
            queryParams.append('max_amount', localFilters.max_amount);

        window.location.href = `/billing/accounts/reports/outstanding/export/pdf?${queryParams.toString()}`;
    };

    const toggleExpanded = (patientId: number) => {
        setExpandedPatient(expandedPatient === patientId ? null : patientId);
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Billing', href: '/billing' },
                { title: 'Accounts', href: '/billing/accounts' },
                { title: 'Outstanding Balances', href: '/billing/accounts/reports/outstanding' },
            ]}
        >
            <Head title="Outstanding Balances Report" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                            Outstanding Balances Report
                        </h1>
                        <p className="text-gray-600 dark:text-gray-400">
                            View and manage patient outstanding balances with aging analysis
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={exportToExcel}>
                            <FileSpreadsheet className="mr-2 h-4 w-4" />
                            Export Excel
                        </Button>
                        <Button variant="outline" onClick={exportToPdf}>
                            <FileText className="mr-2 h-4 w-4" />
                            Export PDF
                        </Button>
                    </div>
                </div>

                {/* Summary Cards */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        label={`Total Outstanding · ${summary.charge_count} charges`}
                        value={formatCurrency(summary.total_outstanding)}
                        icon={<AlertCircle className="h-4 w-4" />}
                        variant="error"
                    />
                    <StatCard
                        label={`Patients · ${summary.insured_count} insured`}
                        value={summary.patient_count}
                        icon={<Users className="h-4 w-4" />}
                        variant="info"
                    />
                    <StatCard
                        label="Current (0-30 days)"
                        value={formatCurrency(summary.aging_totals.current)}
                        icon={<Calendar className="h-4 w-4" />}
                        variant="success"
                    />
                    <StatCard
                        label="Overdue (90+ days)"
                        value={formatCurrency(summary.aging_totals.days_90_plus)}
                        icon={<AlertCircle className="h-4 w-4" />}
                        variant="warning"
                    />
                </div>

                {/* Aging Summary Bar */}
                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-base">Aging Distribution</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex h-4 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                            {summary.total_outstanding > 0 && (
                                <>
                                    <div
                                        className="bg-green-500 transition-all"
                                        style={{
                                            width: `${(summary.aging_totals.current / summary.total_outstanding) * 100}%`,
                                        }}
                                        title={`Current: ${formatCurrency(summary.aging_totals.current)}`}
                                    />
                                    <div
                                        className="bg-yellow-500 transition-all"
                                        style={{
                                            width: `${(summary.aging_totals.days_30 / summary.total_outstanding) * 100}%`,
                                        }}
                                        title={`31-60 days: ${formatCurrency(summary.aging_totals.days_30)}`}
                                    />
                                    <div
                                        className="bg-orange-500 transition-all"
                                        style={{
                                            width: `${(summary.aging_totals.days_60 / summary.total_outstanding) * 100}%`,
                                        }}
                                        title={`61-90 days: ${formatCurrency(summary.aging_totals.days_60)}`}
                                    />
                                    <div
                                        className="bg-red-500 transition-all"
                                        style={{
                                            width: `${(summary.aging_totals.days_90_plus / summary.total_outstanding) * 100}%`,
                                        }}
                                        title={`90+ days: ${formatCurrency(summary.aging_totals.days_90_plus)}`}
                                    />
                                </>
                            )}
                        </div>
                        <div className="mt-2 flex justify-between text-xs text-muted-foreground">
                            <div className="flex items-center gap-1">
                                <div className="h-2 w-2 rounded-full bg-green-500" />
                                Current: {formatCurrency(summary.aging_totals.current)}
                            </div>
                            <div className="flex items-center gap-1">
                                <div className="h-2 w-2 rounded-full bg-yellow-500" />
                                31-60: {formatCurrency(summary.aging_totals.days_30)}
                            </div>
                            <div className="flex items-center gap-1">
                                <div className="h-2 w-2 rounded-full bg-orange-500" />
                                61-90: {formatCurrency(summary.aging_totals.days_60)}
                            </div>
                            <div className="flex items-center gap-1">
                                <div className="h-2 w-2 rounded-full bg-red-500" />
                                90+: {formatCurrency(summary.aging_totals.days_90_plus)}
                            </div>
                        </div>
                    </CardContent>
                </Card>

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
                            <div className="min-w-[180px]">
                                <label className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Department
                                </label>
                                <Select
                                    value={localFilters.department_id || 'all'}
                                    onValueChange={(value) =>
                                        handleFilterChange('department_id', value === 'all' ? '' : value)
                                    }
                                >
                                    <SelectTrigger className="mt-1">
                                        <SelectValue placeholder="All Departments" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Departments</SelectItem>
                                        {departments.map((dept) => (
                                            <SelectItem
                                                key={dept.id}
                                                value={dept.id.toString()}
                                            >
                                                {dept.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="min-w-[180px]">
                                <label className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Insurance Status
                                </label>
                                <Select
                                    value={localFilters.has_insurance || 'all'}
                                    onValueChange={(value) =>
                                        handleFilterChange('has_insurance', value === 'all' ? '' : value)
                                    }
                                >
                                    <SelectTrigger className="mt-1">
                                        <SelectValue placeholder="All Patients" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Patients</SelectItem>
                                        <SelectItem value="true">Insured Only</SelectItem>
                                        <SelectItem value="false">Uninsured Only</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="min-w-[120px]">
                                <label className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Min Amount
                                </label>
                                <Input
                                    type="number"
                                    placeholder="0.00"
                                    value={localFilters.min_amount}
                                    onChange={(e) =>
                                        handleFilterChange('min_amount', e.target.value)
                                    }
                                    className="mt-1"
                                />
                            </div>

                            <div className="min-w-[120px]">
                                <label className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Max Amount
                                </label>
                                <Input
                                    type="number"
                                    placeholder="No limit"
                                    value={localFilters.max_amount}
                                    onChange={(e) =>
                                        handleFilterChange('max_amount', e.target.value)
                                    }
                                    className="mt-1"
                                />
                            </div>

                            <Button onClick={applyFilters}>Apply Filters</Button>
                            <Button variant="outline" onClick={clearFilters}>
                                Clear
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                {/* Outstanding Balances Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>Patient Outstanding Balances</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {balances.length > 0 ? (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Patient</TableHead>
                                        <TableHead>Insurance</TableHead>
                                        <TableHead className="text-right">Total</TableHead>
                                        <TableHead className="text-right bg-green-50 dark:bg-green-900/20">
                                            Current
                                        </TableHead>
                                        <TableHead className="text-right bg-yellow-50 dark:bg-yellow-900/20">
                                            31-60
                                        </TableHead>
                                        <TableHead className="text-right bg-orange-50 dark:bg-orange-900/20">
                                            61-90
                                        </TableHead>
                                        <TableHead className="text-right bg-red-50 dark:bg-red-900/20">
                                            90+
                                        </TableHead>
                                        <TableHead className="text-center">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {balances.map((balance) => (
                                        <>
                                            <TableRow
                                                key={balance.patient_id}
                                                className="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800"
                                                onClick={() => toggleExpanded(balance.patient_id)}
                                            >
                                                <TableCell>
                                                    <div>
                                                        <div className="font-medium">
                                                            {balance.patient_name}
                                                        </div>
                                                        <div className="text-xs text-muted-foreground">
                                                            {balance.patient_number}
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    {balance.has_insurance ? (
                                                        <Badge variant="outline" className="bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-300">
                                                            {balance.insurance_provider}
                                                        </Badge>
                                                    ) : (
                                                        <Badge variant="secondary">None</Badge>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right font-bold text-red-600">
                                                    {formatCurrency(balance.total_outstanding)}
                                                </TableCell>
                                                <TableCell className="text-right bg-green-50 dark:bg-green-900/20">
                                                    {formatCurrency(balance.aging.current)}
                                                </TableCell>
                                                <TableCell className="text-right bg-yellow-50 dark:bg-yellow-900/20">
                                                    {formatCurrency(balance.aging.days_30)}
                                                </TableCell>
                                                <TableCell className="text-right bg-orange-50 dark:bg-orange-900/20">
                                                    {formatCurrency(balance.aging.days_60)}
                                                </TableCell>
                                                <TableCell className="text-right bg-red-50 dark:bg-red-900/20">
                                                    {formatCurrency(balance.aging.days_90_plus)}
                                                </TableCell>
                                                <TableCell className="text-center">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={(e) => {
                                                            e.stopPropagation();
                                                            toggleExpanded(balance.patient_id);
                                                        }}
                                                    >
                                                        {expandedPatient === balance.patient_id
                                                            ? 'Hide'
                                                            : 'Details'}
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                            {expandedPatient === balance.patient_id && (
                                                <TableRow>
                                                    <TableCell colSpan={8} className="bg-gray-50 dark:bg-gray-800/50">
                                                        <div className="p-4">
                                                            <h4 className="font-medium mb-2">
                                                                Service Breakdown
                                                            </h4>
                                                            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                                                                {Object.entries(balance.service_breakdown).map(
                                                                    ([type, data]) => (
                                                                        <div
                                                                            key={type}
                                                                            className="bg-white dark:bg-gray-900 p-3 rounded-lg border"
                                                                        >
                                                                            <div className="text-xs text-muted-foreground capitalize">
                                                                                {type.replace('_', ' ')}
                                                                            </div>
                                                                            <div className="font-medium">
                                                                                {formatCurrency(data.amount)}
                                                                            </div>
                                                                            <div className="text-xs text-muted-foreground">
                                                                                {data.count} charge(s)
                                                                            </div>
                                                                        </div>
                                                                    )
                                                                )}
                                                            </div>
                                                            {balance.departments.length > 0 && (
                                                                <div className="mt-3">
                                                                    <span className="text-sm text-muted-foreground">
                                                                        Departments:{' '}
                                                                    </span>
                                                                    {balance.departments.map((dept, idx) => (
                                                                        <Badge
                                                                            key={idx}
                                                                            variant="outline"
                                                                            className="mr-1"
                                                                        >
                                                                            {dept}
                                                                        </Badge>
                                                                    ))}
                                                                </div>
                                                            )}
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            )}
                                        </>
                                    ))}
                                </TableBody>
                            </Table>
                        ) : (
                            <div className="text-center py-12 text-muted-foreground">
                                <AlertCircle className="mx-auto h-12 w-12 mb-4 opacity-50" />
                                <p className="text-lg font-medium">No outstanding balances found</p>
                                <p className="text-sm">
                                    All patients have paid their charges or no charges match the filters.
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
