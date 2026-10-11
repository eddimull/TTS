<?php

namespace Tests\Feature;

use App\Models\Bands;
use App\Models\EventTypes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PriceEstimatorWebTest extends TestCase
{
    use RefreshDatabase;

    private Bands $band;
    private User $owner;
    private User $member;
    private EventTypes $wedding;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();

        $this->owner = User::factory()->create();
        $this->member = User::factory()->create();
        $this->band = Bands::factory()->create();
        $this->band->owners()->create(['user_id' => $this->owner->id]);
        $this->band->members()->create(['user_id' => $this->member->id]);
        $this->wedding = EventTypes::factory()->create(['name' => 'Wedding']);
    }

    public function test_owner_can_view_estimator_page(): void
    {
        $this->actingAs($this->owner)
            ->get("/bands/{$this->band->id}/price-estimator")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('PriceEstimator/Index')
                ->where('band.id', $this->band->id)
                ->has('addOns', 3)
                ->where('standardHours', 4)
            );
    }

    public function test_member_cannot_view_estimator_page(): void
    {
        $this->actingAs($this->member)
            ->get("/bands/{$this->band->id}/price-estimator")
            ->assertForbidden();
    }

    public function test_owner_can_request_estimate_json(): void
    {
        $this->actingAs($this->owner)
            ->postJson("/bands/{$this->band->id}/price-estimate", [
                'event_type_id' => $this->wedding->id,
                'date' => '2027-05-15',
                'start_time' => '19:00',
                'duration_hours' => 4,
            ])
            ->assertOk()
            ->assertJsonStructure(['estimate', 'low', 'high', 'confidence', 'baseline_source', 'breakdown', 'warnings', 'comparables'])
            ->assertJsonPath('warnings.0.code', 'no_baseline');
    }

    public function test_estimate_json_validates(): void
    {
        $this->actingAs($this->owner)
            ->postJson("/bands/{$this->band->id}/price-estimate", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['event_type_id', 'date']);
    }

    public function test_member_cannot_request_estimate(): void
    {
        $this->actingAs($this->member)
            ->postJson("/bands/{$this->band->id}/price-estimate", [
                'event_type_id' => $this->wedding->id,
                'date' => '2027-05-15',
            ])
            ->assertForbidden();
    }

    public function test_owner_can_read_and_patch_settings(): void
    {
        $this->actingAs($this->owner)
            ->getJson("/bands/{$this->band->id}/pricing-settings")
            ->assertOk()
            ->assertJsonPath('settings.round_to', 50.0)
            ->assertJsonStructure(['settings', 'event_types', 'history']);

        $this->actingAs($this->owner)
            ->patchJson("/bands/{$this->band->id}/pricing-settings", [
                'settings' => ['lodging_fee' => 350, 'base_rates' => [(string) $this->wedding->id => 8000]],
            ])
            ->assertOk()
            ->assertJsonPath('settings.lodging_fee', 350.0)
            ->assertJsonPath("settings.base_rates.{$this->wedding->id}", 8000.0);
    }

    public function test_member_cannot_read_or_patch_settings(): void
    {
        $this->actingAs($this->member)
            ->getJson("/bands/{$this->band->id}/pricing-settings")
            ->assertForbidden();

        $this->actingAs($this->member)
            ->patchJson("/bands/{$this->band->id}/pricing-settings", ['settings' => ['round_to' => 10]])
            ->assertForbidden();
    }

    public function test_shared_props_expose_owned_band_ids(): void
    {
        $this->actingAs($this->owner)
            ->get("/bands/{$this->band->id}/price-estimator")
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.owned_band_ids', [$this->band->id])
            );

        $this->actingAs($this->member)
            ->get('/bookings')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.owned_band_ids', [])
            );
    }
}
