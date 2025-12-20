<?php

namespace App\Http\Requests\Media;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UploadMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $bandId = $this->input('band_id');
        return Auth::user()->canWrite('media', $bandId);
    }

    public function rules(): array
    {
        return [
            'band_id' => 'required|exists:bands,id',
            'files' => 'required|array|min:1',
            'files.*' => 'required|file|max:512000', // 500MB
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'folder_path' => 'nullable|string|max:255',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:media_tags,id',
            'booking_id' => 'nullable|exists:bookings,id',
            'event_id' => 'nullable|exists:events,id',
        ];
    }

    public function messages(): array
    {
        return [
            'files.required' => 'Please select at least one file to upload.',
            'files.*.max' => 'Each file must not exceed 500MB.',
            'band_id.required' => 'Band selection is required.',
        ];
    }
}
