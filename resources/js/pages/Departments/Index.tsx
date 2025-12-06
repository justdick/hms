import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { Building2, Plus, Users } from 'lucide-react';
import { useState } from 'react';
import { columns } from './columns';
import { DataTable } from './data-table';
import DepartmentModal, { Department } from './DepartmentModal';

interface Props {
    departments: {
        data: Department[];
        links: unknown;
        meta: unknown;
    };
    types?: Record<string, string>;
}

const defaultTypes: Record<string, string> = {
    opd: 'Outpatient (OPD)',
    ipd: 'Inpatient (IPD)',
    diagnostic: 'Diagnostic',
    support: 'Support',
};

export default function DepartmentsIndex({ departments, types = defaultTypes }: Props) {
    const [showModal, setShowModal] = useState(false);
    const [editingDepartment, setEditingDepartment] = useState<Department | null>(null);

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

    const data = departments.data;

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
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Total
                                    </p>
                                    <p className="text-3xl font-bold text-gray-900 dark:text-gray-100">
                                        {data.length}
                                    </p>
                                </div>
                                <Building2 className="h-8 w-8 text-blue-600" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Active
                                    </p>
                                    <p className="text-3xl font-bold text-green-600">
                                        {data.filter((d) => d.is_active).length}
                                    </p>
                                </div>
                                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-green-100 dark:bg-green-900">
                                    <div className="h-3 w-3 rounded-full bg-green-600" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        OPD Clinics
                                    </p>
                                    <p className="text-3xl font-bold text-blue-600">
                                        {data.filter((d) => d.type === 'opd').length}
                                    </p>
                                </div>
                                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900">
                                    <div className="h-3 w-3 rounded-full bg-blue-600" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        Staff Assigned
                                    </p>
                                    <p className="text-3xl font-bold text-gray-900 dark:text-gray-100">
                                        {data.reduce((sum, d) => sum + (d.users_count || 0), 0)}
                                    </p>
                                </div>
                                <Users className="h-8 w-8 text-purple-600" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardContent className="p-6">
                        <DataTable columns={columns(handleEdit, handleDelete)} data={data} />
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
