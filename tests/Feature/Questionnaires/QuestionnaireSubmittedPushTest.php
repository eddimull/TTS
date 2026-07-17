<?php

namespace Tests\Feature\Questionnaires;

use App\Jobs\SendUserPush;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\DeviceToken;
use App\Models\QuestionnaireInstances;
use App\Models\Questionnaires;
use App\Models\User;
use App\Notifications\QuestionnaireSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class QuestionnaireSubmittedPushTest extends TestCase
{
    use RefreshDatabase;

    private Bands $band;
    private User $owner1;
    private User $owner2;
    private Contacts $contact;
    private QuestionnaireInstances $instance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->band = Bands::factory()->create();
        $this->owner1 = User::factory()->create();
        $this->owner2 = User::factory()->create();
        $this->band->owners()->create(['user_id' => $this->owner1->id]);
        $this->band->owners()->create(['user_id' => $this->owner2->id]);

        $booking = Bookings::factory()->create(['band_id' => $this->band->id]);
        $this->contact = Contacts::factory()->create(['band_id' => $this->band->id, 'can_login' => true]);
        $booking->contacts()->attach($this->contact, ['is_primary' => true]);

        $template = Questionnaires::factory()->create(['band_id' => $this->band->id]);
        $this->instance = QuestionnaireInstances::create([
            'questionnaire_id' => $template->id,
            'booking_id' => $booking->id,
            'recipient_contact_id' => $this->contact->id,
            'sent_by_user_id' => $this->owner1->id,
            'name' => $template->name,
            'description' => '',
            'status' => QuestionnaireInstances::STATUS_SENT,
            'sent_at' => now(),
        ]);

        DeviceToken::create(['user_id' => $this->owner1->id, 'token' => 'tok-1', 'platform' => 'android']);
        // owner2 has no devices.
    }

    private function submitAsContact(): void
    {
        $this->actingAs($this->contact, 'contact')->post(
            route('portal.booking.questionnaire.submit', [
                'booking' => $this->instance->booking_id,
                'instance' => $this->instance->id,
            ])
        )->assertStatus(302);
    }

    public function test_submit_notifies_all_owners_and_pushes_to_device_holders(): void
    {
        Notification::fake();
        Bus::fake([SendUserPush::class]);

        $this->submitAsContact();

        Notification::assertSentTo($this->owner1, QuestionnaireSubmitted::class);
        Notification::assertSentTo($this->owner2, QuestionnaireSubmitted::class);

        Bus::assertDispatched(SendUserPush::class, function (SendUserPush $job) {
            return $job->userId === $this->owner1->id
                && $job->alert === true
                && $job->data['type'] === 'questionnaire_submitted'
                && $job->data['instanceId'] === (string) $this->instance->id
                && $job->data['questionnaireId'] === (string) $this->instance->questionnaire_id
                && str_contains($job->data['title'], 'submitted');
        });
        Bus::assertNotDispatched(SendUserPush::class, fn (SendUserPush $job) => $job->userId === $this->owner2->id);
    }

    public function test_resubmit_pushes_with_updated_wording(): void
    {
        $this->instance->update(['status' => QuestionnaireInstances::STATUS_SUBMITTED, 'submitted_at' => now()->subHour()]);
        Notification::fake();
        Bus::fake([SendUserPush::class]);

        $this->submitAsContact();

        Bus::assertDispatched(SendUserPush::class, fn (SendUserPush $job) =>
            $job->userId === $this->owner1->id && str_contains($job->data['title'], 'updated'));
    }
}
