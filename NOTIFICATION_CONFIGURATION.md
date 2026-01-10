# Notification System Configuration Guide

## 🎛️ Easy On/Off Control

You can enable or disable WhatsApp and SMS notifications anytime by changing `.env` file.

---

## 🔧 Configuration

### **Enable/Disable Notifications**

Edit your `.env` file:

```env
# Set to true to enable, false to disable
NOTIFICATION_WHATSAPP_ENABLED=false
NOTIFICATION_SMS_ENABLED=false
```

### **Configuration Scenarios**

#### **Scenario 1: Both Disabled (Default - Development)**
```env
NOTIFICATION_WHATSAPP_ENABLED=false
NOTIFICATION_SMS_ENABLED=false
```
- ✅ App works normally
- ✅ OTP 999999 works
- ✅ No external API calls
- ✅ All notifications logged only

#### **Scenario 2: WhatsApp Only (Recommended)**
```env
NOTIFICATION_WHATSAPP_ENABLED=true
NOTIFICATION_SMS_ENABLED=false

# WhatsApp credentials required
WHATSAPP_PHONE_NUMBER_ID=your_id
WHATSAPP_ACCESS_TOKEN=your_token
WHATSAPP_BUSINESS_ACCOUNT_ID=your_account_id
```
- ✅ WhatsApp notifications sent
- ✅ SMS skipped
- ✅ Lower cost
- ✅ Better user experience

#### **Scenario 3: SMS Only**
```env
NOTIFICATION_WHATSAPP_ENABLED=false
NOTIFICATION_SMS_ENABLED=true

# MSG91 credentials required
MSG91_AUTH_KEY=your_auth_key
MSG91_SENDER_ID=LTPLAY
```
- ✅ SMS notifications sent
- ✅ WhatsApp skipped
- ✅ Traditional approach

#### **Scenario 4: Both Enabled (Maximum Reliability)**
```env
NOTIFICATION_WHATSAPP_ENABLED=true
NOTIFICATION_SMS_ENABLED=true

# Both credentials required
WHATSAPP_PHONE_NUMBER_ID=your_id
WHATSAPP_ACCESS_TOKEN=your_token
MSG91_AUTH_KEY=your_auth_key
MSG91_SENDER_ID=LTPLAY
```
- ✅ WhatsApp tried first
- ✅ SMS as fallback
- ✅ Maximum delivery rate
- ✅ Higher cost

---

## 📋 Complete .env Configuration

```env
# ============================================
# NOTIFICATION SYSTEM CONFIGURATION
# ============================================

# Enable/Disable Channels (true/false)
NOTIFICATION_WHATSAPP_ENABLED=false
NOTIFICATION_SMS_ENABLED=false

# ============================================
# WHATSAPP CLOUD API
# ============================================
WHATSAPP_API_URL=https://graph.facebook.com/v18.0
WHATSAPP_PHONE_NUMBER_ID=
WHATSAPP_ACCESS_TOKEN=
WHATSAPP_BUSINESS_ACCOUNT_ID=

# ============================================
# MSG91 SMS GATEWAY
# ============================================
SMS_GATEWAY=msg91
MSG91_AUTH_KEY=
MSG91_SENDER_ID=
MSG91_OTP_TEMPLATE_ID=
MSG91_BOOKING_TEMPLATE_ID=
MSG91_CANCEL_TEMPLATE_ID=
MSG91_DLT_ENTITY_ID=
```

---

## 🚀 Quick Start Guide

### **Step 1: Development (No Setup Required)**

```env
NOTIFICATION_WHATSAPP_ENABLED=false
NOTIFICATION_SMS_ENABLED=false
```

**What happens:**
- App works perfectly
- OTP 999999 always works
- All notifications logged to `storage/logs/laravel.log`
- No external API calls
- No cost

**Use for:**
- Local development
- Testing app functionality
- Demo purposes

---

### **Step 2: Enable WhatsApp (Recommended)**

**Prerequisites:**
1. Meta Business Account
2. WhatsApp Business API access
3. Approved message templates

