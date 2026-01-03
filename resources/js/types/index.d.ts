import { InertiaLinkProps } from '@inertiajs/react';
import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
    permissions?: {
        pharmacy?: {
            inventory: boolean;
            dispensing: boolean;
        };
        admissions?: {
            discharge: boolean;
        };
        billing?: {
            viewAll: boolean;
            collect: boolean;
            override: boolean;
            reconcile: boolean;
            reports: boolean;
            statements: boolean;
            manageCredit: boolean;
            void: boolean;
            refund: boolean;
            configure: boolean;
        };
        backups?: {
            view: boolean;
            create: boolean;
            delete: boolean;
            restore: boolean;
            manageSettings: boolean;
        };
        users?: {
            viewAll: boolean;
            create: boolean;
            update: boolean;
            resetPassword: boolean;
        };
        roles?: {
            viewAll: boolean;
            create: boolean;
            update: boolean;
            delete: boolean;
        };
        theme?: {
            view: boolean;
            manage: boolean;
        };
        nhisSettings?: {
            view: boolean;
            manage: boolean;
        };
        pricing?: {
            view: boolean;
            edit: boolean;
        };
        investigations?: {
            viewLab: boolean;
            viewRadiology: boolean;
            uploadExternal: boolean;
        };
        checkins?: {
            view: boolean;
        };
        patients?: {
            view: boolean;
        };
        consultations?: {
            view: boolean;
        };
        wards?: {
            view: boolean;
        };
        minorProcedures?: {
            view: boolean;
        };
        departments?: {
            view: boolean;
        };
        insurance?: {
            view: boolean;
        };
    };
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    items?: NavItem[];
}

export interface ThemeColors {
    primary: string;
    primaryForeground: string;
    secondary: string;
    secondaryForeground: string;
    accent: string;
    accentForeground: string;
    success: string;
    warning: string;
    error: string;
    info: string;
}

export interface ThemeBranding {
    logoUrl: string | null;
    hospitalName: string;
}

export interface ThemeConfig {
    colors: ThemeColors;
    branding: ThemeBranding;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    sidebarOpen: boolean;
    flash?: {
        success?: string;
        error?: string;
        warning?: string;
        info?: string;
    };
    theme?: ThemeConfig;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}
