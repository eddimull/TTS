<?php

namespace Tests\Feature\RehearsalPlanner;

use App\Events\RehearsalPlannerStreamEvent;
use App\Models\RehearsalPlannerMessage;
use App\Models\RehearsalPlannerSession;
use App\Services\RehearsalPlannerContextBuilder;
use App\Services\RehearsalPlannerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PlannerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_parse_plan_and_suggestions_and_strip(): void
    {
        $text = "Here is my plan.\n".
            "```plan\n{\"title\":\"T\",\"items\":[{\"song_id\":1,\"title\":\"A\",\"reason\":\"r\"}]}\n```\n".
            "```suggestions\n[\"One\",\"Two\"]\n```";

        $plan = RehearsalPlannerService::parsePlan($text);
        $this->assertSame('T', $plan['title']);
        $this->assertSame(1, $plan['items'][0]['song_id']);

        $this->assertSame(['One', 'Two'], RehearsalPlannerService::parseSuggestions($text));

        $stripped = RehearsalPlannerService::stripBlocks($text);
        $this->assertStringNotContainsString('```plan', $stripped);
        $this->assertStringNotContainsString('```suggestions', $stripped);
        $this->assertStringContainsString('Here is my plan.', $stripped);
    }

    public function test_no_blocks_returns_null_and_empty(): void
    {
        $text = 'Just a normal reply.';
        $this->assertNull(RehearsalPlannerService::parsePlan($text));
        $this->assertSame([], RehearsalPlannerService::parseSuggestions($text));
        $this->assertSame('Just a normal reply.', RehearsalPlannerService::stripBlocks($text));
    }

    public function test_run_turn_error_path_marks_message_failed_and_dispatches_error(): void
    {
        Event::fake([RehearsalPlannerStreamEvent::class]);

        // Context build throws -> exercises the catch branch with no live AI call.
        $builder = $this->createMock(RehearsalPlannerContextBuilder::class);
        $builder->method('build')->willThrowException(new \RuntimeException('boom'));

        $service = new RehearsalPlannerService($builder);

        $session = RehearsalPlannerSession::factory()->create();
        $assistant = RehearsalPlannerMessage::factory()->create([
            'session_id' => $session->id,
            'role'       => 'assistant',
            'status'     => 'streaming',
        ]);

        $service->runTurn($session, $assistant, 'hello');

        $this->assertSame('failed', $assistant->fresh()->status);

        Event::assertDispatched(
            RehearsalPlannerStreamEvent::class,
            fn (RehearsalPlannerStreamEvent $e) => $e->type === 'error'
                && $e->sessionId === $session->id
                && ($e->data['message_id'] ?? null) === $assistant->id,
        );
    }
}
