import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Switch } from '@/components/ui/switch';
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
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    Bed,
    Building2,
    CheckCircle,
    Edit3,
    ExternalLink,
    Info,
    Loader2,
    Plus,
    Settings,
    Shield,
    Trash2,
    XCircle,
} from 'lucide-react';
import { useEffect, useState } from 'react';

const SERVICE_TYPES = [
    { value: 'consultation', label: 'Consultation' },
    { value: 'laboratory', label: 'Laboratory' },
    { value: 'pharmacy', label: 'Pharmacy' },
    { value: 'ward', label: 'Ward' },
    { value: 'procedure', label: 'Procedure' },
    { value: 'radiology', label: 'Radiology' },
] as const;

interface DepartmentBilling {
    id: number;
    department_id: number | null;
    department_code: string;
    department_name: string;
    consultation_fee: number;
    equipment_fee: number;
    emergency_surcharge: number;
    payment_required_before_consultation: boolean;
    emergency_override_allowed: boolean;
    payment_grace_period_minutes: number;
    is_active: boolean;
}

interface Department {
    id: number;
    name: string;
    code: string;
    type: string;
    billing: DepartmentBilling | null;
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
    payment_plans_available: boolean;
    grace_period_days: number;
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

interface WardBillingTemplate {
    id: number;
    service_name: string;
    service_code: string;
    description: string | null;
    billing_type:
        | 'one_time'
        | 'daily'
        | 'hourly'
        | 'percentage'
        | 'quantity_based'
        | 'event_triggered';
    base_amount: number;
    nhis_amount: number | null;
    effective_from: string;
    effective_to: string | null;
    is_active: boolean;
}

interface Ward {
    id: number;
    name: string;
    code: string;
}

interface Props {
    systemConfig: Record<string, BillingConfiguration[]>;
    departments: Department[];
    serviceRules: Record<string, ServiceChargeRule[]>;
    wardBillingTemplates: WardBillingTemplate[];
    wards: Ward[];
}

export default function BillingConfigurationIndex({
    systemConfig,
    departments,
    serviceRules,
    wardBillingTemplates,
    wards,
}: Props) {
    const [editingDepartment, setEditingDepartment] =
        useState<Department | null>(null);
    const [editingService, setEditingService] =
        useState<ServiceChargeRule | null>(null);
    const [configureServiceType, setConfigureServiceType] = useState<
        string | null
    >(null);
    const [editingConfig, setEditingConfig] =
        useState<BillingConfiguration | null>(null);
    const [selectedDepartments, setSelectedDepartments] = useState<number[]>(
        [],
    );
    const [showBulkConfig, setShowBulkConfig] = useState(false);
    const [editingWardBilling, setEditingWardBilling] =
        useState<WardBillingTemplate | null>(null);
    const [showCreateWardBilling, setShowCreateWardBilling] = useState(false);

    const toggleDepartmentSelection = (deptId: number) => {
        setSelectedDepartments((prev) =>
            prev.includes(deptId)
                ? prev.filter((id) => id !== deptId)
                : [...prev, deptId],
        );
    };

    const toggleAllDepartments = () => {
        if (selectedDepartments.length === departments.length) {
            setSelectedDepartments([]);
        } else {
            setSelectedDepartments(departments.map((d) => d.id));
        }
    };

    const formatCurrency = (amount: number | null | undefined) => {
        if (amount === null || amount === undefined) return '-';
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

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Billing', href: '/billing' },
                { title: 'Configuration', href: '' },
            ]}
        >
            <Head title="Billing Configuration" />

            <div className="space-y-6">
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
                </div>

                <Tabs defaultValue="departments" className="space-y-6">
                    <TabsList className="grid w-full grid-cols-4">
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
                            value="ward-billing"
                            className="flex items-center gap-2"
                        >
                            <Bed className="h-4 w-4" />
                            Ward Billing
                        </TabsTrigger>
                        <TabsTrigger
                            value="system"
                            className="flex items-center gap-2"
                        >
                            <Settings className="h-4 w-4" />
                            System Config
                        </TabsTrigger>
                    </TabsList>

