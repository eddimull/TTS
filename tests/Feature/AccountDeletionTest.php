<?php

namespace Tests\Feature;

use App\Mail\AccountDeletionConfirmation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Web (session) account-deletion request flow. The destructive confirmation
 * step is shared with the mobile flow and is covered by
 * Tests\Feature\Api\Mobile\AccountDeletionTest — here we only assert that the
 * authenticated web request emails the signed link and never deletes directly.
 */
class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_sends_confirmation_email_and_does_not_delete(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/account/delete')
            ->assertRedirect();

        // Account still exists — only confirming the emailed link deletes it.
        $this->assertDatabaseHas('users', ['id' => $user->id]);

        Mail::assertSent(AccountDeletionConfirmation::class, fn ($mail) => $mail->hasTo($user->email));
    }

    public function test_request_requires_authentication(): void
    {
        Mail::fake();

        $this->post('/account/delete')->assertRedirect('/login');

        Mail::assertNothingSent();
    }
}
