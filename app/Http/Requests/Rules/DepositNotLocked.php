<?php

namespace App\Http\Requests\Rules;

use App\Models\Bookings;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class DepositNotLocked implements ValidationRule
{
    public function __construct(private ?Bookings $booking) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->booking === null || $this->booking->contract_signed_date === null) {
            return;
        }

        // The booking form resubmits deposit fields even when untouched, so
        // lock on change, not on presence — otherwise every post-signing save
        // of any booking field is rejected.
        $current = $this->booking->getAttribute($attribute);
        $unchanged = match (true) {
            $current === null && $value === null => true,
            is_numeric($current) && is_numeric($value) => (float) $current === (float) $value,
            default => (string) $current === (string) $value,
        };

        if (!$unchanged) {
            $fail('Deposit is locked because the contract is signed.');
        }
    }
}
