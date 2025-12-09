import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTheme, type ThemeConfig, type ThemeColors } from '@/contexts/theme-context';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { AlertCircle, Check, Palette, RefreshCw, Save, Upload, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

interface Props {
    theme: ThemeConfig;
    canManageTheme: boolean;
}

// Color configuration for the picker
const colorConfig: { key: keyof ThemeColors; label: string; description: string }[] = [
    { key: 'primary', label: 'Primary', description: 'Main brand color' },
    { key: 'primaryForeground', label: 'Primary Foreground', description: 'Text on primary' },
    { key: 'secondary', label: 'Secondary', description: 'Secondary elements' },
    { key: 'secondaryForeground', label: 'Secondary Foreground', description: 'Text on secondary' },
    { key: 'accent', label: 'Accent', description: 'Accent highlights' },
    { key: 'accentForeground', label: 'Accent Foreground', description: 'Text on accent' },
    { key: 'success', label: 'Success', description: 'Success states' },
    { key: 'warning', label: 'Warning', description: 'Warning states' },
    { key: 'error', label: 'Error', description: 'Error states' },
    { key: 'info', label: 'Info', description: 'Info states' },
];

// Convert HSL string to CSS hsl() format
function hslToCSS(hsl: string): string {
    return `hsl(${hsl})`;
}

// Parse HSL string to object
function parseHSL(hsl: string): { h: number; s: number; l: number } | null {
    const match = hsl.match(/^(\d{1,3})\s+(\d{1,3})%\s+(\d{1,3})%$/);
    if (!match) return null;
    return {
        h: parseInt(match[1], 10),
        s: parseInt(match[2], 10),
        l: parseInt(match[3], 10),
    };
}

// Format HSL object to string
function formatHSL(h: number, s: number, l: number): string {
    return `${h} ${s}% ${l}%`;
}

// Color swatch component with HSL input
function ColorSwatch({
    colorKey,
    value,
    label,
    description,
    onChange,
    disabled,
}: {
    colorKey: string;
    value: string;
    label: string;
    description: string;
    onChange: (value: string) => void;
    disabled?: boolean;
}) {
    const parsed = parseHSL(value);
    const [h, setH] = useState(parsed?.h ?? 0);
    const [s, setS] = useState(parsed?.s ?? 50);
    const [l, setL] = useState(parsed?.l ?? 50);

    useEffect(() => {
        const newParsed = parseHSL(value);
        if (newParsed) {
            setH(newParsed.h);
            setS(newParsed.s);
            setL(newParsed.l);
        }
    }, [value]);

    const handleChange = useCallback((newH: number, newS: number, newL: number) => {
        const clamped = {
            h: Math.max(0, Math.min(360, newH)),
            s: Math.max(0, Math.min(100, newS)),
            l: Math.max(0, Math.min(100, newL)),
        };
        setH(clamped.h);
        setS(clamped.s);
        setL(clamped.l);
        onChange(formatHSL(clamped.h, clamped.s, clamped.l));
    }, [onChange]);

    return (
        <div className="flex items-center gap-4 rounded-lg border p-3 dark:border-gray-700">
            <div
                className="h-12 w-12 shrink-0 rounded-md border shadow-sm dark:border-gray-600"
                style={{ backgroundColor: hslToCSS(value) }}
            />
            <div className="flex-1 space-y-2">
                <div>
                    <Label className="font-medium">{label}</Label>
                    <p className="text-xs text-muted-foreground">{description}</p>
                </div>
                <div className="flex gap-2">
                    <div className="flex-1">
                        <Label className="text-xs">H (0-360)</Label>
                        <Input
                            type="number"
                            min={0}
                            max={360}
                            value={h}
                            onChange={(e) => handleChange(parseInt(e.target.value) || 0, s, l)}
                            disabled={disabled}
                            className="h-8"
                        />
                    </div>
                    <div className="flex-1">
                        <Label className="text-xs">S (%)</Label>
                        <Input
                            type="number"
                            min={0}
                            max={100}
                            value={s}
                            onChange={(e) => handleChange(h, parseInt(e.target.value) || 0, l)}
                            disabled={disabled}
                            className="h-8"
                        />
                    </div>
                    <div className="flex-1">
                        <Label className="text-xs">L (%)</Label>
                        <Input
                            type="number"
                            min={0}
                            max={100}
                            value={l}
                            onChange={(e) => handleChange(h, s, parseInt(e.target.value) || 0)}
                            disabled={disabled}
                            className="h-8"
                        />
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function ThemeSettingsIndex({ theme: initialTheme, canManageTheme }: Props) {
    const { theme: contextTheme, updateTheme, resetTheme } = useTheme();
    const [localTheme, setLocalTheme] = useState<ThemeConfig>(initialTheme);
    const [isSaving, setIsSaving] = useState(false);
    const [isResetting, setIsResetting] = useState(false);
    const [isUploading, setIsUploading] = useState(false);
    const [hasChanges, setHasChanges] = useState(false);
    const [saveStatus, setSaveStatus] = useState<'idle' | 'success' | 'error'>('idle');
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);

    // Track changes
    useEffect(() => {
        const colorsChanged = JSON.stringify(localTheme.colors) !== JSON.stringify(initialTheme.colors);
        const brandingChanged = JSON.stringify(localTheme.branding) !== JSON.stringify(initialTheme.branding);
        setHasChanges(colorsChanged || brandingChanged);
    }, [localTheme, initialTheme]);

    // Apply live preview
    useEffect(() => {
        updateTheme(localTheme);
    }, [localTheme, updateTheme]);

    const handleColorChange = useCallback((key: keyof ThemeColors, value: string) => {
        setLocalTheme((prev) => ({
            ...prev,
            colors: {
                ...prev.colors,
                [key]: value,
            },
        }));
        setSaveStatus('idle');
    }, []);

    const handleBrandingChange = useCallback((key: 'hospitalName', value: string) => {
        setLocalTheme((prev) => ({
            ...prev,
            branding: {
                ...prev.branding,
                [key]: value,
            },
        }));
        setSaveStatus('idle');
    }, []);


    const handleSave = async () => {
        setIsSaving(true);
        setSaveStatus('idle');
        setErrorMessage(null);

        try {
            const response = await fetch('/api/settings/theme', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    colors: localTheme.colors,
                    branding: { hospitalName: localTheme.branding.hospitalName },
                }),
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Failed to save theme settings');
            }

            setSaveStatus('success');
            setHasChanges(false);
            setTimeout(() => setSaveStatus('idle'), 3000);
        } catch (error) {
            setSaveStatus('error');
            setErrorMessage(error instanceof Error ? error.message : 'Failed to save theme settings');
        } finally {
            setIsSaving(false);
        }
    };

    const handleReset = async () => {
        if (!confirm('Are you sure you want to reset all theme settings to defaults?')) {
            return;
        }

        setIsResetting(true);
        setErrorMessage(null);

        try {
            const response = await fetch('/api/settings/theme/reset', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Failed to reset theme settings');
            }

            setLocalTheme(data.data);
            resetTheme();
            setSaveStatus('success');
            setHasChanges(false);
            router.reload({ only: ['theme'] });
        } catch (error) {
            setErrorMessage(error instanceof Error ? error.message : 'Failed to reset theme settings');
        } finally {
            setIsResetting(false);
        }
    };

    const handleLogoUpload = async (event: React.ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        if (!file) return;

        // Validate file type
        const validTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/svg+xml'];
        if (!validTypes.includes(file.type)) {
            setErrorMessage('Invalid file type. Please upload a PNG, JPG, or SVG file.');
            return;
        }

        // Validate file size (2MB max)
        if (file.size > 2 * 1024 * 1024) {
            setErrorMessage('File size exceeds 2MB limit.');
            return;
        }

        setIsUploading(true);
        setErrorMessage(null);

        try {
            const formData = new FormData();
            formData.append('logo', file);

            const response = await fetch('/api/settings/theme/logo', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: formData,
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Failed to upload logo');
            }

            setLocalTheme((prev) => ({
                ...prev,
                branding: {
                    ...prev.branding,
                    logoUrl: data.data.logoUrl,
                },
            }));
            setSaveStatus('success');
            setTimeout(() => setSaveStatus('idle'), 3000);
        } catch (error) {
            setErrorMessage(error instanceof Error ? error.message : 'Failed to upload logo');
        } finally {
            setIsUploading(false);
            if (fileInputRef.current) {
                fileInputRef.current.value = '';
            }
        }
    };

    const handleRemoveLogo = async () => {
        setLocalTheme((prev) => ({
            ...prev,
            branding: {
                ...prev.branding,
                logoUrl: null,
            },
        }));
        setHasChanges(true);
    };


    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin/users' },
                { title: 'Theme Settings', href: '' },
            ]}
        >
            <Head title="Theme Settings" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="flex items-center gap-2 text-3xl font-bold text-gray-900 dark:text-gray-100">
                            <Palette className="h-8 w-8" />
                            Theme Settings
                        </h1>
                        <p className="mt-2 text-gray-600 dark:text-gray-400">
                            Customize the application's colors and branding
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        {hasChanges && (
                            <Badge variant="warning" className="mr-2">
                                Unsaved changes
                            </Badge>
                        )}
                        {saveStatus === 'success' && (
                            <Badge variant="success" className="mr-2">
                                <Check className="mr-1 h-3 w-3" />
                                Saved
                            </Badge>
                        )}
                        <Button
                            variant="outline"
                            onClick={handleReset}
                            disabled={isResetting || !canManageTheme}
                        >
                            <RefreshCw className={`mr-2 h-4 w-4 ${isResetting ? 'animate-spin' : ''}`} />
                            Reset to Defaults
                        </Button>
                        <Button
                            onClick={handleSave}
                            disabled={isSaving || !hasChanges || !canManageTheme}
                        >
                            <Save className="mr-2 h-4 w-4" />
                            {isSaving ? 'Saving...' : 'Save Changes'}
                        </Button>
                    </div>
                </div>

                {/* Error Message */}
                {errorMessage && (
                    <div className="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 p-4 text-red-800 dark:border-red-800 dark:bg-red-900/20 dark:text-red-200">
                        <AlertCircle className="h-5 w-5" />
                        <span>{errorMessage}</span>
                        <Button
                            variant="ghost"
                            size="sm"
                            className="ml-auto"
                            onClick={() => setErrorMessage(null)}
                        >
                            <X className="h-4 w-4" />
                        </Button>
                    </div>
                )}

                {!canManageTheme && (
                    <div className="flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
                        <AlertCircle className="h-5 w-5" />
                        <span>You don't have permission to modify theme settings. Contact an administrator.</span>
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Branding Section */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Branding</CardTitle>
                            <CardDescription>
                                Customize your hospital's branding
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {/* Hospital Name */}
                            <div className="space-y-2">
                                <Label htmlFor="hospitalName">Hospital Name</Label>
                                <Input
                                    id="hospitalName"
                                    value={localTheme.branding.hospitalName}
                                    onChange={(e) => handleBrandingChange('hospitalName', e.target.value)}
                                    placeholder="Enter hospital name"
                                    disabled={!canManageTheme}
                                />
                            </div>

                            {/* Logo Upload */}
                            <div className="space-y-2">
                                <Label>Logo</Label>
                                <div className="flex items-center gap-4">
                                    {localTheme.branding.logoUrl ? (
                                        <div className="relative">
                                            <img
                                                src={localTheme.branding.logoUrl}
                                                alt="Hospital Logo"
                                                className="h-16 w-16 rounded-lg border object-contain dark:border-gray-700"
                                            />
                                            {canManageTheme && (
                                                <Button
                                                    variant="destructive"
                                                    size="sm"
                                                    className="absolute -right-2 -top-2 h-6 w-6 rounded-full p-0"
                                                    onClick={handleRemoveLogo}
                                                >
                                                    <X className="h-3 w-3" />
                                                </Button>
                                            )}
                                        </div>
                                    ) : (
                                        <div className="flex h-16 w-16 items-center justify-center rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600">
                                            <Upload className="h-6 w-6 text-gray-400" />
                                        </div>
                                    )}
                                    <div className="flex-1">
                                        <input
                                            ref={fileInputRef}
                                            type="file"
                                            accept=".png,.jpg,.jpeg,.svg"
                                            onChange={handleLogoUpload}
                                            className="hidden"
                                            id="logo-upload"
                                            disabled={!canManageTheme || isUploading}
                                        />
                                        <Button
                                            variant="outline"
                                            onClick={() => fileInputRef.current?.click()}
                                            disabled={!canManageTheme || isUploading}
                                        >
                                            <Upload className="mr-2 h-4 w-4" />
                                            {isUploading ? 'Uploading...' : 'Upload Logo'}
                                        </Button>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            PNG, JPG, or SVG. Max 2MB.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Live Preview */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Live Preview</CardTitle>
                            <CardDescription>
                                See how your changes will look
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4 rounded-lg border p-4 dark:border-gray-700">
                                <div className="flex items-center gap-3">
                                    {localTheme.branding.logoUrl ? (
                                        <img
                                            src={localTheme.branding.logoUrl}
                                            alt="Logo Preview"
                                            className="h-10 w-10 rounded object-contain"
                                        />
                                    ) : (
                                        <div
                                            className="flex h-10 w-10 items-center justify-center rounded font-bold text-white"
                                            style={{ backgroundColor: hslToCSS(localTheme.colors.primary) }}
                                        >
                                            H
                                        </div>
                                    )}
                                    <span className="font-semibold">{localTheme.branding.hospitalName}</span>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <button
                                        className="rounded px-3 py-1.5 text-sm font-medium text-white"
                                        style={{ backgroundColor: hslToCSS(localTheme.colors.primary) }}
                                    >
                                        Primary Button
                                    </button>
                                    <button
                                        className="rounded px-3 py-1.5 text-sm font-medium"
                                        style={{
                                            backgroundColor: hslToCSS(localTheme.colors.secondary),
                                            color: hslToCSS(localTheme.colors.secondaryForeground),
                                        }}
                                    >
                                        Secondary
                                    </button>
                                    <button
                                        className="rounded px-3 py-1.5 text-sm font-medium text-white"
                                        style={{ backgroundColor: hslToCSS(localTheme.colors.accent) }}
                                    >
                                        Accent
                                    </button>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <span
                                        className="rounded-full px-2 py-0.5 text-xs font-medium text-white"
                                        style={{ backgroundColor: hslToCSS(localTheme.colors.success) }}
                                    >
                                        Success
                                    </span>
                                    <span
                                        className="rounded-full px-2 py-0.5 text-xs font-medium text-white"
                                        style={{ backgroundColor: hslToCSS(localTheme.colors.warning) }}
                                    >
                                        Warning
                                    </span>
                                    <span
                                        className="rounded-full px-2 py-0.5 text-xs font-medium text-white"
                                        style={{ backgroundColor: hslToCSS(localTheme.colors.error) }}
                                    >
                                        Error
                                    </span>
                                    <span
                                        className="rounded-full px-2 py-0.5 text-xs font-medium text-white"
                                        style={{ backgroundColor: hslToCSS(localTheme.colors.info) }}
                                    >
                                        Info
                                    </span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Color Settings */}
                <Card>
                    <CardHeader>
                        <CardTitle>Color Palette</CardTitle>
                        <CardDescription>
                            Customize the application colors using HSL values
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-2">
                            {colorConfig.map(({ key, label, description }) => (
                                <ColorSwatch
                                    key={key}
                                    colorKey={key}
                                    value={localTheme.colors[key]}
                                    label={label}
                                    description={description}
                                    onChange={(value) => handleColorChange(key, value)}
                                    disabled={!canManageTheme}
                                />
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
