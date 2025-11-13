<?php

namespace Tests\Feature;

use App\Models\Bands;
use App\Models\Contacts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ContactAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test contact can view login page
     */
    public function test_contact_can_view_login_page()
    {
        $response = $this->get(route('portal.login'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Contact/Login'));
    }

    /**
     * Test contact can login with valid credentials
     */
    public function test_contact_can_login_with_valid_credentials()
    {
        $band = Bands::factory()->create();
        $contact = Contacts::factory()->create([
            'band_id' => $band->id,
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'can_login' => true,
        ]);

        $response = $this->post(route('portal.login'), [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('portal.dashboard'));
        $this->assertAuthenticatedAs($contact, 'contact');
    }

    /**
     * Test contact cannot login with invalid password
     */
    public function test_contact_cannot_login_with_invalid_password()
    {
        $band = Bands::factory()->create();
        $contact = Contacts::factory()->create([
            'band_id' => $band->id,
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'can_login' => true,
        ]);

        $response = $this->post(route('portal.login'), [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertGuest('contact');
    }

    /**
     * Test contact cannot login if can_login is false
     */
    public function test_contact_cannot_login_if_login_disabled()
    {
        $band = Bands::factory()->create();
        $contact = Contacts::factory()->create([
            'band_id' => $band->id,
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'can_login' => false,
        ]);

        $response = $this->post(route('portal.login'), [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertGuest('contact');
    }

    /**
     * Test contact login requires email
     */
    public function test_contact_login_requires_email()
    {
        $response = $this->post(route('portal.login'), [
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    /**
     * Test contact login requires password
     */
    public function test_contact_login_requires_password()
    {
        $response = $this->post(route('portal.login'), [
            'email' => 'test@example.com',
        ]);

        $response->assertSessionHasErrors(['password']);
    }

    /**
     * Test contact can logout
     */
    public function test_contact_can_logout()
    {
        $band = Bands::factory()->create();
        $contact = Contacts::factory()->create([
            'band_id' => $band->id,
            'can_login' => true,
        ]);

        $this->actingAs($contact, 'contact');

        $response = $this->post(route('portal.logout'));

        $response->assertRedirect(route('portal.login'));
        $this->assertGuest('contact');
    }

    /**
     * Test contact can view forgot password page
     */
    public function test_contact_can_view_forgot_password_page()
    {
        $response = $this->get(route('portal.password.request'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Contact/ForgotPassword'));
    }

    /**
     * Test contact can request password reset
     */
    public function test_contact_can_request_password_reset()
    {
        $band = Bands::factory()->create();
        $contact = Contacts::factory()->create([
            'band_id' => $band->id,
            'email' => 'test@example.com',
            'can_login' => true,
        ]);

        $response = $this->post(route('portal.password.email'), [
            'email' => 'test@example.com',
        ]);

        $response->assertRedirect(route('portal.login'));
        $response->assertSessionHas('status');
    }

    /**
     * Test password reset request doesn't reveal if email exists
     */
    public function test_password_reset_request_doesnt_reveal_nonexistent_email()
    {
        $response = $this->post(route('portal.password.email'), [
            'email' => 'nonexistent@example.com',
        ]);

        // Should still show success message to prevent email enumeration
        $response->assertRedirect(route('portal.login'));
        $response->assertSessionHas('status');
    }

    /**
     * Test password reset request requires valid email format
     */
    public function test_password_reset_request_requires_valid_email_format()
    {
        $response = $this->post(route('portal.password.email'), [
            'email' => 'not-an-email',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    /**
     * Test contact can view password reset form
     */
    public function test_contact_can_view_password_reset_form()
    {
        $token = 'test-token';
        $email = 'test@example.com';

        $response = $this->get(route('portal.password.reset', ['token' => $token]) . '?email=' . $email);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('Contact/ResetPassword', false) // Don't check if file exists
                ->has('token')
                ->has('email')
        );
    }

    /**
     * Test contact can reset password with valid token
     */
    public function test_contact_can_reset_password_with_valid_token()
    {
        $band = Bands::factory()->create();
        $contact = Contacts::factory()->create([
            'band_id' => $band->id,
            'email' => 'test@example.com',
            'can_login' => true,
            'password' => Hash::make('oldpassword'),
        ]);

        // Generate a valid token
        $token = Password::broker('contacts')->createToken($contact);

        $response = $this->post(route('portal.password.update'), [
            'token' => $token,
            'email' => 'test@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertRedirect(route('portal.login'));
        $response->assertSessionHas('status');

        // Verify password was changed
        $this->assertTrue(Hash::check('newpassword123', $contact->fresh()->password));
    }

    /**
     * Test password reset requires password confirmation
     */
    public function test_password_reset_requires_confirmation()
    {
        $response = $this->post(route('portal.password.update'), [
            'token' => 'test-token',
            'email' => 'test@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'differentpassword',
        ]);

        $response->assertSessionHasErrors(['password']);
    }

    /**
     * Test password reset requires minimum length
     */
    public function test_password_reset_requires_minimum_length()
    {
        $response = $this->post(route('portal.password.update'), [
            'token' => 'test-token',
            'email' => 'test@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertSessionHasErrors(['password']);
    }

    /**
     * Test password reset with invalid token fails
     */
    public function test_password_reset_with_invalid_token_fails()
    {
        $band = Bands::factory()->create();
        $contact = Contacts::factory()->create([
            'band_id' => $band->id,
            'email' => 'test@example.com',
            'can_login' => true,
        ]);

        $response = $this->post(route('portal.password.update'), [
            'token' => 'invalid-token',
            'email' => 'test@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    /**
     * Test remember me functionality
     */
    public function test_contact_can_login_with_remember_me()
    {
        $band = Bands::factory()->create();
        $contact = Contacts::factory()->create([
            'band_id' => $band->id,
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'can_login' => true,
        ]);

        $response = $this->post(route('portal.login'), [
            'email' => 'test@example.com',
            'password' => 'password123',
            'remember' => true,
        ]);

        $response->assertRedirect(route('portal.dashboard'));
        $this->assertAuthenticatedAs($contact, 'contact');
        
        // Check that remember token was set
        $this->assertNotNull($contact->fresh()->remember_token);
    }

    /**
     * Test authenticated contact cannot access login page
     */
    public function test_authenticated_contact_cannot_access_login_page()
    {
        $band = Bands::factory()->create();
        $contact = Contacts::factory()->create([
            'band_id' => $band->id,
            'can_login' => true,
        ]);

        $this->actingAs($contact, 'contact');

        $response = $this->get(route('portal.login'));

        // Should be redirected away from login page (middleware behavior)
        $response->assertRedirect();
    }
}
