import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Key, Shield } from 'lucide-react';

interface Permission {
    id: number;
    name: string;
}

interface RoleData {
    id: number;
    name: string;
    permissions: string[];
}

interface Props {
    role: RoleData;
    permissions: Record<string, Permission[]>;
}

export default function RolesEdit({ role, permissions }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        name: role.name,
        permissions: role.permissions as string[],
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/admin/roles/${role.id}`);
    };

    const togglePermission = (permissionName: string) => {
        const newPermissions = data.permissions.includes(permissionName)
            ? data.permissions.filter((p) => p !== permissionName)
            : [...data.permissions, permissionName];
        setData('permissions', newPermissions);
    };

    const toggleCategory = (category: string) => {
        const categoryPermissions = permissions[category].map((p) => p.name);
        const allSelected = categoryPermissions.every((p) =>
            data.permissions.includes(p),
        );

        if (allSelected) {
            // Deselect all in category
            setData(
                'permissions',
                data.permissions.filter(
                    (p) => !categoryPermissions.includes(p),
                ),
            );
        } else {
            // Select all in category
            const newPermissions = [
                ...new Set([...data.permissions, ...categoryPermissions]),
            ];
            setData('permissions', newPermissions);
        }
    };

    const isCategoryFullySelected = (category: string) => {
        const categoryPermissions = permissions[category].map((p) => p.name);
        return categoryPermissions.every((p) => data.permissions.includes(p));
    };

    const isCategoryPartiallySelected = (category: string) => {
        const categoryPermissions = permissions[category].map((p) => p.name);
        const selectedCount = categoryPermissions.filter((p) =>
            data.permissions.includes(p),
        ).length;
        return selectedCount > 0 && selectedCount < categoryPermissions.length;
    };

    const formatCategoryName = (category: string) => {
        return category
            .split('-')
            .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
            .join(' ');
    };

    const formatPermissionName = (name: string) => {
        // Extract action from permission name (e.g., "users.view-all" -> "View All")
        const parts = name.split('.');
        const action = parts[1] || parts[0];
        return action
            .split('-')
            .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
            .join(' ');
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                { title: 'Roles', href: '/admin/roles' },
                { title: 'Edit Role', href: '' },
            ]}
        >
            <Head title={`Edit Role - ${role.name}`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Link href="/admin/roles">
                        <Button variant="ghost" size="sm">
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to Roles
                        </Button>
                    </Link>
                    <div>
                        <h1 className="flex items-center gap-2 text-3xl font-bold text-gray-900 dark:text-gray-100">
                            <Shield className="h-8 w-8" />
                            Edit Role
                        </h1>
                        <p className="mt-1 text-gray-600 dark:text-gray-400">
                            Update role name and permissions
                        </p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Role Name */}
                    <Card className="max-w-3xl">
                        <CardHeader>
                            <CardTitle>Role Information</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div>
                                <Label htmlFor="name">Role Name *</Label>
                                <Input
                                    id="name"
                                    type="text"
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    placeholder="e.g., Doctor, Nurse, Receptionist"
                                    required
                                    className="mt-1 max-w-md"
                                />
                                {errors.name && (
                                    <p className="mt-1 text-sm text-red-600">
                                        {errors.name}
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Permissions */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Key className="h-5 w-5" />
                                Permissions
                            </CardTitle>
                            <p className="text-sm text-gray-500">
                                Select the permissions this role should have.
                                Selected: {data.permissions.length}
                            </p>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-6">
                                {Object.entries(permissions).map(
                                    ([category, categoryPermissions]) => (
                                        <div
                                            key={category}
                                            className="rounded-lg border p-4 dark:border-gray-700"
                                        >
                                            {/* Category Header */}
                                            <div className="mb-3 flex items-center gap-2 border-b pb-2 dark:border-gray-700">
                                                <Checkbox
                                                    id={`category-${category}`}
                                                    checked={isCategoryFullySelected(
                                                        category,
                                                    )}
                                                    ref={(el) => {
                                                        if (el) {
                                                            (
                                                                el as HTMLButtonElement & {
                                                                    indeterminate: boolean;
                                                                }
                                                            ).indeterminate =
                                                                isCategoryPartiallySelected(
                                                                    category,
                                                                );
                                                        }
                                                    }}
                                                    onCheckedChange={() =>
                                                        toggleCategory(category)
                                                    }
                                                />
                                                <Label
                                                    htmlFor={`category-${category}`}
                                                    className="cursor-pointer text-base font-semibold"
                                                >
                                                    {formatCategoryName(
                                                        category,
                                                    )}
                                                </Label>
                                                <span className="text-sm text-gray-500">
                                                    (
                                                    {
                                                        categoryPermissions.filter(
                                                            (p) =>
                                                                data.permissions.includes(
                                                                    p.name,
                                                                ),
                                                        ).length
                                                    }
                                                    /
                                                    {categoryPermissions.length}
                                                    )
                                                </span>
                                            </div>

                                            {/* Permissions in Category */}
                                            <div className="grid grid-cols-2 gap-2 md:grid-cols-3 lg:grid-cols-4">
                                                {categoryPermissions.map(
                                                    (permission) => (
                                                        <div
                                                            key={permission.id}
                                                            className="flex items-center space-x-2"
                                                        >
                                                            <Checkbox
                                                                id={`permission-${permission.id}`}
                                                                checked={data.permissions.includes(
                                                                    permission.name,
                                                                )}
                                                                onCheckedChange={() =>
                                                                    togglePermission(
                                                                        permission.name,
                                                                    )
                                                                }
                                                            />
                                                            <Label
                                                                htmlFor={`permission-${permission.id}`}
                                                                className="cursor-pointer text-sm font-normal"
                                                                title={
                                                                    permission.name
                                                                }
                                                            >
                                                                {formatPermissionName(
                                                                    permission.name,
                                                                )}
                                                            </Label>
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                        </div>
                                    ),
                                )}
                            </div>
                            {errors.permissions && (
                                <p className="mt-2 text-sm text-red-600">
                                    {errors.permissions}
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    {/* Actions */}
                    <div className="flex gap-4">
                        <Link href="/admin/roles">
                            <Button type="button" variant="outline">
                                Cancel
                            </Button>
                        </Link>
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Saving...' : 'Save Changes'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
