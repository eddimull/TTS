<?php

namespace Tests\Feature;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contracts;
use App\Models\Events;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers updating a booking after its contract is signed.
 *
 * Regression: the web booking form always resubmits deposit_type /
 * deposit_value even when unchanged, and DepositNotLocked failed on mere
 * presence of the field. Once a contract was signed, every booking save
 * 422'd on the deposit rule — which also aborted the form's follow-up
 * event saves, so venue/location edits could never be persisted.
 */
class BookingUpdateSignedContractTest extends TestCase
{
    use RefreshDatabase;

    private Bands $band;
    private User $owner;
    private Bookings $booking;
    private Events $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->band = Bands::factory()->create();
        $this->owner = User::factory()->create();
        $this->band->owners()->create(['user_id' => $this->owner->id]);

        $this->booking = Bookings::factory()->create([
            'band_id'         => $this->band->id,
            'status'          => 'confirmed',
            'contract_option' => 'default',
            'deposit_type'    => 'percent',
            'deposit_value'   => '50.00',
        ]);
        $this->event = Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id'   => $this->booking->id,
            'date'           => now()->addDays(30)->format('Y-m-d'),
            'venue_name'     => 'TBD',
        ]);

        Contracts::factory()->create([
            'contractable_id'   => $this->booking->id,
            'contractable_type' => Bookings::class,
            'status'            => 'completed',
        ]);
    }

    /** Payload the booking form sends: engagement fields incl. unchanged deposit. */
    private function formPayload(array $overrides = []): array
    {
        return array_merge([
            'name'            => $this->booking->name,
            'event_type_id'   => $this->booking->event_type_id,
            'price'           => (string) $this->booking->price,
            'status'          => $this->booking->status,
            'contract_option' => $this->booking->contract_option,
            'notes'           => $this->booking->notes,
            'deposit_type'    => 'percent',
            'deposit_value'   => '50.00',
        ], $overrides);
    }

    public function test_update_with_unchanged_deposit_fields_succeeds_when_contract_is_signed(): void
    {
        $response = $this->actingAs($this->owner)->put(
            route('bands.booking.update', [$this->band, $this->booking]),
            $this->formPayload(['name' => 'Renamed Gig']),
        );

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('bookings', [
            'id'   => $this->booking->id,
            'name' => 'Renamed Gig',
        ]);
    }

    public function test_update_with_changed_deposit_value_is_rejected_when_contract_is_signed(): void
    {
        $response = $this->actingAs($this->owner)->put(
            route('bands.booking.update', [$this->band, $this->booking]),
            $this->formPayload(['deposit_value' => '75.00']),
        );

        $response->assertSessionHasErrors('deposit_value');
        $this->assertSame('50.00', $this->booking->fresh()->deposit_value);
    }

    public function test_update_with_changed_deposit_type_is_rejected_when_contract_is_signed(): void
    {
        $response = $this->actingAs($this->owner)->put(
            route('bands.booking.update', [$this->band, $this->booking]),
            $this->formPayload(['deposit_type' => 'amount', 'deposit_value' => '50.00']),
        );

        $response->assertSessionHasErrors('deposit_type');
        $this->assertSame('percent', $this->booking->fresh()->deposit_type);
    }

    public function test_event_venue_can_be_updated_when_contract_is_signed(): void
    {
        $response = $this->actingAs($this->owner)->put(
            route('Update Booking Event', [$this->band->id, $this->booking->id, $this->event->id]),
            [
                'title'         => $this->event->title,
                'date'          => $this->event->date,
                'venue_name'    => 'The Spotted Cat',
                'venue_address' => '623 Frenchmen St, New Orleans, LA',
            ],
        );

        $response->assertSessionHasNoErrors();
        $fresh = $this->event->fresh();
        $this->assertSame('The Spotted Cat', $fresh->venue_name);
        $this->assertSame('623 Frenchmen St, New Orleans, LA', $fresh->venue_address);
    }
}
