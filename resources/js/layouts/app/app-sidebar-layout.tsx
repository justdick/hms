import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { showVitalsAlertToast } from '@/components/Ward/VitalsAlertToast';
import { useVitalsAlerts } from '@/hooks/use-vitals-alerts';
import { type BreadcrumbItem } from '@/types';
import { type PropsWithChildren, useEffect, useRef } from 'react';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: PropsWithChildren<{ breadcrumbs?: BreadcrumbItem[] }>) {
    // Global vitals alerts monitoring - shows alerts from all wards
    const { alerts, dismissAlert } = useVitalsAlerts({
        pollingInterval: 30000,
        enabled: true,
    });

    // Track shown alerts to prevent duplicate toasts
    const shownAlertIds = useRef<Set<number>>(new Set());
    const lastOverdueAlertTime = useRef<Map<number, number>>(new Map());

    // Show toast notifications for new alerts
    useEffect(() => {
        const now = Date.now();

        alerts.forEach((alert) => {
            const isOverdue = alert.status === 'overdue';
            const isDue = alert.status === 'due';

            // Only show toast for due or overdue alerts
            if (!isDue && !isOverdue) {
                return;
            }

            // For overdue alerts, implement repeat notifications every 15 minutes
            if (isOverdue) {
                const lastShown = lastOverdueAlertTime.current.get(alert.id);
                const fifteenMinutes = 15 * 60 * 1000;

                // Show if never shown or if 15 minutes have passed since last shown
                if (!lastShown || now - lastShown >= fifteenMinutes) {
                    lastOverdueAlertTime.current.set(alert.id, now);
                    showVitalsAlertToast(alert, dismissAlert);
                }
            } else if (isDue && !shownAlertIds.current.has(alert.id)) {
                // For due alerts, show only once
                shownAlertIds.current.add(alert.id);
                showVitalsAlertToast(alert, dismissAlert);
            }
        });

        // Clean up shown alerts that are no longer in the list
        const currentAlertIds = new Set(alerts.map((a) => a.id));
        shownAlertIds.current.forEach((id) => {
            if (!currentAlertIds.has(id)) {
                shownAlertIds.current.delete(id);
                lastOverdueAlertTime.current.delete(id);
            }
        });
    }, [alerts, dismissAlert]);

    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="overflow-x-hidden">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>
        </AppShell>
    );
}
