<?php

namespace Tests\Feature\Api\Mobile\Notifications;

use App\Models\Bandnotification;
use App\Models\User;
use App\Notifications\TTSNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\Mobile\Chat\ChatTestHelpers;
use Tests\TestCase;

class NotificationsApiTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    private function seedNotifications(User $user, int $count): void
    {
        // Bandnotification ids are random UUIDs (Str::uuid()), not time-ordered,
        // so latest('id') can't reliably identify "the row just inserted" — track
        // ids already seen instead and take the one row not yet in that set.
        $seenIds = [];
        for ($i = 1; $i <= $count; $i++) {
            $user->notify(new TTSNotification(['text' => "n{$i}", 'route' => 'dashboard', 'routeParams' => null]));
            $row = Bandnotification::where('notifiable_id', $user->id)
                ->whereNotIn('id', $seenIds)
                ->first();
            $seenIds[] = $row->id;
            // distinct created_at so the cursor is deterministic
            $row->forceFill(['created_at' => now()->subMinutes($count - $i)])->save();
        }
    }

    public function test_index_paginates_past_fifty_with_a_cursor_and_reports_unseen(): void
    {
        [$owner] = $this->makeOwnerWithBand();
        $this->seedNotifications($owner, 55);

        $first = $this->actingAs($owner)->getJson('/api/mobile/notifications?limit=30')->assertOk();
        $this->assertCount(30, $first->json('notifications'));
        $this->assertSame('n55', $first->json('notifications.0.text'), 'newest first');
        $this->assertSame(['id', 'kind', 'text', 'deeplink', 'web_url', 'read_at', 'seen_at', 'created_at'], array_keys($first->json('notifications.0')));
        $this->assertNotNull($first->json('next_cursor'));
        $this->assertSame(55, $first->json('unseen_count'));

        $second = $this->actingAs($owner)->getJson('/api/mobile/notifications?limit=30&cursor=' . urlencode($first->json('next_cursor')))->assertOk();
        $this->assertCount(25, $second->json('notifications'));
        $this->assertSame('n25', $second->json('notifications.0.text'));
        $this->assertSame('n1', $second->json('notifications.24.text'));
        $this->assertNull($second->json('next_cursor'));

        $ids = array_merge($first->json('notifications.*.id'), $second->json('notifications.*.id'));
        $this->assertCount(55, array_unique($ids), 'no duplicates across pages');
    }

    public function test_read_read_all_and_seen_semantics(): void
    {
        [$owner] = $this->makeOwnerWithBand();
        $this->seedNotifications($owner, 3);
        $rows = Bandnotification::where('notifiable_id', $owner->id)->get();

        $this->actingAs($owner)->postJson("/api/mobile/notifications/{$rows[0]->id}/read")->assertNoContent();
        $this->assertNotNull($rows[0]->fresh()->read_at);
        $this->assertNull($rows[0]->fresh()->seen_at, 'read does not imply seen');

        $this->actingAs($owner)->postJson('/api/mobile/notifications/seen')->assertNoContent();
        $this->assertSame(0, Bandnotification::where('notifiable_id', $owner->id)->whereNull('seen_at')->count());
        $this->assertSame(2, Bandnotification::where('notifiable_id', $owner->id)->whereNull('read_at')->count());
        $this->actingAs($owner)->getJson('/api/mobile/notifications/unseen-count')->assertOk()->assertJson(['count' => 0]);

        $this->actingAs($owner)->postJson('/api/mobile/notifications/read-all')->assertNoContent();
        $this->assertSame(0, Bandnotification::where('notifiable_id', $owner->id)->whereNull('read_at')->count());
    }

    public function test_cannot_read_another_users_notification(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $this->seedNotifications($owner, 1);
        $row = Bandnotification::where('notifiable_id', $owner->id)->first();

        $this->actingAs($member)->postJson("/api/mobile/notifications/{$row->id}/read")->assertNotFound();
        $this->assertNull($row->fresh()->read_at);
    }

    public function test_routes_require_authentication(): void
    {
        $this->getJson('/api/mobile/notifications')->assertUnauthorized();
        $this->postJson('/api/mobile/notifications/seen')->assertUnauthorized();
    }
}
