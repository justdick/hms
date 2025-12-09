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
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Copy, Key, UserPlus, X } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Role {
    id: number;
    name: string;
}

interface Department {
    id: number;
    name: string;
}

interface Props {
    roles: Role[];
    departments: Department[];
}

interface FlashData {
    success?: string;
    error?: string;
    temporary_password?: string;
}

export default function UsersCreate({ roles, departments }: Props) {
    const { props } = usePage<{ flash?: FlashData }>();
    const flash = props.flash || {};

    const [passwordModalOpen, setPasswordModalOpen] = useState(false);
    const [temporaryPassword, setTemporaryPassword] = useState<string | null>(null);
    const [copied, setCopied] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        username: '',
        roles: [] as string[],
        departments: [] as number[],
    });

    // Show password modal when temporary_password is in flash
    useEffect(() => {
        if (flash.temporary_password) {
            setTemporaryPassword(flash.temporary_password);
            setPasswordModalOpen(true);
        }
    }, [flash.temporary_password]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/users');
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

    const copyToClipboard = () => {
        if (temporaryPassword) {
            navigator.clipboard.writeText(temporaryPassword);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        }
    };

    const closePasswordModal = () => {
        setPasswordModalOpen(false);
        setTemporaryPassword(null);
        setCopied(false);
        // Navigate to users list after closing
        router.visit('/admin/users');
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                { title: 'Users', href: '/admin/users' },
                { title: 'Create User', href: '' },
            ]}
        >
            <Head title="Create User" />

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
                            <UserPlus className="h-8 w-8" />
                            Create User
                        </h1>
                        <p className="mt-1 text-gray-600 dark:text-gray-400">
                            Add a new user to the system
                        </p>
                    </div>
                </div>

                <Card className="max-w-3xl">
                    <CardHeader>
                        <CardTitle>User Information</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-6">
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

                            {/* Password Note */}
                            <div className="rounded-lg border bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-950/30">
                                <div className="flex items-start gap-3">
                                    <Key className="mt-0.5 h-5 w-5 text-blue-600 dark:text-blue-400" />
                                    <div>
                                        <p className="font-medium text-blue-900 dark:text-blue-100">
                                            Temporary Password
                                        </p>
                                        <p className="text-sm text-blue-700 dark:text-blue-300">
                                            A temporary password will be generated automatically. You'll need to share it with the user securely. They will be required to change it on their first login.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div className="flex gap-4 border-t pt-6 dark:border-gray-700">
                                <Link href="/admin/users">
                                    <Button type="button" variant="outline">
                                        Cancel
                                    </Button>
                                </Link>
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Creating...' : 'Create User'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>

            {/* Temporary Password Modal */}
            <Dialog open={passwordModalOpen} onOpenChange={closePasswordModal}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Key className="h-5 w-5" />
                            User Created Successfully
                        </DialogTitle>
                        <DialogDescription>
                            Please share this temporary password with the user securely. They will be required to change it on their first login.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="my-4">
                        <div className="flex items-center gap-2 rounded-lg border bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
                            <code className="flex-1 text-lg font-mono font-semibold">
                                {temporaryPassword}
                            </code>
                            <Button variant="outline" size="sm" onClick={copyToClipboard}>
                                <Copy className="mr-1 h-4 w-4" />
                                {copied ? 'Copied!' : 'Copy'}
                            </Button>
                        </div>
                        <p className="mt-2 text-sm text-amber-600 dark:text-amber-400">
                            ⚠️ This password will only be shown once. Make sure to copy it now.
                        </p>
                    </div>
                    <DialogFooter>
                        <Button onClick={closePasswordModal}>Done</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
