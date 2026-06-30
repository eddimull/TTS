<?php

namespace Tests\Feature\RehearsalPlanner;

use App\Models\RehearsalPlannerMessage;
use App\Models\RehearsalPlannerSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlannerModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_has_ordered_messages_and_payload_casts_to_array(): void
    {
        $session = RehearsalPlannerSession::factory()->create();

        RehearsalPlannerMessage::factory()->create([
            'session_id' => $session->id,
            'role'       => 'user',
            'content'    => 'Plan next week',
            'status'     => 'complete',
        ]);
        RehearsalPlannerMessage::factory()->create([
            'session_id' => $session->id,
            'role'       => 'assistant',
            'content'    => 'Here is a plan',
            'payload'    => ['suggestions' => ['A', 'B'], 'plan' => null],
            'status'     => 'complete',
        ]);

        $session->refresh()->load('messages');

        $this->assertCount(2, $session->messages);
        $this->assertSame('user', $session->messages->first()->role);
        $this->assertIsArray($session->messages->last()->payload);
        $this->assertSame(['A', 'B'], $session->messages->last()->payload['suggestions']);
    }
}
