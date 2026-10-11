<?php

namespace Tests\Feature;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebBookingContactBandScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_adding_a_contact_never_reuses_another_bands_contact(): void
    {
        $owner = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $owner->id]);
        $booking = Bookings::factory()->create(['band_id' => $band->id]);

        $otherBand = Bands::factory()->create();
        $foreign = Contacts::factory()->create([
            'band_id' => $otherBand->id,
            'email' => 'planner@example.com',
            'name' => 'Other Band Planner',
            'phone' => '555-0000',
        ]);

        $this->actingAs($owner)
            ->post("/bands/{$band->id}/booking/{$booking->id}/contacts", [
                'name' => 'Our Planner',
                'email' => 'planner@example.com',
                'phone' => '555-1111',
            ])
            ->assertRedirect();

        $attached = $booking->contacts()->get();
        $this->assertCount(1, $attached);
        $this->assertNotSame($foreign->id, $attached->first()->id);
        $this->assertSame($band->id, $attached->first()->band_id);
        $this->assertSame('Our Planner', $attached->first()->name);
        $this->assertSame('Other Band Planner', $foreign->fresh()->name);
    }

    public function test_adding_a_contact_reuses_the_same_bands_contact(): void
    {
        $owner = User::factory()->create();
        $band = Bands::factory()->create();
        $band->owners()->create(['user_id' => $owner->id]);
        $booking = Bookings::factory()->create(['band_id' => $band->id]);
        $existing = Contacts::factory()->create(['band_id' => $band->id, 'email' => 'planner@example.com']);

        $this->actingAs($owner)
            ->post("/bands/{$band->id}/booking/{$booking->id}/contacts", [
                'name' => 'Our Planner',
                'email' => 'planner@example.com',
            ])
            ->assertRedirect();

        $this->assertSame([$existing->id], $booking->contacts()->pluck('contacts.id')->all());
    }
}
