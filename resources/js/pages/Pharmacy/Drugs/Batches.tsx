import DrugController from '@/actions/App/Http/Controllers/Pharmacy/DrugController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
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
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    AlertCircle,
    ArrowLeft,
    Building,
    Calendar,
    ChevronLeft,
    ChevronRight,
    Eye,
    Package,
    Plus,
} from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Drug {
    id: number;
    name: string;
    form: string;
    unit_type: string;
}

interface Supplier {
    id: number;
    name: string;
}

interface DrugBatch {
    id: number;
    batch_number: string;
    supplier: Supplier;
    expiry_date: string;
    manufacture_date?: string;
    quantity_received: number;
    quantity_remaining: number;
    cost_per_unit: number;
    selling_price_per_unit: number;
    received_date: string;
    notes?: string;
}

interface PaginatedBatches {
    data: DrugBatch[];
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

interface Props {
    drug: Drug;
    batches: PaginatedBatches;
    suppliers: Supplier[];
}

const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleDateString();
};

const getDaysUntilExpiry = (expiryDate: string) => {
    const expiry = new Date(expiryDate);
    const now = new Date();
    const daysUntilExpiry = Math.ceil(
        (expiry.getTime() - now.getTime()) / (1000 * 60 * 60 * 24),
    );
    return daysUntilExpiry;
};

const getBatchStatus = (batch: DrugBatch) => {
    const daysUntilExpiry = getDaysUntilExpiry(batch.expiry_date);

    if (daysUntilExpiry < 0) {
        return {
            label: 'Expired',
            className:
                'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
        };
    }
    if (daysUntilExpiry <= 7) {
        return {
            label: 'Expires Soon',
            className:
                'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300',
        };
    }
    if (daysUntilExpiry <= 30) {
        return {
            label: 'Expiring',
            className:
                'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
        };
    }
    return {
        label: 'Good',
        className:
            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    };
};

