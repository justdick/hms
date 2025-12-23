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
    const hospitalName =
        theme?.branding?.hospitalName || 'Hospital Management System';

    return (
        <div className="relative flex min-h-svh flex-col items-center justify-center p-6 md:p-10">
            {/* Subtle gradient background using theme colors */}
            <div className="absolute inset-0 -z-10 bg-gradient-to-br from-secondary via-background to-secondary/50 dark:from-background dark:via-background dark:to-secondary/20" />

            {/* Decorative elements using theme primary color */}
            <div className="absolute top-0 left-1/4 -z-10 h-72 w-72 rounded-full bg-primary/10 blur-3xl dark:bg-primary/5" />
            <div className="absolute right-1/4 bottom-0 -z-10 h-72 w-72 rounded-full bg-accent/20 blur-3xl dark:bg-accent/10" />

            <div className="w-full max-w-md">
                {/* Card container */}
                <div className="rounded-2xl border border-border/60 bg-card/80 px-8 py-10 shadow-xl shadow-muted/50 backdrop-blur-sm dark:border-border/60 dark:bg-card/80 dark:shadow-background/50">
                    <div className="flex flex-col gap-8">
                        {/* Logo and header */}
                        <div className="flex flex-col items-center gap-6">
                            <Link
                                href={home()}
                                className="flex flex-col items-center gap-3 transition-opacity hover:opacity-80"
                            >
                                <div className="flex h-16 w-16 items-center justify-center rounded-xl bg-primary p-3 shadow-lg shadow-primary/25">
                                    {logoUrl ? (
                                        <img
                                            src={logoUrl}
                                            alt={hospitalName}
                                            className="size-full object-contain"
                                        />
                                    ) : (
                                        <AppLogoIcon className="size-full fill-current text-primary-foreground" />
                                    )}
                                </div>
                                <span className="text-xl font-bold tracking-tight text-foreground">
                                    {hospitalName}
                                </span>
                            </Link>

                            <div className="space-y-2 text-center">
                                <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                                    {title}
                                </h1>
                                <p className="text-sm text-muted-foreground">
                                    {description}
                                </p>
                            </div>
                        </div>

                        {/* Form content */}
                        {children}
                    </div>
                </div>

                {/* Footer */}
                <p className="mt-6 text-center text-xs text-muted-foreground">
                    © {new Date().getFullYear()} {hospitalName}. All rights
                    reserved.
                </p>
            </div>
        </div>
    );
}
