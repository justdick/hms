// Content script injected into HMS pages to enable extension communication

(function() {
    'use strict';
    
    // Mark that extension is installed
    localStorage.setItem('hms-nhis-extension-installed', 'true');
    
    // Create a marker element for detection
    const marker = document.createElement('div');
    marker.id = 'hms-nhis-extension-marker';
    marker.style.display = 'none';
    marker.dataset.version = chrome.runtime.getManifest().version;
    document.body.appendChild(marker);
    
    // Listen for verification requests from HMS page
    window.addEventListener('message', (event) => {
        // Only accept messages from same origin
        if (event.origin !== window.location.origin) return;
        
        if (event.data?.type === 'HMS_NHIS_VERIFY_REQUEST') {
            // Store verification request and notify background
            chrome.runtime.sendMessage({
                type: 'STORE_VERIFICATION_REQUEST',
                membershipNumber: event.data.membershipNumber,
                hmsTabId: null // Will be set by background
            });
            
            // Acknowledge receipt
            window.postMessage({ type: 'HMS_NHIS_VERIFY_ACK' }, '*');
        }
    });
    
    // Listen for CCC data from background script
    chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
        if (message.type === 'NHIS_CCC_RECEIVED') {
            // Forward to HMS page via postMessage
            window.postMessage({
                type: 'NHIS_CCC_RECEIVED',
                data: message.data
            }, '*');
            sendResponse({ received: true });
        }
        return true;
    });
    
    console.log('HMS NHIS Extension: Ready on HMS page');
})();
