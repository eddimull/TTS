<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class ReorderQueueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by middleware (auth:sanctum + mobile.band); captain check in the controller
    }

    public function rules(): array
    {
        return [
            // Every upcoming entry, in its new order.
            'entry_ids'   => 'required|array|min:1',
            'entry_ids.*' => 'integer|distinct',
        ];
    }
}
