<?php

namespace Tests\Unit\Rules;

use App\Http\Requests\Rules\DepositNotLocked;
use App\Models\Bookings;
use App\Models\Contracts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepositNotLockedTest extends TestCase
{
    use RefreshDatabase;

    public function test_rule_passes_when_booking_has_no_contract(): void
    {
        $booking = Bookings::factory()->create();
        $rule = new DepositNotLocked($booking);
        $failed = false;
        $rule->validate('deposit_type', 'amount', function () use (&$failed) {
            $failed = true;
        });
        $this->assertFalse($failed);
    }

    public function test_rule_passes_when_contract_is_unsigned(): void
    {
        $booking = Bookings::factory()->create();
        Contracts::factory()->create([
            'contractable_id'   => $booking->id,
            'contractable_type' => Bookings::class,
            'status'            => 'pending',
        ]);
        $booking->load('contract');
        $rule = new DepositNotLocked($booking);
        $failed = false;
        $rule->validate('deposit_type', 'amount', function () use (&$failed) {
            $failed = true;
        });
        $this->assertFalse($failed);
    }

    public function test_rule_fails_when_contract_is_signed(): void
    {
        // Factory default deposit_type is 'percent', so 'amount' is a change.
        $booking = $this->signedBooking();
        $rule = new DepositNotLocked($booking);
        $message = null;
        $rule->validate('deposit_type', 'amount', function ($m) use (&$message) {
            $message = $m;
        });
        $this->assertNotNull($message);
        $this->assertStringContainsString('locked', strtolower($message));
    }

    public function test_rule_passes_when_signed_but_deposit_type_unchanged(): void
    {
        $booking = $this->signedBooking(); // deposit_type 'percent'
        $rule = new DepositNotLocked($booking);
        $failed = false;
        $rule->validate('deposit_type', 'percent', function () use (&$failed) {
            $failed = true;
        });
        $this->assertFalse($failed);
    }

    public function test_rule_passes_when_signed_but_deposit_value_unchanged(): void
    {
        $booking = $this->signedBooking(); // deposit_value '50.00'
        $rule = new DepositNotLocked($booking);
        $failed = false;
        $rule->validate('deposit_value', '50.00', function () use (&$failed) {
            $failed = true;
        });
        $this->assertFalse($failed);
    }

    public function test_rule_treats_numerically_equal_deposit_value_as_unchanged(): void
    {
        $booking = $this->signedBooking(); // deposit_value '50.00'
        $rule = new DepositNotLocked($booking);
        $failed = false;
        $rule->validate('deposit_value', '50', function () use (&$failed) {
            $failed = true;
        });
        $this->assertFalse($failed);
    }

    public function test_rule_fails_when_signed_and_deposit_value_changed(): void
    {
        $booking = $this->signedBooking(); // deposit_value '50.00'
        $rule = new DepositNotLocked($booking);
        $message = null;
        $rule->validate('deposit_value', '75.00', function ($m) use (&$message) {
            $message = $m;
        });
        $this->assertNotNull($message);
        $this->assertStringContainsString('locked', strtolower($message));
    }

    private function signedBooking(): Bookings
    {
        $booking = Bookings::factory()->create([
            'deposit_type'  => 'percent',
            'deposit_value' => '50.00',
        ]);
        Contracts::factory()->create([
            'contractable_id'   => $booking->id,
            'contractable_type' => Bookings::class,
            'status'            => 'completed',
        ]);
        return $booking->load('contract');
    }
}
