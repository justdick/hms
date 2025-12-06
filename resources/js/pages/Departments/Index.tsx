import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { Building2, Edit, Plus, Trash2, Users } from 'lucide-react';

interface Department {
    id: number;
    name: string;
    code: string;
    description?: string;
    type: 'opd' | 'ipd' | 'diagnostic' | 'support';
    is_active: boolean;
    checkins_count?: number;
    users_count?: number;
}

interface Props {
    departments: {
        data: Department[];
        links: unknown;
        meta: unknown;
    };
}

const typeLabels: Record<string, string> = {
    opd: 'Outpatient',
    ipd: 'Inpatient',
    diagnostic: 'Diagnostic',
    support: 'Support',
};

const typeColors: Record<string, string> = {
    opd: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
    ipd: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
    diagnostic: 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-300',
    support: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
};

export default function DepartmentsIndex({ departments }: Props) {
    const handleDelete = (department: Department) => {
        if (
            confirm(
                `Are you sure you want to delete "${department.name}"? This action cannot be undone.`,
            )
        ) {
            router.delete(`/departments/${department.id}`);
        }
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
                    <Link href="/departments/create">
                        <Button>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Department
                        </Button>
                    </Link>
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
                                        {departments.data.length}
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
                                        {departments.data.filter((d) => d.is_active).length}
                                    </p>
                                </div>
                                <div className="h-8 w-8 rounded-full bg-green-100 dark:bg-green-900" />
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
                                        {departments.data.filter((d) => d.type === 'opd').length}
                                    </p>
                                </div>
                                <div className="h-8 w-8 rounded-full bg-blue-100 dark:bg-blue-900" />
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
                                        {departments.data.reduce((sum, d) => sum + (d.users_count || 0), 0)}
                                    </p>
                                </div>
                                <Users className="h-8 w-8 text-purple-600" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Code</TableHead>
                                    <TableHead>Type</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">Staff</TableHead>
                                    <TableHead className="text-right">Check-ins</TableHead>
                                    <TableHead className="w-[100px]">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {departments.data.length > 0 ? (
                                    departments.data.map((department) => (
                                        <TableRow key={department.id}>
                                            <TableCell>
                                                <div>
                                                    <p className="font-medium">{department.name}</p>
                                                    {department.description && (
                                                        <p className="text-sm text-gray-500 dark:text-gray-400">
                                                            {department.description}
                                                        </p>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <code className="rounded bg-gray-100 px-2 py-1 text-sm dark:bg-gray-800">
                                                    {department.code}
                                                </code>
                                            </TableCell>
                                            <TableCell>
                                                <span className={`inline-flex rounded-full px-2 py-1 text-xs font-medium ${typeColors[department.type]}`}>
                                                    {typeLabels[department.type]}
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant={department.is_active ? 'default' : 'secondary'}>
                                                    {department.is_active ? 'Active' : 'Inactive'}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {department.users_count || 0}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {department.checkins_count || 0}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex gap-1">
                                                    <Link href={`/departments/${department.id}/edit`}>
                                                        <Button variant="ghost" size="sm">
                                                            <Edit className="h-4 w-4" />
                                                        </Button>
                                                    </Link>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => handleDelete(department)}
                                                        className="text-red-600 hover:text-red-700"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                ) : (
                                    <TableRow>
                                        <TableCell colSpan={7} className="py-12 text-center">
                                            <Building2 className="mx-auto mb-4 h-12 w-12 text-gray-300 dark:text-gray-600" />
                                            <p className="text-gray-500 dark:text-gray-400">
                                                No departments found. Add your first department to get started.
                                            </p>
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
