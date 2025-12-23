import { Head, router } from '@inertiajs/react';
import {
    CheckCircle,
    Cloud,
    CloudOff,
    Database,
    Download,
    HardDrive,
    MoreHorizontal,
    Plus,
    RefreshCw,
    Settings,
    Shield,
    ShieldOff,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { StatCard } from '@/components/ui/stat-card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

import RestoreConfirmationModal from './RestoreConfirmationModal';

interface Backup {
    id: number;
    filename: string;
    file_size: number;
    file_path: string | null;
    google_drive_file_id: string | null;
    status: 'pending' | 'completed' | 'failed';
    source: 'manual_ui' | 'manual_cli' | 'scheduled' | 'pre_restore';
    is_protected: boolean;
    created_by: number | null;
    completed_at: string | null;
    error_message: string | null;
    created_at: string;
    creator?: {
        id: number;
        name: string;
    };
}

interface Props {
    backups: {
        data: Backup[];
        links: unknown;
        meta: unknown;
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '#' },
    { title: 'Backups', href: '/admin/backups' },
];

function formatFileSize(bytes: number): string {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleString();
}

function getSourceLabel(source: string): string {
    const labels: Record<string, string> = {
        manual_ui: 'Manual (UI)',
        manual_cli: 'Manual (CLI)',
        scheduled: 'Scheduled',
        pre_restore: 'Pre-Restore',
    };
    return labels[source] || source;
}

function getStatusBadge(status: string) {
    switch (status) {
        case 'completed':
            return <Badge variant="default">Completed</Badge>;
        case 'pending':
            return <Badge variant="secondary">Pending</Badge>;
        case 'failed':
            return <Badge variant="destructive">Failed</Badge>;
        default:
            return <Badge variant="outline">{status}</Badge>;
    }
}

export default function BackupIndex({ backups }: Props) {
    const [isCreating, setIsCreating] = useState(false);
    const [restoreBackup, setRestoreBackup] = useState<Backup | null>(null);

    const handleCreateBackup = () => {
        setIsCreating(true);
        router.post(
            '/admin/backups',
            {},
            {
                onFinish: () => setIsCreating(false),
            },
        );
    };

    const handleDelete = (backup: Backup) => {
        if (
            confirm(
                `Are you sure you want to delete backup "${backup.filename}"? This action cannot be undone.`,
            )
        ) {
            router.delete(`/admin/backups/${backup.id}`);
        }
    };

    const handleToggleProtection = (backup: Backup) => {
        router.post(`/admin/backups/${backup.id}/toggle-protection`);
    };

    const completedBackups = backups.data.filter(
        (b) => b.status === 'completed',
    );
    const totalSize = completedBackups.reduce((sum, b) => sum + b.file_size, 0);
    const cloudBackups = completedBackups.filter((b) => b.google_drive_file_id);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Database Backups" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="flex items-center gap-2 text-3xl font-bold text-gray-900 dark:text-gray-100">
                            <Database className="h-8 w-8" />
                            Database Backups
                        </h1>
                        <p className="mt-2 text-gray-600 dark:text-gray-400">
                            Manage database backups and restore operations
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <a href="/admin/backups/settings">
                                <Settings className="mr-2 h-4 w-4" />
                                Settings
                            </a>
                        </Button>
                        <Button
                            onClick={handleCreateBackup}
                            disabled={isCreating}
                        >
                            {isCreating ? (
                                <>
                                    <RefreshCw className="mr-2 h-4 w-4 animate-spin" />
                                    Creating...
                                </>
                            ) : (
                                <>
                                    <Plus className="mr-2 h-4 w-4" />
                                    Create Backup
                                </>
                            )}
                        </Button>
                    </div>
                </div>

                {/* Stats Cards */}
                <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <StatCard
                        label="Total Backups"
                        value={backups.total}
                        icon={<Database className="h-4 w-4" />}
                        variant="info"
                    />
                    <StatCard
                        label="Completed"
                        value={completedBackups.length}
                        icon={<CheckCircle className="h-4 w-4" />}
                        variant="success"
                    />
                    <StatCard
                        label="On Cloud"
                        value={cloudBackups.length}
                        icon={<Cloud className="h-4 w-4" />}
                        variant="info"
                    />
                    <StatCard
                        label="Total Size"
                        value={formatFileSize(totalSize)}
                        icon={<HardDrive className="h-4 w-4" />}
                        variant="default"
                    />
                </div>

                {/* Backups Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>Backup History</CardTitle>
                        <CardDescription>
                            View and manage all database backups
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {backups.data.length === 0 ? (
                            <div className="py-12 text-center">
                                <Database className="mx-auto h-12 w-12 text-gray-400" />
                                <h3 className="mt-4 text-lg font-medium text-gray-900 dark:text-gray-100">
                                    No backups yet
                                </h3>
                                <p className="mt-2 text-gray-600 dark:text-gray-400">
                                    Create your first backup to protect your
                                    data.
                                </p>
                                <Button
                                    onClick={handleCreateBackup}
                                    className="mt-4"
                                >
                                    <Plus className="mr-2 h-4 w-4" />
                                    Create Backup
                                </Button>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Filename</TableHead>
                                        <TableHead>Size</TableHead>
                                        <TableHead>Storage</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Source</TableHead>
                                        <TableHead>Created</TableHead>
                                        <TableHead className="w-[70px]"></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {backups.data.map((backup) => (
                                        <TableRow key={backup.id}>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    {backup.is_protected && (
                                                        <Shield className="h-4 w-4 text-amber-500" />
                                                    )}
                                                    <span className="font-medium">
                                                        {backup.filename}
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {formatFileSize(
                                                    backup.file_size,
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    {backup.file_path && (
                                                        <span
                                                            className="flex items-center gap-1 text-sm"
                                                            title="Stored locally"
                                                        >
                                                            <HardDrive className="h-4 w-4 text-gray-500" />
                                                        </span>
                                                    )}
                                                    {backup.google_drive_file_id ? (
                                                        <span
                                                            className="flex items-center gap-1 text-sm"
                                                            title="Stored on Google Drive"
                                                        >
                                                            <Cloud className="h-4 w-4 text-blue-500" />
                                                        </span>
                                                    ) : (
                                                        <span
                                                            className="flex items-center gap-1 text-sm"
                                                            title="Not on Google Drive"
                                                        >
                                                            <CloudOff className="h-4 w-4 text-gray-300" />
                                                        </span>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {getStatusBadge(backup.status)}
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline">
                                                    {getSourceLabel(
                                                        backup.source,
                                                    )}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                <div className="text-sm">
                                                    {formatDate(
                                                        backup.created_at,
                                                    )}
                                                </div>
                                                {backup.creator && (
                                                    <div className="text-xs text-gray-500">
                                                        by {backup.creator.name}
                                                    </div>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        asChild
                                                    >
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                        >
                                                            <MoreHorizontal className="h-4 w-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        {backup.file_path && (
                                                            <DropdownMenuItem
                                                                asChild
                                                            >
                                                                <a
                                                                    href={`/admin/backups/${backup.id}/download`}
                                                                >
                                                                    <Download className="mr-2 h-4 w-4" />
                                                                    Download
                                                                </a>
                                                            </DropdownMenuItem>
                                                        )}
                                                        {backup.status ===
                                                            'completed' && (
                                                            <DropdownMenuItem
                                                                onClick={() =>
                                                                    setRestoreBackup(
                                                                        backup,
                                                                    )
                                                                }
                                                            >
                                                                <RefreshCw className="mr-2 h-4 w-4" />
                                                                Restore
                                                            </DropdownMenuItem>
                                                        )}
                                                        <DropdownMenuItem
                                                            onClick={() =>
                                                                handleToggleProtection(
                                                                    backup,
                                                                )
                                                            }
                                                        >
                                                            {backup.is_protected ? (
                                                                <>
                                                                    <ShieldOff className="mr-2 h-4 w-4" />
                                                                    Remove
                                                                    Protection
                                                                </>
                                                            ) : (
                                                                <>
                                                                    <Shield className="mr-2 h-4 w-4" />
                                                                    Protect
                                                                </>
                                                            )}
                                                        </DropdownMenuItem>
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem
                                                            onClick={() =>
                                                                handleDelete(
                                                                    backup,
                                                                )
                                                            }
                                                            className="text-red-600"
                                                            disabled={
                                                                backup.is_protected
                                                            }
                                                        >
                                                            <Trash2 className="mr-2 h-4 w-4" />
                                                            Delete
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Restore Confirmation Modal */}
            <RestoreConfirmationModal
                backup={restoreBackup}
                onClose={() => setRestoreBackup(null)}
            />
        </AppLayout>
    );
}
