import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ChevronDown, Key, UserCog, X } from 'lucide-react';
import { useState } from 'react';

interface Role {
    id: number;
    name: string;
}

interface Department {
    id: number;
    name: string;
}

interface Permission {
    id: number;
    name: string;
}

interface UserData {
    id: number;
    name: string;
    username: string;
    is_active: boolean;
    roles: string[];
    departments: number[];
    direct_permissions: string[];
}

interface Props {
    user: UserData;
    roles: Role[];
    departments: Department[];
    permissions: Record<string, Permission[]>;
}

export default function UsersEdit({ user, roles, departments, permissions }: Props) {
    const [permissionsOpen, setPermissionsOpen] = useState(false);
    
    const { data, setData, put, processing, errors } = useForm({
        name: user.name,
        username: user.username,
        roles: user.roles,
        departments: user.departments,
        direct_permissions: user.direct_permissions || [],
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/admin/users/${user.id}`);
    };

    const toggleRole = (roleName: string) => {
        const newRoles = data.roles.includes(roleName)
            ? data.roles.filter((r) => r !== roleName)
            : [...data.roles, roleName];
        setData('roles', newRoles);
    };

    const toggleDepartment = (deptId: number) => {
        const newDepts = data.departments.includes(deptId)
            ? data.departments.filter((d) => d !== deptId)
            : [...data.departments, deptId];
        setData('departments', newDepts);
    };

    const toggleDirectPermission = (permissionName: string) => {
        const newPermissions = data.direct_permissions.includes(permissionName)
            ? data.direct_permissions.filter((p) => p !== permissionName)
            : [...data.direct_permissions, permissionName];
        setData('direct_permissions', newPermissions);
    };

    const toggleCategory = (category: string) => {
        const categoryPermissions = permissions[category].map((p) => p.name);
        const allSelected = categoryPermissions.every((p) => data.direct_permissions.includes(p));

        if (allSelected) {
            setData('direct_permissions', data.direct_permissions.filter((p) => !categoryPermissions.includes(p)));
        } else {
            const newPermissions = [...new Set([...data.direct_permissions, ...categoryPermissions])];
            setData('direct_permissions', newPermissions);
        }
    };

    const isCategoryFullySelected = (category: string) => {
        const categoryPermissions = permissions[category].map((p) => p.name);
        return categoryPermissions.every((p) => data.direct_permissions.includes(p));
    };

    const isCategoryPartiallySelected = (category: string) => {
        const categoryPermissions = permissions[category].map((p) => p.name);
        const selectedCount = categoryPermissions.filter((p) => data.direct_permissions.includes(p)).length;
        return selectedCount > 0 && selectedCount < categoryPermissions.length;
    };

    const formatCategoryName = (category: string) => {
        return category
            .split('-')
            .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
            .join(' ');
    };

    const formatPermissionName = (name: string) => {
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
                { title: 'Users', href: '/admin/users' },
                { title: 'Edit User', href: '' },
            ]}
        >
            <Head title={`Edit User - ${user.name}`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Link href="/admin/users">
                        <Button variant="ghost" size="sm">
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to Users
                        </Button>
                    </Link>
                    <div>
                        <h1 className="flex items-center gap-2 text-3xl font-bold text-gray-900 dark:text-gray-100">
                            <UserCog className="h-8 w-8" />
                            Edit User
                        </h1>
                        <p className="mt-1 text-gray-600 dark:text-gray-400">
                            Update user information and access
                        </p>
                    </div>
                </div>

                <Card className="max-w-3xl">
                    <CardHeader>
                        <CardTitle>User Information</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-6">
                            {/* Status Badge */}
                            <div className="flex items-center gap-2">
                                <span className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Status:
                                </span>
                                {user.is_active ? (
                                    <Badge className="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                        Active
                                    </Badge>
                                ) : (
                                    <Badge variant="destructive">Inactive</Badge>
                                )}
                            </div>

                            {/* Basic Information */}
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <Label htmlFor="name">Full Name *</Label>
                                    <Input
                                        id="name"
                                        type="text"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder="e.g., John Doe"
                                        required
                                        className="mt-1"
                                    />
                                    {errors.name && (
                                        <p className="mt-1 text-sm text-red-600">{errors.name}</p>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="username">Username *</Label>
                                    <Input
                                        id="username"
                                        type="text"
                                        value={data.username}
                                        onChange={(e) => setData('username', e.target.value.toLowerCase())}
                                        placeholder="e.g., johndoe"
                                        minLength={4}
                                        required
                                        className="mt-1"
                                    />
                                    <p className="mt-1 text-xs text-gray-500">
                                        Minimum 4 characters, alphanumeric only
                                    </p>
                                    {errors.username && (
                                        <p className="mt-1 text-sm text-red-600">{errors.username}</p>
                                    )}
                                </div>
                            </div>

                            {/* Roles Selection */}
                            <div className="space-y-4 border-t pt-4 dark:border-gray-700">
                                <div>
                                    <Label>Roles *</Label>
                                    <p className="text-sm text-gray-500">
                                        Select at least one role for this user
                                    </p>
                                </div>

                                {/* Selected Roles */}
                                {data.roles.length > 0 && (
                                    <div className="flex flex-wrap gap-2">
                                        {data.roles.map((roleName) => (
                                            <Badge
                                                key={roleName}
                                                variant="secondary"
                                                className="cursor-pointer"
                                                onClick={() => toggleRole(roleName)}
                                            >
                                                {roleName}
                                                <X className="ml-1 h-3 w-3" />
                                            </Badge>
                                        ))}
                                    </div>
                                )}

                                {/* Role Checkboxes */}
                                <div className="grid grid-cols-2 gap-3 md:grid-cols-3">
                                    {roles.map((role) => (
                                        <div
                                            key={role.id}
                                            className="flex items-center space-x-2"
                                        >
                                            <Checkbox
                                                id={`role-${role.id}`}
                                                checked={data.roles.includes(role.name)}
                                                onCheckedChange={() => toggleRole(role.name)}
                                            />
                                            <Label
                                                htmlFor={`role-${role.id}`}
                                                className="cursor-pointer text-sm font-normal"
                                            >
                                                {role.name}
                                            </Label>
                                        </div>
                                    ))}
                                </div>
                                {errors.roles && (
                                    <p className="text-sm text-red-600">{errors.roles}</p>
                                )}
                            </div>

                            {/* Departments Selection */}
                            <div className="space-y-4 border-t pt-4 dark:border-gray-700">
                                <div>
                                    <Label>Departments</Label>
                                    <p className="text-sm text-gray-500">
                                        Optionally assign the user to one or more departments
                                    </p>
                                </div>

                                {/* Selected Departments */}
                                {data.departments.length > 0 && (
                                    <div className="flex flex-wrap gap-2">
                                        {data.departments.map((deptId) => {
                                            const dept = departments.find((d) => d.id === deptId);
                                            return dept ? (
                                                <Badge
                                                    key={deptId}
                                                    variant="outline"
                                                    className="cursor-pointer"
                                                    onClick={() => toggleDepartment(deptId)}
                                                >
                                                    {dept.name}
                                                    <X className="ml-1 h-3 w-3" />
                                                </Badge>
                                            ) : null;
                                        })}
                                    </div>
                                )}

                                {/* Department Checkboxes */}
                                <div className="grid grid-cols-2 gap-3 md:grid-cols-3">
                                    {departments.map((dept) => (
                                        <div
                                            key={dept.id}
                                            className="flex items-center space-x-2"
                                        >
                                            <Checkbox
                                                id={`dept-${dept.id}`}
                                                checked={data.departments.includes(dept.id)}
                                                onCheckedChange={() => toggleDepartment(dept.id)}
                                            />
                                            <Label
                                                htmlFor={`dept-${dept.id}`}
                                                className="cursor-pointer text-sm font-normal"
                                            >
                                                {dept.name}
                                            </Label>
                                        </div>
                                    ))}
                                </div>
                                {errors.departments && (
                                    <p className="text-sm text-red-600">{errors.departments}</p>
                                )}
                            </div>

                            {/* Direct Permissions Section */}
                            {permissions && Object.keys(permissions).length > 0 && (
                                <Collapsible
                                    open={permissionsOpen}
                                    onOpenChange={setPermissionsOpen}
                                    className="space-y-4 border-t pt-4 dark:border-gray-700"
                                >
                                    <CollapsibleTrigger asChild>
                                        <div className="flex cursor-pointer items-center justify-between">
                                            <div>
                                                <Label className="flex items-center gap-2">
                                                    <Key className="h-4 w-4" />
                                                    Direct Permissions
                                                    {data.direct_permissions.length > 0 && (
                                                        <Badge variant="secondary" className="ml-2">
                                                            {data.direct_permissions.length}
                                                        </Badge>
                                                    )}
                                                </Label>
                                                <p className="text-sm text-gray-500">
                                                    Assign permissions directly to this user (in addition to role permissions)
                                                </p>
                                            </div>
                                            <ChevronDown
                                                className={`h-5 w-5 text-gray-500 transition-transform ${
                                                    permissionsOpen ? 'rotate-180' : ''
                                                }`}
                                            />
                                        </div>
                                    </CollapsibleTrigger>

                                    <CollapsibleContent className="space-y-4">
                                        {Object.entries(permissions).map(([category, categoryPermissions]) => (
                                            <div key={category} className="rounded-lg border p-3 dark:border-gray-700">
                                                <div className="mb-2 flex items-center gap-2 border-b pb-2 dark:border-gray-700">
                                                    <Checkbox
                                                        id={`direct-category-${category}`}
                                                        checked={isCategoryFullySelected(category)}
                                                        ref={(el) => {
                                                            if (el) {
                                                                (el as HTMLButtonElement & { indeterminate: boolean }).indeterminate = isCategoryPartiallySelected(category);
                                                            }
                                                        }}
                                                        onCheckedChange={() => toggleCategory(category)}
                                                    />
                                                    <Label
                                                        htmlFor={`direct-category-${category}`}
                                                        className="cursor-pointer text-sm font-semibold"
                                                    >
                                                        {formatCategoryName(category)}
                                                    </Label>
                                                    <span className="text-xs text-gray-500">
                                                        ({categoryPermissions.filter((p) => data.direct_permissions.includes(p.name)).length}/{categoryPermissions.length})
                                                    </span>
                                                </div>
                                                <div className="grid grid-cols-2 gap-2 md:grid-cols-3 lg:grid-cols-4">
                                                    {categoryPermissions.map((permission) => (
                                                        <div
                                                            key={permission.id}
                                                            className="flex items-center space-x-2"
                                                        >
                                                            <Checkbox
                                                                id={`direct-perm-${permission.id}`}
                                                                checked={data.direct_permissions.includes(permission.name)}
                                                                onCheckedChange={() => toggleDirectPermission(permission.name)}
                                                            />
                                                            <Label
                                                                htmlFor={`direct-perm-${permission.id}`}
                                                                className="cursor-pointer text-xs font-normal"
                                                                title={permission.name}
                                                            >
                                                                {formatPermissionName(permission.name)}
                                                            </Label>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        ))}
                                    </CollapsibleContent>
                                </Collapsible>
                            )}

                            {/* Password Note */}
                            <div className="rounded-lg border bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
                                <p className="text-sm text-gray-600 dark:text-gray-400">
                                    <strong>Note:</strong> Password cannot be changed here. Use the "Reset Password" button on the users list to generate a new temporary password for this user.
                                </p>
                            </div>

                            <div className="flex gap-4 border-t pt-6 dark:border-gray-700">
                                <Link href="/admin/users">
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </Link>
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Saving...' : 'Save Changes'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
