// Content script for NHIA portal (ccc.nhia.gov.gh)

(async function () {
    'use strict';

    console.log('HMS NHIS Extension: Content script loaded on', window.location.href);

    // Get pending verification from extension storage
    let pending = null;

    try {
        const result = await chrome.storage.local.get(['pendingVerification']);
        pending = result.pendingVerification;
        console.log('HMS NHIS Extension: Got pending verification', pending);
    } catch (e) {
        console.log('HMS NHIS Extension: Could not get pending from storage', e);
    }

    if (!pending) {
        console.log('HMS NHIS Extension: No pending verification found');
        return;
    }

    // Check if verification is recent (within 10 minutes)
    if (Date.now() - pending.timestamp > 10 * 60 * 1000) {
        console.log('HMS NHIS Extension: Pending verification expired');
        chrome.storage.local.remove(['pendingVerification']);
        return;
    }

    const currentUrl = window.location.href;
    const membershipNumber = pending.membershipNumber;
    const credentials = pending.credentials;

    console.log('HMS NHIS Extension: Processing page', currentUrl, 'with membership', membershipNumber);

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
        await sleep(800);
        await waitAndClick('Generate New Claims Code');
    } else if (currentUrl.includes('/Home/cardType')) {
        // Card type selection - click NHIS Card
        await sleep(500);
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
        await sleep(1000);

        console.log('HMS NHIS Extension: Looking for login form...');

        // Find all inputs on the page
        const allInputs = document.querySelectorAll('input');
        console.log('HMS NHIS Extension: Found', allInputs.length, 'inputs');
        
        let usernameInput = null;
        let passwordInput = null;
        
        // Find username and password inputs
        for (const input of allInputs) {
            const placeholder = (input.placeholder || '').toLowerCase();
            const type = (input.type || '').toLowerCase();
            
            if (type === 'password' || placeholder.includes('password')) {
                passwordInput = input;
            } else if (type === 'text' || placeholder.includes('mobile') || placeholder.includes('user') || placeholder.includes('number')) {
                usernameInput = input;
            }
        }

        console.log('HMS NHIS Extension: Found inputs', { usernameInput, passwordInput });

        if (usernameInput && passwordInput) {
            console.log('HMS NHIS Extension: Auto-filling login credentials');

            // Simulate typing in username field
            usernameInput.focus();
            usernameInput.value = '';
            await sleep(100);
            
            // Type character by character to trigger validation
            for (const char of creds.username) {
                usernameInput.value += char;
                usernameInput.dispatchEvent(new Event('input', { bubbles: true }));
                usernameInput.dispatchEvent(new KeyboardEvent('keydown', { bubbles: true }));
                usernameInput.dispatchEvent(new KeyboardEvent('keyup', { bubbles: true }));
                await sleep(30);
            }
            usernameInput.dispatchEvent(new Event('change', { bubbles: true }));
            usernameInput.dispatchEvent(new Event('blur', { bubbles: true }));

            await sleep(200);

            // Simulate typing in password field
            passwordInput.focus();
            passwordInput.value = '';
            await sleep(100);
            
            for (const char of creds.password) {
                passwordInput.value += char;
                passwordInput.dispatchEvent(new Event('input', { bubbles: true }));
                passwordInput.dispatchEvent(new KeyboardEvent('keydown', { bubbles: true }));
                passwordInput.dispatchEvent(new KeyboardEvent('keyup', { bubbles: true }));
                await sleep(30);
            }
            passwordInput.dispatchEvent(new Event('change', { bubbles: true }));
            passwordInput.dispatchEvent(new Event('blur', { bubbles: true }));

            await sleep(500);

            // Find and click login button
            let loginBtn = null;
            
            // Method 1: Find by exact text content "LOGIN"
            const allElements = document.querySelectorAll('div, button, a, span');
            for (const el of allElements) {
                const text = el.textContent.trim().toUpperCase();
                if (text === 'LOGIN' && el.offsetParent !== null) { // visible element
                    loginBtn = el;
                    console.log('HMS NHIS Extension: Found LOGIN element', el.tagName, el.className);
                    break;
                }
            }

            if (loginBtn) {
                console.log('HMS NHIS Extension: Clicking login button');
                
                // Scroll into view
                loginBtn.scrollIntoView({ behavior: 'instant', block: 'center' });
                await sleep(100);
                
                // Try native click
                loginBtn.click();
                
                await sleep(200);
                
                // Try mouse events
                const rect = loginBtn.getBoundingClientRect();
                const centerX = rect.left + rect.width / 2;
                const centerY = rect.top + rect.height / 2;
                
                loginBtn.dispatchEvent(new MouseEvent('mousedown', { 
                    bubbles: true, cancelable: true, view: window,
                    clientX: centerX, clientY: centerY
                }));
                await sleep(50);
                loginBtn.dispatchEvent(new MouseEvent('mouseup', { 
                    bubbles: true, cancelable: true, view: window,
                    clientX: centerX, clientY: centerY
                }));
                await sleep(50);
                loginBtn.dispatchEvent(new MouseEvent('click', { 
                    bubbles: true, cancelable: true, view: window,
                    clientX: centerX, clientY: centerY
                }));
                
                console.log('HMS NHIS Extension: Login button clicked');
            } else {
                console.log('HMS NHIS Extension: Login button not found');
            }
        } else {
            console.log('HMS NHIS Extension: Login form inputs not found');
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
            element = findElementByText(selector);
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
        await sleep(800);

        console.log('HMS NHIS Extension: Looking for card number inputs...');

        // Find the input fields
        const inputs = document.querySelectorAll('input[type="text"], input:not([type])');
        console.log('HMS NHIS Extension: Found', inputs.length, 'inputs');

        if (inputs.length >= 2) {
            // Fill membership number in both fields
            inputs[0].focus();
            inputs[0].value = number;
            inputs[0].dispatchEvent(new Event('input', { bubbles: true }));
            inputs[0].dispatchEvent(new Event('change', { bubbles: true }));

            await sleep(200);

            inputs[1].focus();
            inputs[1].value = number;
            inputs[1].dispatchEvent(new Event('input', { bubbles: true }));
            inputs[1].dispatchEvent(new Event('change', { bubbles: true }));

            console.log('HMS NHIS Extension: Filled membership number', number);

            // Wait a moment then click submit
            await sleep(500);

            const submitBtn =
                findElementByText('SUBMIT') ||
                document.querySelector('button[type="submit"]') ||
                document.querySelector('[class*="submit"]');

            if (submitBtn) {
                console.log('HMS NHIS Extension: Clicking submit');
                submitBtn.click();
            } else {
                console.log('HMS NHIS Extension: Submit button not found');
            }
        } else {
            console.log('HMS NHIS Extension: Not enough input fields found');
        }
    }

    // Helper: Extract CCC from result page
    async function extractAndSendCcc() {
        await sleep(800);

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
        const cccMatch = pageText.match(/CCC\s*:\s*["']?(\d+)["']?/i);
        if (cccMatch) data.ccc = cccMatch[1];

        // Extract Status
        const statusMatch = pageText.match(/STATUS\s*:\s*["']?(\w+)["']?/i);
        if (statusMatch) data.status = statusMatch[1];

        // Extract Name
        const nameMatch = pageText.match(/NAME\s*:\s*["']?([^\n"']+)["']?/i);
        if (nameMatch) data.memberName = nameMatch[1].trim();

        // Extract HIN (membership number)
        const hinMatch = pageText.match(/HIN\s*#?\s*:\s*["']?(\d+)["']?/i);
        if (hinMatch) data.membershipNumber = hinMatch[1];

        // Extract DOB
        const dobMatch = pageText.match(/DOB\s*:\s*["']?([\d-]+)["']?/i);
        if (dobMatch) data.dob = dobMatch[1];

        // Extract Gender
        const genderMatch = pageText.match(/GENDER\s*:\s*["']?(\w+)["']?/i);
        if (genderMatch) data.gender = genderMatch[1];

        // Extract Coverage dates
        const startMatch = pageText.match(/START\s*:\s*["']?([\d-]+)["']?/i);
        if (startMatch) data.coverageStart = startMatch[1];

        const endMatch = pageText.match(/END\s*:\s*["']?([\d-]+)["']?/i);
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

            // Clear pending verification
            chrome.storage.local.remove(['pendingVerification']);
        } else {
            console.log('HMS NHIS Extension: CCC not found on page');
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
