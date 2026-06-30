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

    public function test_parse_plan_with_multiple_nested_items_and_strip(): void
    {
        // A multi-item plan: nested array of objects. The old brace-anchored
        // regex (`\{.*?\}`) stops at the first inner `}`, yielding invalid JSON
        // and silently dropping the plan. The fence-anchored regex must capture
        // the entire JSON body.
        $text = "Here is my plan.\n".
            "```plan\n".
            "{\"title\":\"Wedding\",\"items\":[".
            "{\"song_id\":1,\"title\":\"A\",\"reason\":\"r\"},".
            "{\"song_id\":null,\"title\":\"B\",\"reason\":\"r2\"}".
            "]}\n".
            "```";

        $plan = RehearsalPlannerService::parsePlan($text);
        $this->assertNotNull($plan, 'Multi-item plan must parse, not be dropped.');
        $this->assertSame('Wedding', $plan['title']);
        $this->assertCount(2, $plan['items']);
        $this->assertSame(1, $plan['items'][0]['song_id']);
        $this->assertNull($plan['items'][1]['song_id']);
        $this->assertSame('B', $plan['items'][1]['title']);

        $stripped = RehearsalPlannerService::stripBlocks($text);
        $this->assertStringNotContainsString('```plan', $stripped);
        // The fenced block's tail must be fully removed — no JSON leftovers.
        $this->assertStringNotContainsString('"items"', $stripped);
        $this->assertStringNotContainsString('}', $stripped);
        $this->assertStringNotContainsString(']', $stripped);
        $this->assertStringContainsString('Here is my plan.', $stripped);
    }

    public function test_parse_both_plan_and_suggestions_and_strip_both(): void
    {
        $text = "Some prose first.\n".
            "```plan\n".
            "{\"title\":\"T\",\"items\":[".
            "{\"song_id\":1,\"title\":\"A\",\"reason\":\"r\"},".
            "{\"song_id\":2,\"title\":\"B\",\"reason\":\"r2\"}".
            "]}\n".
            "```\n".
            "```suggestions\n[\"One\",\"Two\"]\n```";

        $plan = RehearsalPlannerService::parsePlan($text);
        $this->assertSame('T', $plan['title']);
        $this->assertCount(2, $plan['items']);
        $this->assertSame(2, $plan['items'][1]['song_id']);

        $this->assertSame(['One', 'Two'], RehearsalPlannerService::parseSuggestions($text));

        $stripped = RehearsalPlannerService::stripBlocks($text);
        $this->assertStringNotContainsString('```plan', $stripped);
        $this->assertStringNotContainsString('```suggestions', $stripped);
        $this->assertStringNotContainsString('"items"', $stripped);
        $this->assertStringNotContainsString('One', $stripped);
        $this->assertSame('Some prose first.', $stripped);
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
