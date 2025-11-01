<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\Bands;
use App\Models\BandPaymentGroup;
use App\Services\PaymentGroupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

class PaymentGroupServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PaymentGroupService $service;
    protected Bands $band;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new PaymentGroupService();
        $this->band = Bands::factory()->create(['name' => 'Test Band']);
    }

    public function test_can_create_payment_group()
    {
        $data = [
            'name' => 'Sound Crew',
            'description' => 'Audio engineers',
            'default_payout_type' => 'fixed',
            'default_payout_value' => 500.00,
            'display_order' => 1,
            'is_active' => true,
        ];

        $group = $this->service->create($this->band->id, $data);

        $this->assertInstanceOf(BandPaymentGroup::class, $group);
        $this->assertEquals('Sound Crew', $group->name);
        $this->assertEquals('Audio engineers', $group->description);
        $this->assertEquals('fixed', $group->default_payout_type);
        $this->assertEquals(500.00, $group->default_payout_value);
        $this->assertEquals(1, $group->display_order);
        $this->assertTrue($group->is_active);

        $this->assertDatabaseHas('band_payment_groups', [
            'band_id' => $this->band->id,
            'name' => 'Sound Crew',
        ]);
    }

    public function test_create_with_minimal_data()
    {
        $data = [
            'name' => 'Players',
            'default_payout_type' => 'equal_split',
        ];

        $group = $this->service->create($this->band->id, $data);

        $this->assertEquals('Players', $group->name);
        $this->assertEquals('equal_split', $group->default_payout_type);
        $this->assertNull($group->description);
        $this->assertNull($group->default_payout_value);
        $this->assertEquals(0, $group->display_order);
        $this->assertTrue($group->is_active);
    }

    public function test_create_fails_without_required_fields()
    {
        $this->expectException(ValidationException::class);
        
        $data = [
            'description' => 'Missing required fields',
        ];

        $this->service->create($this->band->id, $data);
    }

    public function test_create_fails_with_invalid_payout_type()
    {
        $this->expectException(ValidationException::class);
        
        $data = [
            'name' => 'Test Group',
            'default_payout_type' => 'invalid_type',
        ];

        $this->service->create($this->band->id, $data);
    }

    public function test_create_fails_with_duplicate_name_for_same_band()
    {
        $data = [
            'name' => 'Sound Crew',
            'default_payout_type' => 'fixed',
        ];

        $this->service->create($this->band->id, $data);

        $this->expectException(ValidationException::class);
        $this->service->create($this->band->id, $data);
    }

    public function test_create_allows_duplicate_name_for_different_bands()
    {
        $band2 = Bands::factory()->create(['name' => 'Another Band']);

        $data = [
            'name' => 'Sound Crew',
            'default_payout_type' => 'fixed',
        ];

        $group1 = $this->service->create($this->band->id, $data);
        $group2 = $this->service->create($band2->id, $data);

        $this->assertNotEquals($group1->id, $group2->id);
        $this->assertEquals($group1->name, $group2->name);
    }

    public function test_create_fails_with_percentage_over_100()
    {
        $this->expectException(ValidationException::class);
        
        $data = [
            'name' => 'Test Group',
            'default_payout_type' => 'percentage',
            'default_payout_value' => 150,
        ];

        $this->service->create($this->band->id, $data);
    }

    public function test_can_update_payment_group()
    {
        $group = BandPaymentGroup::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'Original Name',
            'default_payout_type' => 'equal_split',
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'description' => 'Updated description',
            'default_payout_type' => 'fixed',
            'default_payout_value' => 600.00,
            'is_active' => false,
        ];

        $updated = $this->service->update($this->band->id, $group->id, $updateData);

        $this->assertEquals('Updated Name', $updated->name);
        $this->assertEquals('Updated description', $updated->description);
        $this->assertEquals('fixed', $updated->default_payout_type);
        $this->assertEquals(600.00, $updated->default_payout_value);
        $this->assertFalse($updated->is_active);
    }

    public function test_update_with_partial_data()
    {
        $group = BandPaymentGroup::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'Original Name',
            'description' => 'Original description',
            'default_payout_type' => 'equal_split',
        ]);

        $updateData = [
            'name' => 'Updated Name',
        ];

        $updated = $this->service->update($this->band->id, $group->id, $updateData);

        $this->assertEquals('Updated Name', $updated->name);
        $this->assertEquals('Original description', $updated->description); // Unchanged
        $this->assertEquals('equal_split', $updated->default_payout_type); // Unchanged
    }

    public function test_update_fails_for_nonexistent_group()
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        
        $this->service->update($this->band->id, 99999, ['name' => 'Test']);
    }

    public function test_update_fails_for_group_in_different_band()
    {
        $band2 = Bands::factory()->create();
        $group = BandPaymentGroup::factory()->create(['band_id' => $band2->id]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        
        $this->service->update($this->band->id, $group->id, ['name' => 'Test']);
    }

    public function test_can_delete_payment_group()
    {
        $group = BandPaymentGroup::factory()->create([
            'band_id' => $this->band->id,
        ]);

        $result = $this->service->delete($this->band->id, $group->id);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('band_payment_groups', [
            'id' => $group->id,
        ]);
    }

    public function test_delete_fails_for_nonexistent_group()
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        
        $this->service->delete($this->band->id, 99999);
    }

    public function test_can_get_groups_by_band()
    {
        BandPaymentGroup::factory()->count(3)->create([
            'band_id' => $this->band->id,
        ]);

        $band2 = Bands::factory()->create();
        BandPaymentGroup::factory()->count(2)->create([
            'band_id' => $band2->id,
        ]);

        $groups = $this->service->getByBand($this->band->id);

        $this->assertCount(3, $groups);
        foreach ($groups as $group) {
            $this->assertEquals($this->band->id, $group->band_id);
        }
    }

    public function test_get_by_band_filters_active_only()
    {
        BandPaymentGroup::factory()->count(2)->create([
            'band_id' => $this->band->id,
            'is_active' => true,
        ]);

        BandPaymentGroup::factory()->count(3)->create([
            'band_id' => $this->band->id,
            'is_active' => false,
        ]);

        $allGroups = $this->service->getByBand($this->band->id, false);
        $activeGroups = $this->service->getByBand($this->band->id, true);

        $this->assertCount(5, $allGroups);
        $this->assertCount(2, $activeGroups);
    }

    public function test_get_by_band_orders_by_display_order_and_name()
    {
        BandPaymentGroup::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'Zebras',
            'display_order' => 1,
        ]);

        BandPaymentGroup::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'Alpha',
            'display_order' => 2,
        ]);

        BandPaymentGroup::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'Beta',
            'display_order' => 2,
        ]);

        $groups = $this->service->getByBand($this->band->id);

        $this->assertEquals('Zebras', $groups[0]->name);
        $this->assertEquals('Alpha', $groups[1]->name);
        $this->assertEquals('Beta', $groups[2]->name);
    }

    public function test_can_reorder_payment_groups()
    {
        $group1 = BandPaymentGroup::factory()->create([
            'band_id' => $this->band->id,
            'display_order' => 0,
        ]);

        $group2 = BandPaymentGroup::factory()->create([
            'band_id' => $this->band->id,
            'display_order' => 1,
        ]);

        $group3 = BandPaymentGroup::factory()->create([
            'band_id' => $this->band->id,
            'display_order' => 2,
        ]);

        // Reorder: 3, 1, 2
        $this->service->reorder($this->band->id, [$group3->id, $group1->id, $group2->id]);

        $this->assertEquals(0, $group3->fresh()->display_order);
        $this->assertEquals(1, $group1->fresh()->display_order);
        $this->assertEquals(2, $group2->fresh()->display_order);
    }

    public function test_can_toggle_active_status()
    {
        $group = BandPaymentGroup::factory()->create([
            'band_id' => $this->band->id,
            'is_active' => true,
        ]);

        $updated = $this->service->toggleActive($this->band->id, $group->id);
        $this->assertFalse($updated->is_active);

        $updated = $this->service->toggleActive($this->band->id, $group->id);
        $this->assertTrue($updated->is_active);
    }

    public function test_find_group_or_fail_returns_group()
    {
        $group = BandPaymentGroup::factory()->create([
            'band_id' => $this->band->id,
        ]);

        $found = $this->service->findGroupOrFail($this->band->id, $group->id);

        $this->assertEquals($group->id, $found->id);
    }

    public function test_find_group_or_fail_throws_exception()
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        
        $this->service->findGroupOrFail($this->band->id, 99999);
    }
}
