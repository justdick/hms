import AppLogoIcon from '@/components/app-logo-icon';
import { dashboard, login } from '@/routes';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    Activity,
    Beaker,
    CalendarCheck,
    ClipboardList,
    CreditCard,
    Pill,
    Shield,
    Stethoscope,
    Users,
} from 'lucide-react';

const features = [
    {
        icon: Users,
        title: 'Patient Management',
        description:
            'Complete patient registration and multi-visit history tracking',
    },
    {
        icon: CalendarCheck,
        title: 'Check-in & OPD',
        description:
            'Walk-in patient check-in with department-based queue management',
    },
    {
        icon: Stethoscope,
        title: 'Consultations',
        description: 'SOAP notes, diagnoses, prescriptions, and lab ordering',
    },
    {
        icon: ClipboardList,
        title: 'Ward Management',
        description:
            'Admissions, bed management, ward rounds, and nursing care',
    },
    {
        icon: Pill,
        title: 'Pharmacy',
        description:
            'Drug inventory, batch tracking, and prescription dispensing',
    },
    {
        icon: Beaker,
        title: 'Laboratory',
        description:
            'Lab service catalog, sample tracking, and result reporting',
    },
    {
        icon: CreditCard,
        title: 'Billing & Revenue',
        description:
            'Itemized billing, multiple payment methods, and reporting',
    },
    {
        icon: Shield,
        title: 'Insurance Claims',
        description:
            'Coverage management, claims vetting, and batch processing',
    },
];

export default function Welcome() {
    const { auth, theme } = usePage<SharedData>().props;
    const logoUrl = theme?.branding?.logoUrl;
    const hospitalName =
        theme?.branding?.hospitalName || 'Hospital Management System';

    return (
        <>
            <Head title="Welcome" />
            <div className="flex min-h-screen flex-col bg-gradient-to-br from-secondary via-background to-secondary/50 dark:from-background dark:via-background dark:to-secondary/20">
                {/* Decorative background elements */}
                <div className="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
                    <div className="absolute top-0 left-1/4 h-96 w-96 rounded-full bg-primary/10 blur-3xl dark:bg-primary/5" />
                    <div className="absolute right-1/4 bottom-1/4 h-96 w-96 rounded-full bg-accent/15 blur-3xl dark:bg-accent/10" />
                </div>

                {/* Header */}
                <header className="border-b border-border/40 bg-background/80 backdrop-blur-sm">
                    <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                        <div className="flex items-center gap-3">
                            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary p-2 shadow-md shadow-primary/25">
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
                            <span className="text-lg font-semibold text-foreground">
                                {hospitalName}
                            </span>
                        </div>
                        <nav className="flex items-center gap-3">
                            {auth.user ? (
                                <Link
                                    href={dashboard()}
                                    className="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm transition-colors hover:bg-primary/90"
                                >
                                    <Activity className="h-4 w-4" />
                                    Dashboard
                                </Link>
                            ) : (
                                <Link
                                    href={login()}
                                    className="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm transition-colors hover:bg-primary/90"
                                >
                                    Sign In
                                </Link>
                            )}
                        </nav>
                    </div>
                </header>

                {/* Main Content */}
                <main className="flex-1">
                    {/* Hero Section */}
                    <section className="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-24 lg:px-8">
                        <div className="text-center">
                            <div className="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-2xl bg-primary p-4 shadow-xl shadow-primary/25">
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
                            <h1 className="text-4xl font-bold tracking-tight text-foreground sm:text-5xl">
                                {hospitalName}
                            </h1>
                            <p className="mx-auto mt-4 max-w-2xl text-lg text-muted-foreground">
                                Comprehensive hospital management solution for
                                streamlined operations, better patient care, and
                                efficient administration.
                            </p>
                            {!auth.user && (
                                <div className="mt-8">
                                    <Link
                                        href={login()}
                                        className="inline-flex items-center gap-2 rounded-lg bg-primary px-6 py-3 text-base font-medium text-primary-foreground shadow-lg shadow-primary/25 transition-all hover:bg-primary/90 hover:shadow-xl"
                                    >
                                        Get Started
                                    </Link>
                                </div>
                            )}
                        </div>
                    </section>

                    {/* Features Grid */}
                    <section className="mx-auto max-w-7xl px-4 pb-16 sm:px-6 lg:px-8">
                        <h2 className="mb-8 text-center text-2xl font-semibold text-foreground">
                            Core Modules
                        </h2>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            {features.map((feature) => (
                                <div
                                    key={feature.title}
                                    className="group rounded-xl border border-border/60 bg-card/80 p-5 shadow-sm backdrop-blur-sm transition-all hover:border-primary/30 hover:shadow-md"
                                >
                                    <div className="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary transition-colors group-hover:bg-primary group-hover:text-primary-foreground">
                                        <feature.icon className="h-5 w-5" />
                                    </div>
                                    <h3 className="mb-1 font-semibold text-foreground">
                                        {feature.title}
                                    </h3>
                                    <p className="text-sm text-muted-foreground">
                                        {feature.description}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </section>
                </main>

                {/* Footer */}
                <footer className="border-t border-border/40 bg-background/80 backdrop-blur-sm">
                    <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                        <div className="flex flex-col items-center gap-4 sm:flex-row sm:justify-between">
                            <p className="text-sm text-muted-foreground">
                                © {new Date().getFullYear()} {hospitalName}. All
                                rights reserved.
                            </p>
                            <div className="text-center text-sm text-muted-foreground sm:text-right">
                                <p>
                                    Developed by{' '}
                                    <a
                                        href="mailto:developer@example.com"
                                        className="font-medium text-primary hover:underline"
                                    >
                                        Your Developer Name
                                    </a>
                                </p>
                                <p className="mt-1">
                                    Contact:{' '}
                                    <a
                                        href="tel:+1234567890"
                                        className="text-primary hover:underline"
                                    >
                                        +123 456 7890
                                    </a>
                                </p>
                            </div>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
