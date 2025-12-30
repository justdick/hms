# SSL Setup for WAMP (HTTPS on LAN)

Run these commands in PowerShell as Administrator:

## Step 1: Create SSL directory and copy config
```powershell
New-Item -ItemType Directory -Path "C:\wamp64\bin\apache\apache2.4.65\conf\ssl" -Force
Copy-Item "C:\wamp64\www\hms3\ssl-setup\openssl.cnf" "C:\wamp64\bin\apache\apache2.4.65\conf\ssl\"
```

## Step 2: Generate the SSL certificate (valid for 10 years)
```powershell
cd C:\wamp64\bin\apache\apache2.4.65\conf\ssl
& "C:\wamp64\bin\apache\apache2.4.65\bin\openssl.exe" req -x509 -nodes -days 3650 -newkey rsa:2048 -keyout server.key -out server.crt -config openssl.cnf
```

## Step 3: Enable SSL module in Apache
Edit `C:\wamp64\bin\apache\apache2.4.65\conf\httpd.conf`:
- Find and uncomment (remove #): `LoadModule ssl_module modules/mod_ssl.so`
- Find and uncomment (remove #): `Include conf/extra/httpd-ssl.conf`
- Find and uncomment (remove #): `LoadModule socache_shmcb_module modules/mod_socache_shmcb.so`

## Step 4: Configure SSL Virtual Host
Edit `C:\wamp64\bin\apache\apache2.4.65\conf\extra\httpd-ssl.conf`:

Replace the entire `<VirtualHost _default_:443>` block with:

```apache
<VirtualHost _default_:443>
    DocumentRoot "C:/wamp64/www/hms3/public"
    ServerName 192.168.1.76:443
    
    SSLEngine on
    SSLCertificateFile "C:/wamp64/bin/apache/apache2.4.65/conf/ssl/server.crt"
    SSLCertificateKeyFile "C:/wamp64/bin/apache/apache2.4.65/conf/ssl/server.key"
    
    <Directory "C:/wamp64/www/hms3/public">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog "logs/ssl_error.log"
    CustomLog "logs/ssl_access.log" common
</VirtualHost>
```

## Step 5: Restart WAMP
- Click WAMP icon → Restart All Services
- Or run: `net stop wampapache64 && net start wampapache64`

## Step 6: Update .env
Change APP_URL to:
```
APP_URL=https://192.168.1.76
```

## Step 7: Access the app
- From server: https://localhost or https://192.168.1.76
- From clients: https://192.168.1.76

First time accessing, browser will show warning. Click "Advanced" → "Proceed to 192.168.1.76"

## Optional: Install certificate on client machines
To remove the browser warning, install `server.crt` on client machines:
1. Copy `C:\wamp64\bin\apache\apache2.4.65\conf\ssl\server.crt` to client
2. Double-click → Install Certificate → Local Machine → Trusted Root Certification Authorities
