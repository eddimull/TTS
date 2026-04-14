<?php

namespace Tests\Feature;

use Mockery;
use Tests\TestCase;
use App\Models\User;
use App\Models\Bands;
use App\Models\Events;
use App\Models\Roster;
use App\Models\EventMember;
use App\Models\RosterMember;
use App\Models\BandCalendars;
use App\Models\CalendarAccess;
use App\Models\BandPaymentGroup;
use Illuminate\Support\Str;
use Google\Service\Calendar;
use App\Services\CalendarService;
use Illuminate\Http\UploadedFile;
use Google\Service\Calendar\AclRule;
use App\Services\GoogleCalendarService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Testing\FileFactory;
use Spatie\GoogleCalendar\GoogleCalendar;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BandsControllerTest extends TestCase
{
    private $band;
    private $owner;
    private $member;
    private $nonMember;

    protected function setUp(): void
    {
        parent::setUp();
        $this->band = Bands::factory()->create();
        $this->owner = User::factory()->create();
        $this->member = User::factory()->create();
        $this->nonMember = User::factory()->create();

        $this->band->owners()->create(['user_id' => $this->owner->id]);
        $this->band->members()->create(['user_id' => $this->member->id]);
    }
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_can_upload_logo(): void
    {
        Storage::fake('s3');

        $logo = UploadedFile::fake()->image('logo.png');
        $response = $this->actingAs($this->owner)->post(route('bands.uploadLogo', $this->band), [
            'logo' => $logo,
        ]);

        $imageName = Str::slug($this->band->name) . '-logo-' . time() . '.' . $logo->extension();
        $response->assertSessionHas('successMessage');
        $response->assertStatus(302);
        Storage::disk('s3')->assertExists($this->band->site_name . '/' . $imageName);
        $this->assertDatabaseHas('bands', [
            'id' => $this->band->id,
            'logo' => '/images/' . $this->band->site_name . '/' . $imageName,
        ]);
    }

    public function test_random_user_cannot_upload_logo(): void
    {
        Storage::fake('s3');
        $logo = UploadedFile::fake()->image('logo.png');
        $response = $this->actingAs($this->nonMember)->post(route('bands.uploadLogo', $this->band), [
            'logo' => $logo,
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_be_granted_access_to_calendar(): void
    {
        $mockService = $this->mock(GoogleCalendarService::class);
        $mockService->shouldReceive('addAccess')->once();
        $mockService->shouldReceive('getCalendar')->once();
        $calendar = BandCalendars::factory()->create(['band_id' => $this->band->id]);


        $response = $this->actingAs($this->owner)->post(route('bands.grantCalendarAccess', [$calendar]), [
            'user_id' => $this->member->id,
            'role' => 'owner',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('successMessage');

        $this->assertDatabaseHas('calendar_access', [
            'user_id' => $this->member->id,
            'band_calendar_id' => $calendar->id,
            'role' => 'owner',
        ]);

        Mockery::close();
    }

    public function test_user_access_can_be_revoked(): void
    {
        $mockService = $this->mock(GoogleCalendarService::class);
        $mockService->shouldReceive('revokeAccess')->once();
        $mockService->shouldReceive('findAccess')->once();
        $mockService->shouldReceive('getCalendar')->once();
        $calendar = BandCalendars::factory()->create(['band_id' => $this->band->id]);
        CalendarAccess::create([
            'band_calendar_id' => $calendar->id,
            'user_id' => $this->member->id,
            'role' => 'writer',
        ]);

        $response = $this->actingAs($this->owner)->delete(route('bands.revokeCalendarAccess', [$calendar, $this->member]));

        $response->assertStatus(302);
        $response->assertSessionHas('successMessage');

        $this->assertDatabaseMissing('calendar_access', [
            'user_id' => $this->member->id,
            'band_calendar_id' => $calendar->id,
        ]);

        Mockery::close();
    }

    public function test_delete_member_removes_calendar_access_and_permissions(): void
    {
        $mockService = $this->mock(GoogleCalendarService::class);
        $mockService->shouldReceive('getCalendar')->once()->andReturn(Mockery::mock(\Google\Service\Calendar\Calendar::class));
        $mockService->shouldReceive('findAccess')->once()->andReturn(Mockery::mock(AclRule::class));
        $mockService->shouldReceive('revokeAccess')->once();

        $calendar = BandCalendars::factory()->create(['band_id' => $this->band->id]);
        CalendarAccess::create([
            'band_calendar_id' => $calendar->id,
            'user_id' => $this->member->id,
            'role' => 'reader',
        ]);

        setPermissionsTeamId($this->band->id);
        $this->member->assignRole('band-member');
        $this->member->givePermissionTo(['read:events', 'read:charts']);
        setPermissionsTeamId(0);

        $response = $this->actingAs($this->owner)
            ->delete(route('bands.deleteMember', [$this->band, $this->member]));

        $response->assertStatus(302);
        $response->assertSessionHas('successMessage');

        $this->assertDatabaseMissing('band_members', [
            'user_id' => $this->member->id,
            'band_id' => $this->band->id,
        ]);

        $this->assertDatabaseMissing('calendar_access', [
            'user_id' => $this->member->id,
            'band_calendar_id' => $calendar->id,
        ]);

        setPermissionsTeamId($this->band->id);
        $this->assertFalse($this->member->fresh()->hasRole('band-member'));
        $this->assertFalse($this->member->fresh()->hasPermissionTo('read:events'));
        setPermissionsTeamId(0);
    }

    public function test_delete_member_removes_from_payment_groups(): void
    {
        $this->mock(GoogleCalendarService::class)->shouldReceive('getCalendar', 'findAccess', 'revokeAccess')->andReturnNull();

        $group = BandPaymentGroup::factory()->create(['band_id' => $this->band->id]);
        $group->users()->attach($this->member->id, ['payout_type' => 'equal_split', 'payout_value' => null, 'notes' => null]);

        $this->assertDatabaseHas('band_payment_group_members', ['user_id' => $this->member->id]);

        $this->actingAs($this->owner)
            ->delete(route('bands.deleteMember', [$this->band, $this->member]));

        $this->assertDatabaseMissing('band_payment_group_members', ['user_id' => $this->member->id]);
    }

    public function test_delete_member_removes_from_future_events_but_not_past_events(): void
    {
        $this->mock(GoogleCalendarService::class)->shouldReceive('getCalendar', 'findAccess', 'revokeAccess')->andReturnNull();

        $futureEvent = Events::factory()->forBand($this->band)->create(['date' => now()->addMonth()]);
        $pastEvent   = Events::factory()->forBand($this->band)->create(['date' => now()->subMonth()]);

        EventMember::factory()->create([
            'event_id'  => $futureEvent->id,
            'band_id'   => $this->band->id,
            'user_id'   => $this->member->id,
        ]);
        EventMember::factory()->create([
            'event_id'  => $pastEvent->id,
            'band_id'   => $this->band->id,
            'user_id'   => $this->member->id,
        ]);

        $this->actingAs($this->owner)
            ->delete(route('bands.deleteMember', [$this->band, $this->member]));

        $this->assertDatabaseMissing('event_members', [
            'event_id' => $futureEvent->id,
            'user_id'  => $this->member->id,
            'deleted_at' => null,
        ]);

        $this->assertDatabaseHas('event_members', [
            'event_id' => $pastEvent->id,
            'user_id'  => $this->member->id,
        ]);
    }

    public function test_delete_member_detaches_roster_memberships(): void
    {
        $this->mock(GoogleCalendarService::class)->shouldReceive('getCalendar', 'findAccess', 'revokeAccess')->andReturnNull();

        $roster = Roster::factory()->create(['band_id' => $this->band->id]);
        $rosterMember = RosterMember::factory()->user($this->member)->create(['roster_id' => $roster->id]);

        $this->actingAs($this->owner)
            ->delete(route('bands.deleteMember', [$this->band, $this->member]));

        $this->assertDatabaseHas('roster_members', [
            'id'        => $rosterMember->id,
            'user_id'   => null,
            'is_active' => false,
        ]);
    }

    public function test_delete_owner_performs_full_cleanup(): void
    {
        $ownerToRemove = User::factory()->create();
        $this->band->owners()->create(['user_id' => $ownerToRemove->id]);

        setPermissionsTeamId($this->band->id);
        $ownerToRemove->assignRole('band-owner');
        setPermissionsTeamId(0);

        $calendar = BandCalendars::factory()->create(['band_id' => $this->band->id]);
        CalendarAccess::create([
            'band_calendar_id' => $calendar->id,
            'user_id' => $ownerToRemove->id,
            'role' => 'owner',
        ]);

        $mockService = $this->mock(GoogleCalendarService::class);
        $mockService->shouldReceive('getCalendar')->once()->andReturn(Mockery::mock(\Google\Service\Calendar\Calendar::class));
        $mockService->shouldReceive('findAccess')->once()->andReturn(Mockery::mock(AclRule::class));
        $mockService->shouldReceive('revokeAccess')->once();

        $response = $this->actingAs($this->owner)
            ->delete(route('bands.deleteOwner', [$this->band, $ownerToRemove->id]));

        $response->assertStatus(302);
        $response->assertSessionHas('successMessage');

        $this->assertDatabaseMissing('band_owners', [
            'user_id' => $ownerToRemove->id,
            'band_id' => $this->band->id,
        ]);

        $this->assertDatabaseMissing('calendar_access', [
            'user_id' => $ownerToRemove->id,
            'band_calendar_id' => $calendar->id,
        ]);

        setPermissionsTeamId($this->band->id);
        $this->assertFalse($ownerToRemove->fresh()->hasRole('band-owner'));
        setPermissionsTeamId(0);
    }

    public function test_non_owner_cannot_delete_member(): void
    {
        $response = $this->actingAs($this->member)
            ->delete(route('bands.deleteMember', [$this->band, $this->member]));

        $response->assertStatus(403);

        $this->assertDatabaseHas('band_members', [
            'user_id' => $this->member->id,
            'band_id' => $this->band->id,
        ]);
    }
}
