<?php

namespace Tests\Feature\RehearsalPlanner;

use App\Jobs\RehearsalPlannerTurnJob;
use App\Models\Bands;
use App\Models\RehearsalPlannerSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PlannerControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a user who owns the band, authenticated via Sanctum.
     *
     * The `mobile.band:read:rehearsals` middleware (EnsureUserInBand) has
     * three gates: (1) X-Band-ID header present, (2) band membership via
     * allBands(), (3) Sanctum tokenCan('read:rehearsals'). Owning the band
     * satisfies membership; a plain createToken() grants ['*'] abilities so
     * tokenCan passes. The X-Band-ID header (and bearer token) must be sent
     * on every request (see headers()). This mirrors
     * tests/Feature/Api/Mobile/RehearsalsTest.php exactly.
     */
    private function actingMember(): array
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);

        $token = $user->createToken('test-device')->plainTextToken;
        config(['services.anthropic.key' => 'test-key']);

        return [$user, $band, $token];
    }

    private function headers(Bands $band): array
    {
        return ['X-Band-ID' => $band->id];
    }

    public function test_start_creates_session_and_placeholder_and_dispatches_job(): void
    {
        // The turn runs on the queue (Fix 1): the request returns the channel
        // immediately, before any broadcast, and pushes the job.
        Queue::fake();

        [$user, $band, $token] = $this->actingMember();

        $res = $this->withToken($token)->postJson(
            "/api/mobile/bands/{$band->id}/rehearsal-planner/sessions",
            [],
            $this->headers($band)
        );

        $res->assertOk()->assertJsonStructure(['session_id', 'channel', 'assistant_message_id']);
        $this->assertSame('private-rehearsal-planner.' . $res->json('session_id'), $res->json('channel'));
        $this->assertDatabaseHas('rehearsal_planner_sessions', [
            'id'      => $res->json('session_id'),
            'band_id' => $band->id,
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('rehearsal_planner_messages', [
            'id'     => $res->json('assistant_message_id'),
            'role'   => 'assistant',
            'status' => 'streaming',
        ]);

        Queue::assertPushed(
            RehearsalPlannerTurnJob::class,
            fn (RehearsalPlannerTurnJob $job) => $job->sessionId === (int) $res->json('session_id')
                && $job->assistantMessageId === (int) $res->json('assistant_message_id')
                && $job->userText === null
                && $job->userMessageId === null,
        );
    }

    public function test_message_persists_user_turn_and_dispatches_job(): void
    {
        Queue::fake();

        [$user, $band, $token] = $this->actingMember();
        $session = RehearsalPlannerSession::factory()->create([
            'band_id' => $band->id,
            'user_id' => $user->id,
        ]);

        $res = $this->withToken($token)->postJson(
            "/api/mobile/bands/{$band->id}/rehearsal-planner/sessions/{$session->id}/messages",
            ['text' => 'What should we rehearse?'],
            $this->headers($band)
        );

        $res->assertOk()->assertJsonStructure([
            'user_message' => ['id', 'role', 'content'],
            'assistant_message_id',
            'channel',
        ]);
        $this->assertDatabaseHas('rehearsal_planner_messages', [
            'session_id' => $session->id,
            'role'       => 'user',
            'content'    => 'What should we rehearse?',
        ]);

        Queue::assertPushed(
            RehearsalPlannerTurnJob::class,
            fn (RehearsalPlannerTurnJob $job) => $job->sessionId === $session->id
                && $job->assistantMessageId === (int) $res->json('assistant_message_id')
                && $job->userText === 'What should we rehearse?'
                && $job->userMessageId === (int) $res->json('user_message.id'),
        );
    }

    public function test_missing_api_key_returns_503(): void
    {
        [$user, $band, $token] = $this->actingMember();
        config(['services.anthropic.key' => null]);

        $this->withToken($token)->postJson(
            "/api/mobile/bands/{$band->id}/rehearsal-planner/sessions",
            [],
            $this->headers($band)
        )
            ->assertStatus(503)
            ->assertJson(['error' => 'Anthropic API key not configured.']);
    }

    public function test_requires_auth(): void
    {
        $band = Bands::factory()->create();
        $this->postJson("/api/mobile/bands/{$band->id}/rehearsal-planner/sessions")
            ->assertUnauthorized();
    }

    public function test_show_for_session_from_another_band_returns_404(): void
    {
        [$user, $band, $token] = $this->actingMember();

        // A session belonging to a DIFFERENT band the user also owns
        // (so route membership passes) but mismatched against the URL band.
        $otherBand = Bands::factory()->create();
        $otherBand->owners()->create(['user_id' => $user->id]);
        $session = RehearsalPlannerSession::factory()->create([
            'band_id' => $otherBand->id,
            'user_id' => $user->id,
        ]);

        // URL + header use $band, but the session belongs to $otherBand → abort_unless 404.
        $this->withToken($token)->getJson(
            "/api/mobile/bands/{$band->id}/rehearsal-planner/sessions/{$session->id}",
            $this->headers($band)
        )->assertNotFound();
    }

    public function test_message_for_session_from_another_band_returns_404(): void
    {
        Queue::fake();

        [$user, $band, $token] = $this->actingMember();

        $otherBand = Bands::factory()->create();
        $otherBand->owners()->create(['user_id' => $user->id]);
        $session = RehearsalPlannerSession::factory()->create([
            'band_id' => $otherBand->id,
            'user_id' => $user->id,
        ]);

        $this->withToken($token)->postJson(
            "/api/mobile/bands/{$band->id}/rehearsal-planner/sessions/{$session->id}/messages",
            ['text' => 'cross-band attempt'],
            $this->headers($band)
        )->assertNotFound();

        Queue::assertNothingPushed();
    }

    public function test_start_with_valid_rehearsal_id_persists_it_on_the_session(): void
    {
        Queue::fake();

        [$user, $band, $token] = $this->actingMember();

        $schedule = \App\Models\RehearsalSchedule::factory()->create(['band_id' => $band->id]);
        $rehearsal = \App\Models\Rehearsal::factory()->create([
            'band_id'               => $band->id,
            'rehearsal_schedule_id' => $schedule->id,
        ]);

        $res = $this->withToken($token)->postJson(
            "/api/mobile/bands/{$band->id}/rehearsal-planner/sessions",
            ['rehearsal_id' => $rehearsal->id],
            $this->headers($band)
        );

        $res->assertOk();
        $this->assertDatabaseHas('rehearsal_planner_sessions', [
            'id'           => $res->json('session_id'),
            'band_id'      => $band->id,
            'rehearsal_id' => $rehearsal->id,
        ]);
    }

    public function test_start_rejects_soft_deleted_rehearsal(): void
    {
        Queue::fake();

        [$user, $band, $token] = $this->actingMember();

        // A trashed rehearsal must not be plannable: exists() ignores the
        // SoftDeletes global scope, so the rule must exclude deleted rows or it
        // would persist a dangling rehearsal_id that resolves to null focus.
        $schedule = \App\Models\RehearsalSchedule::factory()->create(['band_id' => $band->id]);
        $rehearsal = \App\Models\Rehearsal::factory()->create([
            'band_id'               => $band->id,
            'rehearsal_schedule_id' => $schedule->id,
        ]);
        $rehearsal->delete();

        $this->withToken($token)->postJson(
            "/api/mobile/bands/{$band->id}/rehearsal-planner/sessions",
            ['rehearsal_id' => $rehearsal->id],
            $this->headers($band)
        )->assertStatus(422)->assertJsonValidationErrors('rehearsal_id');

        Queue::assertNothingPushed();
    }

    public function test_start_rejects_rehearsal_id_from_another_band(): void
    {
        Queue::fake();

        [$user, $band, $token] = $this->actingMember();

        // A rehearsal that belongs to a DIFFERENT band must not be plannable
        // under this band's route — the scoped exists rule rejects it (422).
        $otherBand = Bands::factory()->create();
        $otherSchedule = \App\Models\RehearsalSchedule::factory()->create(['band_id' => $otherBand->id]);
        $otherRehearsal = \App\Models\Rehearsal::factory()->create([
            'band_id'               => $otherBand->id,
            'rehearsal_schedule_id' => $otherSchedule->id,
        ]);

        $this->withToken($token)->postJson(
            "/api/mobile/bands/{$band->id}/rehearsal-planner/sessions",
            ['rehearsal_id' => $otherRehearsal->id],
            $this->headers($band)
        )->assertStatus(422)->assertJsonValidationErrors('rehearsal_id');

        Queue::assertNothingPushed();
    }
}
