# HMS Code Protection & Licensing Strategy

## Overview

This document outlines the strategy for protecting HMS source code when deploying to client local servers, and implementing a licensing system for subscription-based revenue.

## Architecture

```
┌─────────────────────────────────────────────────────────┐
│              YOUR LICENSE SERVER (online)               │
│                                                         │
│  Laravel App hosted on your server/cloud                │
│  - Client management                                    │
│  - Payment processing (Paystack/Stripe)                 │
│  - License generation                                   │
│  - License delivery API                                 │
└─────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────┐
│              CLIENT'S LOCAL SERVER                      │
│                                                         │
│  ├── XAMPP/Laragon + SourceGuardian Loader              │
│  ├── Encoded PHP files (protected)                      │
│  ├── license.lic (expires, locked to hardware)          │
│  └── MySQL database                                     │
└─────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────┐
│              CLIENT WORKSTATIONS                        │
│                                                         │
│  ├── Web browser (Chrome/Edge)                          │
│  ├── Access HMS via LAN (192.168.x.x)                   │
│  └── NHIS CCC Extension (if needed)                     │
└─────────────────────────────────────────────────────────┘
```

## Code Protection Options

### Option 1: SourceGuardian (~$199 one-time)

**Pros:**
- Cheaper than ionCube
- License locking (MAC, IP, domain, expiration)
- License file system for easy renewals
- Good documentation

**Cons:**
- Less widespread than ionCube (but still well-supported)

### Option 2: ionCube (~$199-$399)

**Pros:**
- Industry standard
- Most hosting providers have loader pre-installed
- Very secure

**Cons:**
- More expensive
- Annual renewal for updates

### Option 3: DIY License System (Free)

**Pros:**
- No cost
- Full control

**Cons:**
- Code is readable (just obfuscated)
- Easier to bypass

### Recommendation: SourceGuardian

Best balance of cost, security, and features for HMS deployment.

## SourceGuardian License Locking

When encoding, lock files to prevent unauthorized copying:

| Lock Type | Use Case |
|-----------|----------|
| MAC Address | Lock to specific server hardware |
| IP Address | Lock to server IP (less reliable if IP changes) |
| Domain | Lock to specific domain/hostname |
| License File | Require .lic file (best for subscriptions) |
| Expiration | Time-limited licenses |

### Recommended Lock Combination

```
MAC Address + License File + Expiration
```

This ensures:
- Files only work on authorized server
- You control renewal via .lic files
- Non-paying clients lose access

## License File System

### How It Works

1. **Initial Deployment:**
   - Encode PHP files with SourceGuardian
   - Lock to client's server MAC address
   - Generate initial .lic file with expiration date
   - Deploy to client

2. **Renewal:**
   - Client pays subscription
   - Generate new .lic file with extended expiration
   - Client replaces old .lic file
   - No need to re-deploy encoded files

### License File Contents

SourceGuardian .lic files contain:
- Hardware identifiers (MAC/IP)
- Expiration date
- Custom data (client ID, features, etc.)
- Cryptographic signature

## Automated Licensing System

### Components Needed

1. **License Portal** (Laravel app on your server)
2. **Payment Integration** (Paystack for Ghana)
3. **SourceGuardian License Generator**
4. **Client Database**

### Database Schema (License Portal)

