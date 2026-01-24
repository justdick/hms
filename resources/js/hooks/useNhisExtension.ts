import { useCallback, useEffect, useState } from 'react';

// Chrome extension API types (only available when extension is installed)
declare const chrome:
    | {
          runtime?: {
              sendMessage: (
                  extensionId: string,
                  message: unknown,
                  callback?: (response: unknown) => void,
              ) => void;
          };
      }
    | undefined;

interface CccData {
    ccc: string | null;
    status: string;
    memberName: string;
    membershipNumber: string;
    dob: string;
    gender: string;
    coverageStart: string;
    coverageEnd: string;
    error?: string | null;
    errorType?: 'INACTIVE' | 'GHANACARD_NOT_LINKED' | 'NOT_FOUND' | 'UNKNOWN' | null;
}

export type NhisIdType = 'nhis' | 'ghanacard';

interface UseNhisExtensionReturn {
    isExtensionInstalled: boolean;
    isVerifying: boolean;
    cccData: CccData | null;
    startVerification: (
        membershipNumber: string,
        credentials?: { username: string; password: string },
        portalUrl?: string,
        idType?: NhisIdType,
    ) => void;
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
            console.log('useNhisExtension: Received window message', event.data?.type);
            // Only accept messages from same origin or extension
            if (event.data?.type === 'NHIS_CCC_RECEIVED') {
                console.log('useNhisExtension: Received CCC from extension:', event.data.data);
                setCccData(event.data.data);
                setIsVerifying(false);
            }
        };

        console.log('useNhisExtension: Adding message listener');
        window.addEventListener('message', handleMessage);
        return () => {
            console.log('useNhisExtension: Removing message listener');
            window.removeEventListener('message', handleMessage);
        };
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
                    },
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
        const extensionFlag = localStorage.getItem(
            'hms-nhis-extension-installed',
        );
        if (extensionFlag === 'true') {
            setIsExtensionInstalled(true);
        }
    }, []);

    const startVerification = useCallback(
        (
            membershipNumber: string,
            credentials?: { username: string; password: string },
            portalUrl?: string,
            idType: NhisIdType = 'nhis',
        ) => {
            setIsVerifying(true);
            setCccData(null);

            // Send verification request to extension via postMessage
            // The extension's hms-content.js listens for this and stores in chrome.storage
            window.postMessage(
                {
                    type: 'HMS_NHIS_VERIFY_REQUEST',
                    membershipNumber,
                    credentials: credentials || null,
                    idType, // 'nhis' or 'ghanacard'
                },
                '*',
            );

            // Give extension time to store the data, then open portal
            setTimeout(() => {
                window.open(portalUrl || 'https://ccc.nhia.gov.gh/', '_blank');
            }, 300);

            // Also try to communicate with extension directly
            if (EXTENSION_ID && chrome?.runtime?.sendMessage) {
                try {
                    chrome.runtime.sendMessage(EXTENSION_ID, {
                        type: 'NHIS_VERIFY_REQUEST',
                        membershipNumber,
                        credentials,
                        idType,
                    });
                } catch {
                    // Extension communication failed, rely on postMessage
                }
            }
        },
        [],
    );

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
        clearCccData,
    };
}
