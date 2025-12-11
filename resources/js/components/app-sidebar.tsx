import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
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
    Building2,
    ClipboardList,
    CreditCard,
    Database,
    FileText,
    FlaskConical,
    FolderSync,
    History,
    Hospital,
    LayoutGrid,
    Link2,
    Package,
    Palette,
    Pill,
    Settings,
    Shield,
    Stethoscope,
    TableProperties,
    UserCheck,
    Users,
    Wallet,
} from 'lucide-react';
import AppLogo from './app-logo';

const footerNavItems: NavItem[] = [];

// Build billing navigation items based on permissions
function buildBillingNavItems(billingPermissions?: {
    viewAll?: boolean;
    collect?: boolean;
    override?: boolean;
    reconcile?: boolean;
    reports?: boolean;
    statements?: boolean;
    manageCredit?: boolean;
    void?: boolean;
    refund?: boolean;
    configure?: boolean;
}): NavItem[] | undefined {
    const items: NavItem[] = [];

    // Payments - available to users with collect permission
    if (billingPermissions?.collect || billingPermissions?.viewAll) {
        items.push({
            title: 'Payments',
            href: '/billing',
            icon: Wallet,
        });
    }

    // Accounts Dashboard - requires view-all permission
    if (billingPermissions?.viewAll) {
        items.push({
            title: 'Dashboard',
            href: '/billing/accounts',
            icon: BarChart3,
        });
    }

    // Reconciliation - requires reconcile permission
    if (billingPermissions?.reconcile) {
        items.push({
            title: 'Reconciliation',
            href: '/billing/accounts/reconciliation',
            icon: ClipboardList,
        });
    }

    // Payment History - requires view-all permission
    if (billingPermissions?.viewAll) {
        items.push({
            title: 'History',
            href: '/billing/accounts/history',
            icon: History,
        });
    }

    // Reports - requires reports permission
    if (billingPermissions?.reports) {
        items.push({
            title: 'Outstanding',
            href: '/billing/accounts/reports/outstanding',
            icon: FileText,
        });
        items.push({
            title: 'Revenue',
            href: '/billing/accounts/reports/revenue',
            icon: BarChart3,
        });
    }

    // Credit Patients - requires manage-credit permission
    if (billingPermissions?.manageCredit) {
        items.push({
            title: 'Credit Patients',
            href: '/billing/accounts/credit-patients',
            icon: UserCheck,
        });
    }

    // Configuration - requires configure permission
    if (billingPermissions?.configure) {
        items.push({
            title: 'Configuration',
            href: '/billing/configuration',
            icon: Settings,
        });
    }

    return items.length > 0 ? items : undefined;
}

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
            items: buildBillingNavItems(auth.permissions?.billing),
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
        {
            title: 'Departments',
            href: '/departments',
            icon: Building2,
        },
    ];

    // Add Backups menu item if user has backup permissions
    if (auth.permissions?.backups?.view) {
        mainNavItems.push({
            title: 'Backups',
            href: '/admin/backups',
            icon: Database,
        });
    }

    // Build admin sub-items based on permissions
    const adminItems: NavItem[] = [];

    if (auth.permissions?.users?.viewAll) {
        adminItems.push({
            title: 'Users',
            href: '/admin/users',
            icon: Users,
        });
    }

    if (auth.permissions?.roles?.viewAll) {
        adminItems.push({
            title: 'Roles',
            href: '/admin/roles',
            icon: Shield,
        });
    }

    if (auth.permissions?.theme?.view || auth.permissions?.theme?.manage) {
        adminItems.push({
            title: 'Theme Settings',
            href: '/admin/theme-settings',
            icon: Palette,
        });
    }

    if (auth.permissions?.nhisSettings?.view || auth.permissions?.nhisSettings?.manage) {
        adminItems.push({
            title: 'NHIS Settings',
            href: '/admin/nhis-settings',
            icon: Shield,
        });
    }

    // Add Administration menu if user has any admin permissions
    if (adminItems.length > 0) {
        mainNavItems.push({
            title: 'Administration',
            href: '/admin/users',
            icon: Settings,
            items: adminItems,
        });
    }
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
            </SidebarFooter>
        </Sidebar>
    );
}
