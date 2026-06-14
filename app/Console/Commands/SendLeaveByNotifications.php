<?php

namespace App\Console\Commands;

use App\Services\Push\LeaveByPushService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendLeaveByNotifications extends Command
{
    protected $signature = 'notifications:tick';
    protected $description = 'Send due leave-by push notifications for today\'s rostered events';

    public function handle(LeaveByPushService $service): int
    {
        $service->run(Carbon::now());
        $this->info('Leave-by notification tick complete.');

        return self::SUCCESS;
    }
}
