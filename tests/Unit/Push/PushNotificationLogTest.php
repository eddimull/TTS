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

    public function test_log_row_can_be_created_with_dedupe_key_and_no_event(): void
    {
        $user = \App\Models\User::factory()->create();

        $log = \App\Models\PushNotificationLog::create([
            'user_id'    => $user->id,
            'type'       => 'rehearsal_cancelled',
            'dedupe_key' => 'rehearsal:1:cancelled:1234567890',
            'sent_at'    => now(),
        ]);

        $this->assertNull($log->event_id);
        $this->assertSame('rehearsal:1:cancelled:1234567890', $log->fresh()->dedupe_key);
    }

    public function test_dedupe_key_is_unique_per_user(): void
    {
        $user = \App\Models\User::factory()->create();
        $attrs = [
            'user_id'    => $user->id,
            'type'       => 'rehearsal_cancelled',
            'dedupe_key' => 'rehearsal:1:cancelled:1234567890',
            'sent_at'    => now(),
        ];

        \App\Models\PushNotificationLog::create($attrs);

        $this->expectException(\Illuminate\Database\QueryException::class);
        \App\Models\PushNotificationLog::create($attrs);
    }
}
