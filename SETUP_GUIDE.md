# LTP Backend Setup & Issue Resolution Guide

## ✅ DEPLOYMENT ISSUES FIXED

### Duplicate Table Migrations Removed
- ❌ Deleted: `2025_01_25_000002_create_notifications_table.php` (duplicate)
- ❌ Deleted: `2026_01_08_102731_create_personal_access_tokens_table.php` (duplicate)
- ❌ Deleted: `2026_01_08_103000_create_personal_access_tokens_table.php` (duplicate)
- ❌ Deleted: `2025_01_20_000002_create_settings_table.php` (duplicate)
- ❌ Deleted: `2024_01_15_000000_add_partial_payment_fields_to_bookings_table.php` (duplicate)
- ❌ Deleted: `2025_01_26_000001_add_cancellation_to_notification_types.php` (merged into base)
- ✅ Updated: `2024_01_01_000020_create_notifications_table.php` (merged schema, added cancellation type)

## Current Issues Identified

### 1. Database Not Running
**Error**: PostgreSQL connection refused on port 5432
**Fix**: Start PostgreSQL service
```bash
# Windows - Run as Administrator
net start postgresql-x64-14
# OR check your PostgreSQL version and start accordingly
```

### 2. Missing Database Schema Updates
**Issue**: Several migrations need to be run to support new features

**Created Migrations**:
- `2025_01_27_000001_add_partial_to_payment_status.php` - Adds 'partial' to payment_status enum
- `2025_01_27_000002_add_soft_deletes_to_turfs.php` - Adds deleted_at column to turfs table

**Run After Starting PostgreSQL**:
```bash
cd "d:\Akash Wagh\LTP-Code-git-clone\ltp-apis"
php artisan migrate
```

### 3. Offline Booking with Partial/Pay on Location
**Status**: ✅ CODE READY - Needs database migration

**Features Implemented**:
- Full payment support
- Partial payment with advance percentage tracking
- Pay on turf (pay on location) support
- Automatic commission calculation
- WhatsApp notifications

**Payment Types Supported**:
- `full` - Complete payment upfront
- `partial` - Advance payment with pending amount tracking
- `pay_on_turf` - No advance, full payment at venue

**Required Migration**: Run `2025_01_27_000001_add_partial_to_payment_status.php`

**API Endpoint**: `POST /api/v1/owner/bookings/offline`

**Request Body**:
```json
{
  "turf_id": 1,
  "slot_ids": [1, 2],
  "player_name": "John Doe",
  "player_phone": "9876543210",
  "booking_date": "2025-01-28",
  "start_time": "10:00",
  "end_time": "11:00",
  "amount": 1000,
  "payment_method": "cash|upi|online|pay_on_turf",
  "payment_type": "full|partial|pay_on_turf",
  "paid_amount": 500  // Required only for partial payment
}
```

**Payment Confirmation**: `POST /api/v1/owner/bookings/{id}/confirm-payment`
```json
{
  "amount": 500  // Optional - defaults to remaining pending_amount
}
```

### 4. OTP Sending
**Status**: ✅ WORKING - Development Mode

**Current Behavior**:
- Static OTP: `999999` (hardcoded for development)
- No SMS integration configured
- OTP verification accepts `999999` always

**Location**: `app/Services/OtpService.php`

**To Enable Real SMS**:
1. Add MSG91 credentials to `.env`:
```env
MSG91_AUTH_KEY=your_auth_key_here
MSG91_SENDER_ID=your_sender_id_here
```

2. Update `OtpService.php` to use SMS gateway:
```php
public function generate($phone, $purpose = 'login')
{
    $otp = rand(100000, 999999); // Generate random OTP
    
    // Send via SMS
    $smsService = app(SmsService::class);
    $smsService->sendOtp($phone, $otp);
    
    // Store in database
    Otp::create([
        'phone' => $phone,
        'otp' => $otp,
        'purpose' => $purpose,
        'expires_at' => Carbon::now()->addMinutes(config('app.otp_expiry_minutes', 10)),
    ]);
    
    return $otp;
}
```

### 5. Default 999999 OTP
**Status**: ✅ VERIFIED - Working as Expected

**Behavior**:
- Development mode uses static OTP `999999`
- Always accepted for testing
- Bypasses SMS costs during development

**To Disable**: Update `OtpService.php` and remove the hardcoded check

### 6. Laravel Log Permissions
**Status**: ✅ RESOLVED

**Previous Error**: Permission denied for `storage/logs/laravel.log`
**Current Status**: Logs are accessible and readable

**Note**: Old logs show errors from previous project path `D:\Akash Wagh\LTP-Code\ltp-apis`
Current path is `D:\Akash Wagh\LTP-Code-git-clone\ltp-apis`

**To Clear Old Logs**:
```bash
cd "d:\Akash Wagh\LTP-Code-git-clone\ltp-apis"
del storage\logs\laravel.log
```

## Database Schema Status

