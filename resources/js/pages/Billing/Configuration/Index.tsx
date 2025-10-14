import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    Building2,
    CheckCircle,
    Edit3,
    Plus,
    Settings,
    Shield,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';

interface Department {
    id: number;
    name: string;
    code: string;
}

interface DepartmentBilling {
    id: number;
    department_code: string;
    department_name: string;
    consultation_fee: number;
    equipment_fee: number;
    emergency_surcharge: number;
    payment_required_before_consultation: boolean;
    emergency_override_allowed: boolean;
    payment_grace_period_minutes: number;
    allow_partial_payment: boolean;
    payment_plan_available: boolean;
    is_active: boolean;
}

interface ServiceChargeRule {
    id: number;
    service_type: string;
    service_code?: string;
    service_name: string;
    charge_timing: string;
    payment_required: string;
    payment_timing: string;
    emergency_override_allowed: boolean;
    partial_payment_allowed: boolean;
    service_blocking_enabled: boolean;
    hide_details_until_paid: boolean;
    is_active: boolean;
}

interface BillingConfiguration {
    id: number;
    key: string;
    category: string;
    value: any;
    description: string;
    is_active: boolean;
}

interface Props {
    systemConfig: Record<string, BillingConfiguration[]>;
    departmentBilling: DepartmentBilling[];
    serviceRules: Record<string, ServiceChargeRule[]>;
    departments: Department[];
}

