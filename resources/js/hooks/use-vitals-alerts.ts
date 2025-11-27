import { useCallback, useEffect, useRef, useState } from 'react';

export type VitalsAlertStatus =
    | 'pending'
    | 'due'
    | 'overdue'
    | 'completed'
    | 'dismissed';

export interface VitalsAlert {
    id: number;
    patient_admission_id: number;
    ward_id: number;
    patient_name: string;
    bed_number: string;
    ward_name: string;
    due_at: string;
    status: VitalsAlertStatus;
    time_overdue_minutes: number | null;
}

interface UseVitalsAlertsOptions {
    wardId?: number;
    pollingInterval?: number;
    enabled?: boolean;
}

interface UseVitalsAlertsReturn {
    alerts: VitalsAlert[];
    loading: boolean;
    error: string | null;
    acknowledgeAlert: (alertId: number) => Promise<void>;
    dismissAlert: (alertId: number) => Promise<void>;
    refetch: () => Promise<void>;
}

const POLLING_INTERVAL = 30000; // 30 seconds
const MAX_RETRY_DELAY = 120000; // 2 minutes

/**
 * Hook for managing vitals alerts with polling
 */
export const useVitalsAlerts = (
    options: UseVitalsAlertsOptions = {},
): UseVitalsAlertsReturn => {
    const {
        wardId,
        pollingInterval = POLLING_INTERVAL,
        enabled = true,
    } = options;

    const [alerts, setAlerts] = useState<VitalsAlert[]>([]);
    const [loading, setLoading] = useState<boolean>(true);
    const [error, setError] = useState<string | null>(null);

    const retryCount = useRef<number>(0);
    const pollingTimeoutRef = useRef<NodeJS.Timeout | null>(null);

    /**
     * Fetch active alerts from API
     */
    const fetchAlerts = useCallback(async (): Promise<void> => {
        try {
            const url = new URL(
                '/api/vitals-alerts/active',
                window.location.origin,
            );

            if (wardId) {
                url.searchParams.append('ward_id', wardId.toString());
            }

            const response = await fetch(url.toString(), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`Failed to fetch alerts: ${response.status}`);
            }

            const data = await response.json();
            setAlerts(data.alerts || []);
            setError(null);
            retryCount.current = 0;
        } catch (err) {
            const errorMessage =
                err instanceof Error ? err.message : 'Failed to fetch alerts';
            console.error('Error fetching vitals alerts:', err);

            retryCount.current += 1;

            if (retryCount.current >= 3) {
                setError(errorMessage);
            }
        } finally {
            setLoading(false);
        }
    }, [wardId]);

    /**
     * Acknowledge an alert
     */
    const acknowledgeAlert = useCallback(
        async (alertId: number): Promise<void> => {
            try {
                const response = await fetch(
                    `/api/vitals-alerts/${alertId}/acknowledge`,
                    {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    },
                );

                if (!response.ok) {
                    throw new Error(
                        `Failed to acknowledge alert: ${response.status}`,
                    );
                }

                // Refetch alerts to update UI
                await fetchAlerts();
            } catch (err) {
                console.error('Error acknowledging alert:', err);
                throw err;
            }
        },
        [fetchAlerts],
    );

    /**
     * Dismiss an alert
     */
    const dismissAlert = useCallback(async (alertId: number): Promise<void> => {
        try {
            const response = await fetch(
                `/api/vitals-alerts/${alertId}/dismiss`,
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                },
            );

            if (!response.ok) {
                throw new Error(`Failed to dismiss alert: ${response.status}`);
            }

            // Remove alert from local state immediately for better UX
            setAlerts((prev) => prev.filter((alert) => alert.id !== alertId));
        } catch (err) {
            console.error('Error dismissing alert:', err);
            throw err;
        }
    }, []);

    /**
     * Set up polling
     */
    useEffect(() => {
        if (!enabled) {
            return;
        }

        // Initial fetch
        fetchAlerts();

        // Set up polling with exponential backoff on errors
        const startPolling = () => {
            if (pollingTimeoutRef.current) {
                clearTimeout(pollingTimeoutRef.current);
            }

            const delay =
                retryCount.current > 0
                    ? Math.min(
                          pollingInterval * Math.pow(2, retryCount.current - 1),
                          MAX_RETRY_DELAY,
                      )
                    : pollingInterval;

            pollingTimeoutRef.current = setTimeout(() => {
                fetchAlerts().then(startPolling);
            }, delay);
        };

        startPolling();

        // Cleanup
        return () => {
            if (pollingTimeoutRef.current) {
                clearTimeout(pollingTimeoutRef.current);
            }
        };
    }, [enabled, fetchAlerts, pollingInterval]);

    return {
        alerts,
        loading,
        error,
        acknowledgeAlert,
        dismissAlert,
        refetch: fetchAlerts,
    };
};