                    {/* Department Billing Configuration - All Departments View */}
                    <TabsContent value="departments" className="space-y-6">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <div>
                                    <CardTitle>
                                        Department Billing Configuration
                                    </CardTitle>
                                    <p className="text-sm text-muted-foreground">
                                        Configure consultation fees and billing
                                        rules for each department
                                    </p>
                                </div>
                                {selectedDepartments.length > 0 && (
                                    <Button
                                        onClick={() => setShowBulkConfig(true)}
                                    >
                                        <Settings className="mr-2 h-4 w-4" />
                                        Configure {
                                            selectedDepartments.length
                                        }{' '}
                                        Selected
                                    </Button>
                                )}
                            </CardHeader>
                            <CardContent>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-12">
                                                <Checkbox
                                                    checked={
                                                        selectedDepartments.length ===
                                                            departments.length &&
                                                        departments.length > 0
                                                    }
                                                    onCheckedChange={
                                                        toggleAllDepartments
                                                    }
                                                />
                                            </TableHead>
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
                                            <TableHead>Status</TableHead>
                                            <TableHead>Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {departments.map((dept) => (
                                            <TableRow key={dept.id}>
                                                <TableCell>
                                                    <Checkbox
                                                        checked={selectedDepartments.includes(
                                                            dept.id,
                                                        )}
                                                        onCheckedChange={() =>
                                                            toggleDepartmentSelection(
                                                                dept.id,
                                                            )
                                                        }
                                                    />
                                                </TableCell>
                                                <TableCell>
                                                    <div>
                                                        <div className="font-medium">
                                                            {dept.name}
                                                        </div>
                                                        <div className="text-sm text-gray-500">
                                                            {dept.code}
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="font-medium">
                                                    {dept.billing ? (
                                                        formatCurrency(
                                                            dept.billing
                                                                .consultation_fee,
                                                        )
                                                    ) : (
                                                        <span className="text-gray-400">
                                                            Not configured
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {dept.billing
                                                        ? formatCurrency(
                                                              dept.billing
                                                                  .equipment_fee ||
                                                                  0,
                                                          )
                                                        : '-'}
                                                </TableCell>
                                                <TableCell>
                                                    {dept.billing
                                                        ? formatCurrency(
                                                              dept.billing
                                                                  .emergency_surcharge ||
                                                                  0,
                                                          )
                                                        : '-'}
                                                </TableCell>
                                                <TableCell>
                                                    {dept.billing ? (
                                                        dept.billing
                                                            .payment_required_before_consultation ? (
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
                                                        )
                                                    ) : (
                                                        '-'
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {dept.billing ? (
                                                        getStatusBadge(
                                                            dept.billing
                                                                .is_active,
                                                        )
                                                    ) : (
                                                        <Badge
                                                            variant="outline"
                                                            className="border-yellow-200 bg-yellow-50 text-yellow-700"
                                                        >
                                                            Not Configured
                                                        </Badge>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <Button
                                                        variant={
                                                            dept.billing
                                                                ? 'ghost'
                                                                : 'outline'
                                                        }
                                                        size="sm"
                                                        onClick={() =>
                                                            setEditingDepartment(
                                                                dept,
                                                            )
                                                        }
                                                    >
                                                        {dept.billing ? (
                                                            <Edit3 className="h-4 w-4" />
                                                        ) : (
                                                            <>
                                                                <Plus className="mr-1 h-4 w-4" />
                                                                Configure
                                                            </>
                                                        )}
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
                            <CardHeader>
                                <CardTitle>Service Charge Rules</CardTitle>
                                <p className="text-sm text-muted-foreground">
                                    Configure payment requirements for each
                                    service type
                                </p>
                            </CardHeader>
                            <CardContent>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Service Type</TableHead>
                                            <TableHead>
                                                Payment Before Service
                                            </TableHead>
                                            <TableHead>
                                                Block If Unpaid
                                            </TableHead>
                                            <TableHead>
                                                Emergency Override
                                            </TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {SERVICE_TYPES.map((serviceType) => {
                                            const rule =
                                                serviceRules[
                                                    serviceType.value
                                                ]?.[0];
                                            return (
                                                <TableRow
                                                    key={serviceType.value}
                                                >
                                                    <TableCell>
                                                        <div className="font-medium">
                                                            {serviceType.label}
                                                        </div>
                                                    </TableCell>
                                                    <TableCell>
                                                        {rule ? (
                                                            rule.payment_required ===
                                                            'mandatory' ? (
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
                                                            )
                                                        ) : (
                                                            <span className="text-gray-400">
                                                                -
                                                            </span>
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        {rule ? (
                                                            rule.service_blocking_enabled ? (
                                                                <Badge
                                                                    variant="outline"
                                                                    className="bg-red-100 text-red-700"
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
                                                            )
                                                        ) : (
                                                            <span className="text-gray-400">
                                                                -
                                                            </span>
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        {rule ? (
                                                            rule.emergency_override_allowed ? (
                                                                <Badge
                                                                    variant="outline"
                                                                    className="bg-green-100 text-green-700"
                                                                >
                                                                    Allowed
                                                                </Badge>
                                                            ) : (
                                                                <Badge
                                                                    variant="outline"
                                                                    className="bg-gray-100 text-gray-700"
                                                                >
                                                                    No
                                                                </Badge>
                                                            )
                                                        ) : (
                                                            <span className="text-gray-400">
                                                                -
                                                            </span>
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        {rule ? (
                                                            getStatusBadge(
                                                                rule.is_active,
                                                            )
                                                        ) : (
                                                            <Badge
                                                                variant="outline"
                                                                className="border-yellow-200 bg-yellow-50 text-yellow-700"
                                                            >
                                                                Not Configured
                                                            </Badge>
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        <Button
                                                            variant={
                                                                rule
                                                                    ? 'ghost'
                                                                    : 'outline'
                                                            }
                                                            size="sm"
                                                            onClick={() => {
                                                                if (rule) {
                                                                    setEditingService(
                                                                        rule,
                                                                    );
                                                                } else {
                                                                    setConfigureServiceType(
                                                                        serviceType.value,
                                                                    );
                                                                }
                                                            }}
                                                        >
                                                            {rule ? (
                                                                <Edit3 className="h-4 w-4" />
                                                            ) : (
                                                                <>
                                                                    <Plus className="mr-1 h-4 w-4" />
                                                                    Configure
                                                                </>
                                                            )}
                                                        </Button>
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Ward Billing Templates */}
                    <TabsContent value="ward-billing" className="space-y-6">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <div>
                                    <CardTitle>
                                        Ward Billing Templates
                                    </CardTitle>
                                    <p className="text-sm text-muted-foreground">
                                        Configure recurring daily/hourly fees
                                        for ward admissions
                                    </p>
                                </div>
                                <Button
                                    onClick={() =>
                                        setShowCreateWardBilling(true)
                                    }
                                >
                                    <Plus className="mr-2 h-4 w-4" />
                                    Add Template
                                </Button>
                            </CardHeader>
                            <CardContent>
                                {wardBillingTemplates.length === 0 ? (
                                    <div className="py-8 text-center text-gray-500">
                                        <Bed className="mx-auto h-12 w-12 text-gray-300" />
                                        <p className="mt-2">
                                            No ward billing templates
                                            configured.
                                        </p>
                                        <p className="text-sm">
                                            Add templates to automatically
                                            charge daily fees for admissions.
                                        </p>
                                    </div>
                                ) : (
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>
                                                    Service Name
                                                </TableHead>
                                                <TableHead>Code</TableHead>
                                                <TableHead>Type</TableHead>
                                                <TableHead>
                                                    Cash Amount
                                                </TableHead>
                                                <TableHead>
                                                    NHIS Amount
                                                </TableHead>
                                                <TableHead>
                                                    Effective From
                                                </TableHead>
                                                <TableHead>Status</TableHead>
                                                <TableHead>Actions</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {wardBillingTemplates.map(
                                                (template) => (
                                                    <TableRow key={template.id}>
                                                        <TableCell>
                                                            <div>
                                                                <div className="font-medium">
                                                                    {
                                                                        template.service_name
                                                                    }
                                                                </div>
                                                                {template.description && (
                                                                    <div className="text-sm text-gray-500">
                                                                        {
                                                                            template.description
                                                                        }
                                                                    </div>
                                                                )}
                                                            </div>
                                                        </TableCell>
                                                        <TableCell className="font-mono text-sm">
                                                            {
                                                                template.service_code
                                                            }
                                                        </TableCell>
                                                        <TableCell>
                                                            <Badge
                                                                variant="outline"
                                                                className="capitalize"
                                                            >
                                                                {template.billing_type.replace(
                                                                    '_',
                                                                    ' ',
                                                                )}
                                                            </Badge>
                                                        </TableCell>
                                                        <TableCell className="font-medium">
                                                            {formatCurrency(
                                                                template.base_amount,
                                                            )}
                                                            {template.billing_type ===
                                                                'daily' &&
                                                                '/day'}
                                                            {template.billing_type ===
                                                                'hourly' &&
                                                                '/hr'}
                                                        </TableCell>
                                                        <TableCell>
                                                            <span
                                                                className={
                                                                    template.nhis_amount ===
                                                                    0
                                                                        ? 'font-medium text-green-600'
                                                                        : 'font-medium text-blue-600'
                                                                }
                                                            >
                                                                {formatCurrency(
                                                                    template.nhis_amount ??
                                                                        0,
                                                                )}
                                                                {template.nhis_amount ===
                                                                    0 && (
                                                                    <Badge
                                                                        variant="outline"
                                                                        className="ml-2 bg-green-50 text-green-700"
                                                                    >
                                                                        Free
                                                                    </Badge>
                                                                )}
                                                            </span>
                                                        </TableCell>
                                                        <TableCell>
                                                            {new Date(
                                                                template.effective_from,
                                                            ).toLocaleDateString()}
                                                        </TableCell>
                                                        <TableCell>
                                                            {getStatusBadge(
                                                                template.is_active,
                                                            )}
                                                        </TableCell>
                                                        <TableCell>
                                                            <div className="flex gap-1">
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        setEditingWardBilling(
                                                                            template,
                                                                        )
                                                                    }
                                                                >
                                                                    <Edit3 className="h-4 w-4" />
                                                                </Button>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    className="text-red-600 hover:text-red-700"
                                                                    onClick={() => {
                                                                        if (
                                                                            confirm(
                                                                                'Are you sure you want to delete this template?',
                                                                            )
                                                                        ) {
                                                                            router.delete(
                                                                                `/billing/configuration/ward-billing/${template.id}`,
                                                                            );
                                                                        }
                                                                    }}
                                                                >
                                                                    <Trash2 className="h-4 w-4" />
                                                                </Button>
                                                            </div>
                                                        </TableCell>
                                                    </TableRow>
                                                ),
                                            )}
                                        </TableBody>
                                    </Table>
                                )}
                            </CardContent>
                        </Card>

                        <Alert>
                            <Info className="h-4 w-4" />
                            <AlertDescription>
                                Ward billing templates define recurring charges
                                that are automatically applied to admitted
                                patients. Daily fees are charged at midnight for
                                each day of stay.
                            </AlertDescription>
                        </Alert>
                    </TabsContent>

                    {/* System Configuration */}
                    <TabsContent value="system" className="space-y-6">
                        {Object.keys(systemConfig).length === 0 ? (
                            <Card>
                                <CardContent className="py-8 text-center text-gray-500">
                                    No system configurations found.
                                </CardContent>
                            </Card>
                        ) : (
                            <div className="grid gap-6 md:grid-cols-2">
                                {Object.entries(systemConfig).map(
                                    ([category, configs]) => (
                                        <Card key={category}>
                                            <CardHeader>
                                                <CardTitle className="capitalize">
                                                    {category.replace(
                                                        /_/g,
                                                        ' ',
                                                    )}{' '}
                                                    Settings
                                                </CardTitle>
                                            </CardHeader>
                                            <CardContent className="space-y-4">
                                                {configs.map((config) => (
                                                    <div
                                                        key={config.id}
                                                        className="flex items-start justify-between rounded-lg border p-3"
                                                    >
                                                        <div className="space-y-1">
                                                            <Label className="text-sm font-medium">
                                                                {config.key.replace(
                                                                    /_/g,
                                                                    ' ',
                                                                )}
                                                            </Label>
                                                            {config.description && (
                                                                <div className="text-sm text-gray-500">
                                                                    {
                                                                        config.description
                                                                    }
                                                                </div>
                                                            )}
                                                            <div className="text-sm">
                                                                Current:{' '}
                                                                <span className="font-medium">
                                                                    {typeof config.value ===
                                                                    'boolean'
                                                                        ? config.value
                                                                            ? 'Yes'
                                                                            : 'No'
                                                                        : String(
                                                                              config.value,
                                                                          )}
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                setEditingConfig(
                                                                    config,
                                                                )
                                                            }
                                                        >
                                                            <Edit3 className="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                ))}
                                            </CardContent>
                                        </Card>
                                    ),
                                )}
                            </div>
                        )}
                    </TabsContent>
                </Tabs>
            </div>

            {/* Department Billing Modal - handles both create and edit */}
            <DepartmentBillingModal
                department={editingDepartment}
                onOpenChange={(open) => !open && setEditingDepartment(null)}
            />

            {/* Bulk Configuration Modal */}
            <BulkConfigModal
                open={showBulkConfig}
                onOpenChange={(open) => {
                    setShowBulkConfig(open);
                    if (!open) setSelectedDepartments([]);
                }}
                selectedDepartments={departments.filter((d) =>
                    selectedDepartments.includes(d.id),
                )}
            />

            {/* Configure Service Rule Modal */}
            <ConfigureServiceRuleModal
                serviceType={configureServiceType}
                onOpenChange={(open) => !open && setConfigureServiceType(null)}
            />

            {/* Edit Service Rule Modal */}
            <EditServiceRuleModal
                serviceRule={editingService}
                onOpenChange={(open) => !open && setEditingService(null)}
            />

            {/* Edit System Config Modal */}
            <EditConfigModal
                config={editingConfig}
                onOpenChange={(open) => !open && setEditingConfig(null)}
            />

            {/* Ward Billing Template Modals */}
            <WardBillingTemplateModal
                template={editingWardBilling}
                onOpenChange={(open) => !open && setEditingWardBilling(null)}
            />
            <CreateWardBillingTemplateModal
                open={showCreateWardBilling}
                onOpenChange={setShowCreateWardBilling}
            />
        </AppLayout>
    );
}

// Department Billing Modal - handles both create and edit
function DepartmentBillingModal({
    department,
    onOpenChange,
}: {
    department: Department | null;
    onOpenChange: (open: boolean) => void;
}) {
    const isEditing = !!department?.billing;

    const form = useForm({
        department_id: department?.id?.toString() || '',
        equipment_fee: department?.billing?.equipment_fee?.toString() || '0',
        emergency_surcharge:
            department?.billing?.emergency_surcharge?.toString() || '0',
        payment_required_before_consultation:
            department?.billing?.payment_required_before_consultation || false,
        emergency_override_allowed:
            department?.billing?.emergency_override_allowed || false,
        payment_grace_period_minutes:
            department?.billing?.payment_grace_period_minutes?.toString() ||
            '30',
        is_active: department?.billing?.is_active ?? true,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!department) return;

        if (isEditing && department.billing) {
            form.put(
                `/billing/configuration/department/${department.billing.id}`,
                {
                    onSuccess: () => onOpenChange(false),
                },
            );
        } else {
            form.post('/billing/configuration/department', {
                onSuccess: () => onOpenChange(false),
            });
        }
    };

    // Reset form when department changes
    if (department && form.data.department_id !== department.id.toString()) {
        form.setData({
            department_id: department.id.toString(),
            equipment_fee: department.billing?.equipment_fee?.toString() || '0',
            emergency_surcharge:
                department.billing?.emergency_surcharge?.toString() || '0',
            payment_required_before_consultation:
                department.billing?.payment_required_before_consultation ||
                false,
            emergency_override_allowed:
                department.billing?.emergency_override_allowed || false,
            payment_grace_period_minutes:
                department.billing?.payment_grace_period_minutes?.toString() ||
                '30',
            is_active: department.billing?.is_active ?? true,
        });
    }

    return (
        <Dialog open={!!department} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? 'Edit' : 'Configure'} Department Billing
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? `Update billing configuration for ${department?.name}`
                            : `Set up billing configuration for ${department?.name}`}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit}>
                    <div className="grid gap-4 py-4">
                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label className="text-right">Department</Label>
                            <div className="col-span-3 font-medium">
                                {department?.name} ({department?.code})
                            </div>
                        </div>

                        <Alert className="col-span-full">
                            <Info className="h-4 w-4" />
                            <AlertDescription className="flex items-center justify-between">
                                <span>
                                    {department?.billing?.consultation_fee
                                        ? `Current consultation fee: GHS ${Number(department.billing.consultation_fee).toFixed(2)}`
                                        : 'Consultation fee not set'}
                                    . Manage pricing in the Pricing Dashboard.
                                </span>
                                <Button
                                    variant="link"
                                    size="sm"
                                    className="ml-2 h-auto p-0"
                                    type="button"
                                    asChild
                                >
                                    <Link
                                        href={`/admin/pricing-dashboard?search=${encodeURIComponent(department?.code || '')}`}
                                        target="_blank"
                                    >
                                        Set Price
                                        <ExternalLink className="ml-1 h-3 w-3" />
                                    </Link>
                                </Button>
                            </AlertDescription>
                        </Alert>

                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label
                                htmlFor="equipment_fee"
                                className="text-right"
                            >
                                Equipment Fee
                            </Label>
                            <Input
                                id="equipment_fee"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="0.00"
                                className="col-span-3"
                                value={form.data.equipment_fee}
                                onChange={(e) =>
                                    form.setData(
                                        'equipment_fee',
                                        e.target.value,
                                    )
                                }
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
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="0.00"
                                className="col-span-3"
                                value={form.data.emergency_surcharge}
                                onChange={(e) =>
                                    form.setData(
                                        'emergency_surcharge',
                                        e.target.value,
                                    )
                                }
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
                                type="number"
                                min="0"
                                placeholder="30"
                                className="col-span-3"
                                value={form.data.payment_grace_period_minutes}
                                onChange={(e) =>
                                    form.setData(
                                        'payment_grace_period_minutes',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>

                        <div className="space-y-3 pt-2">
                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="payment_required_before_consultation"
                                    checked={
                                        form.data
                                            .payment_required_before_consultation
                                    }
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'payment_required_before_consultation',
                                            checked === true,
                                        )
                                    }
                                />
                                <Label htmlFor="payment_required_before_consultation">
                                    Payment required before consultation
                                </Label>
                            </div>
                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="emergency_override_allowed"
                                    checked={
                                        form.data.emergency_override_allowed
                                    }
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'emergency_override_allowed',
                                            checked === true,
                                        )
                                    }
                                />
                                <Label htmlFor="emergency_override_allowed">
                                    Emergency override allowed
                                </Label>
                            </div>
                            {isEditing && (
                                <div className="flex items-center space-x-2 pt-2">
                                    <Switch
                                        id="is_active"
                                        checked={form.data.is_active}
                                        onCheckedChange={(checked) =>
                                            form.setData('is_active', checked)
                                        }
                                    />
                                    <Label htmlFor="is_active">Active</Label>
                                </div>
                            )}
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && (
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            )}
                            {isEditing ? 'Save Changes' : 'Configure'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

// Configure Service Rule Modal Component
function ConfigureServiceRuleModal({
    serviceType,
    onOpenChange,
}: {
    serviceType: string | null;
    onOpenChange: (open: boolean) => void;
}) {
    const serviceLabel =
        SERVICE_TYPES.find((s) => s.value === serviceType)?.label ||
        serviceType;

    const form = useForm({
        service_type: serviceType || '',
        service_name: serviceLabel || '',
        charge_timing: 'before_service',
        payment_required: 'optional',
        payment_timing: 'immediate',
        emergency_override_allowed: true,
        service_blocking_enabled: false,
        hide_details_until_paid: false,
    });

    // Update form when serviceType changes
    if (serviceType && form.data.service_type !== serviceType) {
        const label =
            SERVICE_TYPES.find((s) => s.value === serviceType)?.label ||
            serviceType;
        form.setData({
            ...form.data,
            service_type: serviceType,
            service_name: label,
        });
    }

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/billing/configuration/service-rule', {
            onSuccess: () => {
                onOpenChange(false);
                form.reset();
            },
        });
    };

    return (
        <Dialog open={!!serviceType} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[450px]">
                <DialogHeader>
                    <DialogTitle>Configure {serviceLabel} Service</DialogTitle>
                    <DialogDescription>
                        Set payment rules for {serviceLabel?.toLowerCase()}{' '}
                        services.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit}>
                    <div className="grid gap-4 py-4">
                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label className="text-right">Service</Label>
                            <div className="col-span-3 font-medium capitalize">
                                {serviceLabel}
                            </div>
                        </div>

                        <div className="space-y-4 pt-2">
                            <div className="flex items-center justify-between rounded-lg border p-3">
                                <div>
                                    <Label>
                                        Payment required before service
                                    </Label>
                                    <p className="text-sm text-muted-foreground">
                                        Patient must pay before receiving this
                                        service
                                    </p>
                                </div>
                                <Switch
                                    checked={
                                        form.data.payment_required ===
                                        'mandatory'
                                    }
                                    onCheckedChange={(checked) => {
                                        form.setData(
                                            'payment_required',
                                            checked ? 'mandatory' : 'optional',
                                        );
                                    }}
                                />
                            </div>

                            <div className="flex items-center justify-between rounded-lg border p-3">
                                <div>
                                    <Label>Block service if unpaid</Label>
                                    <p className="text-sm text-muted-foreground">
                                        Prevent service delivery until payment
                                        is made
                                    </p>
                                </div>
                                <Switch
                                    checked={form.data.service_blocking_enabled}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'service_blocking_enabled',
                                            checked,
                                        )
                                    }
                                />
                            </div>

                            <div className="flex items-center justify-between rounded-lg border p-3">
                                <div>
                                    <Label>Allow emergency override</Label>
                                    <p className="text-sm text-muted-foreground">
                                        Allow service in emergencies without
                                        payment
                                    </p>
                                </div>
                                <Switch
                                    checked={
                                        form.data.emergency_override_allowed
                                    }
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'emergency_override_allowed',
                                            checked,
                                        )
                                    }
                                />
                            </div>

                            <div className="flex items-center justify-between rounded-lg border p-3">
                                <div>
                                    <Label>Hide results until paid</Label>
                                    <p className="text-sm text-muted-foreground">
                                        Results visible only after payment
                                        (e.g., lab results)
                                    </p>
                                </div>
                                <Switch
                                    checked={form.data.hide_details_until_paid}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'hide_details_until_paid',
                                            checked,
                                        )
                                    }
                                />
                            </div>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && (
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            )}
                            Configure
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

// Edit Service Rule Modal Component
function EditServiceRuleModal({
    serviceRule,
    onOpenChange,
}: {
    serviceRule: ServiceChargeRule | null;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm({
        charge_timing: serviceRule?.charge_timing || 'before_service',
        payment_required: serviceRule?.payment_required || 'optional',
        payment_timing: serviceRule?.payment_timing || 'immediate',
        emergency_override_allowed:
            serviceRule?.emergency_override_allowed || false,
        service_blocking_enabled:
            serviceRule?.service_blocking_enabled || false,
        hide_details_until_paid: serviceRule?.hide_details_until_paid || false,
        is_active: serviceRule?.is_active ?? true,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!serviceRule) return;

        form.put(`/billing/configuration/service-rule/${serviceRule.id}`, {
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={!!serviceRule} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[450px]">
                <DialogHeader>
                    <DialogTitle>
                        Edit {serviceRule?.service_name} Service
                    </DialogTitle>
                    <DialogDescription>
                        Update payment rules for this service type.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit}>
                    <div className="grid gap-4 py-4">
                        <div className="space-y-4">
                            <div className="flex items-center justify-between rounded-lg border p-3">
                                <div>
                                    <Label>
                                        Payment required before service
                                    </Label>
                                    <p className="text-sm text-muted-foreground">
                                        Patient must pay before receiving this
                                        service
                                    </p>
                                </div>
                                <Switch
                                    checked={
                                        form.data.payment_required ===
                                        'mandatory'
                                    }
                                    onCheckedChange={(checked) => {
                                        form.setData(
                                            'payment_required',
                                            checked ? 'mandatory' : 'optional',
                                        );
                                    }}
                                />
                            </div>

                            <div className="flex items-center justify-between rounded-lg border p-3">
                                <div>
                                    <Label>Block service if unpaid</Label>
                                    <p className="text-sm text-muted-foreground">
                                        Prevent service delivery until payment
                                        is made
                                    </p>
                                </div>
                                <Switch
                                    checked={form.data.service_blocking_enabled}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'service_blocking_enabled',
                                            checked,
                                        )
                                    }
                                />
                            </div>

                            <div className="flex items-center justify-between rounded-lg border p-3">
                                <div>
                                    <Label>Allow emergency override</Label>
                                    <p className="text-sm text-muted-foreground">
                                        Allow service in emergencies without
                                        payment
                                    </p>
                                </div>
                                <Switch
                                    checked={
                                        form.data.emergency_override_allowed
                                    }
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'emergency_override_allowed',
                                            checked,
                                        )
                                    }
                                />
                            </div>

                            <div className="flex items-center justify-between rounded-lg border p-3">
                                <div>
                                    <Label>Hide results until paid</Label>
                                    <p className="text-sm text-muted-foreground">
                                        Results visible only after payment
                                        (e.g., lab results)
                                    </p>
                                </div>
                                <Switch
                                    checked={form.data.hide_details_until_paid}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'hide_details_until_paid',
                                            checked,
                                        )
                                    }
                                />
                            </div>

                            <div className="flex items-center justify-between rounded-lg border p-3">
                                <div>
                                    <Label>Active</Label>
                                    <p className="text-sm text-muted-foreground">
                                        Enable or disable this service rule
                                    </p>
                                </div>
                                <Switch
                                    checked={form.data.is_active}
                                    onCheckedChange={(checked) =>
                                        form.setData('is_active', checked)
                                    }
                                />
                            </div>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && (
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            )}
                            Save Changes
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

// Edit System Config Modal Component
function EditConfigModal({
    config,
    onOpenChange,
}: {
    config: BillingConfiguration | null;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm<{
        configs: Array<{
            key: string;
            value: string | boolean;
            category: string;
            description: string;
        }>;
    }>({
        configs: [
            {
                key: config?.key || '',
                value: config?.value ?? '',
                category: config?.category || '',
                description: config?.description || '',
            },
        ],
    });

    // Update form data when config changes
    useEffect(() => {
        if (config) {
            form.setData('configs', [
                {
                    key: config.key,
                    value: config.value,
                    category: config.category,
                    description: config.description || '',
                },
            ]);
        }
    }, [config]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/billing/configuration/system', {
            onSuccess: () => onOpenChange(false),
        });
    };

    const isBooleanValue = typeof config?.value === 'boolean';

    return (
        <Dialog open={!!config} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[400px]">
                <DialogHeader>
                    <DialogTitle>Edit Configuration</DialogTitle>
                    <DialogDescription>
                        Update {config?.key?.replace(/_/g, ' ')}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit}>
                    <div className="grid gap-4 py-4">
                        {config?.description && (
                            <p className="text-sm text-gray-500">
                                {config.description}
                            </p>
                        )}

                        {isBooleanValue ? (
                            <div className="flex items-center space-x-2">
                                <Switch
                                    id="config_value"
                                    checked={
                                        form.data.configs[0].value === true
                                    }
                                    onCheckedChange={(checked) =>
                                        form.setData('configs', [
                                            {
                                                ...form.data.configs[0],
                                                value: checked,
                                            },
                                        ])
                                    }
                                />
                                <Label htmlFor="config_value">Enabled</Label>
                            </div>
                        ) : (
                            <div className="space-y-2">
                                <Label htmlFor="config_value">Value</Label>
                                <Input
                                    id="config_value"
                                    value={String(form.data.configs[0].value)}
                                    onChange={(e) =>
                                        form.setData('configs', [
                                            {
                                                ...form.data.configs[0],
                                                value: e.target.value,
                                            },
                                        ])
                                    }
                                />
                            </div>
                        )}
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && (
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            )}
                            Save
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

// Bulk Configuration Modal Component
function BulkConfigModal({
    open,
    onOpenChange,
    selectedDepartments,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    selectedDepartments: Department[];
}) {
    const form = useForm({
        department_ids: selectedDepartments.map((d) => d.id),
        equipment_fee: '0',
        emergency_surcharge: '0',
        payment_required_before_consultation: false,
        emergency_override_allowed: false,
        payment_grace_period_minutes: '30',
    });

    // Update department_ids when selectedDepartments changes
    if (
        open &&
        form.data.department_ids.length !== selectedDepartments.length
    ) {
        form.setData(
            'department_ids',
            selectedDepartments.map((d) => d.id),
        );
    }

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/billing/configuration/department/bulk', {
            onSuccess: () => {
                onOpenChange(false);
                form.reset();
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[550px]">
                <DialogHeader>
                    <DialogTitle>Bulk Configure Departments</DialogTitle>
                    <DialogDescription>
                        Apply the same billing configuration to{' '}
                        {selectedDepartments.length} selected departments. This
                        will create or update billing settings for all selected
                        departments.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit}>
                    <div className="grid gap-4 py-4">
                        <div className="rounded-lg border bg-muted/50 p-3">
                            <Label className="text-sm font-medium">
                                Selected Departments
                            </Label>
                            <div className="mt-2 flex flex-wrap gap-2">
                                {selectedDepartments.map((dept) => (
                                    <Badge key={dept.id} variant="secondary">
                                        {dept.name}
                                    </Badge>
                                ))}
                            </div>
                        </div>

                        <Alert>
                            <Info className="h-4 w-4" />
                            <AlertDescription className="flex items-center justify-between">
                                <span>
                                    Consultation fees are managed in the Pricing
                                    Dashboard.
                                </span>
                                <Button
                                    variant="link"
                                    size="sm"
                                    className="ml-2 h-auto p-0"
                                    type="button"
                                    asChild
                                >
                                    <Link
                                        href="/admin/pricing-dashboard"
                                        target="_blank"
                                    >
                                        Open Pricing Dashboard
                                        <ExternalLink className="ml-1 h-3 w-3" />
                                    </Link>
                                </Button>
                            </AlertDescription>
                        </Alert>

                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label
                                htmlFor="bulk_equipment_fee"
                                className="text-right"
                            >
                                Equipment Fee
                            </Label>
                            <Input
                                id="bulk_equipment_fee"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="0.00"
                                className="col-span-3"
                                value={form.data.equipment_fee}
                                onChange={(e) =>
                                    form.setData(
                                        'equipment_fee',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>

                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label
                                htmlFor="bulk_emergency_surcharge"
                                className="text-right"
                            >
                                Emergency Surcharge
                            </Label>
                            <Input
                                id="bulk_emergency_surcharge"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="0.00"
                                className="col-span-3"
                                value={form.data.emergency_surcharge}
                                onChange={(e) =>
                                    form.setData(
                                        'emergency_surcharge',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>

                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label
                                htmlFor="bulk_grace_period"
                                className="text-right"
                            >
                                Grace Period (min)
                            </Label>
                            <Input
                                id="bulk_grace_period"
                                type="number"
                                min="0"
                                placeholder="30"
                                className="col-span-3"
                                value={form.data.payment_grace_period_minutes}
                                onChange={(e) =>
                                    form.setData(
                                        'payment_grace_period_minutes',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>

                        <div className="space-y-3 pt-2">
                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="bulk_payment_required"
                                    checked={
                                        form.data
                                            .payment_required_before_consultation
                                    }
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'payment_required_before_consultation',
                                            checked === true,
                                        )
                                    }
                                />
                                <Label htmlFor="bulk_payment_required">
                                    Payment required before consultation
                                </Label>
                            </div>
                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="bulk_emergency_override"
                                    checked={
                                        form.data.emergency_override_allowed
                                    }
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'emergency_override_allowed',
                                            checked === true,
                                        )
                                    }
                                />
                                <Label htmlFor="bulk_emergency_override">
                                    Emergency override allowed
                                </Label>
                            </div>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && (
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            )}
                            Configure {selectedDepartments.length} Departments
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

// Ward Billing Template Modal - for editing
function WardBillingTemplateModal({
    template,
    onOpenChange,
}: {
    template: WardBillingTemplate | null;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm({
        service_name: template?.service_name || '',
        description: template?.description || '',
        billing_type: template?.billing_type || 'daily',
        base_amount: template?.base_amount?.toString() || '0',
        nhis_amount: template?.nhis_amount?.toString() || '',
        effective_from:
            template?.effective_from || new Date().toISOString().split('T')[0],
        effective_to: template?.effective_to || '',
        is_active: template?.is_active ?? true,
    });

    // Reset form when template changes
    if (template && form.data.service_name !== template.service_name) {
        form.setData({
            service_name: template.service_name,
            description: template.description || '',
            billing_type: template.billing_type,
            base_amount: template.base_amount.toString(),
            nhis_amount: template.nhis_amount?.toString() || '',
            effective_from: template.effective_from,
            effective_to: template.effective_to || '',
            is_active: template.is_active,
        });
    }

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!template) return;

        form.put(`/billing/configuration/ward-billing/${template.id}`, {
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={!!template} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle>Edit Ward Billing Template</DialogTitle>
                    <DialogDescription>
                        Update the billing template for {template?.service_name}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit}>
                    <div className="grid gap-4 py-4">
                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label
                                htmlFor="edit_service_name"
                                className="text-right"
                            >
                                Service Name
                            </Label>
                            <Input
                                id="edit_service_name"
                                value={form.data.service_name}
                                onChange={(e) =>
                                    form.setData('service_name', e.target.value)
                                }
                                className="col-span-3"
                            />
                        </div>
                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label
                                htmlFor="edit_description"
                                className="text-right"
                            >
                                Description
                            </Label>
                            <Input
                                id="edit_description"
                                value={form.data.description}
                                onChange={(e) =>
                                    form.setData('description', e.target.value)
                                }
                                className="col-span-3"
                            />
                        </div>
                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label
                                htmlFor="edit_billing_type"
                                className="text-right"
                            >
                                Billing Type
                            </Label>
                            <Select
                                value={form.data.billing_type}
                                onValueChange={(value) =>
                                    form.setData(
                                        'billing_type',
                                        value as
                                            | 'daily'
                                            | 'hourly'
                                            | 'one_time',
                                    )
                                }
                            >
                                <SelectTrigger className="col-span-3">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="daily">Daily</SelectItem>
                                    <SelectItem value="hourly">
                                        Hourly
                                    </SelectItem>
                                    <SelectItem value="one_time">
                                        One Time
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label
                                htmlFor="edit_base_amount"
                                className="text-right"
                            >
                                Cash Amount (GHS)
                            </Label>
                            <Input
                                id="edit_base_amount"
                                type="number"
                                step="0.01"
                                min="0"
                                value={form.data.base_amount}
                                onChange={(e) =>
                                    form.setData('base_amount', e.target.value)
                                }
                                className="col-span-3"
                            />
                        </div>
                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label
                                htmlFor="edit_nhis_amount"
                                className="text-right"
                            >
                                NHIS Amount (GHS)
                            </Label>
                            <div className="col-span-3 space-y-1">
                                <Input
                                    id="edit_nhis_amount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={form.data.nhis_amount}
                                    onChange={(e) =>
                                        form.setData(
                                            'nhis_amount',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="0.00"
                                />
                                <p className="text-xs text-gray-500">
                                    Set to 0 if NHIS patients don't pay this fee
                                </p>
                            </div>
                        </div>
                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label
                                htmlFor="edit_effective_from"
                                className="text-right"
                            >
                                Effective From
                            </Label>
                            <Input
                                id="edit_effective_from"
                                type="date"
                                value={form.data.effective_from}
                                onChange={(e) =>
                                    form.setData(
                                        'effective_from',
                                        e.target.value,
                                    )
                                }
                                className="col-span-3"
                            />
                        </div>
                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label
                                htmlFor="edit_effective_to"
                                className="text-right"
                            >
                                Effective To
                            </Label>
                            <Input
                                id="edit_effective_to"
                                type="date"
                                value={form.data.effective_to}
                                onChange={(e) =>
                                    form.setData('effective_to', e.target.value)
                                }
                                className="col-span-3"
                                placeholder="Leave empty for no end date"
                            />
                        </div>
                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label className="text-right">Status</Label>
                            <div className="col-span-3 flex items-center space-x-2">
                                <Switch
                                    id="edit_is_active"
                                    checked={form.data.is_active}
                                    onCheckedChange={(checked) =>
                                        form.setData('is_active', checked)
                                    }
                                />
                                <Label htmlFor="edit_is_active">Active</Label>
                            </div>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && (
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            )}
                            Save Changes
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

// Create Ward Billing Template Modal
function CreateWardBillingTemplateModal({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm({
        service_name: '',
        service_code: '',
        description: '',
        billing_type: 'daily',
        base_amount: '',
        nhis_amount: '0',
        effective_from: new Date().toISOString().split('T')[0],
        effective_to: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/billing/configuration/ward-billing', {
            onSuccess: () => {
                onOpenChange(false);
                form.reset();
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle>Create Ward Billing Template</DialogTitle>
                    <DialogDescription>
                        Add a new recurring fee template for ward admissions
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit}>
                    <div className="grid gap-4 py-4">
                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label
                                htmlFor="service_name"
                                className="text-right"
                            >
                                Service Name
                            </Label>
                            <Input
                                id="service_name"
                                value={form.data.service_name}
                                onChange={(e) =>
                                    form.setData('service_name', e.target.value)
                                }
                                className="col-span-3"
                                placeholder="e.g., Daily Ward Fee"
                            />
                            {form.errors.service_name && (
                                <p className="col-span-3 col-start-2 text-sm text-red-500">
                                    {form.errors.service_name}
                                </p>
                            )}
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
                                value={form.data.service_code}
                                onChange={(e) =>
                                    form.setData(
                                        'service_code',
                                        e.target.value
                                            .toUpperCase()
                                            .replace(/\s+/g, '_'),
                                    )
                                }
                                className="col-span-3 font-mono"
                                placeholder="e.g., DAILY_WARD_FEE"
                            />
                            {form.errors.service_code && (
                                <p className="col-span-3 col-start-2 text-sm text-red-500">
                                    {form.errors.service_code}
                                </p>
                            )}
                        </div>
                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label htmlFor="description" className="text-right">
                                Description
                            </Label>
                            <Input
                                id="description"
                                value={form.data.description}
                                onChange={(e) =>
                                    form.setData('description', e.target.value)
                                }
                                className="col-span-3"
                                placeholder="Optional description"
                            />
                        </div>
                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label
                                htmlFor="billing_type"
                                className="text-right"
                            >
                                Billing Type
                            </Label>
                            <Select
                                value={form.data.billing_type}
                                onValueChange={(value) =>
                                    form.setData('billing_type', value)
                                }
                            >
                                <SelectTrigger className="col-span-3">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="daily">
                                        Daily (charged every day)
                                    </SelectItem>
                                    <SelectItem value="hourly">
                                        Hourly (charged per hour)
                                    </SelectItem>
                                    <SelectItem value="one_time">
                                        One Time (charged once on admission)
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label htmlFor="base_amount" className="text-right">
                                Cash Amount (GHS)
                            </Label>
                            <Input
                                id="base_amount"
                                type="number"
                                step="0.01"
                                min="0"
                                value={form.data.base_amount}
                                onChange={(e) =>
                                    form.setData('base_amount', e.target.value)
                                }
                                className="col-span-3"
                                placeholder="0.00"
                            />
                            {form.errors.base_amount && (
                                <p className="col-span-3 col-start-2 text-sm text-red-500">
                                    {form.errors.base_amount}
                                </p>
                            )}
                        </div>
                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label htmlFor="nhis_amount" className="text-right">
                                NHIS Amount (GHS)
                            </Label>
                            <div className="col-span-3 space-y-1">
                                <Input
                                    id="nhis_amount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={form.data.nhis_amount}
                                    onChange={(e) =>
                                        form.setData(
                                            'nhis_amount',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="0.00"
                                />
                                <p className="text-xs text-gray-500">
                                    Set to 0 if NHIS patients don't pay this fee
                                </p>
                                {form.errors.nhis_amount && (
                                    <p className="text-sm text-red-500">
                                        {form.errors.nhis_amount}
                                    </p>
                                )}
                            </div>
                        </div>
                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label
                                htmlFor="effective_from"
                                className="text-right"
                            >
                                Effective From
                            </Label>
                            <Input
                                id="effective_from"
                                type="date"
                                value={form.data.effective_from}
                                onChange={(e) =>
                                    form.setData(
                                        'effective_from',
                                        e.target.value,
                                    )
                                }
                                className="col-span-3"
                            />
                            {form.errors.effective_from && (
                                <p className="col-span-3 col-start-2 text-sm text-red-500">
                                    {form.errors.effective_from}
                                </p>
                            )}
                        </div>
                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label
                                htmlFor="effective_to"
                                className="text-right"
                            >
                                Effective To
                            </Label>
                            <Input
                                id="effective_to"
                                type="date"
                                value={form.data.effective_to}
                                onChange={(e) =>
                                    form.setData('effective_to', e.target.value)
                                }
                                className="col-span-3"
                            />
                            <p className="col-span-3 col-start-2 text-xs text-gray-500">
                                Leave empty for no end date
                            </p>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && (
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            )}
                            Create Template
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
