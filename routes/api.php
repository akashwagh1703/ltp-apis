<?php

use Illuminate\Support\Facades\Route;

// Admin Routes
Route::prefix('v1/admin')->namespace('App\Http\Controllers\Api\V1\Admin')->group(function () {
    Route::post('login', 'AuthController@login');
    
    Route::middleware(['auth:sanctum', 'admin.auth'])->group(function () {
        Route::post('logout', 'AuthController@logout');
        Route::get('me', 'AuthController@me');
        
        Route::get('dashboard/stats', 'DashboardController@stats');
        Route::get('dashboard/recent-bookings', 'DashboardController@recentBookings');
        
        Route::apiResource('owners', 'OwnerController');
        Route::put('owners/{id}/status', 'OwnerController@updateStatus');
        Route::put('owners/{id}/commission-rate', 'OwnerController@updateCommissionRate');
        Route::apiResource('turfs', 'TurfController');
        Route::post('turfs/{id}/approve', 'TurfController@approve');
        Route::post('turfs/{id}/reject', 'TurfController@reject');
        Route::post('turfs/{id}/suspend', 'TurfController@suspend');
        Route::post('turfs/{id}/activate', 'TurfController@activate');
        Route::post('turfs/{id}/images', 'TurfImageController@upload');
        Route::delete('turf-images/{id}', 'TurfImageController@delete');
        Route::post('turf-images/{id}/set-primary', 'TurfImageController@setPrimary');
        Route::apiResource('bookings', 'BookingController')->only(['index', 'show']);
        Route::post('bookings/{id}/cancel', 'BookingController@cancel');
        
        Route::get('players', 'PlayerController@index');
        Route::get('players/{id}', 'PlayerController@show');
        Route::put('players/{id}/status', 'PlayerController@updateStatus');
        
        Route::get('payouts', 'PayoutController@index');
        Route::post('payouts/generate', 'PayoutController@generate');
        Route::post('payouts/{id}/process', 'PayoutController@process');
        Route::post('payouts/{id}/release', 'PayoutController@release');
        
        Route::apiResource('banners', 'BannerController');
        Route::apiResource('faqs', 'FaqController');
        Route::apiResource('coupons', 'CouponController');
        
        Route::get('turf-update-requests', 'TurfUpdateRequestController@index');
        Route::post('turf-update-requests/{id}/approve', 'TurfUpdateRequestController@approve');
        Route::post('turf-update-requests/{id}/reject', 'TurfUpdateRequestController@reject');
        
        Route::get('reports/bookings', 'ReportController@bookings');
        Route::get('reports/turf-wise', 'ReportController@turfWise');
        Route::get('reports/owner-wise', 'ReportController@ownerWise');
        Route::get('reports/payment-mode', 'ReportController@paymentMode');
        
        Route::get('logs/activity', 'ActivityLogController@index');
        
        Route::get('reviews', 'ReviewController@index');
        Route::put('reviews/{id}/status', 'ReviewController@updateStatus');
        Route::delete('reviews/{id}', 'ReviewController@destroy');
        
        Route::get('settings', 'SettingController@index');
        Route::post('settings', 'SettingController@update');
        Route::put('settings/{key}', 'SettingController@updateSingle');
        Route::get('settings/commission/rate', 'SettingController@getCommissionRate');
        Route::put('settings/commission/rate', 'SettingController@updateCommissionRate');
        
        Route::get('subscriptions', 'SubscriptionController@index');
        Route::post('subscriptions', 'SubscriptionController@store');
        Route::post('subscriptions/{id}/renew', 'SubscriptionController@renew');
        Route::get('subscriptions/plans', 'SubscriptionController@plans');
        Route::put('subscriptions/plans/{id}', 'SubscriptionController@updatePlan');
        Route::get('subscriptions/owners-without', 'SubscriptionController@ownersWithoutSubscription');
        Route::get('subscriptions/statistics', 'SubscriptionController@statistics');
    });
});

// Player Routes
Route::prefix('v1/player')->namespace('App\Http\Controllers\Api\V1\Player')->group(function () {
    Route::post('auth/send-otp', 'AuthController@sendOtp');
    Route::post('auth/verify-otp', 'AuthController@verifyOtp');
    
    Route::get('turfs', 'TurfController@index');
    Route::get('turfs/featured', 'TurfController@featured');
    Route::get('turfs/{id}', 'TurfController@show');
    Route::get('slots/available', 'SlotController@available');
    Route::post('slots/generate', 'SlotController@generate');
    Route::post('slots/update-prices', 'SlotController@updatePrices');
    
    Route::get('banners', 'BannerController@index');
    Route::get('faqs', 'FaqController@index');
    Route::get('coupons/available', 'CouponController@available');
    Route::post('coupons/validate', 'CouponController@validate');
    
    Route::middleware(['auth:sanctum', 'player.auth'])->group(function () {
        Route::post('auth/logout', 'AuthController@logout');
        Route::put('auth/profile', 'AuthController@updateProfile');
        
        Route::get('bookings', 'BookingController@index');
        Route::post('bookings', 'BookingController@store');
        Route::post('bookings/{id}/confirm-payment', 'BookingController@confirmPayment');
        Route::post('bookings/{id}/cancel', 'BookingController@cancel');
        
        Route::post('payment/create-order', 'PaymentController@createOrder');
        Route::post('payment/verify', 'PaymentController@verifyPayment');
        
        Route::post('reviews', 'ReviewController@store');
        Route::get('reviews/my', 'ReviewController@myReviews');
        
        Route::get('notifications', 'NotificationController@index');
        Route::post('notifications/{id}/read', 'NotificationController@markAsRead');
        Route::post('notifications/read-all', 'NotificationController@markAllAsRead');
        
        Route::get('me', 'AuthController@me');
    });
});

// Owner Routes
Route::prefix('v1/owner')->namespace('App\Http\Controllers\Api\V1\Owner')->group(function () {
    Route::post('auth/send-otp', 'AuthController@sendOtp');
    Route::post('auth/verify-otp', 'AuthController@verifyOtp');
    
    Route::middleware(['auth:sanctum', 'owner.auth'])->group(function () {
        Route::post('auth/logout', 'AuthController@logout');
        Route::put('auth/profile', 'AuthController@updateProfile');
        
        Route::get('dashboard/stats', 'DashboardController@stats');
        Route::get('dashboard/recent-bookings', 'DashboardController@recentBookings');
        
        Route::get('turfs', 'TurfController@index');
        Route::get('turfs/{id}', 'TurfController@show');
        Route::post('turfs/{id}/request-update', 'TurfController@requestUpdate');
        
        Route::post('slots/generate', 'SlotController@generate');
        Route::get('slots', 'SlotController@list');
        Route::post('slots/update-prices', 'SlotController@updatePrices');
        
        Route::get('bookings', 'BookingController@index');
        Route::post('bookings/offline', 'BookingController@createOffline');
        Route::get('bookings/stats', 'BookingController@stats');
        
        Route::get('payouts', 'PayoutController@index');
        Route::get('payouts/{id}', 'PayoutController@show');
        
        Route::get('reviews', 'ReviewController@index');
        
        Route::get('notifications', 'NotificationController@index');
        Route::post('notifications/{id}/read', 'NotificationController@markAsRead');
        Route::post('notifications/read-all', 'NotificationController@markAllAsRead');
        
        Route::get('me', 'AuthController@me');
    });
});
