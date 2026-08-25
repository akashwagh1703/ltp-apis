<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireUnconfirmedBookings extends Command
{
    protected $signature = 'bookings:expire-unconfirmed';
    protected $description = 'Expire awaiting / pay-on-arrival bookings whose payment hold has passed and free their slots';

    public function handle()
    {
        $expired = Booking::expireUnconfirmed();
        $this->info("Expired {$expired} unconfirmed bookings");
        Log::info('bookings:expire-unconfirmed', ['expired' => $expired]);

        return 0;
    }
}
