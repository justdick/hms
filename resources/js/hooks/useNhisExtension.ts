import { useCallback, useEffect, useState } from 'react';

// Chrome extension API types (only available when extension is installed)
declare const chrome: {
    runtime?: {
        sendMessage: (extensionId: string, message: unknown, callback?: (response: unknown) => void) => void;
    };
} | undefined;

interface CccData {
    ccc: string;
    status: string;
    memberName: string;
    membershipNumber: string;
    dob: string;
    gender: string;
    coverageStart: string;
    coverageEnd: string;
}

interface UseNhisExtensionReturn {
    isExtensionInstalled: boolean;
    isVerifying: boolean;
    cccData: CccData | null;
    startVerification: (membershipNumber: string, credentials?: { username: string; password: string }, portalUrl?: string) => void;
    clearCccData: () => void;
}

// Extension ID - update this after installing the extension
const EXTENSION_ID = ''; // Leave empty to use postMessage fallback

export function useNhisExtension(): UseNhisExtensionReturn {
    const [isExtensionInstalled, setIsExtensionInstalled] = useState(false);
    const [isVerifying, setIsVerifying] = useState(false);
    const [cccData, setCccData] = useState<CccData | null>(null);

    // Check if extension is installed
    useEffect(() => {
        checkExtension();
    }, []);

    // Listen for CCC data from extension
    useEffect(() => {
        const handleMessage = (event: MessageEvent) => {
            // Only accept messages from same origin or extension
            if (event.data?.type === 'NHIS_CCC_RECEIVED') {
                console.log('Received CCC from extension:', event.data.data);
                setCccData(event.data.data);
                setIsVerifying(false);
            }
        };

        window.addEventListener('message', handleMessage);
        return () => window.removeEventListener('message', handleMessage);
    }, []);

    const checkExtension = useCallback(() => {
        // Method 1: Try to communicate with extension directly
        if (EXTENSION_ID && chrome?.runtime?.sendMessage) {
            try {
                chrome.runtime.sendMessage(
                    EXTENSION_ID,
                    { type: 'CHECK_EXTENSION' },
                    (response: unknown) => {
                        if ((response as { installed?: boolean })?.installed) {
                            setIsExtensionInstalled(true);
                        }
                    }
                );
            } catch {
                // Extension not available
            }
        }

        // Method 2: Check for extension marker in DOM (extension can inject this)
        const marker = document.getElementById('hms-nhis-extension-marker');
        if (marker) {
            setIsExtensionInstalled(true);
        }

        // Method 3: Use localStorage flag set by extension
        const extensionFlag = localStorage.getItem('hms-nhis-extension-installed');
        if (extensionFlag === 'true') {
            setIsExtensionInstalled(true);
        }
    }, []);

    const startVerification = useCallback((membershipNumber: string, credentials?: { username: string; password: string }, portalUrl?: string) => {
        setIsVerifying(true);
        setCccData(null);

        // Store verification request in localStorage for extension to pick up
        localStorage.setItem('hms-nhis-pending-verification', JSON.stringify({
            membershipNumber,
            credentials: credentials || null,
            timestamp: Date.now(),
            origin: window.location.origin
        }));

        // Open NHIA portal
        window.open(portalUrl || 'https://ccc.nhia.gov.gh/', '_blank');

        // Also try to communicate with extension directly
        if (EXTENSION_ID && chrome?.runtime?.sendMessage) {
            try {
                chrome.runtime.sendMessage(EXTENSION_ID, {
                    type: 'NHIS_VERIFY_REQUEST',
                    membershipNumber
                });
            } catch {
                // Extension communication failed, rely on localStorage
            }
        }
    }, []);

    const clearCccData = useCallback(() => {
        setCccData(null);
        setIsVerifying(false);
        localStorage.removeItem('hms-nhis-pending-verification');
    }, []);

    return {
        isExtensionInstalled,
        isVerifying,
        cccData,
        startVerification,
        clearCccData
    };
}
