<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\User;
use App\Services\ContactPortalService;
use App\Notifications\ContactPortalAccessGranted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Hash;

class ContactPortalServiceTest extends TestCase
{
    use RefreshDatabase;

    private ContactPortalService $service;
    private User $user;
    private Bands $band;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ContactPortalService();

        // Create test user and band
        $this->user = User::factory()->create();
        $this->band = Bands::factory()->create();
        $this->band->owners()->create(['user_id' => $this->user->id]);
    }

    public function test_grants_portal_access_after_contract_completion_for_default_contract(): void
    {
        Notification::fake();

        // Create booking with default contract option
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'contract_option' => 'default',
        ]);

        // Create contacts for the booking
        $contact1 = Contacts::factory()->create([
            'band_id' => $this->band->id,
            'can_login' => false,
            'email' => 'client1@example.com',
        ]);

        $contact2 = Contacts::factory()->create([
            'band_id' => $this->band->id,
            'can_login' => false,
            'email' => 'client2@example.com',
        ]);

        $booking->contacts()->attach($contact1->id, ['role' => 'primary']);
        $booking->contacts()->attach($contact2->id, ['role' => 'secondary']);

        // Execute the service method
        $this->service->grantPortalAccessAfterContractCompletion($booking);

        // Assert contacts now have portal access
        $this->assertTrue($contact1->fresh()->can_login);
        $this->assertTrue($contact2->fresh()->can_login);
        $this->assertNotNull($contact1->fresh()->password);
        $this->assertNotNull($contact2->fresh()->password);

        // Assert password_change_required flag is set
        $this->assertTrue($contact1->fresh()->password_change_required);
        $this->assertTrue($contact2->fresh()->password_change_required);

        // Assert notifications were sent
        Notification::assertSentTo($contact1, ContactPortalAccessGranted::class);
        Notification::assertSentTo($contact2, ContactPortalAccessGranted::class);
    }

    public function test_grants_portal_access_for_external_contract(): void
    {
        Notification::fake();

        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'contract_option' => 'external',
        ]);

        $contact = Contacts::factory()->create([
            'band_id' => $this->band->id,
            'can_login' => false,
        ]);

        $booking->contacts()->attach($contact->id, ['role' => 'primary']);

        $this->service->grantPortalAccessAfterContractCompletion($booking);

        $this->assertTrue($contact->fresh()->can_login);
        $this->assertTrue($contact->fresh()->password_change_required);
        Notification::assertSentTo($contact, ContactPortalAccessGranted::class);
    }

    public function test_does_not_grant_portal_access_for_none_contract(): void
    {
        Notification::fake();

        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'contract_option' => 'none',
        ]);

        $contact = Contacts::factory()->create([
            'band_id' => $this->band->id,
            'can_login' => false,
        ]);

        $booking->contacts()->attach($contact->id, ['role' => 'primary']);

        $this->service->grantPortalAccessAfterContractCompletion($booking);

        // Assert no access was granted
        $this->assertFalse($contact->fresh()->can_login);
        Notification::assertNothingSent();
    }

    public function test_skips_contacts_that_already_have_portal_access(): void
    {
        Notification::fake();

        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'contract_option' => 'default',
        ]);

        $existingContact = Contacts::factory()->create([
            'band_id' => $this->band->id,
            'can_login' => true,
            'password' => Hash::make('existing-password'),
        ]);

        $newContact = Contacts::factory()->create([
            'band_id' => $this->band->id,
            'can_login' => false,
        ]);

        $booking->contacts()->attach($existingContact->id, ['role' => 'primary']);
        $booking->contacts()->attach($newContact->id, ['role' => 'secondary']);

        $originalPassword = $existingContact->password;

        $this->service->grantPortalAccessAfterContractCompletion($booking);

        // Assert existing contact's password wasn't changed
        $this->assertEquals($originalPassword, $existingContact->fresh()->password);

        // Assert new contact got access
        $this->assertTrue($newContact->fresh()->can_login);
        $this->assertTrue($newContact->fresh()->password_change_required);

        // Assert notification only sent to new contact
        Notification::assertSentTo($newContact, ContactPortalAccessGranted::class);
        Notification::assertNotSentTo($existingContact, ContactPortalAccessGranted::class);
    }

    public function test_grant_portal_access_generates_temporary_password(): void
    {
        Notification::fake();

        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'contract_option' => 'default',
        ]);

        $contact = Contacts::factory()->create([
            'band_id' => $this->band->id,
            'can_login' => false,
            'password' => null,
        ]);

        $booking->contacts()->attach($contact->id, ['role' => 'primary']);

        $result = $this->service->grantPortalAccess($contact->fresh(), $booking);

        $this->assertTrue($result);
        $this->assertNotNull($contact->fresh()->password);
        $this->assertTrue($contact->fresh()->can_login);
        $this->assertTrue($contact->fresh()->password_change_required);
    }

    public function test_notification_contains_correct_booking_and_band_info(): void
    {
        Notification::fake();

        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'contract_option' => 'default',
            'name' => 'Summer Festival 2025',
        ]);

        $contact = Contacts::factory()->create([
            'band_id' => $this->band->id,
            'can_login' => false,
            'name' => 'John Doe',
            'email' => 'johndoe@example.com',
        ]);

        $booking->contacts()->attach($contact->id, ['role' => 'primary']);

        $this->service->grantPortalAccess($contact->fresh(), $booking);

        Notification::assertSentTo($contact, ContactPortalAccessGranted::class, function ($notification, $channels) use ($contact, $booking) {
            // Verify notification was sent via mail channel
            $this->assertContains('mail', $channels);
            return true;
        });
    }

    public function test_handles_booking_with_no_contacts_gracefully(): void
    {
        Notification::fake();

        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'contract_option' => 'default',
        ]);

        // Execute without any contacts attached
        $this->service->grantPortalAccessAfterContractCompletion($booking);

        // Should not throw error
        Notification::assertNothingSent();
    }
}
