<?php

namespace Tests\Feature;

use Tests\TestCase;

use App\Models\User;
use App\Models\Bands;

use App\Models\Bookings;
use App\Models\Contacts;

use App\Notifications\PaymentReceived;
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

    public function it_can_store_a_payment_and_sends_notification()
    {

        // Arrange
        $this->actingAs($this->user);

        $paymentData = [
            'date' => now()->format('Y-m-d'),
            'amount' => 100,
            'name' => 'Test payment'
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
            'user_id' => $this->user->id
        ]);



        // Assert notification was sent
        Notification::assertSentTo(
            [$this->contact],
            PaymentReceived::class
        );
    }

    public function it_validates_required_fields()
    {
        // Arrange
        $this->actingAs($this->user);

        // Act
        $response = $this->post(route('Store Booking Payment', [
            'band' => $this->band,
            'booking' => $this->booking
        ]), []);

        // Assert
        $response->assertSessionHasErrors(['name', 'amount', 'date']);

        // Assert no notification was sent
        Notification::assertNothingSent();
    }


    public function it_validates_amount_is_numeric()
    {
        // Arrange
        $this->actingAs($this->user);

        $paymentData = [
            'amount' => 'not-a-number',
            'date' => now()->format('Y-m-d'),
            'name' => 'Invalidated amount'
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


    public function unauthorized_users_cannot_create_payments()
    {
        // Act
        $response = $this->post(route('Store Booking Payment', [
            'band' => $this->band,
            'booking' => $this->booking
        ]), [
            'amount' => 100.00,
            'date' => now()->format('Y-m-d'),
            'name' => 'bank_transfer'
        ]);

        // Assert
        $response->assertRedirect(route('login'));

        // Assert no notification was sent
        Notification::assertNothingSent();
    }


    public function it_queues_notification_for_sending()
    {
        // Arrange
        $this->actingAs($this->user);

        $paymentData = [
            'amount' => 100.00,
            'date' => now()->format('Y-m-d'),
            'name' => 'Test queue notification'
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
}
