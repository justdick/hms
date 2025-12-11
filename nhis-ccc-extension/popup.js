// Popup script
document.addEventListener('DOMContentLoaded', async () => {
    const statusEl = document.getElementById('status');
    const pendingInfoEl = document.getElementById('pending-info');
    const membershipEl = document.getElementById('membership-number');
    
    // Check for pending verification
    const result = await chrome.storage.local.get(['pendingVerification']);
    const pending = result.pendingVerification;
    
    if (pending && pending.membershipNumber) {
        // Check if it's recent (within last 10 minutes)
        const age = Date.now() - pending.timestamp;
        if (age < 10 * 60 * 1000) {
            statusEl.className = 'status pending';
            statusEl.textContent = '⏳ Verification in progress...';
            pendingInfoEl.style.display = 'block';
            membershipEl.textContent = pending.membershipNumber;
        } else {
            // Expired
            chrome.storage.local.remove(['pendingVerification']);
            showIdle();
        }
    } else {
        showIdle();
    }
    
    function showIdle() {
        statusEl.className = 'status ready';
        statusEl.textContent = '✓ Ready - Waiting for HMS verification request';
        pendingInfoEl.style.display = 'none';
    }
});
