import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { StatCard } from '@/components/ui/stat-card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { BedAssignmentModal } from '@/components/Ward/BedAssignmentModal';
import { useVitalsAlerts } from '@/hooks/use-vitals-alerts';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import {
    Activity,
    AlertCircle,
    ArrowLeft,
    Bed,
    Edit,
    Hospital,
    Pill,
    Settings,
    Thermometer,
    User,
    UserCheck,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import {
    createWardPatientsColumns,
    WardPatientData,
} from './ward-patients-columns';
import { WardPatientsDataTable } from './ward-patients-data-table';

interface BedData {
    id: number;
    ward_id: number;
    bed_number: string;
    status: 'available' | 'occupied' | 'maintenance' | 'cleaning';
    type: 'standard' | 'icu' | 'isolation' | 'private';
    is_active: boolean;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedAdmissions {
    data: WardPatientData[];
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
    links: PaginationLink[];
}

interface Ward {
    id: number;
    name: string;
    code: string;
    description?: string;
    total_beds: number;
    available_beds: number;
    is_active: boolean;
    beds: BedData[];
    created_at: string;
}

interface WardStats {
    total_patients: number;
    pending_meds_count: number;
    vitals_due_count: number;
    vitals_overdue_count: number;
}

interface Props {
    ward: Ward;
    stats: WardStats;
    admissions: PaginatedAdmissions;
    filters?: {
        search?: string;
    };
}

export default function WardShow({
    ward,
    stats,
    admissions,
    filters = {},
}: Props) {
    const [bedModalOpen, setBedModalOpen] = useState(false);
    const [selectedAdmission, setSelectedAdmission] =
        useState<WardPatientData | null>(null);
    const [isChangingBed, setIsChangingBed] = useState(false);

    // Fetch vitals alerts for this ward (toasts are handled globally in AppLayout)
    const { alerts } = useVitalsAlerts({
        wardId: ward.id,
        pollingInterval: 30000,
        enabled: true,
    });

    // Create columns with wardId
    const columns = createWardPatientsColumns(ward.id);

    // Get current admissions for bed display
    const currentAdmissions = admissions.data;

    // Get available beds for the modal
    const availableBeds = ward.beds.filter((bed) => bed.status === 'available');

    // Handle bed action from table
    const handleBedAction = (
        admission: WardPatientData,
        action: 'assign' | 'change',
    ) => {
        setSelectedAdmission(admission);
        setIsChangingBed(action === 'change');
        setBedModalOpen(true);
    };

    const handleBedModalClose = () => {
        setBedModalOpen(false);
        setSelectedAdmission(null);
        setIsChangingBed(false);
    };

    const getBedStatusColor = (status: string) => {
        const colors = {
            available:
                'bg-green-100 text-green-800 border-green-200 dark:bg-green-900 dark:text-green-200 dark:border-green-800',
            occupied:
                'bg-red-100 text-red-800 border-red-200 dark:bg-red-900 dark:text-red-200 dark:border-red-800',
            maintenance:
                'bg-yellow-100 text-yellow-800 border-yellow-200 dark:bg-yellow-900 dark:text-yellow-200 dark:border-yellow-800',
            cleaning:
                'bg-blue-100 text-blue-800 border-blue-200 dark:bg-blue-900 dark:text-blue-200 dark:border-blue-800',
        };
        return (
            colors[status as keyof typeof colors] ||
            'bg-gray-100 text-gray-800 border-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700'
        );
    };

    const getBedTypeIcon = (type: string) => {
        switch (type) {
            case 'icu':
                return <Activity className="h-4 w-4" />;
            case 'isolation':
                return <Settings className="h-4 w-4" />;
            case 'private':
                return <UserCheck className="h-4 w-4" />;
            default:
                return <Bed className="h-4 w-4" />;
        }
    };

    const getOccupancyRate = () => {
        if (ward.total_beds === 0) return 0;
        const occupied = ward.total_beds - ward.available_beds;
        return Math.round((occupied / ward.total_beds) * 100);
    };

    const formatDateTime = (dateString: string) => {
        return new Date(dateString).toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Wards', href: '/wards' },
                { title: ward.name, href: '' },
            ]}
        >
            <Head title={`${ward.name} - Ward Details`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div className="flex items-center gap-4">
                        <Link href="/wards">
                            <Button variant="ghost" size="sm">
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Back to Wards
                            </Button>
                        </Link>
                        <div>
                            <div className="flex items-center gap-3">
                                <h1 className="flex items-center gap-2 text-3xl font-bold text-gray-900 dark:text-gray-100">
                                    <Hospital className="h-8 w-8" />
                                    {ward.name}
                                </h1>
                                <Badge
                                    variant={
                                        ward.is_active ? 'default' : 'secondary'
                                    }
                                >
                                    {ward.is_active ? 'Active' : 'Inactive'}
                                </Badge>
                            </div>
                            <p className="mt-2 text-gray-600 dark:text-gray-400">
                                Code: {ward.code}
                            </p>
                            {ward.description && (
                                <p className="mt-1 text-gray-600 dark:text-gray-400">
                                    {ward.description}
                                </p>
                            )}
                        </div>
                    </div>

                    <Link href={`/wards/${ward.id}/edit`}>
                        <Button>
                            <Edit className="mr-2 h-4 w-4" />
                            Edit Ward
                        </Button>
                    </Link>
                </div>

                {/* Stats Cards */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-7">
                    <StatCard
                        label="Total Beds"
                        value={ward.total_beds}
                        icon={<Bed className="h-4 w-4" />}
                        variant="info"
                    />
                    <StatCard
                        label="Available"
                        value={ward.available_beds}
                        icon={<Users className="h-4 w-4" />}
                        variant="success"
                    />
                    <StatCard
                        label="Occupied"
                        value={ward.total_beds - ward.available_beds}
                        icon={<User className="h-4 w-4" />}
                        variant="error"
                    />
                    <StatCard
                        label="Occupancy"
                        value={`${getOccupancyRate()}%`}
                        icon={<Activity className="h-4 w-4" />}
                        variant="default"
                    />
                    <StatCard
                        label="Pending Meds"
                        value={stats.pending_meds_count}
                        icon={<Pill className="h-4 w-4" />}
                        variant="warning"
                    />
                    <StatCard
                        label="Vitals Due"
                        value={stats.vitals_due_count}
                        icon={<Thermometer className="h-4 w-4" />}
                        variant="warning"
                    />
                    <StatCard
                        label="Vitals Overdue"
                        value={stats.vitals_overdue_count}
                        icon={<AlertCircle className="h-4 w-4" />}
                        variant="error"
                    />
                </div>

                <Tabs defaultValue="patients" className="w-full">
                    <TabsList>
                        <TabsTrigger
                            value="patients"
                            className="flex items-center gap-2"
                        >
                            <Users className="h-4 w-4" />
                            Current Patients ({admissions.total})
                        </TabsTrigger>
                        <TabsTrigger
                            value="beds"
                            className="flex items-center gap-2"
                        >
                            <Bed className="h-4 w-4" />
                            Beds
                        </TabsTrigger>
                    </TabsList>

                    {/* Beds Tab */}
                    <TabsContent value="beds">
                        <Card>
                            <CardHeader>
                                <CardTitle>Bed Management</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {ward.beds.length > 0 ? (
                                    <div className="grid grid-cols-2 gap-4 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8">
                                        {ward.beds
                                            .sort((a, b) =>
                                                a.bed_number.localeCompare(
                                                    b.bed_number,
                                                ),
                                            )
                                            .map((bed) => {
                                                const currentPatient =
                                                    currentAdmissions.find(
                                                        (admission) =>
                                                            admission.bed_id ===
                                                            bed.id,
                                                    );

                                                const bedContent = (
                                                    <div className="text-center">
                                                        <div className="mb-2 flex justify-center">
                                                            {getBedTypeIcon(
                                                                bed.type,
                                                            )}
                                                        </div>
                                                        <div className="text-sm font-semibold">
                                                            Bed {bed.bed_number}
                                                        </div>
                                                        <div className="text-xs text-gray-600 capitalize dark:text-gray-400">
                                                            {bed.type}
                                                        </div>
                                                        <div className="mt-2">
                                                            <Badge
                                                                variant="outline"
                                                                className={`text-xs ${getBedStatusColor(bed.status).split(' ')[1]} ${getBedStatusColor(bed.status).split(' ')[2]}`}
                                                            >
                                                                {bed.status}
                                                            </Badge>
                                                        </div>
                                                        {currentPatient && (
                                                            <div className="bg-opacity-50 dark:bg-opacity-50 mt-2 rounded bg-white p-2 text-xs dark:bg-gray-900">
                                                                <div className="font-medium dark:text-gray-100">
                                                                    {
                                                                        currentPatient
                                                                            .patient
                                                                            .first_name
                                                                    }{' '}
                                                                    {
                                                                        currentPatient
                                                                            .patient
                                                                            .last_name
                                                                    }
                                                                </div>
                                                                <div className="text-gray-600 dark:text-gray-400">
                                                                    {
                                                                        currentPatient.admission_number
                                                                    }
                                                                </div>
                                                            </div>
                                                        )}
                                                    </div>
                                                );

                                                // If bed is occupied, make it clickable to go to patient page
                                                if (currentPatient) {
                                                    return (
                                                        <Link
                                                            key={bed.id}
                                                            href={`/wards/${ward.id}/patients/${currentPatient.id}`}
                                                            className={`relative block rounded-lg border-2 p-4 transition-transform hover:scale-105 hover:shadow-lg ${getBedStatusColor(bed.status)} ${!bed.is_active ? 'opacity-50' : ''} cursor-pointer`}
                                                        >
                                                            {bedContent}
                                                        </Link>
                                                    );
                                                }

                                                return (
                                                    <div
                                                        key={bed.id}
                                                        className={`relative rounded-lg border-2 p-4 ${getBedStatusColor(bed.status)} ${!bed.is_active ? 'opacity-50' : ''}`}
                                                    >
                                                        {bedContent}
                                                    </div>
                                                );
                                            })}
                                    </div>
                                ) : (
                                    <div className="py-8 text-center text-gray-500 dark:text-gray-400">
                                        <Bed className="mx-auto mb-4 h-12 w-12 text-gray-300 dark:text-gray-600" />
                                        <p>No beds configured for this ward</p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Current Patients Tab */}
                    <TabsContent value="patients">
                        <Card>
                            <CardHeader>
                                <CardTitle>Current Patients</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <WardPatientsDataTable
                                    columns={columns}
                                    data={admissions.data}
                                    pagination={admissions}
                                    searchValue={filters.search ?? ''}
                                    wardId={ward.id}
                                    onBedAction={handleBedAction}
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>

            {/* Bed Assignment Modal */}
            {selectedAdmission && (
                <BedAssignmentModal
                    open={bedModalOpen}
                    onClose={handleBedModalClose}
                    admission={{
                        id: selectedAdmission.id,
                        bed_id: selectedAdmission.bed_id,
                    }}
                    availableBeds={availableBeds}
                    allBeds={ward.beds}
                    hasAvailableBeds={availableBeds.length > 0}
                    isChangingBed={isChangingBed}
                />
            )}
        </AppLayout>
    );
}
