# HMS NHIS CCC Auto-Verification Extension

Chrome/Edge browser extension that automatically captures CCC (Claims Check Code) from the NHIA portal and sends it to your HMS.

## Installation

### For Development/Testing:

1. Open Chrome/Edge and go to `chrome://extensions/` (or `edge://extensions/`)
2. Enable "Developer mode" (toggle in top right)
3. Click "Load unpacked"
4. Select this `nhis-ccc-extension` folder
5. The extension icon should appear in your toolbar

### Icons Setup:

Create PNG icons in the `icons/` folder:
- `icon16.png` (16x16 pixels)
- `icon48.png` (48x48 pixels)  
- `icon128.png` (128x128 pixels)

You can use any NHIS-related icon or the HMS logo.

## How It Works

1. **HMS triggers verification**: When staff clicks "Verify NHIS" in HMS, it stores the membership number and opens the NHIA portal
2. **Extension auto-fills**: The extension detects the NHIA portal and automatically:
   - Clicks "Generate New Claims Code"
   - Selects "NHIS Card"
   - Fills in the membership number
   - Clicks Submit
3. **CCC captured**: When the result page loads, the extension extracts the CCC and member details
4. **Sent to HMS**: The CCC is sent back to the HMS tab via postMessage
5. **Auto-populated**: HMS receives the CCC and auto-fills the field

## Permissions

- `activeTab`: To interact with the current tab
- `storage`: To store pending verification requests
- `scripting`: To inject scripts for communication
- `host_permissions` for `ccc.nhia.gov.gh`: To run on the NHIA portal

## Troubleshooting

### Extension not working?
1. Make sure you're logged into the NHIA portal first
2. Check that the extension is enabled in `chrome://extensions/`
3. Try reloading the extension

### CCC not being sent to HMS?
1. Make sure the HMS tab is still open
2. Check the browser console for errors
3. Try the verification again

## Development

To modify the extension:
1. Edit the files
2. Go to `chrome://extensions/`
3. Click the refresh icon on the extension card
4. Test your changes
