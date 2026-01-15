# Deployment Checklist - LTP Backend

## ✅ Pre-Deployment Fixes Completed

### Duplicate Migrations Removed
- [x] Removed duplicate notifications table migration
- [x] Removed 2 duplicate personal_access_tokens migrations  
- [x] Removed duplicate settings table migration
- [x] Removed duplicate partial payment migration
- [x] Merged cancellation notification type into base migration
- [x] Updated notifications schema (read_at instead of is_read)

### New Migrations Added
- [x] `2025_01_27_000001_add_partial_to_payment_status.php` - Adds 'partial' payment status
- [x] `2025_01_27_000002_add_soft_deletes_to_turfs.php` - Adds soft deletes to turfs

### Code Updates
- [x] Added SoftDeletes trait to Turf model
- [x] Notification model uses correct column names (body, read_at)

## 🚀 Deployment Steps

### 1. Start PostgreSQL
```bash
# Windows (Run as Administrator)
net start postgresql-x64-14

# Linux/Mac
sudo systemctl start postgresql
# OR
sudo service postgresql start
```

### 2. Fresh Migration (If Clean Database)
```bash
cd "d:\Akash Wagh\LTP-Code-git-clone\ltp-apis"
php artisan migrate:fresh --seed
```

### 3. Run Migrations (If Existing Database)
```bash
cd "d:\Akash Wagh\LTP-Code-git-clone\ltp-apis"
php artisan migrate
```

### 4. Clear Caches
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

### 5. Start Services
```bash
# Terminal 1 - Laravel Server
php artisan serve

# Terminal 2 - Queue Worker (for FCM notifications)
php artisan queue:work --tries=3 --timeout=90
```

## 🧪 Post-Deployment Testing

### Test 1: Check Migrations Status
```bash
php artisan migrate:status
```
Expected: All migrations should show "Ran"

### Test 2: Test Offline Booking - Full Payment
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

### Test 3: Test Offline Booking - Partial Payment
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

### Test 4: Test Offline Booking - Pay on Location
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

### Test 5: Verify Database Schema
```sql
-- Check notifications table structure
\d notifications

-- Should have columns: id, user_id, user_type, title, body, data, type, read_at, created_at, updated_at

-- Check bookings payment_status enum
SELECT column_name, data_type, udt_name 
FROM information_schema.columns 
WHERE table_name = 'bookings' AND column_name = 'payment_status';

-- Check turfs has deleted_at
SELECT column_name 
FROM information_schema.columns 
WHERE table_name = 'turfs' AND column_name = 'deleted_at';
```

## ⚠️ Common Issues & Solutions

### Issue: "relation already exists"
**Solution**: Migration already ran. Check `php artisan migrate:status`

### Issue: "column does not exist"
**Solution**: Run `php artisan migrate` to add missing columns

### Issue: "constraint violation"
**Solution**: Check enum values match code expectations

### Issue: Queue jobs not processing
**Solution**: Ensure queue worker is running: `php artisan queue:work`

## 📊 Database Schema Summary

### Bookings Table - Payment Fields
- `payment_mode`: online, cash, upi, pay_on_turf
- `payment_status`: pending, success, failed, refunded, **partial** ✨
- `paid_amount`: Amount already paid
- `pending_amount`: Amount still pending
- `advance_percentage`: Percentage paid upfront

### Notifications Table - Updated Schema
- `title`: Notification title
- `body`: Notification message (was 'message')
- `read_at`: Timestamp when read (was 'is_read' boolean)
- `type`: booking, payment, review, promotional, reminder, general, **cancellation** ✨

### Turfs Table - Soft Deletes
- `deleted_at`: Timestamp for soft deletion ✨

## 🎯 Success Criteria

- [ ] All migrations run successfully
- [ ] No duplicate table errors
- [ ] Offline booking works with all payment types
- [ ] Notifications created with correct schema
- [ ] Queue worker processing FCM notifications
- [ ] OTP verification works (999999 in dev mode)
- [ ] Logs accessible without permission errors

## 📝 Notes

- OTP is hardcoded to `999999` for development
- FCM notifications require queue worker running
- WhatsApp notifications are non-blocking (failures logged)
- Commission rate defaults to 5% if not set on owner
