<?php

namespace Tests\Feature\Api\Mobile\Chat;

use App\Models\ConversationParticipant;
use App\Services\Chat\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DeliveredReceiptTest extends TestCase
{
    use RefreshDatabase;
    use ChatTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_thread_participants_include_last_delivered_at(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);
        $dm = app(ConversationService::class)->dmBetween($owner, $member);
        $dm->messages()->create(['user_id' => $owner->id, 'body' => 'hi']);

        ConversationParticipant::where('conversation_id', $dm->id)
            ->where('user_id', $member->id)
            ->update(['last_delivered_at' => '2026-01-02 03:04:05']);

        $response = $this->actingAs($owner)
            ->getJson("/api/mobile/conversations/{$dm->id}/messages")
            ->assertOk();

        $participants = collect($response->json('participants'));
        $other = $participants->firstWhere('user_id', $member->id);
        $this->assertNotNull($other['last_delivered_at']);
        $this->assertStringStartsWith('2026-01-02T03:04:05', $other['last_delivered_at']);
        $me = $participants->firstWhere('user_id', $owner->id);
        $this->assertNull($me['last_delivered_at']);
    }
}
