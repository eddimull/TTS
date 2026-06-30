<?php

namespace Tests\Feature\RehearsalPlanner;

use App\Jobs\RehearsalPlannerTurnJob;
use App\Models\RehearsalPlannerMessage;
use App\Models\RehearsalPlannerSession;
use App\Services\RehearsalPlannerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PlannerTurnJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_loads_models_and_calls_run_turn(): void
    {
        $session = RehearsalPlannerSession::factory()->create();
        $assistant = RehearsalPlannerMessage::factory()->create([
            'session_id' => $session->id,
            'role'       => 'assistant',
            'status'     => 'streaming',
        ]);

        $mock = Mockery::mock(RehearsalPlannerService::class);
        $mock->shouldReceive('runTurn')
            ->once()
            ->withArgs(function ($s, $a, $text) use ($session, $assistant) {
                return $s instanceof RehearsalPlannerSession
                    && $s->id === $session->id
                    && $a instanceof RehearsalPlannerMessage
                    && $a->id === $assistant->id
                    && $text === 'hello there';
            });

        $job = new RehearsalPlannerTurnJob($session->id, $assistant->id, 'hello there');
        $job->handle($mock);
    }

    public function test_handle_no_ops_when_records_missing(): void
    {
        $mock = Mockery::mock(RehearsalPlannerService::class);
        $mock->shouldReceive('runTurn')->never();

        $job = new RehearsalPlannerTurnJob(999999, 999999, null);
        $job->handle($mock);

        // No exception thrown — graceful no-op.
        $this->assertTrue(true);
    }

    public function test_job_payload_carries_ids_not_models(): void
    {
        // Serializable: stores scalar ids + text, never full models.
        $job = new RehearsalPlannerTurnJob(7, 11, 'text');

        $this->assertSame(7, $job->sessionId);
        $this->assertSame(11, $job->assistantMessageId);
        $this->assertSame('text', $job->userText);
        $this->assertGreaterThan(120, $job->timeout, 'Timeout must exceed the agent 120s stream timeout.');
    }
}
