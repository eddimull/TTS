<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\BandOwners;
use App\Models\Bands;
use App\Models\BandSubs;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\Payments;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MeBookingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/mobile/me/bookings');
        $response->assertStatus(401);
    }

    public function test_returns_bookings_across_all_users_bands(): void
    {
        $user = User::factory()->create();

        $bandA = Bands::create([
            'name' => 'Band A', 'site_name' => 'band-a-' . uniqid(), 'is_personal' => false,
        ]);
        $bandB = Bands::create([
            'name' => 'Band B', 'site_name' => 'band-b-' . uniqid(), 'is_personal' => false,
        ]);
        $personal = Bands::create([
            'name' => "{$user->name}'s Band", 'site_name' => 'eddies-band-' . uniqid(), 'is_personal' => true,
        ]);

        foreach ([$bandA, $bandB, $personal] as $b) {
            BandOwners::create(['user_id' => $user->id, 'band_id' => $b->id]);
        }

        Bookings::factory()->create(['name' => 'A Gig', 'band_id' => $bandA->id]);
        Bookings::factory()->create(['name' => 'B Gig', 'band_id' => $bandB->id]);
        Bookings::factory()->create(['name' => 'Church', 'band_id' => $personal->id]);

        $token = $user->createToken('test')->plainTextToken;
        $response = $this->withToken($token)->getJson('/api/mobile/me/bookings');
        $response->assertOk();

        $bookings = $response->json('bookings');
        $this->assertCount(3, $bookings);

        $names = collect($bookings)->pluck('name')->all();
        $this->assertContains('A Gig', $names);
        $this->assertContains('B Gig', $names);
        $this->assertContains('Church', $names);

        $church = collect($bookings)->firstWhere('name', 'Church');
        $this->assertTrue($church['band']['is_personal']);
    }

    public function test_excludes_bookings_from_bands_user_does_not_belong_to(): void
    {
        $user = User::factory()->create();
        $myBand = Bands::create([
            'name' => 'Mine', 'site_name' => 'mine-' . uniqid(), 'is_personal' => false,
        ]);
        $otherBand = Bands::create([
            'name' => 'Other', 'site_name' => 'other-' . uniqid(), 'is_personal' => false,
        ]);
        BandOwners::create(['user_id' => $user->id, 'band_id' => $myBand->id]);

        Bookings::factory()->create(['name' => 'Mine Gig', 'band_id' => $myBand->id]);
        Bookings::factory()->create(['name' => 'Other Gig', 'band_id' => $otherBand->id]);

        $token = $user->createToken('test')->plainTextToken;
        $response = $this->withToken($token)->getJson('/api/mobile/me/bookings');
        $response->assertOk();

        $bookings = $response->json('bookings');
        $names = collect($bookings)->pluck('name')->all();
        $this->assertContains('Mine Gig', $names);
        $this->assertNotContains('Other Gig', $names);
    }

    public function test_returns_empty_array_when_user_has_no_bands(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/mobile/me/bookings');

        $response->assertOk();
        $this->assertSame([], $response->json('bookings'));
    }

    public function test_excludes_bookings_from_bands_user_is_only_a_sub_for(): void
    {
        $user = User::factory()->create();
        $myBand = Bands::create([
            'name' => 'Mine', 'site_name' => 'mine-' . uniqid(), 'is_personal' => false,
        ]);
        $subBand = Bands::create([
            'name' => 'Sub For', 'site_name' => 'sub-' . uniqid(), 'is_personal' => false,
        ]);
        BandOwners::create(['user_id' => $user->id, 'band_id' => $myBand->id]);
        BandSubs::create(['user_id' => $user->id, 'band_id' => $subBand->id]);

        Bookings::factory()->create([
            'name' => 'Mine Gig', 'band_id' => $myBand->id,
        ]);
        Bookings::factory()->create([
            'name' => 'Sub Band Gig', 'band_id' => $subBand->id,
        ]);

        $token = $user->createToken('test')->plainTextToken;
        $response = $this->withToken($token)->getJson('/api/mobile/me/bookings');
        $response->assertOk();

        $names = collect($response->json('bookings'))->pluck('name')->all();
        $this->assertContains('Mine Gig', $names);
        $this->assertNotContains('Sub Band Gig', $names,
            'Bookings carry money/contract info subs should not see');
    }

    public function test_filters_by_status_upcoming_and_year(): void
    {
        $user = User::factory()->create();
        $band = Bands::create([
            'name' => 'B', 'site_name' => 'b-' . uniqid(), 'is_personal' => false,
        ]);
        BandOwners::create(['user_id' => $user->id, 'band_id' => $band->id]);

        // Past confirmed booking (should be excluded by upcoming=1).
        $pastBooking = Bookings::factory()->create([
            'name' => 'Past', 'band_id' => $band->id, 'status' => 'confirmed',
        ]);
        Events::factory()->create([
            'eventable_type' => Bookings::class, 'eventable_id' => $pastBooking->id,
            'date' => '2024-01-01',
        ]);
        // Future confirmed booking in 2026 (should match all three filters).
        $futureConfirmed2026 = Bookings::factory()->create([
            'name' => 'Future Confirmed 2026', 'band_id' => $band->id, 'status' => 'confirmed',
        ]);
        Events::factory()->create([
            'eventable_type' => Bookings::class, 'eventable_id' => $futureConfirmed2026->id,
            'date' => '2026-12-31',
        ]);
        // Future pending booking in 2026 (should be excluded by status=confirmed).
        $futurePending2026 = Bookings::factory()->create([
            'name' => 'Future Pending 2026', 'band_id' => $band->id, 'status' => 'pending',
        ]);
        Events::factory()->create([
            'eventable_type' => Bookings::class, 'eventable_id' => $futurePending2026->id,
            'date' => '2026-11-01',
        ]);
        // Future confirmed booking in 2027 (should be excluded by year=2026).
        $futureConfirmed2027 = Bookings::factory()->create([
            'name' => 'Future Confirmed 2027', 'band_id' => $band->id, 'status' => 'confirmed',
        ]);
        Events::factory()->create([
            'eventable_type' => Bookings::class, 'eventable_id' => $futureConfirmed2027->id,
            'date' => '2027-06-01',
        ]);

        $token = $user->createToken('test')->plainTextToken;
        $response = $this->withToken($token)->getJson(
            '/api/mobile/me/bookings?status=confirmed&upcoming=1&year=2026'
        );
        $response->assertOk();

        $names = collect($response->json('bookings'))->pluck('name')->all();
        $this->assertSame(['Future Confirmed 2026'], $names);
    }

    public function test_from_param_filters_to_on_or_after(): void
    {
        $user = User::factory()->create();
        $band = Bands::create([
            'name' => 'Band', 'site_name' => 'b-' . uniqid(), 'is_personal' => false,
        ]);
        BandOwners::create(['user_id' => $user->id, 'band_id' => $band->id]);

        $oldBooking = Bookings::factory()->for($band, 'band')->create(['name' => 'Old']);
        Events::factory()->create([
            'eventable_type' => Bookings::class, 'eventable_id' => $oldBooking->id,
            'date' => '2026-01-15',
        ]);
        $newBooking = Bookings::factory()->for($band, 'band')->create(['name' => 'New']);
        Events::factory()->create([
            'eventable_type' => Bookings::class, 'eventable_id' => $newBooking->id,
            'date' => '2026-06-01',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/mobile/me/bookings?from=2026-05-01');

        $response->assertOk();
        $names = collect($response->json('bookings'))->pluck('name')->all();
        $this->assertEqualsCanonicalizing(['New'], $names);
    }

    public function test_to_param_filters_to_on_or_before(): void
    {
        $user = User::factory()->create();
        $band = Bands::create([
            'name' => 'Band', 'site_name' => 'b-' . uniqid(), 'is_personal' => false,
        ]);
        BandOwners::create(['user_id' => $user->id, 'band_id' => $band->id]);

        $oldBooking2 = Bookings::factory()->for($band, 'band')->create(['name' => 'Old']);
        Events::factory()->create([
            'eventable_type' => Bookings::class, 'eventable_id' => $oldBooking2->id,
            'date' => '2026-01-15',
        ]);
        $newBooking2 = Bookings::factory()->for($band, 'band')->create(['name' => 'New']);
        Events::factory()->create([
            'eventable_type' => Bookings::class, 'eventable_id' => $newBooking2->id,
            'date' => '2026-06-01',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/mobile/me/bookings?to=2026-05-01');

        $response->assertOk();
        $names = collect($response->json('bookings'))->pluck('name')->all();
        $this->assertEqualsCanonicalizing(['Old'], $names);
    }

    public function test_from_and_to_together_narrow_to_inclusive_range(): void
    {
        $user = User::factory()->create();
        $band = Bands::create([
            'name' => 'Band', 'site_name' => 'b-' . uniqid(), 'is_personal' => false,
        ]);
        BandOwners::create(['user_id' => $user->id, 'band_id' => $band->id]);

        $beforeBooking = Bookings::factory()->for($band, 'band')->create(['name' => 'Before']);
        Events::factory()->create([
            'eventable_type' => Bookings::class, 'eventable_id' => $beforeBooking->id,
            'date' => '2026-01-01',
        ]);
        $insideBooking = Bookings::factory()->for($band, 'band')->create(['name' => 'Inside']);
        Events::factory()->create([
            'eventable_type' => Bookings::class, 'eventable_id' => $insideBooking->id,
            'date' => '2026-03-15',
        ]);
        $afterBooking = Bookings::factory()->for($band, 'band')->create(['name' => 'After']);
        Events::factory()->create([
            'eventable_type' => Bookings::class, 'eventable_id' => $afterBooking->id,
            'date' => '2026-12-01',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/mobile/me/bookings?from=2026-02-01&to=2026-05-01');

        $response->assertOk();
        $names = collect($response->json('bookings'))->pluck('name')->all();
        $this->assertEqualsCanonicalizing(['Inside'], $names);
    }

    public function test_from_after_to_returns_422(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/mobile/me/bookings?from=2026-06-01&to=2026-05-01');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('from');
    }

    public function test_amount_paid_is_correct_with_aggregated_payments(): void
    {
        // amount_paid is fed by the withSum('payment_total_cents') aggregate
        // (paid-only, raw cents). Guards that the aggregate matches a manual
        // sum and that pending payments are excluded.
        $user = User::factory()->create();
        $band = Bands::create([
            'name' => 'Band', 'site_name' => 'b-' . uniqid(), 'is_personal' => false,
        ]);
        BandOwners::create(['user_id' => $user->id, 'band_id' => $band->id]);

        $booking = Bookings::factory()->for($band, 'band')->create(['name' => 'Paid Gig']);
        // Price cast set() multiplies by 100, so these are dollars: 300 + 150 = 450 paid.
        Payments::factory()->create([
                'band_id' => $band->id,
            'payable_type' => Bookings::class, 'payable_id' => $booking->id,
            'amount' => 300, 'status' => 'paid',
        ]);
        Payments::factory()->create([
                'band_id' => $band->id,
            'payable_type' => Bookings::class, 'payable_id' => $booking->id,
            'amount' => 150, 'status' => 'paid',
        ]);
        // A pending payment must NOT count toward amount_paid.
        Payments::factory()->create([
                'band_id' => $band->id,
            'payable_type' => Bookings::class, 'payable_id' => $booking->id,
            'amount' => 999, 'status' => 'pending',
        ]);

        $response = $this->actingAs($user)->getJson('/api/mobile/me/bookings');
        $response->assertOk();

        $paid = collect($response->json('bookings'))->firstWhere('name', 'Paid Gig');
        $this->assertSame('450.00', $paid['amount_paid']);

        // The list must not serialize per-payment detail: amount_paid comes
        // from the withSum aggregate, not an eager-loaded payments relation.
        // Guards against reintroducing ->with('payments') on the list query.
        $this->assertSame([], $paid['payments']);
    }

    public function test_index_does_not_run_per_booking_payment_query(): void
    {
        // Regression for TTS-BAND-113: amount_paid ran a sum() query per
        // booking. Query count must not scale with the number of bookings.
        $user = User::factory()->create();
        $band = Bands::create([
            'name' => 'Band', 'site_name' => 'b-' . uniqid(), 'is_personal' => false,
        ]);
        BandOwners::create(['user_id' => $user->id, 'band_id' => $band->id]);

        foreach (range(1, 5) as $i) {
            $booking = Bookings::factory()->for($band, 'band')->create(['name' => "Gig $i"]);
            Payments::factory()->create([
                'band_id' => $band->id,
                'payable_type' => Bookings::class, 'payable_id' => $booking->id,
                'amount' => 100, 'status' => 'paid',
            ]);
        }

        $token = $user->createToken('test')->plainTextToken;

        DB::enableQueryLog();
        $response = $this->withToken($token)->getJson('/api/mobile/me/bookings');
        $response->assertOk();
        $this->assertCount(5, $response->json('bookings'));

        $paymentAggregates = collect(DB::getQueryLog())
            ->filter(fn ($q) => str_contains($q['query'], 'sum(`amount`)')
                && str_contains($q['query'], 'payments'))
            ->count();
        DB::disableQueryLog();

        // With the withSum aggregate, the per-booking sum() queries are gone.
        $this->assertSame(0, $paymentAggregates,
            "Expected no per-booking payment sum() queries, found {$paymentAggregates}");
    }

    public function test_no_params_still_returns_all_bookings(): void
    {
        $user = User::factory()->create();
        $band = Bands::create([
            'name' => 'Band', 'site_name' => 'b-' . uniqid(), 'is_personal' => false,
        ]);
        BandOwners::create(['user_id' => $user->id, 'band_id' => $band->id]);

        Bookings::factory()->for($band, 'band')->create();
        Bookings::factory()->for($band, 'band')->create();
        Bookings::factory()->for($band, 'band')->create();

        $response = $this->actingAs($user)->getJson('/api/mobile/me/bookings');

        $response->assertOk();
        $this->assertCount(3, $response->json('bookings'));
    }
}
