# Let's Turf Play - API Documentation

## Base URL
```
http://localhost:8000/api
```

## Authentication
All authenticated endpoints require Bearer token in header:
```
Authorization: Bearer {token}
```

---

## Admin APIs

### Authentication

#### Login
```http
POST /v1/admin/login
Content-Type: application/json

{
  "email": "admin@letsturf.com",
  "password": "admin123"
}

Response:
{
  "token": "...",
  "admin": {...}
}
```

#### Logout
```http
POST /v1/admin/logout
Authorization: Bearer {token}
```

### Dashboard

#### Get Stats
```http
GET /v1/admin/dashboard/stats
Authorization: Bearer {token}

Response:
{
  "total_turfs": 50,
  "active_turfs": 45,
  "total_owners": 20,
  "total_players": 500,
  "total_bookings": 1000,
  "today_bookings": 25,
  "total_revenue": 50000,
  "today_revenue": 2500
}
```

### Turfs

#### List Turfs
```http
GET /v1/admin/turfs?status=active&owner_id=1&search=cricket
Authorization: Bearer {token}
```

#### Create Turf
```http
POST /v1/admin/turfs
Authorization: Bearer {token}
Content-Type: application/json

{
  "owner_id": 1,
  "name": "Green Field Cricket Turf",
  "description": "Professional cricket turf",
  "address_line1": "123 Main St",
  "city": "Mumbai",
  "state": "Maharashtra",
  "pincode": "400001",
  "size": "100x50",
  "capacity": 22,
  "opening_time": "06:00",
  "closing_time": "22:00",
  "slot_duration": 60,
  "images": ["url1", "url2"],
  "amenities": [{"name": "Parking", "icon": "parking"}],
  "pricing": [
    {"day_type": "weekday", "time_slot": "morning", "price": 500},
    {"day_type": "weekend", "time_slot": "evening", "price": 800}
  ]
}
```

### Owners

#### List Owners
```http
GET /v1/admin/owners?status=active&search=john
Authorization: Bearer {token}
```

#### Create Owner
```http
POST /v1/admin/owners
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "phone": "9876543210",
  "password": "password123",
  "pan_number": "ABCDE1234F",
  "bank_name": "HDFC Bank",
  "account_holder_name": "John Doe",
  "account_number": "1234567890",
  "ifsc_code": "HDFC0001234"
}
```

### Bookings

#### List Bookings
```http
GET /v1/admin/bookings?status=completed&turf_id=1&date_from=2024-01-01&date_to=2024-01-31
Authorization: Bearer {token}
```

#### Cancel Booking
```http
POST /v1/admin/bookings/{id}/cancel
Authorization: Bearer {token}
Content-Type: application/json

{
  "reason": "Customer request"
}
```

### Payouts

#### Generate Payout
```http
POST /v1/admin/payouts/generate
Authorization: Bearer {token}
Content-Type: application/json

{
  "owner_id": 1,
  "period_start": "2024-01-01",
  "period_end": "2024-01-31"
}
```

#### Process Payout
```http
POST /v1/admin/payouts/{id}/process
Authorization: Bearer {token}
```

### Reports

#### Bookings Report
```http
GET /v1/admin/reports/bookings?date_from=2024-01-01&date_to=2024-01-31
Authorization: Bearer {token}
```

#### Turf-wise Report
```http
GET /v1/admin/reports/turf-wise?date_from=2024-01-01&date_to=2024-01-31
Authorization: Bearer {token}
```

#### Owner-wise Report
```http
GET /v1/admin/reports/owner-wise?date_from=2024-01-01&date_to=2024-01-31
Authorization: Bearer {token}
```

#### Payment Mode Report
```http
GET /v1/admin/reports/payment-mode?date_from=2024-01-01&date_to=2024-01-31
Authorization: Bearer {token}
```

---

## Player APIs

### Authentication

#### Send OTP
```http
POST /v1/player/auth/send-otp
Content-Type: application/json

{
  "phone": "9876543210"
}
```

#### Verify OTP
```http
POST /v1/player/auth/verify-otp
Content-Type: application/json

{
  "phone": "9876543210",
  "otp": "123456"
}

Response:
{
  "token": "...",
  "player": {...}
}
```

### Turfs

#### List Turfs
```http
GET /v1/player/turfs?city=Mumbai&search=cricket
```

