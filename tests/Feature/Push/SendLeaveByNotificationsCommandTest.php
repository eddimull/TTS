<?php

namespace Tests\Feature\Push;

use App\Services\Push\LeaveByPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SendLeaveByNotificationsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_tick_invokes_service_run(): void
    {
        $mock = Mockery::mock(LeaveByPushService::class);
        $mock->shouldReceive('run')->once();
        $this->app->instance(LeaveByPushService::class, $mock);

        $this->artisan('notifications:tick')->assertExitCode(0);
    }
}