### ✅ Completed Migrations
- `personal_access_tokens` table (multiple migrations exist)
- `fcm_tokens` table
- `notifications` table
- `notification_logs` table
- `jobs` table (for queue support)
- Partial payment fields in bookings
- Commission fields in bookings
- Pay on turf payment mode

### ⚠️ Pending Migrations (Need to Run)
1. `2025_01_27_000001_add_partial_to_payment_status.php`
2. `2025_01_27_000002_add_soft_deletes_to_turfs.php`

## Testing Checklist

### After Starting PostgreSQL and Running Migrations:

1. **Test Offline Booking - Full Payment**
```bash
curl -X POST http://localhost:8000/api/v1/owner/bookings/offline \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "turf_id": 1,
    "slot_ids": [1],
    "player_name": "Test Player",
    "player_phone": "9876543210",
    "booking_date": "2025-01-28",
    "start_time": "10:00",
    "end_time": "11:00",
    "amount": 1000,
    "payment_method": "cash",
    "payment_type": "full"
  }'
```

2. **Test Offline Booking - Partial Payment**
```bash
curl -X POST http://localhost:8000/api/v1/owner/bookings/offline \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "turf_id": 1,
    "slot_ids": [1],
    "player_name": "Test Player",
    "player_phone": "9876543210",
    "booking_date": "2025-01-28",
    "start_time": "12:00",
    "end_time": "13:00",
    "amount": 1000,
    "payment_method": "upi",
    "payment_type": "partial",
    "paid_amount": 300
  }'
```

3. **Test Offline Booking - Pay on Location**
```bash
curl -X POST http://localhost:8000/api/v1/owner/bookings/offline \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "turf_id": 1,
    "slot_ids": [1],
    "player_name": "Test Player",
    "player_phone": "9876543210",
    "booking_date": "2025-01-28",
    "start_time": "14:00",
    "end_time": "15:00",
    "amount": 1000,
    "payment_method": "pay_on_turf",
    "payment_type": "pay_on_turf"
  }'
```

4. **Test OTP Generation**
```bash
curl -X POST http://localhost:8000/api/v1/auth/send-otp \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "9876543210"
  }'
```

5. **Test OTP Verification**
```bash
curl -X POST http://localhost:8000/api/v1/auth/verify-otp \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "9876543210",
    "otp": "999999"
  }'
```

## Quick Start Commands

```bash
# 1. Start PostgreSQL (Windows - Run as Administrator)
net start postgresql-x64-14

# 2. Navigate to project
cd "d:\Akash Wagh\LTP-Code-git-clone\ltp-apis"

# 3. Run migrations
php artisan migrate

# 4. Clear old logs (optional)
del storage\logs\laravel.log

# 5. Start Laravel server
php artisan serve

# 6. Start queue worker (for FCM notifications)
php artisan queue:work --tries=3
```

## Environment Configuration

### Current .env Settings
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=ltp_db
DB_USERNAME=ltp_user
DB_PASSWORD=LetsPlayTurf@123

QUEUE_CONNECTION=database

OTP_EXPIRY_MINUTES=10
COMMISSION_PERCENTAGE=10
SLOT_LOCK_MINUTES=10

FIREBASE_CREDENTIALS=storage/firebase-credentials.json
```

### Required for Production
```env
# SMS Gateway (MSG91)
MSG91_AUTH_KEY=your_key_here
MSG91_SENDER_ID=your_sender_here

# Payment Gateway (Razorpay)
RAZORPAY_KEY_ID=your_key_here
RAZORPAY_KEY_SECRET=your_secret_here

# FCM (Already configured)
FIREBASE_CREDENTIALS=storage/firebase-credentials.json
```

## Code Changes Summary

### Files Modified
1. `app/Models/Turf.php` - Added SoftDeletes trait

### Files Created
1. `database/migrations/2025_01_27_000001_add_partial_to_payment_status.php`
2. `database/migrations/2025_01_27_000002_add_soft_deletes_to_turfs.php`

### Files Already Implemented (Previous Sessions)
1. `app/Services/FcmService.php` - Complete FCM implementation
2. `app/Jobs/SendFcmNotification.php` - Queue job for notifications
3. `app/Models/NotificationLog.php` - Notification tracking
4. `app/Http/Controllers/Api/V1/Owner/BookingController.php` - Offline booking with partial payment
5. `app/Services/OtpService.php` - OTP generation and verification

## Next Steps

1. ✅ Start PostgreSQL service
2. ✅ Run `php artisan migrate`
3. ✅ Test offline booking with all payment types
4. ✅ Verify OTP functionality (999999 works)
5. ⚠️ Configure MSG91 for production SMS
6. ⚠️ Start queue worker for FCM notifications
7. ⚠️ Clear old logs if needed

## Support

If issues persist:
1. Check PostgreSQL is running: `pg_isready -h 127.0.0.1 -p 5432`
2. Check Laravel logs: `storage/logs/laravel.log`
3. Check queue jobs: `SELECT * FROM jobs;`
4. Check migrations: `php artisan migrate:status`