#### Featured Turfs
```http
GET /v1/player/turfs/featured
```

#### Turf Details
```http
GET /v1/player/turfs/{id}
```

### Slots

#### Available Slots
```http
GET /v1/player/slots/available?turf_id=1&date=2024-01-15
```

### Bookings

#### Create Booking
```http
POST /v1/player/bookings
Authorization: Bearer {token}
Content-Type: application/json

{
  "turf_slot_id": 123
}

Response:
{
  "booking": {...},
  "payment_order": {
    "order_id": "order_xxx",
    "amount": 500
  }
}
```

#### Confirm Payment
```http
POST /v1/player/bookings/{id}/confirm-payment
Authorization: Bearer {token}
Content-Type: application/json

{
  "order_id": "order_xxx",
  "payment_id": "pay_xxx",
  "signature": "xxx",
  "method": "upi"
}
```

#### Cancel Booking
```http
POST /v1/player/bookings/{id}/cancel
Authorization: Bearer {token}
```

#### My Bookings
```http
GET /v1/player/bookings
Authorization: Bearer {token}
```

### Reviews

#### Create Review
```http
POST /v1/player/reviews
Authorization: Bearer {token}
Content-Type: application/json

{
  "booking_id": 123,
  "rating": 5,
  "comment": "Great experience!"
}
```

### Notifications

#### List Notifications
```http
GET /v1/player/notifications
Authorization: Bearer {token}
```

#### Mark as Read
```http
POST /v1/player/notifications/{id}/read
Authorization: Bearer {token}
```

---

## Owner APIs

### Authentication

#### Send OTP
```http
POST /v1/owner/auth/send-otp
Content-Type: application/json

{
  "phone": "9876543210"
}
```

#### Verify OTP
```http
POST /v1/owner/auth/verify-otp
Content-Type: application/json

{
  "phone": "9876543210",
  "otp": "123456"
}

Response:
{
  "token": "...",
  "owner": {...}
}
```

### Dashboard

#### Get Stats
```http
GET /v1/owner/dashboard/stats
Authorization: Bearer {token}
```

#### Recent Bookings
```http
GET /v1/owner/dashboard/recent-bookings
Authorization: Bearer {token}
```

### Turfs

#### My Turfs
```http
GET /v1/owner/turfs
Authorization: Bearer {token}
```

#### Request Update
```http
POST /v1/owner/turfs/{id}/request-update
Authorization: Bearer {token}
Content-Type: application/json

{
  "updates": {
    "opening_time": "05:00",
    "closing_time": "23:00"
  }
}
```

### Slots

#### Generate Slots
```http
POST /v1/owner/slots/generate
Authorization: Bearer {token}
Content-Type: application/json

{
  "turf_id": 1,
  "date": "2024-01-15"
}
```

#### List Slots
```http
GET /v1/owner/slots?turf_id=1&date=2024-01-15
Authorization: Bearer {token}
```

### Bookings

#### List Bookings
```http
GET /v1/owner/bookings?status=confirmed&turf_id=1&date=2024-01-15
Authorization: Bearer {token}
```

#### Create Offline Booking
```http
POST /v1/owner/bookings/offline
Authorization: Bearer {token}
Content-Type: application/json

{
  "turf_id": 1,
  "turf_slot_id": 123,
  "player_name": "John Doe",
  "player_phone": "9876543210"
}
```

#### Booking Stats
```http
GET /v1/owner/bookings/stats
Authorization: Bearer {token}
```

### Payouts

#### My Payouts
```http
GET /v1/owner/payouts
Authorization: Bearer {token}
```

#### Payout Details
```http
GET /v1/owner/payouts/{id}
Authorization: Bearer {token}
```

---

## Response Formats

### Success Response
```json
{
  "data": {...},
  "message": "Success"
}
```

### Error Response
```json
{
  "message": "Error message",
  "errors": {
    "field": ["Validation error"]
  }
}
```

### Pagination Response
```json
{
  "data": [...],
  "links": {...},
  "meta": {
    "current_page": 1,
    "total": 100
  }
}
```

---

## Status Codes

- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `500` - Server Error

---

## Rate Limiting

- 60 requests per minute for authenticated users
- 30 requests per minute for guest users

---

## Testing

### Default Admin Credentials
```
Email: admin@letsturf.com
Password: admin123
```

### Test OTP
In development, use OTP: `123456` for any phone number
