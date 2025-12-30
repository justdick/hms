// Background service worker for HMS NHIS CCC Extension

// Listen for messages from content script
chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
    console.log('HMS NHIS Extension BG: Received message', message.type);
    
    if (message.type === 'CCC_CAPTURED') {
        // CCC was captured from NHIA portal - send to HMS tab
        console.log('HMS NHIS Extension BG: CCC captured', message.data);
        
        // Get pending verification IMMEDIATELY before it might be cleared
        chrome.storage.local.get(['pendingVerification'], async (result) => {
            const pending = result.pendingVerification;
            console.log('HMS NHIS Extension BG: Got pending for CCC delivery', pending);
            
            if (pending) {
                await sendCccToHmsWithPending(message.data, pending);
            } else {
                console.log('HMS NHIS Extension BG: No pending, trying all localhost tabs');
                await sendCccToAllHmsTabs(message.data);
            }
        });
        
        sendResponse({ success: true });
    }
    
    if (message.type === 'STORE_VERIFICATION_REQUEST') {
        // Store verification request from HMS
        chrome.storage.local.set({
            pendingVerification: {
                membershipNumber: message.membershipNumber,
                hmsTabId: sender.tab?.id,
                hmsOrigin: message.hmsOrigin,
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

async function sendCccToHmsWithPending(data, pending) {
    console.log('HMS NHIS Extension BG: Sending CCC to HMS with pending', pending);
    
    // Find all tabs that might be HMS
    const tabs = await chrome.tabs.query({});
    
    for (const tab of tabs) {
        // Check if this tab is from HMS origin or localhost or LAN IP
        if (tab.url && (
            (pending.hmsOrigin && tab.url.startsWith(pending.hmsOrigin)) ||
            tab.url.includes('localhost') ||
            tab.url.includes('127.0.0.1') ||
            tab.url.includes('192.168.1.3')
        )) {
            await sendToTab(tab, data);
        }
    }
    
    // Clear pending verification after sending
    chrome.storage.local.remove(['pendingVerification']);
}

async function sendCccToAllHmsTabs(data) {
    console.log('HMS NHIS Extension BG: Sending CCC to all HMS tabs');
    
    const tabs = await chrome.tabs.query({});
    
    for (const tab of tabs) {
        if (tab.url && (
            tab.url.includes('localhost') || 
            tab.url.includes('127.0.0.1') ||
            tab.url.includes('192.168.1.3')
        )) {
            await sendToTab(tab, data);
        }
    }
}

async function sendToTab(tab, data) {
    console.log('HMS NHIS Extension BG: Sending to tab', tab.id, tab.url);
    
    try {
        // Try sending message to content script
        await chrome.tabs.sendMessage(tab.id, {
            type: 'NHIS_CCC_RECEIVED',
            data: data
        });
        console.log('HMS NHIS Extension BG: Message sent via content script');
    } catch (e) {
        console.log('HMS NHIS Extension BG: Content script not available, injecting');
        // Fallback: inject script to post message
        try {
            await chrome.scripting.executeScript({
                target: { tabId: tab.id },
                func: (cccData) => {
                    console.log('HMS NHIS Extension: Injected script posting CCC', cccData);
                    window.postMessage({ type: 'NHIS_CCC_RECEIVED', data: cccData }, '*');
                },
                args: [data]
            });
            console.log('HMS NHIS Extension BG: Script injected');
        } catch (e2) {
            console.log('HMS NHIS Extension BG: Could not inject script', e2);
        }
    }
}