**Configuration:**
```env
NOTIFICATION_WHATSAPP_ENABLED=true
NOTIFICATION_SMS_ENABLED=false

WHATSAPP_PHONE_NUMBER_ID=123456789012345
WHATSAPP_ACCESS_TOKEN=EAAxxxxxxxxxxxxxxx
WHATSAPP_BUSINESS_ACCOUNT_ID=123456789012345
```

**What happens:**
- WhatsApp messages sent for OTP, bookings, cancellations
- If WhatsApp fails, app continues normally
- Errors logged but don't stop execution
- OTP 999999 still works as fallback

**Cost:** ~₹0.40 per message

---

### **Step 3: Enable SMS (Optional)**

**Prerequisites:**
1. MSG91 account
2. DLT registration (for India)
3. Approved sender ID and templates

**Configuration:**
```env
NOTIFICATION_WHATSAPP_ENABLED=false
NOTIFICATION_SMS_ENABLED=true

MSG91_AUTH_KEY=your_auth_key_here
MSG91_SENDER_ID=LTPLAY
MSG91_OTP_TEMPLATE_ID=template_id_here
```

**What happens:**
- SMS sent via MSG91
- If SMS fails, app continues normally
- Errors logged but don't stop execution
- OTP 999999 still works as fallback

**Cost:** ~₹0.20 per SMS

---

### **Step 4: Enable Both (Maximum Reliability)**

```env
NOTIFICATION_WHATSAPP_ENABLED=true
NOTIFICATION_SMS_ENABLED=true

# Add both WhatsApp and MSG91 credentials
```

**What happens:**
- WhatsApp tried first
- If WhatsApp fails, SMS tried
- If both fail, app continues normally
- OTP 999999 still works as fallback

**Cost:** ~₹0.40-0.60 per notification

---

## 🔄 How to Switch On/Off

### **To Disable WhatsApp:**
```bash
# Edit .env
NOTIFICATION_WHATSAPP_ENABLED=false
```

### **To Disable SMS:**
```bash
# Edit .env
NOTIFICATION_SMS_ENABLED=false
```

### **To Disable Both:**
```bash
# Edit .env
NOTIFICATION_WHATSAPP_ENABLED=false
NOTIFICATION_SMS_ENABLED=false
```

**No code changes needed! Just edit .env and restart the app.**

---

## 📊 Notification Flow

### **When Both Enabled:**

```
User Action (Login/Booking)
    ↓
Try WhatsApp
    ↓
Success? → Done ✅
    ↓
Failed? → Try SMS
    ↓
Success? → Done ✅
    ↓
Failed? → Log error, continue app ✅
    ↓
OTP 999999 still works ✅
```

### **When Only WhatsApp Enabled:**

```
User Action
    ↓
Try WhatsApp
    ↓
Success/Failed → Continue app ✅
    ↓
OTP 999999 still works ✅
```

### **When Both Disabled:**

```
User Action
    ↓
Log notification only
    ↓
Continue app ✅
    ↓
OTP 999999 works ✅
```

---

## 🎯 Notification Types & Status

| Notification Type | WhatsApp | SMS | Static OTP |
|-------------------|----------|-----|------------|
| **Owner Login OTP** | ✅ | ✅ | ✅ Always works |
| **Player Login OTP** | ✅ | ✅ | ✅ Always works |
| **Turf Created** | ✅ | ✅ | N/A |
| **Booking Confirmed (Online)** | ✅ | ✅ | N/A |
| **Booking Confirmed (Offline)** | ✅ | ✅ | N/A |
| **Booking Cancelled** | ✅ | ✅ | N/A |
| **Booking Completed** | ✅ | ✅ | N/A |

---

## 🛡️ Safety Features

### **1. Non-Blocking**
- Notification failures never stop app execution
- All wrapped in try-catch blocks
- Errors logged for debugging

### **2. Graceful Degradation**
- Works without any credentials
- Works with partial configuration
- Falls back to logging

### **3. Static OTP Preserved**
- OTP 999999 always works
- Independent of notification system
- Perfect for development/testing

### **4. Easy Rollback**
- Disable anytime via .env
- No code deployment needed
- Instant effect after restart

---

## 📝 Monitoring & Logs

### **View Notification Logs:**

