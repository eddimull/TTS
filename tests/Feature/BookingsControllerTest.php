<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Tests\TestCase;
use App\Models\User;
use App\Models\Bands;
use App\Models\Events;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\Payments;
use App\Models\userPermissions;
use Illuminate\Http\UploadedFile;
use Spatie\Browsershot\Browsershot;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BookingsControllerTest extends TestCase
{
    use RefreshDatabase;

    private $band;
    private $owner;
    private $member;
    private $nonMember;
    private $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->band = Bands::factory()->create();
        $this->owner = User::factory()->create();
        $this->member = User::factory()->create();
        $this->nonMember = User::factory()->create();

        $this->band->owners()->create(['user_id' => $this->owner->id]);
        $this->band->members()->create(['user_id' => $this->member->id]);

        $this->booking = Bookings::factory()->create(['band_id' => $this->band->id]);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    private function mockBrowsershotForTest()
    {
        // Use 'overload:' to mock static method calls (only affects current test)
        $mock = \Mockery::mock('overload:' . Browsershot::class);
        
        $mock->shouldReceive('html')
            ->with(\Mockery::type('string'))
            ->andReturnSelf();
        
        $mock->shouldReceive('setNodeBinary')
            ->with(\Mockery::any())
            ->andReturnSelf();
            
        $mock->shouldReceive('setNpmBinary')
            ->with(\Mockery::any())
            ->andReturnSelf();
            
        $mock->shouldReceive('setOption')
            ->with(\Mockery::any(), \Mockery::any())
            ->andReturnSelf();
            
        $mock->shouldReceive('format')
            ->with(\Mockery::any())
            ->andReturnSelf();
            
        $mock->shouldReceive('showBackground')
            ->andReturnSelf();
            
        $mock->shouldReceive('taggedPdf')
            ->andReturnSelf();
            
        $mock->shouldReceive('savePdf')
            ->with(\Mockery::type('string'))
            ->andReturnUsing(function ($path) {
                file_put_contents($path, '%PDF-1.4 fake pdf content');
                return true;
            });
    }

    public function test_owner_can_view_bookings_index()
    {
        $bookings = Bookings::factory()->count(3)->create(['band_id' => $this->band->id]);

        $response = $this->actingAs($this->owner)->get(route('Bookings Home', $this->band));


        $response->assertStatus(200);
        $response->assertInertia(
            fn($assert) => $assert
                ->component('Bookings/Index')
                ->has('bookings', 4) // 3 bookings created in the test + 1 booking created in the setUp method
                ->has('bands')
        );
    }

    public function test_member_can_view_bookings_index()
    {
        $bookings = Bookings::factory()->count(3)->create(['band_id' => $this->band->id]);

        $response = $this->actingAs($this->member)->get(route('Bookings Home', $this->band));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($assert) => $assert
                ->component('Bookings/Index')
                ->has('bookings', 4)
        );
    }

    public function test_member_can_create_booking()
    {
        $duration = 2;
        \Illuminate\Support\Facades\DB::beginTransaction();
        userPermissions::create([
            'user_id' => $this->member->id,
            'band_id' => $this->band->id,
            'read_bookings' => true,
            'write_bookings' => true,
        ]);


        $bookingData = Bookings::factory()->duration($duration)->make(['band_id' => $this->band->id])->toArray();
        $bookingData['duration'] = $duration;
        $bookingData['start_time'] = Carbon::parse($bookingData['start_time'])->format('H:i');
        unset($bookingData['end_time']);

        $response = $this->actingAs($this->member)->post(route('bands.booking.store', $this->band), $bookingData);

        $response->assertStatus(302); // Assert that a redirect occurred


        unset($bookingData['start_time']);
        unset($bookingData['end_time']);
        unset($bookingData['status']);
        unset($bookingData['duration']);
        unset($bookingData['author_id']); // sometimes an owner or member can create a booking, which results in a different author_id
        unset($bookingData['amount_paid']); 
        unset($bookingData['is_paid']);
        unset($bookingData['amount_due']);
        
        $bookingData['author_id'] = $this->member->id;
        $bookingData['price'] = $bookingData['price'] * 100;

        $this->assertDatabaseHas('bookings', $bookingData);

        $booking = $this->band->bookings()->where('name', $bookingData['name'])->first();

        $this->assertNotNull($booking, 'Booking was not created');

        $this->assertNotNull($booking->contract->custom_terms);

        $response->assertRedirect(route('Booking Details', ['band' => $this->band, 'booking' => $booking]));
        \Illuminate\Support\Facades\DB::rollBack();
    }

    public function test_non_member_cannot_create_booking()
    {
        $bookingData = Bookings::factory()->make(['band_id' => $this->band->id])->toArray();
        unset($bookingData['author_id']);
        unset($bookingData['amount_paid']); 
        unset($bookingData['is_paid']);
        unset($bookingData['amount_due']);

        $response = $this->actingAs($this->nonMember)->post(route('bands.booking.store', $this->band), $bookingData);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('bookings', $bookingData);
    }

    public function test_owner_can_update_booking()
    {
        $booking = Bookings::factory()->create(['band_id' => $this->band->id]);
        $updatedData = Bookings::factory()->make(['band_id' => $this->band->id])->toArray();
        $updatedName = $updatedData['name'];

        $response = $this->actingAs($this->owner)->put(route('bands.booking.update', [$this->band, $booking]), $updatedData);

        //this is two fold. The request excludes the author_id, so this checks that the author_id is not updated and the result exists
        unset($updatedData['author_id']);
        unset($updatedData['amount_paid']); 
        unset($updatedData['is_paid']);
        unset($updatedData['amount_due']);
        $updatedData['price'] = $updatedData['price'] * 100;

        $response->assertSessionHas('successMessage', "$updatedName has been updated.");
        $this->assertDatabaseHas('bookings', $updatedData);
    }

    public function test_owner_can_delete_booking()
    {
        $response = $this->actingAs($this->owner)->delete(route('bands.booking.destroy', [$this->band, $this->booking]));
        
        $response->assertRedirect(route('Bookings Home'));
        $this->assertDatabaseMissing('bookings', ['id' => $this->booking->id]);
    }

    public function test_owner_can_delete_booking_with_contacts()
    {
        $contact = Contacts::factory()->create();
        $this->booking->contacts()->attach($contact);

        $response = $this->actingAs($this->owner)->delete(route('bands.booking.destroy', [$this->band, $this->booking]));

        $response->assertRedirect(route('Bookings Home'));
        $this->assertDatabaseMissing('bookings', ['id' => $this->booking->id]);
        $this->assertDatabaseMissing('booking_contacts', ['booking_id' => $this->booking->id, 'contact_id' => $contact->id]);
        $this->assertDatabaseHas('contacts', ['id' => $contact->id]); // Ensure the contact itself is not deleted
    }

    public function test_owner_can_delete_booking_with_events()
    {
        $event = Events::factory()->create([
            'eventable_type' => 'App\Models\Bookings',
            'eventable_id' => $this->booking->id
        ]);
        

        $response = $this->actingAs($this->owner)->delete(route('bands.booking.destroy', [$this->band, $this->booking]));

        $response->assertRedirect(route('Bookings Home'));
        $this->assertDatabaseMissing('bookings', ['id' => $this->booking->id]);
        $this->assertDatabaseMissing('events', ['eventable_type'=> 'App\Models\Bookings', 'eventable_id' => $this->booking->id]);
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
        $response = $this->actingAs($this->member)->get(route('Booking Details', [$this->band, $this->booking]));

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

        // Get the BookingContacts pivot record that was created
        $bookingContact = $this->booking->contacts()->where('contact_id', $contact->id)->first()->pivot;

        $updatedData = [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '9876543210',
            'role' => 'Assistant To The Regional Manager',
            'is_primary' => false,
        ];

        $response = $this->actingAs($this->owner)->put(route('Update Booking Contact', [$this->band, $this->booking, $bookingContact]), $updatedData);

        $response->assertRedirect();
        $response->assertSessionHas('successMessage');

        $this->assertDatabaseHas('booking_contacts', ['role' => $updatedData['role']]);
    }

    public function test_owner_can_delete_booking_contact()
    {
        $contact = Contacts::factory()->create();
        $this->booking->contacts()->attach($contact);

        // Get the BookingContacts pivot record that was created
        $bookingContact = $this->booking->contacts()->where('contact_id', $contact->id)->first()->pivot;

        $response = $this->actingAs($this->owner)->delete(route('Delete Booking Contact', [$this->band, $this->booking, $bookingContact]));

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
        $this->mockBrowsershotForTest();
        
        $response = $this->actingAs($this->owner)->get(route('Booking Receipt', [$this->band, $this->booking]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_owner_can_download_booking_contract()
    {
        $this->mockBrowsershotForTest();
        
        // Mock Storage for S3 operations
        Storage::fake('s3');
        
        // Create a fake logo file
        Storage::disk('s3')->put('default.png', 'fake image content');
        
        $this->band->logo = '/images/default.png';
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

    public function test_contract_download_succeeds_without_custom_terms()
    {
        $this->mockBrowsershotForTest();

        // Mock Storage for S3 operations
        Storage::fake('s3');

        // Create a fake logo file
        Storage::disk('s3')->put('default.png', 'fake image content');

        $this->band->logo = '/images/default.png';
        $this->band->save();

        $contacts = Contacts::factory()->count(2)->create();
        $this->booking->contacts()->attach($contacts, ['role' => 'Test Role']);
        $this->booking->contract()->create([
            'author_id' => $this->owner->id,
            'custom_terms' => null,
        ]);

        $response = $this->actingAs($this->owner)->get(route('Download Booking Contract', [$this->band, $this->booking]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }
}