```sql
-- Clients table
CREATE TABLE clients (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(50),
    facility_name VARCHAR(255),
    server_mac VARCHAR(50),
    server_ip VARCHAR(50),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Licenses table
CREATE TABLE licenses (
    id BIGINT PRIMARY KEY,
    client_id BIGINT REFERENCES clients(id),
    license_key VARCHAR(255) UNIQUE,
    license_file_path VARCHAR(255),
    issued_at TIMESTAMP,
    expires_at TIMESTAMP,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Payments table
CREATE TABLE payments (
    id BIGINT PRIMARY KEY,
    client_id BIGINT REFERENCES clients(id),
    license_id BIGINT REFERENCES licenses(id),
    amount DECIMAL(10,2),
    currency VARCHAR(10) DEFAULT 'GHS',
    payment_reference VARCHAR(255),
    payment_method VARCHAR(50),
    status VARCHAR(50),
    paid_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### License Generation Flow

```php
// LicenseService.php
class LicenseService
{
    public function generateLicense(Client $client, int $months = 12): License
    {
        $expiresAt = now()->addMonths($months);
        $licenseKey = Str::uuid();
        
        // Generate .lic file using SourceGuardian CLI
        $licenseFile = $this->generateSourceGuardianLicense([
            'mac' => $client->server_mac,
            'expires' => $expiresAt->format('Y-m-d'),
            'client_id' => $client->id,
            'license_key' => $licenseKey,
        ]);
        
        // Store license record
        return License::create([
            'client_id' => $client->id,
            'license_key' => $licenseKey,
            'license_file_path' => $licenseFile,
            'issued_at' => now(),
            'expires_at' => $expiresAt,
        ]);
    }
    
    private function generateSourceGuardianLicense(array $data): string
    {
        // SourceGuardian provides CLI tool or PHP API
        // This generates the .lic file
        $outputPath = storage_path("licenses/{$data['license_key']}.lic");
        
        // Example CLI command (actual syntax depends on SourceGuardian version)
        $command = sprintf(
            'sglic --mac=%s --expires=%s --output=%s',
            $data['mac'],
            $data['expires'],
            $outputPath
        );
        
        exec($command);
        
        return $outputPath;
    }
}
```

### Payment Webhook Handler

```php
// PaystackWebhookController.php
public function handlePayment(Request $request)
{
    $payload = $request->all();
    
    // Verify webhook signature
    if (!$this->verifyPaystackSignature($request)) {
        return response('Invalid signature', 400);
    }
    
    if ($payload['event'] === 'charge.success') {
        $reference = $payload['data']['reference'];
        $clientId = $payload['data']['metadata']['client_id'];
        
        $client = Client::findOrFail($clientId);
        
        // Generate new license
        $license = app(LicenseService::class)->generateLicense($client);
        
        // Record payment
        Payment::create([
            'client_id' => $client->id,
            'license_id' => $license->id,
            'amount' => $payload['data']['amount'] / 100,
            'payment_reference' => $reference,
            'status' => 'completed',
            'paid_at' => now(),
        ]);
        
        // Notify client
        Mail::to($client->email)->send(new LicenseReadyMail($license));
    }
    
    return response('OK');
}
```

### License Download API (for HMS auto-fetch)

```php
// LicenseApiController.php
public function download(Request $request)
{
    $request->validate([
        'client_id' => 'required',
        'license_key' => 'required',
    ]);
    
    $license = License::where('client_id', $request->client_id)
        ->where('license_key', $request->license_key)
        ->where('is_active', true)
        ->where('expires_at', '>', now())
        ->latest()
        ->firstOrFail();
    
    return response()->download($license->license_file_path, 'license.lic');
}
```

### HMS License Check (Client Side)

```php
// app/Providers/LicenseServiceProvider.php
class LicenseServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if (app()->runningInConsole()) {
            return; // Allow artisan commands
        }
        
        $this->validateLicense();
    }
    
    private function validateLicense()
    {
        $licensePath = base_path('license.lic');
        
        if (!file_exists($licensePath)) {
            abort(403, 'License file not found. Please contact support.');
        }
        
        // SourceGuardian validates the .lic file automatically
        // If invalid, the encoded PHP files won't execute
        
        // Optional: Add grace period warning
        $this->checkExpirationWarning();
    }
    
    private function checkExpirationWarning()
    {
        // Check if license expires within 30 days
        // Show warning to admin users
    }
}
```

### Auto-Fetch License (Optional)

```php
// app/Console/Commands/FetchLicense.php
class FetchLicense extends Command
{
    protected $signature = 'license:fetch';
    protected $description = 'Fetch latest license from license server';
    
