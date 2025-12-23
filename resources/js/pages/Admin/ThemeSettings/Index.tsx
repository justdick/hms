import { Badge } from '@/components/ui/badge';
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
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    useTheme,
    type ThemeColors,
    type ThemeConfig,
} from '@/contexts/theme-context';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import {
    AlertCircle,
    Check,
    Palette,
    RefreshCw,
    Save,
    Upload,
    X,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { HslColorPicker } from 'react-colorful';

// Get CSRF token from cookie (more reliable than meta tag on first load)
function getCsrfToken(): string {
    // Try meta tag first
    const metaToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');
    if (metaToken) return metaToken;

    // Fall back to XSRF-TOKEN cookie (Laravel sets this)
    const cookies = document.cookie.split(';');
    for (const cookie of cookies) {
        const [name, value] = cookie.trim().split('=');
        if (name === 'XSRF-TOKEN') {
            return decodeURIComponent(value);
        }
    }
    return '';
}

interface Props {
    theme: ThemeConfig;
    canManageTheme: boolean;
}

// Color configuration for the picker - grouped by section
const mainColorConfig: {
    key: keyof ThemeColors;
    label: string;
    description: string;
}[] = [
    { key: 'primary', label: 'Primary', description: 'Main brand color' },
    {
        key: 'primaryForeground',
        label: 'Primary Foreground',
        description: 'Text on primary',
    },
    { key: 'secondary', label: 'Secondary', description: 'Secondary elements' },
    {
        key: 'secondaryForeground',
        label: 'Secondary Foreground',
        description: 'Text on secondary',
    },
    { key: 'accent', label: 'Accent', description: 'Accent highlights' },
    {
        key: 'accentForeground',
        label: 'Accent Foreground',
        description: 'Text on accent',
    },
];

const statusColorConfig: {
    key: keyof ThemeColors;
    label: string;
    description: string;
}[] = [
    { key: 'success', label: 'Success', description: 'Success states' },
    { key: 'warning', label: 'Warning', description: 'Warning states' },
    { key: 'error', label: 'Error', description: 'Error states' },
    { key: 'info', label: 'Info', description: 'Info states' },
];

const sidebarColorConfig: {
    key: keyof ThemeColors;
    label: string;
    description: string;
}[] = [
    {
        key: 'sidebar',
        label: 'Sidebar Background',
        description: 'Sidebar background color',
    },
    {
        key: 'sidebarForeground',
        label: 'Sidebar Text',
        description: 'Text color in sidebar',
    },
    {
        key: 'sidebarPrimary',
        label: 'Sidebar Primary',
        description: 'Active/selected items',
    },
    {
        key: 'sidebarPrimaryForeground',
        label: 'Sidebar Primary Text',
        description: 'Text on active items',
    },
    {
        key: 'sidebarAccent',
        label: 'Sidebar Accent',
        description: 'Hover/highlight background',
    },
    {
        key: 'sidebarAccentForeground',
        label: 'Sidebar Accent Text',
        description: 'Text on hover items',
    },
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

// Color swatch component with visual color picker
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
    const [localColor, setLocalColor] = useState({
        h: parsed?.h ?? 0,
        s: parsed?.s ?? 50,
        l: parsed?.l ?? 50,
    });
    const [isOpen, setIsOpen] = useState(false);
    const isInternalChange = useRef(false);

    // Sync from prop only when it changes externally
    useEffect(() => {
        if (isInternalChange.current) {
            isInternalChange.current = false;
            return;
        }
        const newParsed = parseHSL(value);
        if (newParsed) {
            setLocalColor({ h: newParsed.h, s: newParsed.s, l: newParsed.l });
        }
    }, [value]);

    const handlePickerChange = useCallback(
        (color: { h: number; s: number; l: number }) => {
            const clamped = {
                h: Math.max(0, Math.min(360, Math.round(color.h))),
                s: Math.max(0, Math.min(100, Math.round(color.s))),
                l: Math.max(0, Math.min(100, Math.round(color.l))),
            };
            setLocalColor(clamped);
            isInternalChange.current = true;
            onChange(formatHSL(clamped.h, clamped.s, clamped.l));
        },
        [onChange],
    );

    const handleInputChange = useCallback(
        (field: 'h' | 's' | 'l', val: number) => {
            const max = field === 'h' ? 360 : 100;
            const clamped = Math.max(0, Math.min(max, val));
            const newColor = { ...localColor, [field]: clamped };
            setLocalColor(newColor);
            isInternalChange.current = true;
            onChange(formatHSL(newColor.h, newColor.s, newColor.l));
        },
        [localColor, onChange],
    );

    return (
        <div className="flex items-center gap-3 rounded-lg border p-3 dark:border-gray-700">
            <Popover open={isOpen} onOpenChange={setIsOpen}>
                <PopoverTrigger asChild disabled={disabled}>
                    <button
                        className="h-10 w-10 shrink-0 rounded-md border shadow-sm transition-transform hover:scale-105 focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600"
                        style={{ backgroundColor: hslToCSS(value) }}
                        aria-label={`Pick color for ${label}`}
                    />
                </PopoverTrigger>
                <PopoverContent className="w-auto p-3" align="start">
                    <HslColorPicker
                        color={localColor}
                        onChange={handlePickerChange}
                    />
                    <div className="mt-3 flex gap-2">
                        <div className="flex-1">
                            <Label className="text-xs">H</Label>
                            <Input
                                type="number"
                                min={0}
                                max={360}
                                value={localColor.h}
                                onChange={(e) =>
                                    handleInputChange(
                                        'h',
                                        parseInt(e.target.value) || 0,
                                    )
                                }
                                className="h-7 text-xs"
                            />
                        </div>
                        <div className="flex-1">
                            <Label className="text-xs">S</Label>
                            <Input
                                type="number"
                                min={0}
                                max={100}
                                value={localColor.s}
                                onChange={(e) =>
                                    handleInputChange(
                                        's',
                                        parseInt(e.target.value) || 0,
                                    )
                                }
                                className="h-7 text-xs"
                            />
                        </div>
                        <div className="flex-1">
                            <Label className="text-xs">L</Label>
                            <Input
                                type="number"
                                min={0}
                                max={100}
                                value={localColor.l}
                                onChange={(e) =>
                                    handleInputChange(
                                        'l',
                                        parseInt(e.target.value) || 0,
                                    )
                                }
                                className="h-7 text-xs"
                            />
                        </div>
                    </div>
                </PopoverContent>
            </Popover>
            <div className="min-w-0 flex-1">
                <Label className="text-sm font-medium">{label}</Label>
                <p className="truncate text-xs text-muted-foreground">
                    {description}
                </p>
            </div>
        </div>
    );
}

export default function ThemeSettingsIndex({
    theme: initialTheme,
    canManageTheme,
}: Props) {
    const { theme: contextTheme, updateTheme, resetTheme } = useTheme();
    const [localTheme, setLocalTheme] = useState<ThemeConfig>(initialTheme);
    const [isSaving, setIsSaving] = useState(false);
    const [isResetting, setIsResetting] = useState(false);
    const [isUploading, setIsUploading] = useState(false);
    const [hasChanges, setHasChanges] = useState(false);
    const [saveStatus, setSaveStatus] = useState<'idle' | 'success' | 'error'>(
        'idle',
    );
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);

    // Track changes
    useEffect(() => {
        const colorsChanged =
            JSON.stringify(localTheme.colors) !==
            JSON.stringify(initialTheme.colors);
        const brandingChanged =
            JSON.stringify(localTheme.branding) !==
            JSON.stringify(initialTheme.branding);
        setHasChanges(colorsChanged || brandingChanged);
    }, [localTheme, initialTheme]);

    // Apply live preview
    useEffect(() => {
        updateTheme(localTheme);
    }, [localTheme, updateTheme]);

    const handleColorChange = useCallback(
        (key: keyof ThemeColors, value: string) => {
            setLocalTheme((prev) => ({
                ...prev,
                colors: {
                    ...prev.colors,
                    [key]: value,
                },
            }));
            setSaveStatus('idle');
        },
        [],
    );

    const handleBrandingChange = useCallback(
        (key: 'hospitalName', value: string) => {
            setLocalTheme((prev) => ({
                ...prev,
                branding: {
                    ...prev.branding,
                    [key]: value,
                },
            }));
            setSaveStatus('idle');
        },
        [],
    );

    const handleSave = async () => {
        setIsSaving(true);
        setSaveStatus('idle');
        setErrorMessage(null);

        const csrfToken = getCsrfToken();
        try {
            const response = await fetch('/api/settings/theme', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-XSRF-TOKEN': csrfToken,
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    colors: localTheme.colors,
                    branding: {
                        hospitalName: localTheme.branding.hospitalName,
                    },
                }),
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(
                    data.message || 'Failed to save theme settings',
                );
            }

            setSaveStatus('success');
            setHasChanges(false);
            setTimeout(() => setSaveStatus('idle'), 3000);
        } catch (error) {
            setSaveStatus('error');
            setErrorMessage(
                error instanceof Error
                    ? error.message
                    : 'Failed to save theme settings',
            );
        } finally {
            setIsSaving(false);
        }
    };

    const handleReset = async () => {
        if (
            !confirm(
                'Are you sure you want to reset all theme settings to defaults?',
            )
        ) {
            return;
        }

        setIsResetting(true);
        setErrorMessage(null);

        const csrfToken = getCsrfToken();
        try {
            const response = await fetch('/api/settings/theme/reset', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-XSRF-TOKEN': csrfToken,
                },
                credentials: 'same-origin',
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(
                    data.message || 'Failed to reset theme settings',
                );
            }

            setLocalTheme(data.data);
            resetTheme();
            setSaveStatus('success');
            setHasChanges(false);
            router.reload({ only: ['theme'] });
        } catch (error) {
            setErrorMessage(
                error instanceof Error
                    ? error.message
                    : 'Failed to reset theme settings',
            );
        } finally {
            setIsResetting(false);
        }
    };

    const handleLogoUpload = async (
        event: React.ChangeEvent<HTMLInputElement>,
    ) => {
        const file = event.target.files?.[0];
        if (!file) return;

        // Validate file type
        const validTypes = [
            'image/png',
            'image/jpeg',
            'image/jpg',
            'image/svg+xml',
        ];
        if (!validTypes.includes(file.type)) {
            setErrorMessage(
                'Invalid file type. Please upload a PNG, JPG, or SVG file.',
            );
            return;
        }

        // Validate file size (2MB max)
        if (file.size > 2 * 1024 * 1024) {
            setErrorMessage('File size exceeds 2MB limit.');
            return;
        }

        setIsUploading(true);
        setErrorMessage(null);

        const csrfToken = getCsrfToken();
        try {
            const formData = new FormData();
            formData.append('logo', file);

            const response = await fetch('/api/settings/theme/logo', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'X-XSRF-TOKEN': csrfToken,
                },
                credentials: 'same-origin',
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
            setErrorMessage(
                error instanceof Error
                    ? error.message
                    : 'Failed to upload logo',
            );
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
                            <RefreshCw
                                className={`mr-2 h-4 w-4 ${isResetting ? 'animate-spin' : ''}`}
                            />
                            Reset to Defaults
                        </Button>
                        <Button
                            onClick={handleSave}
                            disabled={
                                isSaving || !hasChanges || !canManageTheme
                            }
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
                        <span>
                            You don't have permission to modify theme settings.
                            Contact an administrator.
                        </span>
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
                                <Label htmlFor="hospitalName">
                                    Hospital Name
                                </Label>
                                <Input
                                    id="hospitalName"
                                    value={localTheme.branding.hospitalName}
                                    onChange={(e) =>
                                        handleBrandingChange(
                                            'hospitalName',
                                            e.target.value,
                                        )
                                    }
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
                                                src={
                                                    localTheme.branding.logoUrl
                                                }
                                                alt="Hospital Logo"
                                                className="h-16 w-16 rounded-lg border object-contain dark:border-gray-700"
                                            />
                                            {canManageTheme && (
                                                <Button
                                                    variant="destructive"
                                                    size="sm"
                                                    className="absolute -top-2 -right-2 h-6 w-6 rounded-full p-0"
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
                                            disabled={
                                                !canManageTheme || isUploading
                                            }
                                        />
                                        <Button
                                            variant="outline"
                                            onClick={() =>
                                                fileInputRef.current?.click()
                                            }
                                            disabled={
                                                !canManageTheme || isUploading
                                            }
                                        >
                                            <Upload className="mr-2 h-4 w-4" />
                                            {isUploading
                                                ? 'Uploading...'
                                                : 'Upload Logo'}
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
                                            style={{
                                                backgroundColor: hslToCSS(
                                                    localTheme.colors.primary,
                                                ),
                                            }}
                                        >
                                            H
                                        </div>
                                    )}
                                    <span className="font-semibold">
                                        {localTheme.branding.hospitalName}
                                    </span>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <button
                                        className="rounded px-3 py-1.5 text-sm font-medium text-white"
                                        style={{
                                            backgroundColor: hslToCSS(
                                                localTheme.colors.primary,
                                            ),
                                        }}
                                    >
                                        Primary Button
                                    </button>
                                    <button
                                        className="rounded px-3 py-1.5 text-sm font-medium"
                                        style={{
                                            backgroundColor: hslToCSS(
                                                localTheme.colors.secondary,
                                            ),
                                            color: hslToCSS(
                                                localTheme.colors
                                                    .secondaryForeground,
                                            ),
                                        }}
                                    >
                                        Secondary
                                    </button>
                                    <button
                                        className="rounded px-3 py-1.5 text-sm font-medium text-white"
                                        style={{
                                            backgroundColor: hslToCSS(
                                                localTheme.colors.accent,
                                            ),
                                        }}
                                    >
                                        Accent
                                    </button>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <span
                                        className="rounded-full px-2 py-0.5 text-xs font-medium text-white"
                                        style={{
                                            backgroundColor: hslToCSS(
                                                localTheme.colors.success,
                                            ),
                                        }}
                                    >
                                        Success
                                    </span>
                                    <span
                                        className="rounded-full px-2 py-0.5 text-xs font-medium text-white"
                                        style={{
                                            backgroundColor: hslToCSS(
                                                localTheme.colors.warning,
                                            ),
                                        }}
                                    >
                                        Warning
                                    </span>
                                    <span
                                        className="rounded-full px-2 py-0.5 text-xs font-medium text-white"
                                        style={{
                                            backgroundColor: hslToCSS(
                                                localTheme.colors.error,
                                            ),
                                        }}
                                    >
                                        Error
                                    </span>
                                    <span
                                        className="rounded-full px-2 py-0.5 text-xs font-medium text-white"
                                        style={{
                                            backgroundColor: hslToCSS(
                                                localTheme.colors.info,
                                            ),
                                        }}
                                    >
                                        Info
                                    </span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Main Colors */}
                <Card>
                    <CardHeader>
                        <CardTitle>Main Colors</CardTitle>
                        <CardDescription>
                            Primary brand colors used throughout the application
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {mainColorConfig.map(
                                ({ key, label, description }) => (
                                    <ColorSwatch
                                        key={key}
                                        colorKey={key}
                                        value={
                                            localTheme.colors[key] ?? '0 0% 50%'
                                        }
                                        label={label}
                                        description={description}
                                        onChange={(value) =>
                                            handleColorChange(key, value)
                                        }
                                        disabled={!canManageTheme}
                                    />
                                ),
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Status Colors */}
                <Card>
                    <CardHeader>
                        <CardTitle>Status Colors</CardTitle>
                        <CardDescription>
                            Colors for success, warning, error, and info states
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            {statusColorConfig.map(
                                ({ key, label, description }) => (
                                    <ColorSwatch
                                        key={key}
                                        colorKey={key}
                                        value={
                                            localTheme.colors[key] ?? '0 0% 50%'
                                        }
                                        label={label}
                                        description={description}
                                        onChange={(value) =>
                                            handleColorChange(key, value)
                                        }
                                        disabled={!canManageTheme}
                                    />
                                ),
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Sidebar Colors */}
                <Card>
                    <CardHeader>
                        <CardTitle>Sidebar Colors</CardTitle>
                        <CardDescription>
                            Customize the sidebar appearance including the area
                            around the logo
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {sidebarColorConfig.map(
                                ({ key, label, description }) => (
                                    <ColorSwatch
                                        key={key}
                                        colorKey={key}
                                        value={
                                            localTheme.colors[key] ?? '0 0% 50%'
                                        }
                                        label={label}
                                        description={description}
                                        onChange={(value) =>
                                            handleColorChange(key, value)
                                        }
                                        disabled={!canManageTheme}
                                    />
                                ),
                            )}
                        </div>
                        {/* Sidebar Preview */}
                        <div className="mt-4 rounded-lg border p-4 dark:border-gray-700">
                            <p className="mb-2 text-sm font-medium">
                                Sidebar Preview
                            </p>
                            <div
                                className="w-48 rounded-lg p-3"
                                style={{
                                    backgroundColor: hslToCSS(
                                        localTheme.colors.sidebar ??
                                            '210 20% 98%',
                                    ),
                                }}
                            >
                                <div className="mb-3 flex items-center gap-2">
                                    <div
                                        className="flex h-8 w-8 items-center justify-center rounded text-xs font-bold"
                                        style={{
                                            backgroundColor: hslToCSS(
                                                localTheme.colors
                                                    .sidebarPrimary ??
                                                    '210 90% 45%',
                                            ),
                                            color: hslToCSS(
                                                localTheme.colors
                                                    .sidebarPrimaryForeground ??
                                                    '0 0% 100%',
                                            ),
                                        }}
                                    >
                                        H
                                    </div>
                                    <span
                                        className="truncate text-sm font-semibold"
                                        style={{
                                            color: hslToCSS(
                                                localTheme.colors
                                                    .sidebarForeground ??
                                                    '210 40% 20%',
                                            ),
                                        }}
                                    >
                                        Hospital
                                    </span>
                                </div>
                                <div className="space-y-1">
                                    <div
                                        className="rounded px-2 py-1.5 text-xs"
                                        style={{
                                            backgroundColor: hslToCSS(
                                                localTheme.colors
                                                    .sidebarAccent ??
                                                    '210 30% 94%',
                                            ),
                                            color: hslToCSS(
                                                localTheme.colors
                                                    .sidebarAccentForeground ??
                                                    '210 40% 25%',
                                            ),
                                        }}
                                    >
                                        Dashboard
                                    </div>
                                    <div
                                        className="px-2 py-1.5 text-xs"
                                        style={{
                                            color: hslToCSS(
                                                localTheme.colors
                                                    .sidebarForeground ??
                                                    '210 40% 20%',
                                            ),
                                        }}
                                    >
                                        Patients
                                    </div>
                                    <div
                                        className="px-2 py-1.5 text-xs"
                                        style={{
                                            color: hslToCSS(
                                                localTheme.colors
                                                    .sidebarForeground ??
                                                    '210 40% 20%',
                                            ),
                                        }}
                                    >
                                        Check-in
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
