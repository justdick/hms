/**
 * Audio utility for playing alert sounds with volume control
 */

interface AudioCache {
    [key: string]: HTMLAudioElement;
}

const audioCache: AudioCache = {};

/**
 * Preload an audio file into memory
 */
export const preloadAudio = (url: string): Promise<void> => {
    return new Promise((resolve, reject) => {
        if (audioCache[url]) {
            resolve();
            return;
        }

        const audio = new Audio(url);
        audio.preload = 'auto';

        audio.addEventListener('canplaythrough', () => {
            audioCache[url] = audio;
            resolve();
        });

        audio.addEventListener('error', (error) => {
            console.warn(`Failed to preload audio: ${url}`, error);
            reject(error);
        });

        audio.load();
    });
};

/**
 * Play an audio file with specified volume
 */
export const playAudio = async (
    url: string,
    volume: number = 0.7,
): Promise<void> => {
    try {
        let audio = audioCache[url];

        if (!audio) {
            audio = new Audio(url);
            audioCache[url] = audio;
        }

        // Clone the audio element to allow multiple simultaneous plays
        const audioClone = audio.cloneNode() as HTMLAudioElement;
        audioClone.volume = Math.max(0, Math.min(1, volume));

        await audioClone.play();
    } catch (error) {
        // Handle autoplay policy errors gracefully
        if (error instanceof Error && error.name === 'NotAllowedError') {
            console.warn(
                'Audio playback blocked by browser autoplay policy',
                error,
            );
        } else {
            console.error('Failed to play audio', error);
        }
    }
};

/**
 * Preload all vitals alert sounds
 */
export const preloadVitalsAlertSounds = async (): Promise<void> => {
    try {
        await Promise.all([
            preloadAudio('/sounds/vitals-alert-gentle.mp3'),
            preloadAudio('/sounds/vitals-alert-urgent.mp3'),
        ]);
    } catch (error) {
        console.warn('Failed to preload some alert sounds', error);
    }
};
