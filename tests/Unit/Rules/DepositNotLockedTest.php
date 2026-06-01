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
        $booking = Bookings::factory()->create();
        Contracts::factory()->create([
            'contractable_id'   => $booking->id,
            'contractable_type' => Bookings::class,
            'status'            => 'completed',
        ]);
        $booking->load('contract');
        $rule = new DepositNotLocked($booking);
        $message = null;
        $rule->validate('deposit_type', 'amount', function ($m) use (&$message) {
            $message = $m;
        });
        $this->assertNotNull($message);
        $this->assertStringContainsString('locked', strtolower($message));
    }
}
