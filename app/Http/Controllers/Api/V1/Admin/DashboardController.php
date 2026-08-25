<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Owner;
use App\Models\Player;
use App\Models\Turf;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function stats()
    {
        try {
            $today = Carbon::today();
            $yesterday = Carbon::yesterday();
            $thisMonth = Carbon::now()->startOfMonth();
            $lastMonth = Carbon::now()->subMonth();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_turfs' => Turf::where('status', '!=', 'deleted')->count(),
                    'active_turfs' => Turf::where('status', 'approved')->count(),
                    'pending_turfs' => Turf::where('status', 'pending')->count(),
                    'total_owners' => Owner::where('status', '!=', 'deleted')->count(),
                    'active_owners' => Owner::where('status', 'active')->count(),
                    'total_players' => Player::where('status', '!=', 'deleted')->count(),
                    'active_players' => Player::where('status', 'active')->count(),
                    'active_users' => Player::where('updated_at', '>=', Carbon::now()->subDays(7))->count(),
                    'last_month_users' => Player::where('updated_at', '>=', $lastMonth)->count(),
                    'total_bookings' => Booking::count(),
                    'today_bookings' => Booking::whereDate('booking_date', $today)->count(),
                    'yesterday_bookings' => Booking::whereDate('booking_date', $yesterday)->count(),
                    'month_bookings' => Booking::whereDate('booking_date', '>=', $thisMonth)->count(),
                    'total_revenue' => (float) Booking::where('booking_status', 'completed')->sum('amount'),
                    'today_revenue' => (float) Booking::where('booking_status', 'completed')->whereDate('booking_date', $today)->sum('amount'),
                    'yesterday_revenue' => (float) Booking::where('booking_status', 'completed')->whereDate('booking_date', $yesterday)->sum('amount'),
                    'month_revenue' => (float) Booking::where('booking_status', 'completed')->whereDate('booking_date', '>=', $thisMonth)->sum('amount'),
                    'pending_bookings' => Booking::where('booking_status', 'pending')->count(),
                    'cancelled_bookings' => Booking::where('booking_status', 'cancelled')->count(),
                    'avg_response_time' => 120,
                    'last_avg_response' => 150
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard stats',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function recentBookings()
    {
        try {
            $bookings = Booking::with(['turf', 'player', 'owner'])
                ->latest()
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $bookings
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch recent bookings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function pendingActions()
    {
        try {
            $actions = [];
            
            // Check for pending owner fee payments
            $pendingFees = \App\Models\SubscriptionPayment::where('status', 'awaiting_admin')->count();
            if ($pendingFees > 0) {
                $actions[] = [
                    'id' => 1,
                    'type' => 'fee',
                    'title' => 'Pay LTP fees',
                    'description' => "{$pendingFees} owner fee payment(s) to confirm",
                    'priority' => 'high',
                    'link' => '/subscriptions'
                ];
            }
            
            // Check for pending applications
            if (class_exists('\App\Models\OwnerApplication')) {
                $pendingApps = \App\Models\OwnerApplication::where('status', 'pending')->count();
                if ($pendingApps > 0) {
                    $actions[] = [
                        'id' => 2,
                        'type' => 'application',
                        'title' => 'Owner Applications',
                        'description' => "{$pendingApps} applications to review",
                        'priority' => 'medium',
                        'link' => '/owner-applications'
                    ];
                }
            }
            
            $pendingListings = \App\Models\Turf::where('status', 'pending')->count();
            if ($pendingListings > 0) {
                $actions[] = [
                    'id' => 3,
                    'type' => 'listing',
                    'title' => 'Turf listings',
                    'description' => "{$pendingListings} listing(s) to review",
                    'priority' => 'high',
                    'link' => '/turfs'
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $actions
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch pending actions',
                'data' => []
            ]);
        }
    }

    public function topTurfs()
    {
        try {
            $turfs = Turf::with(['bookings' => function($query) {
                    $query->where('booking_status', 'completed')
                          ->where('created_at', '>=', Carbon::now()->subDays(30));
                }])
                ->where('status', 'approved')
                ->get()
                ->map(function($turf) {
                    $revenue = $turf->bookings->sum('amount');
                    $bookings = $turf->bookings->count();
                    return [
                        'id' => $turf->id,
                        'name' => $turf->name,
                        'location' => $turf->location ?? 'N/A',
                        'revenue' => $revenue,
                        'bookings' => $bookings,
                        'rating' => 4.5, // Mock rating
                        'performance' => min(100, ($bookings * 5))
                    ];
                })
                ->sortByDesc('revenue')
                ->take(5)
                ->values();
                
            return response()->json([
                'success' => true,
                'data' => $turfs
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch top turfs',
                'data' => []
            ]);
        }
    }

    public function revenueChart(Request $request)
    {
        try {
            $period = $request->get('period', '7d');
            $days = $period === '30d' ? 30 : 7;
            
            $data = [];
            for ($i = $days - 1; $i >= 0; $i--) {
                $date = Carbon::now()->subDays($i);
                $revenue = Booking::where('booking_status', 'completed')
                    ->whereDate('booking_date', $date)
                    ->sum('amount');
                    
                $data[] = [
                    'label' => $date->format($days > 7 ? 'M j' : 'D'),
                    'value' => (float) $revenue,
                    'date' => $date->toDateString()
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch revenue chart',
                'data' => []
            ]);
        }
    }
}