export default function BillingConfigurationIndex({
    systemConfig,
    departmentBilling,
    serviceRules,
    departments,
}: Props) {
    const [editingDepartment, setEditingDepartment] =
        useState<DepartmentBilling | null>(null);
    const [editingService, setEditingService] =
        useState<ServiceChargeRule | null>(null);
    const [showAddDepartment, setShowAddDepartment] = useState(false);
    const [showAddService, setShowAddService] = useState(false);

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-GH', {
            style: 'currency',
            currency: 'GHS',
        }).format(amount);
    };

    const getStatusBadge = (isActive: boolean) => {
        return isActive ? (
            <Badge
                variant="outline"
                className="border-green-200 bg-green-100 text-green-700"
            >
                <CheckCircle className="mr-1 h-3 w-3" />
                Active
            </Badge>
        ) : (
            <Badge
                variant="outline"
                className="border-gray-200 bg-gray-100 text-gray-700"
            >
                <XCircle className="mr-1 h-3 w-3" />
                Inactive
            </Badge>
        );
    };

    const handleDepartmentUpdate = (
        department: DepartmentBilling,
        data: any,
    ) => {
        router.put(`/billing/configuration/department/${department.id}`, data, {
            onSuccess: () => setEditingDepartment(null),
        });
    };

    const handleServiceUpdate = (service: ServiceChargeRule, data: any) => {
        router.put(`/billing/configuration/service-rule/${service.id}`, data, {
            onSuccess: () => setEditingService(null),
        });
    };

    const handleAddDepartment = (data: any) => {
        router.post('/billing/configuration/department', data, {
            onSuccess: () => setShowAddDepartment(false),
        });
    };

    const handleAddService = (data: any) => {
        router.post('/billing/configuration/service-rule', data, {
            onSuccess: () => setShowAddService(false),
        });
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Billing', href: '/billing' },
                { title: 'Configuration', href: '' },
            ]}
        >
            <Head title="Billing Configuration" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">
                            Billing Configuration
                        </h1>
                        <p className="text-gray-600">
                            Manage billing rules, department fees, and payment
                            enforcement
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <Button variant="outline">
                            <Settings className="mr-2 h-4 w-4" />
                            System Settings
                        </Button>
                    </div>
                </div>

                {/* Configuration Tabs */}
                <Tabs defaultValue="departments" className="space-y-6">
                    <TabsList className="grid w-full grid-cols-3">
                        <TabsTrigger
                            value="departments"
                            className="flex items-center gap-2"
                        >
                            <Building2 className="h-4 w-4" />
                            Department Billing
                        </TabsTrigger>
                        <TabsTrigger
                            value="services"
                            className="flex items-center gap-2"
                        >
                            <Shield className="h-4 w-4" />
                            Service Rules
                        </TabsTrigger>
                        <TabsTrigger
                            value="system"
                            className="flex items-center gap-2"
                        >
                            <Settings className="h-4 w-4" />
                            System Config
                        </TabsTrigger>
                    </TabsList>

                    {/* Department Billing Configuration */}
                    <TabsContent value="departments" className="space-y-6">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <CardTitle>
                                    Department Billing Configuration
                                </CardTitle>
                                <Button
                                    onClick={() => setShowAddDepartment(true)}
                                >
                                    <Plus className="mr-2 h-4 w-4" />
                                    Add Department
                                </Button>
                            </CardHeader>
                            <CardContent>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Department</TableHead>
                                            <TableHead>
                                                Consultation Fee
                                            </TableHead>
                                            <TableHead>Equipment Fee</TableHead>
                                            <TableHead>
                                                Emergency Surcharge
                                            </TableHead>
                                            <TableHead>
                                                Payment Required
                                            </TableHead>
                                            <TableHead>
                                                Emergency Override
                                            </TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {departmentBilling.map((dept) => (
                                            <TableRow key={dept.id}>
                                                <TableCell>
                                                    <div>
                                                        <div className="font-medium">
                                                            {
                                                                dept.department_name
                                                            }
                                                        </div>
                                                        <div className="text-sm text-gray-500">
                                                            {
                                                                dept.department_code
                                                            }
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="font-medium">
                                                    {formatCurrency(
                                                        dept.consultation_fee,
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {formatCurrency(
                                                        dept.equipment_fee || 0,
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {formatCurrency(
                                                        dept.emergency_surcharge ||
                                                            0,
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {dept.payment_required_before_consultation ? (
                                                        <Badge
                                                            variant="outline"
                                                            className="bg-red-100 text-red-700"
                                                        >
                                                            <AlertTriangle className="mr-1 h-3 w-3" />
                                                            Required
                                                        </Badge>
                                                    ) : (
                                                        <Badge
                                                            variant="outline"
                                                            className="bg-green-100 text-green-700"
                                                        >
                                                            <CheckCircle className="mr-1 h-3 w-3" />
                                                            Optional
                                                        </Badge>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {dept.emergency_override_allowed ? (
                                                        <Badge
                                                            variant="outline"
                                                            className="bg-orange-100 text-orange-700"
                                                        >
                                                            Allowed
                                                        </Badge>
                                                    ) : (
                                                        <Badge
                                                            variant="outline"
                                                            className="bg-gray-100 text-gray-700"
                                                        >
                                                            Disabled
                                                        </Badge>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {getStatusBadge(
                                                        dept.is_active,
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            setEditingDepartment(
                                                                dept,
                                                            )
                                                        }
                                                    >
                                                        <Edit3 className="h-4 w-4" />
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Service Rules Configuration */}
                    <TabsContent value="services" className="space-y-6">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <CardTitle>Service Charge Rules</CardTitle>
                                <Button onClick={() => setShowAddService(true)}>
                                    <Plus className="mr-2 h-4 w-4" />
                                    Add Service Rule
                                </Button>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-6">
                                    {Object.entries(serviceRules).map(
                                        ([serviceType, rules]) => (
                                            <div
                                                key={serviceType}
                                                className="space-y-3"
                                            >
                                                <h3 className="text-lg font-medium capitalize">
                                                    {serviceType} Services
                                                </h3>
                                                <Table>
                                                    <TableHeader>
                                                        <TableRow>
                                                            <TableHead>
                                                                Service
                                                            </TableHead>
                                                            <TableHead>
                                                                Charge Timing
                                                            </TableHead>
                                                            <TableHead>
                                                                Payment Required
                                                            </TableHead>
                                                            <TableHead>
                                                                Service Blocking
                                                            </TableHead>
                                                            <TableHead>
                                                                Hide Details
                                                            </TableHead>
                                                            <TableHead>
                                                                Emergency
                                                                Override
                                                            </TableHead>
                                                            <TableHead>
                                                                Status
                                                            </TableHead>
                                                            <TableHead>
                                                                Actions
                                                            </TableHead>
                                                        </TableRow>
                                                    </TableHeader>
                                                    <TableBody>
                                                        {rules.map((rule) => (
                                                            <TableRow
                                                                key={rule.id}
                                                            >
                                                                <TableCell>
                                                                    <div>
                                                                        <div className="font-medium">
                                                                            {
                                                                                rule.service_name
                                                                            }
                                                                        </div>
                                                                        {rule.service_code && (
                                                                            <div className="text-sm text-gray-500">
                                                                                {
                                                                                    rule.service_code
                                                                                }
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                </TableCell>
                                                                <TableCell>
                                                                    <Badge variant="outline">
                                                                        {rule.charge_timing.replace(
                                                                            '_',
                                                                            ' ',
                                                                        )}
                                                                    </Badge>
                                                                </TableCell>
                                                                <TableCell>
                                                                    <Badge
                                                                        variant="outline"
                                                                        className={
                                                                            rule.payment_required ===
                                                                            'mandatory'
                                                                                ? 'bg-red-100 text-red-700'
                                                                                : rule.payment_required ===
                                                                                    'optional'
                                                                                  ? 'bg-green-100 text-green-700'
                                                                                  : 'bg-yellow-100 text-yellow-700'
                                                                        }
                                                                    >
                                                                        {
                                                                            rule.payment_required
                                                                        }
                                                                    </Badge>
                                                                </TableCell>
                                                                <TableCell>
                                                                    {rule.service_blocking_enabled ? (
                                                                        <Badge
                                                                            variant="outline"
                                                                            className="bg-red-100 text-red-700"
                                                                        >
                                                                            <XCircle className="mr-1 h-3 w-3" />
                                                                            Enabled
                                                                        </Badge>
                                                                    ) : (
                                                                        <Badge
                                                                            variant="outline"
                                                                            className="bg-green-100 text-green-700"
                                                                        >
                                                                            <CheckCircle className="mr-1 h-3 w-3" />
                                                                            Disabled
                                                                        </Badge>
                                                                    )}
                                                                </TableCell>
                                                                <TableCell>
                                                                    {rule.hide_details_until_paid ? (
                                                                        <Badge
                                                                            variant="outline"
                                                                            className="bg-orange-100 text-orange-700"
                                                                        >
                                                                            Yes
                                                                        </Badge>
                                                                    ) : (
                                                                        <Badge
                                                                            variant="outline"
                                                                            className="bg-gray-100 text-gray-700"
                                                                        >
                                                                            No
                                                                        </Badge>
                                                                    )}
                                                                </TableCell>
                                                                <TableCell>
                                                                    {rule.emergency_override_allowed ? (
                                                                        <Badge
                                                                            variant="outline"
                                                                            className="bg-orange-100 text-orange-700"
                                                                        >
                                                                            Allowed
                                                                        </Badge>
                                                                    ) : (
                                                                        <Badge
                                                                            variant="outline"
                                                                            className="bg-gray-100 text-gray-700"
                                                                        >
                                                                            Disabled
                                                                        </Badge>
                                                                    )}
                                                                </TableCell>
                                                                <TableCell>
                                                                    {getStatusBadge(
                                                                        rule.is_active,
                                                                    )}
                                                                </TableCell>
                                                                <TableCell>
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        onClick={() =>
                                                                            setEditingService(
                                                                                rule,
                                                                            )
                                                                        }
                                                                    >
                                                                        <Edit3 className="h-4 w-4" />
                                                                    </Button>
                                                                </TableCell>
                                                            </TableRow>
                                                        ))}
                                                    </TableBody>
                                                </Table>
                                            </div>
                                        ),
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* System Configuration */}
                    <TabsContent value="system" className="space-y-6">
                        <div className="grid gap-6 md:grid-cols-2">
                            {Object.entries(systemConfig).map(
                                ([category, configs]) => (
                                    <Card key={category}>
                                        <CardHeader>
                                            <CardTitle className="capitalize">
                                                {category} Settings
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-4">
                                            {configs.map((config) => (
                                                <div
                                                    key={config.id}
                                                    className="space-y-2"
                                                >
                                                    <Label className="text-sm font-medium">
                                                        {config.key.replace(
                                                            '_',
                                                            ' ',
                                                        )}
                                                    </Label>
                                                    <div className="text-sm text-gray-600">
                                                        {config.description}
                                                    </div>
                                                    <div className="text-sm">
                                                        Current:{' '}
                                                        <span className="font-medium">
                                                            {JSON.stringify(
                                                                config.value,
                                                            )}
                                                        </span>
                                                    </div>
                                                </div>
                                            ))}
                                        </CardContent>
                                    </Card>
                                ),
                            )}
                        </div>
                    </TabsContent>
                </Tabs>
            </div>

            {/* Add Department Modal */}
            <Dialog
                open={showAddDepartment}
                onOpenChange={setShowAddDepartment}
            >
                <DialogContent className="sm:max-w-[425px]">
                    <DialogHeader>
                        <DialogTitle>
                            Add Department Billing Configuration
                        </DialogTitle>
                        <DialogDescription>
                            Set up billing configuration for a new department.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            const formData = new FormData(e.currentTarget);
                            const data = {
                                department_code:
                                    formData.get('department_code'),
                                department_name:
                                    formData.get('department_name'),
                                consultation_fee: parseFloat(
                                    formData.get('consultation_fee') as string,
                                ),
                                equipment_fee:
                                    parseFloat(
                                        formData.get('equipment_fee') as string,
                                    ) || 0,
                                emergency_surcharge:
                                    parseFloat(
                                        formData.get(
                                            'emergency_surcharge',
                                        ) as string,
                                    ) || 0,
                                payment_required_before_consultation:
                                    formData.get(
                                        'payment_required_before_consultation',
                                    ) === 'on',
                                emergency_override_allowed:
                                    formData.get(
                                        'emergency_override_allowed',
                                    ) === 'on',
                                payment_grace_period_minutes:
                                    parseInt(
                                        formData.get(
                                            'payment_grace_period_minutes',
                                        ) as string,
                                    ) || 30,
                                allow_partial_payment:
                                    formData.get('allow_partial_payment') ===
                                    'on',
                                payment_plan_available:
                                    formData.get('payment_plan_available') ===
                                    'on',
                            };
                            handleAddDepartment(data);
                        }}
                    >
                        <div className="grid gap-4 py-4">
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label
                                    htmlFor="department_code"
                                    className="text-right"
                                >
                                    Code
                                </Label>
                                <Input
                                    id="department_code"
                                    name="department_code"
                                    placeholder="e.g., CARDIO"
                                    className="col-span-3"
                                    required
                                />
                            </div>
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label
                                    htmlFor="department_name"
                                    className="text-right"
                                >
                                    Name
                                </Label>
                                <Input
                                    id="department_name"
                                    name="department_name"
                                    placeholder="e.g., Cardiology"
                                    className="col-span-3"
                                    required
                                />
                            </div>
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label
                                    htmlFor="consultation_fee"
                                    className="text-right"
                                >
                                    Consultation Fee
                                </Label>
                                <Input
                                    id="consultation_fee"
                                    name="consultation_fee"
                                    type="number"
                                    step="0.01"
                                    placeholder="50.00"
                                    className="col-span-3"
                                    required
                                />
                            </div>
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label
                                    htmlFor="equipment_fee"
                                    className="text-right"
                                >
                                    Equipment Fee
                                </Label>
                                <Input
                                    id="equipment_fee"
                                    name="equipment_fee"
                                    type="number"
                                    step="0.01"
                                    placeholder="10.00"
                                    className="col-span-3"
                                />
                            </div>
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label
                                    htmlFor="emergency_surcharge"
                                    className="text-right"
                                >
                                    Emergency Surcharge
                                </Label>
                                <Input
                                    id="emergency_surcharge"
                                    name="emergency_surcharge"
                                    type="number"
                                    step="0.01"
                                    placeholder="25.00"
                                    className="col-span-3"
                                />
                            </div>
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label
                                    htmlFor="payment_grace_period_minutes"
                                    className="text-right"
                                >
                                    Grace Period (min)
                                </Label>
                                <Input
                                    id="payment_grace_period_minutes"
                                    name="payment_grace_period_minutes"
                                    type="number"
                                    placeholder="30"
                                    className="col-span-3"
                                />
                            </div>
                            <div className="space-y-3">
                                <div className="flex items-center space-x-2">
                                    <input
                                        type="checkbox"
                                        id="payment_required_before_consultation"
                                        name="payment_required_before_consultation"
                                        className="rounded"
                                    />
                                    <Label htmlFor="payment_required_before_consultation">
                                        Payment required before consultation
                                    </Label>
                                </div>
                                <div className="flex items-center space-x-2">
                                    <input
                                        type="checkbox"
                                        id="emergency_override_allowed"
                                        name="emergency_override_allowed"
                                        className="rounded"
                                    />
                                    <Label htmlFor="emergency_override_allowed">
                                        Emergency override allowed
                                    </Label>
                                </div>
                                <div className="flex items-center space-x-2">
                                    <input
                                        type="checkbox"
                                        id="allow_partial_payment"
                                        name="allow_partial_payment"
                                        className="rounded"
                                    />
                                    <Label htmlFor="allow_partial_payment">
                                        Allow partial payment
                                    </Label>
                                </div>
                                <div className="flex items-center space-x-2">
                                    <input
                                        type="checkbox"
                                        id="payment_plan_available"
                                        name="payment_plan_available"
                                        className="rounded"
                                    />
                                    <Label htmlFor="payment_plan_available">
                                        Payment plan available
                                    </Label>
                                </div>
                            </div>
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setShowAddDepartment(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit">Add Department</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Add Service Rule Modal */}
            <Dialog open={showAddService} onOpenChange={setShowAddService}>
                <DialogContent className="sm:max-w-[500px]">
                    <DialogHeader>
                        <DialogTitle>Add Service Charge Rule</DialogTitle>
                        <DialogDescription>
                            Configure billing rules for a new service type.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            const formData = new FormData(e.currentTarget);
                            const data = {
                                service_type: formData.get('service_type'),
                                service_code:
                                    formData.get('service_code') || null,
                                service_name: formData.get('service_name'),
                                charge_timing: formData.get('charge_timing'),
                                payment_required:
                                    formData.get('payment_required'),
                                payment_timing: formData.get('payment_timing'),
                                emergency_override_allowed:
                                    formData.get(
                                        'emergency_override_allowed',
                                    ) === 'on',
                                partial_payment_allowed:
                                    formData.get('partial_payment_allowed') ===
                                    'on',
                                payment_plans_available:
                                    formData.get('payment_plans_available') ===
                                    'on',
                                grace_period_days:
                                    parseInt(
                                        formData.get(
                                            'grace_period_days',
                                        ) as string,
                                    ) || 0,
                                service_blocking_enabled:
                                    formData.get('service_blocking_enabled') ===
                                    'on',
                                hide_details_until_paid:
                                    formData.get('hide_details_until_paid') ===
                                    'on',
                            };
                            handleAddService(data);
                        }}
                    >
                        <div className="grid gap-4 py-4">
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label
                                    htmlFor="service_type"
                                    className="text-right"
                                >
                                    Service Type
                                </Label>
                                <Select name="service_type" required>
                                    <SelectTrigger className="col-span-3">
                                        <SelectValue placeholder="Select service type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="consultation">
                                            Consultation
                                        </SelectItem>
                                        <SelectItem value="laboratory">
                                            Laboratory
                                        </SelectItem>
                                        <SelectItem value="pharmacy">
                                            Pharmacy
                                        </SelectItem>
                                        <SelectItem value="ward">
                                            Ward
                                        </SelectItem>
                                        <SelectItem value="procedure">
                                            Procedure
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label
                                    htmlFor="service_code"
                                    className="text-right"
                                >
                                    Service Code
                                </Label>
                                <Input
                                    id="service_code"
                                    name="service_code"
                                    placeholder="Optional service code"
                                    className="col-span-3"
                                />
                            </div>
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label
                                    htmlFor="service_name"
                                    className="text-right"
                                >
                                    Service Name
                                </Label>
                                <Input
                                    id="service_name"
                                    name="service_name"
                                    placeholder="e.g., X-Ray Service"
                                    className="col-span-3"
                                    required
                                />
                            </div>
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label
                                    htmlFor="charge_timing"
                                    className="text-right"
                                >
                                    Charge Timing
                                </Label>
                                <Select name="charge_timing" required>
                                    <SelectTrigger className="col-span-3">
                                        <SelectValue placeholder="When to charge" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="on_checkin">
                                            On Check-in
                                        </SelectItem>
                                        <SelectItem value="before_service">
                                            Before Service
                                        </SelectItem>
                                        <SelectItem value="after_service">
                                            After Service
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label
                                    htmlFor="payment_required"
                                    className="text-right"
                                >
                                    Payment Required
                                </Label>
                                <Select name="payment_required" required>
                                    <SelectTrigger className="col-span-3">
                                        <SelectValue placeholder="Payment requirement" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="mandatory">
                                            Mandatory
                                        </SelectItem>
                                        <SelectItem value="optional">
                                            Optional
                                        </SelectItem>
                                        <SelectItem value="deferred">
                                            Deferred
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label
                                    htmlFor="payment_timing"
                                    className="text-right"
                                >
                                    Payment Timing
                                </Label>
                                <Select name="payment_timing" required>
                                    <SelectTrigger className="col-span-3">
                                        <SelectValue placeholder="When payment is due" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="immediate">
                                            Immediate
                                        </SelectItem>
                                        <SelectItem value="before_service">
                                            Before Service
                                        </SelectItem>
                                        <SelectItem value="after_service">
                                            After Service
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label
                                    htmlFor="grace_period_days"
                                    className="text-right"
                                >
                                    Grace Period (days)
                                </Label>
                                <Input
                                    id="grace_period_days"
                                    name="grace_period_days"
                                    type="number"
                                    placeholder="0"
                                    className="col-span-3"
                                />
                            </div>
                            <div className="space-y-3">
                                <div className="flex items-center space-x-2">
                                    <input
                                        type="checkbox"
                                        id="emergency_override_allowed"
                                        name="emergency_override_allowed"
                                        className="rounded"
                                    />
                                    <Label htmlFor="emergency_override_allowed">
                                        Emergency override allowed
                                    </Label>
                                </div>
                                <div className="flex items-center space-x-2">
                                    <input
                                        type="checkbox"
                                        id="partial_payment_allowed"
                                        name="partial_payment_allowed"
                                        className="rounded"
                                    />
                                    <Label htmlFor="partial_payment_allowed">
                                        Partial payment allowed
                                    </Label>
                                </div>
                                <div className="flex items-center space-x-2">
                                    <input
                                        type="checkbox"
                                        id="payment_plans_available"
                                        name="payment_plans_available"
                                        className="rounded"
                                    />
                                    <Label htmlFor="payment_plans_available">
                                        Payment plans available
                                    </Label>
                                </div>
                                <div className="flex items-center space-x-2">
                                    <input
                                        type="checkbox"
                                        id="service_blocking_enabled"
                                        name="service_blocking_enabled"
                                        className="rounded"
                                    />
                                    <Label htmlFor="service_blocking_enabled">
                                        Service blocking enabled
                                    </Label>
                                </div>
                                <div className="flex items-center space-x-2">
                                    <input
                                        type="checkbox"
                                        id="hide_details_until_paid"
                                        name="hide_details_until_paid"
                                        className="rounded"
                                    />
                                    <Label htmlFor="hide_details_until_paid">
                                        Hide details until paid
                                    </Label>
                                </div>
                            </div>
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setShowAddService(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit">Add Service Rule</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
