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
        if ($this->booking === null) {
            return;
        }
        if ($this->booking->contract_signed_date !== null) {
            $fail('Deposit is locked because the contract is signed.');
        }
    }
}
