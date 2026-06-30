<?php

namespace Tests\Feature\RehearsalPlanner;

use App\Ai\Agents\RehearsalPlannerAgent;
use App\Events\RehearsalPlannerStreamEvent;
use App\Models\RehearsalPlannerMessage;
use App\Models\RehearsalPlannerSession;
use App\Services\RehearsalPlannerContextBuilder;
use App\Services\RehearsalPlannerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Ai\Messages\Message;
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

    public function test_history_excludes_current_user_turn_but_keeps_prior_turns(): void
    {
        // Fix 2 invariant: history carries only PRIOR complete turns; the
        // current user turn is carried solely by the prompt, so it must NOT be
        // replayed in history (otherwise the agent sees it twice).
        $session = RehearsalPlannerSession::factory()->create();

        // A prior, complete user+assistant exchange.
        $priorUser = RehearsalPlannerMessage::factory()->create([
            'session_id' => $session->id,
            'role'       => 'user',
            'content'    => 'Prior question',
            'status'     => 'complete',
        ]);
        $priorAssistant = RehearsalPlannerMessage::factory()->create([
            'session_id' => $session->id,
            'role'       => 'assistant',
            'content'    => 'Prior answer',
            'status'     => 'complete',
        ]);

        // The current turn: a new user message followed by the assistant
        // placeholder (mirrors the controller's create order).
        $currentUser = RehearsalPlannerMessage::factory()->create([
            'session_id' => $session->id,
            'role'       => 'user',
            'content'    => 'Current question',
            'status'     => 'complete',
        ]);
        $assistant = RehearsalPlannerMessage::factory()->create([
            'session_id' => $session->id,
            'role'       => 'assistant',
            'status'     => 'streaming',
        ]);

        $service = new class (app(RehearsalPlannerContextBuilder::class)) extends RehearsalPlannerService {
            public function exposeHistory(
                RehearsalPlannerSession $session,
                RehearsalPlannerMessage $assistant,
                ?string $userText,
                ?int $userMessageId = null,
            ): array {
                return $this->historyForTurn($session, $assistant, $userText, $userMessageId);
            }
        };

        /** @var Message[] $history */
        $history = $service->exposeHistory($session, $assistant, 'Current question', $currentUser->id);

        $contents = array_map(fn (Message $m) => (string) $m->content, $history);

        $this->assertContains('Prior question', $contents);
        $this->assertContains('Prior answer', $contents);
        $this->assertNotContains('Current question', $contents, 'Current user turn must NOT be replayed in history.');
        $this->assertCount(2, $history, 'History should contain only the two prior turns.');
    }

    public function test_start_turn_history_excludes_only_assistant_placeholder(): void
    {
        // The opening start() turn has no user text; prior complete turns (if
        // any) are replayed and only the assistant placeholder is excluded.
        $session = RehearsalPlannerSession::factory()->create();

        $priorUser = RehearsalPlannerMessage::factory()->create([
            'session_id' => $session->id,
            'role'       => 'user',
            'content'    => 'Earlier message',
            'status'     => 'complete',
        ]);
        $assistant = RehearsalPlannerMessage::factory()->create([
            'session_id' => $session->id,
            'role'       => 'assistant',
            'status'     => 'streaming',
        ]);

        $service = new class (app(RehearsalPlannerContextBuilder::class)) extends RehearsalPlannerService {
            public function exposeHistory(
                RehearsalPlannerSession $session,
                RehearsalPlannerMessage $assistant,
                ?string $userText,
                ?int $userMessageId = null,
            ): array {
                return $this->historyForTurn($session, $assistant, $userText, $userMessageId);
            }
        };

        $history = $service->exposeHistory($session, $assistant, null, null);
        $contents = array_map(fn (Message $m) => (string) $m->content, $history);

        $this->assertSame(['Earlier message'], $contents);
    }

    public function test_run_turn_happy_path_streams_and_persists(): void
    {
        // Fix 2: live streaming happy-path — uses laravel/ai's built-in
        // FakeTextGateway (RehearsalPlannerAgent::fake()) which drives the
        // full stream→accumulate→persist→done flow without a live AI call.
        Event::fake([RehearsalPlannerStreamEvent::class]);

        $fakeResponse = "Here is your plan.\n" .
            "```plan\n" .
            "{\"title\":\"Wedding Prep\",\"items\":[{\"song_id\":1,\"title\":\"Song A\",\"reason\":\"popular\"}]}\n" .
            "```\n" .
            "```suggestions\n[\"Draft another plan\",\"Explore new songs\"]\n```";

        RehearsalPlannerAgent::fake([$fakeResponse]);

        $session = RehearsalPlannerSession::factory()->create();
        $assistant = RehearsalPlannerMessage::factory()->create([
            'session_id' => $session->id,
            'role'       => 'assistant',
            'status'     => 'streaming',
        ]);

        $service = app(RehearsalPlannerService::class);
        $service->runTurn($session, $assistant, 'What should we rehearse?');

        $fresh = $assistant->fresh();

        // Message must be complete with stripped visible text (no fenced blocks).
        $this->assertSame('complete', $fresh->status);
        $this->assertStringContainsString('Here is your plan.', $fresh->content);
        $this->assertStringNotContainsString('```plan', $fresh->content);
        $this->assertStringNotContainsString('```suggestions', $fresh->content);

        // Payload must contain the parsed plan and suggestions.
        $payload = $fresh->payload;
        $this->assertSame('Wedding Prep', $payload['plan']['title'] ?? null);
        $this->assertCount(1, $payload['plan']['items'] ?? []);
        $this->assertSame(['Draft another plan', 'Explore new songs'], $payload['suggestions'] ?? []);

        // At least one text_delta event and exactly one done event must be dispatched.
        Event::assertDispatched(
            RehearsalPlannerStreamEvent::class,
            fn (RehearsalPlannerStreamEvent $e) => $e->type === 'text_delta'
                && $e->sessionId === $session->id,
        );
        Event::assertDispatched(
            RehearsalPlannerStreamEvent::class,
            fn (RehearsalPlannerStreamEvent $e) => $e->type === 'done'
                && $e->sessionId === $session->id
                && ($e->data['message_id'] ?? null) === $assistant->id,
        );
    }
}
