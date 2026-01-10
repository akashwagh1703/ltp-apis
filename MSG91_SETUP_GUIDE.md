# MSG91 SMS Gateway - Complete Setup Guide

## 📱 What is MSG91?

MSG91 is India's leading cloud communication platform for SMS, Voice, Email, and WhatsApp.

**Website:** https://msg91.com

---

## 🚀 Quick Setup (30 Minutes)

### **Step 1: Create Account (5 mins)**

1. Go to https://msg91.com/signup
2. Sign up with:
   - Email address
   - Mobile number
   - Company name: "Let's Turf Play"
3. Verify email and mobile
4. Complete profile

**Free Credits:** ₹20-50 for testing

---

### **Step 2: Get Auth Key (2 mins)**

1. Login to MSG91 dashboard
2. Click on **Settings** (top right)
3. Go to **API Keys** section
4. Copy your **Auth Key**
   - Format: `123456ABCDEFabcdef123456`
5. Save it securely

---

### **Step 3: Request Sender ID (5 mins + 1-2 days approval)**

1. Go to **Sender ID** section in dashboard
2. Click **Create New Sender ID**
3. Enter details:
   - **Sender ID:** `LTPLAY` or `LETSRF` (6 chars max)
   - **Purpose:** Transactional
   - **Sample Message:** "Your LTP OTP is 123456"
4. Submit for approval
5. Wait 1-2 business days

**Note:** You can test with default sender ID while waiting.

---

### **Step 4: DLT Registration (Required for Production)**

#### **What is DLT?**
Distributed Ledger Technology - Government mandate for commercial SMS in India.

#### **Choose DLT Provider:**
- **Airtel:** https://www.airtel.in/business/commercial-communication
- **Jio:** https://trueconnect.jio.com
- **Vodafone:** https://www.vilpower.in
- **BSNL:** https://www.ucc-bsnl.co.in

#### **Documents Required:**
- Business registration certificate
- PAN card
- GST certificate (if applicable)
- Address proof
- Authorized signatory ID proof

#### **Registration Steps:**

**A. Register Entity (Your Business)**
1. Login to DLT portal
2. Register as Principal Entity
3. Upload documents
4. Submit for approval
5. Get **Entity ID** (19 digits)

**B. Register Sender ID**
1. Go to Sender ID section
2. Register: `LTPLAY`
3. Select: Transactional
4. Submit for approval

**C. Create Templates**

**Template 1: OTP**
```
Header: LTPLAY
Template Type: Transactional
Category: OTP
Message: Your LTP OTP is {#var#}. Valid for 10 minutes. Do not share this code with anyone.
```

**Template 2: Booking Confirmation**
```
Header: LTPLAY
Template Type: Transactional
Category: Service Explicit
Message: Booking confirmed! #{#var#} at {#var#} on {#var#} at {#var#}. Amount: Rs.{#var#}. Thank you!
```

**Template 3: Booking Cancellation**
```
Header: LTPLAY
Template Type: Transactional
Category: Service Explicit
Message: Booking #{#var#} cancelled. Reason: {#var#}. Refund will be processed in 5-7 business days.
```

**D. Get Template IDs**
- After approval, you'll get Template IDs
- Format: `1234567890123456789` (19 digits)
- Save all Template IDs

**Timeline:** 2-5 business days

---

### **Step 5: Link DLT with MSG91 (5 mins)**

1. Login to MSG91 dashboard
2. Go to **Settings** → **DLT Configuration**
3. Enter your **DLT Entity ID**
4. Map templates:
   - Select MSG91 template
   - Enter DLT Template ID
   - Click Map
5. Verify all mappings

---

### **Step 6: Buy Credits (5 mins)**

1. Go to **Recharge** section
2. Choose amount:
   - **Testing:** ₹500 (~2,500 SMS)
   - **Production:** ₹2,000-5,000
