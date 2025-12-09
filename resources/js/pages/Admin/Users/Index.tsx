import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
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
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    CheckCircle,
    Copy,
    Key,
    MoreVertical,
    Pencil,
    Plus,
    Search,
    Shield,
    UserCog,
    Users,
    XCircle,
} from 'lucide-react';
import { useEffect, useState } from 'react';

interface Role {
    id: number;
    name: string;
}

interface Department {
    id: number;
    name: string;
}

interface User {
    id: number;
    name: string;
    username: string;
    is_active: boolean;
    roles: Role[];
    departments: Department[];
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedUsers {
    data: User[];
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
    links: PaginationLink[];
}

interface Props {
    users: PaginatedUsers;
    roles: Role[];
    departments: Department[];
    filters: {
        search?: string;
        role?: string;
        department?: string;
    };
}

interface FlashData {
    success?: string;
    error?: string;
    temporary_password?: string;
}

export default function UsersIndex({ users, roles, departments, filters }: Props) {
    const { props } = usePage<{ flash?: FlashData }>();
    const flash = props.flash || {};

    const [search, setSearch] = useState(filters.search || '');
    const [roleFilter, setRoleFilter] = useState(filters.role || '');
    const [departmentFilter, setDepartmentFilter] = useState(filters.department || '');
    const [passwordModalOpen, setPasswordModalOpen] = useState(false);
    const [temporaryPassword, setTemporaryPassword] = useState<string | null>(null);
    const [copied, setCopied] = useState(false);

    // Show password modal when temporary_password is in flash
    useEffect(() => {
        if (flash.temporary_password) {
            setTemporaryPassword(flash.temporary_password);
            setPasswordModalOpen(true);
        }
    }, [flash.temporary_password]);

    const handleSearch = () => {
        router.get('/admin/users', {
            search: search || undefined,
            role: roleFilter || undefined,
            department: departmentFilter || undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter') {
            handleSearch();
        }
    };

    const handleRoleFilterChange = (value: string) => {
        const newRole = value === 'all' ? '' : value;
        setRoleFilter(newRole);
        router.get('/admin/users', {
            search: search || undefined,
            role: newRole || undefined,
            department: departmentFilter || undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleDepartmentFilterChange = (value: string) => {
        const newDept = value === 'all' ? '' : value;
        setDepartmentFilter(newDept);
        router.get('/admin/users', {
            search: search || undefined,
            role: roleFilter || undefined,
            department: newDept || undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleToggleActive = (user: User) => {
        router.post(`/admin/users/${user.id}/toggle-active`, {}, {
            preserveScroll: true,
        });
    };

    const handleResetPassword = (user: User) => {
        router.post(`/admin/users/${user.id}/reset-password`, {}, {
            preserveScroll: true,
        });
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
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                { title: 'Users', href: '' },
            ]}
        >
            <Head title="User Management" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="flex items-center gap-2 text-3xl font-bold text-gray-900 dark:text-gray-100">
                            <Users className="h-8 w-8" />
                            User Management
                        </h1>
                        <p className="mt-2 text-gray-600 dark:text-gray-400">
                            Manage system users, roles, and department assignments
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Link href="/admin/roles">
                            <Button variant="outline">
                                <Shield className="mr-2 h-4 w-4" />
                                Manage Roles
                            </Button>
                        </Link>
                        <Link href="/admin/users/create">
                            <Button>
                                <Plus className="mr-2 h-4 w-4" />
                                Add User
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* Filters */}
                <Card>
                    <CardContent className="p-4">
                        <div className="flex flex-col gap-4 md:flex-row md:items-end">
                            <div className="flex-1">
                                <label className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Search
                                </label>
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                                    <Input
                                        type="text"
                                        placeholder="Search by name or username..."
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        onKeyDown={handleKeyDown}
                                        className="pl-10"
                                    />
                                </div>
                            </div>
                            <div className="w-full md:w-48">
                                <label className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Role
                                </label>
                                <Select value={roleFilter || 'all'} onValueChange={handleRoleFilterChange}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="All Roles" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Roles</SelectItem>
                                        {roles.map((role) => (
                                            <SelectItem key={role.id} value={role.name}>
                                                {role.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="w-full md:w-48">
                                <label className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Department
                                </label>
                                <Select value={departmentFilter || 'all'} onValueChange={handleDepartmentFilterChange}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="All Departments" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Departments</SelectItem>
                                        {departments.map((dept) => (
                                            <SelectItem key={dept.id} value={dept.id.toString()}>
                                                {dept.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <Button onClick={handleSearch} variant="secondary">
                                <Search className="mr-2 h-4 w-4" />
                                Search
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                {/* Users Table */}
                <Card>
                    <CardContent className="p-0">
                        {users.data.length > 0 ? (
                            <>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Name</TableHead>
                                            <TableHead>Username</TableHead>
                                            <TableHead>Roles</TableHead>
                                            <TableHead>Departments</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead className="text-right">Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {users.data.map((user) => (
                                            <TableRow key={user.id}>
                                                <TableCell className="font-medium">
                                                    {user.name}
                                                </TableCell>
                                                <TableCell className="text-gray-600 dark:text-gray-400">
                                                    {user.username}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex flex-wrap gap-1">
                                                        {user.roles.map((role) => (
                                                            <Badge key={role.id} variant="secondary">
                                                                {role.name}
                                                            </Badge>
                                                        ))}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex flex-wrap gap-1">
                                                        {user.departments.length > 0 ? (
                                                            user.departments.map((dept) => (
                                                                <Badge key={dept.id} variant="outline">
                                                                    {dept.name}
                                                                </Badge>
                                                            ))
                                                        ) : (
                                                            <span className="text-gray-400">None</span>
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    {user.is_active ? (
                                                        <Badge className="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                            <CheckCircle className="mr-1 h-3 w-3" />
                                                            Active
                                                        </Badge>
                                                    ) : (
                                                        <Badge variant="destructive">
                                                            <XCircle className="mr-1 h-3 w-3" />
                                                            Inactive
                                                        </Badge>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
                                                            <Button variant="ghost" size="sm">
                                                                <MoreVertical className="h-4 w-4" />
                                                                <span className="sr-only">Actions</span>
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuItem asChild>
                                                                <Link href={`/admin/users/${user.id}/edit`}>
                                                                    <Pencil className="mr-2 h-4 w-4" />
                                                                    Edit
                                                                </Link>
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem onClick={() => handleToggleActive(user)}>
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
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem onClick={() => handleResetPassword(user)}>
                                                                <Key className="mr-2 h-4 w-4" />
                                                                Reset Password
                                                            </DropdownMenuItem>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>

                                {/* Pagination */}
                                {users.last_page > 1 && (
                                    <div className="flex items-center justify-between border-t px-4 py-3 dark:border-gray-700">
                                        <div className="text-sm text-gray-600 dark:text-gray-400">
                                            Showing {users.from} to {users.to} of {users.total} users
                                        </div>
                                        <div className="flex gap-1">
                                            {users.links.map((link, index) => (
                                                <Button
                                                    key={index}
                                                    variant={link.active ? 'default' : 'outline'}
                                                    size="sm"
                                                    disabled={!link.url}
                                                    onClick={() => {
                                                        if (link.url) {
                                                            router.get(link.url, {}, { preserveState: true });
                                                        }
                                                    }}
                                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                                />
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </>
                        ) : (
                            <div className="py-12 text-center">
                                <UserCog className="mx-auto mb-4 h-16 w-16 text-gray-300 dark:text-gray-600" />
                                <h3 className="mb-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    No users found
                                </h3>
                                <p className="mb-4 text-gray-600 dark:text-gray-400">
                                    {filters.search || filters.role || filters.department
                                        ? 'Try adjusting your search or filters.'
                                        : 'Get started by adding your first user.'}
                                </p>
                                {!filters.search && !filters.role && !filters.department && (
                                    <Link href="/admin/users/create">
                                        <Button>
                                            <Plus className="mr-2 h-4 w-4" />
                                            Add User
                                        </Button>
                                    </Link>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Temporary Password Modal */}
            <Dialog open={passwordModalOpen} onOpenChange={closePasswordModal}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Key className="h-5 w-5" />
                            Temporary Password
                        </DialogTitle>
                        <DialogDescription>
                            Please share this temporary password with the user securely. They will be required to change it on their next login.
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
