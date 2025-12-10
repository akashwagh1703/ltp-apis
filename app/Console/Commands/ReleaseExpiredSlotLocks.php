<?php

namespace App\Console\Commands;

use App\Services\SlotService;
use Illuminate\Console\Command;

class ReleaseExpiredSlotLocks extends Command
{
    protected $signature = 'slots:release-locks';
    protected $description = 'Release expired slot locks';

    public function handle(SlotService $slotService)
    {
        $released = $slotService->releaseExpiredLocks();
        $this->info("Released {$released} expired slot locks");
    }
}
