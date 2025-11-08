import { playAudio, preloadVitalsAlertSounds } from '@/lib/audio';
import { useCallback, useEffect, useState } from 'react';

export type SoundType = 'gentle' | 'urgent';

export interface SoundAlertSettings {
    enabled: boolean;
    volume: number;
    soundType: SoundType;
}

const STORAGE_KEY = 'vitals-sound-alert-settings';

const DEFAULT_SETTINGS: SoundAlertSettings = {
    enabled: true,
    volume: 0.7,
    soundType: 'gentle',
};

/**
 * Load settings from localStorage
 */
const loadSettings = (): SoundAlertSettings => {
    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored) {
            const parsed = JSON.parse(stored);
            return { ...DEFAULT_SETTINGS, ...parsed };
        }
    } catch (error) {
        console.warn('Failed to load sound alert settings', error);
    }
    return DEFAULT_SETTINGS;
};

/**
 * Save settings to localStorage
 */
const saveSettings = (settings: SoundAlertSettings): void => {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(settings));
    } catch (error) {
        console.warn('Failed to save sound alert settings', error);
    }
};

/**
 * Hook for managing vitals alert sounds
 */
export const useSoundAlert = () => {
    const [settings, setSettings] = useState<SoundAlertSettings>(loadSettings);

    // Preload sounds on mount
    useEffect(() => {
        preloadVitalsAlertSounds().catch((error) => {
            console.warn('Failed to preload alert sounds', error);
        });
    }, []);

    /**
     * Play an alert sound
     */
    const playAlert = useCallback(
        async (type: SoundType = 'gentle'): Promise<void> => {
            if (!settings.enabled) {
                return;
            }

            try {
                const soundUrl = `/sounds/vitals-alert-${type}.mp3`;
                await playAudio(soundUrl, settings.volume);
            } catch (error) {
                // Handle audio playback errors gracefully
                if (error instanceof Error) {
                    if (error.name === 'NotAllowedError') {
                        console.warn(
                            'Audio playback blocked by browser autoplay policy. User interaction may be required.',
                        );
                    } else {
                        console.error('Failed to play alert sound', error);
                    }
                }
            }
        },
        [settings.enabled, settings.volume],
    );

    /**
     * Update settings and persist to localStorage
     */
    const updateSettings = useCallback(
        (newSettings: Partial<SoundAlertSettings>): void => {
            setSettings((prev) => {
                const updated = { ...prev, ...newSettings };
                saveSettings(updated);
                return updated;
            });
        },
        [],
    );

    /**
     * Reset settings to defaults
     */
    const resetSettings = useCallback((): void => {
        setSettings(DEFAULT_SETTINGS);
        saveSettings(DEFAULT_SETTINGS);
    }, []);

    return {
        settings,
        playAlert,
        updateSettings,
        resetSettings,
    };
};
