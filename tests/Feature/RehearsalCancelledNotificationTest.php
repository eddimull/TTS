<?php

namespace Tests\Feature;

use App\Models\Bands;
use App\Models\Rehearsal;
use App\Models\RehearsalSchedule;
use App\Models\User;
use App\Notifications\RehearsalCancelled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RehearsalCancelledNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function makeRehearsal(): Rehearsal
    {
        $band = Bands::factory()->create();
        $schedule = RehearsalSchedule::factory()->weekly()->create([
            'band_id' => $band->id,
            'name'    => 'Tuesday Practice',
        ]);

        return Rehearsal::factory()->create([
            'rehearsal_schedule_id' => $schedule->id,
            'band_id'               => $band->id,
        ]);
    }

    public function test_channels_are_database_plus_mail_when_email_enabled(): void
    {
        $rehearsal = $this->makeRehearsal();
        $emailOn  = User::factory()->create(['emailNotifications' => true]);
        $emailOff = User::factory()->create(['emailNotifications' => false]);

        $n = new RehearsalCancelled($rehearsal, true, now()->addDays(3)->toDateString());

        $this->assertEqualsCanonicalizing(['database', 'mail'], $n->via($emailOn));
        $this->assertSame(['database'], $n->via($emailOff));
    }

    public function test_cancelled_headline_and_payload(): void
    {
        $rehearsal = $this->makeRehearsal();
        $date = now()->addDays(3)->toDateString();

        $n = new RehearsalCancelled($rehearsal, true, $date);
        $payload = $n->toArray(User::factory()->create());

        $this->assertStringContainsString('Tuesday Practice', $payload['text']);
        $this->assertStringContainsString('cancelled', $payload['text']);
        $this->assertSame($rehearsal->id, $payload['rehearsal_id']);
        $this->assertTrue($payload['is_cancelled']);
        $this->assertSame($date, $payload['date']);
        $this->assertSame('/rehearsal-schedules', $payload['link']);
    }

    public function test_restored_headline(): void
    {
        $rehearsal = $this->makeRehearsal();
        $n = new RehearsalCancelled($rehearsal, false, now()->addDays(3)->toDateString());

        $this->assertStringContainsString('back on', $n->toArray(User::factory()->create())['text']);
    }
}
