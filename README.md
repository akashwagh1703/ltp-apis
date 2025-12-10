# Let's Turf Play - Backend APIs

Complete Laravel backend API for Let's Turf Play platform with Admin, Player, and Owner applications.

## 🚀 Tech Stack

- Laravel 11
- PHP 8.2+
- MySQL 8.0+
- Redis (for caching)
- Laravel Sanctum (Authentication)

## 📦 Installation

```bash
# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Seed database
php artisan db:seed

# Start server
php artisan serve
```

## 🔌 API Endpoints

### Base URL
```
http://localhost:8000/api/v1
```

### Admin APIs
- Authentication: `/api/v1/admin/login`
- Dashboard: `/api/v1/admin/dashboard/stats`
- Turfs: `/api/v1/admin/turfs`
- Owners: `/api/v1/admin/owners`
- Bookings: `/api/v1/admin/bookings`
- Payouts: `/api/v1/admin/payouts`
- Reports: `/api/v1/admin/reports/*`

### Player APIs
- Authentication: `/api/v1/player/send-otp`
- Home: `/api/v1/player/home`
- Turfs: `/api/v1/player/turfs`
- Bookings: `/api/v1/player/bookings`
- Payments: `/api/v1/player/payments/*`

### Owner APIs
- Authentication: `/api/v1/owner/send-otp`
- Dashboard: `/api/v1/owner/dashboard/stats`
- Turfs: `/api/v1/owner/turfs`
- Slots: `/api/v1/owner/turfs/{id}/slots`
- Bookings: `/api/v1/owner/bookings`
- Offline Booking: `/api/v1/owner/bookings/offline`

## 🔐 Authentication

All protected routes require Bearer token:
```
Authorization: Bearer {token}
```

## 📊 Database Schema

- 22 Tables
- Complete relationships
- Indexes for performance
- Soft deletes where needed

## 🎯 Features

- ✅ API Versioning (v1)
- ✅ JWT Authentication
- ✅ OTP System
- ✅ Payment Integration (Razorpay)
- ✅ File Upload
- ✅ Activity Logging
- ✅ Rate Limiting
- ✅ Error Handling
- ✅ Validation
- ✅ API Resources

## 📝 Documentation

Full API documentation available at: `/api/documentation`

## 🧪 Testing

```bash
php artisan test
```

## 📞 Support

For issues, contact: support@letsturf.com
