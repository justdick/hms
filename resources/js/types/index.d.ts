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
