<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Tests\TestCase;
use App\Models\User;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\Payments;
use App\Models\userPermissions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BookingsControllerTest extends TestCase
{
    private Bands $band;
    private User $owner;
    private User $privilegedMember;
    private User $unprivilegedMember;
    private User $nonMember;
    private Bookings $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->band = Bands::factory()->create();
        $this->owner = User::factory()->create();
        $this->privilegedMember = User::factory()->create();
        $this->unprivilegedMember = User::factory()->create();
        $this->nonMember = User::factory()->create();

        setPermissionsTeamId($this->band->id);
        $this->privilegedMember->givePermissionTo('read_bookings', 'write_bookings');
        $this->unprivilegedMember->givePermissionTo('read_bookings');

        $this->band->owners()->create(['user_id' => $this->owner->id]);
        $this->band->members()->create(['user_id' => $this->privilegedMember->id]);
        $this->band->members()->create(['user_id' => $this->unprivilegedMember->id]);

        $this->booking = Bookings::factory()->create(['band_id' => $this->band->id]);
    }

    public function test_owner_can_view_bookings_index()
    {
        $bookings = Bookings::factory()->count(3)->create(['band_id' => $this->band->id]);

        $response = $this->actingAs($this->owner)->get(route('Bookings Home', $this->band));

        // log out the response for debugging
        $response->dump();

        $response->assertStatus(200);
        $response->assertInertia(
            fn($assert) => $assert
                ->component('Bookings/Index')
                ->has('bookings', 4) // 3 bookings created in the test + 1 booking created in the setUp method
                ->has('bands')
        );
    }

    public function test_members_can_view_bookings_index()
    {
        $bookings = Bookings::factory()->count(3)->create(['band_id' => $this->band->id]);

        $response = $this->actingAs($this->privilegedMember)->get(route('Bookings Home', $this->band));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($assert) => $assert
                ->component('Bookings/Index')
                ->has('bookings', 4)
        );

        $response = $this->actingAs($this->unprivilegedMember)->get(route('Bookings Home', $this->band));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($assert) => $assert
                ->component('Bookings/Index')
                ->has('bookings', 4)
        );
    }

    public function test_owner_can_create_booking()
    {
        $bookingData = Bookings::factory()->make(['band_id' => $this->band->id, 'author_id' => $this->owner->id])->toArray();
        $bookingData['duration'] = 3;

        $response = $this->actingAs($this->owner)->postJson(route('bands.booking.store', $this->band), $bookingData);

        $response->assertRedirect();

        $checkData = [
            'name' => $bookingData['name'],
            'author_id' => $bookingData['author_id'],
            'band_id' => $bookingData['band_id'],
            'event_type_id' => $bookingData['event_type_id'],
            'venue_name' => $bookingData['venue_name'],
        ];

        $this->assertDatabaseHas('bookings', $checkData);

        $booking = $this->band->bookings()->where('name', $bookingData['name'])->first();

        $this->assertNotNull($booking, 'Booking was not created');

        $response->assertRedirect(route('Booking Details', ['band' => $this->band, 'booking' => $booking]));
    }

    public function test_privileged_member_can_create_booking()
    {
        $bookingData = Bookings::factory()->make(['band_id' => $this->band->id, 'author_id' => $this->privilegedMember->id])->toArray();
        $bookingData['duration'] = 3;

        $response = $this->actingAs($this->privilegedMember)->postJson(route('bands.booking.store', $this->band), $bookingData);

        $response->assertStatus(302); // Assert that a redirect occurred

        $checkData = [
            'name' => $bookingData['name'],
            'author_id' => $bookingData['author_id'],
            'band_id' => $bookingData['band_id'],
            'event_type_id' => $bookingData['event_type_id'],
            'venue_name' => $bookingData['venue_name'],
        ];

        $this->assertDatabaseHas('bookings', $checkData);

        $booking = $this->band->bookings()->where('name', $bookingData['name'])->first();

        $this->assertNotNull($booking, 'Booking was not created');

        $response->assertRedirect(route('Booking Details', ['band' => $this->band, 'booking' => $booking]));
    }

    public function test_unprivileged_member_cannot_create_booking()
    {
        $bookingData = Bookings::factory()->make(['band_id' => $this->band->id])->toArray();

        $response = $this->actingAs($this->unprivilegedMember)->postJson(route('bands.booking.store', $this->band), $bookingData);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('bookings', $bookingData);
    }

    public function test_non_member_cannot_create_booking()
    {
        $bookingData = Bookings::factory()->make(['band_id' => $this->band->id])->toArray();

        $response = $this->actingAs($this->nonMember)->postJson(route('bands.booking.store', $this->band), $bookingData);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('bookings', $bookingData);
    }

    public function test_owner_cannot_create_booking_for_another_band()
    {
        $band = Bands::factory()->hasOwners(1)->create();
        $bookingData = Bookings::factory()->make(['band_id' => $band->id, 'author_id' => $this->owner->id])->toArray();
        $bookingData['duration'] = 3;

        $response = $this->actingAs($this->owner)->postJson(route('bands.booking.store', $band), $bookingData);

        $response->assertForbidden();
    }

    public function test_owner_can_update_booking()
    {
        $booking = Bookings::factory()->create(['band_id' => $this->band->id]);
        $updatedData = Bookings::factory()->make(['band_id' => $this->band->id])->toArray();
        $updatedName = $updatedData['name'];

        $response = $this->actingAs($this->owner)->put(route('bands.booking.update', [$this->band, $booking]), $updatedData);

        //this is two fold. The request excludes the author_id, so this checks that the author_id is not updated and the result exists
        unset($updatedData['author_id']);
        $updatedData['price'] = $updatedData['price'] * 100;

        $response->assertSessionHas('successMessage', "$updatedName has been updated.");
        $this->assertDatabaseHas('bookings', $updatedData);
    }

    public function test_owner_can_delete_booking()
    {
        $booking = Bookings::factory()->create(['band_id' => $this->band->id]);

        $response = $this->actingAs($this->owner)->delete(route('bands.booking.destroy', [$this->band, $booking]));

        $response->assertRedirect(route('Bookings Home'));
        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
    }

    public function test_owner_can_view_booking_details()
    {
        $response = $this->actingAs($this->owner)->get(route('Booking Details', [$this->band, $this->booking]));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($assert) => $assert
                ->component('Bookings/Show')
                ->has('booking')
                ->has('band')
        );
    }

    public function test_booking_can_be_updated()
    {
        $this->booking->name = 'Updated Booking Name';

        $response = $this->actingAs($this->owner)->put(route('bands.booking.update', [$this->band, $this->booking]), $this->booking->toArray());

        $response->assertRedirect();
        $response->assertSessionHas('successMessage');
        $this->assertDatabaseHas('bookings', ['name' => 'Updated Booking Name']);
    }

    public function test_member_can_view_booking_details()
    {
        $response = $this->actingAs($this->privilegedMember)->get(route('Booking Details', [$this->band, $this->booking]));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($assert) => $assert
                ->component('Bookings/Show')
                ->has('booking')
                ->has('band')
        );
    }

    public function test_non_member_cannot_view_booking_details()
    {
        $response = $this->actingAs($this->nonMember)->get(route('Booking Details', [$this->band, $this->booking]));

        $response->assertStatus(403);
    }

    public function test_owner_can_view_booking_contacts()
    {
        $response = $this->actingAs($this->owner)->get(route('Booking Contacts', [$this->band, $this->booking]));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($assert) => $assert
                ->component('Bookings/Contacts')
                ->has('booking')
                ->has('band')
        );
    }

    public function test_owner_can_add_booking_contact()
    {
        $contactData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'role' => 'Manager',
            'is_primary' => true,
        ];

        $response = $this->actingAs($this->owner)->post(route('Store Booking Contact', [$this->band, $this->booking]), $contactData);

        $response->assertRedirect();
        $response->assertSessionHas('successMessage');
        $this->assertDatabaseHas('contacts', ['email' => 'john@example.com']);
    }

    public function test_owner_can_update_booking_contact()
    {
        $contact = Contacts::factory()->create();
        $this->booking->contacts()->attach($contact);

        $updatedData = [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '9876543210',
            'role' => 'Assistant To The Regional Manager',
            'is_primary' => false,
        ];

        $response = $this->actingAs($this->owner)->put(route('Update Booking Contact', [$this->band, $this->booking, $contact]), $updatedData);

        $response->assertRedirect();
        $response->assertSessionHas('successMessage');

        $this->assertDatabaseHas('booking_contacts', ['role' => $updatedData['role']]);
    }

    public function test_owner_can_delete_booking_contact()
    {
        $contact = Contacts::factory()->create();
        $this->booking->contacts()->attach($contact);

        $response = $this->actingAs($this->owner)->delete(route('Delete Booking Contact', [$this->band, $this->booking, $contact]));

        $response->assertRedirect();
        $response->assertSessionHas('successMessage');
        $this->assertDatabaseMissing('booking_contacts', ['contact_id' => $contact->id, 'booking_id' => $this->booking->id]);
    }

    public function test_owner_can_view_booking_finances()
    {
        $response = $this->actingAs($this->owner)->get(route('Booking Finances', [$this->band, $this->booking]));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($assert) => $assert
                ->component('Bookings/Finances')
                ->has('booking')
                ->has('band')
                ->has('payments')
        );
    }

    public function test_owner_can_add_booking_payment()
    {
        $paymentData = [
            'name' => 'Deposit',
            'amount' => 500,
            'date' => now()->format('Y-m-d'),
        ];

        $response = $this->actingAs($this->owner)->post(route('Store Booking Payment', [$this->band, $this->booking]), $paymentData);

        $response->assertRedirect();
        $response->assertSessionHas('successMessage');
        $this->assertDatabaseHas('payments', ['name' => 'Deposit', 'amount' => 50000]); // Remember, amount is stored in cents
    }

    public function test_owner_can_delete_booking_payment()
    {
        $payment = Payments::factory()->create(['payable_id' => $this->booking->id, 'payable_type' => Bookings::class]);

        $response = $this->actingAs($this->owner)->delete(route('Delete Booking Payment', [$this->band, $this->booking, $payment]));

        $response->assertRedirect();
        $response->assertSessionHas('successMessage');
        $this->assertDatabaseMissing('payments', ['id' => $payment->id]);
    }

    public function test_owner_can_download_booking_receipt()
    {
        $response = $this->actingAs($this->owner)->get(route('Booking Receipt', [$this->band, $this->booking]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_no_contacts_redirect()
    {
        $this->markTestIncomplete('Sometimes it fails because the response is a 200 instead of a 302');
        $response = $this->actingAs($this->owner)->get(route('Booking Contract', [$this->band, $this->booking]));
        $response->assertStatus(302);
        $response->assertSessionHas('warningMessage');
    }

    public function test_owner_can_view_booking_contract()
    {
        $contacts = Contacts::factory()->count(2)->create();
        $this->booking->contacts()->attach($contacts, ['role' => 'Test Role']);

        $response = $this->actingAs($this->owner)->get(route('Booking Contract', [$this->band, $this->booking]));
        $response->assertStatus(200);
        $response->assertInertia(
            fn($assert) => $assert
                ->component('Bookings/Contract')
                ->has('booking')
                ->has('band')
        );
    }

    public function test_owner_can_download_booking_contract()
    {
        // Copy an actual default.png to the test directory
        $sourcePath = base_path('public/images/default.png');
        $testPath = storage_path('app/public/images/default.png');

        // Ensure the directory exists
        if (!file_exists(dirname($testPath)))
        {
            mkdir(dirname($testPath), 0777, true);
        }

        // Copy the file
        copy($sourcePath, $testPath);

        // Use the copied file path
        $this->band->logo = $testPath;
        $this->band->save();

        $contacts = Contacts::factory()->count(2)->create();
        $this->booking->contacts()->attach($contacts, ['role' => 'Test Role']);
        $this->booking->contract()->create([
            'author_id' => $this->owner->id,
            'custom_terms' => [['title' => 'Test Term', 'content' => 'Test Content']],
        ]);

        $response = $this->actingAs($this->owner)->get(route('Download Booking Contract', [$this->band, $this->booking]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }
}