export default function DrugBatches({ drug, batches, suppliers }: Props) {
    const [showAddForm, setShowAddForm] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        supplier_id: '',
        batch_number: '',
        expiry_date: '',
        manufacture_date: '',
        quantity_received: '',
        cost_per_unit: '',
        selling_price_per_unit: '',
        received_date: new Date().toISOString().split('T')[0],
        notes: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(DrugController.storeBatch(drug.id), {
            onSuccess: () => {
                setShowAddForm(false);
                reset();
            },
        });
    };

    const totalStock =
        batches?.data?.reduce(
            (sum, batch) => sum + batch.quantity_remaining,
            0,
        ) || 0;
    const totalValue =
        batches?.data?.reduce(
            (sum, batch) =>
                sum +
                batch.quantity_remaining * Number(batch.selling_price_per_unit),
            0,
        ) || 0;
    const expiredBatches =
        batches?.data?.filter(
            (batch) => getDaysUntilExpiry(batch.expiry_date) < 0,
        ).length || 0;
    const expiringSoon =
        batches?.data?.filter((batch) => {
            const days = getDaysUntilExpiry(batch.expiry_date);
            return days >= 0 && days <= 30;
        })?.length || 0;

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Pharmacy', href: '/pharmacy' },
                { title: 'Drugs', href: '/pharmacy/drugs' },
                { title: drug.name, href: `/pharmacy/drugs/${drug.id}` },
                {
                    title: 'Batches',
                    href: `/pharmacy/drugs/${drug.id}/batches`,
                },
            ]}
        >
            <Head title={`${drug.name} - Batch Management`} />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={`/pharmacy/drugs/${drug.id}`}>
                                <ArrowLeft className="mr-1 h-4 w-4" />
                                Back to Drug
                            </Link>
                        </Button>
                        <div>
                            <h1 className="flex items-center gap-2 text-2xl font-bold">
                                <Package className="h-6 w-6" />
                                {drug.name} - Batch Management
                            </h1>
                            <p className="text-muted-foreground">
                                Manage drug batches, track expiry dates and
                                stock levels
                            </p>
                        </div>
                    </div>
                    <Dialog open={showAddForm} onOpenChange={setShowAddForm}>
                        <DialogTrigger asChild>
                            <Button>
                                <Plus className="mr-1 h-4 w-4" />
                                Add New Batch
                            </Button>
                        </DialogTrigger>
                        <DialogContent className="max-w-2xl">
                            <DialogHeader>
                                <DialogTitle>
                                    Add New Batch for {drug.name}
                                </DialogTitle>
                            </DialogHeader>
                            <form onSubmit={submit} className="space-y-4">
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="supplier_id">
                                            Supplier *
                                        </Label>
                                        <Select
                                            value={data.supplier_id}
                                            onValueChange={(value) =>
                                                setData('supplier_id', value)
                                            }
                                        >
                                            <SelectTrigger
                                                className={
                                                    errors.supplier_id
                                                        ? 'border-red-500'
                                                        : ''
                                                }
                                            >
                                                <SelectValue placeholder="Select supplier" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {suppliers?.map((supplier) => (
                                                    <SelectItem
                                                        key={supplier.id}
                                                        value={supplier.id.toString()}
                                                    >
                                                        {supplier.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {errors.supplier_id && (
                                            <p className="text-sm text-red-500">
                                                {errors.supplier_id}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="batch_number">
                                            Batch Number *
                                        </Label>
                                        <Input
                                            id="batch_number"
                                            type="text"
                                            value={data.batch_number}
                                            onChange={(e) =>
                                                setData(
                                                    'batch_number',
                                                    e.target.value,
                                                )
                                            }
                                            className={
                                                errors.batch_number
                                                    ? 'border-red-500'
                                                    : ''
                                            }
                                            placeholder="Enter batch number"
                                        />
                                        {errors.batch_number && (
                                            <p className="text-sm text-red-500">
                                                {errors.batch_number}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="expiry_date">
                                            Expiry Date *
                                        </Label>
                                        <Input
                                            id="expiry_date"
                                            type="date"
                                            value={data.expiry_date}
                                            onChange={(e) =>
                                                setData(
                                                    'expiry_date',
                                                    e.target.value,
                                                )
                                            }
                                            className={
                                                errors.expiry_date
                                                    ? 'border-red-500'
                                                    : ''
                                            }
                                        />
                                        {errors.expiry_date && (
                                            <p className="text-sm text-red-500">
                                                {errors.expiry_date}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="manufacture_date">
                                            Manufacture Date
                                        </Label>
                                        <Input
                                            id="manufacture_date"
                                            type="date"
                                            value={data.manufacture_date}
                                            onChange={(e) =>
                                                setData(
                                                    'manufacture_date',
                                                    e.target.value,
                                                )
                                            }
                                            className={
                                                errors.manufacture_date
                                                    ? 'border-red-500'
                                                    : ''
                                            }
                                        />
                                        {errors.manufacture_date && (
                                            <p className="text-sm text-red-500">
                                                {errors.manufacture_date}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="quantity_received">
                                            Quantity Received *
                                        </Label>
                                        <Input
                                            id="quantity_received"
                                            type="number"
                                            min="1"
                                            value={data.quantity_received}
                                            onChange={(e) =>
                                                setData(
                                                    'quantity_received',
                                                    e.target.value,
                                                )
                                            }
                                            className={
                                                errors.quantity_received
                                                    ? 'border-red-500'
                                                    : ''
                                            }
                                            placeholder="Enter quantity"
                                        />
                                        {errors.quantity_received && (
                                            <p className="text-sm text-red-500">
                                                {errors.quantity_received}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="cost_per_unit">
                                            Cost per Unit *
                                        </Label>
                                        <Input
                                            id="cost_per_unit"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={data.cost_per_unit}
                                            onChange={(e) =>
                                                setData(
                                                    'cost_per_unit',
                                                    e.target.value,
                                                )
                                            }
                                            className={
                                                errors.cost_per_unit
                                                    ? 'border-red-500'
                                                    : ''
                                            }
                                            placeholder="0.00"
                                        />
                                        {errors.cost_per_unit && (
                                            <p className="text-sm text-red-500">
                                                {errors.cost_per_unit}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="selling_price_per_unit">
                                            Selling Price per Unit *
                                        </Label>
                                        <Input
                                            id="selling_price_per_unit"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={data.selling_price_per_unit}
                                            onChange={(e) =>
                                                setData(
                                                    'selling_price_per_unit',
                                                    e.target.value,
                                                )
                                            }
                                            className={
                                                errors.selling_price_per_unit
                                                    ? 'border-red-500'
                                                    : ''
                                            }
                                            placeholder="0.00"
                                        />
                                        {errors.selling_price_per_unit && (
                                            <p className="text-sm text-red-500">
                                                {errors.selling_price_per_unit}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="received_date">
                                            Received Date *
                                        </Label>
                                        <Input
                                            id="received_date"
                                            type="date"
                                            value={data.received_date}
                                            onChange={(e) =>
                                                setData(
                                                    'received_date',
                                                    e.target.value,
                                                )
                                            }
                                            className={
                                                errors.received_date
                                                    ? 'border-red-500'
                                                    : ''
                                            }
                                        />
                                        {errors.received_date && (
                                            <p className="text-sm text-red-500">
                                                {errors.received_date}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="notes">Notes</Label>
                                    <Textarea
                                        id="notes"
                                        value={data.notes}
                                        onChange={(e) =>
                                            setData('notes', e.target.value)
                                        }
                                        placeholder="Additional notes about this batch"
                                        rows={3}
                                    />
                                </div>

                                <div className="flex justify-end gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setShowAddForm(false)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        {processing ? 'Adding...' : 'Add Batch'}
                                    </Button>
                                </div>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>

                {/* Batch Statistics */}
                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Total Batches
                            </CardTitle>
                            <Package className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-blue-600">
                                {batches?.total || 0}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Active batches
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Total Stock
                            </CardTitle>
                            <Package className="h-4 w-4 text-green-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600">
                                {totalStock}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                {drug.unit_type} available
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Expiring Soon
                            </CardTitle>
                            <Calendar className="h-4 w-4 text-orange-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-orange-600">
                                {expiringSoon}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Within 30 days
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Total Value
                            </CardTitle>
                            <Building className="h-4 w-4 text-blue-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-blue-600">
                                ${totalValue.toLocaleString()}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Current inventory value
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Alert for Expired Batches */}
                {expiredBatches > 0 && (
                    <div className="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-950">
                        <div className="flex items-start gap-3">
                            <AlertCircle className="mt-0.5 h-5 w-5 text-red-600" />
                            <div>
                                <h3 className="font-medium text-red-900 dark:text-red-100">
                                    Expired Batches Detected
                                </h3>
                                <p className="mt-1 text-sm text-red-700 dark:text-red-300">
                                    {expiredBatches} batch
                                    {expiredBatches !== 1
                                        ? 'es have'
                                        : ' has'}{' '}
                                    expired and should be removed from inventory
                                    immediately.
                                </p>
                            </div>
                        </div>
                    </div>
                )}

                {/* Batches Table */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Package className="h-5 w-5" />
                            Drug Batches ({batches?.total || 0})
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {batches?.data?.length > 0 ? (
                            <div className="space-y-4">
                                <div className="rounded-md border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>
                                                    Batch Info
                                                </TableHead>
                                                <TableHead>Supplier</TableHead>
                                                <TableHead>Stock</TableHead>
                                                <TableHead>
                                                    Expiry Status
                                                </TableHead>
                                                <TableHead>Financial</TableHead>
                                                <TableHead>Actions</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {batches?.data?.map((batch) => {
                                                const status =
                                                    getBatchStatus(batch);
                                                const daysUntilExpiry =
                                                    getDaysUntilExpiry(
                                                        batch.expiry_date,
                                                    );

                                                return (
                                                    <TableRow key={batch.id}>
                                                        <TableCell>
                                                            <div className="space-y-1">
                                                                <div className="font-medium">
                                                                    {
                                                                        batch.batch_number
                                                                    }
                                                                </div>
                                                                <div className="text-sm text-muted-foreground">
                                                                    Received:{' '}
                                                                    {formatDate(
                                                                        batch.received_date,
                                                                    )}
                                                                </div>
                                                            </div>
                                                        </TableCell>
                                                        <TableCell>
                                                            <div className="flex items-center gap-1">
                                                                <Building className="h-3 w-3 text-muted-foreground" />
                                                                {
                                                                    batch
                                                                        .supplier
                                                                        .name
                                                                }
                                                            </div>
                                                        </TableCell>
                                                        <TableCell>
                                                            <div className="space-y-1">
                                                                <div className="font-medium">
                                                                    {
                                                                        batch.quantity_remaining
                                                                    }{' '}
                                                                    /{' '}
                                                                    {
                                                                        batch.quantity_received
                                                                    }
                                                                </div>
                                                                <div className="text-sm text-muted-foreground">
                                                                    {
                                                                        drug.unit_type
                                                                    }
                                                                </div>
                                                            </div>
                                                        </TableCell>
                                                        <TableCell>
                                                            <div className="space-y-2">
                                                                <Badge
                                                                    className={
                                                                        status.className
                                                                    }
                                                                >
                                                                    {
                                                                        status.label
                                                                    }
                                                                </Badge>
                                                                <div className="text-sm text-muted-foreground">
                                                                    {formatDate(
                                                                        batch.expiry_date,
                                                                    )}
                                                                </div>
                                                                <div className="text-xs text-muted-foreground">
                                                                    {daysUntilExpiry <
                                                                    0
                                                                        ? `${Math.abs(daysUntilExpiry)} days ago`
                                                                        : daysUntilExpiry ===
                                                                            0
                                                                          ? 'Today'
                                                                          : `${daysUntilExpiry} days`}
                                                                </div>
                                                            </div>
                                                        </TableCell>
                                                        <TableCell>
                                                            <div className="space-y-1">
                                                                <div className="font-medium">
                                                                    $
                                                                    {(
                                                                        batch.quantity_remaining *
                                                                        Number(
                                                                            batch.selling_price_per_unit,
                                                                        )
                                                                    ).toLocaleString()}
                                                                </div>
                                                                <div className="text-sm text-muted-foreground">
                                                                    $
                                                                    {Number(
                                                                        batch.selling_price_per_unit,
                                                                    ).toFixed(
                                                                        2,
                                                                    )}{' '}
                                                                    per{' '}
                                                                    {
                                                                        drug.unit_type
                                                                    }
                                                                </div>
                                                                <div className="text-xs text-muted-foreground">
                                                                    Cost: $
                                                                    {Number(
                                                                        batch.cost_per_unit,
                                                                    ).toFixed(
                                                                        2,
                                                                    )}
                                                                </div>
                                                            </div>
                                                        </TableCell>
                                                        <TableCell>
                                                            <Button
                                                                size="sm"
                                                                variant="ghost"
                                                            >
                                                                <Eye className="mr-1 h-3 w-3" />
                                                                View
                                                            </Button>
                                                        </TableCell>
                                                    </TableRow>
                                                );
                                            })}
                                        </TableBody>
                                    </Table>
                                </div>

                                {/* Pagination */}
                                {batches?.links && (
                                    <div className="flex items-center justify-between">
                                        <div className="text-sm text-muted-foreground">
                                            Showing{' '}
                                            {Math.min(
                                                ((batches?.current_page || 1) -
                                                    1) *
                                                    (batches?.per_page || 10) +
                                                    1,
                                                batches?.total || 0,
                                            )}{' '}
                                            to{' '}
                                            {Math.min(
                                                (batches?.current_page || 1) *
                                                    (batches?.per_page || 10),
                                                batches?.total || 0,
                                            )}{' '}
                                            of {batches?.total || 0} batch(es).
                                        </div>
                                        <div className="flex items-center space-x-2">
                                            {batches?.links?.map(
                                                (link, index) => {
                                                    if (!link.url) {
                                                        return (
                                                            <Button
                                                                key={index}
                                                                variant="outline"
                                                                size="sm"
                                                                disabled
                                                                className="opacity-50"
                                                            >
                                                                {link.label ===
                                                                '&laquo; Previous' ? (
                                                                    <ChevronLeft className="h-4 w-4" />
                                                                ) : link.label ===
                                                                  'Next &raquo;' ? (
                                                                    <ChevronRight className="h-4 w-4" />
                                                                ) : (
                                                                    <span
                                                                        dangerouslySetInnerHTML={{
                                                                            __html: link.label,
                                                                        }}
                                                                    />
                                                                )}
                                                            </Button>
                                                        );
                                                    }

                                                    return (
                                                        <Button
                                                            key={index}
                                                            variant={
                                                                link.active
                                                                    ? 'default'
                                                                    : 'outline'
                                                            }
                                                            size="sm"
                                                            asChild
                                                        >
                                                            <Link
                                                                href={link.url}
                                                            >
                                                                {link.label ===
                                                                '&laquo; Previous' ? (
                                                                    <ChevronLeft className="h-4 w-4" />
                                                                ) : link.label ===
                                                                  'Next &raquo;' ? (
                                                                    <ChevronRight className="h-4 w-4" />
                                                                ) : (
                                                                    <span
                                                                        dangerouslySetInnerHTML={{
                                                                            __html: link.label,
                                                                        }}
                                                                    />
                                                                )}
                                                            </Link>
                                                        </Button>
                                                    );
                                                },
                                            )}
                                        </div>
                                    </div>
                                )}
                            </div>
                        ) : (
                            <div className="py-12 text-center">
                                <Package className="mx-auto mb-4 h-16 w-16 text-muted-foreground opacity-50" />
                                <h3 className="mb-2 text-lg font-medium">
                                    No Batches Found
                                </h3>
                                <p className="mb-4 text-muted-foreground">
                                    This drug doesn't have any batches yet. Add
                                    your first batch to start tracking
                                    inventory.
                                </p>
                                <Button onClick={() => setShowAddForm(true)}>
                                    <Plus className="mr-1 h-4 w-4" />
                                    Add First Batch
                                </Button>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