3. Payment methods:
   - Credit/Debit card
   - Net banking
   - UPI
4. Complete payment
5. Credits reflect instantly

**Pricing:**
- Transactional SMS: ₹0.20 per SMS
- OTP SMS: ₹0.25 per SMS

---

### **Step 7: Configure in LTP APIs**

Edit your `.env` file:

```env
# Enable SMS notifications
NOTIFICATION_SMS_ENABLED=true

# MSG91 Configuration
SMS_GATEWAY=msg91
MSG91_AUTH_KEY=your_auth_key_here
MSG91_SENDER_ID=LTPLAY
MSG91_OTP_TEMPLATE_ID=your_otp_template_id
MSG91_BOOKING_TEMPLATE_ID=your_booking_template_id
MSG91_CANCEL_TEMPLATE_ID=your_cancel_template_id
MSG91_DLT_ENTITY_ID=your_dlt_entity_id
```

**Restart your app:**
```bash
php artisan config:clear
php artisan cache:clear
```

---

## 🧪 Testing

### **Test with MSG91 Dashboard (Before DLT)**

1. Go to **Campaign** → **Quick SMS**
2. Enter test number
3. Type message
4. Send
5. Check delivery report

### **Test with API (After Configuration)**

**Test Owner Login:**
```bash
curl -X POST http://your-api-url/api/v1/owner/auth/send-otp \
  -H "Content-Type: application/json" \
  -d '{"phone": "9876543210"}'
```

**Test Player Login:**
```bash
curl -X POST http://your-api-url/api/v1/player/auth/send-otp \
  -H "Content-Type: application/json" \
  -d '{"phone": "9876543210"}'
```

**Check Logs:**
```bash
tail -f storage/logs/laravel.log | grep MSG91
```

---

## 📊 MSG91 Dashboard Features

### **1. Campaign Management**
- Send bulk SMS
- Schedule messages
- Import contacts

### **2. Reports & Analytics**
- Delivery reports
- Failed messages
- Credit usage
- Daily/Monthly reports

### **3. API Logs**
- View all API calls
- Success/failure status
- Error messages
- Response times

### **4. Credits Management**
- Current balance
- Recharge history
- Usage statistics
- Low balance alerts

---

## 🔧 MSG91 API Endpoints

### **Simple SMS API:**
```
GET https://api.msg91.com/api/sendhttp.php
Parameters:
  - authkey: Your auth key
  - mobiles: Phone number
  - message: SMS text
  - sender: Sender ID
  - route: 4 (Transactional)
```

### **OTP API (Recommended):**
```
POST https://control.msg91.com/api/v5/otp
Headers:
  - authkey: Your auth key
Body:
  - template_id: Template ID
  - mobile: Phone number
  - otp: OTP code
```

### **Flow API (Template-based):**
```
POST https://control.msg91.com/api/v5/flow/
Headers:
  - authkey: Your auth key
Body:
  - flow_id: Template ID
  - mobiles: Phone number
  - var1, var2: Template variables
```

---

## 💰 Pricing Details

### **SMS Rates (India):**

| Type | Rate | DND Bypass | Time Restriction |
|------|------|------------|------------------|
| **Transactional** | ₹0.20 | ✅ Yes | ❌ None |
| **OTP** | ₹0.25 | ✅ Yes | ❌ None |
| **Promotional** | ₹0.10 | ❌ No | ✅ 9 AM - 9 PM |

### **Volume Discounts:**

| Volume | Rate |
|--------|------|
| 0 - 10K | ₹0.25 |
| 10K - 50K | ₹0.22 |
| 50K - 1L | ₹0.20 |
| 1L+ | ₹0.18 |

### **Monthly Cost Estimate (LTP):**

**Scenario: 1000 bookings/month**

