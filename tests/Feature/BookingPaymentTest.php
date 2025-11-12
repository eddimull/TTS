<?php

namespace Tests\Feature;

use Tests\TestCase;

use App\Models\User;
use App\Models\Bands;

use App\Models\Bookings;
use App\Models\Contacts;

use App\Enums\PaymentType;
use App\Notifications\PaymentReceived;
use App\Notifications\BandPaymentReceived;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BookingPaymentTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $band;
    protected $booking;
    protected $contact;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Fake notifications
        Notification::fake();

        // Create test data
        $this->user = User::factory()->create();
        $this->contact = Contacts::factory()->create();
        $this->band = Bands::factory()->create();
        $this->booking = Bookings::factory()->create([
            'band_id' => $this->band->id
        ]);
        $this->band->owners()->create(['user_id' => $this->user->id]);
        $this->booking->contacts()->attach($this->contact);
    }

    public function test_it_can_store_a_payment_and_sends_notification()
    {

        // Arrange
        $this->actingAs($this->user);

        $paymentType = PaymentType::Cash;

        $paymentData = [
            'date' => now()->format('Y-m-d'),
            'amount' => 100,
            'name' => 'Test payment',
            'payment_type' => $paymentType->value,
        ];

        // Act
        $response = $this->post(route('Store Booking Payment', [
            'band' => $this->band,
            'booking' => $this->booking
        ]), $paymentData);

        // Assert
        $response->assertRedirect();
        $response->assertSessionHas('successMessage', 'Payment has been added.');

        // Assert payment was stored
        $this->assertDatabaseHas('payments', [
            'amount' => 10000,
            'name' => 'Test payment',
            'band_id' => $this->band->id,
            'user_id' => $this->user->id,
            'payment_type' => $paymentType->value,
            'payer_type' => 'App\\Models\\User',
            'payer_id' => $this->user->id,
        ]);



        // Assert notification was sent to contact
        Notification::assertSentTo(
            [$this->contact],
            PaymentReceived::class
        );

        // Assert notification was sent to band owner
        Notification::assertSentTo(
            [$this->user],
            BandPaymentReceived::class
        );
    }

    public function test_it_validates_required_fields()
    {
        // Arrange
        $this->actingAs($this->user);

        // Act
        $response = $this->post(route('Store Booking Payment', [
            'band' => $this->band,
            'booking' => $this->booking
        ]), []);

        // Assert
        $response->assertSessionHasErrors(['name', 'amount', 'date', 'payment_type']);

        // Assert no notification was sent
        Notification::assertNothingSent();
    }

    public function test_it_validates_payment_type_is_valid()
    {
        // Arrange
        $this->actingAs($this->user);

        $paymentData = [
            'amount' => 100,
            'date' => now()->format('Y-m-d'),
            'name' => 'Test payment',
            'payment_type' => 'invalid_type',
        ];

        // Act
        $response = $this->post(route('Store Booking Payment', [
            'band' => $this->band,
            'booking' => $this->booking
        ]), $paymentData);

        // Assert
        $response->assertSessionHasErrors(['payment_type']);

        // Assert no notification was sent
        Notification::assertNothingSent();
    }


    public function test_it_validates_amount_is_numeric()
    {
        // Arrange
        $this->actingAs($this->user);
        $paymentType = PaymentType::Cash;

        $paymentData = [
            'amount' => 'not-a-number',
            'date' => now()->format('Y-m-d'),
            'name' => 'Invalidated amount',
            'payment_type' => $paymentType->value,
        ];

        // Act
        $response = $this->post(route('Store Booking Payment', [
            'band' => $this->band,
            'booking' => $this->booking
        ]), $paymentData);

        // Assert
        $response->assertSessionHasErrors(['amount']);

        // Assert no notification was sent
        Notification::assertNothingSent();
    }


    public function test_unauthorized_users_cannot_create_payments()
    {
        $paymentType = PaymentType::Check;
        $response = $this->post(route('Store Booking Payment', [
            'band' => $this->band,
            'booking' => $this->booking
        ]), [
            'amount' => 100.00,
            'date' => now()->format('Y-m-d'),
            'name' => 'Test payment',
            'payment_type' => $paymentType->value,
        ]);

        
        $response->assertRedirect(route('login'));

        
        Notification::assertNothingSent();
    }


    public function test_it_queues_notification_for_sending()
    {
        $paymentType = PaymentType::Venmo;
        $this->actingAs($this->user);

        $paymentData = [
            'amount' => 100.00,
            'date' => now()->format('Y-m-d'),
            'name' => 'Test queue notification',
            'payment_type' => $paymentType->value,
        ];

        // Act
        $response = $this->post(route('Store Booking Payment', [
            'band' => $this->band,
            'booking' => $this->booking
        ]), $paymentData);

        // Assert notification was queued
        Notification::assertSentTo(
            $this->contact,
            function (PaymentReceived $notification)
            {
                return true;
            }
        );
    }

    public function test_it_notifies_band_members_and_owners()
    {
        // Arrange
        $this->actingAs($this->user);
        
        // Create additional band member
        $bandMember = User::factory()->create();
        $this->band->members()->create(['user_id' => $bandMember->id]);
        $paymentType = PaymentType::Zelle;
        $paymentData = [
            'amount' => 150.00,
            'date' => now()->format('Y-m-d'),
            'name' => 'Test band member notification',
            'payment_type' => $paymentType->value,
        ];

        // Act
        $response = $this->post(route('Store Booking Payment', [
            'band' => $this->band,
            'booking' => $this->booking
        ]), $paymentData);

        // Assert
        $response->assertRedirect();
        $response->assertSessionHas('successMessage');

        // Assert notification was sent to contact
        Notification::assertSentTo(
            [$this->contact],
            PaymentReceived::class
        );

        // Assert notification was sent to band owner
        Notification::assertSentTo(
            [$this->user],
            BandPaymentReceived::class
        );

        // Assert notification was sent to band member
        Notification::assertSentTo(
            [$bandMember],
            BandPaymentReceived::class
        );
    }

    public function test_it_accepts_all_payment_types()
    {
        // Arrange
        $this->actingAs($this->user);

        // Loop through all payment types in the enum
        foreach (PaymentType::cases() as $paymentType) {
            // Reset notification fake for each iteration
            Notification::fake();

            $paymentData = [
                'amount' => 100.00,
                'date' => now()->format('Y-m-d'),
                'name' => 'Test payment - ' . $paymentType->label(),
                'payment_type' => $paymentType->value,
            ];

            // Act
            $response = $this->post(route('Store Booking Payment', [
                'band' => $this->band,
                'booking' => $this->booking
            ]), $paymentData);

            // Assert
            $response->assertRedirect();
            $response->assertSessionHas('successMessage', 'Payment has been added.');

            // Assert payment was stored with correct payment type
            $this->assertDatabaseHas('payments', [
                'name' => 'Test payment - ' . $paymentType->label(),
                'band_id' => $this->band->id,
                'user_id' => $this->user->id,
                'payment_type' => $paymentType->value,
                'payer_type' => 'App\\Models\\User',
                'payer_id' => $this->user->id,
            ]);

            // Assert notifications were sent
            Notification::assertSentTo([$this->contact], PaymentReceived::class);
            Notification::assertSentTo([$this->user], BandPaymentReceived::class);
        }
    }
}
