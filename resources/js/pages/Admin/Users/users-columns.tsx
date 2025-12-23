'use client';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Link, router } from '@inertiajs/react';
import { ColumnDef } from '@tanstack/react-table';
import {
    CheckCircle,
    Key,
    Lock,
    MoreHorizontal,
    Pencil,
    XCircle,
} from 'lucide-react';

export interface Role {
    id: number;
    name: string;
}

export interface Department {
    id: number;
    name: string;
}

export interface UserData {
    id: number;
    name: string;
    username: string;
    is_active: boolean;
    roles: Role[];
    departments: Department[];
}

const PROTECTED_ADMIN_USERNAME = 'admin';

const isProtectedAdmin = (user: UserData) =>
    user.username === PROTECTED_ADMIN_USERNAME;

export const usersColumns: ColumnDef<UserData>[] = [
    {
        accessorKey: 'name',
        header: 'Name',
        cell: ({ row }) => {
            const user = row.original;
            return (
                <div className="flex items-center gap-2 font-medium">
                    {user.name}
                    {isProtectedAdmin(user) && (
                        <span title="Protected account">
                            <Lock className="h-3 w-3 text-amber-500" />
                        </span>
                    )}
                </div>
            );
        },
    },
    {
        accessorKey: 'username',
        header: 'Username',
        cell: ({ row }) => (
            <span className="text-muted-foreground">
                {row.getValue('username')}
            </span>
        ),
    },
    {
        accessorKey: 'roles',
        header: 'Roles',
        cell: ({ row }) => {
            const roles = row.original.roles;
            return (
                <div className="flex flex-wrap gap-1">
                    {roles.map((role) => (
                        <Badge key={role.id} variant="secondary">
                            {role.name}
                        </Badge>
                    ))}
                </div>
            );
        },
        filterFn: (row, id, value) => {
            const roles = row.original.roles;
            return roles.some((role) => value.includes(role.name));
        },
    },
    {
        accessorKey: 'departments',
        header: 'Departments',
        cell: ({ row }) => {
            const departments = row.original.departments;
            if (departments.length === 0) {
                return <span className="text-muted-foreground">None</span>;
            }
            return (
                <div className="flex flex-wrap gap-1">
                    {departments.map((dept) => (
                        <Badge key={dept.id} variant="outline">
                            {dept.name}
                        </Badge>
                    ))}
                </div>
            );
        },
    },
    {
        accessorKey: 'is_active',
        header: 'Status',
        cell: ({ row }) => {
            const isActive = row.getValue('is_active');
            return isActive ? (
                <Badge className="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                    <CheckCircle className="mr-1 h-3 w-3" />
                    Active
                </Badge>
            ) : (
                <Badge variant="destructive">
                    <XCircle className="mr-1 h-3 w-3" />
                    Inactive
                </Badge>
            );
        },
    },
    {
        id: 'actions',
        header: () => <span className="sr-only">Actions</span>,
        cell: ({ row }) => {
            const user = row.original;

            const handleToggleActive = () => {
                router.post(
                    `/admin/users/${user.id}/toggle-active`,
                    {},
                    {
                        preserveScroll: true,
                    },
                );
            };

            const handleResetPassword = () => {
                router.post(
                    `/admin/users/${user.id}/reset-password`,
                    {},
                    {
                        preserveScroll: true,
                    },
                );
            };

            return (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" className="h-8 w-8 p-0">
                            <span className="sr-only">Open menu</span>
                            <MoreHorizontal className="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem asChild>
                            <Link href={`/admin/users/${user.id}/edit`}>
                                <Pencil className="mr-2 h-4 w-4" />
                                Edit
                            </Link>
                        </DropdownMenuItem>
                        {!isProtectedAdmin(user) && (
                            <DropdownMenuItem onClick={handleToggleActive}>
                                {user.is_active ? (
                                    <>
                                        <XCircle className="mr-2 h-4 w-4" />
                                        Deactivate
                                    </>
                                ) : (
                                    <>
                                        <CheckCircle className="mr-2 h-4 w-4" />
                                        Activate
                                    </>
                                )}
                            </DropdownMenuItem>
                        )}
                        <DropdownMenuSeparator />
                        <DropdownMenuItem onClick={handleResetPassword}>
                            <Key className="mr-2 h-4 w-4" />
                            Reset Password
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            );
        },
    },
];
