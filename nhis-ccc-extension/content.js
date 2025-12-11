// Content script for NHIA portal (ccc.nhia.gov.gh)

(async function () {
    'use strict';

    // Get pending verification from background or localStorage
    let pending = null;

    try {
        pending = await chrome.runtime.sendMessage({ type: 'GET_PENDING_VERIFICATION' });
    } catch (e) {
        console.log('HMS NHIS Extension: Could not get pending from background');
    }

    // Fallback: check localStorage (set by HMS page)
    if (!pending) {
        try {
            const stored = localStorage.getItem('hms-nhis-pending-verification');
            if (stored) {
                const parsed = JSON.parse(stored);
                // Only use if recent (within 10 minutes)
                if (Date.now() - parsed.timestamp < 10 * 60 * 1000) {
                    pending = parsed;
                }
            }
        } catch (e) {
            console.log('HMS NHIS Extension: Could not read localStorage');
        }
    }

    if (!pending) {
        console.log('HMS NHIS Extension: No pending verification');
        return;
    }

    const currentUrl = window.location.href;
    const membershipNumber = pending.membershipNumber;
    const credentials = pending.credentials;

    console.log('HMS NHIS Extension: Processing page', currentUrl);

    // Handle different pages in the NHIA flow
    if (currentUrl.includes('/Home/Index') || currentUrl === 'https://ccc.nhia.gov.gh/') {
        // Login page - auto-fill credentials if available
        if (credentials && credentials.username && credentials.password) {
            await handleLogin(credentials);
        } else {
            console.log('HMS NHIS Extension: No credentials available, waiting for manual login');
        }
    } else if (currentUrl.includes('/Home/membershipCheck')) {
        // Main page - click "Generate New Claims Code"
        await waitAndClick('Generate New Claims Code');
    } else if (currentUrl.includes('/Home/cardType')) {
        // Card type selection - click NHIS Card
        await waitAndClick('#nhiaCard', true);
    } else if (currentUrl.includes('/Home/cardNumber')) {
        // Card number entry - fill and submit
        await fillCardNumber(membershipNumber);
    } else if (currentUrl.includes('/Home/claimCode')) {
        // Result page - extract CCC and send to HMS
        await extractAndSendCcc();
    }

    // Helper: Handle login
    async function handleLogin(creds) {
        await sleep(500);

        const usernameInput = document.querySelector('input[type="text"], input[placeholder*="Mobile"], input[placeholder*="User"]');
        const passwordInput = document.querySelector('input[type="password"], input[placeholder*="Password"]');
        const loginBtn = findElementByText('LOGIN') || document.querySelector('button[type="submit"]');

        if (usernameInput && passwordInput) {
            console.log('HMS NHIS Extension: Auto-filling login credentials');

            usernameInput.value = creds.username;
            usernameInput.dispatchEvent(new Event('input', { bubbles: true }));

            passwordInput.value = creds.password;
            passwordInput.dispatchEvent(new Event('input', { bubbles: true }));

            await sleep(300);

            if (loginBtn) {
                console.log('HMS NHIS Extension: Clicking login');
                loginBtn.click();
            }
        }
    }

    // Helper: Wait for element and click
    async function waitAndClick(selector, isId = false) {
        await sleep(500);

        let element;
        if (isId) {
            element = document.querySelector(selector);
        } else {
            // Find by text content
            const elements = document.querySelectorAll('div, button, a');
            for (const el of elements) {
                if (el.textContent.trim() === selector) {
                    element = el;
                    break;
                }
            }
        }

        if (element) {
            console.log('HMS NHIS Extension: Clicking', selector);
            element.click();
        } else {
            console.log('HMS NHIS Extension: Element not found', selector);
        }
    }

    // Helper: Fill card number form
    async function fillCardNumber(number) {
        await sleep(500);

        // Find the input fields
        const inputs = document.querySelectorAll('input[type="text"], input:not([type])');

        if (inputs.length >= 2) {
            // Fill membership number in both fields
            inputs[0].value = number;
            inputs[0].dispatchEvent(new Event('input', { bubbles: true }));

            inputs[1].value = number;
            inputs[1].dispatchEvent(new Event('input', { bubbles: true }));

            console.log('HMS NHIS Extension: Filled membership number', number);

            // Wait a moment then click submit
            await sleep(300);

            const submitBtn =
                findElementByText('SUBMIT') ||
                document.querySelector('button[type="submit"]') ||
                document.querySelector('[class*="submit"]');

            if (submitBtn) {
                console.log('HMS NHIS Extension: Clicking submit');
                submitBtn.click();
            }
        }
    }

    // Helper: Extract CCC from result page
    async function extractAndSendCcc() {
        await sleep(500);

        const data = {
            ccc: null,
            status: null,
            memberName: null,
            membershipNumber: null,
            dob: null,
            gender: null,
            coverageStart: null,
            coverageEnd: null,
        };

        // Parse the result page
        const pageText = document.body.innerText;

        // Extract CCC
        const cccMatch = pageText.match(/CCC\s*:\s*(\d+)/i);
        if (cccMatch) data.ccc = cccMatch[1];

        // Extract Status
        const statusMatch = pageText.match(/STATUS\s*:\s*(\w+)/i);
        if (statusMatch) data.status = statusMatch[1];

        // Extract Name
        const nameMatch = pageText.match(/NAME\s*:\s*([^\n]+)/i);
        if (nameMatch) data.memberName = nameMatch[1].trim();

        // Extract HIN (membership number)
        const hinMatch = pageText.match(/HIN\s*#?\s*:\s*(\d+)/i);
        if (hinMatch) data.membershipNumber = hinMatch[1];

        // Extract DOB
        const dobMatch = pageText.match(/DOB\s*:\s*([\d-]+)/i);
        if (dobMatch) data.dob = dobMatch[1];

        // Extract Gender
        const genderMatch = pageText.match(/GENDER\s*:\s*(\w+)/i);
        if (genderMatch) data.gender = genderMatch[1];

        // Extract Coverage dates
        const startMatch = pageText.match(/START\s*:\s*([\d-]+)/i);
        if (startMatch) data.coverageStart = startMatch[1];

        const endMatch = pageText.match(/END\s*:\s*([\d-]+)/i);
        if (endMatch) data.coverageEnd = endMatch[1];

        console.log('HMS NHIS Extension: Extracted data', data);

        if (data.ccc) {
            // Send to background script
            chrome.runtime.sendMessage({
                type: 'CCC_CAPTURED',
                data: data,
            });

            // Show success indicator on page
            showSuccessMessage(data.ccc);

            // Clear pending verification from localStorage
            localStorage.removeItem('hms-nhis-pending-verification');
        }
    }

    // Helper: Find element by text
    function findElementByText(text) {
        const elements = document.querySelectorAll('div, button, a, span');
        for (const el of elements) {
            if (el.textContent.trim().toUpperCase() === text.toUpperCase()) {
                return el;
            }
        }
        return null;
    }

    // Helper: Show success message
    function showSuccessMessage(ccc) {
        const banner = document.createElement('div');
        banner.style.cssText = `
            position: fixed;
            top: 10px;
            right: 10px;
            background: #10b981;
            color: white;
            padding: 16px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            z-index: 99999;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        `;
        banner.innerHTML = `
            ✓ CCC <strong>${ccc}</strong> sent to HMS<br>
            <small>You can close this tab</small>
        `;
        document.body.appendChild(banner);

        // Auto-remove after 10 seconds
        setTimeout(() => banner.remove(), 10000);
    }

    // Helper: Sleep
    function sleep(ms) {
        return new Promise((resolve) => setTimeout(resolve, ms));
    }
})();
