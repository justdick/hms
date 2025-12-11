// Content script injected into HMS pages to enable extension communication

(function () {
    'use strict';

    // Mark that extension is installed
    try {
        localStorage.setItem('hms-nhis-extension-installed', 'true');
    } catch (e) {
        console.log('HMS NHIS Extension: Could not set localStorage');
    }

    // Create a marker element for detection
    const marker = document.createElement('div');
    marker.id = 'hms-nhis-extension-marker';
    marker.style.display = 'none';
    try {
        marker.dataset.version = chrome.runtime.getManifest().version;
    } catch (e) {
        marker.dataset.version = 'unknown';
    }
    document.body.appendChild(marker);

    // Listen for verification requests from HMS page
    window.addEventListener('message', (event) => {
        // Only accept messages from same origin
        if (event.origin !== window.location.origin) return;

        if (event.data?.type === 'HMS_NHIS_VERIFY_REQUEST') {
            console.log('HMS NHIS Extension: Received verify request', event.data);
            
            // Store verification request in extension storage (accessible from all tabs)
            try {
                chrome.storage.local.set({
                    pendingVerification: {
                        membershipNumber: event.data.membershipNumber,
                        credentials: event.data.credentials,
                        timestamp: Date.now(),
                        hmsOrigin: window.location.origin
                    }
                }, () => {
                    if (chrome.runtime.lastError) {
                        console.log('HMS NHIS Extension: Storage error', chrome.runtime.lastError);
                        return;
                    }
                    console.log('HMS NHIS Extension: Stored pending verification');
                    // Acknowledge receipt
                    window.postMessage({ type: 'HMS_NHIS_VERIFY_ACK' }, '*');
                });
            } catch (e) {
                console.log('HMS NHIS Extension: Extension context invalidated, please refresh the page');
            }
        }
    });

    // Listen for CCC data from background script
    try {
        chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
            if (message.type === 'NHIS_CCC_RECEIVED') {
                // Forward to HMS page via postMessage
                window.postMessage(
                    {
                        type: 'NHIS_CCC_RECEIVED',
                        data: message.data,
                    },
                    '*',
                );
                sendResponse({ received: true });
            }
            return true;
        });
    } catch (e) {
        console.log('HMS NHIS Extension: Could not add message listener');
    }

    console.log('HMS NHIS Extension: Ready on HMS page');
})();
