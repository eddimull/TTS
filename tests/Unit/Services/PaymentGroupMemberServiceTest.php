<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\Bands;
use App\Models\BandPaymentGroup;
use App\Models\User;
use App\Services\PaymentGroupMemberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

class PaymentGroupMemberServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PaymentGroupMemberService $service;
    protected Bands $band;
    protected BandPaymentGroup $group;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new PaymentGroupMemberService();
        $this->band = Bands::factory()->create();
        $this->group = BandPaymentGroup::factory()->create([
            'band_id' => $this->band->id,
        ]);
        $this->user = User::factory()->create();
    }

    public function test_can_add_member_to_group()
    {
        $data = [
            'payout_type' => 'fixed',
            'payout_value' => 600.00,
            'notes' => 'Lead engineer',
        ];

        $this->service->addMember($this->band->id, $this->group->id, $this->user->id, $data);

        $this->assertDatabaseHas('band_payment_group_members', [
            'band_payment_group_id' => $this->group->id,
            'user_id' => $this->user->id,
            'payout_type' => 'fixed',
            'payout_value' => 600.00,
            'notes' => 'Lead engineer',
        ]);
    }

    public function test_can_add_member_with_minimal_data()
    {
        $this->service->addMember($this->band->id, $this->group->id, $this->user->id);

        $this->assertDatabaseHas('band_payment_group_members', [
            'band_payment_group_id' => $this->group->id,
            'user_id' => $this->user->id,
        ]);

        $member = $this->group->users()->where('user_id', $this->user->id)->first();
        $this->assertNull($member->pivot->payout_type);
        $this->assertNull($member->pivot->payout_value);
    }

    public function test_add_member_fails_for_nonexistent_user()
    {
        $this->expectException(ValidationException::class);
        
        $this->service->addMember($this->band->id, $this->group->id, 99999);
    }

    public function test_add_member_fails_for_duplicate()
    {
        $this->service->addMember($this->band->id, $this->group->id, $this->user->id);

        $this->expectException(ValidationException::class);
        $this->service->addMember($this->band->id, $this->group->id, $this->user->id);
    }

    public function test_add_member_fails_with_invalid_payout_type()
    {
        $this->expectException(ValidationException::class);
        
        $data = [
            'payout_type' => 'invalid_type',
        ];

        $this->service->addMember($this->band->id, $this->group->id, $this->user->id, $data);
    }

    public function test_add_member_fails_with_percentage_over_100()
    {
        $this->expectException(ValidationException::class);
        
        $data = [
            'payout_type' => 'percentage',
            'payout_value' => 150,
        ];

        $this->service->addMember($this->band->id, $this->group->id, $this->user->id, $data);
    }

    public function test_can_remove_member_from_group()
    {
        $this->group->users()->attach($this->user->id);

        $this->service->removeMember($this->band->id, $this->group->id, $this->user->id);

        $this->assertDatabaseMissing('band_payment_group_members', [
            'band_payment_group_id' => $this->group->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_remove_member_fails_for_nonmember()
    {
        $this->expectException(ValidationException::class);
        
        $this->service->removeMember($this->band->id, $this->group->id, $this->user->id);
    }

    public function test_can_update_member_configuration()
    {
        $this->group->users()->attach($this->user->id, [
            'payout_type' => 'equal_split',
        ]);

        $updateData = [
            'payout_type' => 'fixed',
            'payout_value' => 700.00,
            'notes' => 'Updated notes',
        ];

        $this->service->updateMember($this->band->id, $this->group->id, $this->user->id, $updateData);

        $member = $this->group->fresh()->users()->where('user_id', $this->user->id)->first();
        $this->assertEquals('fixed', $member->pivot->payout_type);
        $this->assertEquals(700.00, $member->pivot->payout_value);
        $this->assertEquals('Updated notes', $member->pivot->notes);
    }

    public function test_update_member_fails_for_nonmember()
    {
        $this->expectException(ValidationException::class);
        
        $this->service->updateMember($this->band->id, $this->group->id, $this->user->id, [
            'payout_type' => 'fixed',
        ]);
    }

    public function test_can_get_members_of_group()
    {
        $users = User::factory()->count(3)->create();
        
        foreach ($users as $user) {
            $this->group->users()->attach($user->id);
        }

        $members = $this->service->getMembers($this->band->id, $this->group->id);

        $this->assertCount(3, $members);
        $this->assertEquals($users->pluck('id')->sort()->values(), $members->pluck('id')->sort()->values());
    }

    public function test_can_get_member_config()
    {
        $this->group->users()->attach($this->user->id, [
            'payout_type' => 'percentage',
            'payout_value' => 20,
        ]);

        $config = $this->service->getMemberConfig($this->band->id, $this->group->id, $this->user->id);

        $this->assertEquals('percentage', $config['payout_type']);
        $this->assertEquals(20, $config['payout_value']);
    }

    public function test_get_member_config_returns_group_default_when_no_override()
    {
        $group = BandPaymentGroup::factory()->fixed(500)->create([
            'band_id' => $this->band->id,
        ]);

        $group->users()->attach($this->user->id);

        $config = $this->service->getMemberConfig($this->band->id, $group->id, $this->user->id);

        $this->assertEquals('fixed', $config['payout_type']);
        $this->assertEquals(500.00, $config['payout_value']);
    }

    public function test_can_bulk_add_members()
    {
        $users = User::factory()->count(3)->create();
        $userIds = $users->pluck('id')->toArray();

        $defaultConfig = [
            'payout_type' => 'equal_split',
            'notes' => 'Bulk added',
        ];

        $this->service->addMembers($this->band->id, $this->group->id, $userIds, $defaultConfig);

        $this->assertEquals(3, $this->group->users()->count());

        foreach ($users as $user) {
            $member = $this->group->users()->where('user_id', $user->id)->first();
            $this->assertEquals('equal_split', $member->pivot->payout_type);
            $this->assertEquals('Bulk added', $member->pivot->notes);
        }
    }

    public function test_bulk_add_skips_invalid_users()
    {
        $validUser = User::factory()->create();
        $userIds = [$validUser->id, 99999, 88888]; // Include invalid IDs

        $this->service->addMembers($this->band->id, $this->group->id, $userIds);

        $this->assertEquals(1, $this->group->users()->count());
        $this->assertTrue($this->group->users()->where('user_id', $validUser->id)->exists());
    }

    public function test_bulk_add_skips_already_added_users()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Add user1 first
        $this->group->users()->attach($user1->id);

        // Try to bulk add both
        $this->service->addMembers($this->band->id, $this->group->id, [$user1->id, $user2->id]);

        $this->assertEquals(2, $this->group->users()->count());
    }

    public function test_can_clear_all_members()
    {
        $users = User::factory()->count(5)->create();
        
        foreach ($users as $user) {
            $this->group->users()->attach($user->id);
        }

        $this->assertEquals(5, $this->group->users()->count());

        $this->service->clearMembers($this->band->id, $this->group->id);

        $this->assertEquals(0, $this->group->fresh()->users()->count());
    }

    public function test_can_check_if_user_is_member()
    {
        $this->assertFalse($this->service->isMember($this->band->id, $this->group->id, $this->user->id));

        $this->group->users()->attach($this->user->id);

        $this->assertTrue($this->service->isMember($this->band->id, $this->group->id, $this->user->id));
    }

    public function test_can_get_user_groups()
    {
        $group1 = BandPaymentGroup::factory()->create(['band_id' => $this->band->id]);
        $group2 = BandPaymentGroup::factory()->create(['band_id' => $this->band->id]);
        $group3 = BandPaymentGroup::factory()->create(['band_id' => $this->band->id]);

        // Add user to group1 and group3
        $group1->users()->attach($this->user->id);
        $group3->users()->attach($this->user->id);

        $userGroups = $this->service->getUserGroups($this->band->id, $this->user->id);

        $this->assertCount(2, $userGroups);
        $this->assertTrue($userGroups->contains('id', $group1->id));
        $this->assertTrue($userGroups->contains('id', $group3->id));
        $this->assertFalse($userGroups->contains('id', $group2->id));
    }

    public function test_get_user_groups_returns_empty_for_nonmember()
    {
        BandPaymentGroup::factory()->count(3)->create(['band_id' => $this->band->id]);

        $userGroups = $this->service->getUserGroups($this->band->id, $this->user->id);

        $this->assertCount(0, $userGroups);
    }

    public function test_get_user_groups_filters_by_band()
    {
        $band2 = Bands::factory()->create();
        
        $group1 = BandPaymentGroup::factory()->create(['band_id' => $this->band->id]);
        $group2 = BandPaymentGroup::factory()->create(['band_id' => $band2->id]);

        $group1->users()->attach($this->user->id);
        $group2->users()->attach($this->user->id);

        $userGroupsBand1 = $this->service->getUserGroups($this->band->id, $this->user->id);
        $userGroupsBand2 = $this->service->getUserGroups($band2->id, $this->user->id);

        $this->assertCount(1, $userGroupsBand1);
        $this->assertCount(1, $userGroupsBand2);
        $this->assertEquals($group1->id, $userGroupsBand1->first()->id);
        $this->assertEquals($group2->id, $userGroupsBand2->first()->id);
    }
}
