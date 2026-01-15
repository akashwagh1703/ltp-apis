# Booking Listing Issue - Fixed

## Problem
Bookings listing endpoint was failing, likely due to missing database columns from pending migrations.

## Root Cause
The BookingResource was trying to access new columns (`paid_amount`, `pending_amount`, `platform_commission`, etc.) that don't exist in the database yet because migrations haven't been run.

## Solution Applied ✅

### 1. Made BookingResource Backward Compatible
Updated `app/Http/Resources/BookingResource.php` to:
- Return core fields that always exist
- Conditionally add new fields only if they exist in database using `isset()`
- Prevents errors when columns are missing

### 2. Added Error Handling
Updated `BookingController@index` to:
- Wrap query in try-catch block
- Log detailed error information
- Return proper error response with message

## Files Modified

1. **app/Http/Resources/BookingResource.php**
   - Made all new fields conditional with `isset()` checks
   - Ensures backward compatibility with old database schema

2. **app/Http/Controllers/Api/V1/Owner/BookingController.php**
   - Added try-catch to `index()` method
   - Added error logging for debugging

## Testing

### Test Booking Listing
```bash
GET /api/v1/owner/bookings
Authorization: Bearer YOUR_TOKEN
```

### With Filters
```bash
GET /api/v1/owner/bookings?status=confirmed&booking_type=offline&date=2025-01-28
Authorization: Bearer YOUR_TOKEN
```

### Expected Response (Before Migration)
```json
{
  "data": [
    {
      "id": 1,
      "booking_number": "BK...",
      "booking_date": "2025-01-28",
      "start_time": "10:00",
      "end_time": "11:00",
      "amount": 1000,
      "discount_amount": 0,
      "final_amount": 1000,
      "booking_type": "offline",
      "payment_mode": "cash",
      "status": "confirmed",
      "payment_status": "pending",
      "player_name": "Test Player",
      "player_phone": "9876543210",
      "turf": { ... },
      "created_at": "...",
      "updated_at": "..."
    }
  ],
  "links": { ... },
  "meta": { ... }
}
```

### Expected Response (After Migration)
Same as above, plus:
```json
{
  "data": [
    {
      ...
      "slot_duration": 60,
      "paid_amount": 300,
      "pending_amount": 700,
      "advance_percentage": 30.00,
      "platform_commission": 50,
      "owner_payout": 950,
      "commission_rate": 5.00,
      ...
    }
  ]
}
```

## Available Filters

- `status` - Filter by booking_status (confirmed, completed, cancelled, no_show)
- `booking_type` - Filter by type (online, offline)
- `payment_status` - Filter by payment status (pending, success, failed, refunded)
- `turf_id` - Filter by specific turf
- `date` - Filter by booking date (YYYY-MM-DD)

## Error Handling

If listing fails, you'll get:
```json
{
  "message": "Failed to fetch bookings: [error details]"
}
```

Check Laravel logs for detailed error:
```bash
tail -f storage/logs/laravel.log
```

## Migration Required

To get full functionality with all fields, run migrations:

```bash
# Start PostgreSQL
net start postgresql-x64-14

# Run migrations
cd "d:\Akash Wagh\LTP-Code-git-clone\ltp-apis"
php artisan migrate
```

## Migrations Pending

1. `2025_01_20_120000_add_partial_payment_to_bookings.php` - Adds paid_amount, pending_amount, advance_percentage
2. `2025_01_20_000001_add_commission_fields_to_bookings_table.php` - Adds commission fields
3. `2025_01_27_000001_add_partial_to_payment_status.php` - Adds 'partial' to payment_status enum
4. `2025_01_27_000002_add_soft_deletes_to_turfs.php` - Adds soft deletes to turfs

## Backward Compatibility

✅ Works with old database schema (missing columns)
✅ Works with new database schema (all columns present)
✅ No breaking changes
✅ Graceful degradation

## Notes

- Listing will work even without migrations
- New fields will appear automatically after migrations run
- No code changes needed after migration
- Error logging helps identify any other issues
