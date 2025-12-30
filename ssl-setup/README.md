# SSL Setup for WAMP (HTTPS on LAN)

This enables HTTPS so clipboard works on client machines.

## Server IP: 192.168.1.3
## Client Access URL: https://192.168.1.3:8443

> **Note**: WAMP uses port 8443 for HTTPS because XAMPP uses 443.

---

## Step 1: Copy SSL files to Apache

Run in PowerShell (as Admin) on the SERVER:

```powershell
# Create SSL directory
New-Item -ItemType Directory -Path "C:\wamp64\bin\apache\apache2.4.65\conf\ssl" -Force

# Copy certificate files
Copy-Item "C:\wamp64\www\hms\ssl-setup\server.crt" "C:\wamp64\bin\apache\apache2.4.65\conf\ssl\"
Copy-Item "C:\wamp64\www\hms\ssl-setup\server.key" "C:\wamp64\bin\apache\apache2.4.65\conf\ssl\"

# Copy SSL config file
Copy-Item "C:\wamp64\www\hms\ssl-setup\httpd-ssl.conf" "C:\wamp64\bin\apache\apache2.4.65\conf\extra\"
```

## Step 2: Enable SSL in Apache

Edit `C:\wamp64\bin\apache\apache2.4.65\conf\httpd.conf`:

Find and UNCOMMENT these lines (remove the # at the start):

```apache
LoadModule ssl_module modules/mod_ssl.so
LoadModule socache_shmcb_module modules/mod_socache_shmcb.so
Include conf/extra/httpd-ssl.conf
```

## Step 3: Restart WAMP

- Click WAMP tray icon → Restart All Services

Or run in PowerShell:
```powershell
net stop wampapache64; net start wampapache64
```

## Step 4: Update .env file

Edit `C:\wamp64\www\hms3\.env` and change:

```
APP_URL=https://192.168.1.76
```

## Step 5: Test

- On SERVER: Open https://localhost or https://192.168.1.3
- On CLIENT machines: Open https://192.168.1.3

First time, browser shows warning:
1. Click "Advanced" 
2. Click "Proceed to 192.168.1.76 (unsafe)"

This is normal for self-signed certificates. Clipboard will now work!

---

## Optional: Remove browser warning on clients

To permanently trust the certificate on client machines:

1. Copy `server.crt` to the client machine
2. Double-click `server.crt`
3. Click "Install Certificate"
4. Select "Local Machine" → Next
5. Select "Place all certificates in the following store"
6. Click Browse → Select "Trusted Root Certification Authorities"
7. Click Next → Finish
8. Restart browser

---

## Troubleshooting

**Apache won't start:**
- Check `C:\wamp64\bin\apache\apache2.4.65\logs\error.log`
- Make sure port 443 isn't used by another app

**Certificate error:**
- Make sure server.crt and server.key are in `conf\ssl\` folder
- Check paths in httpd-ssl.conf match your installation
