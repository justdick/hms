// Background service worker for HMS NHIS CCC Extension

// Listen for messages from content script
chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
    if (message.type === 'CCC_CAPTURED') {
        // CCC was captured from NHIA portal - send to HMS tab
        sendCccToHms(message.data);
        sendResponse({ success: true });
    }
    
    if (message.type === 'STORE_VERIFICATION_REQUEST') {
        // Store verification request from HMS
        chrome.storage.local.set({
            pendingVerification: {
                membershipNumber: message.membershipNumber,
                hmsTabId: message.hmsTabId,
                timestamp: Date.now()
            }
        });
        sendResponse({ success: true });
    }
    
    if (message.type === 'GET_PENDING_VERIFICATION') {
        chrome.storage.local.get(['pendingVerification'], (result) => {
            sendResponse(result.pendingVerification || null);
        });
        return true; // Keep channel open for async response
    }
    
    return true;
});

// Listen for messages from web pages (HMS)
chrome.runtime.onMessageExternal.addListener((message, sender, sendResponse) => {
    if (message.type === 'NHIS_VERIFY_REQUEST') {
        // HMS is requesting verification
        chrome.storage.local.set({
            pendingVerification: {
                membershipNumber: message.membershipNumber,
                hmsOrigin: sender.origin,
                hmsTabId: sender.tab?.id,
                timestamp: Date.now()
            }
        });
        sendResponse({ success: true, extensionReady: true });
    }
    
    if (message.type === 'CHECK_EXTENSION') {
        // HMS checking if extension is installed
        sendResponse({ installed: true, version: chrome.runtime.getManifest().version });
    }
    
    return true;
});

async function sendCccToHms(data) {
    const result = await chrome.storage.local.get(['pendingVerification']);
    const pending = result.pendingVerification;
    
    if (!pending) {
        console.log('No pending verification');
        return;
    }
    
    // Send to the HMS tab that initiated the request
    if (pending.hmsTabId) {
        try {
            await chrome.tabs.sendMessage(pending.hmsTabId, {
                type: 'NHIS_CCC_RECEIVED',
                data: data
            });
        } catch (e) {
            // Fallback: inject script to post message
            chrome.scripting.executeScript({
                target: { tabId: pending.hmsTabId },
                func: (cccData) => {
                    window.postMessage({ type: 'NHIS_CCC_RECEIVED', data: cccData }, '*');
                },
                args: [data]
            });
        }
    }
    
    // Clear pending verification
    chrome.storage.local.remove(['pendingVerification']);
}
