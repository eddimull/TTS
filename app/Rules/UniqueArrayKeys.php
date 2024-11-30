<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueArrayKeys implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  \Closure  $fail
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_array($value))
        {
            $fail('The :attribute must be an array.');
            return;
        }

        $keys = array_map(function ($key)
        {
            return strtolower(str_replace(['_loadin_time', '_time'], '', $key));
        }, array_keys($value));

        if (count($keys) !== count(array_unique($keys)))
        {
            $fail('The :attribute must have unique keys (ignoring _loadin_time and _time suffixes).');
        }
    }
}
