import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { StatCard } from '@/components/ui/stat-card';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { Building2, CheckCircle, Plus, Stethoscope, Users } from 'lucide-react';
import { useState } from 'react';
import { columns } from './columns';
import { DataTable } from './data-table';
import DepartmentModal, { Department } from './DepartmentModal';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedDepartments {
    data: Department[];
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
    links: PaginationLink[];
}

interface Stats {
    total: number;
    active: number;
    opd: number;
    staff_count: number;
}

interface Filters {
    search?: string;
    type?: string;
    status?: string;
}

interface Props {
    departments: PaginatedDepartments;
    types?: Record<string, string>;
    stats: Stats;
    filters: Filters;
}

const defaultTypes: Record<string, string> = {
    opd: 'Outpatient (OPD)',
    ipd: 'Inpatient (IPD)',
    diagnostic: 'Diagnostic',
    support: 'Support',
};

export default function DepartmentsIndex({
    departments,
    types = defaultTypes,
    stats,
    filters,
}: Props) {
    const [showModal, setShowModal] = useState(false);
    const [editingDepartment, setEditingDepartment] =
        useState<Department | null>(null);

    const handleEdit = (department: Department) => {
        setEditingDepartment(department);
        setShowModal(true);
    };

    const handleDelete = (department: Department) => {
        if (
            confirm(
                `Are you sure you want to delete "${department.name}"? This action cannot be undone.`,
            )
        ) {
            router.delete(`/departments/${department.id}`);
        }
    };

    const handleCloseModal = () => {
        setShowModal(false);
        setEditingDepartment(null);
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Settings', href: '#' },
                { title: 'Departments', href: '' },
            ]}
        >
            <Head title="Departments" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="flex items-center gap-2 text-3xl font-bold text-gray-900 dark:text-gray-100">
                            <Building2 className="h-8 w-8" />
                            Departments / Clinics
                        </h1>
                        <p className="mt-2 text-gray-600 dark:text-gray-400">
                            Manage hospital departments and clinics
                        </p>
                    </div>
                    <Button onClick={() => setShowModal(true)}>
                        <Plus className="mr-2 h-4 w-4" />
                        Add Department
                    </Button>
                </div>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <StatCard
                        label="Total Departments"
                        value={stats.total}
                        icon={<Building2 className="h-4 w-4" />}
                        variant="info"
                    />
                    <StatCard
                        label="Active"
                        value={stats.active}
                        icon={<CheckCircle className="h-4 w-4" />}
                        variant="success"
                    />
                    <StatCard
                        label="OPD Clinics"
                        value={stats.opd}
                        icon={<Stethoscope className="h-4 w-4" />}
                        variant="info"
                    />
                    <StatCard
                        label="Staff Assigned"
                        value={stats.staff_count}
                        icon={<Users className="h-4 w-4" />}
                        variant="default"
                    />
                </div>

                <Card>
                    <CardContent className="p-6">
                        <DataTable
                            columns={columns(handleEdit, handleDelete)}
                            data={departments.data}
                            pagination={departments}
                            types={types}
                            filters={filters}
                        />
                    </CardContent>
                </Card>
            </div>

            <DepartmentModal
                open={showModal}
                onClose={handleCloseModal}
                department={editingDepartment}
                types={types}
            />
        </AppLayout>
    );
}
