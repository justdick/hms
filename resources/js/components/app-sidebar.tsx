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
    DollarSign,
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
    ScanLine,
    Settings,
    Shield,
    Stethoscope,
    TableProperties,
    TestTubes,
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

    // Patient Accounts - requires collect permission (unified prepaid + credit)
    if (billingPermissions?.collect || billingPermissions?.viewAll) {
        items.push({
            title: 'Patient Accounts',
            href: '/billing/patient-accounts',
            icon: Wallet,
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

    // Pricing Dashboard - requires configure permission (centralized pricing management)
    if (billingPermissions?.configure) {
        items.push({
            title: 'Pricing Dashboard',
            href: '/admin/pricing-dashboard',
            icon: DollarSign,
        });
    }

    return items.length > 0 ? items : undefined;
}

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;

    // Handle session expiry - redirect to login if user is null
    if (!auth.user) {
        window.location.href = '/login';
        return null;
    }

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

    // Build investigations sub-items based on permissions
    const investigationsItems: NavItem[] = [];

    // Laboratory - visible if user has lab permissions (lab-orders.view-all or lab-orders.view-dept)
    if (auth.permissions?.investigations?.viewLab) {
        investigationsItems.push({
            title: 'Laboratory',
            href: '/lab',
            icon: FlaskConical,
        });
    }

    // Radiology - visible if user has radiology.view-worklist permission
    if (auth.permissions?.investigations?.viewRadiology) {
        investigationsItems.push({
            title: 'Radiology',
            href: '/radiology',
            icon: ScanLine,
        });
    }

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
    ];

    // Check-in - requires checkins.view permission
    if (auth.permissions?.checkins?.view) {
        mainNavItems.push({
            title: 'Check-in',
            href: '/checkin',
            icon: Stethoscope,
        });
    }

    // Patients - requires patients.view permission
    if (auth.permissions?.patients?.view) {
        mainNavItems.push({
            title: 'Patients',
            href: '/patients',
            icon: Users,
        });
    }

    // Consultation - requires consultations.view permission
    if (auth.permissions?.consultations?.view) {
        mainNavItems.push({
            title: 'Consultation',
            href: '/consultation',
            icon: Stethoscope,
        });
    }

    // Wards - requires wards.view permission
    if (auth.permissions?.wards?.view) {
        mainNavItems.push({
            title: 'Wards',
            href: '/wards',
            icon: Hospital,
        });
    }

    // Investigations - requires lab or radiology permissions
    if (investigationsItems.length > 0) {
        mainNavItems.push({
            title: 'Investigations',
            href: '/lab',
            icon: TestTubes,
            items: investigationsItems,
        });
    }

    // Minor Procedures - requires minor-procedures.view permission
    if (auth.permissions?.minorProcedures?.view) {
        mainNavItems.push({
            title: 'Minor Procedures',
            href: '/minor-procedures',
            icon: Bandage,
        });
    }

    // Pharmacy - requires pharmacy permissions
    if (pharmacyItems.length > 0) {
        mainNavItems.push({
            title: 'Pharmacy',
            href: '/pharmacy',
            icon: Pill,
            items: pharmacyItems,
        });
    }

    // Billing - requires billing permissions
    const billingItems = buildBillingNavItems(auth.permissions?.billing);
    if (billingItems && billingItems.length > 0) {
        mainNavItems.push({
            title: 'Billing',
            href: '/billing',
            icon: CreditCard,
            items: billingItems,
        });
    }

    // Insurance - requires insurance.view permission
    if (auth.permissions?.insurance?.view) {
        mainNavItems.push({
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
        });
    }

    // Departments - requires departments.view permission
    if (auth.permissions?.departments?.view) {
        mainNavItems.push({
            title: 'Departments',
            href: '/departments',
            icon: Building2,
        });
    }

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

    if (
        auth.permissions?.nhisSettings?.view ||
        auth.permissions?.nhisSettings?.manage
    ) {
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
