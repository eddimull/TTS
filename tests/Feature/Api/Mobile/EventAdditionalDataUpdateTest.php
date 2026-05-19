<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers PATCH /api/mobile/events/{event} for the additional_data fields
 * the mobile event-edit screen exposes:
 *
 *   - is_public, outside, backline_provided, production_needed (booleans)
 *   - lodging (canonical 4-row {title, type, data} list)
 *
 * Regression coverage for the case where the mobile app sent these fields
 * but the backend never wrote `lodging` (no validation, no handler), so
 * lodging edits silently disappeared.
 */
class EventAdditionalDataUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwnedEvent(array $additionalData = []): array
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $user->id]);

        $booking = Bookings::factory()->create(['band_id' => $band->id]);

        $event = Events::factory()->create([
            'eventable_id'    => $booking->id,
            'eventable_type'  => Bookings::class,
            'date'            => now()->addDays(7)->format('Y-m-d'),
            'additional_data' => (object) $additionalData,
        ]);

        $token = $user->createToken('test-device')->plainTextToken;

        return compact('user', 'band', 'booking', 'event', 'token');
    }

    private function patchEvent(string $token, Bands $band, Events $event, array $payload)
    {
        return $this->withToken($token)
            ->withHeaders(['X-Band-ID' => $band->id])
            ->patchJson("/api/mobile/events/{$event->key}", $payload);
    }

    // ── Flag toggles ─────────────────────────────────────────────────────────

    public function test_update_persists_is_public_flag(): void
    {
        ['band' => $band, 'event' => $event, 'token' => $token] = $this->makeOwnedEvent();

        $this->patchEvent($token, $band, $event, [
            'is_public' => true,
        ])->assertOk();

        // is_public is stored as `additional_data->public` on the model.
        $this->assertTrue($event->fresh()->additional_data->public);
    }

    public function test_update_persists_outside_flag(): void
    {
        ['band' => $band, 'event' => $event, 'token' => $token] = $this->makeOwnedEvent();

        $this->patchEvent($token, $band, $event, [
            'outside' => true,
        ])->assertOk();

        $this->assertTrue($event->fresh()->additional_data->outside);
    }

    public function test_update_persists_backline_provided_flag(): void
    {
        ['band' => $band, 'event' => $event, 'token' => $token] = $this->makeOwnedEvent();

        $this->patchEvent($token, $band, $event, [
            'backline_provided' => true,
        ])->assertOk();

        $this->assertTrue($event->fresh()->additional_data->backline_provided);
    }

    public function test_update_persists_production_needed_flag(): void
    {
        ['band' => $band, 'event' => $event, 'token' => $token] = $this->makeOwnedEvent();

        $this->patchEvent($token, $band, $event, [
            'production_needed' => true,
        ])->assertOk();

        $this->assertTrue($event->fresh()->additional_data->production_needed);
    }

    public function test_update_can_flip_flag_to_false(): void
    {
        ['band' => $band, 'event' => $event, 'token' => $token] =
            $this->makeOwnedEvent(['outside' => true]);

        $this->patchEvent($token, $band, $event, [
            'outside' => false,
        ])->assertOk();

        $this->assertFalse($event->fresh()->additional_data->outside);
    }

    // ── Lodging ──────────────────────────────────────────────────────────────

    public function test_update_persists_lodging_block(): void
    {
        ['band' => $band, 'event' => $event, 'token' => $token] = $this->makeOwnedEvent();

        $this->patchEvent($token, $band, $event, [
            'lodging' => [
                ['title' => 'Provided',  'type' => 'checkbox', 'data' => true],
                ['title' => 'location',  'type' => 'text',     'data' => 'Hilton Riverwalk'],
                ['title' => 'check_in',  'type' => 'text',     'data' => '15:00'],
                ['title' => 'check_out', 'type' => 'text',     'data' => '11:00'],
            ],
        ])->assertOk();

        $lodging = $event->fresh()->additional_data->lodging;
        $this->assertCount(4, $lodging);

        // The "Provided" checkbox row is coerced to a real bool so the
        // read-side `data == true` comparison works.
        $this->assertSame('Provided', $lodging[0]->title);
        $this->assertSame('checkbox', $lodging[0]->type);
        $this->assertTrue($lodging[0]->data);

        $this->assertSame('location', $lodging[1]->title);
        $this->assertSame('Hilton Riverwalk', $lodging[1]->data);
        $this->assertSame('15:00', $lodging[2]->data);
        $this->assertSame('11:00', $lodging[3]->data);
    }

    public function test_update_coerces_truthy_strings_on_provided_checkbox(): void
    {
        // The Flutter side sends a real bool, but defend against any
        // client (or older payload) that sends "1" or "true" as a string.
        ['band' => $band, 'event' => $event, 'token' => $token] = $this->makeOwnedEvent();

        $this->patchEvent($token, $band, $event, [
            'lodging' => [
                ['title' => 'Provided', 'type' => 'checkbox', 'data' => '1'],
                ['title' => 'location', 'type' => 'text',     'data' => ''],
                ['title' => 'check_in', 'type' => 'text',     'data' => ''],
                ['title' => 'check_out','type' => 'text',     'data' => ''],
            ],
        ])->assertOk();

        $lodging = $event->fresh()->additional_data->lodging;
        $this->assertTrue($lodging[0]->data);
    }

    public function test_update_rejects_lodging_row_with_invalid_type(): void
    {
        ['band' => $band, 'event' => $event, 'token' => $token] = $this->makeOwnedEvent();

        $this->patchEvent($token, $band, $event, [
            'lodging' => [
                ['title' => 'Provided', 'type' => 'radio', 'data' => true],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('lodging.0.type');
    }

    public function test_update_rejects_lodging_row_missing_title(): void
    {
        ['band' => $band, 'event' => $event, 'token' => $token] = $this->makeOwnedEvent();

        $this->patchEvent($token, $band, $event, [
            'lodging' => [
                ['type' => 'text', 'data' => 'Hilton'],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('lodging.0.title');
    }

    public function test_update_without_lodging_key_leaves_existing_lodging_untouched(): void
    {
        ['band' => $band, 'event' => $event, 'token' => $token] = $this->makeOwnedEvent([
            'lodging' => [
                ['title' => 'Provided', 'type' => 'checkbox', 'data' => true],
                ['title' => 'location', 'type' => 'text',     'data' => 'Original'],
            ],
        ]);

        $this->patchEvent($token, $band, $event, [
            'title' => 'Renamed',
        ])->assertOk();

        $fresh = $event->fresh();
        $this->assertSame('Renamed', $fresh->title);
        $this->assertCount(2, $fresh->additional_data->lodging);
        $this->assertSame('Original', $fresh->additional_data->lodging[1]->data);
    }
}
