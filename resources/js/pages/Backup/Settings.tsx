import { Head, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle,
    Clock,
    Cloud,
    Loader2,
    Mail,
    Save,
    Trash2,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

interface BackupSettings {
    id: number;
    schedule_enabled: boolean;
    schedule_frequency: 'daily' | 'weekly' | 'custom' | null;
    schedule_time: string | null;
    cron_expression: string | null;
    retention_daily: number;
    retention_weekly: number;
    retention_monthly: number;
    google_drive_enabled: boolean;
    google_drive_folder_id: string | null;
    has_google_credentials: boolean;
    notification_emails: string[] | null;
}

interface Props {
    settings: BackupSettings;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '#' },
    { title: 'Backups', href: '/admin/backups' },
    { title: 'Settings', href: '/admin/backups/settings' },
];

export default function BackupSettingsPage({ settings }: Props) {
    const [testingConnection, setTestingConnection] = useState(false);
    const [connectionResult, setConnectionResult] = useState<{
        success: boolean;
        message: string;
    } | null>(null);
    const [newEmail, setNewEmail] = useState('');

    const { data, setData, put, processing, errors } = useForm({
        schedule_enabled: settings.schedule_enabled,
        schedule_frequency: settings.schedule_frequency || 'daily',
        schedule_time: settings.schedule_time || '02:00',
        cron_expression: settings.cron_expression || '',
        retention_daily: settings.retention_daily,
        retention_weekly: settings.retention_weekly,
        retention_monthly: settings.retention_monthly,
        google_drive_enabled: settings.google_drive_enabled,
        google_drive_folder_id: settings.google_drive_folder_id || '',
        google_credentials: '',
        notification_emails: settings.notification_emails || [],
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put('/admin/backups/settings');
    };

    const handleTestConnection = async () => {
        setTestingConnection(true);
        setConnectionResult(null);

        try {
            const response = await fetch(
                '/admin/backups/settings/test-google-drive',
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN':
                            document
                                .querySelector('meta[name="csrf-token"]')
                                ?.getAttribute('content') || '',
                    },
                },
            );
            const result = await response.json();
            setConnectionResult(result);
        } catch {
            setConnectionResult({
                success: false,
                message: 'Failed to test connection. Please try again.',
            });
        } finally {
            setTestingConnection(false);
        }
    };

    const addEmail = () => {
        if (newEmail && !data.notification_emails.includes(newEmail)) {
            setData('notification_emails', [
                ...data.notification_emails,
                newEmail,
            ]);
            setNewEmail('');
        }
    };

    const removeEmail = (email: string) => {
        setData(
            'notification_emails',
            data.notification_emails.filter((e) => e !== email),
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Backup Settings" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <a href="/admin/backups">
                            <ArrowLeft className="h-5 w-5" />
                        </a>
                    </Button>
                    <div>
                        <h1 className="text-3xl font-bold text-gray-900 dark:text-gray-100">
                            Backup Settings
                        </h1>
                        <p className="mt-1 text-gray-600 dark:text-gray-400">
                            Configure automated backups, retention policies, and
                            notifications
                        </p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Schedule Settings */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Clock className="h-5 w-5" />
                                Schedule Settings
                            </CardTitle>
                            <CardDescription>
                                Configure automatic backup schedule
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <Label htmlFor="schedule_enabled">
                                        Enable Scheduled Backups
                                    </Label>
                                    <p className="text-sm text-gray-500">
                                        Automatically create backups on a
                                        schedule
                                    </p>
                                </div>
                                <Switch
                                    id="schedule_enabled"
                                    checked={data.schedule_enabled}
                                    onCheckedChange={(checked) =>
                                        setData('schedule_enabled', checked)
                                    }
                                />
                            </div>

                            {data.schedule_enabled && (
                                <>
                                    <div className="grid grid-cols-2 gap-4">
                                        <div className="space-y-2">
                                            <Label htmlFor="schedule_frequency">
                                                Frequency
                                            </Label>
                                            <Select
                                                value={data.schedule_frequency}
                                                onValueChange={(value) =>
                                                    setData(
                                                        'schedule_frequency',
                                                        value as
                                                            | 'daily'
                                                            | 'weekly'
                                                            | 'custom',
                                                    )
                                                }
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Select frequency" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="daily">
                                                        Daily
                                                    </SelectItem>
                                                    <SelectItem value="weekly">
                                                        Weekly
                                                    </SelectItem>
                                                    <SelectItem value="custom">
                                                        Custom (Cron)
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                            {errors.schedule_frequency && (
                                                <p className="text-sm text-red-500">
                                                    {errors.schedule_frequency}
                                                </p>
                                            )}
                                        </div>

                                        {data.schedule_frequency !==
                                            'custom' && (
                                            <div className="space-y-2">
                                                <Label htmlFor="schedule_time">
                                                    Time
                                                </Label>
                                                <Input
                                                    id="schedule_time"
                                                    type="time"
                                                    value={data.schedule_time}
                                                    onChange={(e) =>
                                                        setData(
                                                            'schedule_time',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                                {errors.schedule_time && (
                                                    <p className="text-sm text-red-500">
                                                        {errors.schedule_time}
                                                    </p>
                                                )}
                                            </div>
                                        )}
                                    </div>

                                    {data.schedule_frequency === 'custom' && (
                                        <div className="space-y-2">
                                            <Label htmlFor="cron_expression">
                                                Cron Expression
                                            </Label>
                                            <Input
                                                id="cron_expression"
                                                value={data.cron_expression}
                                                onChange={(e) =>
                                                    setData(
                                                        'cron_expression',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="0 2 * * *"
                                            />
                                            <p className="text-sm text-gray-500">
                                                Example: "0 2 * * *" runs at
                                                2:00 AM daily
                                            </p>
                                            {errors.cron_expression && (
                                                <p className="text-sm text-red-500">
                                                    {errors.cron_expression}
                                                </p>
                                            )}
                                        </div>
                                    )}
                                </>
                            )}
                        </CardContent>
                    </Card>

                    {/* Retention Policy */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Trash2 className="h-5 w-5" />
                                Retention Policy
                            </CardTitle>
                            <CardDescription>
                                Configure how long backups are kept before
                                automatic cleanup
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-3 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="retention_daily">
                                        Daily Backups
                                    </Label>
                                    <Input
                                        id="retention_daily"
                                        type="number"
                                        min="0"
                                        max="365"
                                        value={data.retention_daily}
                                        onChange={(e) =>
                                            setData(
                                                'retention_daily',
                                                parseInt(e.target.value) || 0,
                                            )
                                        }
                                    />
                                    <p className="text-sm text-gray-500">
                                        Keep last N daily backups
                                    </p>
                                    {errors.retention_daily && (
                                        <p className="text-sm text-red-500">
                                            {errors.retention_daily}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="retention_weekly">
                                        Weekly Backups
                                    </Label>
                                    <Input
                                        id="retention_weekly"
                                        type="number"
                                        min="0"
                                        max="52"
                                        value={data.retention_weekly}
                                        onChange={(e) =>
                                            setData(
                                                'retention_weekly',
                                                parseInt(e.target.value) || 0,
                                            )
                                        }
                                    />
                                    <p className="text-sm text-gray-500">
                                        Keep last N weekly backups
                                    </p>
                                    {errors.retention_weekly && (
                                        <p className="text-sm text-red-500">
                                            {errors.retention_weekly}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="retention_monthly">
                                        Monthly Backups
                                    </Label>
                                    <Input
                                        id="retention_monthly"
                                        type="number"
                                        min="0"
                                        max="24"
                                        value={data.retention_monthly}
                                        onChange={(e) =>
                                            setData(
                                                'retention_monthly',
                                                parseInt(e.target.value) || 0,
                                            )
                                        }
                                    />
                                    <p className="text-sm text-gray-500">
                                        Keep last N monthly backups
                                    </p>
                                    {errors.retention_monthly && (
                                        <p className="text-sm text-red-500">
                                            {errors.retention_monthly}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Google Drive Settings */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Cloud className="h-5 w-5" />
                                Google Drive Integration
                            </CardTitle>
                            <CardDescription>
                                Configure cloud storage for off-site backup
                                redundancy
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <Label htmlFor="google_drive_enabled">
                                        Enable Google Drive
                                    </Label>
                                    <p className="text-sm text-gray-500">
                                        Upload backups to Google Drive for
                                        off-site storage
                                    </p>
                                </div>
                                <Switch
                                    id="google_drive_enabled"
                                    checked={data.google_drive_enabled}
                                    onCheckedChange={(checked) =>
                                        setData('google_drive_enabled', checked)
                                    }
                                />
                            </div>

                            {data.google_drive_enabled && (
                                <>
                                    <div className="space-y-2">
                                        <Label htmlFor="google_drive_folder_id">
                                            Folder ID
                                        </Label>
                                        <Input
                                            id="google_drive_folder_id"
                                            value={data.google_drive_folder_id}
                                            onChange={(e) =>
                                                setData(
                                                    'google_drive_folder_id',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Enter Google Drive folder ID"
                                        />
                                        <p className="text-sm text-gray-500">
                                            The folder ID from your Google Drive
                                            URL
                                        </p>
                                        {errors.google_drive_folder_id && (
                                            <p className="text-sm text-red-500">
                                                {errors.google_drive_folder_id}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="google_credentials">
                                            Service Account Credentials (JSON)
                                            {settings.has_google_credentials && (
                                                <span className="ml-2 text-green-600">
                                                    (Configured)
                                                </span>
                                            )}
                                        </Label>
                                        <Textarea
                                            id="google_credentials"
                                            value={data.google_credentials}
                                            onChange={(e) =>
                                                setData(
                                                    'google_credentials',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder={
                                                settings.has_google_credentials
                                                    ? 'Leave empty to keep existing credentials, or paste new JSON to update'
                                                    : 'Paste your Google service account JSON credentials here'
                                            }
                                            rows={4}
                                        />
                                        {errors.google_credentials && (
                                            <p className="text-sm text-red-500">
                                                {errors.google_credentials}
                                            </p>
                                        )}
                                    </div>

                                    <div className="flex items-center gap-4">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={handleTestConnection}
                                            disabled={testingConnection}
                                        >
                                            {testingConnection ? (
                                                <>
                                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                    Testing...
                                                </>
                                            ) : (
                                                'Test Connection'
                                            )}
                                        </Button>

                                        {connectionResult && (
                                            <div
                                                className={`flex items-center gap-2 ${connectionResult.success ? 'text-green-600' : 'text-red-600'}`}
                                            >
                                                {connectionResult.success ? (
                                                    <CheckCircle className="h-5 w-5" />
                                                ) : (
                                                    <XCircle className="h-5 w-5" />
                                                )}
                                                <span className="text-sm">
                                                    {connectionResult.message}
                                                </span>
                                            </div>
                                        )}
                                    </div>
                                </>
                            )}
                        </CardContent>
                    </Card>

                    {/* Notification Settings */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Mail className="h-5 w-5" />
                                Notification Settings
                            </CardTitle>
                            <CardDescription>
                                Configure email notifications for backup
                                failures
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label>Notification Recipients</Label>
                                <div className="flex gap-2">
                                    <Input
                                        type="email"
                                        value={newEmail}
                                        onChange={(e) =>
                                            setNewEmail(e.target.value)
                                        }
                                        placeholder="Enter email address"
                                        onKeyDown={(e) => {
                                            if (e.key === 'Enter') {
                                                e.preventDefault();
                                                addEmail();
                                            }
                                        }}
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={addEmail}
                                    >
                                        Add
                                    </Button>
                                </div>
                                {errors.notification_emails && (
                                    <p className="text-sm text-red-500">
                                        {errors.notification_emails}
                                    </p>
                                )}
                            </div>

                            {data.notification_emails.length > 0 && (
                                <div className="space-y-2">
                                    <Label>Current Recipients</Label>
                                    <div className="flex flex-wrap gap-2">
                                        {data.notification_emails.map(
                                            (email) => (
                                                <div
                                                    key={email}
                                                    className="flex items-center gap-1 rounded-full bg-gray-100 px-3 py-1 text-sm dark:bg-gray-800"
                                                >
                                                    <span>{email}</span>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            removeEmail(email)
                                                        }
                                                        className="ml-1 text-gray-500 hover:text-red-500"
                                                    >
                                                        <XCircle className="h-4 w-4" />
                                                    </button>
                                                </div>
                                            ),
                                        )}
                                    </div>
                                </div>
                            )}

                            {data.notification_emails.length === 0 && (
                                <p className="text-sm text-amber-600">
                                    No notification recipients configured. You
                                    won't receive alerts for backup failures.
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    {/* Submit Button */}
                    <div className="flex justify-end gap-4">
                        <Button type="button" variant="outline" asChild>
                            <a href="/admin/backups">Cancel</a>
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? (
                                <>
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    Saving...
                                </>
                            ) : (
                                <>
                                    <Save className="mr-2 h-4 w-4" />
                                    Save Settings
                                </>
                            )}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
