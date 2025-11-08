import { useEffect, useState } from 'react';

/**
 * Hook to calculate and update time remaining until a target date
 * Updates every minute
 */
export const useTimeRemaining = (targetDate: string | Date | null): number | null => {
    const [minutesRemaining, setMinutesRemaining] = useState<number | null>(null);

    useEffect(() => {
        if (!targetDate) {
            setMinutesRemaining(null);
            return;
        }

        const calculateMinutes = () => {
            const target = new Date(targetDate);
            const now = new Date();
            const diffMs = target.getTime() - now.getTime();
            const diffMinutes = Math.floor(diffMs / (1000 * 60));
            setMinutesRemaining(diffMinutes);
        };

        // Calculate immediately
        calculateMinutes();

        // Update every minute
        const interval = setInterval(calculateMinutes, 60000);

        return () => clearInterval(interval);
    }, [targetDate]);

    return minutesRemaining;
};

/**
 * Hook to calculate and update time elapsed since a target date
 * Updates every minute
 */
export const useTimeElapsed = (targetDate: string | Date | null): number | null => {
    const [minutesElapsed, setMinutesElapsed] = useState<number | null>(null);

    useEffect(() => {
        if (!targetDate) {
            setMinutesElapsed(null);
            return;
        }

        const calculateMinutes = () => {
            const target = new Date(targetDate);
            const now = new Date();
            const diffMs = now.getTime() - target.getTime();
            const diffMinutes = Math.floor(diffMs / (1000 * 60));
            setMinutesElapsed(diffMinutes);
        };

        // Calculate immediately
        calculateMinutes();

        // Update every minute
        const interval = setInterval(calculateMinutes, 60000);

        return () => clearInterval(interval);
    }, [targetDate]);

    return minutesElapsed;
};
