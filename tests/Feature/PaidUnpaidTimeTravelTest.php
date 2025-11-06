<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Bands;
use App\Models\BandOwners;
use App\Models\Bookings;
use App\Models\Payments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

class PaidUnpaidTimeTravelTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $band;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->band = Bands::factory()->create();
        BandOwners::create([
            'user_id' => $this->user->id,
            'band_id' => $this->band->id
        ]);
    }

    public function test_paid_unpaid_route_returns_successful_response()
    {
        $response = $this->actingAs($this->user)
            ->get(route('Paid/Unpaid'));

        $response->assertStatus(200);
    }

    public function test_paid_unpaid_returns_inertia_component()
    {
        $response = $this->actingAs($this->user)
            ->get(route('Paid/Unpaid'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Finances/PaidUnpaid')
            ->has('paidUnpaid')
        );
    }

    public function test_paid_unpaid_with_snapshot_date_filters_bookings()
    {
        // Create bookings at different times
        $oldBooking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 10000,
            'created_at' => now()->subDays(10)
        ]);

        $newBooking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 10000,
            'created_at' => now()->subDays(2)
        ]);

        // Request with snapshot date 5 days ago
        $snapshotDate = now()->subDays(5)->format('Y-m-d');

        $response = $this->actingAs($this->user)
            ->get(route('Paid/Unpaid', ['snapshot_date' => $snapshotDate]));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Finances/PaidUnpaid')
            ->has('paidUnpaid')
            ->has('snapshotDate')
            ->where('snapshotDate', $snapshotDate)
            ->where('paidUnpaid.0.unpaidBookings', function ($bookings) use ($oldBooking) {
                return count($bookings) === 1 && $bookings[0]['id'] === $oldBooking->id;
            })
        );
    }

    public function test_paid_unpaid_without_snapshot_date_returns_all_bookings()
    {
        // Create bookings at different times
        $oldBooking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 10000,
            'created_at' => now()->subDays(10)
        ]);

        $newBooking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 10000,
            'created_at' => now()->subDays(2)
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('Paid/Unpaid'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Finances/PaidUnpaid')
            ->has('paidUnpaid')
            ->where('paidUnpaid.0.unpaidBookings', function ($bookings) {
                return count($bookings) === 2;
            })
        );
    }

    public function test_paid_unpaid_with_snapshot_date_before_all_bookings_returns_empty()
    {
        Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 10000,
            'created_at' => now()->subDays(5)
        ]);

        // Request with snapshot date before any bookings
        $snapshotDate = now()->subDays(10)->format('Y-m-d');

        $response = $this->actingAs($this->user)
            ->get(route('Paid/Unpaid', ['snapshot_date' => $snapshotDate]));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Finances/PaidUnpaid')
            ->has('paidUnpaid')
            ->where('paidUnpaid.0.unpaidBookings', function ($bookings) {
                return count($bookings) === 0;
            })
            ->where('paidUnpaid.0.paidBookings', function ($bookings) {
                return count($bookings) === 0;
            })
        );
    }

    public function test_paid_unpaid_distinguishes_between_paid_and_unpaid()
    {
        $unpaidBooking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 10000,
            'created_at' => now()->subDays(10)
        ]);

        $paidBooking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 10000,
            'created_at' => now()->subDays(10)
        ]);

        Payments::factory()->create([
            'payable_id' => $paidBooking->id,
            'payable_type' => Bookings::class,
            'band_id' => $this->band->id,
            'amount' => 10000
        ]);

        $snapshotDate = now()->subDays(5)->format('Y-m-d');

        $response = $this->actingAs($this->user)
            ->get(route('Paid/Unpaid', ['snapshot_date' => $snapshotDate]));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Finances/PaidUnpaid')
            ->has('paidUnpaid')
            ->where('paidUnpaid.0.paidBookings', function ($bookings) use ($paidBooking) {
                return count($bookings) === 1 && $bookings[0]['id'] === $paidBooking->id;
            })
            ->where('paidUnpaid.0.unpaidBookings', function ($bookings) use ($unpaidBooking) {
                return count($bookings) === 1 && $bookings[0]['id'] === $unpaidBooking->id;
            })
        );
    }

    public function test_paid_unpaid_requires_authentication()
    {
        $response = $this->get(route('Paid/Unpaid'));

        $response->assertRedirect('/login');
    }

    public function test_paid_unpaid_with_multiple_bands_filters_correctly()
    {
        // Create a second band
        $band2 = Bands::factory()->create();
        BandOwners::create([
            'user_id' => $this->user->id,
            'band_id' => $band2->id
        ]);

        // Create bookings for both bands
        $band1OldBooking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 10000,
            'created_at' => now()->subDays(10)
        ]);

        $band1NewBooking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 10000,
            'created_at' => now()->subDays(2)
        ]);

        $band2OldBooking = Bookings::factory()->create([
            'band_id' => $band2->id,
            'price' => 20000,
            'created_at' => now()->subDays(10)
        ]);

        $snapshotDate = now()->subDays(5)->format('Y-m-d');

        $response = $this->actingAs($this->user)
            ->get(route('Paid/Unpaid', ['snapshot_date' => $snapshotDate]));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Finances/PaidUnpaid')
            ->has('paidUnpaid', 2)
            ->where('paidUnpaid.0.unpaidBookings', function ($bookings) {
                return count($bookings) === 1;
            })
            ->where('paidUnpaid.1.unpaidBookings', function ($bookings) {
                return count($bookings) === 1;
            })
        );
    }
}
