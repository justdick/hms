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
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { ExternalLink, Info, Settings, Shield } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

interface NhisSettings {
    id: number;
    verification_mode: 'manual' | 'extension';
    nhia_portal_url: string;
    facility_code: string | null;
    nhia_username: string | null;
    auto_open_portal: boolean;
}

interface Props {
    settings: NhisSettings;
}

export default function NhisSettingsIndex({ settings }: Props) {
    const [formData, setFormData] = useState({
        verification_mode: settings.verification_mode,
        nhia_portal_url: settings.nhia_portal_url,
        facility_code: settings.facility_code || '',
        nhia_username: settings.nhia_username || '',
        nhia_password: '', // Never pre-fill password
        auto_open_portal: settings.auto_open_portal,
    });
    const [saving, setSaving] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);

        router.put('/admin/nhis-settings', formData, {
            onSuccess: () => {
                toast.success('NHIS settings saved successfully');
                setSaving(false);
            },
            onError: () => {
                toast.error('Failed to save settings');
                setSaving(false);
            },
        });
    };

    return (
        <AppLayout>
            <Head title="NHIS Settings" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">
                        NHIS Settings
                    </h1>
                    <p className="text-muted-foreground">
                        Configure NHIS verification and claims settings
                    </p>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Verification Mode */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Shield className="h-5 w-5" />
                                CCC Verification Mode
                            </CardTitle>
                            <CardDescription>
                                Choose how staff will verify NHIS membership and
                                obtain CCC codes
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <RadioGroup
                                value={formData.verification_mode}
                                onValueChange={(value) =>
                                    setFormData({
                                        ...formData,
                                        verification_mode: value as
                                            | 'manual'
                                            | 'extension',
                                    })
                                }
                                className="space-y-4"
                            >
                                <div className="flex items-start space-x-3 rounded-lg border p-4">
                                    <RadioGroupItem
                                        value="manual"
                                        id="manual"
                                    />
                                    <div className="space-y-1">
                                        <Label
                                            htmlFor="manual"
                                            className="font-medium"
                                        >
                                            Manual Entry
                                        </Label>
                                        <p className="text-sm text-muted-foreground">
                                            Staff manually opens the NHIA portal,
                                            verifies membership, and enters the
                                            CCC code into HMS.
                                        </p>
                                    </div>
                                </div>

                                <div className="flex items-start space-x-3 rounded-lg border p-4">
                                    <RadioGroupItem
                                        value="extension"
                                        id="extension"
                                    />
                                    <div className="space-y-1">
                                        <Label
                                            htmlFor="extension"
                                            className="font-medium"
                                        >
                                            Browser Extension (Automated)
                                        </Label>
                                        <p className="text-sm text-muted-foreground">
                                            Uses the HMS NHIS Extension to
                                            automatically verify membership and
                                            capture CCC codes. Requires extension
                                            installation on all workstations.
                                        </p>
                                    </div>
                                </div>
                            </RadioGroup>

                            {formData.verification_mode === 'extension' && (
                                <div className="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-950/20">
                                    <div className="flex items-start gap-3">
                                        <Info className="mt-0.5 h-5 w-5 text-blue-600 dark:text-blue-400" />
                                        <div className="space-y-2">
                                            <p className="text-sm font-medium text-blue-700 dark:text-blue-300">
                                                Extension Required
                                            </p>
                                            <p className="text-sm text-blue-600 dark:text-blue-400">
                                                The browser extension must be
                                                installed on all workstations
                                                that will perform NHIS
                                                verification. Download and
                                                install from the{' '}
                                                <code className="rounded bg-blue-100 px-1 dark:bg-blue-900">
                                                    nhis-ccc-extension
                                                </code>{' '}
                                                folder.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Portal Settings */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Settings className="h-5 w-5" />
                                Portal Settings
                            </CardTitle>
                            <CardDescription>
                                Configure NHIA portal connection settings
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="nhia_portal_url">
                                    NHIA Portal URL
                                </Label>
                                <div className="flex gap-2">
                                    <Input
                                        id="nhia_portal_url"
                                        type="url"
                                        value={formData.nhia_portal_url}
                                        onChange={(e) =>
                                            setFormData({
                                                ...formData,
                                                nhia_portal_url: e.target.value,
                                            })
                                        }
                                        placeholder="https://ccc.nhia.gov.gh/"
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        onClick={() =>
                                            window.open(
                                                formData.nhia_portal_url,
                                                '_blank',
                                            )
                                        }
                                    >
                                        <ExternalLink className="h-4 w-4" />
                                    </Button>
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    The URL of the NHIA Claims Clearinghouse
                                    portal
                                </p>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="facility_code">
                                    Facility Code (HP ID)
                                </Label>
                                <Input
                                    id="facility_code"
                                    type="text"
                                    value={formData.facility_code}
                                    onChange={(e) =>
                                        setFormData({
                                            ...formData,
                                            facility_code: e.target.value,
                                        })
                                    }
                                    placeholder="e.g., 11586"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Your facility's Health Provider ID from NHIA
                                </p>
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="nhia_username">
                                        NHIA Username
                                    </Label>
                                    <Input
                                        id="nhia_username"
                                        type="text"
                                        value={formData.nhia_username}
                                        onChange={(e) =>
                                            setFormData({
                                                ...formData,
                                                nhia_username: e.target.value,
                                            })
                                        }
                                        placeholder="e.g., HP11586"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="nhia_password">
                                        NHIA Password
                                    </Label>
                                    <Input
                                        id="nhia_password"
                                        type="password"
                                        value={formData.nhia_password}
                                        onChange={(e) =>
                                            setFormData({
                                                ...formData,
                                                nhia_password: e.target.value,
                                            })
                                        }
                                        placeholder="Leave blank to keep existing"
                                    />
                                </div>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Credentials for automatic login to NHIA portal (used by extension). Password is encrypted.
                            </p>

                            <div className="flex items-center justify-between rounded-lg border p-4">
                                <div className="space-y-0.5">
                                    <Label htmlFor="auto_open_portal">
                                        Auto-open Portal
                                    </Label>
                                    <p className="text-sm text-muted-foreground">
                                        Automatically open the NHIA portal when
                                        verifying membership
                                    </p>
                                </div>
                                <Switch
                                    id="auto_open_portal"
                                    checked={formData.auto_open_portal}
                                    onCheckedChange={(checked) =>
                                        setFormData({
                                            ...formData,
                                            auto_open_portal: checked,
                                        })
                                    }
                                />
                            </div>
                        </CardContent>
                    </Card>

                    {/* Save Button */}
                    <div className="flex justify-end">
                        <Button type="submit" disabled={saving}>
                            {saving ? 'Saving...' : 'Save Settings'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
