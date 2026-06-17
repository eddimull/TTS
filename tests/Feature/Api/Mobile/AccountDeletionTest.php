<?php

namespace Tests\Feature\Api\Mobile;

use App\Mail\AccountDeletionConfirmation;
use App\Models\BandMembers;
use App\Models\BandOwners;
use App\Models\Bands;
use App\Models\DeviceToken;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Mockery;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_sends_confirmation_email_and_does_not_delete(): void
    {
        Mail::fake();

        $user  = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->deleteJson('/api/mobile/account')
            ->assertStatus(202)
            ->assertJsonStructure(['message']);

        // Account still exists — only confirming the email link deletes it.
        $this->assertDatabaseHas('users', ['id' => $user->id]);

        Mail::assertSent(AccountDeletionConfirmation::class, fn ($mail) => $mail->hasTo($user->email));
    }

    public function test_request_requires_authentication(): void
    {
        $this->deleteJson('/api/mobile/account')->assertUnauthorized();
    }

    public function test_valid_signed_link_deletes_account_and_personal_data(): void
    {
        $mockCalendar = Mockery::mock(GoogleCalendarService::class);
        $this->app->instance(GoogleCalendarService::class, $mockCalendar);

        $user = User::factory()->create();
        $user->createToken('device-a');
        DeviceToken::factory()->create(['user_id' => $user->id]);

        $url = URL::temporarySignedRoute(
            'mobile.account.confirm-deletion',
            now()->addMinutes(60),
            ['user' => $user->id],
        );

        // GET renders the confirmation page and must NOT delete (prefetch-safe).
        $this->get($url)->assertOk();
        $this->assertDatabaseHas('users', ['id' => $user->id]);

        // POST to the same signed URL performs the deletion.
        $this->post($url)->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('device_tokens', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
    }

    public function test_get_confirmation_link_does_not_delete_so_prefetch_is_safe(): void
    {
        $user = User::factory()->create();

        $url = URL::temporarySignedRoute(
            'mobile.account.confirm-deletion',
            now()->addMinutes(60),
            ['user' => $user->id],
        );

        // A GET (as issued by email scanners / link prefetchers) must never
        // delete — it only renders the confirmation page.
        $this->get($url)->assertOk();
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_deletion_detaches_from_band_but_preserves_other_members(): void
    {
        $mockCalendar = Mockery::mock(GoogleCalendarService::class);
        $this->app->instance(GoogleCalendarService::class, $mockCalendar);

        $leaving   = User::factory()->create();
        $remaining = User::factory()->create();

        $band = Bands::factory()->create();
        BandOwners::factory()->create(['user_id' => $leaving->id, 'band_id' => $band->id]);
        BandMembers::factory()->create(['user_id' => $remaining->id, 'band_id' => $band->id]);

        $url = URL::temporarySignedRoute(
            'mobile.account.confirm-deletion',
            now()->addMinutes(60),
            ['user' => $leaving->id],
        );

        $this->post($url)->assertOk();

        // The leaving user and their owner row are gone...
        $this->assertDatabaseMissing('users', ['id' => $leaving->id]);
        $this->assertDatabaseMissing('band_owners', ['user_id' => $leaving->id, 'band_id' => $band->id]);

        // ...but the band and its other member survive untouched.
        $this->assertDatabaseHas('bands', ['id' => $band->id]);
        $this->assertDatabaseHas('band_members', ['user_id' => $remaining->id, 'band_id' => $band->id]);
    }

    public function test_invalid_signature_is_rejected_and_account_kept(): void
    {
        $user = User::factory()->create();

        // Tampered URL — valid route, bad signature. Both verbs must 403.
        $this->post("/api/mobile/account/confirm-deletion/{$user->id}?signature=deadbeef&expires=9999999999")
            ->assertForbidden();
        $this->get("/api/mobile/account/confirm-deletion/{$user->id}?signature=deadbeef&expires=9999999999")
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_invalid_signature_on_unknown_user_still_403_not_404(): void
    {
        // Signature is validated before any DB lookup, so a forged link can't be
        // used to probe whether an account id exists (always 403, never 404).
        $this->post('/api/mobile/account/confirm-deletion/999999?signature=deadbeef&expires=9999999999')
            ->assertForbidden();
    }
}
