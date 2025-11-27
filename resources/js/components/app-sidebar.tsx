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
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    Bandage,
    BarChart3,
    ClipboardList,
    CreditCard,
    FileText,
    FlaskConical,
    FolderSync,
    Hospital,
    LayoutGrid,
    Link2,
    Package,
    Pill,
    Shield,
    Stethoscope,
    TableProperties,
    Users,
} from 'lucide-react';
import AppLogo from './app-logo';

const footerNavItems: NavItem[] = [];

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;

    // Build pharmacy sub-items based on permissions
    const pharmacyItems: NavItem[] = [];

    if (auth.permissions?.pharmacy?.inventory) {
        pharmacyItems.push({
            title: 'Inventory',
            href: '/pharmacy/inventory',
            icon: Package,
        });
    }

    if (auth.permissions?.pharmacy?.dispensing) {
        pharmacyItems.push({
            title: 'Dispensing',
            href: '/pharmacy/dispensing',
            icon: ClipboardList,
        });
    }

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
            title: 'Patients',
            href: '/patients',
            icon: Users,
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
            title: 'Minor Procedures',
            href: '/minor-procedures',
            icon: Bandage,
        },
        {
            title: 'Pharmacy',
            href: '/pharmacy',
            icon: Pill,
            items: pharmacyItems.length > 0 ? pharmacyItems : undefined,
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
                    title: 'Batches',
                    href: '/admin/insurance/batches',
                    icon: FolderSync,
                },
                {
                    title: 'Analytics',
                    href: '/admin/insurance/reports',
                    icon: BarChart3,
                },
                {
                    title: 'NHIS Tariffs',
                    href: '/admin/nhis-tariffs',
                    icon: TableProperties,
                },
                {
                    title: 'NHIS Mappings',
                    href: '/admin/nhis-mappings',
                    icon: Link2,
                },
                {
                    title: 'G-DRG Tariffs',
                    href: '/admin/gdrg-tariffs',
                    icon: TableProperties,
                },
            ],
        },
    ];
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
