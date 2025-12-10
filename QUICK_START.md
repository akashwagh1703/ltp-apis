# Quick Start Guide - Let's Turf Play APIs

## 🚀 Get Started in 5 Minutes

### Step 1: Install Laravel Dependencies
```bash
cd ltp-apis
composer install
```

### Step 2: Setup Environment
```bash
cp .env.example .env
php artisan key:generate
```

### Step 3: Configure Database
Edit `.env` file:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ltp_database
DB_USERNAME=root
DB_PASSWORD=your_password
```

### Step 4: Run Migrations & Seeders
```bash
php artisan migrate
php artisan db:seed
```

### Step 5: Start Server
```bash
php artisan serve
```

API will be available at: `http://localhost:8000/api`

---

## 🧪 Test the API

### 1. Admin Login
```bash
curl -X POST http://localhost:8000/api/v1/admin/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@letsturf.com",
    "password": "admin123"
  }'
```

### 2. Get Dashboard Stats
```bash
curl -X GET http://localhost:8000/api/v1/admin/dashboard/stats \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 3. Player Send OTP
```bash
curl -X POST http://localhost:8000/api/v1/player/auth/send-otp \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "9876543210"
  }'
```

---

## 📱 Default Credentials

### Admin Portal
```
Email: admin@letsturf.com
Password: admin123
```

### Player/Owner Apps
```
Use any phone number
OTP: 123456 (in development mode)
```

---

## 🔧 Optional Configuration

### SMS Gateway (MSG91)
```env
SMS_GATEWAY=msg91
MSG91_AUTH_KEY=your_auth_key
MSG91_SENDER_ID=your_sender_id
```

### Payment Gateway (Razorpay)
```env
PAYMENT_GATEWAY=razorpay
RAZORPAY_KEY_ID=your_key_id
RAZORPAY_KEY_SECRET=your_key_secret
```

### Push Notifications (FCM)
```env
FCM_SERVER_KEY=your_fcm_server_key
```

### AWS S3 (File Storage)
```env
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
AWS_DEFAULT_REGION=ap-south-1
AWS_BUCKET=your_bucket_name
```

---

## 📚 Documentation

- **API Documentation**: See `API_DOCUMENTATION.md`
- **Setup Guide**: See `SETUP_GUIDE.md`
- **Project Summary**: See `PROJECT_SUMMARY.md`
- **Implementation Status**: See `IMPLEMENTATION_STATUS.md`

---

## 🎯 Key Endpoints

### Admin
- `POST /v1/admin/login` - Admin login
- `GET /v1/admin/dashboard/stats` - Dashboard stats
- `GET /v1/admin/turfs` - List turfs
- `POST /v1/admin/owners` - Create owner
- `GET /v1/admin/bookings` - List bookings
- `POST /v1/admin/payouts/generate` - Generate payout

### Player
- `POST /v1/player/auth/send-otp` - Send OTP
- `POST /v1/player/auth/verify-otp` - Verify OTP
- `GET /v1/player/turfs` - Browse turfs
- `GET /v1/player/slots/available` - Available slots
- `POST /v1/player/bookings` - Create booking

### Owner
- `POST /v1/owner/auth/send-otp` - Send OTP
- `POST /v1/owner/auth/verify-otp` - Verify OTP
- `GET /v1/owner/dashboard/stats` - Dashboard stats
- `POST /v1/owner/slots/generate` - Generate slots
- `POST /v1/owner/bookings/offline` - Offline booking

---

## 🐛 Troubleshooting

### Database Connection Error
```bash
# Check MySQL is running
mysql -u root -p

# Create database
CREATE DATABASE ltp_database;
```

### Permission Errors
```bash
# Fix storage permissions
chmod -R 775 storage bootstrap/cache
```

### Composer Issues
```bash
# Update composer
composer self-update
composer update
```

### Migration Errors
```bash
# Reset database
php artisan migrate:fresh --seed
```

---

## 📞 Support

For issues or questions:
1. Check `API_DOCUMENTATION.md`
2. Review `SETUP_GUIDE.md`
3. Check Laravel logs: `storage/logs/laravel.log`

---

## ✅ Verification Checklist

After setup, verify:
- [ ] Server is running on port 8000
- [ ] Admin login works
- [ ] Database has 22 tables
- [ ] Default admin user exists
- [ ] Settings are seeded
- [ ] API returns JSON responses

---

## 🎉 You're Ready!

Your Let's Turf Play backend API is now running and ready for:
- Frontend integration
- Mobile app development
- Testing
- Production deployment

**Happy Coding! 🚀**
