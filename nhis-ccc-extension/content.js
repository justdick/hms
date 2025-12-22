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
        // The button is: <a id="newclaimsCode1" class="btn btn-generate">
        const generateBtn = document.querySelector('#newclaimsCode1') ||
                           document.querySelector('a.btn-generate') ||
                           findElementByText('Generate New Claims Code');
        if (generateBtn) {
            console.log('HMS NHIS Extension: Clicking Generate New Claims Code');
            generateBtn.click();
        } else {
            console.log('HMS NHIS Extension: Generate button not found');
        }
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
        await sleep(800);

        console.log('HMS NHIS Extension: Looking for login form...');

        const usernameInput = document.querySelector(
            'input[type="text"], input[placeholder*="Mobile"], input[placeholder*="User"]',
        );
        const passwordInput = document.querySelector(
            'input[type="password"], input[placeholder*="Password"]',
        );

        console.log('HMS NHIS Extension: Found inputs', { usernameInput, passwordInput });

        if (usernameInput && passwordInput) {
            console.log('HMS NHIS Extension: Auto-filling login credentials');

            // Clear and fill username
            usernameInput.focus();
            usernameInput.value = creds.username;
            usernameInput.dispatchEvent(new Event('input', { bubbles: true }));
            usernameInput.dispatchEvent(new Event('change', { bubbles: true }));

            await sleep(200);

            // Clear and fill password
            passwordInput.focus();
            passwordInput.value = creds.password;
            passwordInput.dispatchEvent(new Event('input', { bubbles: true }));
            passwordInput.dispatchEvent(new Event('change', { bubbles: true }));

            await sleep(300);

            // Find and click login button - it's an <a> tag with id="submit"
            await sleep(300);
            
            // The login button is: <a id="submit" class="btn btn-login">LOGIN</a>
            let loginBtn = document.querySelector('#submit') ||
                           document.querySelector('a.btn-login') ||
                           document.querySelector('a[class*="login"]');
            
            // Fallback: find by text
            if (!loginBtn) {
                const allLinks = document.querySelectorAll('a');
                for (const link of allLinks) {
                    if (link.textContent.trim() === 'LOGIN') {
                        loginBtn = link;
                        break;
                    }
                }
            }

            if (loginBtn) {
                console.log('HMS NHIS Extension: Found login button', loginBtn.outerHTML);
                loginBtn.click();
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

            // Submit button is likely: <a id="submit" class="btn btn-...">SUBMIT</a>
            const submitBtn = document.querySelector('#submit') ||
                              document.querySelector('a.btn-login') ||
                              document.querySelector('a[class*="submit"]');
            
            // Fallback: find <a> by text
            let btn = submitBtn;
            if (!btn) {
                const allLinks = document.querySelectorAll('a');
                for (const link of allLinks) {
                    if (link.textContent.trim() === 'SUBMIT') {
                        btn = link;
                        break;
                    }
                }
            }

            if (btn) {
                console.log('HMS NHIS Extension: Clicking submit', btn.outerHTML);
                btn.click();
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
            error: null,
        };

        // Parse the result page
        const pageText = document.body.innerText;

        // Check for error modal (INACTIVE membership)
        // The error modal shows "An Error Occured!" with "INACTIVE" text
        const hasErrorModal = pageText.includes('An Error Occured') || 
                              pageText.includes('An Error Occurred') ||
                              pageText.includes('INACTIVE');
        
        if (hasErrorModal) {
            // Check for specific error messages
            if (pageText.includes('INACTIVE')) {
                data.error = 'INACTIVE';
                data.status = 'INACTIVE';
            } else {
                // Try to extract error message
                const errorMatch = pageText.match(/An Error Occur[r]?ed[!]?\s*([^\n]+)/i);
                if (errorMatch) {
                    data.error = errorMatch[1].trim();
                } else {
                    data.error = 'Unknown error';
                }
            }
            console.log('HMS NHIS Extension: Detected error/inactive status', data.error);
        }

        // Extract CCC (may not exist if INACTIVE)
        const cccMatch = pageText.match(/CCC\s*:\s*["']?(\d+)["']?/i);
        if (cccMatch) data.ccc = cccMatch[1];

        // Extract Status from the page (STATUS : ACTIVE or similar)
        // This is different from the error status
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

        // Extract Coverage dates - these are visible even when INACTIVE
        // Format on page: "START : 29-10-2022" and "END : 28-10-2023"
        const startMatch = pageText.match(/START\s*:\s*["']?([\d-]+)["']?/i);
        if (startMatch) data.coverageStart = startMatch[1];

        const endMatch = pageText.match(/END\s*:\s*["']?([\d-]+)["']?/i);
        if (endMatch) data.coverageEnd = endMatch[1];

        console.log('HMS NHIS Extension: Extracted data', data);

        // Send data if we have CCC OR if we have dates (even for INACTIVE)
        // This allows HMS to update dates even when membership is expired
        if (data.ccc || data.coverageStart || data.coverageEnd || data.status) {
            // Send to background script (it will clear pending verification after sending to HMS)
            chrome.runtime.sendMessage({
                type: 'CCC_CAPTURED',
                data: data,
            });

            // Show appropriate message
            if (data.ccc) {
                showSuccessMessage(data.ccc);
            } else if (data.error || data.status === 'INACTIVE') {
                showErrorMessage(data.status || data.error, data.coverageEnd);
            }

            // Auto-close this tab after 3 seconds
            setTimeout(() => {
                window.close();
            }, 3000);
        } else {
            console.log('HMS NHIS Extension: No useful data found on page');
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

    // Helper: Show error message for INACTIVE membership
    function showErrorMessage(status, endDate) {
        const banner = document.createElement('div');
        banner.style.cssText = `
            position: fixed;
            top: 10px;
            right: 10px;
            background: #ef4444;
            color: white;
            padding: 16px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            z-index: 99999;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        `;
        banner.innerHTML = `
            ⚠️ Membership <strong>${status}</strong><br>
            <small>${endDate ? `Expired: ${endDate}` : 'Coverage expired'}</small><br>
            <small>Data sent to HMS - you can close this tab</small>
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
