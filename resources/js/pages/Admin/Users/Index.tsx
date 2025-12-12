import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import { Head, usePage } from '@inertiajs/react';
import { Copy, Key, Users } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Department, Role, UserData, usersColumns } from './users-columns';
import { UsersDataTable } from './users-data-table';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedUsers {
    data: UserData[];
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
        per_page?: number;
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
                <div>
                    <h1 className="flex items-center gap-2 text-3xl font-bold text-gray-900 dark:text-gray-100">
                        <Users className="h-8 w-8" />
                        User Management
                    </h1>
                    <p className="mt-2 text-gray-600 dark:text-gray-400">
                        Manage system users, roles, and department assignments
                    </p>
                </div>

                {/* DataTable */}
                <UsersDataTable
                    columns={usersColumns}
                    data={users.data}
                    pagination={users}
                    roles={roles}
                    departments={departments}
                    filters={filters}
                />
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
                            Please share this temporary password with the user securely. They will be
                            required to change it on their next login.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="my-4">
                        <div className="flex items-center gap-2 rounded-lg border bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
                            <code className="flex-1 font-mono text-lg font-semibold">
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
