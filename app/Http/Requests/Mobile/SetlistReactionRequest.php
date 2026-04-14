<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class SetlistReactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth handled by middleware (auth:sanctum + mobile.band)
    }

    public function rules(): array
    {
        return [
            'queue_entry_id' => 'required|integer|exists:live_setlist_queue,id',
            'reaction'       => 'required|in:positive,negative,neutral',
        ];
    }
}