```bash
# All notifications
tail -f storage/logs/laravel.log | grep -E "WhatsApp|SMS|MSG91"

# WhatsApp only
tail -f storage/logs/laravel.log | grep WhatsApp

# SMS only
tail -f storage/logs/laravel.log | grep MSG91

# Disabled notifications
tail -f storage/logs/laravel.log | grep "disabled via config"
```

### **Log Messages:**

**When Disabled:**
```
[INFO] WhatsApp disabled via config, skipping message
[INFO] SMS disabled via config, skipping message
```

**When Enabled & Working:**
```
[INFO] WhatsApp sent successfully
[INFO] MSG91 SMS sent successfully
```

**When Enabled & Failed:**
```
[WARNING] WhatsApp OTP failed, continuing
[ERROR] MSG91 SMS failed
```

---

## 🧪 Testing Checklist

### **Test with Both Disabled:**
- [ ] Owner login works (OTP 999999)
- [ ] Player login works (OTP 999999)
- [ ] Bookings work
- [ ] No external API calls
- [ ] Check logs show "disabled via config"

### **Test with WhatsApp Enabled:**
- [ ] Owner receives WhatsApp OTP
- [ ] Player receives WhatsApp OTP
- [ ] Booking confirmations sent
- [ ] Cancellation notifications sent
- [ ] Check logs show "WhatsApp sent successfully"

### **Test with SMS Enabled:**
- [ ] Owner receives SMS OTP
- [ ] Player receives SMS OTP
- [ ] Booking confirmations sent
- [ ] Check logs show "MSG91 SMS sent successfully"

### **Test with Both Enabled:**
- [ ] WhatsApp tried first
- [ ] SMS as fallback
- [ ] Both work independently

---

## 💰 Cost Comparison

### **Monthly Cost (1000 bookings scenario):**

| Configuration | OTP | Bookings | Cancellations | Total |
|---------------|-----|----------|---------------|-------|
| **Both Disabled** | ₹0 | ₹0 | ₹0 | **₹0** |
| **WhatsApp Only** | ₹800 | ₹800 | ₹160 | **₹1,760** |
| **SMS Only** | ₹400 | ₹400 | ₹80 | **₹880** |
| **Both Enabled** | ₹1,200 | ₹1,200 | ₹240 | **₹2,640** |

---

## 🎯 Recommended Configuration

### **For Development:**
```env
NOTIFICATION_WHATSAPP_ENABLED=false
NOTIFICATION_SMS_ENABLED=false
```

### **For Production (Budget):**
```env
NOTIFICATION_WHATSAPP_ENABLED=true
NOTIFICATION_SMS_ENABLED=false
```

### **For Production (Maximum Reliability):**
```env
NOTIFICATION_WHATSAPP_ENABLED=true
NOTIFICATION_SMS_ENABLED=true
```

---

## ❓ FAQ

**Q: Can I disable notifications temporarily?**
A: Yes, just set both to `false` in `.env` and restart.

**Q: Will OTP 999999 stop working?**
A: No, it always works regardless of notification settings.

**Q: Do I need to deploy code to enable/disable?**
A: No, just edit `.env` and restart the app.

**Q: What if I don't have WhatsApp credentials?**
A: Set `NOTIFICATION_WHATSAPP_ENABLED=false` and it will be skipped.

**Q: Can I test without spending money?**
A: Yes, keep both disabled and use OTP 999999.

**Q: What happens if both fail?**
A: App continues normally, errors are logged, OTP 999999 works.

---

## 🚀 Quick Commands

### **Disable All Notifications:**
```bash
# Edit .env
NOTIFICATION_WHATSAPP_ENABLED=false
NOTIFICATION_SMS_ENABLED=false

# Restart
php artisan config:clear
php artisan cache:clear
```

### **Enable WhatsApp Only:**
```bash
# Edit .env
NOTIFICATION_WHATSAPP_ENABLED=true
NOTIFICATION_SMS_ENABLED=false

# Restart
php artisan config:clear
```

### **Enable Both:**
```bash
# Edit .env
NOTIFICATION_WHATSAPP_ENABLED=true
NOTIFICATION_SMS_ENABLED=true

# Restart
php artisan config:clear
```

---

**Status:** ✅ Fully implemented with easy on/off control!
