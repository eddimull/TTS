<?php

namespace App\Rules;

use Closure;
use App\Models\Bands;
use Illuminate\Contracts\Validation\ValidationRule;

class PartOfBand implements ValidationRule
{
    protected $band;

    public function __construct(Bands $band)
    {
        $this->band = $band;
    }

    public function passes($attribute, $value): bool
    {
        // Check if the user is part of the band
        return $this->band->members()->where('user_id', $value)->exists() ||
               $this->band->owners()->where('user_id', $value)->exists();
    }
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$this->passes($attribute, $value)) {
            $fail('The selected user is not a member or owner of the band.');
        }
    }
}
