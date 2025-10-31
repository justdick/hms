import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import {
    BarChart3,
    BookOpen,
    CreditCard,
    FileBarChart,
    FileText,
    FlaskConical,
    Folder,
    Hospital,
    LayoutGrid,
    Pill,
    Shield,
    Stethoscope,
    TrendingUp,
} from 'lucide-react';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Check-in',
        href: '/checkin',
        icon: Stethoscope,
    },
    {
        title: 'Consultation',
        href: '/consultation',
        icon: Stethoscope,
    },
    {
        title: 'Wards',
        href: '/wards',
        icon: Hospital,
    },
    {
        title: 'Laboratory',
        href: '/lab',
        icon: FlaskConical,
    },
    {
        title: 'Pharmacy',
        href: '/pharmacy',
        icon: Pill,
    },
    {
        title: 'Billing',
        href: '/billing',
        icon: CreditCard,
    },
    {
        title: 'Insurance',
        href: '/admin/insurance',
        icon: Shield,
        items: [
            {
                title: 'Providers',
                href: '/admin/insurance/providers',
                icon: Hospital,
            },
            {
                title: 'Plans',
                href: '/admin/insurance/plans',
                icon: FileText,
            },
            {
                title: 'Claims',
                href: '/admin/insurance/claims',
                icon: CreditCard,
            },
            {
                title: 'Coverage Rules',
                href: '/admin/insurance/coverage-rules',
                icon: FileBarChart,
            },
            {
                title: 'Tariffs',
                href: '/admin/insurance/tariffs',
                icon: TrendingUp,
            },
            {
                title: 'Reports',
                href: '/admin/insurance/reports',
                icon: BarChart3,
            },
        ],
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
