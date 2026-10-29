<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Questionnaire routing writes the couple's colors to
 * additional_data.wedding.colors and toasts as a "Toasts" dance entry; the
 * mobile event payload and edits must carry both through.
 */
class EventWeddingColorsTest extends TestCase
{
    use RefreshDatabase;

    private Bands $band;
    private Events $event;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $this->band = Bands::factory()->create();
        $this->band->owners()->create(['user_id' => $user->id]);
        $booking = Bookings::factory()->create(['band_id' => $this->band->id]);
        $this->event = Events::factory()->create([
            'eventable_id' => $booking->id,
            'eventable_type' => Bookings::class,
            'date' => now()->addDays(7)->format('Y-m-d'),
            'additional_data' => [
                'attire' => 'All black',
                'wedding' => [
                    'onsite' => true,
                    'colors' => 'Sage and gold',
                    'dances' => [['title' => 'Toasts', 'data' => str_repeat('Best man, then maid of honor. ', 20)]],
                ],
            ],
        ]);
        $this->token = $user->createToken('test-device')->plainTextToken;
    }

    private function client()
    {
        return $this->withToken($this->token)->withHeaders(['X-Band-ID' => $this->band->id]);
    }

    public function test_show_exposes_wedding_colors_and_toasts(): void
    {
        $response = $this->client()->getJson("/api/mobile/events/{$this->event->key}")->assertOk();

        $wedding = collect($response->json())->dot()->filter(fn ($v, $k) => str_ends_with($k, 'wedding.colors'));
        $this->assertSame(['Sage and gold'], $wedding->values()->all());
        $this->assertStringContainsString('"title":"Toasts"', $response->getContent());
        $this->assertStringContainsString('All black', $response->getContent());
    }

    public function test_show_returns_empty_colors_string_when_unset(): void
    {
        $this->event->update(['additional_data' => ['wedding' => ['onsite' => false, 'dances' => []]]]);

        $response = $this->client()->getJson("/api/mobile/events/{$this->event->key}")->assertOk();

        $colors = collect($response->json())->dot()->filter(fn ($v, $k) => str_ends_with($k, 'wedding.colors'));
        $this->assertSame([''], $colors->values()->all());
    }

    public function test_editing_dances_keeps_colors_and_accepts_long_toasts(): void
    {
        $toasts = rtrim(str_repeat('Best man, then maid of honor. ', 20)); // > 255 chars

        $this->client()->patchJson("/api/mobile/events/{$this->event->key}", [
            'wedding' => ['onsite' => true, 'dances' => [['title' => 'Toasts', 'data' => $toasts]]],
        ])->assertOk();

        $data = json_decode(json_encode($this->event->fresh()->additional_data), true);
        $this->assertSame('Sage and gold', data_get($data, 'wedding.colors'));
        $this->assertSame($toasts, data_get($data, 'wedding.dances.0.data'));
    }

    public function test_colors_can_be_edited_from_mobile(): void
    {
        $this->client()->patchJson("/api/mobile/events/{$this->event->key}", [
            'wedding' => ['colors' => 'Navy'],
        ])->assertOk();

        $data = json_decode(json_encode($this->event->fresh()->additional_data), true);
        $this->assertSame('Navy', data_get($data, 'wedding.colors'));
        $this->assertSame('All black', data_get($data, 'attire'));
    }
}