| Message Type | Count | Rate | Cost |
|--------------|-------|------|------|
| Owner OTP | 500 | ₹0.25 | ₹125 |
| Player OTP | 1500 | ₹0.25 | ₹375 |
| Booking Confirmation | 1000 | ₹0.20 | ₹200 |
| Cancellation | 200 | ₹0.20 | ₹40 |
| **Total** | **3200** | - | **₹740** |

---

## 🛡️ Best Practices

### **1. Use OTP API for OTPs**
- Dedicated OTP service
- Better delivery rates
- Auto-retry mechanism
- Fraud detection

### **2. Use Templates**
- Faster approval
- Better delivery
- DLT compliant
- Professional look

### **3. Monitor Credits**
- Set low balance alerts
- Auto-recharge option
- Track usage daily

### **4. Handle Failures**
- Log all failures
- Retry mechanism
- Fallback options
- Alert on high failure rate

### **5. Test Thoroughly**
- Test all operators (Airtel, Jio, Vodafone)
- Test DND numbers
- Test peak hours
- Test different message types

---

## 🔍 Troubleshooting

### **Issue: SMS not received**

**Check:**
1. Credits available?
2. Sender ID approved?
3. DLT template approved?
4. Phone number correct?
5. Network issues?

**Solution:**
- Check delivery report in dashboard
- Verify DLT mapping
- Test with different number
- Contact MSG91 support

### **Issue: API error**

**Common Errors:**

| Error | Cause | Solution |
|-------|-------|----------|
| `Invalid Auth Key` | Wrong auth key | Check .env file |
| `Insufficient Credits` | No balance | Recharge account |
| `Template not found` | Wrong template ID | Verify template ID |
| `DLT not mapped` | Template not linked | Map in dashboard |
| `Invalid mobile` | Wrong format | Check phone format |

### **Issue: High failure rate**

**Possible Causes:**
- DND numbers (use transactional route)
- Invalid numbers
- Network issues
- Template issues

**Solution:**
- Use route 4 (transactional)
- Validate phone numbers
- Check operator status
- Review template content

---

## 📞 MSG91 Support

### **Contact Options:**

**Email:** support@msg91.com

**Phone:** +91-9650-950-950

**Live Chat:** Available on dashboard

**Support Hours:** 24/7

**Response Time:** 
- Critical: 1-2 hours
- Normal: 4-6 hours

### **Resources:**

**Documentation:** https://docs.msg91.com

**API Reference:** https://docs.msg91.com/reference

**Video Tutorials:** https://www.youtube.com/c/MSG91

**Community:** https://community.msg91.com

---

## ✅ Setup Checklist

### **Before Production:**

- [ ] MSG91 account created
- [ ] Auth key obtained
- [ ] Credits purchased (₹1000+)
- [ ] Sender ID approved
- [ ] DLT entity registered
- [ ] DLT templates approved
- [ ] Templates mapped in MSG91
- [ ] Configuration added to .env
- [ ] Tested with real numbers
- [ ] Tested all message types
- [ ] Delivery reports verified
- [ ] Error handling tested
- [ ] Logs monitoring setup

---

## 🎯 Quick Reference

### **Essential URLs:**

- **Dashboard:** https://control.msg91.com
- **DLT Portal:** https://www.airtel.in/business/commercial-communication
- **API Docs:** https://docs.msg91.com
- **Support:** https://msg91.com/help

### **Essential IDs:**

```
Auth Key: ________________________
Sender ID: LTPLAY
DLT Entity ID: ________________________
OTP Template ID: ________________________
Booking Template ID: ________________________
Cancel Template ID: ________________________
```

---

## 🚀 Ready to Go!

Once you complete all steps:

1. ✅ MSG91 configured
2. ✅ DLT approved
3. ✅ Credits loaded
4. ✅ .env updated
5. ✅ Testing done

**Enable SMS in .env:**
```env
NOTIFICATION_SMS_ENABLED=true
```

**Restart and you're live!** 🎉

---

**Need Help?** Check logs or contact MSG91 support!
