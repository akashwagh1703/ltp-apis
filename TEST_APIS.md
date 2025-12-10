# API Testing Guide

## Prerequisites

### 1. Install Laravel (if not done)
```bash
cd ltp-apis
composer install
```

### 2. Setup Environment
```bash
copy .env.example .env
php artisan key:generate
```

### 3. Configure Database
Edit `.env` file:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ltp_database
DB_USERNAME=root
DB_PASSWORD=your_password
```

### 4. Run Migrations
```bash
php artisan migrate
php artisan db:seed
```

### 5. Start Server
```bash
php artisan serve
```

---

## Quick API Tests

### Test 1: Admin Login
```bash
curl -X POST http://localhost:8000/api/v1/admin/login ^
  -H "Content-Type: application/json" ^
  -d "{\"email\":\"admin@letsturf.com\",\"password\":\"admin123\"}"
```

**Expected Response:**
```json
{
  "token": "1|xxxxx...",
  "admin": {
    "id": 1,
    "name": "Super Admin",
    "email": "admin@letsturf.com"
  }
}
```

### Test 2: Get Dashboard Stats
```bash
curl -X GET http://localhost:8000/api/v1/admin/dashboard/stats ^
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

### Test 3: List Owners
```bash
curl -X GET http://localhost:8000/api/v1/admin/owners ^
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

### Test 4: List Turfs
```bash
curl -X GET http://localhost:8000/api/v1/admin/turfs ^
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

---

## Frontend Testing

### 1. Install Dependencies
```bash
cd ltp-admin-frontend
npm install
```

### 2. Start Frontend
```bash
npm run dev
```

### 3. Test Login
- Open: http://localhost:3011/login
- Email: admin@letsturf.com
- Password: admin123

---

## Common Issues

### Issue 1: CORS Error
Add to `bootstrap/app.php`:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        'api/*'
    ]);
})
```

### Issue 2: Database Connection
- Ensure MySQL is running
- Check database credentials in `.env`
- Create database: `CREATE DATABASE ltp_database;`

### Issue 3: Token Not Working
- Clear browser localStorage
- Re-login to get new token

---

## Testing Checklist

- [ ] Backend server running (port 8000)
- [ ] Frontend server running (port 3011)
- [ ] Database created and migrated
- [ ] Admin user seeded
- [ ] Login working
- [ ] Dashboard loading
- [ ] Owners page loading
