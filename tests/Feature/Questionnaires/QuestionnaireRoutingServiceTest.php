<?php

namespace Tests\Feature\Questionnaires;

use App\Exceptions\QuestionnaireRoutingException;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\Events;
use App\Models\QuestionnaireApplications;
use App\Models\QuestionnaireFields;
use App\Models\QuestionnaireInstanceFields;
use App\Models\QuestionnaireInstances;
use App\Models\QuestionnaireResponses;
use App\Models\Questionnaires;
use App\Models\Song;
use App\Models\User;
use App\Services\ClientSongRequestsService;
use App\Services\QuestionnaireRoutingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionnaireRoutingServiceTest extends TestCase
{
    use RefreshDatabase;

    private QuestionnaireRoutingService $service;
    private Bands $band;
    private Bookings $booking;
    private Events $event;
    private Questionnaires $template;
    private QuestionnaireInstances $instance;
    private User $user;
    private int $position = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(QuestionnaireRoutingService::class);
        $this->user = User::factory()->create();
        $this->band = Bands::factory()->create();
        $this->booking = Bookings::factory()->create(['band_id' => $this->band->id]);
        $this->event = Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $this->booking->id,
            'venue_name' => 'Il Mercato',
            'notes' => 'Load in at 3',
            'additional_data' => [
                'attire' => 'All black',
                'wedding' => ['onsite' => 0, 'dances' => [['title' => 'First Dance', 'data' => 'TBD']]],
            ],
        ]);
        $this->template = Questionnaires::factory()->create(['band_id' => $this->band->id]);
        $this->instance = QuestionnaireInstances::factory()->submitted()->create([
            'booking_id' => $this->booking->id,
            'questionnaire_id' => $this->template->id,
        ]);
    }

    private function answer(string $type, string $label, ?string $value, ?string $target = null, array $settings = null): QuestionnaireResponses
    {
        $this->position += 10;
        $source = QuestionnaireFields::factory()->create([
            'questionnaire_id' => $this->template->id,
            'type' => $type,
            'label' => $label,
            'position' => $this->position,
            'mapping_target' => $target,
            'settings' => $settings,
        ]);
        $field = QuestionnaireInstanceFields::factory()->create([
            'instance_id' => $this->instance->id,
            'source_field_id' => $source->id,
            'type' => $type,
            'label' => $label,
            'position' => $this->position,
            'mapping_target' => $target,
            'settings' => $settings,
        ]);

        return QuestionnaireResponses::create([
            'instance_id' => $this->instance->id,
            'instance_field_id' => $field->id,
            'value' => $value,
        ]);
    }

    private function items(array $preview): array
    {
        return collect($preview['groups'])->flatMap(fn ($g) => $g['items'])->keyBy('response_id')->all();
    }

    private function eventData(): array
    {
        return json_decode(json_encode($this->event->fresh()->additional_data), true);
    }

    public function test_preview_groups_items_by_target_group_in_display_order(): void
    {
        $first = $this->answer('short_text', 'First Dance', 'Forever and Ever, Amen by Randy Travis', 'wedding.dance.first');
        $anything = $this->answer('long_text', 'Anything else we should know?', 'No slow songs', 'performance.notes');
        $colors = $this->answer('long_text', 'Wedding colors', 'Sage and gold', 'wedding.colors');
        $onsite = $this->answer('yes_no', 'Will the ceremony be onsite?', 'yes', 'wedding.onsite');

        $preview = $this->service->preview($this->instance);

        $this->assertSame(['timeline', 'performance', 'details'], array_column($preview['groups'], 'key'));
        $this->assertSame(['Timeline', 'Performance', 'Details'], array_column($preview['groups'], 'label'));

        $timeline = $preview['groups'][0]['items'][0];
        $this->assertSame($first->id, $timeline['response_id']);
        $this->assertSame('First Dance', $timeline['field_label']);
        $this->assertSame('Forever and Ever, Amen by Randy Travis', $timeline['value_display']);
        $this->assertSame('wedding.dance.first', $timeline['target']);
        $this->assertSame('First dance', $timeline['target_label']);
        $this->assertSame('pending', $timeline['status']);
        $this->assertTrue($timeline['default_checked']);
        $this->assertContains(['key' => 'wedding.toasts', 'label' => 'Toasts'], $timeline['compatible_targets']);

        $details = collect($preview['groups'][2]['items'])->pluck('response_id')->all();
        $this->assertSame([$colors->id, $onsite->id], $details);
        $this->assertSame('Yes', $this->items($preview)[$onsite->id]['value_display']);
        $this->assertSame($anything->id, $preview['groups'][1]['items'][0]['response_id']);
        $this->assertSame($this->event->id, $preview['event_id']);
    }

    public function test_preview_routed_target_overrides_field_mapping(): void
    {
        $response = $this->answer('long_text', 'Anything else we should know?', 'No slow songs', 'performance.notes');
        $response->update(['routed_target' => 'event.notes']);

        $item = $this->items($this->service->preview($this->instance))[$response->id];

        $this->assertSame('event.notes', $item['target']);
    }

    public function test_preview_placeholder_answers_start_unchecked(): void
    {
        $ids = [];
        foreach (['TBD', ' n/a ', 'None', '?', '-', '', 'tbd'] as $i => $value) {
            $ids[] = $this->answer('short_text', "Dance {$i}", $value, 'wedding.dance.money')->id;
        }
        $real = $this->answer('short_text', 'Money Dance song', 'Gold Digger', 'wedding.dance.money');

        $items = $this->items($this->service->preview($this->instance));

        foreach ($ids as $id) {
            $this->assertFalse($items[$id]['default_checked'], "response {$id} should be unchecked");
        }
        $this->assertTrue($items[$real->id]['default_checked']);
    }

    public function test_preview_status_reflects_applied_and_stale_answers(): void
    {
        $applied = $this->answer('short_text', 'First Dance', 'Song A', 'wedding.dance.first');
        $applied->forceFill(['applied_to_event_at' => now(), 'updated_at' => now()])->save();
        $stale = $this->answer('short_text', 'Money Dance song', 'Song B', 'wedding.dance.money');
        $stale->forceFill(['applied_to_event_at' => now()->subHour(), 'updated_at' => now()])->save();

        $items = $this->items($this->service->preview($this->instance));

        $this->assertSame('applied', $items[$applied->id]['status']);
        $this->assertFalse($items[$applied->id]['default_checked']);
        $this->assertSame('stale', $items[$stale->id]['status']);
        $this->assertTrue($items[$stale->id]['default_checked']);
    }

    public function test_preview_flags_a_song_on_both_lists_and_counts_requests(): void
    {
        $both = Song::factory()->create(['band_id' => $this->band->id, 'title' => 'We Are Family']);
        $must = Song::factory()->create(['band_id' => $this->band->id]);
        $skip = Song::factory()->create(['band_id' => $this->band->id]);
        $this->answer('song_picker', 'Must-play songs from our list', json_encode([$both->id, $must->id]), null, ['purpose' => 'must_play']);
        $this->answer('song_picker', 'Do-not-play songs from our list', json_encode([$both->id, $skip->id]), null, ['purpose' => 'do_not_play']);
        $this->answer('long_text', 'Other must-play songs / artists not on our list', "September\n\nAnything by Prince\nTBD");
        $this->answer('long_text', 'Other do-not-play songs / artists not on our list', 'Chicken dance');

        $preview = $this->service->preview($this->instance);

        $this->assertSame([[
            'kind' => 'song_both_lists',
            'song_id' => $both->id,
            'title' => 'We Are Family',
            'artist' => (string) $both->artist,
            'resolution' => null,
        ]], $preview['conflicts']);
        $this->assertSame(2, $preview['song_requests']['must_play']);
        $this->assertSame(2, $preview['song_requests']['do_not_play']);
        $this->assertSame(['September', 'Anything by Prince'], $preview['song_requests']['write_ins']);
        $this->assertSame(['Chicken dance'], $preview['song_requests']['do_not_play_write_ins']);
        // Write-ins are informational, never "unrouted".
        $this->assertSame([], $preview['unrouted']);

        $this->instance->update(['song_overrides' => [(string) $both->id => 'must_play']]);
        $this->assertSame('must_play', $this->service->preview($this->instance->fresh())['conflicts'][0]['resolution']);
    }

    public function test_preview_reports_venue_mismatch_including_i_l_confusion(): void
    {
        $venue = $this->answer('short_text', 'Venue Name', 'II Mercato');

        $conflicts = $this->service->preview($this->instance)['conflicts'];

        $this->assertSame([[
            'kind' => 'venue_mismatch',
            'response_id' => $venue->id,
            'booking_value' => 'Il Mercato',
            'form_value' => 'II Mercato',
            'resolution' => null,
        ]], $conflicts);
    }

    public function test_preview_ignores_case_and_whitespace_only_venue_differences(): void
    {
        $this->answer('short_text', 'Venue Name', '  il   MERCATO ');

        $this->assertSame([], $this->service->preview($this->instance)['conflicts']);
    }

    public function test_preview_contacts_and_unrouted(): void
    {
        $grace = Contacts::factory()->create(['band_id' => $this->band->id, 'name' => 'Grace Terreson', 'email' => 'grace@example.com', 'phone' => '(504) 555-1234']);
        $this->booking->contacts()->attach($grace, ['is_primary' => true]);
        $bride = $this->answer('short_text', "Bride's Full Name", 'Jane Doe');
        $this->answer('email', 'Best contact email', 'GRACE@example.com');
        $this->answer('phone', 'Best contact phone', '504-555-1234');
        $this->answer('email', 'Second email', 'new@example.com');
        $this->answer('short_text', 'Unanswered', '');

        $preview = $this->service->preview($this->instance);

        $this->assertSame([
            ['name' => 'Grace Terreson', 'email' => 'GRACE@example.com', 'phone' => '504-555-1234', 'on_booking' => true, 'contact_id' => $grace->id],
            ['name' => 'Jane Doe', 'email' => 'new@example.com', 'phone' => '', 'on_booking' => false, 'contact_id' => null],
        ], $preview['contacts']);

        $this->assertCount(1, $preview['unrouted']);
        $this->assertSame($bride->id, $preview['unrouted'][0]['response_id']);
        $this->assertSame("Bride's Full Name", $preview['unrouted'][0]['field_label']);
        $this->assertSame('Jane Doe', $preview['unrouted'][0]['value_display']);
        $targets = collect($preview['unrouted'][0]['compatible_targets']);
        $this->assertContains(['key' => 'performance.notes', 'label' => 'Performance notes'], $targets->all());
        $this->assertContains(['key' => 'wedding.toasts', 'label' => 'Toasts'], $targets->all());
        $this->assertNotContains('wedding.onsite', $targets->pluck('key')->all());
    }

    public function test_route_applies_batch_snapshots_and_stamps(): void
    {
        $both = Song::factory()->create(['band_id' => $this->band->id]);
        $first = $this->answer('short_text', 'First Dance', 'Evergreen', 'wedding.dance.first');
        $toasts = $this->answer('long_text', 'Names and order of toasts', 'Paul Looney, father of the bride', 'wedding.toasts');
        $colors = $this->answer('long_text', 'Wedding colors', 'Sage and gold', 'wedding.colors');
        $anything = $this->answer('long_text', 'Anything else we should know?', 'No slow songs', 'performance.notes');
        $bride = $this->answer('short_text', "Bride's Full Name", 'Jane Doe');
        $venue = $this->answer('short_text', 'Venue Name', 'II Mercato');

        $result = $this->service->route($this->instance, [
            ['response_id' => $first->id, 'target' => 'wedding.dance.first'],
            ['response_id' => $toasts->id, 'target' => 'wedding.toasts'],
            ['response_id' => $colors->id, 'target' => 'wedding.colors'],
            ['response_id' => $anything->id, 'target' => 'performance.notes'],
            ['response_id' => $bride->id, 'target' => 'event.notes', 'remember' => true],
        ], ['songs' => [(string) $both->id => 'do_not_play'], 'venue' => 'form'], $this->user);

        $this->assertSame(5, $result['applied']);
        $data = $this->eventData();
        $dances = collect(data_get($data, 'wedding.dances'));
        $this->assertSame('Evergreen', $dances->firstWhere('title', 'First Dance')['data']);
        $this->assertSame('Paul Looney, father of the bride', $dances->firstWhere('title', 'Toasts')['data']);
        $this->assertSame('Sage and gold', data_get($data, 'wedding.colors'));
        $this->assertSame('All black', data_get($data, 'attire'));
        $this->assertSame('No slow songs', data_get($data, 'performance.notes'));
        $this->assertArrayNotHasKey('times', $data);
        $event = $this->event->fresh();
        $this->assertSame("Load in at 3\nBride's Full Name: Jane Doe", $event->notes);
        $this->assertSame('II Mercato', $event->venue_name);

        // Per-answer routing + "remember" on the template field.
        $this->assertSame('event.notes', $bride->fresh()->routed_target);
        $this->assertNull($first->fresh()->routed_target);
        $sourceId = $bride->instanceField->source_field_id;
        $this->assertSame('event.notes', QuestionnaireFields::find($sourceId)->mapping_target);

        $this->assertSame([(string) $both->id => 'do_not_play'], array_map('strval', $this->instance->fresh()->song_overrides));

        foreach ([$first, $toasts, $colors, $anything, $bride, $venue] as $r) {
            $this->assertNotNull($r->fresh()->applied_to_event_at, "response {$r->id} should be stamped");
            $this->assertSame($this->user->id, $r->fresh()->applied_by_user_id);
        }

        $application = $result['application'];
        $this->assertSame($this->event->id, $application->event_id);
        $this->assertSame('Load in at 3', $application->before['notes']);
        $this->assertSame('Il Mercato', $application->before['venue_name']);
        $this->assertSame('All black', $application->before['additional_data']['attire']);
        $this->assertEqualsCanonicalizing(
            [$first->id, $toasts->id, $colors->id, $anything->id, $bride->id, $venue->id],
            $application->applied_response_ids,
        );

        // Applied answers read back as applied, not stale.
        $items = $this->items($this->service->preview($this->instance->fresh()));
        $this->assertSame('applied', $items[$first->id]['status']);
    }

    public function test_route_rolls_back_every_write_on_a_bad_target(): void
    {
        $first = $this->answer('short_text', 'First Dance', 'Evergreen', 'wedding.dance.first');
        $onsite = $this->answer('yes_no', 'Will the ceremony be onsite?', 'yes', 'wedding.onsite');
        $before = $this->eventData();

        try {
            $this->service->route($this->instance, [
                ['response_id' => $first->id, 'target' => 'wedding.dance.first'],
                // yes_no can't go to a dance entry.
                ['response_id' => $onsite->id, 'target' => 'wedding.dance.money'],
            ], ['songs' => ['5' => 'must_play']], $this->user);
            $this->fail('Expected a routing exception.');
        } catch (QuestionnaireRoutingException $e) {
            $this->assertSame(422, $e->status);
        }

        $this->assertSame($before, $this->eventData());
        $this->assertNull($first->fresh()->applied_to_event_at);
        $this->assertNull($this->instance->fresh()->song_overrides);
        $this->assertSame(0, QuestionnaireApplications::count());
    }

    public function test_route_rejects_a_response_from_another_instance(): void
    {
        $other = QuestionnaireInstances::factory()->create(['booking_id' => $this->booking->id]);
        $field = QuestionnaireInstanceFields::factory()->create(['instance_id' => $other->id, 'type' => 'short_text']);
        $foreign = QuestionnaireResponses::create(['instance_id' => $other->id, 'instance_field_id' => $field->id, 'value' => 'x']);

        $this->expectException(QuestionnaireRoutingException::class);
        $this->service->route($this->instance, [['response_id' => $foreign->id, 'target' => 'wedding.dance.first']], [], $this->user);
    }

    public function test_song_overrides_are_honored_by_client_song_requests(): void
    {
        $both = Song::factory()->create(['band_id' => $this->band->id, 'active' => true]);
        $other = Song::factory()->create(['band_id' => $this->band->id, 'active' => true]);
        $this->answer('song_picker', 'Must', json_encode([$both->id, $other->id]), null, ['purpose' => 'must_play']);
        $this->answer('song_picker', 'Skip', json_encode([$both->id]), null, ['purpose' => 'do_not_play']);
        $requests = app(ClientSongRequestsService::class);

        // Default: both-ways is do-not-play.
        $default = $requests->forBooking($this->booking);
        $this->assertSame([$other->id], $default['must_play']);
        $this->assertSame([$both->id], $default['do_not_play']);

        $this->service->route($this->instance, [], ['songs' => [(string) $both->id => 'must_play']], $this->user);
        $resolved = $requests->forBooking($this->booking->fresh());
        $this->assertEqualsCanonicalizing([$both->id, $other->id], $resolved['must_play']);
        $this->assertSame([], $resolved['do_not_play']);

        $this->service->route($this->instance, [], ['songs' => [(string) $both->id => 'do_not_play']], $this->user);
        $flipped = $requests->forBooking($this->booking->fresh());
        $this->assertSame([$other->id], $flipped['must_play']);
        $this->assertSame([$both->id], $flipped['do_not_play']);
    }

    public function test_undo_restores_the_snapshot_and_clears_stamps(): void
    {
        $first = $this->answer('short_text', 'First Dance', 'Evergreen', 'wedding.dance.first');
        $this->answer('short_text', 'Venue Name', 'II Mercato');
        $before = $this->eventData();

        $result = $this->service->route($this->instance, [
            ['response_id' => $first->id, 'target' => 'wedding.dance.first'],
        ], ['venue' => 'form', 'songs' => ['7' => 'must_play']], $this->user);

        $this->service->undo($result['application']);

        $event = $this->event->fresh();
        $this->assertSame($before, $this->eventData());
        $this->assertSame('Il Mercato', $event->venue_name);
        $this->assertSame('Load in at 3', $event->notes);
        $this->assertNull($this->instance->fresh()->song_overrides);
        $this->assertNull($first->fresh()->applied_to_event_at);
        $this->assertNotNull($result['application']->fresh()->undone_at);

        // A second undo is refused.
        $this->expectException(QuestionnaireRoutingException::class);
        $this->service->undo($result['application']->fresh());
    }

    public function test_undo_refuses_with_409_when_the_event_was_edited_since(): void
    {
        $first = $this->answer('short_text', 'First Dance', 'Evergreen', 'wedding.dance.first');
        $result = $this->service->route($this->instance, [
            ['response_id' => $first->id, 'target' => 'wedding.dance.first'],
        ], [], $this->user);

        $this->travel(5)->seconds();
        $this->event->fresh()->update(['notes' => 'Edited by hand']);

        try {
            $this->service->undo($result['application']);
            $this->fail('Expected a 409.');
        } catch (QuestionnaireRoutingException $e) {
            $this->assertSame(409, $e->status);
            $this->assertSame('Event was edited since — undo manually', $e->getMessage());
        }

        $this->assertSame('Edited by hand', $this->event->fresh()->notes);
        $this->assertNotNull($first->fresh()->applied_to_event_at);
    }

    public function test_undo_refuses_an_edit_made_within_the_same_second(): void
    {
        $this->freezeSecond();
        $first = $this->answer('short_text', 'First Dance', 'Evergreen', 'wedding.dance.first');
        $result = $this->service->route($this->instance, [
            ['response_id' => $first->id, 'target' => 'wedding.dance.first'],
        ], [], $this->user);

        $this->event->fresh()->update(['notes' => 'Edited by hand']);

        $this->expectException(QuestionnaireRoutingException::class);
        $this->service->undo($result['application']);
    }

    public function test_an_answer_edited_in_the_same_second_it_was_sent_reads_stale(): void
    {
        $this->freezeSecond();
        $first = $this->answer('short_text', 'First Dance', 'Evergreen', 'wedding.dance.first');
        $this->service->route($this->instance, [['response_id' => $first->id, 'target' => 'wedding.dance.first']], [], $this->user);

        $first->fresh()->update(['value' => 'At Last']);

        $item = $this->items($this->service->preview($this->instance->fresh()))[$first->id];
        $this->assertSame('stale', $item['status']);
        $this->assertTrue($item['default_checked']);
    }

    public function test_undo_is_refused_while_a_later_send_is_still_applied(): void
    {
        $both = Song::factory()->create(['band_id' => $this->band->id]);
        $first = $this->answer('short_text', 'First Dance', 'Evergreen', 'wedding.dance.first');
        $older = $this->service->route($this->instance, [['response_id' => $first->id, 'target' => 'wedding.dance.first']], [], $this->user);
        // Songs-only send: doesn't touch the event row.
        $newer = $this->service->route($this->instance, [], ['songs' => [(string) $both->id => 'must_play']], $this->user);

        try {
            $this->service->undo($older['application']);
            $this->fail('Expected a 409.');
        } catch (QuestionnaireRoutingException $e) {
            $this->assertSame(409, $e->status);
        }
        $this->assertSame('must_play', $this->instance->fresh()->song_overrides[$both->id]);

        // Undoing newest-first works, even across a second boundary.
        $this->travel(2)->seconds();
        $this->service->undo($newer['application']);
        $this->travel(2)->seconds();
        $this->service->undo($older['application']->fresh());
        $this->assertNull($first->fresh()->applied_to_event_at);
    }

    public function test_a_song_override_is_ignored_once_the_song_is_on_only_one_list(): void
    {
        $song = Song::factory()->create(['band_id' => $this->band->id, 'active' => true]);
        $other = Song::factory()->create(['band_id' => $this->band->id, 'active' => true]);
        $must = $this->answer('song_picker', 'Must', json_encode([$song->id, $other->id]), null, ['purpose' => 'must_play']);
        $this->answer('song_picker', 'Skip', json_encode([$song->id]), null, ['purpose' => 'do_not_play']);
        $this->instance->update(['song_overrides' => [(string) $song->id => 'must_play']]);

        // Client later drops it from must-play, leaving only do-not-play.
        $must->update(['value' => json_encode([$other->id])]);

        $requests = app(ClientSongRequestsService::class)->forBooking($this->booking->fresh());
        $this->assertSame([$other->id], $requests['must_play']);
        $this->assertSame([$song->id], $requests['do_not_play']);
    }

    public function test_contacts_pair_emails_and_phones_by_position_even_when_one_is_blank(): void
    {
        $grace = Contacts::factory()->create(['band_id' => $this->band->id, 'email' => 'grace@example.com', 'phone' => '5045551234']);
        $this->booking->contacts()->attach($grace, ['is_primary' => true]);
        $this->answer('email', 'Email 1', 'stranger@example.com');
        $this->answer('phone', 'Phone 1', '');
        $this->answer('email', 'Email 2', '');
        $this->answer('phone', 'Phone 2', '504-555-1234');

        $contacts = $this->service->preview($this->instance)['contacts'];

        $this->assertCount(2, $contacts);
        $this->assertSame(['stranger@example.com', '', false], [$contacts[0]['email'], $contacts[0]['phone'], $contacts[0]['on_booking']]);
        $this->assertSame(['', '504-555-1234', true], [$contacts[1]['email'], $contacts[1]['phone'], $contacts[1]['on_booking']]);
    }

    public function test_undoing_a_resend_keeps_the_earlier_send_applied(): void
    {
        $first = $this->answer('short_text', 'First Dance', 'Evergreen', 'wedding.dance.first');
        $this->answer('short_text', 'Venue Name', 'II Mercato');
        // Batch A: send the dance, keep the booking's venue spelling.
        $this->service->route($this->instance, [['response_id' => $first->id, 'target' => 'wedding.dance.first']], ['venue' => 'booking'], $this->user);
        $stampA = $first->fresh()->applied_to_event_at;

        // Client edits the answer; batch B re-sends it and re-resolves the venue.
        $this->travel(2)->seconds();
        $first->fresh()->update(['value' => 'At Last']);
        $b = $this->service->route($this->instance, [['response_id' => $first->id, 'target' => 'wedding.dance.first']], ['venue' => 'booking'], $this->user);

        $this->service->undo($b['application']);

        $first->refresh();
        $this->assertTrue($first->applied_to_event_at->equalTo($stampA));
        $this->assertSame($this->user->id, $first->applied_by_user_id);
        $preview = $this->service->preview($this->instance->fresh());
        // The event holds A's value, but the answer has changed since: stale, not pending.
        $this->assertSame('stale', $this->items($preview)[$first->id]['status']);
        // A's venue resolution survives.
        $this->assertSame('booking', collect($preview['conflicts'])->firstWhere('kind', 'venue_mismatch')['resolution']);
    }

    public function test_undoing_a_resend_of_an_unchanged_answer_leaves_it_applied(): void
    {
        $first = $this->answer('short_text', 'First Dance', 'Evergreen', 'wedding.dance.first');
        $this->service->route($this->instance, [['response_id' => $first->id, 'target' => 'wedding.dance.first']], [], $this->user);
        $this->travel(2)->seconds();
        $b = $this->service->route($this->instance, [['response_id' => $first->id, 'target' => 'wedding.dance.first']], [], $this->user);

        $this->service->undo($b['application']);

        $this->assertSame('applied', $this->items($this->service->preview($this->instance->fresh()))[$first->id]['status']);
    }

    public function test_undoing_two_event_edits_newest_first_across_seconds(): void
    {
        $first = $this->answer('short_text', 'First Dance', 'Evergreen', 'wedding.dance.first');
        $colors = $this->answer('long_text', 'Wedding colors', 'Sage', 'wedding.colors');
        $original = $this->eventData();

        $a = $this->service->route($this->instance, [['response_id' => $first->id, 'target' => 'wedding.dance.first']], [], $this->user);
        $this->travel(2)->seconds();
        $b = $this->service->route($this->instance, [['response_id' => $colors->id, 'target' => 'wedding.colors']], [], $this->user);
        $this->travel(2)->seconds();

        $this->service->undo($b['application']);
        $this->travel(2)->seconds();
        $this->service->undo($a['application']->fresh());

        $this->assertSame($original, $this->eventData());
    }
}
