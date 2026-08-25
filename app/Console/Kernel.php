<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        Commands\ReleaseExpiredSlotLocks::class,
        Commands\CompleteExpiredBookings::class,
        Commands\ExpireUnconfirmedBookings::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        $log = storage_path('logs/scheduler.log');
        $schedule->command('slots:release-locks')->everyMinute()->withoutOverlapping()->appendOutputTo($log);
        $schedule->command('bookings:complete-expired')->everyMinute()->withoutOverlapping()->appendOutputTo($log);
        $schedule->command('bookings:expire-unconfirmed')->everyMinute()->withoutOverlapping()->appendOutputTo($log);
        $schedule->command('backup:database')->dailyAt('02:30')->withoutOverlapping()->appendOutputTo($log);
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
