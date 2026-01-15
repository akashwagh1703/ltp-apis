# URGENT: Deploy FcmService Fix

## Problem
The server is still running OLD code that throws exception when Firebase credentials are missing.

## Solution
Deploy the updated `FcmService.php` file to the server.

## Option 1: Git Pull (Recommended)

```bash
# On server
cd /var/www/ltp/ltp-apis
git pull origin main  # or your branch name
php artisan config:clear
php artisan cache:clear
sudo systemctl restart php8.2-fpm
```

## Option 2: Manual File Upload

### Step 1: Upload File
```bash
# From your local machine
scp d:\Akash\ Wagh\LTP-Code-git-clone\ltp-apis\app\Services\FcmService.php user@your-server:/var/www/ltp/ltp-apis/app/Services/
```

### Step 2: Clear Cache on Server
```bash
# SSH into server
ssh user@your-server

# Clear caches
cd /var/www/ltp/ltp-apis
php artisan config:clear
php artisan cache:clear
php artisan route:clear

# Restart PHP-FPM
sudo systemctl restart php8.2-fpm
```

## Option 3: Quick Fix via SSH (Copy-Paste)

```bash
# SSH into server and run this
ssh user@your-server

# Backup old file
cd /var/www/ltp/ltp-apis
cp app/Services/FcmService.php app/Services/FcmService.php.backup

# Edit the file
nano app/Services/FcmService.php
```

Then replace lines 15-26 with:

```php
    protected $messaging;
    protected $enabled = false;

    public function __construct()
    {
        $credentialsPath = base_path(config('services.fcm.credentials_path'));
        
        if (!file_exists($credentialsPath)) {
            Log::warning('Firebase credentials file not found. FCM notifications disabled.');
            $this->enabled = false;
            return;
        }
        
        try {
            $factory = (new Factory)->withServiceAccount($credentialsPath);
            $this->messaging = $factory->createMessaging();
            $this->enabled = true;
        } catch (\Exception $e) {
            Log::error('Failed to initialize Firebase: ' . $e->getMessage());
            $this->enabled = false;
        }
    }
```

And add at the start of `sendToUser` method (around line 35):

```php
    public function sendToUser($userId, $userType, $title, $body, $data = [], $type = 'general')
    {
        if (!$this->enabled) {
            Log::info('FCM disabled - skipping notification', compact('userId', 'userType', 'title'));
            return false;
        }
        
        // ... rest of the method
```

And add at the start of `sendToAll` method (around line 60):

```php
    public function sendToAll($userType, $title, $body, $data = [], $type = 'general')
    {
        if (!$this->enabled) {
            Log::info('FCM disabled - skipping broadcast notification', compact('userType', 'title'));
            return false;
        }
        
        // ... rest of the method
```

Save (Ctrl+O, Enter, Ctrl+X) and restart:

```bash
php artisan config:clear
php artisan cache:clear
sudo systemctl restart php8.2-fpm
```

## Verify Fix

Test the bookings endpoint:
```bash
curl -X GET https://api.playltp.in/api/v1/owner/bookings \
  -H "Authorization: Bearer YOUR_TOKEN"
```

Should return bookings list without error.

## If Still Not Working

Check if opcache is enabled and clear it:
```bash
# On server
sudo systemctl restart php8.2-fpm
# OR
php artisan optimize:clear
```

## Alternative: Disable FcmService Injection Temporarily

If deployment is delayed, you can temporarily remove FcmService from BookingController constructor:

Edit `/var/www/ltp/ltp-apis/app/Http/Controllers/Api/V1/Owner/BookingController.php`:

Change:
```php
public function __construct(SmsService $smsService, FcmService $fcmService)
{
    $this->smsService = $smsService;
    $this->fcmService = $fcmService;
}
```

To:
```php
public function __construct(SmsService $smsService)
{
    $this->smsService = $smsService;
}
```

And update methods that use `$this->fcmService` to:
```php
try {
    $fcmService = app(FcmService::class);
    $fcmService->sendBookingNotification($booking, false);
} catch (\Exception $e) {
    \Log::warning('FCM notification failed: ' . $e->getMessage());
}
```

This way FcmService is only instantiated when actually needed, not on every request.
