import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Key,
    MoreVertical,
    Pencil,
    Plus,
    Search,
    Shield,
    Trash2,
    Users,
} from 'lucide-react';
import { useState } from 'react';

interface Role {
    id: number;
    name: string;
    permissions_count: number;
    users_count: number;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedRoles {
    data: Role[];
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
    links: PaginationLink[];
}

interface Filters {
    search?: string;
    per_page?: number;
}

interface Props {
    roles: PaginatedRoles;
    filters: Filters;
}

export default function RolesIndex({ roles, filters }: Props) {
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [roleToDelete, setRoleToDelete] = useState<Role | null>(null);
    const [search, setSearch] = useState(filters.search || '');

    const handleSearch = () => {
        router.get('/admin/roles', {
            search: search || undefined,
            per_page: filters.per_page,
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

    const handleDeleteClick = (role: Role) => {
        setRoleToDelete(role);
        setDeleteDialogOpen(true);
    };

    const handleDeleteConfirm = () => {
        if (roleToDelete) {
            router.delete(`/admin/roles/${roleToDelete.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    setDeleteDialogOpen(false);
                    setRoleToDelete(null);
                },
            });
        }
    };

    const handleDeleteCancel = () => {
        setDeleteDialogOpen(false);
        setRoleToDelete(null);
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin' },
                { title: 'Roles', href: '' },
            ]}
        >
            <Head title="Role Management" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="flex items-center gap-2 text-3xl font-bold text-gray-900 dark:text-gray-100">
                            <Shield className="h-8 w-8" />
                            Role Management
                        </h1>
                        <p className="mt-2 text-gray-600 dark:text-gray-400">
                            Manage system roles and their permissions
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Link href="/admin/users">
                            <Button variant="outline">
                                <Users className="mr-2 h-4 w-4" />
                                Manage Users
                            </Button>
                        </Link>
                        <Link href="/admin/roles/create">
                            <Button>
                                <Plus className="mr-2 h-4 w-4" />
                                Add Role
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
                                        placeholder="Search by role name..."
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        onKeyDown={handleKeyDown}
                                        className="pl-10"
                                    />
                                </div>
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Show
                                </label>
                                <Select
                                    value={String(filters.per_page || 5)}
                                    onValueChange={(value) => {
                                        router.get('/admin/roles', {
                                            search: search || undefined,
                                            per_page: value,
                                        }, {
                                            preserveState: true,
                                            preserveScroll: true,
                                        });
                                    }}
                                >
                                    <SelectTrigger className="w-20">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="5">5</SelectItem>
                                        <SelectItem value="10">10</SelectItem>
                                        <SelectItem value="25">25</SelectItem>
                                        <SelectItem value="50">50</SelectItem>
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

                {/* Roles Table */}
                <Card>
                    <CardContent className="p-0">
                        {roles.data.length > 0 ? (
                            <>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Role Name</TableHead>
                                        <TableHead className="text-center">Permissions</TableHead>
                                        <TableHead className="text-center">Users</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {roles.data.map((role) => (
                                        <TableRow key={role.id}>
                                            <TableCell className="font-medium">
                                                <div className="flex items-center gap-2">
                                                    <Shield className="h-4 w-4 text-gray-400" />
                                                    {role.name}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-center">
                                                <Badge variant="secondary">
                                                    <Key className="mr-1 h-3 w-3" />
                                                    {role.permissions_count}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-center">
                                                <Badge variant="outline">
                                                    <Users className="mr-1 h-3 w-3" />
                                                    {role.users_count}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {/* Protected role indicator */}
                                                {role.name === 'Admin' ? (
                                                    <span className="text-sm text-gray-500 dark:text-gray-400">
                                                        Protected
                                                    </span>
                                                ) : (
                                                    <>
                                                        {/* Desktop Actions */}
                                                        <div className="hidden items-center justify-end gap-2 lg:flex">
                                                            <Link href={`/admin/roles/${role.id}/edit`}>
                                                                <Button variant="outline" size="sm">
                                                                    <Pencil className="mr-1 h-3 w-3" />
                                                                    Edit
                                                                </Button>
                                                            </Link>
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                                onClick={() => handleDeleteClick(role)}
                                                                disabled={role.users_count > 0}
                                                                className={role.users_count > 0 ? 'opacity-50' : 'text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-950 dark:hover:text-red-300'}
                                                            >
                                                                <Trash2 className="mr-1 h-3 w-3" />
                                                                Delete
                                                            </Button>
                                                        </div>

                                                        {/* Mobile Actions */}
                                                        <div className="lg:hidden">
                                                            <DropdownMenu>
                                                                <DropdownMenuTrigger asChild>
                                                                    <Button variant="ghost" size="sm">
                                                                        <MoreVertical className="h-4 w-4" />
                                                                    </Button>
                                                                </DropdownMenuTrigger>
                                                                <DropdownMenuContent align="end">
                                                                    <DropdownMenuItem asChild>
                                                                        <Link href={`/admin/roles/${role.id}/edit`}>
                                                                            <Pencil className="mr-2 h-4 w-4" />
                                                                            Edit
                                                                        </Link>
                                                                    </DropdownMenuItem>
                                                                    <DropdownMenuSeparator />
                                                                    <DropdownMenuItem
                                                                        onClick={() => handleDeleteClick(role)}
                                                                        disabled={role.users_count > 0}
                                                                        className={role.users_count > 0 ? 'opacity-50' : 'text-red-600 dark:text-red-400'}
                                                                    >
                                                                        <Trash2 className="mr-2 h-4 w-4" />
                                                                        Delete
                                                                    </DropdownMenuItem>
                                                                </DropdownMenuContent>
                                                            </DropdownMenu>
                                                        </div>
                                                    </>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>

                                {/* Pagination */}
                                {roles.last_page > 1 && (
                                    <div className="flex items-center justify-between border-t px-4 py-3 dark:border-gray-700">
                                        <div className="text-sm text-gray-600 dark:text-gray-400">
                                            Showing {roles.from} to {roles.to} of {roles.total} roles
                                        </div>
                                        <div className="flex gap-1">
                                            {roles.links.map((link, index) => (
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
                                <Shield className="mx-auto mb-4 h-16 w-16 text-gray-300 dark:text-gray-600" />
                                <h3 className="mb-2 text-lg font-semibold text-gray-900 dark:text-gray-100">
                                    No roles found
                                </h3>
                                <p className="mb-4 text-gray-600 dark:text-gray-400">
                                    {filters.search
                                        ? 'Try adjusting your search.'
                                        : 'Get started by creating your first role.'}
                                </p>
                                {!filters.search && (
                                    <Link href="/admin/roles/create">
                                        <Button>
                                            <Plus className="mr-2 h-4 w-4" />
                                            Add Role
                                        </Button>
                                    </Link>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Delete Confirmation Dialog */}
            <AlertDialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete Role</AlertDialogTitle>
                        <AlertDialogDescription>
                            {roleToDelete && roleToDelete.users_count > 0 ? (
                                <span className="text-red-600 dark:text-red-400">
                                    Cannot delete this role because it has {roleToDelete.users_count} user(s) assigned.
                                    Please reassign or remove users from this role first.
                                </span>
                            ) : (
                                <>
                                    Are you sure you want to delete the role "{roleToDelete?.name}"?
                                    This action cannot be undone.
                                </>
                            )}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel onClick={handleDeleteCancel}>Cancel</AlertDialogCancel>
                        {roleToDelete && roleToDelete.users_count === 0 && (
                            <AlertDialogAction
                                onClick={handleDeleteConfirm}
                                className="bg-red-600 hover:bg-red-700"
                            >
                                Delete
                            </AlertDialogAction>
                        )}
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
