import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { StatCard } from '@/components/ui/stat-card';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    Calendar,
    Clock,
    Eye,
    FileText,
    Package,
    Pill,
} from 'lucide-react';

interface Patient {
    id: number;
    first_name: string;
    last_name: string;
    patient_number: string;
}

interface PatientCheckin {
    patient: Patient;
}

interface Consultation {
    id: number;
    patient_checkin: PatientCheckin;
}

interface Drug {
    id: number;
    name: string;
    form: string;
    unit_type: string;
}

interface Prescription {
    id: number;
    consultation: Consultation | null;
    drug: Drug | null;
    medication_name: string;
    quantity: number;
    status: string;
    created_at: string;
}

interface DrugBatch {
    id: number;
    batch_number: string;
    expiry_date: string;
    quantity_remaining: number;
    drug: {
        id: number;
        name: string;
    };
}

interface LowStockDrug {
    id: number;
    name: string;
    total_stock: number;
    minimum_stock_level: number;
    unit_type: string;
}

interface Stats {
    pending_prescriptions: number;
    dispensed_today: number;
    low_stock_drugs: number;
    expiring_soon: number;
}

interface Props {
    stats: Stats;
    pendingPrescriptions: Prescription[];
    lowStockDrugs: LowStockDrug[];
    expiringBatches: DrugBatch[];
}

export default function PharmacyIndex({
    stats,
    pendingPrescriptions,
    lowStockDrugs,
    expiringBatches,
}: Props) {
    return (
        <AppLayout breadcrumbs={[{ title: 'Pharmacy', href: '/pharmacy' }]}>
            <Head title="Pharmacy Dashboard" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">
                            Pharmacy Dashboard
                        </h1>
                        <p className="text-muted-foreground">
                            Manage prescriptions, inventory, and dispensing
                            operations
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/pharmacy/inventory">
                                <Package className="mr-2 h-4 w-4" />
                                Inventory
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link href="/pharmacy/dispensing">
                                <Pill className="mr-2 h-4 w-4" />
                                Dispensing
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Stats Cards */}
                <div className="grid gap-4 md:grid-cols-4">
                    <StatCard
                        label="Pending Prescriptions"
                        value={stats.pending_prescriptions}
                        icon={<FileText className="h-4 w-4" />}
                        variant="info"
                    />
                    <StatCard
                        label="Dispensed Today"
                        value={stats.dispensed_today}
                        icon={<Activity className="h-4 w-4" />}
                        variant="success"
                    />
                    <StatCard
                        label="Low Stock Items"
                        value={stats.low_stock_drugs}
                        icon={<AlertTriangle className="h-4 w-4" />}
                        variant="warning"
                    />
                    <StatCard
                        label="Expiring Soon"
                        value={stats.expiring_soon}
                        icon={<Calendar className="h-4 w-4" />}
                        variant="error"
                    />
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    {/* Pending Prescriptions */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Pill className="h-5 w-5" />
                                Recent Prescriptions
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {pendingPrescriptions.length > 0 ? (
                                <>
                                    {pendingPrescriptions.map(
                                        (prescription) => (
                                            <div
                                                key={prescription.id}
                                                className="flex items-center justify-between rounded-lg border p-3"
                                            >
                                                <div className="space-y-1">
                                                    <div className="font-medium">
                                                        {prescription
                                                            .consultation
                                                            ?.patient_checkin
                                                            ?.patient
                                                            ?.first_name ||
                                                            'Unknown'}{' '}
                                                        {prescription
                                                            .consultation
                                                            ?.patient_checkin
                                                            ?.patient
                                                            ?.last_name ||
                                                            'Patient'}
                                                    </div>
                                                    <div className="text-sm text-muted-foreground">
                                                        {prescription.drug
                                                            ?.name ||
                                                            prescription.medication_name}
                                                    </div>
                                                    <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                                        <Clock className="h-3 w-3" />
                                                        {new Date(
                                                            prescription.created_at,
                                                        ).toLocaleDateString()}
                                                    </div>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <Badge
                                                        variant="outline"
                                                        className="bg-blue-50 text-blue-700"
                                                    >
                                                        Qty:{' '}
                                                        {prescription.quantity}
                                                    </Badge>
                                                    <Button size="sm" asChild>
                                                        <Link
                                                            href={`/pharmacy/prescriptions/${prescription.id}/dispense`}
                                                        >
                                                            <Eye className="mr-1 h-3 w-3" />
                                                            Dispense
                                                        </Link>
                                                    </Button>
                                                </div>
                                            </div>
                                        ),
                                    )}
                                    <div className="pt-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                            className="w-full"
                                        >
                                            <Link href="/pharmacy/dispensing">
                                                View All Prescriptions
                                            </Link>
                                        </Button>
                                    </div>
                                </>
                            ) : (
                                <div className="py-6 text-center text-muted-foreground">
                                    <Pill className="mx-auto mb-3 h-12 w-12 opacity-30" />
                                    <p>No pending prescriptions</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Low Stock & Expiring */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <AlertTriangle className="h-5 w-5 text-orange-600" />
                                Inventory Alerts
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {/* Low Stock Items */}
                            {lowStockDrugs.length > 0 && (
                                <div className="space-y-2">
                                    <h4 className="text-sm font-medium text-orange-600">
                                        Low Stock
                                    </h4>
                                    {lowStockDrugs.map((drug) => (
                                        <div
                                            key={drug.id}
                                            className="flex items-center justify-between rounded bg-orange-50 p-2"
                                        >
                                            <div className="text-sm">
                                                <div className="font-medium">
                                                    {drug.name}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {drug.total_stock}{' '}
                                                    {drug.unit_type} remaining
                                                </div>
                                            </div>
                                            <Badge
                                                variant="outline"
                                                className="bg-orange-100 text-orange-700"
                                            >
                                                Reorder
                                            </Badge>
                                        </div>
                                    ))}
                                </div>
                            )}

                            {/* Expiring Batches */}
                            {expiringBatches.length > 0 && (
                                <div className="space-y-2">
                                    <h4 className="text-sm font-medium text-red-600">
                                        Expiring Soon
                                    </h4>
                                    {expiringBatches.map((batch) => (
                                        <div
                                            key={batch.id}
                                            className="flex items-center justify-between rounded bg-red-50 p-2"
                                        >
                                            <div className="text-sm">
                                                <div className="font-medium">
                                                    {batch.drug.name}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    Batch: {batch.batch_number}{' '}
                                                    • {batch.quantity_remaining}{' '}
                                                    remaining
                                                </div>
                                            </div>
                                            <Badge
                                                variant="outline"
                                                className="bg-red-100 text-red-700"
                                            >
                                                {new Date(
                                                    batch.expiry_date,
                                                ).toLocaleDateString()}
                                            </Badge>
                                        </div>
                                    ))}
                                </div>
                            )}

                            {lowStockDrugs.length === 0 &&
                                expiringBatches.length === 0 && (
                                    <div className="py-6 text-center text-muted-foreground">
                                        <Package className="mx-auto mb-3 h-12 w-12 opacity-30" />
                                        <p>No inventory alerts</p>
                                        <p className="text-xs">
                                            All stock levels are healthy
                                        </p>
                                    </div>
                                )}

                            {(lowStockDrugs.length > 0 ||
                                expiringBatches.length > 0) && (
                                <div className="pt-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        asChild
                                        className="w-full"
                                    >
                                        <Link href="/pharmacy/inventory">
                                            Manage Inventory
                                        </Link>
                                    </Button>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
