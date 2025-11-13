<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\Contracts;
use App\Models\Payments;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BookingPaymentReminderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Bands $band;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->band = Bands::factory()->create();
        $this->band->owners()->create(['user_id' => $this->user->id]);
    }

    public function test_calculates_expected_deposit_amount(): void
    {
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 1000.00, // $1000
        ]);

        $this->assertEquals('500.00', $booking->expected_deposit_amount);
    }

    public function test_checks_if_deposit_is_paid(): void
    {
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 1000.00,
        ]);

        // No payments made
        $this->assertFalse($booking->is_deposit_paid);

        // Partial payment (less than deposit)
        $booking->payments()->create([
            'name' => 'Initial Payment',
            'band_id' => $this->band->id,
            'amount' => 200, // $200 (Price cast will convert to cents)
            'date' => now(),
            'status' => 'paid',
        ]);
        $this->assertFalse($booking->fresh()->is_deposit_paid);

        // Exactly deposit amount
        $booking->payments()->create([
            'name' => 'Additional Payment',
            'band_id' => $this->band->id,
            'amount' => 300, // $300 (total $500)
            'date' => now(),
            'status' => 'paid',
        ]);
        $this->assertTrue($booking->fresh()->is_deposit_paid);

        // More than deposit
        $booking->payments()->create([
            'name' => 'Extra Payment',
            'band_id' => $this->band->id,
            'amount' => 100, // $100 (total $600)
            'date' => now(),
            'status' => 'paid',
        ]);
        $this->assertTrue($booking->fresh()->is_deposit_paid);
    }

    public function test_calculates_deposit_due(): void
    {
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 1000.00,
        ]);

        // No payments
        $this->assertEquals('500.00', $booking->deposit_due);

        // Partial payment
        $booking->payments()->create([
            'name' => 'Deposit Payment',
            'band_id' => $this->band->id,
            'amount' => 200, // $200
            'date' => now(),
            'status' => 'paid',
        ]);
        $this->assertEquals('300.00', $booking->fresh()->deposit_due);

        // Fully paid deposit
        $booking->payments()->create([
            'name' => 'Final Deposit Payment',
            'band_id' => $this->band->id,
            'amount' => 300, // $300 more
            'date' => now(),
            'status' => 'paid',
        ]);
        $this->assertEquals('0.00', $booking->fresh()->deposit_due);
    }

    public function test_gets_contract_signed_date(): void
    {
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
        ]);

        // No contract
        $this->assertNull($booking->contract_signed_date);

        // Contract pending
        $contract = $booking->contract()->create([
            'status' => 'pending',
            'author_id' => $this->user->id,
        ]);
        $this->assertNull($booking->fresh()->contract_signed_date);

        // Contract completed - use DB update to bypass timestamps
        \DB::table('contracts')
            ->where('id', $contract->id)
            ->update([
                'status' => 'completed',
                'updated_at' => Carbon::parse('2025-01-01 10:00:00'),
            ]);

        $booking = $booking->fresh();
        $this->assertNotNull($booking->contract_signed_date);
        $this->assertEquals('2025-01-01', $booking->contract_signed_date->format('Y-m-d'));
    }

    public function test_calculates_deposit_due_date(): void
    {
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
        ]);

        $signedDate = Carbon::parse('2025-01-01 10:00:00');
        $contract = $booking->contract()->create([
            'status' => 'completed',
            'author_id' => $this->user->id,
        ]);

        // Use DB update to set specific timestamp
        \DB::table('contracts')
            ->where('id', $contract->id)
            ->update(['updated_at' => $signedDate]);

        $expectedDueDate = $signedDate->copy()->addWeeks(3);
        $this->assertEquals($expectedDueDate->format('Y-m-d'), $booking->fresh()->deposit_due_date->format('Y-m-d'));
    }

    public function test_needs_deposit_reminder_when_due_date_is_today(): void
    {
        $signedDate = now()->subWeeks(3); // 3 weeks ago
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 1000.00,
            'date' => now()->addMonth(), // Future event
        ]);

        $contract = $booking->contract()->create([
            'status' => 'completed',
            'author_id' => $this->user->id,
        ]);

        \DB::table('contracts')
            ->where('id', $contract->id)
            ->update(['updated_at' => $signedDate]);

        $this->assertTrue($booking->fresh()->needs_deposit_reminder);
    }

    public function test_does_not_need_deposit_reminder_when_deposit_is_paid(): void
    {
        $signedDate = now()->subWeeks(3);
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 1000.00,
            'date' => now()->addMonth(),
        ]);

        $contract = $booking->contract()->create([
            'status' => 'completed',
            'author_id' => $this->user->id,
        ]);

        \DB::table('contracts')
            ->where('id', $contract->id)
            ->update(['updated_at' => $signedDate]);

        // Pay deposit
        $booking->payments()->create([
            'name' => 'Deposit',
            'band_id' => $this->band->id,
            'amount' => 500, // $500
            'date' => now(),
            'status' => 'paid',
        ]);

        $this->assertFalse($booking->fresh()->needs_deposit_reminder);
    }

    public function test_does_not_need_deposit_reminder_when_event_is_past(): void
    {
        $signedDate = now()->subWeeks(3);
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 1000.00,
            'date' => now()->subDay(), // Past event
        ]);

        $contract = $booking->contract()->create([
            'status' => 'completed',
            'author_id' => $this->user->id,
        ]);

        \DB::table('contracts')
            ->where('id', $contract->id)
            ->update(['updated_at' => $signedDate]);

        $this->assertFalse($booking->fresh()->needs_deposit_reminder);
    }

    public function test_needs_final_payment_reminder_when_event_is_7_days_away(): void
    {
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 1000.00,
            'date' => now()->addDays(7), // Exactly 7 days away
        ]);

        // Not fully paid
        $booking->payments()->create([
            'name' => 'Partial Payment',
            'band_id' => $this->band->id,
            'amount' => 500, // Only $500 paid
            'date' => now(),
            'status' => 'paid',
        ]);

        $this->assertTrue($booking->fresh()->needs_final_payment_reminder);
    }

    public function test_does_not_need_final_payment_reminder_when_fully_paid(): void
    {
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 1000.00,
            'date' => now()->addDays(7),
        ]);

        // Fully paid
        $booking->payments()->create([
            'name' => 'Full Payment',
            'band_id' => $this->band->id,
            'amount' => 1000, // Full $1000
            'date' => now(),
            'status' => 'paid',
        ]);

        $this->assertFalse($booking->fresh()->needs_final_payment_reminder);
    }

    public function test_does_not_need_final_payment_reminder_when_event_is_too_far(): void
    {
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 1000.00,
            'date' => now()->addDays(14), // 2 weeks away
        ]);

        $booking->payments()->create([
            'name' => 'Partial Payment',
            'band_id' => $this->band->id,
            'amount' => 500, // Only $500 paid
            'date' => now(),
            'status' => 'paid',
        ]);

        $this->assertFalse($booking->fresh()->needs_final_payment_reminder);
    }

    public function test_does_not_need_final_payment_reminder_when_event_is_past(): void
    {
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 1000.00,
            'date' => now()->subDay(), // Past event
        ]);

        $booking->payments()->create([
            'name' => 'Partial Payment',
            'band_id' => $this->band->id,
            'amount' => 500, // Only $500 paid
            'date' => now(),
            'status' => 'paid',
        ]);

        $this->assertFalse($booking->fresh()->needs_final_payment_reminder);
    }

    public function test_pending_payments_do_not_count_toward_deposit(): void
    {
        $booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'price' => 1000.00,
        ]);

        // Create pending payment
        $booking->payments()->create([
            'name' => 'Pending Invoice',
            'band_id' => $this->band->id,
            'amount' => 500, // $500
            'date' => now(),
            'status' => 'pending', // Not paid yet
        ]);

        $this->assertFalse($booking->fresh()->is_deposit_paid);
        $this->assertEquals('500.00', $booking->fresh()->deposit_due);
    }
}
