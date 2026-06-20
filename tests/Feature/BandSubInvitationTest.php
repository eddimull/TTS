<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Bands;
use App\Models\User;
use App\Models\BandSubs;
use App\Models\BandOwners;
use App\Models\BandRole;
use App\Models\BandSubInvitation;
use App\Models\RosterMember;
use App\Models\Roster;
use App\Services\SubInvitationService;
use Illuminate\Support\Facades\Mail;
use App\Mail\BandSubInvitation as BandSubInvitationMail;

class BandSubInvitationTest extends TestCase
{
    use RefreshDatabase;

    protected $subInvitationService;
    protected $band;
    protected $owner;

    protected function setUp(): void
    {
        parent::setUp();

        // Create sub role and permissions
        $this->artisan('db:seed', ['--class' => 'SubRolesPermissionsSeeder']);

        $this->subInvitationService = new SubInvitationService();
        \setPermissionsTeamId(0);

        $this->band = Bands::factory()->create(['name' => 'Test Band']);
        $this->owner = User::factory()->create();

        BandOwners::create([
            'user_id' => $this->owner->id,
            'band_id' => $this->band->id,
        ]);
    }

    public function test_adding_custom_person_to_call_list_creates_invitation_and_sends_mail()
    {
        Mail::fake();

        $this->actingAs($this->owner);

        $response = $this->postJson(route('bands.substitute-call-lists.store', $this->band), [
            'instrument' => 'Trumpet',
            'custom_name' => 'Custom Sub',
            'custom_email' => 'customsub@example.com',
            'custom_phone' => '555-9999',
        ]);

        $response->assertStatus(201);

        // Pending band invitation row created
        $this->assertDatabaseHas('band_sub_invitations', [
            'band_id' => $this->band->id,
            'email' => 'customsub@example.com',
            'name' => 'Custom Sub',
            'pending' => true,
        ]);

        // Band-sub invitation email sent
        Mail::assertSent(BandSubInvitationMail::class, function ($mail) {
            return $mail->hasTo('customsub@example.com');
        });
    }

    public function test_send_invite_false_suppresses_email_and_invitation_row()
    {
        Mail::fake();

        $this->actingAs($this->owner);

        $response = $this->postJson(route('bands.substitute-call-lists.store', $this->band), [
            'instrument' => 'Trumpet',
            'custom_name' => 'Quiet Sub',
            'custom_email' => 'quiet@example.com',
            'custom_phone' => '555-0000',
            'send_invite' => false,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseMissing('band_sub_invitations', [
            'band_id' => $this->band->id,
            'email' => 'quiet@example.com',
        ]);

        Mail::assertNotSent(BandSubInvitationMail::class);
    }

    public function test_inviting_existing_user_creates_band_subs_and_assigns_role()
    {
        Mail::fake();

        $existingUser = User::factory()->create(['email' => 'existing@example.com']);

        $invitation = $this->subInvitationService->inviteSubToBand(
            bandId: $this->band->id,
            email: 'existing@example.com',
            name: 'Existing Sub'
        );

        // Linked to the user
        $this->assertDatabaseHas('band_sub_invitations', [
            'band_id' => $this->band->id,
            'user_id' => $existingUser->id,
            'email' => 'existing@example.com',
        ]);

        // Added to band_subs
        $this->assertDatabaseHas('band_subs', [
            'user_id' => $existingUser->id,
            'band_id' => $this->band->id,
        ]);

        // Sub role assigned
        $this->assertTrue($existingUser->fresh()->hasRole('sub'));

        // Mail sent
        Mail::assertSent(BandSubInvitationMail::class);
    }

    public function test_accepting_band_invitation_links_user_and_creates_band_subs()
    {
        $user = User::factory()->create();

        $invitation = BandSubInvitation::create([
            'band_id' => $this->band->id,
            'email' => $user->email,
            'pending' => true,
        ]);

        $this->assertTrue($invitation->isPending());

        $accepted = $this->subInvitationService->acceptBandInvitation(
            $invitation->invitation_key,
            $user
        );

        $this->assertFalse($accepted->pending);
        $this->assertNotNull($accepted->accepted_at);
        $this->assertEquals($user->id, $accepted->user_id);

        $this->assertDatabaseHas('band_subs', [
            'user_id' => $user->id,
            'band_id' => $this->band->id,
        ]);

        $this->assertTrue($user->fresh()->hasRole('sub'));
    }

    public function test_invitation_key_is_auto_generated_unique_and_length_36()
    {
        $invitation1 = BandSubInvitation::create([
            'band_id' => $this->band->id,
            'email' => 'sub1@example.com',
        ]);

        $band2 = Bands::factory()->create();
        $invitation2 = BandSubInvitation::create([
            'band_id' => $band2->id,
            'email' => 'sub2@example.com',
        ]);

        $this->assertNotNull($invitation1->invitation_key);
        $this->assertEquals(36, strlen($invitation1->invitation_key));
        $this->assertNotEquals($invitation1->invitation_key, $invitation2->invitation_key);
    }

    public function test_registering_with_band_invitation_key_accepts_invite()
    {
        Mail::fake();

        $invitation = BandSubInvitation::create([
            'band_id' => $this->band->id,
            'email' => 'register@example.com',
            'name' => 'Register Sub',
            'pending' => true,
        ]);

        $response = $this->post('/register', [
            'name' => 'Register Sub',
            'email' => 'register@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invitation' => $invitation->invitation_key,
        ]);

        $user = User::where('email', 'register@example.com')->first();
        $this->assertNotNull($user);

        // Invitation accepted
        $invitation->refresh();
        $this->assertFalse($invitation->pending);
        $this->assertNotNull($invitation->accepted_at);
        $this->assertEquals($user->id, $invitation->user_id);

        // band_subs + role
        $this->assertDatabaseHas('band_subs', [
            'user_id' => $user->id,
            'band_id' => $this->band->id,
        ]);
        $this->assertTrue($user->fresh()->hasRole('sub'));
    }

    public function test_adding_roster_member_already_subbing_does_not_reinvite()
    {
        Mail::fake();

        $this->actingAs($this->owner);

        // A registered user who is already a band sub
        $subUser = User::factory()->create(['email' => 'already@example.com']);
        BandSubs::create(['user_id' => $subUser->id, 'band_id' => $this->band->id]);

        $roster = Roster::factory()->create(['band_id' => $this->band->id]);
        $rosterMember = RosterMember::factory()->create([
            'roster_id' => $roster->id,
            'user_id' => $subUser->id,
        ]);

        $response = $this->postJson(route('bands.substitute-call-lists.store', $this->band), [
            'instrument' => 'Sax',
            'roster_member_id' => $rosterMember->id,
        ]);

        $response->assertStatus(201);

        // No new invitation, no mail
        $this->assertDatabaseMissing('band_sub_invitations', [
            'band_id' => $this->band->id,
            'user_id' => $subUser->id,
        ]);
        Mail::assertNotSent(BandSubInvitationMail::class);
    }
}
