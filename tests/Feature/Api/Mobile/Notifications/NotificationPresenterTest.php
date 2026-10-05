<?php

namespace Tests\Feature\Api\Mobile\Notifications;

use App\Models\Bandnotification;
use App\Models\QuestionnaireInstances;
use App\Models\Questionnaires;
use App\Models\User;
use App\Notifications\TTSNotification;
use App\Services\Chat\ConversationService;
use App\Services\Notifications\NotificationPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\Mobile\Chat\ChatTestHelpers;
use Tests\TestCase;

class NotificationPresenterTest extends TestCase
{
    use RefreshDatabase, ChatTestHelpers;

    /** Store a raw database notification row for $user with the given data. */
    private function row(User $user, array $data, array $attrs = []): Bandnotification
    {
        $user->notify(new TTSNotification($data));
        $row = Bandnotification::where('notifiable_id', $user->id)->latest('id')->first();
        if ($attrs) {
            $row->forceFill($attrs)->save();
            $row->refresh();
        }

        return $row;
    }

    private function presentFor(User $user, array $data): array
    {
        return app(NotificationPresenter::class)->present($this->row($user, $data), $user);
    }

    public function test_output_shape_and_timestamps(): void
    {
        [$owner] = $this->makeOwnerWithBand();
        $row = $this->row($owner, ['text' => 'hello'], ['read_at' => now(), 'seen_at' => now()]);

        $out = app(NotificationPresenter::class)->present($row, $owner);

        $this->assertSame(['id', 'kind', 'text', 'deeplink', 'web_url', 'read_at', 'seen_at', 'created_at'], array_keys($out));
        $this->assertSame($row->id, $out['id']);
        $this->assertSame('hello', $out['text']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', $out['read_at']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', $out['seen_at']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', $out['created_at']);
    }

    public function test_booking_details_assoc_params(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $booking = $this->makeBookingEvent($band)->eventable;

        $out = $this->presentFor($owner, [
            'text' => 'Payment received', 'route' => 'Booking Details',
            'routeParams' => ['band' => $band->id, 'booking' => $booking->id],
        ]);

        $this->assertSame('booking', $out['kind']);
        $this->assertSame("/bookings/{$band->id}/{$booking->id}", $out['deeplink']);
        $this->assertSame("/bands/{$band->id}/booking/{$booking->id}", $out['web_url']);
    }

    public function test_events_show_with_scalar_key_and_events_advance(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $event = $this->makeBookingEvent($band);

        foreach (['events.show', 'events.advance'] as $route) {
            $out = $this->presentFor($owner, ['text' => 'x', 'route' => $route, 'routeParams' => $event->key]);
            $this->assertSame('event', $out['kind'], $route);
            $this->assertSame("/events/{$event->key}", $out['deeplink'], $route);
            $this->assertSame("/events/{$event->key}", $out['web_url'], $route);
        }

        $assoc = $this->presentFor($owner, ['text' => 'x', 'route' => 'events.show', 'routeParams' => ['key' => $event->key]]);
        $this->assertSame("/events/{$event->key}", $assoc['deeplink']);
    }

    public function test_broken_event_details_route_resolves_through_the_event_id(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $event = $this->makeBookingEvent($band);

        $out = $this->presentFor($owner, [
            'text' => "Event status changed", 'route' => 'Event Details',
            'routeParams' => ['band' => $band->id, 'event' => $event->id],
            'url' => "/bands/{$band->id}/events/{$event->id}",
        ]);

        $this->assertSame('event', $out['kind']);
        $this->assertSame("/events/{$event->key}", $out['deeplink']);
        $this->assertSame("/events/{$event->key}", $out['web_url']);
    }

    public function test_rehearsal_cancelled_payload_has_no_route_but_has_rehearsal_id(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        [$rehearsal] = $this->makeRehearsalEvent($band);

        $out = $this->presentFor($owner, ['text' => 'Rehearsal cancelled', 'link' => '/rehearsal-schedules', 'rehearsal_id' => $rehearsal->id]);

        $this->assertSame('rehearsal', $out['kind']);
        $this->assertSame("/rehearsals/{$rehearsal->id}", $out['deeplink']);
        $this->assertSame("/bands/{$band->id}/rehearsal-schedules/{$rehearsal->rehearsal_schedule_id}/rehearsals/{$rehearsal->id}", $out['web_url']);
    }

    public function test_conversation_ids_win_over_route_for_comments_and_dms(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $event = $this->makeBookingEvent($band);
        $topic = app(ConversationService::class)->topicFor($event);

        $out = $this->presentFor($owner, [
            'text' => 'A commented on Test Gig: hi', 'route' => 'events.show',
            'routeParams' => ['key' => $event->key, 'comments' => 1], 'conversation_id' => $topic->id, 'message_id' => 1,
        ]);
        $this->assertSame('conversation', $out['kind']);
        $this->assertSame("/conversations/{$topic->id}", $out['deeplink']);
        $this->assertSame("/messages/{$topic->id}", $out['web_url']);

        $dm = $this->presentFor($owner, ['text' => 'B: yo', 'route' => 'messages.index', 'routeParams' => ['conversation' => 42], 'conversation_id' => 42, 'message_id' => 2]);
        $this->assertSame('/conversations/42', $dm['deeplink']);
    }

    public function test_questionnaire_submitted_resolves_to_the_instance_screen(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $booking = $this->makeBookingEvent($band)->eventable;
        $questionnaire = Questionnaires::factory()->create(['band_id' => $band->id]);
        $instance = QuestionnaireInstances::factory()->create(['questionnaire_id' => $questionnaire->id, 'booking_id' => $booking->id]);

        $out = $this->presentFor($owner, ['instance_id' => $instance->id, 'questionnaire_name' => 'Q', 'text' => 'Client submitted the Q', 'route' => 'dashboard', 'routeParams' => []]);

        $this->assertSame('questionnaire', $out['kind']);
        $this->assertSame("/questionnaires/{$questionnaire->id}/instances/{$instance->id}", $out['deeplink']);
    }

    public function test_band_routes_resolve_to_band_settings_for_owners_and_dashboard_for_others(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $member = $this->makeMember($band);

        foreach ([
            ['route' => 'bands.edit', 'routeParams' => $band->id, 'url' => "/bands/{$band->id}/edit"],
            ['route' => 'bands', 'routeParams' => null, 'url' => "/bands/{$band->id}/edit"],   // logo-upload shape
        ] as $data) {
            $data['text'] = 'band changed';
            $o = $this->presentFor($owner, $data);
            $this->assertSame('band', $o['kind']);
            $this->assertSame('/band-settings', $o['deeplink']);
            $this->assertSame("/bands/{$band->id}/edit", $o['web_url']);

            $m = $this->presentFor($member, $data);
            $this->assertSame('dashboard', $m['kind']);
            $this->assertSame('/dashboard', $m['deeplink']);
        }
    }

    public function test_dashboard_fallbacks_and_default_text(): void
    {
        [$owner] = $this->makeOwnerWithBand();

        $nullParams = $this->presentFor($owner, ['text' => 'sub invited', 'route' => 'dashboard', 'routeParams' => null]);
        $this->assertSame(['dashboard', '/dashboard', '/dashboard'], [$nullParams['kind'], $nullParams['deeplink'], $nullParams['web_url']]);

        $defaults = $this->presentFor($owner, []); // TTSNotification fills text '', route 'dashboard', routeParams ''
        $this->assertSame('dashboard', $defaults['kind']);
        $this->assertSame('New notification', $defaults['text']);

        $deleted = $this->presentFor($owner, ['text' => 'gone', 'route' => 'events.show', 'routeParams' => 'no-such-key']);
        $this->assertSame('dashboard', $deleted['kind']);
        $this->assertSame('/dashboard', $deleted['deeplink']);
    }

    public function test_url_patterns_are_used_when_route_is_unknown(): void
    {
        [$owner, $band] = $this->makeOwnerWithBand();
        $booking = $this->makeBookingEvent($band)->eventable;

        $out = $this->presentFor($owner, ['text' => 'x', 'route' => 'something.unknown', 'url' => "/bands/{$band->id}/booking/{$booking->id}"]);
        $this->assertSame("/bookings/{$band->id}/{$booking->id}", $out['deeplink']);
    }
}
