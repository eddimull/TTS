<?php

namespace Tests\Feature\Api\Mobile\Notifications;

use App\Jobs\ProcessEventUpdated;
use App\Models\Bandnotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Api\Mobile\Chat\ChatTestHelpers;
use Tests\TestCase;

class PayloadCorrectionsTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    public function test_event_status_change_notification_names_a_real_web_route(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $event = $this->makeBookingEvent($band);
        // Events has no `status` column (status lives on the eventable, e.g.
        // Bookings) and no `band` relation (band is reached via eventable);
        // SendNotification() nonetheless reads $this->event->status and
        // $this->event->band directly off the Events model. Both reads are
        // pre-existing bugs outside this task's 3-key scope (route /
        // routeParams / url only) — persisting 'status' throws a
        // QueryException (unknown column) and leaving 'band' unset throws
        // "Attempt to read property on null". Set both as plain in-memory
        // attributes (no ->save()) so the status-change branch under test
        // can run without masking or touching either unrelated bug.
        $event->forceFill(['status' => 'confirmed']);
        $event->band = $band;

        // Mirror the observer: originalData carries the previous status.
        (new ProcessEventUpdated($event, ['status' => 'pending']))->SendNotification();

        $row = Bandnotification::where('notifiable_id', $owner->id)->latest('id')->firstOrFail();
        $this->assertSame('events.show', $row->data['route']);
        $this->assertSame(['key' => $event->key], $row->data['routeParams']);
        $this->assertSame("/events/{$event->key}", $row->data['url']);
        $this->assertTrue(Route::has($row->data['route']));
    }
}
