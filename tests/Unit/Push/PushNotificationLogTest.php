<?php

namespace Tests\Unit\Push;

use App\Models\PushNotificationLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushNotificationLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_user_type_is_unique(): void
    {
        PushNotificationLog::create(['event_id' => 1, 'user_id' => 1, 'type' => 'event_reminder_8h']);
        $this->expectException(\Illuminate\Database\QueryException::class);
        PushNotificationLog::create(['event_id' => 1, 'user_id' => 1, 'type' => 'event_reminder_8h']);
    }

    public function test_same_event_user_different_type_allowed(): void
    {
        PushNotificationLog::create(['event_id' => 1, 'user_id' => 1, 'type' => 'event_reminder_8h']);
        PushNotificationLog::create(['event_id' => 1, 'user_id' => 1, 'type' => 'event_departure']);
        $this->assertSame(2, PushNotificationLog::count());
    }
}
