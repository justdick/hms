import { router } from '@inertiajs/react';
import { useCallback, useState } from 'react';

export interface VitalsScheduleData {
    interval_minutes: number;
    [key: string]: number | string | boolean | null | undefined;
}

interface UseVitalsScheduleOptions {
    wardId: number;
    admissionId: number;
    scheduleId?: number;
}

interface UseVitalsScheduleReturn {
    creating: boolean;
    updating: boolean;
    deleting: boolean;
    createSchedule: (data: VitalsScheduleData) => Promise<void>;
    updateSchedule: (data: VitalsScheduleData) => Promise<void>;
    deleteSchedule: () => Promise<void>;
}

/**
 * Hook for managing vitals schedules
 */
export const useVitalsSchedule = (
    options: UseVitalsScheduleOptions,
): UseVitalsScheduleReturn => {
    const { wardId, admissionId, scheduleId } = options;

    const [creating, setCreating] = useState<boolean>(false);
    const [updating, setUpdating] = useState<boolean>(false);
    const [deleting, setDeleting] = useState<boolean>(false);

    /**
     * Create a new vitals schedule
     */
    const createSchedule = useCallback(
        async (data: VitalsScheduleData): Promise<void> => {
            return new Promise((resolve, reject) => {
                setCreating(true);

                router.post(
                    `/wards/${wardId}/patients/${admissionId}/vitals-schedule`,
                    data,
                    {
                        preserveScroll: true,
                        onSuccess: () => {
                            setCreating(false);
                            resolve();
                        },
                        onError: (errors) => {
                            setCreating(false);
                            reject(new Error(Object.values(errors).join(', ')));
                        },
                        onFinish: () => {
                            setCreating(false);
                        },
                    },
                );
            });
        },
        [wardId, admissionId],
    );

    /**
     * Update an existing vitals schedule
     */
    const updateSchedule = useCallback(
        async (data: VitalsScheduleData): Promise<void> => {
            if (!scheduleId) {
                throw new Error('Schedule ID is required for updates');
            }

            return new Promise((resolve, reject) => {
                setUpdating(true);

                router.put(
                    `/wards/${wardId}/patients/${admissionId}/vitals-schedule/${scheduleId}`,
                    data,
                    {
                        preserveScroll: true,
                        onSuccess: () => {
                            setUpdating(false);
                            resolve();
                        },
                        onError: (errors) => {
                            setUpdating(false);
                            reject(new Error(Object.values(errors).join(', ')));
                        },
                        onFinish: () => {
                            setUpdating(false);
                        },
                    },
                );
            });
        },
        [wardId, admissionId, scheduleId],
    );

    /**
     * Delete (disable) a vitals schedule
     */
    const deleteSchedule = useCallback(async (): Promise<void> => {
        if (!scheduleId) {
            throw new Error('Schedule ID is required for deletion');
        }

        return new Promise((resolve, reject) => {
            setDeleting(true);

            router.delete(
                `/wards/${wardId}/patients/${admissionId}/vitals-schedule/${scheduleId}`,
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setDeleting(false);
                        resolve();
                    },
                    onError: (errors) => {
                        setDeleting(false);
                        reject(new Error(Object.values(errors).join(', ')));
                    },
                    onFinish: () => {
                        setDeleting(false);
                    },
                },
            );
        });
    }, [wardId, admissionId, scheduleId]);

    return {
        creating,
        updating,
        deleting,
        createSchedule,
        updateSchedule,
        deleteSchedule,
    };
};
