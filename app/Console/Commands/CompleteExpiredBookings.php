<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Command;

class CompleteExpiredBookings extends Command
{
    protected $signature = 'bookings:complete-expired';
    protected $description = 'Mark past bookings as completed';

    public function handle()
    {
        $completed = Booking::where('booking_status', 'confirmed')
            ->where('booking_date', '<', now()->toDateString())
            ->update(['booking_status' => 'completed']);

        $this->info("Marked {$completed} bookings as completed");
    }
}
