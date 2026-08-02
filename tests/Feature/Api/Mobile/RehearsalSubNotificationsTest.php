<?php

namespace Tests\Feature\Api\Mobile;

use App\Jobs\ProcessRehearsalSubAdded;
use App\Jobs\SendUserPush;
use App\Mail\RehearsalSubAdded;
use App\Mail\RehearsalSubNotice;
use App\Models\BandRole;
use App\Models\Bands;
use App\Models\DeviceToken;
use App\Models\Events;
use App\Models\EventTypes;
use App\Models\Rehearsal;
use App\Models\RehearsalSchedule;
use App\Models\RehearsalSub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RehearsalSubNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function createRehearsalWithSub(?User $subUser = null): array
    {
        $owner = User::factory()->create();
        $band  = Bands::factory()->create();
        $band->owners()->create(['user_id' => $owner->id]);

        $schedule  = RehearsalSchedule::factory()->weekly()->create(['band_id' => $band->id]);
        $rehearsal = Rehearsal::factory()->create([
            'rehearsal_schedule_id' => $schedule->id,
            'band_id'               => $band->id,
        ]);
        Events::factory()->create([
            'eventable_id'   => $rehearsal->id,
            'eventable_type' => 'App\\Models\\Rehearsal',
            'event_type_id'  => EventTypes::factory()->create()->id,
            'date'           => now()->addDays(7)->format('Y-m-d'),
            'start_time'     => '19:00:00',
        ]);

        $sub = RehearsalSub::factory()->create([
            'rehearsal_id' => $rehearsal->id,
            'band_id'      => $band->id,
            'user_id'      => $subUser?->id,
            'email'        => $subUser?->email ?? 'adhoc@example.com',
            'invited_by'   => $owner->id,
        ]);

        return compact('owner', 'band', 'rehearsal', 'sub');
    }

    public function test_store_endpoint_dispatches_added_job(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $band  = Bands::factory()->create();
        $band->owners()->create(['user_id' => $owner->id]);
        $schedule  = RehearsalSchedule::factory()->weekly()->create(['band_id' => $band->id]);
        $rehearsal = Rehearsal::factory()->create([
            'rehearsal_schedule_id' => $schedule->id,
            'band_id'               => $band->id,
        ]);
        Events::factory()->create([
            'eventable_id'   => $rehearsal->id,
            'eventable_type' => 'App\\Models\\Rehearsal',
            'event_type_id'  => EventTypes::factory()->create()->id,
            'date'           => now()->addDays(7)->format('Y-m-d'),
        ]);
        $token = $owner->createToken('t')->plainTextToken;

        $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->postJson("/api/mobile/rehearsals/{$rehearsal->id}/subs", [
                'name' => 'Pat', 'email' => 'pat@example.com',
            ])
            ->assertCreated();

        Queue::assertPushed(ProcessRehearsalSubAdded::class);
    }

    public function test_added_job_emails_adhoc_invitee_without_push(): void
    {
        Mail::fake();
        Queue::fake();

        ['sub' => $sub, 'owner' => $owner] = $this->createRehearsalWithSub();

        (new ProcessRehearsalSubAdded($sub, $owner->id, 'test-dedupe'))->handle();

        Mail::assertSent(RehearsalSubAdded::class,
            fn ($mail) => $mail->hasTo('adhoc@example.com'));
        Queue::assertNotPushed(SendUserPush::class);
    }

    public function test_added_job_emails_and_pushes_registered_sub_with_device(): void
    {
        Mail::fake();
        Queue::fake();

        $subUser = User::factory()->create();
        DeviceToken::factory()->create(['user_id' => $subUser->id]);

        ['sub' => $sub, 'owner' => $owner] = $this->createRehearsalWithSub($subUser);

        (new ProcessRehearsalSubAdded($sub, $owner->id, 'test-dedupe'))->handle();

        Mail::assertSent(RehearsalSubAdded::class,
            fn ($mail) => $mail->hasTo($subUser->email));
        Queue::assertPushed(SendUserPush::class);
    }

    /**
     * Renders a mailable and flattens it to plain text with collapsed
     * whitespace, so assertions can target copy rather than markup.
     */
    private function renderText(\Illuminate\Mail\Mailable $mail): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags($mail->render())));
    }

    public function test_added_mailable_renders_with_role_suffix(): void
    {
        ['sub' => $sub, 'rehearsal' => $rehearsal, 'band' => $band] = $this->createRehearsalWithSub();

        // BandObserver seeds the default roles (incl. "Drums") on band
        // creation, so reuse that row rather than inserting a duplicate —
        // band_roles has a unique (band_id, name) index.
        $role = BandRole::where('band_id', $band->id)->where('name', 'Drums')->firstOrFail();
        $sub->update(['band_role_id' => $role->id]);
        $sub->refresh();

        $text = $this->renderText(
            new RehearsalSubAdded($sub, $rehearsal, $band, '2026-08-10')
        );

        $this->assertStringContainsString($band->name, $text);
        $this->assertStringContainsString($sub->name, $text);
        $this->assertStringContainsString('as a substitute (Drums) for a rehearsal', $text);
        $this->assertStringContainsString('Monday, August 10, 2026', $text);
    }

    public function test_added_mailable_renders_without_role_and_has_no_empty_parens(): void
    {
        ['sub' => $sub, 'rehearsal' => $rehearsal, 'band' => $band] = $this->createRehearsalWithSub();

        $this->assertNull($sub->band_role_id);

        $text = $this->renderText(
            new RehearsalSubAdded($sub, $rehearsal, $band, '2026-08-10')
        );

        $this->assertStringContainsString($band->name, $text);
        $this->assertStringContainsString('as a substitute for a rehearsal', $text);
        $this->assertStringNotContainsString('()', $text);
    }

    public function test_added_mailable_renders_with_null_date(): void
    {
        ['sub' => $sub, 'rehearsal' => $rehearsal, 'band' => $band] = $this->createRehearsalWithSub();

        $text = $this->renderText(
            new RehearsalSubAdded($sub, $rehearsal, $band, null)
        );

        $this->assertStringContainsString('Date: TBD', $text);
    }

    public function test_notice_mailable_renders_band_name_and_body(): void
    {
        $mail = new RehearsalSubNotice(
            'You have been removed from a rehearsal',
            'Your substitute spot for Friday has been cancelled.',
            'The Testing Band',
        );

        $text = $this->renderText($mail);

        $this->assertStringContainsString('The Testing Band', $text);
        $this->assertStringContainsString('Your substitute spot for Friday has been cancelled.', $text);
    }
}
