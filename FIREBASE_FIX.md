# Firebase Credentials Missing - Fixed

## Problem
Offline booking was failing with 500 error:
```
Firebase credentials file not found at: /var/www/ltp/ltp-apis/storage/firebase-credentials.json
```

The FcmService constructor was throwing an exception when Firebase credentials file was missing, causing the entire booking request to fail.

## Root Cause
- Firebase credentials file (`firebase-credentials.json`) not deployed to production server
- FcmService was injected into BookingController constructor
- Missing file caused constructor to fail, preventing any booking operations

## Solution Applied ✅

### Made FcmService Optional
Updated `app/Services/FcmService.php` to:
1. **Gracefully handle missing credentials** - Log warning instead of throwing exception
2. **Add enabled flag** - Track if FCM is available
3. **Skip notifications when disabled** - Return false instead of crashing
4. **Still create notification records** - Database notifications work even without FCM

### Changes Made
```php
protected $enabled = false;

public function __construct()
{
    $credentialsPath = base_path(config('services.fcm.credentials_path'));
    
    if (!file_exists($credentialsPath)) {
        Log::warning('Firebase credentials file not found. FCM notifications disabled.');
        $this->enabled = false;
        return; // Don't throw exception
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

## Impact

### ✅ What Works Now
- Offline bookings work without Firebase
- Partial payments work
- Full payments work
- Pay on location works
- Database notifications still created
- WhatsApp notifications still work

### ⚠️ What's Disabled (Until Firebase Configured)
- Push notifications to mobile apps
- FCM token management
- Real-time notification delivery

## Deploying Firebase Credentials

### Step 1: Copy Credentials to Server
```bash
# From local machine
scp storage/firebase-credentials.json user@server:/var/www/ltp/ltp-apis/storage/

# On server, set permissions
chmod 600 /var/www/ltp/ltp-apis/storage/firebase-credentials.json
chown www-data:www-data /var/www/ltp/ltp-apis/storage/firebase-credentials.json
```

### Step 2: Verify .env Configuration
```env
FIREBASE_CREDENTIALS=storage/firebase-credentials.json
```

### Step 3: Restart Services
```bash
# Restart PHP-FPM
sudo systemctl restart php8.2-fpm

# Restart queue worker
sudo supervisorctl restart laravel-worker:*
```

### Step 4: Test
```bash
# Check logs
tail -f /var/www/ltp/ltp-apis/storage/logs/laravel.log

# Should see:
# "Firebase initialized successfully" (if enabled)
# OR
# "Firebase credentials file not found. FCM notifications disabled." (if missing)
```

## Testing Without Firebase

### Test Offline Booking
```json
POST /api/v1/owner/bookings/offline
{
  "turf_id": 4,
  "slot_ids": [221],
  "player_name": "Test Player",
  "player_phone": "8887888888",
  "booking_date": "2026-01-15",
  "start_time": "22:00:00",
  "end_time": "23:00:00",
  "amount": "1000.00",
  "payment_method": "cash",
  "payment_type": "partial",
  "paid_amount": 300
}
```

### Expected Response
```json
{
  "data": {
    "id": 17,
    "booking_number": "BK...",
    "booking_date": "2026-01-15",
    "start_time": "22:00:00",
    "end_time": "23:00:00",
    "amount": 1000,
    "paid_amount": 300,
    "pending_amount": 700,
    "payment_status": "pending",
    "status": "confirmed",
    ...
  }
}
```

## Logs to Monitor

### When Firebase is Disabled
```
[INFO] FCM disabled - skipping notification {"userId":1,"userType":"owner","title":"New Booking Received"}
```

### When Firebase is Enabled
```
[INFO] FCM notification sent successfully {"userId":1,"tokens":2,"success":true}
```

## Files Modified

1. **app/Services/FcmService.php**
   - Added `$enabled` flag
   - Made constructor non-throwing
   - Added enabled checks to all send methods
   - Graceful degradation when Firebase unavailable

## Security Notes

⚠️ **IMPORTANT**: Never commit `firebase-credentials.json` to git

The file contains:
- Private keys
- Service account credentials
- Project secrets

### Deployment Best Practices
1. Store credentials in secure vault (AWS Secrets Manager, etc.)
2. Deploy via secure CI/CD pipeline
3. Set restrictive file permissions (600)
4. Rotate credentials periodically
5. Monitor access logs

## Backward Compatibility

✅ Works with Firebase configured
✅ Works without Firebase configured
✅ No breaking changes
✅ Existing bookings unaffected
✅ Database notifications always work

## Future Improvements

1. **Add health check endpoint** - Check if FCM is enabled
2. **Admin notification** - Alert when FCM is disabled
3. **Fallback to SMS** - Send SMS when push fails
4. **Retry mechanism** - Queue failed notifications
5. **Metrics dashboard** - Track notification delivery rates
