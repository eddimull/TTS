<?php

namespace Tests\Feature;

use App\Models\BandMembers;
use App\Models\BandOwners;
use App\Models\BandPayoutConfig;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\EventMember;
use App\Models\Events;
use App\Models\Roster;
use App\Models\RosterMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubPayoutTypeTest extends TestCase
{
    use RefreshDatabase;

    private Bands $band;
    private User $owner;
    private Bookings $booking;
    private BandPayoutConfig $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['name' => 'Band Owner']);
        $this->band = Bands::factory()->create();

        BandOwners::create([
            'user_id' => $this->owner->id,
            'band_id' => $this->band->id,
        ]);

        $this->booking = Bookings::factory()->forBand($this->band)->create(['price' => 1000]);

        // Config that pays everyone (members + subs) from the roster attendance data
        $this->config = $this->makeConfig('all');
    }

    private function makeConfig(string $memberTypeFilter): BandPayoutConfig
    {
        return BandPayoutConfig::create([
            'band_id' => $this->band->id,
            'name' => 'Test Config',
            'is_active' => true,
            'band_cut_type' => 'none',
            'band_cut_value' => 0,
            'member_payout_type' => 'equal_split',
            'include_owners' => true,
            'include_members' => true,
            'minimum_payout' => 0,
            'flow_diagram' => [
                'nodes' => [
                    [
                        'id' => 'income-1',
                        'type' => 'income',
                        'data' => ['label' => 'Income'],
                    ],
                    [
                        'id' => 'payout-all',
                        'type' => 'payoutGroup',
                        'data' => [
                            'label' => 'Payout Group',
                            'sourceType' => 'roster',
                            'rosterConfig' => [
                                'memberTypeFilter' => $memberTypeFilter,
                            ],
                            'incomingAllocationType' => 'remainder',
                            'distributionMode' => 'equal_split',
                        ],
                    ],
                ],
                'edges' => [
                    ['source' => 'income-1', 'target' => 'payout-all'],
                ],
            ],
        ]);
    }

    private function createEvent(): Events
    {
        return Events::factory()->create([
            'eventable_id' => $this->booking->id,
            'eventable_type' => 'App\Models\Bookings',
        ]);
    }

    /**
     * A sub added without a user account (no user_id, no roster_member_id)
     * must always be classified as 'substitute'.
     */
    public function test_anonymous_sub_is_classified_as_substitute(): void
    {
        $config = $this->makeConfig('all');
        $event = $this->createEvent();

        EventMember::create([
            'event_id' => $event->id,
            'band_id' => $this->band->id,
            'user_id' => null,
            'roster_member_id' => null,
            'name' => 'Anonymous Sub',
            'attendance_status' => 'attended',
        ]);

        $result = $config->calculatePayouts(1000, null, $this->booking);

        $subPayout = collect($result['member_payouts'])->firstWhere('name', 'Anonymous Sub');
        $this->assertNotNull($subPayout, 'Anonymous sub should appear in payout results');
        $this->assertEquals('substitute', $subPayout['type']);
    }

    /**
     * A sub who has a registered user account but was added directly (no roster_member_id)
     * must be classified as 'substitute', not 'member'.
     *
     * This is the bug: adding the same registered user as a sub on a second gig
     * was incorrectly giving them member-rate pay.
     */
    public function test_registered_user_added_as_sub_is_classified_as_substitute(): void
    {
        $subUser = User::factory()->create(['name' => 'Registered Sub']);

        $event = $this->createEvent();

        // Simulate what EventMemberController::store() does when a registered user is added as a sub
        EventMember::create([
            'event_id' => $event->id,
            'band_id' => $this->band->id,
            'user_id' => $subUser->id,   // Has a user_id because they're registered
            'roster_member_id' => null,  // But NOT on the roster — added as a sub
            'name' => null,
            'attendance_status' => 'attended',
        ]);

        $result = $this->config->calculatePayouts(1000, null, $this->booking);

        $subPayout = collect($result['member_payouts'])->firstWhere('user_id', $subUser->id);
        $this->assertNotNull($subPayout, 'Registered sub should appear in payout results');
        $this->assertEquals('substitute', $subPayout['type'],
            'A user added as a sub (no roster_member_id) should be "substitute", not "member"'
        );
    }

    /**
     * A registered user on the roster should remain classified as 'member'.
     */
    public function test_roster_member_with_user_is_classified_as_member(): void
    {
        $config = $this->makeConfig('all');
        $memberUser = User::factory()->create(['name' => 'Roster Member']);

        $roster = Roster::factory()->create(['band_id' => $this->band->id]);
        $rosterMember = RosterMember::factory()->user($memberUser)->create([
            'roster_id' => $roster->id,
        ]);

        $event = $this->createEvent();

        EventMember::create([
            'event_id' => $event->id,
            'band_id' => $this->band->id,
            'user_id' => $memberUser->id,
            'roster_member_id' => $rosterMember->id,
            'attendance_status' => 'attended',
        ]);

        $result = $config->calculatePayouts(1000, null, $this->booking);

        $memberPayout = collect($result['member_payouts'])->firstWhere('user_id', $memberUser->id);
        $this->assertNotNull($memberPayout, 'Roster member should appear in payout results');
        $this->assertEquals('member', $memberPayout['type']);
    }

    /**
     * A non-user roster entry (roster sub/guest, no user_id) should be 'substitute'.
     */
    public function test_roster_member_without_user_is_classified_as_substitute(): void
    {
        $config = $this->makeConfig('all');
        $roster = Roster::factory()->create(['band_id' => $this->band->id]);
        $rosterMember = RosterMember::factory()->nonUser()->create([
            'roster_id' => $roster->id,
            'name' => 'Roster Guest',
        ]);

        $event = $this->createEvent();

        EventMember::create([
            'event_id' => $event->id,
            'band_id' => $this->band->id,
            'user_id' => null,
            'roster_member_id' => $rosterMember->id,
            'attendance_status' => 'attended',
        ]);

        $result = $config->calculatePayouts(1000, null, $this->booking);

        $subPayout = collect($result['member_payouts'])->firstWhere('name', 'Roster Guest');
        $this->assertNotNull($subPayout, 'Roster guest should appear in payout results');
        $this->assertEquals('substitute', $subPayout['type']);
    }

    /**
     * The members_only filter should exclude subs (including registered-user subs)
     * and only include members from the roster.
     */
    public function test_members_only_filter_excludes_registered_user_subs(): void
    {
        $config = $this->makeConfig('members_only');
        $memberUser = User::factory()->create(['name' => 'Real Member']);
        $subUser = User::factory()->create(['name' => 'Registered Sub']);

        $roster = Roster::factory()->create(['band_id' => $this->band->id]);
        $rosterMember = RosterMember::factory()->user($memberUser)->create([
            'roster_id' => $roster->id,
        ]);

        $event = $this->createEvent();

        // Roster member (type = 'member')
        EventMember::create([
            'event_id' => $event->id,
            'band_id' => $this->band->id,
            'user_id' => $memberUser->id,
            'roster_member_id' => $rosterMember->id,
            'attendance_status' => 'attended',
        ]);

        // Registered user added as sub (type should be 'substitute')
        EventMember::create([
            'event_id' => $event->id,
            'band_id' => $this->band->id,
            'user_id' => $subUser->id,
            'roster_member_id' => null,
            'attendance_status' => 'attended',
        ]);

        $result = $config->calculatePayouts(1000, null, $this->booking);

        // members_only filter — only the roster member should receive a payout
        $this->assertCount(1, $result['member_payouts'],
            'Only the roster member should be paid under members_only filter'
        );
        $this->assertEquals($memberUser->id, $result['member_payouts'][0]['user_id']);
        $this->assertEquals('member', $result['member_payouts'][0]['type']);
    }

    private function makeConfigWithRoleFilter(string $memberTypeFilter, array $filterByRoleId): BandPayoutConfig
    {
        return BandPayoutConfig::create([
            'band_id' => $this->band->id,
            'name' => 'Test Config With Role Filter',
            'is_active' => true,
            'band_cut_type' => 'none',
            'band_cut_value' => 0,
            'member_payout_type' => 'equal_split',
            'include_owners' => true,
            'include_members' => true,
            'minimum_payout' => 0,
            'flow_diagram' => [
                'nodes' => [
                    [
                        'id' => 'income-1',
                        'type' => 'income',
                        'data' => ['label' => 'Income'],
                    ],
                    [
                        'id' => 'payout-all',
                        'type' => 'payoutGroup',
                        'data' => [
                            'label' => 'Payout Group',
                            'sourceType' => 'roster',
                            'rosterConfig' => [
                                'memberTypeFilter' => $memberTypeFilter,
                                'filterByRoleId' => $filterByRoleId,
                            ],
                            'incomingAllocationType' => 'remainder',
                            'distributionMode' => 'fixed',
                            'fixedAmountPerMember' => 400,
                        ],
                    ],
                ],
                'edges' => [
                    ['source' => 'income-1', 'target' => 'payout-all'],
                ],
            ],
        ]);
    }

    /**
     * A sub with no band_role_id must pass through a filterByRoleId filter.
     * Real-world case: a sub added ad-hoc with no role assigned was silently
     * excluded from a "Static Sub Pay" node that had filterByRoleId set to all
     * role IDs, because in_array(null, [1,2,3]) returns false.
     */
    public function test_sub_with_null_role_passes_filterByRoleId_filter(): void
    {
        $config = $this->makeConfigWithRoleFilter('substitutes_only', [1, 2, 3, 4, 5]);

        $event = $this->createEvent();

        EventMember::create([
            'event_id' => $event->id,
            'band_id' => $this->band->id,
            'user_id' => null,
            'roster_member_id' => null,
            'band_role_id' => null,
            'name' => 'Ad Hoc Sub',
            'email' => 'adhoc@example.com',
            'attendance_status' => 'confirmed',
        ]);

        $result = $config->calculatePayouts(1000, null, $this->booking);

        $payout = collect($result['member_payouts'])->firstWhere('name', 'Ad Hoc Sub');
        $this->assertNotNull($payout, 'Sub with null band_role_id should appear in payout results when filterByRoleId is set');
        $this->assertEquals(400, $payout['amount']);
    }

    /**
     * A registered user added as sub on two different events should be 'substitute' on both.
     * This is the exact scenario that triggered the original bug report.
     */
    public function test_registered_sub_is_substitute_on_second_event_for_same_band(): void
    {
        $subUser = User::factory()->create(['name' => 'Repeating Sub']);

        $event1 = $this->createEvent();
        $event2 = $this->createEvent();

        // First gig
        EventMember::create([
            'event_id' => $event1->id,
            'band_id' => $this->band->id,
            'user_id' => $subUser->id,
            'roster_member_id' => null,
            'attendance_status' => 'attended',
        ]);

        // Second gig (same sub, same band, different event)
        EventMember::create([
            'event_id' => $event2->id,
            'band_id' => $this->band->id,
            'user_id' => $subUser->id,
            'roster_member_id' => null,
            'attendance_status' => 'attended',
        ]);

        $result = $this->config->calculatePayouts(1000, null, $this->booking);

        // The sub attended 2 events but is still one person in the attendance data
        $subPayout = collect($result['member_payouts'])->firstWhere('user_id', $subUser->id);
        $this->assertNotNull($subPayout);
        $this->assertEquals('substitute', $subPayout['type'],
            'A registered user added as sub should be "substitute" even on their second gig'
        );
    }
}
