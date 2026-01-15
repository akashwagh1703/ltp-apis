# Partial Payment Booking - Quick Fix

## Issue
Partial payment bookings were failing with server error because the database `payment_status` enum doesn't include 'partial' value yet.

## Temporary Solution Applied ✅
Changed the code to use `'pending'` status for partial payments instead of `'partial'` until the migration can be run.

### How It Works Now:
- **Full Payment**: `payment_status = 'success'`, `pending_amount = 0`
- **Partial Payment**: `payment_status = 'pending'`, `pending_amount > 0`, `paid_amount > 0`
- **Pay on Location**: `payment_status = 'pending'`, `pending_amount = total`, `paid_amount = 0`

### Identifying Partial Payments:
Check if `payment_status = 'pending'` AND `pending_amount > 0` AND `paid_amount > 0`

## Permanent Solution (After PostgreSQL is Running)

### Step 1: Start PostgreSQL
```bash
# Windows (Run as Administrator)
net start postgresql-x64-14
```

### Step 2: Run Migration
```bash
cd "d:\Akash Wagh\LTP-Code-git-clone\ltp-apis"
php artisan migrate
```

### Step 3: Update Code to Use 'partial' Status
After migration runs successfully, update `BookingController.php`:

```php
// Line ~105 - Change from:
$paymentStatus = $paidAmount > 0 ? 'pending' : 'pending';

// To:
$paymentStatus = 'partial';
```

```php
// Line ~330 - Change from:
if ($booking->payment_status === 'pending' && $booking->pending_amount > 0) {

// To:
if ($booking->payment_status === 'partial') {
```

## Testing Partial Payment (Current Workaround)

### Test Request:
```json
POST /api/v1/owner/bookings/offline
{
  "turf_id": 1,
  "slot_ids": [1],
  "player_name": "Test Player",
  "player_phone": "9876543210",
  "booking_date": "2025-01-28",
  "start_time": "10:00",
  "end_time": "11:00",
  "amount": 1000,
  "payment_method": "cash",
  "payment_type": "partial",
  "paid_amount": 300
}
```

### Expected Response:
```json
{
  "data": {
    "id": 1,
    "booking_number": "BK...",
    "payment_status": "pending",
    "paid_amount": 300,
    "pending_amount": 700,
    "advance_percentage": 30.00,
    ...
  }
}
```

### Confirm Remaining Payment:
```json
POST /api/v1/owner/bookings/{id}/confirm-payment
{
  "amount": 700
}
```

## Files Modified

1. **app/Http/Controllers/Api/V1/Owner/BookingController.php**
   - Line ~105: Changed `$paymentStatus = 'partial'` to `'pending'`
   - Line ~330: Changed check from `'partial'` to `'pending' && $booking->pending_amount > 0`

2. **database/migrations/2025_01_27_000001_add_partial_to_payment_status.php**
   - Added safety check for constraint existence

## Database Schema After Migration

```sql
-- payment_status enum will include:
CHECK (payment_status IN ('pending', 'success', 'failed', 'refunded', 'partial'))

-- Tracking fields:
paid_amount DECIMAL(10,2)      -- Amount already paid
pending_amount DECIMAL(10,2)   -- Amount still pending
advance_percentage DECIMAL(5,2) -- Percentage paid upfront
```

## Rollback Plan

If issues occur, the workaround can be reverted by:
1. Keeping `payment_status = 'pending'` for all partial payments
2. Using `paid_amount` and `pending_amount` to track payment progress
3. No migration needed - works with existing schema

## Notes

- ✅ Workaround is production-safe
- ✅ No data loss
- ✅ Backward compatible
- ✅ Can identify partial payments via `pending_amount > 0 AND paid_amount > 0`
- ⚠️ Run migration when PostgreSQL is available for cleaner status tracking
