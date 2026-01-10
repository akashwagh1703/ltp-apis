<?php

return [
    'notification' => [
        'whatsapp_enabled' => env('NOTIFICATION_WHATSAPP_ENABLED', false),
        'sms_enabled' => env('NOTIFICATION_SMS_ENABLED', false),
    ],

    'sms' => [
        'gateway' => env('SMS_GATEWAY', 'msg91'),
        'msg91' => [
            'auth_key' => env('MSG91_AUTH_KEY'),
            'sender_id' => env('MSG91_SENDER_ID'),
            'otp_template_id' => env('MSG91_OTP_TEMPLATE_ID'),
            'booking_template_id' => env('MSG91_BOOKING_TEMPLATE_ID'),
            'cancel_template_id' => env('MSG91_CANCEL_TEMPLATE_ID'),
            'dlt_entity_id' => env('MSG91_DLT_ENTITY_ID'),
        ],
        'twilio' => [
            'sid' => env('TWILIO_SID'),
            'token' => env('TWILIO_TOKEN'),
            'from' => env('TWILIO_FROM'),
        ],
    ],

    'razorpay' => [
        'key' => env('RAZORPAY_KEY_ID', 'rzp_test_xxxxxxxx'),
        'secret' => env('RAZORPAY_KEY_SECRET', 'xxxxxxxx'),
    ],

    'payment' => [
        'gateway' => env('PAYMENT_GATEWAY', 'razorpay'),
        'razorpay' => [
            'key_id' => env('RAZORPAY_KEY_ID'),
            'key_secret' => env('RAZORPAY_KEY_SECRET'),
        ],
        'cashfree' => [
            'app_id' => env('CASHFREE_APP_ID'),
            'secret_key' => env('CASHFREE_SECRET_KEY'),
        ],
    ],

    'fcm' => [
        'server_key' => env('FCM_SERVER_KEY'),
    ],

    'whatsapp' => [
        'api_url' => env('WHATSAPP_API_URL', 'https://graph.facebook.com/v18.0'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
    ],

    'aws' => [
        's3' => [
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'ap-south-1'),
            'bucket' => env('AWS_BUCKET'),
        ],
    ],
];
