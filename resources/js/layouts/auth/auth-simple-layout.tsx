import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';

interface AuthLayoutProps {
    name?: string;
    title?: string;
    description?: string;
}

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: PropsWithChildren<AuthLayoutProps>) {
    const { theme } = usePage<SharedData>().props;
    const logoUrl = theme?.branding?.logoUrl;
    const hospitalName = theme?.branding?.hospitalName || 'Hospital Management System';

    return (
        <div className="relative flex min-h-svh flex-col items-center justify-center p-6 md:p-10">
            {/* Subtle gradient background */}
            <div className="absolute inset-0 -z-10 bg-gradient-to-br from-slate-50 via-white to-blue-50 dark:from-slate-950 dark:via-slate-900 dark:to-slate-950" />
            
            {/* Decorative elements */}
            <div className="absolute top-0 left-1/4 -z-10 h-72 w-72 rounded-full bg-blue-100/40 blur-3xl dark:bg-blue-900/20" />
            <div className="absolute bottom-0 right-1/4 -z-10 h-72 w-72 rounded-full bg-indigo-100/40 blur-3xl dark:bg-indigo-900/20" />

            <div className="w-full max-w-md">
                {/* Card container */}
                <div className="rounded-2xl border border-slate-200/60 bg-white/80 px-8 py-10 shadow-xl shadow-slate-200/50 backdrop-blur-sm dark:border-slate-800/60 dark:bg-slate-900/80 dark:shadow-slate-900/50">
                    <div className="flex flex-col gap-8">
                        {/* Logo and header */}
                        <div className="flex flex-col items-center gap-6">
                            <Link
                                href={home()}
                                className="flex flex-col items-center gap-3 transition-opacity hover:opacity-80"
                            >
                                <div className="flex h-16 w-16 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 to-indigo-600 p-3 shadow-lg shadow-blue-500/25">
                                    {logoUrl ? (
                                        <img
                                            src={logoUrl}
                                            alt={hospitalName}
                                            className="size-full object-contain"
                                        />
                                    ) : (
                                        <AppLogoIcon className="size-full fill-current text-white" />
                                    )}
                                </div>
                                <span className="text-xl font-bold tracking-tight text-slate-900 dark:text-white">
                                    {hospitalName}
                                </span>
                            </Link>

                            <div className="space-y-2 text-center">
                                <h1 className="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">
                                    {title}
                                </h1>
                                <p className="text-sm text-slate-500 dark:text-slate-400">
                                    {description}
                                </p>
                            </div>
                        </div>

                        {/* Form content */}
                        {children}
                    </div>
                </div>

                {/* Footer */}
                <p className="mt-6 text-center text-xs text-slate-400 dark:text-slate-500">
                    © {new Date().getFullYear()} {hospitalName}. All rights reserved.
                </p>
            </div>
        </div>
    );
}