    public function handle()
    {
        $response = Http::get(config('app.license_server_url') . '/api/license/download', [
            'client_id' => config('app.client_id'),
            'license_key' => config('app.license_key'),
        ]);
        
        if ($response->successful()) {
            file_put_contents(base_path('license.lic'), $response->body());
            $this->info('License updated successfully.');
        } else {
            $this->error('Failed to fetch license: ' . $response->status());
        }
    }
}

// Schedule in routes/console.php
Schedule::command('license:fetch')->daily();
```

## Deployment Workflow

### Initial Client Setup

1. **Collect Client Info:**
   - Facility name
   - Server MAC address (run `getmac` on Windows)
   - Contact email/phone

2. **Register in License Portal:**
   - Create client record
   - Process initial payment
   - Generate first license

3. **Prepare Encoded Files:**
   ```bash
   # Encode PHP files with SourceGuardian
   sourceguardian encode \
     --input ./app \
     --output ./dist/app \
     --lock-mac "AA:BB:CC:DD:EE:FF" \
     --license-file
   ```

4. **Deploy to Client:**
   - Install XAMPP + SourceGuardian Loader
   - Copy encoded HMS files
   - Place license.lic in root
   - Import database
   - Configure .env

### Renewal Process

1. Client receives expiration reminder (email)
2. Client pays via portal (Paystack)
3. System auto-generates new .lic
4. Client downloads or HMS auto-fetches
5. Replace old license.lic
6. Done!

## Pricing Model Suggestions

| Plan | Duration | Price (GHS) | Features |
|------|----------|-------------|----------|
| Monthly | 1 month | 200 | Basic support |
| Annual | 12 months | 2,000 | Priority support, 2 months free |
| Lifetime | Forever | 10,000 | Perpetual license, 1 year support |

## Security Considerations

1. **License Server Security:**
   - HTTPS only
   - Rate limiting on API
   - Webhook signature verification
   - Secure storage of .lic files

2. **Client Deployment:**
   - Never include source code
   - Encode ALL PHP files (app, routes, config)
   - Keep .env and database credentials secure

3. **License File:**
   - Unique per client
   - Tied to hardware
   - Time-limited

## Files NOT to Encode

- `public/` folder (assets, index.php entry point)
- `storage/` folder
- `bootstrap/cache/`
- `.env` file
- `composer.json`, `package.json`
- `node_modules/`, `vendor/` (dependencies)

## Files TO Encode

- `app/` (all PHP code)
- `routes/` (route definitions)
- `config/` (configuration files)
- `database/` (migrations, seeders)
- Custom packages in `packages/`

## NHIS CCC Extension

The browser extension is separate from PHP encoding:
- Stays as JavaScript
- Installed on client browsers
- Communicates with HMS via API
- Can be obfuscated with `javascript-obfuscator` if needed

## Tools & Resources

### SourceGuardian
- Website: https://www.sourceguardian.com/
- Price: ~$199 (one-time)
- Loader: Free (install on client servers)

### Alternative: ionCube
- Website: https://www.ioncube.com/
- Price: ~$199-$399
- More widespread loader support

### Installer Creation
- **Inno Setup** (Windows): https://jrsoftware.org/isinfo.php
- **NSIS**: https://nsis.sourceforge.io/

### Payment Gateway
- **Paystack** (Ghana): https://paystack.com/
- **Stripe** (International): https://stripe.com/

## Next Steps

1. [ ] Purchase SourceGuardian encoder
2. [ ] Set up license portal (separate Laravel app)
3. [ ] Integrate Paystack for payments
4. [ ] Create client onboarding workflow
5. [ ] Build installer with Inno Setup
6. [ ] Test full deployment cycle
7. [ ] Document client installation guide

## Questions to Decide

1. **Pricing:** Monthly vs Annual vs Lifetime?
2. **Grace Period:** How long after expiration before lockout?
3. **Features:** Different tiers with different features?
4. **Support:** Included or separate charge?
5. **Updates:** Included in subscription or separate?

---

*Last Updated: December 2025*
