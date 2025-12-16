<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Command;
use Carbon\Carbon;

class CompleteExpiredBookings extends Command
{
    protected $signature = 'bookings:complete-expired';
    protected $description = 'Auto-complete bookings 10 seconds after end time';

    public function handle()
    {
        $now = Carbon::now();
        
        $bookings = Booking::where('booking_status', 'confirmed')
            ->where('payment_status', 'success')
            ->where(function($query) use ($now) {
                // Past date bookings
                $query->where('booking_date', '<', $now->toDateString())
                    // Or today's bookings where end_time + 10 seconds has passed
                    ->orWhere(function($q) use ($now) {
                        $q->where('booking_date', '=', $now->toDateString())
                          ->whereRaw("CONCAT(booking_date, ' ', end_time)::timestamp + interval '10 seconds' < ?", [$now]);
                    });
            })
            ->get();

        foreach ($bookings as $booking) {
            $booking->update(['booking_status' => 'completed']);
        }

        $this->info("Auto-completed {$bookings->count()} bookings");
        return 0;
    }
}
